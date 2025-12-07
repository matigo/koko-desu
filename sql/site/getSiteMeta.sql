SELECT sm."key", sm."value"
  FROM "Site" si INNER JOIN "SiteMeta" sm ON si."id" = sm."site_id"
 WHERE sm."is_deleted" = false and si."is_deleted" = false and si."id" = [SITE_ID]
 ORDER BY sm."key";