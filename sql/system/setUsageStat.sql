INSERT INTO "UsageStats" ( "site_id", "token_id", "http_code", "request_type", "request_uri", "referrer",
                           "event_at", "event_on", "from_ip", "locale_code",
                           "agent", "platform", "browser", "version",
                           "seconds", "sqlops", "message" )
SELECT CASE WHEN [SITE_ID] > 0 THEN [SITE_ID] ELSE NULL END,
       CASE WHEN [TOKEN_ID] > 0 THEN [TOKEN_ID] ELSE NULL END,
       CASE WHEN [HTTP_CODE] BETWEEN 100 AND 599 THEN [HTTP_CODE] ELSE 200 END,
       CASE WHEN '[REQ_TYPE]' = '' THEN 'GET' ELSE TRIM(LEFT('[REQ_TYPE]', 8)) END,
       CASE WHEN '[REQ_URI]' = '' THEN '/' ELSE TRIM(LEFT('[REQ_URI]', 512)) END,
       CASE WHEN '[REFERER]' = '' THEN NULL ELSE TRIM(LEFT('[REFERER]', 1024)) END,

       CURRENT_TIMESTAMP, CURRENT_DATE, TRIM(LEFT('[IP_ADDR]', 64)),
       CASE WHEN LENGTH('[LOCALE]') BETWEEN 2 AND 5 THEN LOWER(TRIM(LEFT('[LOCALE]', 6))) ELSE NULL END,

       CASE WHEN '[AGENT]' = '' THEN NULL ELSE TRIM(LEFT('[AGENT]', 2048)) END,
       CASE WHEN '[UAPLATFORM]' = '' THEN NULL ELSE TRIM(LEFT('[UAPLATFORM]', 64)) END,
       CASE WHEN '[UABROWSER]' = '' THEN 'Unknown' ELSE TRIM(LEFT('[UABROWSER]', 64)) END,
       CASE WHEN '[UAVERSION]' = '' THEN NULL ELSE TRIM(LEFT('[UAVERSION]', 64)) END,

       CASE WHEN [RUNTIME] < 0 THEN ([RUNTIME] * -1) ELSE [RUNTIME] END,
       [SQL_OPS],
       CASE WHEN '[MESSAGE]' = '' THEN NULL ELSE TRIM(LEFT('[MESSAGE]', 512)) END
 RETURNING "id";