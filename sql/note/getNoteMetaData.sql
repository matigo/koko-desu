SELECT mm."key", mm."value", ROUND(EXTRACT(EPOCH FROM nn."updated_at")) as "version"
  FROM Note nn INNER JOIN NoteMeta mm ON nn."id" = mm."note_id"
 WHERE mm."is_deleted" = false and nn."is_deleted" = false and nn."id" = [NOTE_ID]
 ORDER BY mm."key";