DROP FUNCTION account_create;
CREATE OR REPLACE FUNCTION account_create( "in_nickname" character varying, "in_email" character varying, "in_displayname" character varying,
                                           "in_password" character varying, "sha_hash" character varying, "in_gender" character varying,
                                           "in_lastname" character varying, "in_firstname" character varying,
                                           "in_locale" character varying, "in_timezone" character varying, "in_type" character varying,
                                       OUT "out_account_id" integer, OUT "out_account_guid" character varying )
    LANGUAGE plpgsql AS $$

    BEGIN
        /* Perform some Basic Validation */
        IF LENGTH("in_nickname") < 3 THEN
            RAISE EXCEPTION 'Supplied Name is Too Short: %', LENGTH("in_nickname");
        END IF;

        IF LENGTH("in_password") < 8 THEN
            RAISE EXCEPTION 'Supplied Password is Too Short: %', LENGTH("in_password");
        END IF;

        IF LENGTH("in_email") > 0 AND LENGTH("in_email") < 6 THEN
            RAISE EXCEPTION 'Supplied Email is Too Short: %', LENGTH("in_email");
        END IF;

        /* Check to see if an account that matches some attributes is already registered */
        SELECT acct."id", acct."guid" INTO "out_account_id", "out_account_guid"
          FROM "Account" acct
         WHERE acct."is_deleted" = false
           and 'Y' = CASE WHEN acct."login" = LOWER("in_nickname") THEN 'Y'
                          WHEN LENGTH("in_email") > 5 AND acct."email" = LOWER("in_email") THEN 'Y'
                          ELSE 'N' END
         ORDER BY acct."id", CASE WHEN acct."type" IN ('account.expired') THEN 99 ELSE 1 END, acct."is_deleted" DESC
         LIMIT 1;

        /* If there is already an Account associated, ensure it is active (if de-activated) */
        IF COALESCE("out_account_id", 0) > 0 THEN
            UPDATE "Account"
               SET "type" = "in_type",
                   "is_deleted" = false
             WHERE "type" IN ('account.expired') and "id" = "out_account_id";
        END IF;

        /* Create the Account */
        IF COALESCE("out_account_id", 0) <= 0 THEN
            INSERT INTO "Account" ("login", "password", "display_name", "last_name", "first_name", "email", "gender", "locale_code", "timezone", "type")
            SELECT LOWER(TRIM(LEFT("in_nickname", 64))) as "login", encode(sha512(CAST(CONCAT("sha_hash", "in_password") AS bytea)), 'hex') as "password",
                   TRIM(LEFT(CASE WHEN "in_displayname" <> '' THEN "in_displayname" ELSE "in_firstname" END, 80)) as "display_name",

                   TRIM(LEFT("in_lastname", 120)) as "last_name", TRIM(LEFT("in_firstname", 120)) as "first_name",
                   TRIM(LEFT(LOWER("in_email"), 160)) as "email",
                   CASE WHEN UPPER("in_gender") IN ('F','G') THEN 'F' ELSE 'M' END as "gender",

                   (SELECT z."code" FROM "Locale" z WHERE z."is_deleted" = false
                     ORDER BY CASE WHEN z."code" = "in_locale" THEN 0 ELSE 1 END,
                              CASE WHEN z."language_code" = LEFT("in_locale", 2) THEN 0 ELSE 1 END,
                              z."is_available" DESC, z."is_default" DESC
                     LIMIT 1) as "locale_code",
                   "in_timezone" as timezone,
                   LOWER(CASE WHEN "in_type" LIKE 'account.%' THEN "in_type" ELSE 'account.basic' END)::varchar(64) as "type"
            RETURNING "id", "guid" INTO "out_account_id", "out_account_guid";

            /* Add the Initial Account Meta data */
            IF COALESCE("out_account_id", 0) > 0 THEN
                INSERT INTO "AccountMeta" ("account_id", "key", "value")
                VALUES ("out_account_id", 'profile.avatar', 'default.png'),
                       ("out_account_id", 'profile.bio', ''),
                       ("out_account_id", 'profile.location', ''),
                       ("out_account_id", 'preference.can_email', 'N'),
                       ("out_account_id", 'preference.fontfamily', 'auto'),
                       ("out_account_id", 'preference.fontsize', 'md'),
                       ("out_account_id", 'preference.theme', 'default');
            END IF;
        END IF;
    END
    $$;