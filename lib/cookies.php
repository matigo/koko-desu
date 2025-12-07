<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for Cookie Handling and
 *      Restoration of Primary Settings in Midori
 */
require_once( LIB_DIR . '/functions.php');

class Cookies {
    var $cookies;

    function __construct() {
        $this->cookies = $this->_getCookies();

        /* Ensure the basics are in place */
        if ( defined('ENABLE_MULTILANG') === false ) { define('ENABLE_MULTILANG', 0); }
        if ( defined('DEFAULT_LANG') === false ) { define('DEFAULT_LANG', 'EN'); }
    }

    /**
     * Function Collects the Cookies, GET, and POST information and returns an array
     *      containing all of the values the Application will require.
     */
    function _getCookies() {
        $rVal = array();

        $JSON = json_decode(file_get_contents('php://input'), true);
        if ( is_array($JSON) ) {
            foreach( $JSON as $key=>$val ) {
                $key = $this->_CleanKey($key);
                $rVal[$key] = (is_array($val)) ? $val : $this->_CleanRequest($key, $val);
            }
        }

        if ( is_array($_POST) && count($_POST) > 0 ) {
            foreach( $_POST as $key=>$val ) {
                $key = $this->_CleanKey($key);
                $rVal[$key] = $this->_CleanRequest($key, $val);
            }
        }

        if ( is_array($_GET) && count($_GET) > 0 ) {
            foreach( $_GET as $key=>$val ) {
                $key = $this->_CleanKey($key);
                if ( is_array($val) ) {
                    if ( array_key_exists($key, $rVal) === false ) { $rVal[$key] = array(); }
                    foreach ( $val as $kk=>$vv ) {
                        $rVal[$key][] = NoNull($vv);
                    }

                } else {
                    if ( !array_key_exists($key, $rVal) ) { $rVal[$key] = $this->_CleanRequest($key, $val); }
                }
            }
        }

        foreach( $_COOKIE as $key=>$val ) {
            $key = $this->_CleanKey($key);
            if ( !array_key_exists($key, $rVal) ) { $rVal[$key] = $this->_CleanRequest($key, $val); }
        }

        $gah = getallheaders();
        if ( is_array($gah) ) {
            $opts = array( 'authorisation'     => 'token',
                           'authorization'     => 'token',
                          );
            foreach ( getallheaders() as $key=>$val ) {
                $propKey = str_replace('-', '_', $key);
                if ( array_key_exists(strtolower($propKey), $opts) ) {
                    $val = $this->_CleanRequest($key, $val);

                    /* If we have a valid string, set it */
                    if ( mb_strlen($val) > 0 ) { $rVal[ $opts[strtolower($propKey)] ] = $val; }
                }
            }
        }

        /* Determine the Type */
        $rVal['ReqType'] = strtoupper( NoNull($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'], NoNull($_SERVER['REQUEST_METHOD'], 'GET')) );
        if ( $rVal['ReqType'] == 'OPTIONS' ) { $rVal['ReqType'] = 'DELETE'; }

        /* Assemble the Appropriate URL Path (Overrides Existing Information) */
        $URLPath = $this->_readURL();
        foreach ( $URLPath as $Key=>$Val ) {
            $rVal[ $Key ] = $Val;
        }

        /* Add Any Missing Data from URL Query String (Does Not Override Existing Data) */
        $missedData = $this->checkForMissingData();
        foreach( $missedData as $key=>$val ) {
            $propKey = str_replace('-', '_', $key);
            if ( !array_key_exists($propKey, $rVal) ) {
                $rVal[ $propKey ] = $this->_CleanRequest($key, $val);
            }
        }

        /* Populate Missing or Blank Array Values with Defaults (Does Not Override Existing Data) */
        $defaults = $this->_getCookieDefaults();
        foreach($defaults as $key=>$val) {
            if ( array_key_exists($key, $rVal) === false ) {
                $rVal[ $key ] = $val;
            }
        }

        /* Ensure the Token Value (if exists) Is Correctly Formatted */
        if ( array_key_exists('token', $rVal) && NoNull($rVal['token']) != '' ) {
            if ( strpos($rVal['token'], 'Bearer ') == 0 ) {
                $rVal['token'] = NoNull(str_replace('Bearer ', '', $rVal['token']));
            }
        }

        /* Scrub the Page Pointers */
        foreach ( $rVal as $Key=>$Val ) {
            switch ( strtolower($Key) ) {
                case 'pgroot':
                case 'pgsub1':
                case 'pgsub2':
                case 'pgsub3':
                case 'pgsub4':
                case 'pgsub5':
                case 'pgsub6':
                    $rVal[ $Key ] = $this->_stripQueries( $Val );
                    break;

                default:
                    /* Do Nothing */
            }
        }

        /* Do we have a Token Key value? This is part of authentication validation, so override any key that might be in place */
        if ( mb_strlen(NoNull($rVal['token_key'])) >= 40 ) { $rVal['token'] = $rVal['token_key']; }

        /* Get the Appropriate Account Data */
        if ( mb_strlen(NoNull($rVal['token'])) >= 36 ) {
            require_once( LIB_DIR . '/auth.php' );
            $auth = new Auth( $rVal );
            $data = $auth->getTokenData(NoNull($rVal['token']));
            unset($auth);

            /* If we have account data, let's add it to the output array */
            if ( is_array($data) && count($data) > 0 ) {
                foreach ( $data as $Key=>$Value ) {
                    $rVal[ $Key ] = $Value;
                }
            }

            // Set the Display Language
            $rVal['DispLang'] = $this->_getDisplayLanguage($rVal['_language_code']);
        }

        /* Do we have a cron key provided? */
        $cronKey = NoNull($rVal['cronkey'], $rVal['key']);
        if ( YNBool($rVal['_logged_in']) !== true && mb_strlen($cronKey) >= 10 ) {
            if ( isValidCronRequest( $cronKey) ) {
                $rVal['_account_type'] = 'account.system';
                $rVal['_logged_in'] = true;
            }
        }
        unset($rVal['cronkey']);
        unset($rVal['key']);

        /* Don't Keep an Empty Array Object with the Request URI */
        unset($rVal[mb_substr($rVal['ReqURI'], 1)]);

        // Save Some Cookies for Later Use
        $this->_saveCookies($rVal);

        // Return the Cookies
        return $rVal;
    }

    /**
     * Function Returns a Token without the Preceeding Pound
     */
    private function cleanToken( $Token ) {
        return NoNull(str_replace( "#", "", $Token ));
    }

    /**
     * Function Returns the proper key for use in the settings array
     */
    private function _CleanKey( $Key ) {
        if ( defined('TOKEN_KEY') === false ) { define('TOKEN_KEY', 'token'); }
        $Key = strtolower(str_replace('-', '_', $Key));

        /* "token" is a protected word. Do not permit it unless that is the TOKEN_KEY value. */
        if ( TOKEN_KEY != 'token' && $Key == 'token' ) { $Key = getRandomString(6); }

        /* If we have a TOKEN_KEY, set the proper key value */
        if ( $Key == TOKEN_KEY ) { $Key = 'token'; }
        return NoNull($Key);
    }

    /**
     * Function Reads the Request URI and Returns the Contents in an Array
     */
    private function checkForMissingData() {
        $rVal = array();
        $vals = explode( "&", substr( $_SERVER["REQUEST_URI"], strpos( $_SERVER["REQUEST_URI"], "?" ) + 1 ) );

        foreach ( $vals as $val ) {
            $keyval = explode( "=", $val );

            if ( is_array($keyval) ) {
                $kk = NoNull($keyval[0]);
                if ( mb_strlen($kk) > 0 ) {
                    $rVal[ $keyval[0] ] = $keyval[1];
                }
            }
        }

        // Return an Array Containing the Missing Data
        return $rVal;
    }

    /**
     * Function Returns the Default Cookie Values
     */
    private function _getCookieDefaults() {
        $SiteURL = strtolower( NoNull($_SERVER['SERVER_NAME'], $_SERVER['HTTP_HOST']) );
        $LangCd = $this->_getDisplayLanguage();
        $core = $this->_getCoreDefaults();

        /* Determine the Locales */
        $locale = str_replace(array('_', '-'), '-', strtolower($LangCd));
        $default = strtolower(str_replace(array('_', '-'), '-', NoNull(DEFAULT_LANG)));

        /* Return the Array of Defaults */
        $Protocol = getServerProtocol();
        return array( 'DispLang'        => $LangCd,
                      'HomeURL'         => $Protocol . '://' . $SiteURL,
                      'LangURL'         => $Protocol . '://' . $SiteURL . (($locale != $default) ? '/' . $locale : ''),
                      'Route'           => 'web',

                      'visitor_id'      => getRandomString(24),

                      '_address'        => getVisitorIPv4(),
                      '_account_id'     => 0,
                      '_display_name'   => '',
                      '_account_type'   => 'account.anonymous',
                      '_avatar_file'    => 'default.png',
                      '_language_code'  => NoNull($LangCd, $core['locale_code']),
                      '_site_guid'      => NoNull($core['site_guid']),
                      '_site_id'        => nullInt($core['site_id']),
                      '_logged_in'      => false,
                      '_is_admin'       => false,
                      '_is_debug'       => false,
                     );
    }

    /**
     *  Function Returns the Type of Request and the Route Required
     */
    private function _getRouting() {
        $paths = array( 'api'      => 'api',
                        'cdn'      => 'cdn',
                        'hooks'    => 'hooks',
                        'webhook'  => 'hooks',
                        'webhooks' => 'hooks',
                        'file'     => 'files',
                        'files'    => 'files'
                       );

        /* Determine the Routing based on the Subdomain */
        $ReqURL = strtolower( NoNull($_SERVER['SERVER_NAME'], $_SERVER['HTTP_HOST']) );
        $parts = explode('.', $ReqURL);

        /* Return the Routing or an Empty String */
        if ( array_key_exists($parts[0], $paths) ) { return NoNull($paths[$parts[0]]); }
        return '';
    }

    /**
     *  Function Returns a set of basic defaults for the current site
     */
    private function _getCoreDefaults() {
        $url = strtolower( NoNull($_SERVER['SERVER_NAME'], $_SERVER['HTTP_HOST']) );
        $CacheKey = 'core_' . str_replace(array('.', ':'), '_', $url);

        $data = getCacheObject($CacheKey);
        if ( is_array($data) === false ) {
            $ReplStr = array( '[SITE_URL]' => sqlScrub($url) );
            $sqlStr = readResource(SQL_DIR . '/system/getCoreDefaults.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $data = array( 'locale_code' => NoNull($Row['locale_code']),
                                   'site_guid'   => NoNull($Row['site_guid']),
                                   'site_id'     => nullInt($Row['site_id']),
                                  );
                }
            }

            /* Save the data for next time */
            if ( is_array($data) && count($data) > 0 ) { setCacheObject($CacheKey, $data); }
        }

        /* Return the Data */
        return $data;
    }

