/* *************************************************************************
 *  This is the main SQL DataTable Definition for Koko
 *
 *
 * ************************************************************************* */
CREATE DATABASE koko;

CREATE USER kapi WITH ENCRYPTED PASSWORD 'superSecretPassword!123';

GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO kapi;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO kapi;

/** ************************************************************************* *
 *  Required and/or Common Functions
 ** ************************************************************************* */
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

CREATE OR REPLACE FUNCTION guid_before_insert()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."guid" = uuid_generate_v4();
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION guid_before_update()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    IF NEW."guid" <> OLD."guid" THEN
        NEW."guid" = OLD."guid";
    END IF;

    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION date_before_update()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."updated_at" = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION meta_before()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    IF COALESCE(TRIM(NEW."value"), '') <> COALESCE(TRIM(OLD."value"), '') THEN
        NEW."is_deleted" = false;
    END IF;

    IF COALESCE(TRIM(NEW."value"), '') = '' THEN
        NEW."is_deleted" = true;
    END IF;

    NEW."updated_at" = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION primary_before_insert()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."guid" = uuid_generate_v4();

    IF to_jsonb(NEW) ? 'start_at' THEN
        IF NEW."start_at" IS NULL THEN
            NEW."start_at" = CURRENT_TIMESTAMP;
        END IF;
    END IF;

    IF to_jsonb(NEW) ? 'until_at' THEN
        IF NEW."until_at" IS NULL THEN
            IF COALESCE(NEW."until_at", NEW."start_at") < NEW."start_at" THEN
                NEW."until_at" = NULL;
            END IF;
        END IF;
    END IF;

    IF to_jsonb(NEW) ? '"until_at"' AND to_jsonb(NEW) ? 'is_active' THEN
        IF CURRENT_TIMESTAMP > COALESCE(NEW."until_at", CURRENT_TIMESTAMP) THEN
            NEW."is_active" = false;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION primary_before_update()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    IF NEW."guid" <> OLD."guid" THEN
        NEW."guid" = OLD."guid";
    END IF;

    IF to_jsonb(NEW) ? 'start_at' THEN
        IF NEW."start_at" IS NULL THEN
            NEW."start_at" = CURRENT_TIMESTAMP;
        END IF;
    END IF;

    IF to_jsonb(NEW) ? 'until_at' THEN
        IF NEW."until_at" IS NULL THEN
            IF COALESCE(NEW."until_at", NEW."start_at") < NEW."start_at" THEN
                NEW."until_at" = NULL;
            END IF;
        END IF;
    END IF;

    IF to_jsonb(NEW) ? 'until_at' AND to_jsonb(NEW) ? 'is_active' THEN
        IF CURRENT_TIMESTAMP > COALESCE(NEW."until_at", CURRENT_TIMESTAMP) THEN
            NEW."is_active" = false;
        END IF;
    END IF;

    NEW."updated_at" = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION return_original()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    RETURN OLD;
END;
$$;

