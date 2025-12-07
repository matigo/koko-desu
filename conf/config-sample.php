<?php

define('APP_ROOT', '/');                                    // The Application Root Location
define('APP_NAME', 'Koko');                                 // The Application Name
define('CACHE_EXPY', 57600);                                // Number of Seconds Cache Files Can Survive
define('COOKIE_EXPY', 7200);                                // Number of Seconds Mortal Cookies Live For
define('SHA_SALT', 'FooBarBeeGnoFoo');                      // Salt Value used with SHA256 Encryption (Changing Renders Existing Encrypted Data Unreadable)

define('ENABLE_MULTILANG', 1);                              // Enables Multi-Language Support
define('ENABLE_CACHING', 0);                                // Enables Resource Caching
define('DEFAULT_LANG', 'en_US');                            // The Default Application Language (If Not Already Defined)
define('DEBUG_ENABLED', 0);                                 // Set the Debug Level (If Not Already Defined)
define('ENFORCE_PHPVERSION', 1);                            // Enforce a Requirement that the Server Is Running at Least PHP v.X
define('MIN_PHPVERSION', 70200);                            // The Lowest Version of PHP to Accept (7.2)

define('ACCOUNT_LOCK', 366);                                // Number of Days Accounts Can Sit Idle (For Local Authentication / SSO is exempt from this limit)
define('TOKEN_PREFIX', 'TKOKO_');                           // The Authentication Token Validation Prefix
define('TOKEN_EXPY', 120);                                  // Number of Days Tokens Can Sit Idle
define('TOKEN_KEY', 'token');                               // The Key used in the Cookies to access the Authentication Token
define('TIMEZONE', 'UTC');                                  // The Primary Timezone for the Server

define('DB_HOST', '127.0.0.1');                             // Write Database Server (Usually the Primary Database)
define('DB_NAME', '');                                      // Write Database Name
define('DB_USER', '');                                      // Database Login
define('DB_PASS', '');                                      // Database Password
define('DB_PORT', 0);                                       // Database Port (Leave as 0 to use default)
define('DB_ENGINE', 'pgsql');                               // Database Engine (Valid options are 'mysql' and 'pgsql')
define('DB_CHARSET', 'utf8mb4');                            // Database Character Set
define('DB_COLLATE', 'utf8mb4_unicode_ci');                 // Database Collation
define('SQL_SPLITTER', '[-|-|-]');                          // The Split String for Multi-SQL Statements

define('CRON_KEY', '');                                     // A key for use with scheduled jobs

define('PASSWORD_LIFE', 10000);                             // Number of Days a Password Can Be Used for Before Expiring (Default to 28 Years)
define('PASSWORD_UNIQUES', 0);                              // Specifies Whether Passwords Must Be Unique (for an Account) | 0 = No, 1 = Yes

define('CDN_UPLOAD_LIMIT', 128);                            // The Maximum File Size Upload (in MB)
define('CDN_PATH', '/var/www/files');                       // The Path of the CDN's Non-Public Files
define('CDN_DOMAIN', '');                                   // The Single Domain to Use for all CDN-based files
define('API_DOMAIN', '');                                   // The Single Domain to Use for all API-based requests

define('MAIL_SMTPAUTH', 1);                                 // Use SMTP Authentication (This Should Be 1)
define('MAIL_SMTPSECURE', "ssl");                           // The Type of SMTP Security (SSL, TLS, etc.)
define('MAIL_MAILHOST', "");                                // The Host Address of the Mail Server
define('MAIL_MAILPORT', 465);                               // The Port for the Mail Server (465, 590, 990, etc.)
define('MAIL_USERNAME', "");                                // The Login Name for the Mail Server
define('MAIL_USERPASS', "");                                // The Password for the Mail Server
define('MAIL_ADDRESS', "");                                 // The Default Reply-To Address Attached to Emails
define('MAIL_RATELIMIT', 15);                               // The Maximum Number of Messages to Send per Minute

define('USE_S3', 0);                                        // Use Amazon S3 Storage
define('AWS_REGION_NAME', '');                              // The Amazon S3 Region Name
define('AWS_ACCESS_KEY', '');                               // The Amazon S3 Access Key
define('AWS_SECRET_KEY', '');                               // The Amazon S3 Secret Key
define('CLOUDFRONT_URL', '');                               // The Amazon Cloudfront URL

define('USE_GMAPS', 0);                                     // Use the Google Maps API
define('GMAPS_KEY', '');                                    // The Google Maps API Key
define('GMAPS_URL', '');                                    // The Google Maps API URL

define('USE_REDIS', 0);                                     // Use Redis as a cache store
define('REDIS_HOST', '127.0.0.1');                          // Redis server address
define('REDIS_PFIX', '');                                   // A Prefix to prepend onto all Keys
define('REDIS_PORT', 6379);                                 // Redis port (Default 6379)
define('REDIS_EXPY', 7200);                                 // Redis cache lifetime (seconds)
define('REDIS_PASS', '');                                   // Redis access password

?>