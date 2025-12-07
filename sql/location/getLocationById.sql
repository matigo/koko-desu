SELECT lo."guid", lo."id" as "idx", lo."src", lo."name", lo."longitude", lo."latitude",
       
       lo."country_code", co."name" as "country_name", co."label" as "country_label",
       lo."state_id", cs."name" as "state_name", cs."label" as "state_label",
       cs."region_id", cr."name" as "region_name", cr."label" as "region_label",
       
       lo."photo_at", ROUND(EXTRACT(EPOCH FROM lo."photo_at")) as "photo_unix",
       lo."feature_at", ROUND(EXTRACT(EPOCH FROM lo."feature_at")) as "feature_unix",
       lo."is_published", lo."is_visible",

       lo."note_id", nn."guid" as "note_guid", nn."type" as "note_type", nn."content" as "note_text", nn."is_private" as "note_private", nn."hash" as "note_hash",
       (SELECT CASE WHEN COUNT(z."key") > 0 THEN true ELSE false END FROM "LocationMeta" z WHERE z."is_deleted" = false and z."location_id" = lo."id") as "has_meta",

       lo."created_by", acct."display_name" as "account_name", acct."first_name", acct."guid" as "account_guid",
       lo."updated_by",
       lo."created_at", ROUND(EXTRACT(EPOCH FROM lo."created_at")) as "created_unix",
       lo."updated_at", ROUND(EXTRACT(EPOCH FROM lo."updated_at")) as "updated_unix"
  FROM "Account" acct INNER JOIN "Location" lo ON acct."id" = lo."created_by"
                      INNER JOIN "Country" co ON lo."country_code" = co."code"
                 LEFT OUTER JOIN "CountryState" cs ON lo."state_id" = cs."id" AND cs."is_deleted" = false
                 LEFT OUTER JOIN "CountryRegion" cr ON cs."region_id" = cr."id" AND cr."is_deleted" = false
                 LEFT OUTER JOIN "Note" nn ON lo."note_id" = nn."id" AND nn."is_deleted" = false
 WHERE lo."is_deleted" = false and co."is_deleted" = false and co."code" = 'JP'
   and lo."is_published" = true and lo."is_visible" = true 
   and lo."id" = [LOCATION_ID]
 ORDER BY lo."id" LIMIT 1;