/** ************************************************************************* *
 *  Create Sequence (Preliminaries)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Country";
CREATE TABLE IF NOT EXISTS "Country" (
    "code"         varchar(2)            NOT NULL    ,
    "name"         varchar(255)          NOT NULL    ,
    "label"        varchar(64)               NULL    ,
    "sort_order"   smallint              NOT NULL    DEFAULT 50      CHECK ("sort_order" BETWEEN 0 AND 999),
    "is_available" boolean               NOT NULL    DEFAULT false,

    "is_deleted"   boolean               NOT NULL    DEFAULT false,
    "created_at"   timestamp             NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"   timestamp             NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("code")
);
CREATE INDEX idx_country_main ON "Country" ("is_available");

INSERT INTO "Country" ("code", "name")
VALUES ('JP', 'Japan'), ('AF', 'Afghanistan'), ('AL', 'Albania'), ('DZ', 'Algeria'), ('AS', 'American Samoa'), ('AD', 'Andorra'),
       ('AO', 'Angola'), ('AI', 'Anguilla'), ('AQ', 'Antarctica'), ('AG', 'Antigua and Barbuda'), ('AR', 'Argentina'), ('AM', 'Armenia'),
       ('AW', 'Aruba'), ('AU', 'Australia'), ('AT', 'Austria'), ('AZ', 'Azerbaijan'), ('BS', 'Bahamas'), ('BH', 'Bahrain'),
       ('BD', 'Bangladesh'), ('BB', 'Barbados'), ('BY', 'Belarus'), ('BE', 'Belgium'), ('BZ', 'Belize'), ('BJ', 'Benin'),
       ('BM', 'Bermuda'), ('BT', 'Bhutan'), ('BO', 'Bolivia'), ('BA', 'Bosnia-Herzegovina'), ('BW', 'Botswana'), ('BV', 'Bouvet Island'),
       ('BR', 'Brazil'), ('IO', 'British Indian Ocean Territory'), ('BN', 'Brunei'), ('BG', 'Bulgaria'), ('BF', 'Burkina Faso'), ('BI', 'Burundi'),
       ('KH', 'Cambodia'), ('CM', 'Cameroon'), ('CA', 'Canada'), ('CV', 'Cape Verde'), ('KY', 'Cayman Islands'), ('CF', 'Central African Republic'),
       ('TD', 'Chad'), ('CL', 'Chile'), ('CN', 'China'), ('CX', 'Christmas Island'), ('CC', 'Cocos Islands'), ('CO', 'Colombia'),
       ('KM', 'Comoros'), ('CG', 'Congo'), ('CD', 'Congo, Democratic Republic'), ('CK', 'Cook Islands'), ('CR', 'Costa Rica'), ('HR', 'Croatia'),
       ('CU', 'Cuba'), ('CY', 'Cyprus'), ('CZ', 'Czech Republic'), ('DK', 'Denmark'), ('DJ', 'Djibouti'), ('DM', 'Dominica'),
       ('DO', 'Dominican Republic'), ('EC', 'Ecuador'), ('EG', 'Egypt'), ('SV', 'El Salvador'), ('GQ', 'Equatorial Guinea'), ('ER', 'Eritrea'),
       ('EE', 'Estonia'), ('ET', 'Ethiopia'), ('EU', 'European Union'), ('FK', 'Falkland Islands'), ('FO', 'Faroe Islands'), ('FJ', 'Fiji'),
       ('FI', 'Finland'), ('FR', 'France'), ('GF', 'French Guiana'), ('TF', 'French Southern Territories'), ('GA', 'Gabon'), ('GM', 'Gambia'),
       ('GE', 'Georgia'), ('DE', 'Germany'), ('GH', 'Ghana'), ('GI', 'Gibraltar'), ('GB', 'Great Britain'), ('GR', 'Greece'),
       ('GL', 'Greenland'), ('GD', 'Grenada'), ('GP', 'Guadeloupe'), ('GU', 'Guam'), ('GT', 'Guatemala'), ('GG', 'Guernsey'),
       ('GN', 'Guinea'), ('GW', 'Guinea Bissau'), ('GY', 'Guyana'), ('HT', 'Haiti'), ('HM', 'Heard Island and McDonald Islands'), ('HN', 'Honduras'),
       ('HK', 'Hong Kong'), ('HU', 'Hungary'), ('IS', 'Iceland'), ('IN', 'India'), ('ID', 'Indonesia'), ('IR', 'Iran'),
       ('IQ', 'Iraq'), ('IE', 'Ireland'), ('IM', 'Isle of Man'), ('IL', 'Israel'), ('IT', 'Italy'), ('CI', 'Ivory Coast'),
       ('JM', 'Jamaica'), ('JE', 'Jersey'), ('JO', 'Jordan'), ('KZ', 'Kazakhstan'), ('KE', 'Kenya'), ('KI', 'Kiribati'),
       ('KP', 'North Korea'), ('KR', 'South Korea'), ('KW', 'Kuwait'), ('KG', 'Kyrgyzstan'), ('LA', 'Laos'), ('LV', 'Latvia'),
       ('LB', 'Lebanon'), ('LS', 'Lesotho'), ('LR', 'Liberia'), ('LY', 'Libya'), ('LI', 'Liechtenstein'), ('LT', 'Lithuania'),
       ('LU', 'Luxembourg'), ('MO', 'Macau'), ('MK', 'Macedonia'), ('MG', 'Madagascar'), ('MW', 'Malawi'), ('MY', 'Malaysia'),
       ('MV', 'Maldives'), ('ML', 'Mali'), ('MT', 'Malta'), ('MH', 'Marshall Islands'), ('MQ', 'Martinique'), ('MR', 'Mauritania'),
       ('MU', 'Mauritius'), ('YT', 'Mayotte'), ('MX', 'Mexico'), ('FM', 'Micronesia'), ('MD', 'Moldova'), ('MC', 'Monaco'),
       ('MN', 'Mongolia'), ('ME', 'Montenegro'), ('MS', 'Montserrat'), ('MA', 'Morocco'), ('MZ', 'Mozambique'), ('MM', 'Myanmar'),
       ('NA', 'Namibia'), ('NR', 'Nauru'), ('NP', 'Nepal'), ('NL', 'Netherlands'), ('AN', 'Netherlands Antilles'), ('NC', 'New Caledonia'),
       ('NZ', 'New Zealand'), ('NI', 'Nicaragua'), ('NE', 'Niger'), ('NG', 'Nigeria'), ('NU', 'Niue'), ('NF', 'Norfolk Island'),
       ('MP', 'Northern Mariana Islands'), ('NO', 'Norway'), ('OM', 'Oman'), ('PK', 'Pakistan'), ('PW', 'Palau'), ('PA', 'Panama'),
       ('PG', 'Papua New Guinea'), ('PY', 'Paraguay'), ('PE', 'Peru'), ('PH', 'Philippines'), ('PN', 'Pitcairn Island'), ('PL', 'Poland'),
       ('PF', 'Polynesia'), ('PT', 'Portugal'), ('PR', 'Puerto Rico'), ('QA', 'Qatar'), ('RE', 'Reunion'), ('RO', 'Romania'),
       ('RU', 'Russia'), ('RW', 'Rwanda'), ('SH', 'Saint Helena'), ('KN', 'Saint Kitts'), ('LC', 'Saint Lucia Castries'), ('PM', 'Saint Pierre and Miquelon'),
       ('VC', 'Saint Vincent'), ('WS', 'Samoa'), ('SM', 'San Marino'), ('ST', 'Sao Tome'), ('SA', 'Saudi Arabia'), ('SN', 'Senegal'),
       ('RS', 'Serbia'), ('SC', 'Seychelles'), ('SL', 'Sierra Leone'), ('SG', 'Singapore'), ('SK', 'Slovakia'), ('SI', 'Slovenia'),
       ('SB', 'Solomon Islands'), ('SO', 'Somalia'), ('ZA', 'South Africa'), ('GS', 'South Georgia'), ('SS', 'South Sudan'), ('ES', 'Spain'),
       ('LK', 'Sri Lanka'), ('SD', 'Sudan'), ('SR', 'Suriname'), ('SJ', 'Svalbard'), ('SZ', 'Swaziland'), ('SE', 'Sweden'),
       ('CH', 'Switzerland'), ('SY', 'Syria'), ('TW', 'Taiwan'), ('TJ', 'Tajikistan'), ('TZ', 'Tanzania'), ('TH', 'Thailand'),
       ('TG', 'Togo'), ('TK', 'Tokelau'), ('TO', 'Tonga'), ('TT', 'Trinidad and Tobago'), ('TN', 'Tunisia'), ('TR', 'Turkey'),
       ('TM', 'Turkmenistan'), ('TC', 'Turks and Caicos Islands'), ('TV', 'Tuvalu'), ('UK', 'United Kingdom'), ('UG', 'Uganda'), ('UA', 'Ukraine'),
       ('AE', 'United Arab Emirates'), ('UY', 'Uruguay'), ('US', 'United States'), ('UM', 'USA Minor Islands'), ('UZ', 'Uzbekistan'), ('VU', 'Vanuatu'),
       ('VA', 'Vatican City'), ('VE', 'Venezuela'), ('VN', 'Vietnam'), ('VG', 'Virgin Islands (British)'), ('VI', 'Virgin Islands (USA)'),
       ('WF', 'Wallis and Futuna Islands'), ('EH', 'Western Sahara'), ('YE', 'Yemen'), ('ZM', 'Zambia'), ('ZW', 'Zimbabwe');

UPDATE "Country"
   SET "label" = CONCAT('lblCountry', "code"),
       "created_at" = CURRENT_DATE,
       "updated_at" = CURRENT_DATE;

UPDATE "Country"
   SET "sort_order" = CASE WHEN "code" = 'JP' THEN 0
                           WHEN "code" IN ('CA', 'US') THEN 10
                           ELSE 25 END,
       "is_available" = true
 WHERE "code" IN ('JP', 'US', 'CA', 'MX', 'CO');

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_country_before_update ON "Country";
CREATE TRIGGER trg_country_before_update
  BEFORE UPDATE ON "Country"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_country_before_delete ON "Country";
CREATE OR REPLACE RULE rule_country_before_delete AS
    ON DELETE TO "Country"
    DO INSTEAD
       UPDATE "Country" SET "is_available" = false, "is_deleted" = true WHERE "Country"."code" = OLD."code";

DROP TABLE IF EXISTS "CountryRegion";
CREATE TABLE IF NOT EXISTS "CountryRegion" (
    "id"                serial                          ,
    "country_code"      varchar(2)          NOT NULL    ,
    "name"              varchar(256)        NOT NULL    DEFAULT '',
    "short_code"        varchar(8)              NULL    ,
    "label"             varchar(64)             NULL    ,
    "sort_order"        smallint            NOT NULL    DEFAULT 50      CHECK ("sort_order" BETWEEN 0 AND 999),

    "is_deleted"        boolean             NOT NULL    DEFAULT false,
    "created_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("country_code") REFERENCES "Country" ("code")
);
CREATE INDEX idx_region_main ON "CountryRegion" ("country_code");

INSERT INTO "CountryRegion" ("name", "country_code", "label", "sort_order")
VALUES ('Hokkaido', 'JP', 'lblJPRegion01', 10),
       ('Kanto', 'JP', 'lblJPRegion02', 20),
       ('Chubu', 'JP', 'lblJPRegion03', 30),
       ('Kansai', 'JP', 'lblJPRegion04', 40),
       ('Chukoku', 'JP', 'lblJPRegion05', 50),
       ('Shikoku', 'JP', 'lblJPRegion06', 60),
       ('Kyushu and Okinawa', 'JP', 'lblJPRegion07', 70);

UPDATE "CountryRegion"
   SET "created_at" = CURRENT_DATE,
       "updated_at" = CURRENT_DATE;

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_countryregion_before_update ON "CountryRegion";
CREATE TRIGGER trg_countryregion_before_update
  BEFORE UPDATE ON "CountryRegion"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_countryregion_before_delete ON "CountryRegion";
CREATE OR REPLACE RULE rule_countryregion_before_delete AS
    ON DELETE TO "CountryRegion"
    DO INSTEAD
       UPDATE "CountryRegion" SET "is_deleted" = true WHERE "CountryRegion"."id" = OLD."id";

DROP TABLE IF EXISTS "CountryState";
CREATE TABLE IF NOT EXISTS "CountryState" (
    "id"                serial                          ,
    "country_code"      varchar(2)          NOT NULL    ,
    "name"              varchar(256)        NOT NULL    DEFAULT '',
    "short_code"        varchar(8)              NULL    ,
    "label"             varchar(64)             NULL    ,
    "region_id"         integer                 NULL    ,
    "sort_order"        smallint            NOT NULL    DEFAULT 50      CHECK ("sort_order" BETWEEN 0 AND 999),

    "is_deleted"        boolean             NOT NULL    DEFAULT false,
    "created_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("country_code") REFERENCES "Country" ("code"),
    FOREIGN KEY ("region_id") REFERENCES "CountryRegion" ("id")
);
CREATE INDEX idx_state_main ON "CountryState" ("country_code");

INSERT INTO "CountryState" ("name", "country_code", "label", "region_id", "sort_order")
VALUES ('Hokkaido', 'JP', 'lblHokkaido', 1, 10),
       ('Aomori', 'JP', 'lblAomori', 1, 20),        ('Iwate', 'JP', 'lblIwate', 1, 20),       ('Yamagata', 'JP', 'lblYamagata', 1, 20),
       ('Miyagi', 'JP', 'lblMiyagi', 1, 20),        ('Akita', 'JP', 'lblAkita', 1, 20),       ('Fukushima', 'JP', 'lblFukushima', 1, 20),
       ('Ibaraki', 'JP', 'lblIbaraki', 2, 30),       ('Tochigi', 'JP', 'lblTochigi', 2, 30),    ('Gunma', 'JP', 'lblGunma', 2, 30),
       ('Saitama', 'JP', 'lblSaitama', 2, 30),       ('Chiba', 'JP', 'lblChiba', 2, 30),       ('Tokyo', 'JP', 'lblTokyo', 2, 30),
       ('Kanagawa', 'JP', 'lblKanagawa', 2, 30),
       ('Niigata', 'JP', 'lblNiigata', 3, 40),       ('Toyama', 'JP', 'lblToyama', 3, 40),      ('Ishikawa', 'JP', 'lblIshikawa', 3, 40),
       ('Fukui', 'JP', 'lblFukui', 3, 40),          ('Yamanashi', 'JP', 'lblYamanashi', 3, 40),('Nagano', 'JP', 'lblNagano', 3, 40),
       ('Gifu', 'JP', 'lblGifu', 3, 40),            ('Shizuoka', 'JP', 'lblShizuoka', 3, 40),  ('Aichi', 'JP', 'lblAichi', 3, 40),
       ('Mie', 'JP', 'lblMie', 4, 50),              ('Shiga', 'JP', 'lblShiga', 4, 50),       ('Kyoto', 'JP', 'lblKyoto', 4, 50),
       ('Osaka', 'JP', 'lblOsaka', 4, 50),          ('Hyogo', 'JP', 'lblHyogo', 4, 50),       ('Nara', 'JP', 'lblNara', 4, 50),
       ('Wakayama', 'JP', 'lblWakayama', 4, 50),
       ('Tottori', 'JP', 'lblTottori', 5, 60),       ('Shimane', 'JP', 'lblShimane', 5, 60),    ('Okayama', 'JP', 'lblOkayama', 5, 60),
       ('Hiroshima', 'JP', 'lblHiroshima', 5, 60),     ('Yamaguchi', 'JP', 'lblYamaguchi', 5, 60),
       ('Tokushima', 'JP', 'lblTokushima', 6, 70),     ('Kagawa', 'JP', 'lblKagawa', 6, 70),      ('Ehime', 'JP', 'lblEhime', 6, 70),
       ('Kochi', 'JP', 'lblKochi', 6, 70),
       ('Fukuoka', 'JP', 'lblFukuoka', 7, 80),       ('Saga', 'JP', 'lblSaga', 7, 80),       ('Nagasaki', 'JP', 'lblNagasaki', 7, 80),
       ('Kumamoto', 'JP', 'lblKumamoto', 7, 80),       ('Oita', 'JP', 'lblOita', 7, 80),       ('Miyazaki', 'JP', 'lblMiyazaki', 7, 80),
       ('Kagoshima', 'JP', 'lblKagoshima', 7, 80),
       ('Okinawa', 'JP', 'lblOkinawa', 7, 90);

INSERT INTO "CountryState" ("name", "country_code", "label", "sort_order")
VALUES ('Alberta', 'CA', 'lblAlberta', 30), ('British Columbia', 'CA', 'lblBritCol', 30), ('Manitoba', 'CA', 'lblManitoba', 30),
       ('New Brunswick', 'CA', 'lblNewBrun', 30), ('Newfoundland', 'CA', 'lblNewfoundland', 30), ('Nova Scotia', 'CA', 'lblNovaScotia', 30),
       ('Ontario', 'CA', 'lblOntario', 30), ('Prince Edward Island', 'CA', 'lblPEI', 30), ('Quebec', 'CA', 'lblQuebec', 30),
       ('Saskatchewan', 'CA', 'lblSaskatchewan', 30), ('Northwest Territories', 'CA', 'lblNWTerr', 50), ('Nunavut', 'CA', 'lblNunavut', 50),
       ('Yukon', 'CA', 'lblYukon', 50);

INSERT INTO "CountryState" ("name", "country_code")
VALUES ('Alabama', 'US'), ('Alaska', 'US'), ('Arizona', 'US'), ('Arkansas', 'US'), ('California', 'US'), ('Colorado', 'US'), ('Connecticut', 'US'),
       ('Delaware', 'US'), ('Florida', 'US'), ('Georgia', 'US'), ('Hawaii', 'US'), ('Idaho', 'US'), ('Illinois', 'US'), ('Indiana', 'US'), ('Iowa', 'US'),
       ('Kansas', 'US'), ('Kentucky', 'US'), ('Louisiana', 'US'), ('Maine', 'US'), ('Maryland', 'US'), ('Massachusetts', 'US'), ('Michigan', 'US'),
       ('Minnesota', 'US'), ('Mississippi', 'US'), ('Missouri', 'US'), ('Montana', 'US'), ('Nebraska', 'US'), ('Nevada', 'US'), ('New Hampshire', 'US'),
       ('New Jersey', 'US'), ('New Mexico', 'US'), ('New York', 'US'), ('North Carolina', 'US'), ('North Dakota', 'US'), ('Ohio', 'US'), ('Oklahoma', 'US'),
       ('Oregon', 'US'), ('Pennsylvania', 'US'), ('Rhode Island', 'US'), ('South Carolina', 'US'), ('South Dakota', 'US'), ('Tennessee', 'US'),
       ('Texas', 'US'), ('Utah', 'US'), ('Vermont', 'US'), ('Virginia', 'US'), ('Washington', 'US'), ('West Virginia', 'US'), ('Wisconsin', 'US'),
       ('Wyoming', 'US'), ('District of Columbia', 'US');

INSERT INTO "CountryState" ("name", "country_code", "label", "sort_order")
VALUES ('Aguascalientes', 'MX', 'lblMX001', 50),
       ('Baja California', 'MX', 'lblMX002', 50),
       ('Baja California Sur', 'MX', 'lblMX003', 50),
       ('Campeche', 'MX', 'lblMX004', 50),
       ('Chiapas', 'MX', 'lblMX005', 50),
       ('Ciudad de México', 'MX', 'lblMX006', 50),
       ('Chihuahua', 'MX', 'lblMX007', 50),
       ('Coahuila de Zaragoza', 'MX', 'lblMX008', 50),
       ('Colima', 'MX', 'lblMX009', 50),
       ('Durango', 'MX', 'lblMX010', 50),
       ('Guanajuato', 'MX', 'lblMX011', 50),
       ('Guerrero', 'MX', 'lblMX012', 50),
       ('Hidalgo', 'MX', 'lblMX013', 50),
       ('Jalisco', 'MX', 'lblMX014', 50),
       ('México', 'MX', 'lblMX015', 50),
       ('Michoacán de Ocampo', 'MX', 'lblMX016', 50),
       ('Morelos', 'MX', 'lblMX017', 50),
       ('Nayarit', 'MX', 'lblMX018', 50),
       ('Nuevo León', 'MX', 'lblMX019', 50),
       ('Oaxaca', 'MX', 'lblMX020', 50),
       ('Puebla', 'MX', 'lblMX021', 50),
       ('Querétaro de Arteaga', 'MX', 'lblMX022', 50),
       ('Quintana Roo', 'MX', 'lblMX023', 50),
       ('San Luis Potosí', 'MX', 'lblMX024', 50),
       ('Sinaloa', 'MX', 'lblMX025', 50),
       ('Sonora', 'MX', 'lblMX026', 50),
       ('Tabasco', 'MX', 'lblMX027', 50),
       ('Tamaulipas', 'MX', 'lblMX028', 50),
       ('Tlaxcala', 'MX', 'lblMX029', 50),
       ('Veracruz de Ignacio de la Llave', 'MX', 'lblMX030', 50),
       ('Yucatán', 'MX', 'lblMX031', 50),
       ('Zacatecas', 'MX', 'lblMX032', 50);

INSERT INTO "CountryState" ("country_code", "name", "label", "sort_order")
VALUES ('CO', 'Capital District', 'lblCO000', 0),
       ('CO', 'Amazonas', 'lblCO001', 5),
       ('CO', 'Antioquia', 'lblCO002', 10),
       ('CO', 'Arauca', 'lblCO003', 15),
       ('CO', 'Atlántico', 'lblCO004', 20),
       ('CO', 'Bolívar', 'lblCO005', 25),
       ('CO', 'Boyacá', 'lblCO006', 30),
       ('CO', 'Caldas', 'lblCO007', 35),
       ('CO', 'Caquetá', 'lblCO008', 40),
       ('CO', 'Casanare', 'lblCO009', 45),
       ('CO', 'Cauca', 'lblCO010', 50),
       ('CO', 'Cesar', 'lblCO011', 55),
       ('CO', 'Chocó', 'lblCO012', 60),
       ('CO', 'Córdoba', 'lblCO013', 65),
       ('CO', 'Cundinamarca', 'lblCO014', 70),
       ('CO', 'Guainía', 'lblCO015', 75),
       ('CO', 'Guaviare', 'lblCO016', 80),
       ('CO', 'Huila', 'lblCO017', 85),
       ('CO', 'La Guajira', 'lblCO018', 90),
       ('CO', 'Magdalena', 'lblCO019', 95),
       ('CO', 'Meta', 'lblCO020', 100),
       ('CO', 'Nariño', 'lblCO021', 105),
       ('CO', 'Norte de Santander', 'lblCO022', 110),
       ('CO', 'Putumayo', 'lblCO023', 115),
       ('CO', 'Quindío', 'lblCO024', 120),
       ('CO', 'Risaralda', 'lblCO025', 125),
       ('CO', 'San Andrés y Providencia', 'lblCO026', 130),
       ('CO', 'Santander', 'lblCO027', 135),
       ('CO', 'Sucre', 'lblCO028', 140),
       ('CO', 'Tolima', 'lblCO029', 145),
       ('CO', 'Valle del Cauca', 'lblCO030', 150),
       ('CO', 'Vaupés', 'lblCO031', 155),
       ('CO', 'Vichada', 'lblCO032', 160);

UPDATE "CountryState"
   SET "created_at" = CURRENT_DATE,
       "updated_at" = CURRENT_DATE;

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_countrystate_before_update ON "CountryState";
CREATE TRIGGER trg_countrystate_before_update
  BEFORE UPDATE ON "CountryState"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_countrystate_before_delete ON "CountryState";
CREATE OR REPLACE RULE rule_countrystate_before_delete AS
    ON DELETE TO "CountryState"
    DO INSTEAD
       UPDATE "CountryState" SET "is_deleted" = true WHERE "CountryState"."id" = OLD."id";

DROP TABLE IF EXISTS "Language";
CREATE TABLE IF NOT EXISTS "Language" (
    "code"          char(2)             NOT NULL    ,
    "name"          varchar(80)         NOT NULL    ,

    "sort_order"    smallint            NOT NULL    DEFAULT 50      CHECK ("sort_order" BETWEEN 0 AND 999),
    "is_available"  boolean             NOT NULL    DEFAULT false,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("code")
);
CREATE INDEX idx_lang_main ON "Language" ("is_available" DESC);

DROP TABLE IF EXISTS "Locale";
CREATE TABLE IF NOT EXISTS "Locale" (
    "code"          varchar(6)          NOT NULL    ,
    "language_code" char(2)             NOT NULL    ,
    "name"          varchar(80)         NOT NULL    DEFAULT '',

    "is_available"  boolean             NOT NULL    DEFAULT true,
    "is_default"    boolean             NOT NULL    DEFAULT false,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("code"),
    FOREIGN KEY ("language_code") REFERENCES "Language" ("code")
);
CREATE INDEX idx_locale_main ON "Locale" ("is_available" DESC);
CREATE INDEX idx_locale_def ON "Locale" ("is_default" DESC);

INSERT INTO "Language" ("code", "name", "is_available", "sort_order")
VALUES ('ar', 'Arabic', false, 50),
       ('be', 'Belarusian', false, 50),
       ('bg', 'Bulgarian', false, 50),
       ('ca', 'Catalan', false, 50),
       ('cs', 'Czech', false, 50),
       ('da', 'Danish', false, 50),
       ('de', 'German', true, 20),
       ('el', 'Greek', false, 50),
       ('en', 'English', true, 10),
       ('es', 'Spanish', true, 20),
       ('et', 'Estonian', false, 50),
       ('eu', 'Basque', false, 50),
       ('fi', 'Finnish', false, 50),
       ('fo', 'Faroese', false, 50),
       ('fr', 'French', true, 20),
       ('gl', 'Galician', false, 50),
       ('gu', 'Gujarati', false, 50),
       ('he', 'Hebrew', false, 50),
       ('hi', 'Hindi', false, 50),
       ('hr', 'Croatian', false, 50),
       ('hu', 'Hungarian', false, 50),
       ('id', 'Indonesian', false, 50),
       ('is', 'Icelandic', false, 50),
       ('it', 'Italian', false, 50),
       ('ja', 'Japanese', true, 20),
       ('ko', 'Korean', true, 30),
       ('lt', 'Lithuanian', false, 50),
       ('lv', 'Latvian', false, 50),
       ('mk', 'Macedonian', false, 50),
       ('mn', 'Mongolia', false, 50),
       ('ms', 'Malay', false, 50),
       ('nb', 'Norwegian (Bokmål)', false, 50),
       ('nl', 'Dutch', false, 50),
       ('no', 'Norwegian', false, 50),
       ('pl', 'Polish', false, 50),
       ('pt', 'Portugese', false, 50),
       ('rm', 'Romansh', false, 50),
       ('ro', 'Romanian', false, 50),
       ('ru', 'Russian', true, 30),
       ('sk', 'Slovak', false, 50),
       ('sl', 'Slovenian', false, 50),
       ('sq', 'Albanian', false, 50),
       ('sr', 'Serbian', false, 50),
       ('sv', 'Swedish', false, 50),
       ('ta', 'Tamil', false, 50),
       ('te', 'Telugu', false, 50),
       ('th', 'Thai', false, 50),
       ('tg', 'Tagalog', false, 50),
       ('tr', 'Turkish', false, 50),
       ('uk', 'Ukrainian', false, 50),
       ('ur', 'Urdu', false, 50),
       ('vi', 'Vietnamese', false, 50),
       ('zh', 'Chinese', false, 50);

INSERT INTO "Locale" ("code", "language_code", "is_available", "name")
VALUES ('ar_AE', 'ar', false, 'United Arab Emirates'),
       ('ar_BH', 'ar', false, 'Bahrain'),
       ('ar_DZ', 'ar', false, 'Algeria'),
       ('ar_EG', 'ar', false, 'Egypt'),
       ('ar_IN', 'ar', false, 'India'),
       ('ar_IQ', 'ar', false, 'Iraq'),
       ('ar_JO', 'ar', false, 'Jordan'),
       ('ar_KW', 'ar', false, 'Kuwait'),
       ('ar_LB', 'ar', false, 'Lebanon'),
       ('ar_LY', 'ar', false, 'Libya'),
       ('ar_MA', 'ar', false, 'Morocco'),
       ('ar_OM', 'ar', false, 'Oman'),
       ('ar_QA', 'ar', false, 'Qatar'),
       ('ar_SA', 'ar', false, 'Saudi Arabia'),
       ('ar_SD', 'ar', false, 'Sudan'),
       ('ar_SY', 'ar', false, 'Syria'),
       ('ar_TN', 'ar', false, 'Tunisia'),
       ('ar_YE', 'ar', false, 'Yemen'),
       ('be_BY', 'be', false, 'Belarus'),
       ('bg_BG', 'bg', false, 'Bulgaria'),
       ('ca_ES', 'ca', false, 'Spain'),
       ('cs_CZ', 'cs', false, 'Czech Republic'),
       ('da_DK', 'da', false, 'Denmark'),
       ('de_AT', 'de', false, 'Austria'),
       ('de_BE', 'de', false, 'Belgium'),
       ('de_CH', 'de', false, 'Switzerland'),
       ('de_DE', 'de', true, 'Germany'),
       ('de_LU', 'de', false, 'Luxembourg'),
       ('el_GR', 'el', false, 'Greece'),
       ('en_AU', 'en', false, 'Australia'),
       ('en_CA', 'en', false, 'Canada'),
       ('en_GB', 'en', true, 'United Kingdom'),
       ('en_IN', 'en', false, 'India'),
       ('en_NZ', 'en', false, 'New Zealand'),
       ('en_PH', 'en', false, 'Philippines'),
       ('en_US', 'en', true, 'United States'),
       ('en_ZA', 'en', false, 'South Africa'),
       ('en_ZW', 'en', false, 'Zimbabwe'),
       ('es_AR', 'es', false, 'Argentina'),
       ('es_BO', 'es', false, 'Bolivia'),
       ('es_CL', 'es', false, 'Chile'),
       ('es_CO', 'es', false, 'Colombia'),
       ('es_CR', 'es', false, 'Costa Rica'),
       ('es_DO', 'es', false, 'Dominican Republic'),
       ('es_EC', 'es', false, 'Ecuador'),
       ('es_ES', 'es', true, 'Spain'),
       ('es_GT', 'es', false, 'Guatemala'),
       ('es_HN', 'es', false, 'Honduras'),
       ('es_MX', 'es', false, 'Mexico'),
       ('es_NI', 'es', false, 'Nicaragua'),
       ('es_PA', 'es', false, 'Panama'),
       ('es_PE', 'es', false, 'Peru'),
       ('es_PR', 'es', false, 'Puerto Rico'),
       ('es_PY', 'es', false, 'Paraguay'),
       ('es_SV', 'es', false, 'El Salvador'),
       ('es_US', 'es', false, 'United States'),
       ('es_UY', 'es', false, 'Uruguay'),
       ('es_VE', 'es', false, 'Venezuela'),
       ('et_EE', 'et', false, 'Estonia'),
       ('eu_ES', 'eu', false, 'Basque'),
       ('fi_FI', 'fi', false, 'Finland'),
       ('fo_FO', 'fo', false, 'Faroe Islands'),
       ('fr_BE', 'fr', false, 'Belgium'),
       ('fr_CA', 'fr', false, 'Canada'),
       ('fr_CH', 'fr', false, 'Switzerland'),
       ('fr_FR', 'fr', true, 'France'),
       ('fr_LU', 'fr', false, 'Luxembourg'),
       ('gl_ES', 'gl', false, 'Spain'),
       ('gu_IN', 'gu', false, 'India'),
       ('he_IL', 'he', false, 'Israel'),
       ('hi_IN', 'hi', false, 'India'),
       ('hr_HR', 'hr', false, 'Croatia'),
       ('hu_HU', 'hu', false, 'Hungary'),
       ('id_ID', 'id', false, 'Indonesia'),
       ('is_IS', 'is', false, 'Iceland'),
       ('it_CH', 'it', false, 'Switzerland'),
       ('it_IT', 'it', false, 'Italy'),
       ('ja_JP', 'ja', true, 'Japan'),
       ('ko_KR', 'ko', true, 'Republic of Korea'),
       ('lt_LT', 'lt', false, 'Lithuania'),
       ('lv_LV', 'lv', false, 'Latvia'),
       ('mk_MK', 'mk', false, 'FYROM'),
       ('mn_MN', 'mn', false, 'Mongolian'),
       ('ms_MY', 'ms', false, 'Malaysia'),
       ('nb_NO', 'nb', false, 'Norway'),
       ('nl_BE', 'nl', false, 'Belgium'),
       ('nl_NL', 'nl', false, 'The Netherlands'),
       ('no_NO', 'no', false, 'Norway'),
       ('pl_PL', 'pl', false, 'Poland'),
       ('pt_BR', 'pt', false, 'Brazil'),
       ('pt_PT', 'pt', false, 'Portugal'),
       ('rm_CH', 'rm', false, 'Switzerland'),
       ('ro_RO', 'ro', false, 'Romania'),
       ('ru_RU', 'ru', true, 'Russia'),
       ('ru_UA', 'ru', false, 'Ukraine'),
       ('sk_SK', 'sk', false, 'Slovakia'),
       ('sl_SI', 'sl', false, 'Slovenia'),
       ('sq_AL', 'sq', false, 'Albania'),
       ('sr_RS', 'sr', false, 'Yugoslavia'),
       ('sv_FI', 'sv', false, 'Finland'),
       ('sv_SE', 'sv', false, 'Sweden'),
       ('ta_IN', 'ta', false, 'India'),
       ('te_IN', 'te', false, 'India'),
       ('th_TH', 'th', false, 'Thailand'),
       ('tr_TR', 'tr', false, 'Turkey'),
       ('uk_UA', 'uk', false, 'Ukraine'),
       ('ur_PK', 'ur', false, 'Pakistan'),
       ('vi_VN', 'vi', false, 'Viet Nam'),
       ('zh_CN', 'zh', false, 'China'),
       ('zh_HK', 'zh', false, 'Hong Kong'),
       ('zh_TW', 'zh', false, 'Taiwan Province of China');

UPDATE "Language"
   SET "created_at" = CURRENT_DATE,
       "updated_at" = CURRENT_DATE;

UPDATE "Language"
   SET "is_available" = true
 WHERE "code" IN ('en', 'es', 'ja', 'ko', 'ru');

UPDATE "Locale"
   SET "created_at" = CURRENT_DATE,
       "updated_at" = CURRENT_DATE;

UPDATE "Locale"
   SET "is_available" = true
 WHERE "code" IN ('en_US', 'es_MX', 'ja_JP', 'ko_KR', 'ru_RU');

UPDATE "Locale"
   SET "is_default" = true
 WHERE "code" = 'en_US';

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_language_before_update ON "Language";
CREATE TRIGGER trg_language_before_update
  BEFORE UPDATE ON "Language"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_language_before_delete ON "Language";
CREATE OR REPLACE RULE rule_language_before_delete AS
    ON DELETE TO "Language"
    DO INSTEAD
       UPDATE "Language" SET "is_available" = false, "is_deleted" = true WHERE "Language"."code" = OLD."code";

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_locale_before_update ON "Locale";
CREATE TRIGGER trg_locale_before_update
  BEFORE UPDATE ON "Locale"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_locale_before_delete ON "Locale";
CREATE OR REPLACE RULE rule_locale_before_delete AS
    ON DELETE TO "Locale"
    DO INSTEAD
       UPDATE "Locale" SET "is_available" = false, "is_deleted" = true WHERE "Locale"."code" = OLD."code";

/** ************************************************************************* *
 *  Create Sequence (Sites)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Site";
CREATE TABLE IF NOT EXISTS "Site" (
    "id"            serial                          ,

    "name"          varchar(256)        NOT NULL    ,
    "description"   varchar(512)        NOT NULL    DEFAULT '',
    "keywords"      varchar(1024)       NOT NULL    DEFAULT '',
    "locale_code"   varchar(6)          NOT NULL    DEFAULT 'en_us',

    "https"         boolean             NOT NULL    DEFAULT false,
    "theme"         varchar(20)         NOT NULL    DEFAULT 'error',
    "guid"          char(36)     UNIQUE NOT NULL    ,

    "is_default"    boolean             NOT NULL    DEFAULT false,
    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("locale_code") REFERENCES "Locale" ("code")
);
CREATE INDEX idx_site_defs ON "Site" ("is_default");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_site_before_insert ON "Site";
CREATE TRIGGER trg_site_before_insert
  BEFORE INSERT ON "Site"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_site_before_update ON "Site";
CREATE TRIGGER trg_site_before_update
  BEFORE UPDATE ON "Site"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_site_before_delete ON "Site";
CREATE OR REPLACE RULE rule_site_before_delete AS
    ON DELETE TO "Site"
    DO INSTEAD
       UPDATE "Site" SET "is_deleted" = true WHERE "Site"."id" = OLD."id";

DROP TABLE IF EXISTS "SiteUrl";
CREATE TABLE IF NOT EXISTS "SiteUrl" (
    "id"            serial                          ,
    "site_id"       integer             NOT NULL    ,
    "url"           varchar(160)        NOT NULL    ,
    "is_active"     boolean             NOT NULL    DEFAULT true,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("site_id") REFERENCES "Site" ("id")
);
CREATE INDEX idx_surl_main ON "SiteUrl" ("url");

/* Before INSERT */
CREATE OR REPLACE FUNCTION siteurl_before_insert()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    /* Ensure the Site.updated_at Value is Properly Updated */
    UPDATE "Site" SET "updated_at" = CURRENT_TIMESTAMP WHERE "Site"."id" = NEW."site_id";

    /* If this is an active URL, ensure the others are de-activated */
    IF NEW."is_active" = true THEN
        UPDATE "SiteUrl" SET "is_active" = false WHERE "SiteUrl"."site_id" = NEW."site_id" and "SiteUrl"."is_active" = true;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_siteurl_before_insert ON "SiteUrl";
