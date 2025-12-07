/** ************************************************************************* *
 *  Create Sequence (Location)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Location";
CREATE TABLE IF NOT EXISTS "Location" (
    "id"                serial                          ,

    "name"              varchar(512)        NOT NULL    ,
    "primary_type"      varchar(128)            NULL    ,
    "summary"           varchar(512)            NULL    ,
    "description"       text                    NULL    ,

    "address"           varchar(512)            NULL    ,
    "country"           varchar(64)             NULL    ,
    "postal"            varchar(64)             NULL    ,
    "state"             varchar(64)             NULL    ,
    "city"              varchar(64)             NULL    ,

    "phone"             varchar(128)            NULL    ,
    "url"               varchar(512)            NULL    ,

    "types"             varchar(512)            NULL    ,
    "rating"            decimal(6,3)            NULL    ,
    "rating_count"      integer             NOT NULL    DEFAULT 0,

    "longitude"         decimal(12,8)           NULL    ,
    "latitude"          decimal(12,8)           NULL    ,
    "map_url"           varchar(512)            NULL    ,

    "is_active"         boolean             NOT NULL    DEFAULT false,
    "is_valid"          boolean             NOT NULL    DEFAULT false,

    "kids_ok"           boolean             NOT NULL    DEFAULT false,
    "dogs_ok"           boolean             NOT NULL    DEFAULT false,

    "handi_parking"     boolean             NOT NULL    DEFAULT false,
    "handi_access"      boolean             NOT NULL    DEFAULT false,

    "sort_order"        smallint            NOT NULL    DEFAULT 50      CHECK ("sort_order" BETWEEN 0 AND 999),
    "guid"              char(36)     UNIQUE NOT NULL    ,
    "key"               varchar(512) UNIQUE NOT NULL    ,

    "is_deleted"        boolean             NOT NULL    DEFAULT false,
    "created_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id")
);
CREATE INDEX idx_location_guid ON "Location" ("guid");
CREATE INDEX idx_location_main ON "Location" ("is_deleted", "is_active", "is_valid");
CREATE INDEX idx_location_key ON "Location" ("key");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_location_before_insert ON "Location";
CREATE TRIGGER trg_location_before_insert
  BEFORE INSERT ON "Location"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_location_before_update ON "Location";
CREATE TRIGGER trg_location_before_update
  BEFORE UPDATE ON "Location"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_location_before_delete ON "Location";
CREATE OR REPLACE RULE rule_location_before_delete AS
    ON DELETE TO "Location"
    DO INSTEAD
       UPDATE "Location" SET "is_deleted" = true WHERE "Location"."id" = OLD."id";

DROP TABLE IF EXISTS "LocationMeta";
CREATE TABLE IF NOT EXISTS "LocationMeta" (
    "location_id"   integer             NOT NULL    ,
    "key"           varchar(256)        NOT NULL    ,
    "value"         varchar(4096)           NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("location_id", "key"),
    FOREIGN KEY ("location_id") REFERENCES "Location" ("id")
);
CREATE INDEX idx_locationmeta_main ON "LocationMeta" ("location_id");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_locationmeta_before_insert ON "LocationMeta";
CREATE TRIGGER trg_locationmeta_before_insert
  BEFORE INSERT ON "LocationMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_locationmeta_before_update ON "LocationMeta";
CREATE TRIGGER trg_locationmeta_before_update
  BEFORE UPDATE ON "LocationMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before DELETE */
DROP RULE IF EXISTS rule_locationmeta_before_delete ON "LocationMeta";
CREATE OR REPLACE RULE rule_locationmeta_before_delete AS
    ON DELETE TO "LocationMeta"
    DO INSTEAD
       UPDATE "LocationMeta" SET "is_deleted" = true WHERE "LocationMeta"."location_id" = OLD."location_id" and "LocationMeta"."key" = OLD."key";


