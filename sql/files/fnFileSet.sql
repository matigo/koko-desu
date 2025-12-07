DROP FUNCTION file_set;
CREATE OR REPLACE FUNCTION file_set( "in_account_id" integer, "in_fileguid" character varying,
                                     "in_localname" character varying, "in_filename" character varying, "in_path" character varying,
                                     "in_bytes" integer, "in_mimetype" character varying, "in_expiresat" character varying,
                                     "in_access" character varying, "in_filehash" character varying,
                                 OUT "out_file_id" integer, OUT "out_file_guid" character varying, OUT "out_file_version" integer )
    LANGUAGE plpgsql AS $$

    DECLARE xCanEdit    boolean;

    BEGIN
        /* Perform some Basic Validation */
        IF LENGTH("in_localname") < 3 THEN
            RAISE EXCEPTION 'Invalid File.name Provided: %', LENGTH("in_localname");
        END IF;
        IF LENGTH("in_filename") < 3 THEN
            RAISE EXCEPTION 'Invalid File.public_name Provided: %', LENGTH("in_filename");
        END IF;
        IF LENGTH("in_mimetype") < 3 THEN
            RAISE EXCEPTION 'Invalid File.mimetype Provided: %', LENGTH("in_mimetype");
        END IF;
        IF LENGTH("in_fileguid") > 0 AND LENGTH("in_fileguid") <> 36 THEN
            RAISE EXCEPTION 'Invalid File.guid Provided: %', LENGTH("in_fileguid");
        END IF;
        IF "in_bytes" < 1 THEN
            RAISE EXCEPTION 'Supplied Byte count is too small: %', "in_bytes";
        END IF;

        /* Verify the current account has permission to work with Files (recording or editing) */
        SELECT CASE WHEN acct."type" IN ('account.global', 'account.admin') THEN true
                    WHEN fi."id" IS NOT NULL THEN true
                    WHEN LENGTH("in_fileguid") <= 0 THEN true
                    ELSE false END INTO xCanEdit
          FROM Account acct LEFT OUTER JOIN File fi ON acct."id" = fi."account_id" AND fi."is_deleted" = false AND fi."guid" = "in_fileguid"
                                                   AND COALESCE(fi."expires_at", CURRENT_TIMESTAMP + INTERVAL '30 Days') >= CURRENT_TIMESTAMP
         WHERE acct."is_deleted" = false and acct."id" = "in_account_id"
         LIMIT 1;

        /* If we cannot continue, then there's no point doing anything else */
        IF xCanEdit <> true THEN
            RAISE EXCEPTION 'Invalid Permissions for File(s)';
        END IF;

        /* Write (or edit) the File record */
        IF LENGTH("in_fileguid") <> 36 THEN
            INSERT INTO File ("account_id", "name", "public_name", "location", "bytes", "mimetype", "expires_at", "is_readonly", "is_public", "hash")
            SELECT acct."id" as "account_id", TRIM(LEFT("in_localname", 64)) as "name", TRIM(LEFT("in_filename", 256)) as "public_name", TRIM(LEFT("in_path", 256)) as "location",
                   CASE WHEN "in_bytes" > 0 THEN "in_bytes" ELSE 0 END as "bytes", LOWER(TRIM(LEFT("in_mimetype", 64))) as "mimetype",
                   CASE WHEN LENGTH("in_expiresat") >= 10 THEN "in_expiresat"::timestamp ELSE NULL END as "expires_at",
                   CASE WHEN LOWER("in_access") IN ('public-read-write') THEN false ELSE true END as "is_readonly",
                   CASE WHEN LOWER("in_access") IN ('private') THEN false ELSE true END as "is_public",
                   "in_filehash" as "hash"
              FROM Account acct
             WHERE acct."is_deleted" = false and acct."id" = "in_account_id"
                   RETURNING "id", "guid", ROUND(EXTRACT(EPOCH FROM "updated_at")) INTO "out_file_id", "out_file_guid", "out_file_version";

        ELSE
            UPDATE File
               SET "public_name" = TRIM(LEFT("in_filename", 256)),
                   "mimetype" = LOWER(TRIM(LEFT("in_mimetype", 64))),
                   "expires_at" = CASE WHEN LENGTH("in_expiresat") >= 10 THEN "in_expiresat"::timestamp ELSE NULL END,
                   "is_readonly" = CASE WHEN LOWER("in_access") IN ('public-read-write') THEN false ELSE true END,
                   "is_public" = CASE WHEN LOWER("in_access") IN ('private') THEN false ELSE true END
             WHERE "is_deleted" = false and "guid" = "in_fileguid"
                   RETURNING "id", "guid", ROUND(EXTRACT(EPOCH FROM "updated_at")) INTO "out_file_id", "out_file_guid", "out_file_version";
        END IF;

    END
    $$;