SELECT si."id" as "site_id", si."guid" as "site_guid", si."locale_code"
  FROM "Site" si INNER JOIN "SiteUrl" su ON si."id" = su."site_id"
                 INNER JOIN "SiteUrl" bb ON su."site_id" = bb."site_id"
 WHERE bb."is_deleted" = false and su."is_deleted" = false and si."is_deleted" = false and su."is_active" = true
   and bb."url" = LOWER('[SITE_URL]')
 LIMIT 1;