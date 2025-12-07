/* Create the landing site */
INSERT INTO "Site" ("name", "description", "keywords", "locale_code", "https", "theme", "is_default")
SELECT E'Koko Desu'as "name", E'A silly place to play some games' as "description", E'game,simple,car,day,word,search,find' as "keywords",
       'ja_JP' as "locale_code", false as "https", 'koko' as "theme", true as "is_default";

INSERT INTO "SiteUrl" ("site_id", "url", "is_active")
SELECT si."id" as "site_id", E'koko.local' as "url", true as "is_active"
  FROM "Site" si
 WHERE si."is_default" = true
 LIMIT 1;




/* Insert some records into Location */

INSERT INTO "Location" ("country_code", "state_id", "src", "name", "longitude", "latitude", "photo_at", "is_published", "is_visible", "created_by", "updated_by")
SELECT co."code" as "country_code", cs."id" as "state_id", 'abc123.jpeg' as "src", E'Sunset', 136.0473923, 35.4100601, '2025-11-30 07:27:49' as "photo_at",
       true as "is_published", true as "is_visible", acct."id" as "created_by", acct."id" as "updated_by"
  FROM "Account" acct LEFT OUTER JOIN "Country" co ON co."code" = 'JP'
                      LEFT OUTER JOIN "CountryState" cs ON co."code" = cs."country_code" AND cs."id" = 25
 WHERE acct."is_deleted" = false and co."is_deleted" = false and cs."is_deleted" = false
   and acct."type" NOT IN ('account.expired', 'account.banned')
   and acct."id" = 1
 LIMIT 1;
