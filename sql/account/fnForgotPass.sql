CREATE OR REPLACE FUNCTION acct_forgotpass( "in_lookup" character varying,
                                            OUT "token_id" integer, OUT "token_guid" character varying, OUT "account_id" integer,
                                            OUT "out_first_name" character varying, OUT "out_display_name" character varying,
                                            OUT "out_email" character varying, OUT "out_locale_code" varchar(6), OUT "out_can_email" boolean )
    LANGUAGE plpgsql AS $$

    BEGIN
        /* Perform some Basic Validation */
        IF LENGTH("in_lookup") < 3 THEN
            RAISE EXCEPTION 'Supplied Lookup is Too Short: %', LENGTH("in_lookup");
        END IF;

        /* Check to see if the Email address is registered */
        SELECT acct."id", acct."first_name", acct."display_name", acct."email", acct."locale_code",
               CASE WHEN acct."type" IN ('account.admin') THEN true ELSE false END can_email
          INTO "account_id", "out_first_name", "out_display_name", "out_email", "out_locale_code", "out_can_email"
          FROM Account acct
         WHERE acct."is_deleted" = false and acct."type" NOT IN ('account.expired')
           and acct."email" = "in_lookup"
         ORDER BY acct."id" LIMIT 1;

        /* Create the Token Record */
        IF COALESCE("account_id", 0) > 0 THEN
            /* Create the Token Record */
            INSERT INTO Tokens ("guid", "account_id")
            SELECT CONCAT(uuid_generate_v4(), '-', LEFT(MD5(acct."email"), 4), '-', LEFT(MD5(acct."guid"), 8)) as "guid", acct."id"
              FROM Account acct 
             WHERE acct."is_deleted" = false and acct."type" NOT IN ('account.expired') and acct."id" = "account_id"
            RETURNING "id", "guid" INTO "token_id", "token_guid";
        END IF;
    END
    $$;