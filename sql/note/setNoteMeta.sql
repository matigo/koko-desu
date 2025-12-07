INSERT INTO NoteMeta ("note_id", "key", "value")
SELECT nn."id", LOWER('[KEY]') as "key", TRIM(E'[VALUE]') as "value"
  FROM Note nn
 WHERE nn."is_deleted" = false and nn."id" = [NOTE_ID]
    ON CONFLICT ("note_id", "key") DO UPDATE
       SET "value" = TRIM(EXCLUDED."value"),
           "is_deleted" = CASE WHEN LENGTH(TRIM(EXCLUDED."value")) > 0 THEN false ELSE true END
 RETURNING ROUND(EXTRACT(EPOCH FROM "updated_at")) as "version";