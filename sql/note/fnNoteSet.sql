DROP FUNCTION note_set;
CREATE OR REPLACE FUNCTION note_set( "in_account_id" integer, "in_note_id" integer, "in_content" text, "in_type" character varying, "in_private" char(1),
                                 OUT "out_note_id" integer, OUT "out_note_version" integer )
    LANGUAGE plpgsql AS $$

    DECLARE xCanEdit boolean;

    BEGIN

        /* Check whether the current account can Insert/Update a Note record */
        IF COALESCE("in_note_id", 0) > 0 THEN 
            SELECT CASE WHEN me."type" IN ('account.global', 'account.admin') THEN true
                        WHEN nn."updated_by" = me."id" THEN true
                        WHEN nn."is_private" = true AND me."type" IN ('account.global', 'account.admin') THEN true
                        WHEN nn."is_private" = false AND nh."updated_by" = me."id" THEN true
                        ELSE false END as "can_edit" INTO xCanEdit
              FROM Account me LEFT OUTER JOIN Note nn ON nn.is_deleted = false and nn.id = COALESCE("in_note_id", 0) 
                              LEFT OUTER JOIN NoteHistory nh ON nh.is_deleted = false and nh.note_id = COALESCE("in_note_id", 0)
             WHERE me."is_deleted" = false and me."id" = "in_account_id"
             ORDER BY "can_edit" DESC LIMIT 1;

        ELSE
            SELECT true INTO xCanEdit;
        END IF;


        /* Record the Note */
        IF COALESCE("in_note_id", 0) > 0 THEN
            /* UPDATE a record if permitted */
            IF xCanEdit = true THEN
                UPDATE Note
                   SET "type" = (SELECT z."code" FROM Type z WHERE z."is_deleted" = false and z."code" LIKE 'note.%'
                                  ORDER BY CASE WHEN z."code" = LOWER("in_type") THEN 0 ELSE 1 END, CASE WHEN z."code" = 'note.normal' THEN 0 ELSE 1 END, z."code" LIMIT 1),
                       "content" = CASE WHEN LENGTH(TRIM("in_content")) > 0 THEN TRIM("in_content") ELSE "content" END,
                       "is_private" = CASE WHEN UPPER("in_private") IN ('Y', '1', 'TRUE') THEN true ELSE false END,
                       "is_deleted" = CASE WHEN LENGTH(TRIM("in_content")) > 0 THEN false ELSE true END,
                       "updated_by" = "in_account_id"
                 WHERE "id" = COALESCE("in_note_id", 0)
                 RETURNING id, ROUND(EXTRACT(EPOCH FROM updated_at)) INTO "out_note_id", "out_note_version";
            END IF;

        ELSE
            /* INSERT a new record */
            INSERT INTO Note ("type", "content", "is_private", "updated_by")
            SELECT (SELECT z."code" FROM Type z WHERE z."is_deleted" = false and z."code" LIKE 'note.%'
                     ORDER BY CASE WHEN z."code" = LOWER("in_type") THEN 0 ELSE 1 END, CASE WHEN z."code" = 'note.normal' THEN 0 ELSE 1 END, z."code" LIMIT 1) as "type",
                   TRIM("in_content") as "content",
                   CASE WHEN UPPER("in_private") IN ('Y', '1', 'TRUE') THEN true ELSE false END as "is_private",
                   acct."id" as "updated_by"
              FROM Account acct
             WHERE acct."is_deleted" = false and LENGTH(TRIM("in_content")) > 0
               and acct."id" = "in_account_id"
               RETURNING id, ROUND(EXTRACT(EPOCH FROM updated_at)) INTO "out_note_id", "out_note_version";
        END IF;

    END
    $$;