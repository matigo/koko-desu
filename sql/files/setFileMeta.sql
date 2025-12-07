INSERT INTO FileMeta ("file_id", "key", "value")
SELECT fi."id" as "file_id", LOWER('[KEY]') as "key", '[VALUE]' as "value"
  FROM "File" fi
 WHERE fi."is_deleted" = false and fi."id" = [FILE_ID]
    ON CONFLICT ("file_id", "key") DO UPDATE 
       SET "value" = TRIM(EXCLUDED."value"),
           "is_deleted" = CASE WHEN LENGTH(TRIM(EXCLUDED."value")) > 0 THEN false ELSE true END
 RETURNING ROUND(EXTRACT(EPOCH FROM "updated_at")) as "version";