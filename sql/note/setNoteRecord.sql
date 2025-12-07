SELECT "out_note_id" as "note_id", "out_note_version" as "note_version"
  FROM note_set([ACCOUNT_ID], [NOTE_ID], E'[CONTENT]', '[TYPE]', '[PRIVATE]');