    /**
     *  Function Returns the Appropriate Display Language
     */
    private function _getDisplayLanguage( $lang = '' ) {
        if ( YNBool(ENABLE_MULTILANG) === false ) { return NoNull(DEFAULT_LANG); }
        if ( mb_strlen(NoNull($lang)) < 2 || mb_strlen(NoNull($lang)) > 5 ) { $lang = ''; }
        $langs = $this->_getLocales();

        /* If we do not have a provided language, determine if there is a value in the GET or COOKIE globals */
        if ( mb_strlen($lang) < 2 ) {
            $keys = array('ui', 'DispLang', 'lang', 'locale');
            foreach ( $keys as $key ) {
                if ( mb_strlen($lang) < 2 ) {
                    $vv = NoNull($_GET[$key], $_COOKIE[$key]);
                    if ( mb_strlen($vv) >= 2 ) { $lang = $vv; }
                }
            }
        }

        /* If we still do not have a language defined, check the Request Path */
        if ( mb_strlen($lang) < 2 ) {
            $parts = explode('/', NoNull($_SERVER['REQUEST_URI']));

            if ( is_array($parts) && count($parts) > 0 ) {
                foreach ( $parts as $part ) {
                    if ( mb_strlen($lang) < 2 ) {
                        $part = strtolower(NoNull($part));
                        if ( mb_strlen($part) > 0 && in_array($part, $langs) ) { $lang = $part; }
                    }
                }
            }
        }

        /* Do we have a defined language in the browser? */
        if ( mb_strlen($lang) < 2 ) {
            $vv = str_replace(array(',',';'), '|', NoNull($_SERVER['HTTP_ACCEPT_LANGUAGE']));
            $opts = explode('|', "$vv|");
            foreach ( $opts as $opt ) {
                if ( mb_strlen($lang) < 2 ) {
                    $opt = strtolower(NoNull( str_replace(array('_', '-'), '-', $opt)));
                    if ( mb_strlen($opt) >= 2 ) {
                        if ( in_array($opt, $langs) ) {
                            $lang = $opt;
                        } else {
                            foreach ( $langs as $ll ) {
                                $kk = mb_substr($ll, 0, 2);
                                if ( $opt == $kk && mb_strlen($lang) < 2 ) { $lang = $ll; }
                            }
                        }
                    }
                }
            }
        }

        /* Validate the Language is Available */
        if ( isValidLanguage($lang) ) { return strtolower($lang); }
        return strtolower(DEFAULT_LANG);
    }

