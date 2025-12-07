SELECT mm."account_id", ROUND(EXTRACT(EPOCH FROM acct."updated_at")) as "version", mm."key", mm."value"
  FROM Account acct INNER JOIN AccountMeta mm ON acct."id" = mm."account_id"
 WHERE acct."is_deleted" = false and mm."is_deleted" = false and acct."id" = [ACCOUNT_ID]
 ORDER BY mm."key";