CREATE TRIGGER trg_siteurl_before_insert
  BEFORE INSERT ON "SiteUrl"
    FOR EACH ROW
  EXECUTE PROCEDURE siteurl_before_insert();

/* Before UPDATE */
CREATE OR REPLACE FUNCTION siteurl_before_update()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    /* Ensure the Site.updated_at Value is Properly Updated */
    UPDATE "Site" SET "updated_at" = CURRENT_TIMESTAMP WHERE "Site"."id" = NEW."site_id";

    /* If this is an active URL, ensure the others are de-activated */
    IF NEW."is_active" = true THEN
        UPDATE "SiteUrl" SET "is_active" = false WHERE "SiteUrl"."site_id" = NEW."site_id" and "SiteUrl"."is_active" = true and "SiteUrl"."id" <> NEW."id";
    END IF;

    NEW."updated_at" = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_siteurl_before_update ON "SiteUrl";
CREATE TRIGGER trg_siteurl_before_update
  BEFORE UPDATE ON "SiteUrl"
    FOR EACH ROW
  EXECUTE PROCEDURE siteurl_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_siteurl_before_delete ON "SiteUrl";
CREATE OR REPLACE RULE rule_siteurl_before_delete AS
    ON DELETE TO "SiteUrl"
    DO INSTEAD
       UPDATE "SiteUrl" SET "is_deleted" = true WHERE "SiteUrl"."id" = OLD."id";