    /**
     *  Function Cleans the Value, URL Decoding It and Returning the Results
     */
    private function _CleanRequest( $Key, $Value ) {
        if ( defined('TOKEN_PREFIX') === false ) { define('TOKEN_PREFIX', getRandomString(8)); }
        $special = array('RefVisible');

        /* Are we handling a special key? */
        switch ( strtolower($Key) ) {
            case 'authorization':
            case 'authorisation':
            case 'token_key':
            case 'token':
                $Value = NoNull(str_ireplace(array('bearer', ':'), '', $Value));
                if ( mb_strlen($Value) >= 36 ) {
                    if ( mb_substr($Value, 0, mb_strlen(TOKEN_PREFIX)) != TOKEN_PREFIX ) { return ''; }
                }
                break;

            case 'refvisible':
                if ( is_array($Value) ) { return NoNull(implode(',', $Value)); }
                break;

            default:
                /* Do Nothing */
        }

        /* Return the trimmed value */
        return NoNull($Value);
    }

    /**
     *  Function Removes the Queries from the URL Passed
     */
    private function _stripQueries( $String ) {
        $pgItem = explode('?', $String);
        $url = NoNull($pgItem[0]);

        /* Return the Clean Url */
        if ( mb_strlen($url) >= 1 ) { return $url; }
        return '';
    }

