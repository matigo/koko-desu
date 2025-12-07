DROP FUNCTION token_chk;
CREATE OR REPLACE FUNCTION token_chk( "in_token_id" integer, "in_token_guid" character varying, "in_lifespan" integer )
    RETURNS TABLE( "account_id"         integer,
                   "account_guid"       char(36),
                   "email"              varchar(160),
                   "type"               varchar(64),

                   "display_name"       varchar(80),
                   "last_name"          varchar(120),
                   "first_name"         varchar(120),

                   "version"            integer,
                   "locale_code"        varchar(6),
                   "timezone"           varchar(40),
                   "avatar"             varchar(2048),
                   "models"             varchar(2048),

                   "pref_fontfamily"    varchar(64),
                   "pref_fontsize"      varchar(64),
                   "pref_colour"        varchar(64),
                   "pref_canemail"      boolean,

                   "token_id"           integer,
                   "token_guid"         varchar(128),
                   "login_at"           timestamp
       )
    LANGUAGE plpgsql AS $$

    DECLARE xAccountID  integer;
    DECLARE xTokenID    integer;
    DECLARE xTokenGuid  varchar(128);

    BEGIN
        /* Perform some Basic Validation */
        IF LENGTH("in_token_guid") < 36 THEN
            RAISE EXCEPTION 'Invalid Token Guid Supplied: %', LENGTH("in_token_guid");
        END IF;

        IF COALESCE("in_token_id", 0) <= 0 THEN
            RAISE EXCEPTION 'Invalid Token ID Supplied: %', "in_token_id";
        END IF;

        IF COALESCE("in_lifespan", 0) <= 0 THEN
            RAISE EXCEPTION 'Invalid Token Lifespan Supplied: %', "in_lifespan";
        END IF;

        /* Validate the Token.id and Token.guid values */
        SELECT tt."id", tt."guid", tt."account_id" INTO xTokenID, xTokenGuid, xAccountID
          FROM "Account" acct INNER JOIN "Tokens" tt ON acct."id" = tt."account_id"
         WHERE acct."is_deleted" = false and tt."is_deleted" = false and tt."guid" = "in_token_guid" and tt."id" = "in_token_id"
           and tt."updated_at" >= CURRENT_TIMESTAMP - ("in_lifespan" * INTERVAL '1 DAY');

        /* Return the Token Data */
        RETURN QUERY
        SELECT acct."id" as "account_id", acct."guid" as "account_guid", acct."email", acct."type",
               acct."display_name", acct."last_name", acct."first_name",
               ROUND(EXTRACT(EPOCH FROM acct."updated_at"))::integer as "version", acct."locale_code", acct."timezone",
               COALESCE(meta."avatar", 'default.png')::varchar(2048) as "avatar",

               CASE WHEN acct."type" IN ('account.global', 'account.admin') THEN 'basic|pro|expert|all'
                    WHEN acct."type" IN ('account.expert', 'account.education') THEN 'basic|pro|expert'
                    WHEN acct."type" IN ('account.standard', 'account.pro') THEN 'basic|pro'
                    ELSE 'basic' END::varchar(2048) as "models",

               meta."pref_fontfamily"::varchar(64) as "pref_fontfamily",
               meta."pref_fontsize"::varchar(64) as "pref_fontsize",
               meta."pref_colour"::varchar(64) as "pref_colour",
               CASE WHEN UPPER(meta."pref_canemail") IN ('Y','T','1') THEN true ELSE false END as "pref_canemail",

               tt."id" as "token_id", tt."guid" as "token_guid", tt."created_at" as "login_at"
          FROM "Account" acct INNER JOIN "Tokens" tt ON acct."id" = tt."account_id"
                         LEFT OUTER JOIN (SELECT tt."account_id",
                                                 MAX(CASE WHEN am."key" = 'profile.avatar' THEN am."value" ELSE NULL END) as "avatar",
                                                 MAX(CASE WHEN am."key" = 'preference.fontfamily' THEN am."value" ELSE NULL END) "pref_fontfamily",
                                                 MAX(CASE WHEN am."key" = 'preference.fontsize' THEN am."value" ELSE NULL END) "pref_fontsize",
                                                 MAX(CASE WHEN am."key" = 'preference.theme' THEN am."value" ELSE NULL END) "pref_colour",
                                                 MAX(CASE WHEN am."key" = 'preference.can_email' THEN am."value" ELSE NULL END) "pref_canemail",
                                                 MAX(CASE WHEN am."key" = 'permission.can_admin' THEN am."value" ELSE NULL END) "can_admin"
                                            FROM "Tokens" tt LEFT OUTER JOIN "AccountMeta" am ON tt."account_id" = am."account_id" AND am."is_deleted" = false
                                           WHERE tt."is_deleted" = false and tt."id" = COALESCE(xTokenID, 0)
                                           GROUP BY tt."account_id" LIMIT 1) meta ON tt."account_id" = meta."account_id"
         WHERE acct."is_deleted" = false and tt."is_deleted" = false and tt."guid" = COALESCE(xTokenGuid, '') and tt."id" = COALESCE(xTokenID, 0)
         LIMIT 1;

         /* Update the Token Record's UpdatedAt stamp (This is done after the lookup on purpose) */
         IF COALESCE(xTokenID, 0) > 0 AND LENGTH(COALESCE(xTokenGuid, '')) >= 36 THEN
            UPDATE "Tokens"
               SET "updated_at" = CURRENT_TIMESTAMP
             WHERE "is_deleted" = false and "guid" = COALESCE(xTokenGuid, '') and "id" = COALESCE(xTokenID, 0);
         END IF;

    END
    $$;