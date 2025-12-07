UPDATE "Account"
   SET "password" = encode(sha512(CAST(CONCAT('[SHA_SALT]', '[PASSWORD]') AS bytea)), 'hex')
 WHERE "is_deleted" = false and "guid" = '[ACCOUNT_GUID]' and "id" = [ACCOUNT_ID]
 RETURNING "id" as "account_id", "guid" as "account_guid", ROUND(EXTRACT(EPOCH FROM "updated_at")) as "updated_unix";