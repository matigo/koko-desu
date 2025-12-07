SELECT lo."id", ROUND(EXTRACT(EPOCH FROM lo."updated_at")) as "version"
  FROM "Location" lo INNER JOIN "Country" co ON lo."country_code" = co."code"
 WHERE lo."is_deleted" = false and co."is_deleted" = false and co."code" = 'JP'
   and lo."is_published" = true and lo."is_visible" = true 
 ORDER BY RANDOM() LIMIT 1;