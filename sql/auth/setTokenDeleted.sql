UPDATE "Tokens" tt
   SET "is_deleted" = true
 WHERE tt."is_deleted" = false and tt."id" = [TOKEN_ID] and tt."guid" = '[TOKEN_GUID]'
 RETURNING tt."is_deleted", tt."updated_at";