INSERT INTO "Contact" ("school_id", "name", "mail", "subject", "message", "hash")
SELECT (SELECT z."id" FROM "School" z WHERE z."is_deleted" = false and z."guid" = '' ORDER BY z."id" LIMIT 1) as "school_id",
       CASE WHEN LENGTH('[NAME]') > 0 THEN LEFT(E'[NAME]', 512) ELSE NULL END as "name",
       CASE WHEN LENGTH('[MAIL]') > 0 THEN LOWER(LEFT(E'[MAIL]', 160)) ELSE NULL END as "mail",
       CASE WHEN LENGTH('[SUBJECT]') > 0 THEN LEFT(E'[SUBJECT]', 512) ELSE NULL END as "subject",
       E'[MESSAGE]' as "message", encode(sha512(CAST(REPLACE(E'[MESSAGE]', '\', '\\') AS bytea)), 'hex') as "hash"
 LIMIT 1
 RETURNING "id", "guid", "hash", "name", "subject", "message",
           "created_at", ROUND(EXTRACT(EPOCH FROM "created_at")) as "created_unix",
           "updated_at", ROUND(EXTRACT(EPOCH FROM "updated_at")) as "updated_unix";