DROP TABLE IF EXISTS "SiteMeta";
CREATE TABLE IF NOT EXISTS "SiteMeta" (
    "site_id"       integer             NOT NULL    ,
    "key"           varchar(64)         NOT NULL    ,
    "value"         text                NOT NULL    DEFAULT '',

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("site_id", "key"),
    FOREIGN KEY ("site_id") REFERENCES "Site" ("id")
);
CREATE INDEX idx_smeta_site ON "SiteMeta" ("site_id");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_sitemeta_before_insert ON "SiteMeta";
CREATE TRIGGER trg_sitemeta_before_insert
  BEFORE INSERT ON "SiteMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_sitemeta_before_update ON "SiteMeta";
CREATE TRIGGER trg_sitemeta_before_update
  BEFORE UPDATE ON "SiteMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before DELETE */
DROP RULE IF EXISTS rule_sitemeta_before_delete ON "SiteMeta";
CREATE OR REPLACE RULE rule_sitemeta_before_delete AS
    ON DELETE TO "SiteMeta"
    DO INSTEAD
       UPDATE "SiteMeta" SET "is_deleted" = true WHERE "SiteMeta"."site_id" = OLD."site_id" and "SiteMeta"."key" = OLD."key";

/** ************************************************************************* *
 *  Create Sequence (Account)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Account";
CREATE TABLE IF NOT EXISTS "Account" (
    "id"            serial                          ,
    "login"         varchar(64)  UNIQUE NOT NULL    ,
    "password"      varchar(192)        NOT NULL    DEFAULT '',

    "last_name"     varchar(120)        NOT NULL    ,
    "first_name"    varchar(120)        NOT NULL    ,
    "display_name"  varchar(80)         NOT NULL    DEFAULT '',
    "gender"        char(1)             NOT NULL    DEFAULT '-',

    "email"         varchar(160) UNIQUE     NULL    CHECK ("email" LIKE '%@%.%'),
    "locale_code"   varchar(6)          NOT NULL    DEFAULT 'ja_jp',
    "timezone"      varchar(64)         NOT NULL    DEFAULT 'UTC',

    "type"          varchar(64)         NOT NULL    DEFAULT 'account.expired',
    "guid"          char(36)     UNIQUE NOT NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("locale_code") REFERENCES "Locale" ("code")
);
CREATE INDEX idx_account_main ON "Account" ("login", "email");
CREATE INDEX idx_account_guid ON "Account" ("guid");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_account_before_insert ON "Account";
CREATE TRIGGER trg_account_before_insert
  BEFORE INSERT ON "Account"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_account_before_update ON "Account";
CREATE TRIGGER trg_account_before_update
  BEFORE UPDATE ON "Account"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_account_before_delete ON "Account";
CREATE OR REPLACE RULE rule_account_before_delete AS
    ON DELETE TO "Account"
    DO INSTEAD
       UPDATE "Account"
          SET "is_deleted" = true,
              "type" = 'account.expired'
        WHERE "Account"."id" = OLD."id";

DROP TABLE IF EXISTS "AccountMeta";
CREATE TABLE IF NOT EXISTS "AccountMeta" (
    "account_id"    integer             NOT NULL    ,
    "key"           varchar(64)         NOT NULL    ,
    "value"         varchar(2048)       NOT NULL    DEFAULT '',

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("account_id", "key"),
    FOREIGN KEY ("account_id") REFERENCES "Account" ("id")
);
CREATE INDEX idx_acctmeta_main ON "AccountMeta" ("account_id", "is_deleted");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_accountmeta_before_insert ON "AccountMeta";
CREATE TRIGGER trg_accountmeta_before_insert
  BEFORE INSERT ON "AccountMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_accountmeta_before_update ON "AccountMeta";
CREATE TRIGGER trg_accountmeta_before_update
  BEFORE UPDATE ON "AccountMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before DELETE */
