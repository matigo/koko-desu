SELECT su."site_id"
  FROM "Site" si INNER JOIN "SiteUrl" su ON si."id" = su."site_id"
 WHERE si."is_deleted" = false and su."is_deleted" = false and su."url" = LOWER('[DOMAIN]')
 ORDER BY su."updated_at" DESC
 LIMIT 1;