DROP TABLE IF EXISTS "LocationReview";
CREATE TABLE IF NOT EXISTS "LocationReview" (
    "location_id"   integer             NOT NULL    ,
    "key"           varchar(256)        NOT NULL    ,
    "content"       text                NOT NULL    ,

    "publish_at"    timestamp           NOT NULL    ,
    "rating"        decimal(6,3)        NOT NULL    DEFAULT 0,
    "author"        varchar(256)            NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("location_id", "key"),
    FOREIGN KEY ("location_id") REFERENCES "Location" ("id")
);
CREATE INDEX idx_locationreview_main ON "LocationReview" ("location_id");

/* Before INSERT */

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_locationreview_before_update ON "LocationReview";
CREATE TRIGGER trg_locationreview_before_update
  BEFORE UPDATE ON "LocationReview"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_locationreview_before_delete ON "LocationReview";
CREATE OR REPLACE RULE rule_locationreview_before_delete AS
    ON DELETE TO "LocationReview"
    DO INSTEAD
       UPDATE "LocationReview" SET "is_deleted" = true WHERE "LocationReview"."location_id" = OLD."location_id" AND "LocationReview"."key" = OLD."key";

DROP TABLE IF EXISTS "LocationPhoto";
CREATE TABLE IF NOT EXISTS "LocationPhoto" (
    "location_id"   integer             NOT NULL    ,
    "url"           varchar(2048)       NOT NULL    ,

    "height"        integer                 NULL    ,
    "width"         integer                 NULL    ,
    "author"        varchar(256)            NULL    ,

    "local_src"     varchar(512)            NULL    ,
    "hash"          varchar(192)            NULL    ,
    "has_thumb"     boolean             NOT NULL    DEFAULT false,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("location_id", "url"),
    FOREIGN KEY ("location_id") REFERENCES "Location" ("id")
);
CREATE INDEX idx_locationphoto_main ON "LocationPhoto" ("location_id");

/* Before INSERT */

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_locationphoto_before_update ON "LocationPhoto";
CREATE TRIGGER trg_locationphoto_before_update
  BEFORE UPDATE ON "LocationPhoto"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_locationphoto_before_delete ON "LocationPhoto";
CREATE OR REPLACE RULE rule_locationphoto_before_delete AS
    ON DELETE TO "LocationPhoto"
    DO INSTEAD
       UPDATE "LocationPhoto" SET "is_deleted" = true WHERE "LocationPhoto"."location_id" = OLD."location_id" AND "LocationPhoto"."url" = OLD."url";

DROP TABLE IF EXISTS "LocationComment";
CREATE TABLE IF NOT EXISTS "LocationComment" (
    "id"                serial                          ,
    "location_id"       integer             NOT NULL    ,
    "content"           text                NOT NULL    ,

    "publish_at"        timestamp           NOT NULL    ,
    "rating"            decimal(6,3)        NOT NULL    DEFAULT 0,
    "author"            varchar(64)             NULL    ,

    "sort_order"        smallint            NOT NULL    DEFAULT 50      CHECK ("sort_order" BETWEEN 0 AND 999),
    "guid"              char(36)     UNIQUE NOT NULL    ,
    "key"               varchar(512) UNIQUE NOT NULL    ,

    "is_deleted"        boolean             NOT NULL    DEFAULT false,
    "created_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("location_id") REFERENCES "Location" ("id")
);
CREATE INDEX idx_locationcomment_main ON "LocationComment" ("location_id");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_locationcomment_before_insert ON "LocationComment";
CREATE TRIGGER trg_locationcomment_before_insert
  BEFORE INSERT ON "LocationComment"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_locationcomment_before_update ON "LocationComment";
CREATE TRIGGER trg_locationcomment_before_update
  BEFORE UPDATE ON "LocationComment"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_locationcomment_before_delete ON "LocationComment";
CREATE OR REPLACE RULE rule_locationcomment_before_delete AS
    ON DELETE TO "LocationComment"
    DO INSTEAD
       UPDATE "LocationComment" SET "is_deleted" = true WHERE "LocationComment"."id" = OLD."id";




