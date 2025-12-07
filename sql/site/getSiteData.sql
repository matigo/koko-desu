SELECT su."site_id", si."https", su."url" as "site_url", si."guid" as "site_guid", si."name" as "site_name", si."description", si."keywords",
       si."locale_code", lo."language_code",
       si."theme", ROUND(EXTRACT(EPOCH FROM si."updated_at")) as "version", si."is_default", si."updated_at", 10 as "sort_order",
       (SELECT z."value" FROM "SiteMeta" z WHERE z."is_deleted" = false AND z."key" = 'banner.src' AND z."site_id" = si."id" LIMIT 1) as "banner_src",
       CAST(CASE WHEN su."url" = LOWER('[SITE_URL]') THEN false ELSE true END AS boolean) as "do_redirect"
  FROM "Site" si INNER JOIN "SiteUrl" su ON si."id" = su."site_id"
                 INNER JOIN "SiteUrl" bb ON su."site_id" = bb."site_id"
            LEFT OUTER JOIN "Locale" lo ON si."locale_code" = lo."code"
 WHERE bb."is_deleted" = false and su."is_deleted" = false and si."is_deleted" = false and su."is_active" = true
   and bb."url" = LOWER('[SITE_URL]')
 UNION ALL
SELECT su."site_id", si."https", su."url" as "site_url", si."guid" as "site_guid", si."name" as "site_name", si."description", si."keywords",
       si."locale_code", lo."language_code",
       si."theme", ROUND(EXTRACT(EPOCH FROM si."updated_at")) as "version", si."is_default", si."updated_at", 20 as "sort_order",
       (SELECT z."value" FROM "SiteMeta" z WHERE z."is_deleted" = false AND z."key" = 'banner.src' AND z."site_id" = si."id" LIMIT 1) as "banner_src",
       CAST(CASE WHEN su."url" = LOWER('[SITE_URL]') THEN false ELSE true END AS boolean) as "do_redirect"
  FROM "Site" si INNER JOIN "SiteUrl" su ON si."id" = su."site_id"
            LEFT OUTER JOIN "Locale" lo ON si."locale_code" = lo."code"
 WHERE su."is_deleted" = false and si."is_deleted" = false and su."is_active" = true and si."is_default" = true
 ORDER BY "sort_order" LIMIT 1;