    /**
     *  Function returns a list of locales based on the contents of the /lang directory
     */
    private function _getLocales() {
        $langs = getCacheObject('cookies-locales');
        if ( is_array($langs) === false || count($langs) <= 0 ) {
            $langs = array();
            if ( file_exists(LANG_DIR) ) {
                foreach ( glob(LANG_DIR . "/*.json") as $filename) {
                    $key = str_replace(array(LANG_DIR, '.json', '/'), '', $filename);
                    if ( mb_strlen($key) == 5 ) { $langs[] = $key; }
                }
            }
            setCacheObject('cookies-locales', $langs, 60);
        }

        /* If we have locales, let's return them */
        if ( is_array($langs) && count($langs) > 0 ) { return $langs; }
        return array();
    }

    /**
     *  Function returns the Request Uri minus language configurations
     */
    private function _getProperRequestUri() {
        $langs = $this->_getLocales();
        $parts = explode('/', NoNull($_SERVER['REQUEST_URI']));
        $keys = array();

        if ( is_array($parts) && count($parts) > 0 ) {
            foreach ( $parts as $part ) {
                $part = strtolower(NoNull($part));
                if ( mb_strlen($part) > 0 && in_array($part, $langs) === false ) { $keys[] = $part; }
            }
        }

        /* Return a cleaned Uri or the Server Request_uri value */
        if ( is_array($keys) && count($keys) > 0 ) { return implode('/', $keys); }
        return '/';
    }

