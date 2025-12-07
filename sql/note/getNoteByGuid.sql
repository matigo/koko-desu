SELECT nn."id" as "note_id", ROUND(EXTRACT(EPOCH FROM nn."updated_at")) as "version"
  FROM Note nn 
 WHERE nn."is_deleted" = false and nn."guid" = '[NOTE_GUID]'
 LIMIT 1;