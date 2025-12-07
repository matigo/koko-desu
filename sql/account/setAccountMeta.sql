INSERT INTO "AccountMeta" ("account_id", "key", "value")
SELECT acct."id", LOWER('[KEY]') as "key", TRIM('[VALUE]') as "value"
  FROM "Account" acct
 WHERE acct."is_deleted" = false and acct."id" = [ACCOUNT_ID]
    ON CONFLICT ("account_id", "key") DO UPDATE
       SET "value" = EXCLUDED."value",
           "is_deleted" = false
 RETURNING "key", "value", "created_at", "updated_at";