DROP RULE IF EXISTS rule_accountmeta_before_delete ON "AccountMeta";
CREATE OR REPLACE RULE rule_accountmeta_before_delete AS
    ON DELETE TO "AccountMeta"
    DO INSTEAD
       UPDATE "AccountMeta" SET "is_deleted" = true WHERE "AccountMeta"."account_id" = OLD."account_id" and "AccountMeta"."key" = OLD."key";

/** ************************************************************************* *
 *  Create Sequence (Tokens)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Tokens";
CREATE TABLE IF NOT EXISTS "Tokens" (
    "id"            serial                          ,
    "guid"          varchar(50)         NOT NULL    ,
    "account_id"    integer             NOT NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("account_id") REFERENCES "Account" ("id")
);
CREATE INDEX idx_token_acct ON "Tokens" ("account_id");
CREATE INDEX idx_token_guid ON "Tokens" ("guid");

DROP TRIGGER IF EXISTS trg_tokens_before_update ON "Tokens";
CREATE TRIGGER trg_tokens_before_update
  BEFORE UPDATE ON "Tokens"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_tokens_before_delete ON "Tokens";
CREATE OR REPLACE RULE rule_tokens_before_delete AS
    ON DELETE TO "Tokens"
    DO INSTEAD
       UPDATE "Tokens" SET "is_deleted" = true WHERE "Tokens"."id" = OLD."id";

/** ************************************************************************* *
 *  Create Sequence (Note)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Note";
CREATE TABLE IF NOT EXISTS "Note" (
    "id"            serial                          ,
    "type"          varchar(64)         NOT NULL    DEFAULT 'note.normal'   CHECK ("type" LIKE 'note.%'),
    "content"       text                NOT NULL    ,

    "is_private"    boolean             NOT NULL    DEFAULT false,
    "guid"          char(36)     UNIQUE NOT NULL    ,
    "hash"          char(128)           NOT NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_by"    integer             NOT NULL    ,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("updated_by") REFERENCES "Account" ("id")
);
CREATE INDEX idx_note_guid ON "Note" ("guid");

/* Before INSERT */
CREATE OR REPLACE FUNCTION note_before_insert()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."hash" = encode(sha512(CAST(CONCAT(NEW."type", REPLACE(NEW."content", '\', '\\'), CASE WHEN NEW."is_private" = true THEN 'Y' ELSE 'N' END::char(1)) AS bytea)), 'hex');
    NEW."guid" = uuid_generate_v4();

    RETURN NEW;
END;
$$;

/*
\\';
*/

DROP TRIGGER IF EXISTS trg_note_before_insert ON "Note";
CREATE TRIGGER trg_note_before_insert
  BEFORE INSERT ON "Note"
    FOR EACH ROW
  EXECUTE PROCEDURE note_before_insert();

/* Before UPDATE */
CREATE OR REPLACE FUNCTION note_before_update()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    IF NEW."guid" <> OLD."guid" THEN
        NEW."guid" = OLD."guid";
    END IF;

    NEW."hash" = encode(sha512(CAST(CONCAT(NEW."type", REPLACE(NEW."content", '\', '\\'), CASE WHEN NEW."is_private" = true THEN 'Y' ELSE 'N' END::char(1)) AS bytea)), 'hex');

    IF NEW."hash" <> OLD."hash" THEN
        INSERT INTO "NoteHistory" ("note_id", "type", "content", "is_private", "hash", "is_deleted", "updated_at", "updated_by")
        SELECT OLD."id", OLD."type", OLD."content", OLD."is_private", OLD."hash", OLD."is_deleted", OLD."updated_at", OLD."updated_by";
    END IF;

    NEW."updated_at" = Now();
    RETURN NEW;
END;
$$;

/*
\\';
*/

DROP TRIGGER IF EXISTS trg_note_before_update ON "Note";
CREATE TRIGGER trg_note_before_update
  BEFORE UPDATE ON "Note"
    FOR EACH ROW
  EXECUTE PROCEDURE note_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_note_before_delete ON "Note";
CREATE OR REPLACE RULE rule_note_before_delete AS
    ON DELETE TO "Note"
    DO INSTEAD
       UPDATE "Note" SET "is_deleted" = true WHERE "Note"."id" = OLD."id";

DROP TABLE IF EXISTS "NoteMeta";
CREATE TABLE IF NOT EXISTS "NoteMeta" (
    "note_id"       integer             NOT NULL    ,
    "key"           varchar(64)         NOT NULL    ,
    "value"         varchar(2048)       NOT NULL    DEFAULT '',

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("note_id", "key"),
    FOREIGN KEY ("note_id") REFERENCES "Note" ("id")
);
CREATE INDEX idx_nmeta_site ON "NoteMeta" ("note_id");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_notemeta_before_insert ON "NoteMeta";
CREATE TRIGGER trg_notemeta_before_insert
  BEFORE INSERT ON "NoteMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_notemeta_before_update ON "NoteMeta";
CREATE TRIGGER trg_notemeta_before_update
  BEFORE UPDATE ON "NoteMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before DELETE */
DROP RULE IF EXISTS rule_notemeta_before_delete ON "NoteMeta";
CREATE OR REPLACE RULE rule_notemeta_before_delete AS
    ON DELETE TO "NoteMeta"
    DO INSTEAD
       UPDATE "NoteMeta" SET "is_deleted" = true WHERE "NoteMeta"."note_id" = OLD."note_id" and "NoteMeta"."key" = OLD."key";

DROP TABLE IF EXISTS "NoteHistory";
CREATE TABLE IF NOT EXISTS "NoteHistory" (
    "id"              serial                          ,
    "note_id"         integer             NOT NULL    ,
    "type"            varchar(64)         NOT NULL    ,
    "content"         text                NOT NULL    ,

    "is_private"      boolean             NOT NULL    ,
    "hash"            char(128)           NOT NULL    ,

    "is_deleted"      boolean             NOT NULL    ,
    "updated_at"      timestamp           NOT NULL    ,
    "updated_by"      integer             NOT NULL    ,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("note_id") REFERENCES "Note" ("id"),
    FOREIGN KEY ("updated_by") REFERENCES "Account" ("id")
);
CREATE INDEX idx_notehist_main ON "NoteHistory" ("note_id");

/* NoteHistory Needs to be Immutable */
DROP RULE IF EXISTS rule_notehistory_before_update ON "NoteHistory";
CREATE OR REPLACE RULE rule_notehistory_before_update AS
    ON UPDATE TO "NoteHistory"
    DO INSTEAD NOTHING;

DROP RULE IF EXISTS rule_notehistory_before_delete ON "NoteHistory";
CREATE OR REPLACE RULE rule_notehistory_before_delete AS
    ON DELETE TO "NoteHistory"
    DO INSTEAD NOTHING;

/** ************************************************************************* *
 *  Create Sequence (Files)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "File";
CREATE TABLE IF NOT EXISTS "File" (
    "id"            serial                          ,
    "account_id"    integer             NOT NULL    ,

    "name"          varchar(64)         NOT NULL    ,
    "public_name"   varchar(256)        NOT NULL    ,
    "location"      varchar(256)        NOT NULL    ,
    "bytes"         integer             NOT NULL    CHECK ("bytes" > 0),

    "mimetype"      varchar(64)             NULL    ,
    "start_at"      timestamp               NULL    ,
    "until_at"      timestamp               NULL    CHECK("until_at" > "start_at"),
    "is_readonly"   boolean             NOT NULL    DEFAULT true,
    "is_public"     boolean             NOT NULL    DEFAULT false,

    "guid"          char(36)     UNIQUE NOT NULL    ,
    "hash"          char(128)           NOT NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("account_id") REFERENCES "Account" ("id")
);
CREATE INDEX idx_file_acct ON "File" ("account_id");
CREATE INDEX idx_file_guid ON "File" ("guid");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_file_before_insert ON "File";
CREATE TRIGGER trg_file_before_insert
  BEFORE INSERT ON "File"
    FOR EACH ROW
  EXECUTE PROCEDURE primary_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_file_before_update ON "File";
CREATE TRIGGER trg_file_before_update
  BEFORE UPDATE ON "File"
    FOR EACH ROW
  EXECUTE PROCEDURE primary_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_file_before_delete ON "File";
CREATE OR REPLACE RULE rule_file_before_delete AS
    ON DELETE TO "File"
    DO INSTEAD
       UPDATE "File" SET "is_deleted" = true WHERE "File"."id" = OLD."id";

DROP TABLE IF EXISTS "FileMeta";
CREATE TABLE IF NOT EXISTS "FileMeta" (
    "file_id"       integer             NOT NULL    ,
    "key"           varchar(64)         NOT NULL    ,
    "value"         varchar(2048)       NOT NULL    DEFAULT '',

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("file_id", "key"),
    FOREIGN KEY ("file_id") REFERENCES "File" ("id")
);
CREATE INDEX idx_fmeta_main ON "FileMeta" ("file_id");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_filemeta_before_insert ON "FileMeta";
CREATE TRIGGER trg_filemeta_before_insert
  BEFORE INSERT ON "FileMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_filemeta_before_update ON "FileMeta";
CREATE TRIGGER trg_filemeta_before_update
  BEFORE UPDATE ON "FileMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before DELETE */
DROP RULE IF EXISTS rule_filemeta_before_delete ON "FileMeta";
CREATE OR REPLACE RULE rule_filemeta_before_delete AS
    ON DELETE TO "FileMeta"
    DO INSTEAD
       UPDATE "FileMeta" SET "is_deleted" = true WHERE "FileMeta"."file_id" = OLD."file_id" and "FileMeta"."key" = OLD."key";

DROP TABLE IF EXISTS "FileUrl";
CREATE TABLE IF NOT EXISTS "FileUrl" (
    "id"            serial                          ,
    "file_id"       integer             NOT NULL    ,
    "account_id"    integer             NOT NULL    ,

    "guid"          char(36)     UNIQUE NOT NULL    ,
    "accessed_at"   timestamp               NULL    ,
    "token_id"      integer                 NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("file_id") REFERENCES "File" ("id"),
    FOREIGN KEY ("account_id") REFERENCES "Account" ("id"),
    FOREIGN KEY ("token_id") REFERENCES "Tokens" ("id")
);
CREATE INDEX idx_fileurl_guid ON "FileUrl" ("guid");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_fileurl_before_insert ON "FileUrl";
CREATE TRIGGER trg_fileurl_before_insert
  BEFORE INSERT ON "FileUrl"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_fileurl_before_update ON "FileUrl";
CREATE TRIGGER trg_fileurl_before_update
  BEFORE UPDATE ON "FileUrl"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_fileurl_before_delete ON "FileUrl";
CREATE OR REPLACE RULE rule_fileurl_before_delete AS
    ON DELETE TO "FileUrl"
    DO INSTEAD
       UPDATE "FileUrl" SET "is_deleted" = true WHERE "FileUrl"."id" = OLD."id";

/** ************************************************************************* *
 *  Create Sequence (Statistics)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "UsageStats";
CREATE TABLE IF NOT EXISTS "UsageStats" (
    "id"            serial                          ,
    "site_id"       integer             NOT NULL    ,
    "token_id"      integer                 NULL    ,

    "http_code"     smallint            NOT NULL    DEFAULT 200     CHECK ("http_code" BETWEEN 100 AND 599),
    "request_type"  varchar(8)          NOT NULL    DEFAULT 'GET',
    "request_uri"   varchar(512)        NOT NULL    ,
    "referrer"      varchar(1024)           NULL    ,
    "locale_code"   varchar(6)              NULL    ,

    "is_api"        boolean             NOT NULL    DEFAULT false,
    "is_file"       boolean             NOT NULL    DEFAULT false,

    "event_at"      timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "event_on"      varchar(10)         NOT NULL    ,
    "from_ip"       varchar(64)         NOT NULL    ,

    "agent"         varchar(2048)           NULL    ,
    "platform"      varchar(64)             NULL    ,
    "browser"       varchar(64)         NOT NULL    DEFAULT 'unknown',
    "version"       varchar(64)             NULL    ,

    "seconds"       decimal(16,8)       NOT NULL    DEFAULT 0       CHECK ("seconds" >= 0),
    "sqlops"        smallint            NOT NULL    DEFAULT 0       CHECK ("sqlops" >= 0),
    "message"       varchar(512)            NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("site_id") REFERENCES "Site" ("id"),
    FOREIGN KEY ("token_id") REFERENCES "Tokens" ("id")
);
CREATE INDEX idx_usage_site ON "UsageStats" ("site_id");

/* Before INSERT */
CREATE OR REPLACE FUNCTION usagestats_before_insert()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."request_uri" = LOWER(NEW."request_uri");
    NEW."referrer" = CASE WHEN LENGTH(COALESCE(NEW."referrer", '')) > 3 THEN LOWER(NEW."referrer") ELSE NULL END;
    NEW."event_on" = CURRENT_DATE::varchar(10);

    IF NEW."request_uri" LIKE '%/api/%' THEN
        NEW."is_api" = true;
    END IF;

    IF NEW."is_api" = false AND (NEW."request_uri" LIKE '%/file/%' OR NEW."request_uri" LIKE '%/files/%') THEN
        NEW."is_file" = true;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_usagestat_before_insert ON "UsageStats";
CREATE TRIGGER trg_usagestat_before_insert
  BEFORE INSERT ON "UsageStats"
    FOR EACH ROW
  EXECUTE PROCEDURE usagestats_before_insert();

/** ************************************************************************* *
 *  Create Sequence (Contact)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Contact";
CREATE TABLE IF NOT EXISTS "Contact" (
    "id"                serial                          ,

    "name"              varchar(512)        NOT NULL    ,
    "mail"              varchar(160)        NOT NULL    ,
    "subject"           varchar(512)            NULL    ,
    "message"           text                NOT NULL    ,

    "is_sent"           boolean             NOT NULL    DEFAULT false,
    "is_read"           boolean             NOT NULL    DEFAULT false,

    "guid"              char(36)     UNIQUE NOT NULL    ,
    "hash"              char(128)           NOT NULL    ,

    "is_deleted"        boolean             NOT NULL    DEFAULT false,
    "created_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id")
);
CREATE INDEX idx_contact_sent ON "Contact" ("is_sent");
CREATE INDEX idx_contact_guid ON "Contact" ("guid");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_contact_before_insert ON "Contact";
CREATE TRIGGER trg_contact_before_insert
  BEFORE INSERT ON "Contact"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_contact_before_update ON "Contact";
CREATE TRIGGER trg_contact_before_update
  BEFORE UPDATE ON "Contact"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_contact_before_delete ON "Contact";
CREATE OR REPLACE RULE rule_contact_before_delete AS
    ON DELETE TO "Contact"
    DO INSTEAD
       UPDATE "Contact" SET "is_deleted" = true WHERE "Contact"."id" = OLD."id";

DROP TABLE IF EXISTS "ContactMeta";
CREATE TABLE IF NOT EXISTS "ContactMeta" (
    "contact_id"    integer             NOT NULL    ,
    "key"           varchar(64)         NOT NULL    ,
    "value"         varchar(2048)       NOT NULL    DEFAULT '',

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("contact_id", "key"),
    FOREIGN KEY ("contact_id") REFERENCES "Contact" ("id")
);
CREATE INDEX idx_cmmeta_site ON "ContactMeta" ("contact_id");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_contactmeta_before_insert ON "ContactMeta";
CREATE TRIGGER trg_contactmeta_before_insert
  BEFORE INSERT ON "ContactMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_contactmeta_before_update ON "ContactMeta";
CREATE TRIGGER trg_contactmeta_before_update
  BEFORE UPDATE ON "ContactMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before DELETE */
DROP RULE IF EXISTS rule_contactmeta_before_delete ON "ContactMeta";
CREATE OR REPLACE RULE rule_contactmeta_before_delete AS
    ON DELETE TO "ContactMeta"
    DO INSTEAD
       UPDATE "ContactMeta" SET "is_deleted" = true WHERE "ContactMeta"."contact_id" = OLD."contact_id" and "ContactMeta"."key" = OLD."key";

/** ************************************************************************* *
 *  Create Sequence (Location)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Location";
CREATE TABLE IF NOT EXISTS "Location" (
    "id"                serial                          ,

    "country_code"      varchar(2)          NOT NULL    ,
    "state_id"          integer                 NULL    ,

    "src"               varchar(512)        NOT NULL    ,
    "name"              varchar(256)            NULL    ,
    "longitude"         decimal(12,8)       NOT NULL    CHECK ("longitude" BETWEEN -180 AND 180),
    "latitude"          decimal(12,8)       NOT NULL    CHECK ("latitude" BETWEEN -90 AND 90),
    "photo_at"          timestamp               NULL    ,

    "note_id"           integer                 NULL    ,
    "feature_at"        timestamp               NULL    ,
    "is_published"      boolean             NOT NULL    DEFAULT false,
    "is_visible"        boolean             NOT NULL    DEFAULT false,

    "guid"              char(36)     UNIQUE NOT NULL    ,

    "is_deleted"        boolean             NOT NULL    DEFAULT false,
    "created_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "created_by"        integer             NOT NULL    ,
    "updated_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_by"        integer             NOT NULL    ,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("country_code") REFERENCES "Country" ("code"),
    FOREIGN KEY ("state_id") REFERENCES "CountryState" ("id"),
    FOREIGN KEY ("created_by") REFERENCES "Account" ("id"),
    FOREIGN KEY ("updated_by") REFERENCES "Account" ("id")
);
CREATE INDEX idx_location_guid ON "Location" ("guid");
CREATE INDEX idx_location_main ON "Location" ("country_code", "feature_at", "is_deleted");
CREATE INDEX idx_location_by ON "Location" ("created_by");

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
    "key"           varchar(64)         NOT NULL    ,
    "value"         varchar(2048)       NOT NULL    DEFAULT '',

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


