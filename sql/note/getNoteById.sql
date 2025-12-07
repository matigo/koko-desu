SELECT nn."id" as "note_id", nn."guid" as "note_guid", nn."type" as "note_type",
       nn."content" as "note_text", nn."hash", nn."is_private",
       (SELECT CASE WHEN COUNT(z."key") > 0 THEN true ELSE false END FROM NoteMeta z WHERE z."is_deleted" = false and z."note_id" = nn."id" LIMIT 1) as "has_meta",
       (SELECT CASE WHEN COUNT(z."id") > 0 THEN true ELSE false END FROM NoteHistory z WHERE z."is_deleted" = false and z."note_id" = nn."id" LIMIT 1) as "has_history",
       nn."created_at", nn."updated_at", nn."updated_by", acct."version" as "updated_by_version"
  FROM Account acct INNER JOIN Note nn ON acct."id" = nn."updated_by" 
 WHERE acct."is_deleted" = false and nn."is_deleted" = false and nn."id" = [NOTE_ID]
 LIMIT 1;