CREATE OR REPLACE FUNCTION auth_local_login( "in_lookup" character varying, "in_password" character varying, "sha_hash" character varying,
                                             OUT "token_id" integer, OUT "token_guid" character varying, OUT "account_id" integer )
    LANGUAGE plpgsql AS $$

    BEGIN
        /* Perform some Basic Validation */
        IF LENGTH("in_lookup") < 3 THEN
            RAISE EXCEPTION 'Supplied Lookup is Too Short: %', LENGTH("in_lookup");
        END IF;

        IF LENGTH("in_password") < 8 THEN
            RAISE EXCEPTION 'Supplied Password is Too Short: %', LENGTH("in_password");
        END IF;

        /* Check to see if the Email address is already registered */
        SELECT acct."id" INTO "account_id"
          FROM "Account" acct
         WHERE acct."is_deleted" = false and acct."type" NOT IN ('account.expired')
           and acct."password" = encode(sha512(CAST(CONCAT("sha_hash", "in_password") AS bytea)), 'hex')
           and acct."email" = LOWER("in_lookup")
         ORDER BY acct."id" LIMIT 1;

        /* Create the Token Record */
        IF COALESCE("account_id", 0) > 0 THEN
            INSERT INTO "Tokens" ("guid", "account_id")
            SELECT CONCAT(uuid_generate_v4(), '-', LEFT(MD5(acct."email"), 4), '-', LEFT(MD5(acct."guid"), 8)) as "guid", acct."id"
              FROM "Account" acct
             WHERE acct."is_deleted" = false and acct."type" NOT IN ('account.expired') and acct."id" = "account_id"
            RETURNING "id", "guid" INTO "token_id", "token_guid";
        END IF;
    END
    $$;