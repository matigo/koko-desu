DROP FUNCTION site_set;
CREATE OR REPLACE FUNCTION site_set( "in_account_id" integer, "in_site_guid" character varying,
                                     "in_name" character varying, "in_description" character varying, "in_keywords" character varying,
                                     "in_locale" character varying, "in_https" character varying, "in_theme" character varying, "in_visibility" character varying,
                                     "in_url" character varying, "in_password" character varying, "sha_hash" character varying,
                                 OUT "out_site_id" integer, OUT "out_site_guid" character varying )
    LANGUAGE plpgsql AS $$

    DECLARE xSiteUrlID  integer;
    DECLARE xUrlCount   integer;

    BEGIN
        /* Perform some Basic Validation */
        IF LENGTH("in_site_guid") > 0 AND LENGTH("in_site_guid") != 36 THEN
            RAISE EXCEPTION 'Supplied Site GUID is Invalid: %', LENGTH("in_site_guid");
        END IF;

        IF LENGTH("in_password") < 8 THEN
            RAISE EXCEPTION 'Supplied Password is Too Short: %', LENGTH("in_password");
        END IF;

        IF LENGTH("in_email") < 6 THEN
            RAISE EXCEPTION 'Supplied Email is Too Short: %', LENGTH("in_email");
        END IF;

        /* If we have a Site.guid, check that the Account.id owns it */


        IF COALESCE("out_site_id", "in_account_id") != "in_account_id" THEN
            RAISE EXCEPTION 'Supplied Site is not owned by Account';
        END IF;

        /* Check to see if the requested URL is already in use somewhere else */
        IF LENGTH("in_site_guid") <= 0 AND
           (SELECT COUNT(su.id) FROM Site si INNER JOIN SiteUrl su ON si.id = su.site_id
             WHERE si.is_deleted = false and su.is_deleted = false and su.is_active = true and su.url = LOWER('matigo.ca')) > 0 THEN
            RAISE EXCEPTION 'Supplied Site Url is already in use';
        END IF;

        IF LENGTH("in_site_guid") = 36 THEN

        END IF;

        /* Create the Site record */
        IF COALESCE("out_site_id", 0) <= 0 THEN
            INSERT INTO Site ("account_id", "name", "description", "keywords", "locale_code", "https", "theme", "visibility", "version")
            SELECT acct.id as "account_id", TRIM(LEFT("in_name", 256)) as "name",
                   TRIM(LEFT("in_description", 512)) as "description", LOWER(TRIM(LEFT("in_keywords", 1024))) as "keywords",
                   COALESCE((SELECT z."code" FROM Locale z  WHERE z."is_deleted" = false and LOWER(z."code") = LOWER("in_locale") LIMIT 1), acct."locale_code") as "locale_code",
                   CASE WHEN UPPER("in_https") = 'Y' THEN true ELSE false END as "https",
                   LOWER("in_theme") as "theme",
                   (SELECT z."code" FROM Type z WHERE z."is_deleted" = false and z."code" LIKE 'visibility.%'
                     ORDER BY CASE WHEN z."code" = LOWER("in_visibility") THEN 0
                                   WHEN z."code" = 'visibility.public' THEN 1
                                   ELSE 9 END
                     LIMIT 1) as "visibility",
                   ROUND(EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)) as "version"
              FROM Account acct
             WHERE acct."is_deleted" = false and acct."id" = "in_account_id"
            RETURNING "id", "guid" INTO "out_site_id", "out_site_guid";

            /* Set the Initial Metadata */
            IF COALESCE("out_site_id", 0) > 0 THEN
                INSERT INTO SiteMeta ("site_id", "key", "value")
                VALUES ("out_site_id", 'show.article', 'Y'),
                       ("out_site_id", 'show.note', 'N'),
                       ("out_site_id", 'show.quotation', 'Y'),
                       ("out_site_id", 'show.location', 'N'),
                       ("out_site_id", 'site.explicit', 'C'),
                       ("out_site_id", 'site.license', 'CC BY-NC-SA'),
                       ("out_site_id", 'site.rss-cover', ''),
                       ("out_site_id", 'site.rss-items', '25');
            END IF;

        ELSE
            UPDATE Site
               SET name = TRIM(LEFT("in_name", 256)),
                   description = TRIM(LEFT("in_description", 512)),
                   keywords = LOWER(TRIM(LEFT("in_keywords", 1024))),
                   locale_code = (SELECT z."code" FROM Locale z  WHERE z."is_deleted" = false and LOWER(z."code") = LOWER("in_locale") LIMIT 1),
                   https = CASE WHEN UPPER("in_https") = 'Y' THEN true ELSE false END,
                   theme = LOWER("in_theme"),
                   visibility = (SELECT z."code" FROM Type z WHERE z."is_deleted" = false and z."code" LIKE 'visibility.%'
                     ORDER BY CASE WHEN z."code" = LOWER("in_visibility") THEN 0
                                   WHEN z."code" = 'visibility.public' THEN 1
                                   ELSE 9 END
                     LIMIT 1),
                   version = ROUND(EXTRACT(EPOCH FROM CURRENT_TIMESTAMP))
             WHERE is_deleted = false and account_id = "in_account_id" and id = "out_site_id";
        END IF;

        /* Determine a SiteUrl.id and Url Count */
        SELECT MAX(CASE WHEN su."url" = LOWER("in_url") THEN su."id" ELSE NULL END) as suid, COUNT(su."url") as url_count
          INTO xSiteUrlID, xUrlCount
          FROM Site si LEFT OUTER JOIN SiteUrl su ON si."id" = su."site_id" AND su."is_deleted" = false
         WHERE si."is_deleted" = false and si."account_id" = "in_account_id" and si."id" = "out_site_id";

        /* Set the SiteUrl */
        IF COALESCE(xSiteUrlID, 0) <= 0 THEN
            INSERT INTO SiteUrl (site_id, url, is_active)
            VALUES ("out_site_id", LOWER("in_url"), true);

        ELSE
            UPDATE SiteUrl
               SET "is_active" = CASE WHEN "url" = LOWER("in_url") THEN true ELSE false END
             WHERE "is_deleted" = false and "id" = "out_site_id"
               and "is_active" != CASE WHEN "url" = LOWER("in_url") THEN true ELSE false END;
        END IF;

    END
    $$;