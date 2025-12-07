SELECT token_id, token_guid, account_id,
       out_first_name as first_name, out_display_name as display_name, out_email as email,
       out_locale_code as locale_code, out_can_email as can_email
  FROM acct_forgotpass('[MAIL_ADDR]')
 LIMIT 1;