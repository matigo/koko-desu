SELECT acct."id" as "account_id", acct."guid" as "account_guid", ROUND(EXTRACT(EPOCH FROM acct."updated_at")) as "version",
       acct."type" as "account_type", acct."login" as "nickname", COALESCE(acct."display_name", acct."first_name") as "display_name",
       acct."last_name", acct."first_name", acct."gender", acct."email", acct."locale_code", acct."timezone",
       CASE WHEN acct."type" IN ('account.global', 'account.admin') THEN true ELSE false END as "is_admin",
       (SELECT CASE WHEN COUNT(DISTINCT z."key") > 0 THEN true ELSE false END FROM "AccountMeta" z WHERE z."is_deleted" = false and z."account_id" = acct."id") as "has_meta",
       acct."created_at", ROUND(EXTRACT(EPOCH FROM acct."created_at")) as "created_unix",
       acct."updated_at", ROUND(EXTRACT(EPOCH FROM acct."updated_at")) as "updated_unix"
  FROM "Account" acct
 WHERE acct."is_deleted" = false and acct."id" = [ACCOUNT_ID]
 LIMIT 1;