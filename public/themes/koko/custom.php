<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for the Koko Location Game theme
 */
require_once(LIB_DIR . '/functions.php');

class Koko {
    var $settings;
    var $strings;
    var $site;
    var $location;

    function __construct( $settings, $strings = false ) {
        if ( defined('DEFAULT_LANG') === false ) { define('DEFAULT_LANG', 'en_US'); }
        if ( defined('GMAPS_KEY') === false ) { define('GMAPS_KEY', ''); }
        if ( defined('USE_GMAPS') === false ) { define('USE_GMAPS', 0); }

        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $this->location = false;
        $this->cache = array();
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    /**
     *  Function Returns the Response Type (HTML / XML / JSON / etc.)
     */
    public function getResponseType() {
        return NoNull($this->settings['type'], 'application/json');
    }

    /**
     *  Function Returns the Reponse Code (200 / 201 / 400 / 401 / etc.)
     */
    public function getResponseCode() {
        return nullInt($this->settings['status'], 200);
    }

    /**
     *  Function Returns any Error Messages that might have been raised
     */
    public function getResponseMeta() {
        return is_array($this->settings['errors']) ? $this->settings['errors'] : false;
    }

    /**
     *  Function Returns Whether the Dataset May Have More Information or Not
     */
    public function getHasMore() {
        return BoolYN($this->settings['has_more']);
    }

    /** ********************************************************************* *
     *  Public Functions
     ** ********************************************************************* */
    public function getPageHTML($data) { return $this->_getPageHTML($data); }
    public function getPageTitle() { return $this->_getPageTitle(); }

    public function getSiteMap() { return $this->_getSiteMap(); }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    private function _getPageHTML( $data ) {
        $HomeUrl = NoNull($this->settings['HomeURL']);
        $Theme = NoNull( substr(strrchr(__DIR__, '/'), 1) );

        /* Cache the Site Data (If Required) */
        if ( is_array($this->cache) === false || count($this->cache) <= 0 ) { $this->cache = $data; }

        $gMapsKey = '';
        if ( YNBool(USE_GMAPS) && mb_strlen(NoNull(GMAPS_KEY)) >= 30 ) { $gMapsKey = GMAPS_KEY; }

        /* Construct the Primary Return Array */
        $ReplStr = array( '[SHARED_FONT]'   => $HomeUrl . '/shared/fonts',
                          '[SHARED_CSS]'    => $HomeUrl . '/shared/css',
                          '[SHARED_IMG]'    => $HomeUrl . '/shared/images',
                          '[SHARED_JS]'     => $HomeUrl . '/shared/js',

                          '[SITE_FONT]'     => $HomeUrl . "/themes/$Theme/fonts",
                          '[SITE_CSS]'      => $HomeUrl . "/themes/$Theme/css",
                          '[SITE_IMG]'      => $HomeUrl . "/themes/$Theme/img",
                          '[SITE_JS]'       => $HomeUrl . "/themes/$Theme/js",
                          '[HOMEURL]'       => $HomeUrl,

                          '[CSS_VER]'       => CSS_VER,
                          '[GENERATOR]'     => GENERATOR . " (" . APP_VER . ")",
                          '[APP_NAME]'      => APP_NAME,
                          '[APP_VER]'       => APP_VER,
                          '[LANG_CD]'       => NoNull($data['language_code']),
                          '[YEAR]'          => date('Y'),

                          '[GMAPS_KEY]'     => $gMapsKey,

                          '[SITE_URL]'      => $this->settings['HomeURL'],

                          '[PAGE_TITLE]'    => $this->_getPageTitle(),
                          '[PAGE_HTML]'     => $this->_getPageContent(),
                         );

        /* Load any page-specific strings */
        $sOK = $this->_setLanguageStrings();

        /* Append the language strings to the return array */
        foreach ( $this->strings as $Key=>$Value ) {
            $ReplStr["[$Key]"] = NoNull($Value);
        }

        /* Return the Completed HTML */
        $ResFile = $this->_getResourceFile();
        return readResource($ResFile, $ReplStr);
    }

    /**
     *  Function determines which resource file to return
     */
    private function _getResourceKey() {
        $ResDir = dirname(__FILE__) . '/resources';
        $excludes = array( '403', '404', 'forgot', 'login' );
        $rewrite = array( 'signin' => 'login' );

        $key = getGlobalObject('resource-key');
        if ( mb_strlen(NoNull($key)) <= 0 ) {
            $key = NoNull($this->settings['PgRoot'], 'main');
            $idx = -1;

            /* Do we have a location key? */
            for ( $i = 1; $i <= 9; $i++ ) {
                $sub = NoNull($this->settings['PgSub' . $i]);
                if ( mb_strlen($sub) <= 0 ) { $i += 10; }
                if ( mb_strlen($sub) >= 1 && mb_strlen($sub) < 36 ) {
                    $key .= '_' . $sub;
                }
            }

            if ( mb_strlen($key) == 6 ) {
                $idx = alphaToInt($key);
            }
            if ( $idx > 0 && $idx <= 1000000 ) { $key = 'location'; }

            /* Set the Key */
            $key = strtolower(NoNull($key, 'main'));

            /* If the page requested has a different file name, "rewrite" the PgRoot value */
            if ( array_key_exists($key, $rewrite) ) {
                $key = strtolower(NoNull($rewrite[$key], 'error'));
            }

            /* Check that the page exists */
            $req = $ResDir . '/page-' . strtolower(NoNull($key)) . '.html';
            if ( file_exists($req) === false ) { $key = '404'; }

            /* If we have a key, let's save it to local memory */
            if ( mb_strlen(NoNull($key)) > 0 ) { setGlobalObject('resource-key', $key); }
        }

        /* Return the Key in lower-case */
        return strtolower(NoNull($key));
    }

    /**
     *  Function determines which resource file to return
     */
    private function _getResourceFile() {
        $ResDIR = __DIR__ . '/resources';
        $key = $this->_getResourceKey();

        /* Determine the Required Page */
        $ReqPage = 'page-' . NoNull($key, 'main') . '.html';

        /* Confirm the Page Exists and Return the proper Resource path */
        if ( file_exists("$ResDIR/$ReqPage") === false ) {
            $sOK = $this->_setMetaMessage('File Not Found', 404);
            $ReqPage = 'page-404.html';
        }

        /* Return the required page */
        return "$ResDIR/$ReqPage";
    }

    /**
     *  Function attempts to load the appropriate page-specific strings
     */
    private function _setLanguageStrings() {
        $lang = NoNull($this->settings['DispLang'], NoNull($this->settings['displang'], $this->settings['lang']));
        if ( YNBool($this->settings['_logged_in']) ) { $lang = NoNull($this->settings['_language_code'], DEFAULT_LANG); }
        $key = $this->_getResourceKey();

        /* Determine the file name */
        $file = $key . '_' . strtolower(NoNull(str_replace(array('_', '–'), '-', $lang))) . '.json';

        /* Determine the source and check if the file exists */
        $src = __DIR__ . '/lang/';
        if ( file_exists($src . $file) === false ) {
            $file = LANG_DIR . '/' . $key . '_' . strtolower(NoNull(str_replace(array('_', '–'), '-', $lang))) . '.json';
        }

        /* So long as the file exists, lets read it into the strings */
        if ( file_exists($src . $file) ) {
            /* Construct the Replacement and Return arrays */
            $ReplStr = array( '{version_id}' => APP_VER,
                              '{year}'       => date('Y'),
                             );

            /* Collect the strings, overwriting where appropriate */
            $json = readResource( $src . $file );
            $items = json_decode($json, true);
            if ( is_array($items) && count($items) > 0 ) {
                foreach ( $items as $kk=>$vv ) {
                    $this->strings["$kk"] = str_replace(array_keys($ReplStr), array_values($ReplStr), NoNull($vv));
                }
            }
        }

        /* Return a happy boolean */
        return true;
    }

    /** ********************************************************************* *
     *  Static Population Functions
     ** ********************************************************************* */
    /**
     *  Function returns the appropriate HTML for a given page (if required)
     */
    private function _getPageContent() {
        $key = $this->_getResourceKey();

        switch ( $key ) {
            case 'location':
            case 'place':
                return $this->_buildLocationPage();
                break;

            case 'main':
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there is no special content to return */
        return '';
    }

    /**
     *  Function returns an appropriate title for the page
     */
    private function _getPageTitle() {
        $key = '';
        for ( $i = 9; $i > 0; $i-- ) {
            if ( mb_strlen($key) <= 0 ) {
                $vv = NoNull($this->settings['PgSub' . NoNull($i)]);
                if ( mb_strlen($vv) > 0 ) {
                    $key = strtolower($vv);
                    $i = 0;
                }
            }
        }
        if ( mb_strlen($key) <= 0 ) { $key = strtolower(NoNull($this->settings['PgRoot'], 'root')); }

        $CacheKey = 'pgTitle-' . NoNull($key, 'root');
        $title = getGlobalObject($CacheKey);

        /* Do we have a static title for the page? */
        if ( mb_strlen($title) <= 0 ) { $title = NoNull($this->strings['page_' . $key]); }

        /* If we have a location, return the appropriate title */
        if ( mb_strlen($title) <= 0 ) {
            if ( $this->location === false ) {
                require_once(LIB_DIR . '/location.php');
                $this->location = new Location($this->settings, $this->strings);
            }

            $isLocation = $this->location->isValidLocation($key);
            if ( $isLocation ) { $title = $this->location->getTitleByKey($key); }
        }

        /* Return a string, be it populated or empty */
        if ( mb_strlen($title) > 0 ) { setGlobalObject($CacheKey, $title); }
        return CleanName($title);
    }

    /**
     *  Function constructs the appropriate HTML for the list being reported
     */
    private function _buildLocationPage() {
        $html = '';
        $key = '';
        for ( $i = 9; $i > 0; $i-- ) {
            if ( mb_strlen($key) <= 0 ) {
                $vv = NoNull($this->settings['PgSub' . NoNull($i)]);
                if ( mb_strlen($vv) > 0 ) {
                    $key = strtolower($vv);
                    $i = 0;
                }
            }
        }

        /* Do stuff */
        $html = 'This is a location page';
        return $html;


        /* If we're here, there's nothing to return */
        return '';
    }

    /** ********************************************************************* *
     *  Sitemap Functions
     ** ********************************************************************* */
    /**
     *  Function returns an XML file containing the valid links for the site
     */
    private function _getSiteMap() {
        $ts = strtotime(date('Y-m-d H:00:00'));

        $langs = $this->_getSiteLanguageList();
        $xml = $this->_buildSiteMapItem('/', $langs, $ts);

        /* Get the top-level pages */
        $ResDir = __DIR__ . '/resources';
        if ( file_exists($ResDir) ) {
            $excludes = array( '403', '404', 'main', 'grouplist', 'location', 'login' );
            $files = scandir($ResDir);
            if ( is_array($files) ) {
                foreach ( $files as $file ) {
                    if ( mb_substr($file, 0, 5) == 'page-' ) {
                        $key = NoNull(str_replace(array('page-', '.html'), '', $file));
                        if ( in_array($key, $excludes) === false ) {
                            $url = "/$key";
                            $xml .= $this->_buildSiteMapItem($url, $langs, $ts);
                        }
                    }
                }
            }
        }

        /* Wrap the page listing in the template */
        $ReplStr = array( '[URL_DATA]' => NoNull($xml) );
        $map = readResource(FLATS_DIR . '/templates/sitemap.xml', $ReplStr);

        /* If we have valid-looking data, let's return it */
        return ((mb_strlen($map) > 10) ? $map : false);
    }

    /**
     *  Function returns a formatted <url> record for the Sitemap
     */
    private function _buildSiteMapItem( $path, $langs, $unixts = 0 ) {
        $default = strtolower(NoNull(str_replace(array('_', '-'), '-', DEFAULT_LANG)));
        if ( is_array($langs) === false || count($langs) <= 0 ) { $langs = false; }
        if ( mb_strlen(NoNull($path)) <= 0 ) { return ''; }
        $unixts = nullInt($unixts);

        $url = NoNull($this->settings['HomeURL']) . ((mb_strlen($path) > 1) ? $path : '');

        /* Construct the string concatenation */
        $item = tabSpace(1) . "<url>\r\n" .
                tabSpace(2) . "<loc>$url</loc>\r\n";
        if ( is_array($langs) ) {
            foreach ( $langs as $lang ) {
                $lang = str_replace(array('-', '_'), '-', $lang);
                $lo = $lang;
                if ( mb_strlen($lo) >= 5 ) {
                    $lo = mb_substr($lang, 0, 2) . '-' . strtoupper(mb_substr($lang, -2));
                }
                $hreflang = str_replace(array('-', '_'), '-', $lo);

                $rel = (($lang == $default) ? 'canonical' : 'alternate');
                $url = NoNull($this->settings['HomeURL']) . (($lang != $default) ? '/' . $lang : '') . ((mb_strlen($path) > 1) ? $path : '');



                $item .= tabSpace(2) . '<xhtml:link rel="' . $rel . '" hreflang="' . $hreflang . '" href="' . $url . '"/>' . "\r\n";
            }
        }
        if ( $unixts > 1000 ) {
            $item .= tabSpace(2) . '<lastmod>' . apiDate($unixts, 'Z') . '</lastmod>' . "\r\n";
        }
        $item .= tabSpace(1) . "</url>\r\n";

        /* Return the item */
        return $item;
    }

    /**
     *  Function returns a list of languages/locales in the theme's /lang directory
     */
    private function _getSiteLanguageList() {
        $ResDir = __DIR__ . '/lang';
        if ( file_exists($ResDir) ) {
            $langs = array();


            $files = scandir($ResDir);
            if ( is_array($files) ) {
                foreach ( $files as $file ) {
                    $ext = getFileExtension($file);
                    if ( $ext == 'json' && mb_strlen($file) >= 2 ) {
                        $key = NoNull(str_replace(array('json', '.'), '', $file));
                        if ( in_array($key, $langs) === false ) { $langs[] = $key; }
                    }
                }
            }

            /* Return the array of languages */
            if ( is_array($langs) && count($langs) > 0 ) { return $langs; }
        }

        /* If we're here, we have no specific language files */
        return false;
    }

    /** ********************************************************************* *
     *  Class Functions
     ** ********************************************************************* */
    /**
     *  Function Sets a Message in the Meta Field
     */
    private function _setMetaMessage( $msg, $code = 0 ) {
        if ( is_array($this->settings['errors']) === false ) { $this->settings['errors'] = array(); }
        if ( NoNull($msg) != '' ) { $this->settings['errors'][] = NoNull($msg); }
        if ( $code > 0 && nullInt($this->settings['status']) == 0 ) { $this->settings['status'] = nullInt($code); }
        return false;
    }
}
?>