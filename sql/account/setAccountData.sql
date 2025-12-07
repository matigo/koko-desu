UPDATE "Account"
   SET "display_name" = E'[DISPLAY_AS]',
       "last_name" = E'[LAST_NAME]',
       "first_name" = E'[FIRST_NAME]',
       "gender" = CASE WHEN UPPER('[GENDER]') IN ('F','G') THEN 'F' ELSE 'M' END,
       "email" = LOWER(E'[EMAIL]'),
       "locale_code" = '[LOCALE]',
       "timezone" = '[TIMEZONE]'
 WHERE "is_deleted" = false and "guid" = '[ACCOUNT_GUID]' and "id" = [ACCOUNT_ID]
 RETURNING "id" as "account_id", "guid" as "account_guid";