    /**
     * Function Determines the Appropriate Location and Returns an Array Containing
     *      the Display Page as well as the Page Root.
     */
    private function _readURL() {
        $ReqURI = $this->_getProperRequestUri();
        if ( strpos($ReqURI, "?") ) { $ReqURI = mb_substr($ReqURI, 0, strpos($ReqURI, "?")); }
        $filters = array('api', 'cdn', 'files', 'webhook');

        $BasePath = explode( '/', BASE_DIR );
        $URLPath = explode( '/', $ReqURI );
        $route = $this->_getRouting();

        /* Ensure There Are No Blanks in the URL Path */
        $FullPath = explode('/', $ReqURI);
        $URLPath = array();
        foreach ( $FullPath as $sec ) {
            if ( mb_strlen(NoNull($sec)) > 0 ) { $URLPath[] = NoNull($sec); }
        }

        /* Determine If We're In a Sub-Folder */
        foreach ( $BasePath as $Folder ) {
            if ( $Folder != "" ) {
                $idx = array_search($Folder, $URLPath);
                if ( is_numeric($idx) ) { unset( $URLPath[$idx] ); }
            }
        }

        /* Re-Assemble the URL Path */
        $URLPath = explode('/', implode('/', $URLPath));

        /* Confirm the Routing */
        if ( $route == '' ) { $route = (in_array($URLPath[0], $filters) ? $URLPath[0] : 'web'); }

        /* Construct the Return Array */
        $rVal = array( 'ReqURI' => '/' . NoNull(urldecode(implode('/', $URLPath))),
                       'Route'  => NoNull($route, 'web'),
                       'PgRoot' => urldecode((in_array($URLPath[0], $filters) ? $URLPath[1] : $URLPath[0])),
                      );

        /* Construct the Rest of the URL Items */
        $idx = 1;
        if ( count($URLPath) >= 2 ) {
            for ( $i = ((in_array($URLPath[0], $filters) ? 1 : 0) + 1); $i <= count($URLPath); $i++ ) {
                if ( NoNull($URLPath[$i]) != "" && (is_numeric($URLPath[$i]) || !in_array($URLPath[$i], array_values($rVal))) ) {
                    $rVal["PgSub$idx"] = urldecode($URLPath[$i]);
                    $idx++;
                }
            }
        }

        /* Return the Array of Values */
        return $rVal;
    }

    /**
     * Function Saves the Cookies to the Browser's Cache (If Cookies Enabled)
     */
    private function _saveCookies( $cookieVals ) {
        if ( defined('TOKEN_KEY') === false ) { define('TOKEN_KEY', 'token'); }

        if (!headers_sent()) {
            $cookieVals['remember'] = BoolYN(YNBool(NoNull($cookieVals['remember'], 'N')));
            $valids = array( 'token', 'remember', 'device_id', 'visitor_id' );
            $longer = array( 'device_id', 'visitor_id' );
            $domain = strtolower($_SERVER['SERVER_NAME']);

            $isHTTPS = false;
            $protocol = getServerProtocol();
            if ( $protocol == 'https' ) { $isHTTPS = true; }

            $RememberMe = YNBool(NoNull($cookieVals['remember'], 'N'));
            if ( $RememberMe !== true ) { unset($cookieVals['remember']); }

            foreach( $cookieVals as $key=>$val ) {
                if( in_array($key, $valids) ) {
                    $Expires = time() + COOKIE_EXPY;
                    $LifeTime = COOKIE_EXPY;

                    /* Determine the length of time the current cookie should exist for */
                    if ( $RememberMe ) { $LifeTime = 3600 * 24 * 30; }
                    if ( array_key_exists('remember', $_COOKIE) && $RememberMe !== true ) { $LifeTime = COOKIE_EXPY; }
                    if ( in_array($key, $longer) ) { $LifeTime = 3600 * 24 * 365; }
                    if ( $key == 'remember' && $RememberMe !== true ) { $LifeTime = -3600; }

                    /* Are we dealing with an authorisation token? */
                    if ( strtolower($key) == 'token' ) {
                        if ( mb_strlen($val) < 36 ) { $LifeTime = -3600; }
                        $key = TOKEN_KEY;
                    }

                    /* Get the expiration Unix timestamp */
                    $Expires = time() + $LifeTime;

                    /* Set the Cookie */
                    if ( $isHTTPS ) {
                        setcookie($key, "$val", $Expires, '/', $domain, $isHTTPS, true);
                    } else {
                        setcookie($key, "$val", $Expires, '/', $domain);
                    }
                }
            }
        }
    }
}

?>