<?php

/**
 * @author Jason F. Irwin
 *
 * Class Responds to the Web Data Route and Returns the Appropriate Data
 */
require_once(CONF_DIR . '/versions.php');
require_once(CONF_DIR . '/config.php');
require_once( LIB_DIR . '/functions.php');
require_once( LIB_DIR . '/cookies.php');
require_once( LIB_DIR . '/site.php');

class Route extends Midori {
    var $settings;
    var $strings;
    var $custom;
    var $cache;
    var $site;

    function __construct( $settings, $strings ) {
        $this->settings = $settings;
        $this->strings = $strings;
        $this->custom = false;
        $this->site = new Site($this->settings);

        /* Ensure the Asset Version.id Is Set */
        if ( defined('CSS_VER') === false ) {
            $ver = filemtime(CONF_DIR . '/versions.php');
            if ( nullInt($ver) <= 0 ) { $ver = nullInt(APP_VER); }
            define('CSS_VER', $ver);
        }
    }

    /* ************************************************************************************** *
     *  Function determines what needs to be done and returns the appropriate HTML Document.
     * ************************************************************************************** */
    public function getResponseData() {
        $ThemeLocation = NoNull($this->settings['theme'], 'default');
        $ReplStr = $this->_getReplStrArray();
        $this->settings['status'] = 200;

        $html = readResource(FLATS_DIR . '/templates/500.html', $ReplStr);
        $ThemeFile = THEME_DIR . '/error.html';
        $LoggedIn = false;

        /* Collect the Site Data - Redirect if Invalid */
        $this->cache = $this->site->getSiteData();
        if ( is_array($this->cache) ) {
            $RedirectURL = NoNull($_SERVER['HTTP_REFERER'], $this->cache['protocol'] . '://' . $this->cache['HomeURL']);
            $PgRoot = strtolower(NoNull($this->settings['PgRoot']));

            /* Is There an HTTPS Upgrade Request? */
            $Protocol = getServerProtocol();

            /* Determine if a Redirect is Required */
            if ( strtolower($_SERVER['SERVER_NAME']) != NoNull($this->cache['HomeURL']) ) { $this->cache['do_redirect'] = true; }
            if ( $Protocol != $this->cache['protocol'] ) {
                $suffix = '/' . NoNull($this->settings['PgRoot']);
                if ( $suffix != '' ) {
                    for ( $i = 1; $i <= 9; $i++ ) {
                        $itm = NoNull($this->settings['PgSub' . $i]);
                        if ( $itm != '' ) { $suffix .= "/$itm"; }
                    }
                }

                // Redirect to the Appropriate URL
                redirectTo( $this->cache['protocol'] . '://' . NoNull(str_replace('//', '/', $this->cache['HomeURL'] . $suffix), $this->settings ) );
            }

            /* Is this a JSON Request? */
            $CType = NoNull($_SERVER["CONTENT_TYPE"], 'text/html');
            if ( strtolower($CType) == 'application/json' ) { $this->_handleJSONRequest(); }

            /* Is this a Sitemap Request? */
            $this->_handleSitemapRequest($this->cache);

            /* Is this an RSS Feed Request? */
            $this->_handleFeedRequest($this->cache);

            /* Is this a Manifest Request? */
            $this->_handleManifestRequest();

            /* Are We Signing In? */
            if ( $PgRoot == 'validatetoken' && NoNull($this->settings['token']) != '' ) {
                $this->settings['remember'] = false;
                $this->cache['do_redirect'] = true;

                if ( mb_strlen(NoNull($this->settings['target'])) > 0 ) {
                    $RedirectURL = NoNull($_SERVER['HTTP_REFERER'], $this->cache['protocol'] . '://' . $this->cache['HomeURL']) . '/' . NoNull($this->settings['target']);
                }
            }

            /* Are we working with a valid request */
            $this->_checkStaticResourceRequest();

            /* Are We Signed In and Accessing Something That Requires Being Signed In? */
            if ( YNBool($this->settings['_logged_in']) ) {
                switch ( $PgRoot ) {
                    case 'signout':
                    case 'logout':
                        require_once(LIB_DIR . '/auth.php');
                        $auth = new Auth($this->settings);
                        $sOK = $auth->performLogout();
                        unset($auth);

                        /* If we're here, redirect to force the Token scrub */
                        $RedirectURL = NoNull($_SERVER['HTTP_REFERER'], $this->cache['protocol'] . '://' . $this->cache['HomeURL']);
                        redirectTo( $RedirectURL, $this->settings );
                        break;

                    default:
                        /* Do Nothing Here */
                }

            } else {
                /* Is there a redirect required while not signed in? */
                switch ( $PgRoot ) {
                    case 'signout':
                    case 'logout':
                        $RedirectURL = NoNull($_SERVER['HTTP_REFERER'], $this->cache['protocol'] . '://' . $this->cache['HomeURL']);
                        redirectTo( $RedirectURL, $this->settings );
                        break;

                    default:
                        /* Do Nothing Here */
                }
            }

            /* Perform the Redirect if Necessary */
            $suffix = ( YNBool($this->settings['remember']) ) ? '?remember=Y' : '';
            if ( $this->cache['do_redirect'] ) { redirectTo( $RedirectURL . $suffix, $this->settings ); }

            /* Load the Requested HTML Content */
            $html = $this->_getPageHTML( $this->cache );
        }

        /* Return the HTML and unset the various objects */
        unset($this->strings);
        unset($this->custom);
        unset($this->site);
        return $html;
    }

    /**
     *  Function Returns the Response Type (HTML / XML / JSON / etc.)
     */
    public function getResponseType() {
        return NoNull($this->settings['type'], 'text/html');
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

    /** ********************************************************************** *
     *  Private Functions
     ** ********************************************************************** */
    /**
     *  Function Returns an Array With the Appropriate Content
     */
    private function _getPageHTML( $data ) {
        $ThemeLocation = THEME_DIR . '/' . NoNull($data['location'], 'error');
        if ( file_exists("$ThemeLocation/base.html") === false ) {
            $ThemeLocation = THEME_DIR . '/error';
            $data['location'] = 'error';
        }
        $this->_getLanguageStrings($data['location']);
        $ReplStr = $this->_getPageMetadataArray($data);

        /* Populate the Appropriate Language Strings */
        if ( is_array($this->strings) ) {
            foreach ( $this->strings as $Key=>$Value ) {
                $ReplStr["[$Key]"] = NoNull($Value);
            }
        }

        /* If there is a custom theme class, collect the Page HTML from there */
        if ( file_exists("$ThemeLocation/custom.php") ) {
            if ( $this->custom === false ) {
                require_once("$ThemeLocation/custom.php");
                $ClassName = ucfirst(NoNull($data['location'], 'default'));
                $this->custom = new $ClassName( $this->settings, $this->strings );
            }
            if ( method_exists($this->custom, 'getPageHTML') ) {
                $this->settings['errors'] = $this->custom->getResponseMeta();
                $this->settings['status'] = $this->custom->getResponseCode();
                $ReplStr['[PAGE_HTML]'] = $this->custom->getPageHTML($data);
            }

        } else {
            $ReqFile = $this->_getContentPage($data);
            $ReplStr['[PAGE_HTML]'] = readResource($ReqFile, $ReplStr);
        }

        /* Set the Output HTML */
        $html = readResource( THEME_DIR . "/" . $data['location'] . "/base.html", $ReplStr );

        /* Get the Run-time */
        $runtime = getRunTime('html');

        /* Return the Completed HTML Page Content */
        return str_replace('[GenTime]', $runtime, $html);
    }

    /**
     *  Function Parses and Handles Requests that Come In with an Application/JSON Header
     */
    private function _handleJSONRequest() {
        $Action = strtolower(NoNull($this->settings['PgSub1'], $this->settings['PgRoot']));
        $format = strtolower(NoNull($_SERVER['CONTENT_TYPE'], 'text/plain'));
        $valids = array( 'application/json' );
        $meta = array();
        $data = false;
        $code = 401;

        if ( in_array($format, $valids) ) {
            switch ( $Action ) {
                case 'profile':
                    require_once(LIB_DIR . '/account.php');
                    $acct = new Account( $this->settings, $this->strings );
                    $data = $acct->getPublicProfile();
                    $meta = $acct->getResponseMeta();
                    $code = $acct->getResponseCode();
                    unset($acct);
                    break;

            }
        }

        // If We Have an Array of Data, Return It
        if ( is_array($data) ) { formatResult($data, $this->settings, 'application/json', $code, $meta); }
    }

    private function _handleSitemapRequest( $data ) {
        $PgRoot = strtolower(NoNull($this->settings['PgRoot']));
        $xml = '';

        if ( in_array($PgRoot, array('sitemap.xml')) ) {
            if ( is_array($data) && array_key_exists('location', $data) ) {
                $ThemeLocation = THEME_DIR . '/' . NoNull($data['location'], 'error');
                if ( file_exists("$ThemeLocation/custom.php") ) {
                    if ( $this->custom === false ) {
                        require_once("$ThemeLocation/custom.php");
                        $ClassName = ucfirst(NoNull($data['location'], 'default'));
                        $this->custom = new $ClassName( $this->settings, $this->strings );
                    }
                    if ( method_exists($this->custom, 'getSiteMap') ) {
                        $xml = $this->custom->getSiteMap();
                    }
                }
            }
        }

        /* If we have XML, let's return it. Otherwise, exit the function. */
        if ( mb_strlen(NoNull($xml)) > 10 ) { formatResult($xml, $this->settings, 'application/xml', 200); }
    }

    /**
     *  Collect the Language Strings that Will Be Used In the Theme
     *  Note: The Default Theme Language is Loaded First To Reduce the Risk of NULL Descriptors
     */
    private function _getLanguageStrings( $Location ) {
        $ThemeLocation = THEME_DIR . '/' . $Location;
        if ( file_exists("$ThemeLocation/base.html") === false ) { $ThemeLocation = THEME_DIR . '/error'; }
        $locale = strtolower(str_replace('_', '-', DEFAULT_LANG));
        $rVal = array();

        /* Set the base replacements */
        $ReplStr = array( '{version_id}' => APP_VER,
                          '{year}'       => date('Y'),
                         );

        /* Collect the Default Langauge Strings */
        $LangFile = "$ThemeLocation/lang/" . $locale . '.json';
        if ( file_exists( $LangFile ) ) {
            $json = readResource( $LangFile );
            $items = objectToArray(json_decode($json));

            if ( is_array($items) ) {
                foreach ( $items as $Key=>$Value ) {
                    $rVal["$Key"] = str_replace(array_keys($ReplStr), array_values($ReplStr), NoNull($Value));
                }
            }
        }

        /* Is Multi-Lang Enabled And Required? If So, Load It */
        $LangCode = NoNull($this->settings['DispLang'], $this->settings['_language_code']);
        if ( YNBool(ENABLE_MULTILANG) && (strtolower($LangCode) != strtolower(DEFAULT_LANG)) ) {
            $locale = strtolower(str_replace('_', '-', $LangCode));
            $LangFile = "$ThemeLocation/lang/" . $locale . '.json';
            if ( file_exists( $LangFile ) ) {
                $json = readResource( $LangFile );
                $items = objectToArray(json_decode($json));

                if ( is_array($items) ) {
                    foreach ( $items as $Key=>$Value ) {
                        $rVal["$Key"] = str_replace(array_keys($ReplStr), array_values($ReplStr), NoNull($Value));
                    }
                }
            }
        }

        /* Do We Have a Special File for the Page? */
        $locale = strtolower(str_replace('_', '-', DEFAULT_LANG));
        $LangFile = "$ThemeLocation/lang/" . NoNull($this->settings['PgRoot']) . '_' . $locale . '.json';
        if ( file_exists( $LangFile ) ) {
            $json = readResource( $LangFile );
            $items = objectToArray(json_decode($json));

            if ( is_array($items) ) {
                foreach ( $items as $Key=>$Value ) {
                    $rVal["$Key"] = str_replace(array_keys($ReplStr), array_values($ReplStr), NoNull($Value));
                }
            }
        }

        $LangCode = NoNull($this->settings['DispLang'], $this->settings['_language_code']);
        if ( YNBool(ENABLE_MULTILANG) && (strtolower($LangCode) != strtolower(DEFAULT_LANG)) ) {
            $locale = strtolower(str_replace('_', '-', $LangCode));
            $LangFile = "$ThemeLocation/lang/" . NoNull($this->settings['PgRoot']) . '_' . $locale . '.json';
            if ( file_exists( $LangFile ) ) {
                $json = readResource( $LangFile );
                $items = objectToArray(json_decode($json));

                if ( is_array($items) ) {
                    foreach ( $items as $Key=>$Value ) {
                        $rVal["$Key"] = str_replace(array_keys($ReplStr), array_values($ReplStr), NoNull($Value));
                    }
                }
            }
        }

        /* Update the Language Strings for the Class */
        if ( is_array($rVal) ) {
            foreach ( $rVal as $Key=>$Value ) {
                $this->strings["$Key"] = str_replace(array_keys($ReplStr), array_values($ReplStr), NoNull($Value));
            }
        }
    }

    private function _getPageMetadataArray( $data ) {
        $SiteUrl = NoNull($data['protocol'] . '://' . $data['HomeURL'] . '/themes/' . $data['location']);
        $HomeUrl = NoNull($data['protocol'] . '://' . $data['HomeURL']);
        $PgRoot = strtolower(NoNull($this->settings['PgRoot']));
        $ApiUrl = getApiUrl();
        $CdnUrl = getCdnUrl();

        /* Get the Banner (if one exists) */
        $banner_img = NoNull($data['banner_img']);
        if ( NoNull($banner_img) == '' ) { $banner_img = NoNull($data['protocol'] . '://' . $data['HomeURL'] . '/shared/images/social_banner.png'); }

        /* Determine the Copyright Years */
        $copyyear = '2025';
        if ( date('Y') > 2025 ) { $copyyear .= " ~ " . date('Y'); }

        /* Construct the Core Array */
        $rVal = array( '[SHARED_FONT]'  => $HomeUrl . '/shared/fonts',
                       '[SHARED_CSS]'   => $HomeUrl . '/shared/css',
                       '[SHARED_IMG]'   => $HomeUrl . '/shared/images',
                       '[SHARED_JS]'    => $HomeUrl . '/shared/js',

                       '[SITE_FONT]'    => $SiteUrl . '/fonts',
                       '[SITE_CSS]'     => $SiteUrl . '/css',
                       '[SITE_IMG]'     => $SiteUrl . '/img',
                       '[SITE_JS]'      => $SiteUrl . '/js',

                       '[FONT_DIR]'     => $SiteUrl . '/fonts',
                       '[CSS_DIR]'      => $SiteUrl . '/css',
                       '[IMG_DIR]'      => $SiteUrl . '/img',
                       '[JS_DIR]'       => $SiteUrl . '/js',
                       '[HOMEURL]'      => NoNull($this->settings['HomeURL']),
                       '[API_URL]'      => NoNull($data['api_url'], $ApiUrl),
                       '[CDN_URL]'      => NoNull($data['cdn_url'], $CdnUrl),

                       '[CSS_VER]'      => getMetaVersion(),
                       '[GENERATOR]'    => GENERATOR . " (" . APP_VER . ")",
                       '[APP_NAME]'     => APP_NAME,
                       '[APP_VER]'      => APP_VER,
                       '[LANG_CD]'      => NoNull($this->settings['_language_code'], $data['language_code']),
                       '[LOCALE]'       => NoNull($this->settings['_locale_code'], $data['locale']),
                       '[ACCOUNT_TYPE]' => NoNull($this->settings['_account_type'], 'account.guest'),
                       '[AVATAR_URL]'   => NoNull($this->settings['HomeURL']) . '/avatars/' . $this->settings['_avatar_file'],
                       '[WELCOME_LINE]' => NoNull($welcomeLine),
                       '[DISPLAY_NAME]' => NoNull($this->settings['_display_name'], $this->settings['_first_name']),
                       '[UPDATED_AT]'   => NoNull($data['updated_at']),
                       '[PGSUB_1]'      => NoNull($this->settings['PgSub1']),
                       '[TODAY]'        => date('Y-m-d'),
                       '[YEAR]'         => date('Y'),
                       '[COPYYEAR]'     => NoNull($copyyear, date('Y')),

                       '[TOKEN]'        => ((YNBool($this->settings['_logged_in'])) ? NoNull($this->settings['token']) : ''),
                       '[PRIMARY_GUID]' => $this->_getPrimaryGuid(),
                       '[VISITOR_ID]'   => NoNull($this->settings['visitor_id']),
                       '[SESSION_ID]'   => getRandomString(48),

                       '[SITE_URL]'     => $this->settings['HomeURL'],
                       '[SITE_NAME]'    => $data['name'],
                       '[SITEDESCR]'    => $data['description'],
                       '[SITEKEYWD]'    => $data['keywords'],
                       '[SITE_COLOR]'   => NoNull($data['color'], 'auto'),

                       '[PAGE_TITLE]'   => $this->_getPageTitle($data, true),
                       '[PAGE_CSS]'     => $this->_getPageCSS($data),
                       '[PAGE_URL]'     => $this->_getPageUrl(),
                       '[PATH_URL]'     => $this->_getProperPath(),

                       '[CANON_META]'   => $this->_getCanonicalMeta(),
                       '[SCHEMA_META]'  => $this->_getSchemaMeta($data),
                       '[META_DESCR]'   => $this->_getMetaDescription($data),
                       '[META_KEYS]'    => $this->_getMetaKeys($data),

                       '[LANG_HOME]'    => NoNull($this->settings['LangURL']),

                       '[META_TITLE]'   => $this->_getPageTitle($data, true),
                       '[META_DOMAIN]'  => NoNull($data['HomeURL']),
                       '[META_TYPE]'    => NoNull($data['page_type'], 'website'),
                       '[CC-LICENSE]'   => $this->_getCCLicense(NoNull($data['license'], 'CC BY-NC-ND')),
                       '[BANNER_IMG]'   => $banner_img,

                       '[FONT_SIZE]'    => NoNull($data['font-size'], 'md'),
                      );

        /* Return the Strings */
        return $rVal;
    }

    /**
     *  Function returns a constructed Creative Commons license statement for the footer of a page
     */
    private function _getCCLicense( $license ) {
        $idx = array( '0'     => array( 'icon' => 'zero',  'text' => 'No Rights Reserved' ),
                      'by'    => array( 'icon' => 'by',    'text' => 'Attribution' ),
                      'nc'    => array( 'icon' => 'nc',    'text' => 'NonCommercial' ),
                      'nd'    => array( 'icon' => 'nd',    'text' => 'NoDerivatives' ),
                      'pd'    => array( 'icon' => 'pd',    'text' => 'PublicDomain' ),
                      'sa'    => array( 'icon' => 'sa',    'text' => 'ShareAlike' ),
                      'remix' => array( 'icon' => 'remix', 'text' => 'Remix' ),
                      'share' => array( 'icon' => 'share', 'text' => 'Share' ),
                     );
        $valids = array('CC0', 'CC BY', 'CC BY-SA', 'CC BY-ND', 'CC BY-NC', 'CC BY-NC-SA', 'CC BY-NC-ND');
        if ( in_array(strtoupper($license), $valids) === false ) {
            $license = 'CC BY-NC-ND';
        }

        $type = strtolower(NoNull(str_replace(array('CC', '4.0'), '', $license)));
        $icon = '<i class="fab fa-creative-commons"></i> ';
        $desc = '';

        $els = explode('-', $type);
        foreach ( $els as $el ) {
            $icon .= '<i class="fab fa-creative-commons-' . $idx[strtolower($el)]['icon'] . '"></i> ';
            if ( $desc != '' ) { $desc .= '-'; }
            $desc .= NoNull($idx[strtolower($el)]['text']);
        }

        // Return the License String
        return $icon . 'This work is licensed under a <a rel="license" href="http://creativecommons.org/licenses/' . $type . '/4.0/">Creative Commons ' . NoNull($desc) . ' 4.0 International License</a>.';
    }

    /**
     *  Function Collects the Necessary Page Contents
     */
    private function _getContentPage( $data ) {
        $ResDIR = THEME_DIR . "/" . NoNull($data['location'], getRandomString(6));
        if ( file_exists("$ResDIR/base.html") === false ) { $data['location'] = 'error'; }
        $valids = array('forgot', 'rights', 'terms', 'tos');
        $pgName = NoNull($this->settings['PgRoot'], 'main');

        /* If we're not signed in and not visiting an exception page, show the login form */
        if ( $this->settings['_logged_in'] !== true && in_array(strtolower($this->settings['PgRoot']), $valids) === false ) {
            $pgName = 'login';
        }

        $ResDIR = THEME_DIR . "/" . $data['location'] . "/resources/";
        $rVal = 'page-' . NoNull($pgName, '404') . '.html';
        if ( file_exists($ResDIR . $rVal) === false ) { $rVal = 'page-404.html'; }

        if ( $rVal == 'page-404.html' ) { $this->settings['status'] = 404; }
        if ( $rVal == 'page-403.html' ) { $this->settings['status'] = 403; }

        /* Return the Necessary Page */
        return $ResDIR . $rVal;
    }

    /**
     *  Function Returns the Page Title
     */
    private function _getPageTitle( $data, $isMeta = false ) {
        $lblDefault = '';
        $lblName = 'page' . ucfirst(NoNull($this->settings['PgRoot'], $lblDefault));
        $title = NoNull($this->strings[$lblName], NoNull($data['page_title'], $data['name']));

        /* If there is a custom theme class, collect the Page Title from there */
        $ThemeLocation = THEME_DIR . '/' . NoNull($data['location'], 'error');
        if ( file_exists("$ThemeLocation/custom.php") ) {
            if ( $this->custom === false ) {
                require_once("$ThemeLocation/custom.php");
                $ClassName = ucfirst(NoNull($data['location'], 'default'));
                $this->custom = new $ClassName( $this->settings, $this->strings );
            }
            if ( method_exists($this->custom, 'getPageTitle') ) {
                $ttl = $this->custom->getPageTitle();
                if ( mb_strlen(NoNull($ttl)) > 0 ) { $title = NoNull($ttl); }
            }
        }

        if ( $isMeta ) {
            $suffix = NoNull($this->cache['name']);
            if ( mb_strlen($title) > 0 && mb_strlen($suffix) > 0 ) { $title .= ' | ' . $suffix; }
            return htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8');
        } else {
            return NoNull($title);
        }
    }

    /**
     *  Function Determines the Current Page URL
     */
    private function _getPageURL() {
        $rVal = $this->settings['HomeURL'];

        if ( NoNull($this->settings['PgRoot']) != '' ) { $rVal .= '/' . NoNull($this->settings['PgRoot']); }
        for ( $i = 1; $i <= 9; $i++ ) {
            if ( NoNull($this->settings['PgSub' . $i]) != '' ) {
                $rVal .= '/' . NoNull($this->settings['PgSub' . $i]);
            } else {
                return $rVal;
            }
        }

        // Return the Current URL
        return $rVal;
    }

    /**
     *  Function Determines if there is a page-specific CSS file that needs to be returned or not
     */
    private function _getPageCSS( $data ) {
        $PgRoot = strtolower(NoNull($this->settings['PgRoot'], 'main'));
        if ( YNBool($this->settings['_logged_in']) === false ) { $PgRoot = 'login'; }
        $cssFile = $PgRoot . '.css';
        $CssDIR = THEME_DIR . "/" . $data['location'] . "/css/";

        if ( file_exists($CssDIR . $cssFile) ) {
            $cssUrl = $this->settings['HomeURL'] . '/themes/' . NoNull($data['location'], 'admin') . '/css/';
            $cssVer = getMetaVersion();
            return "\r\n" . tabSpace(2) .
                   '<link rel="stylesheet" type="text/css" href="' . $cssUrl . $cssFile . '?ver=' . $cssVer . '" />';
        }

        /* If we're here, there is no dedicated CSS file */
        return '';
    }

    /**
     *  Function Constructs and Returns the Language String Replacement Array
     */
    private function _getReplStrArray() {
        $rVal = array( '[SITEURL]' => NoNull($this->settings['HomeURL']),
                       '[RUNTIME]' => getRunTime('html'),
                      );
        foreach ( $this->strings as $Key=>$Val ) {
            $rVal["[$Key]"] = NoNull($Val);
        }

        // Return the Array
        return $rVal;
    }

    /** ********************************************************************** *
     *  Manifest Functions
     ** ********************************************************************** */
    /**
     *  Function constructs and returns a manifest.json file
     */
    private function _handleManifestRequest() {
        $PgRoot = strtolower(basename(NoNull($this->settings['ReqURI'])));
        if ( in_array($PgRoot, array('manifest.json')) ) {
            $CacheKey = 'manifest-' . NoNull($this->cache['HomeURL']) . '-' . nullInt($this->cache['site_version']);
            $data = getCacheObject($CacheKey);
            $meta = array();
            $code = 200;

            /* If we do not have an already-built manifest, construct one */
            if ( is_array($data) === false ) {
                $iconSizes = array('48x48', '76x76', '96x96', '128x128', '144x144', '192x192', '256x256', '512x512');
                $icons = array();

                /* Is there a custom set of icons? */
                $ResDIR = THEME_DIR . '/' . NoNull(str_replace(array('themes', 'manifest.json', '/'), '', $this->settings['ReqURI']));
                $src = NoNull(str_replace('manifest.json', '', $this->settings['ReqURI']));
                foreach ( $iconSizes as $sz ) {
                    $filename = $ResDIR . '/img/logo-' . $sz . '.png';
                    $srcPath = $src . 'img/logo-' . $sz . '.png';

                    /* If the file exists, add it to the $icons array */
                    if ( file_exists($filename) ) {
                        $icons[] = array( 'src' => $srcPath,
                                          'sizes' => $sz,
                                          'type' => 'image/png'
                                         );
                    }
                }

                /* If there are no icons defined, grab the defaults */
                if ( is_array($icons) === false || (is_array($icons) && count($icons) <= 0 ) ) {
                    $ResDIR = BASE_DIR . '/shared/images/icons';

                    foreach ( $iconSizes as $sz ) {
                        $filename = $ResDIR . '/logo-' . $sz . '.png';
                        $srcPath = '/shared/images/icons/logo-' . $sz . '.png';

                        /* If the file exists, add it to the $icons array */
                        if ( file_exists($filename) ) {
                            $icons[] = array( 'src' => $srcPath,
                                              'sizes' => $sz,
                                              'type' => 'image/png'
                                             );
                        }
                    }
                }

                /* Construct the output array */
                $data = array( 'name'        => NoNull($this->cache['name']),
                               'description' => NoNull($this->cache['description']),
                               'icons'       => $icons,
                               'start_url'   => '/',
                               'scope'       => '/',
                               'display'     => 'standalone',

                               'background_color' => '#fff',
                               'theme_color'      => '#fff',
                              );

                /* Cache the data */
                if ( is_array($data) && count($data) > 0 ) { setCacheObject($CacheKey, $data); }
            }

            /* If We Have an Array of Data, Return It */
            if ( is_array($data) ) { formatResult($data, $this->settings, 'pretty/json', $code, $meta); }
        }

        /* If we're here, we can assume that this is not a manifest request */
    }

    /** ********************************************************************** *
     *  RSS Feed Functions
     ** ********************************************************************** */
    /**
     *  Function returns a complete RSS Feed
     */
    private function _handleFeedRequest( $data = false ) {
        $PgRoot = strtolower(basename(NoNull($this->settings['ReqURI'])));
        if ( in_array($PgRoot, array('rss-feed.xml','rssfeed.xml','feed.xml','rss.xml')) ) {
            if ( is_array($data) === false || count($data) <= 0 ) { $data = array(); }
            $locale = strtolower(str_replace(array('_', '-'), '-', NoNull($this->settings['_language_code'], NoNull($this->settings['DispLang']), $data['locale'])));
            $xml = '';

            $ts = strtotime(date("Y-m-d H:00:00"));
            $CacheKey = 'rss-' . $locale . '-' . NoNull($ts);
            $obj = getCacheObject($CacheKey);
            if ( is_array($obj) && array_key_exists('xml', $obj) ) { $xml = $obj['xml']; }

            /* If we do not have valid data, let's build it */
            if ( mb_strlen($xml) <= 100 ) {
                $banner_file = NoNull($data['banner_src'], '/shared/images/social_banner.png');
                $banner_src = '';
                if ( mb_strlen(NoNull($banner_file)) > 0 ) {
                    if ( file_exists(BASE_DIR . $banner_file) ) { $banner_src = $this->settings['HomeURL'] . $banner_file; }
                }

                /* Set the Replace Array */
                $ReplStr = array( '[LANG_HOME]' => NoNull($this->settings['LangURL']),
                                  '[GENERATOR]' => GENERATOR . " (" . APP_VER . ")",
                                  '[LANGUAGE]'  => str_replace('_', '-', NoNull($this->settings['_language_code'], $data['locale'])),
                                  '[RSS_URL]'   => NoNull($this->settings['LangURL']) . '/feed.xml',
                                  '[LOCALE]'    => NoNull($locale),
                                  '[UPDATEDTS]' => date("D, d M Y H:00:00 O"),

                                  '[TITLE]' => NoNull($data['name']),
                                  '[DESCR]' => NoNull($data['description']),
                                  '[IMAGE]' => $banner_src,

                                  '[ITEMS]' => $this->_getFeedItems($data['site_id'], $locale),
                                 );
                $xml = readResource(FLATS_DIR . '/templates/rss-feed.xml', $ReplStr);
                if ( mb_strlen($xml) > 100 ) {
                    setCacheObject($CacheKey, array('xml' => $xml));
                }
            }

            /* So long as we have what looks like a valid XML file, let's return it */
            if ( mb_strlen($xml) > 100 ) {
                formatResult($xml, $this->settings, 'application/rss+xml');
                exit();
            }
        }

        /* If we're here, there is no Feed request */
    }

    /**
     *  Function collects and constructs RSS items
     */
    private function _getFeedItems( $site_id = 0, $locale = '' ) {
        $locale = str_replace(array('-', '_'), '_', NoNull($locale, DEFAULT_LANG));
        $site_id = nullInt($site_id);

        /* So long as we have a valid Site.id, collect some data */
        if ( $site_id > 0 ) {
            require_once(LIB_DIR . '/article.php');
            $src = new Article($this->settings, $this->strings);
            $data = $src->getSyndicationItems($site_id, $locale);
            unset($src);

            if ( is_array($data) ) {
                $xml = '';

                foreach ( $data as $item ) {
                    $uuid = NoNull($item['guid']) . '-' . str_replace(array('-','_'), '', NoNull($item['locale']));

                    $ReplStr = array( '[TITLE]'  => NoNull($item['title']),
                                      '[AUTHOR]' => NoNull($item['author']),
                                      '[URL]'    => NoNull($item['url']),
                                      '[UUID]'   => strtolower($uuid),

                                      '[SUMMARY]' => NoNull($item['summary']),
                                      '[CONTENT]' => NoNull($item['html']),
                                      '[PLAIN]'   => NoNull($item['text']),

                                      '[PUBLISH_DTS]' => date("D, d M Y H:i:s O", $item['publish_unix']),
                                      '[UPDATED_DTS]' => date("D, d M Y H:i:s O", $item['updated_unix']),
                                     );
                    $xml .= "\r\n" . readResource(FLATS_DIR . '/templates/rss-item.xml', $ReplStr);
                }

                /* If we have data, let's return it */
                if ( mb_strlen(NoNull($xml)) > 0 ) { return NoNull($xml); }
            }
        }

        /* If we're here, there's nothing to return */
        return '';
    }

    /** ********************************************************************** *
     *  Canonical Functions
     ** ********************************************************************** */
    /**
     *  Function returns a complete Canonical Meta block for the HTML head
     */
    private function _getCanonicalMeta() {
        $locale = str_replace(array('_', '-'), '-', strtolower(NoNull($this->settings['DispLang'], $this->settings['_language_code'])));
        $default = strtolower(str_replace(array('_', '-'), '-', NoNull(DEFAULT_LANG)));
        $langs = array();

        /* Collect the languages for the theme */
        if ( is_array($this->cache) && array_key_exists('location', $this->cache) ) {
            $src = THEME_DIR . '/' . strtolower($this->cache['location']) . '/lang';
            if ( file_exists($src) ) {
                foreach ( glob($src . "/*.json") as $filename) {
                    $key = str_replace(array($src, '.json', '/'), '', $filename);
                    if ( mb_strlen($key) == 5 ) { $langs[] = $key; }
                }
            }
        }

        /* If we have a list of languages, let's build the Canonical links */
        if ( is_array($langs) ) {
            $path = $this->_getProperPath();
            $url = NoNull($this->settings['HomeURL']) . '/' . $path;

            /* Set the top-level Canonicals */
            $out = tabSpace(2) . '<link rel="canonical" href="' . $url . '">' . "\r\n" .
                   tabSpace(2) . '<link rel="alternate" hreflang="x-default" href="' . $url . '">' . "\r\n";

            /* Add the Language variations */
            if ( is_array($langs) && count($langs) > 0 ) {
                foreach ( $langs as $lang ) {
                    $url = NoNull($this->settings['HomeURL']) . '/' . (($lang == $default) ? '' : $lang . '/') . $path;
                    $out .= tabSpace(2) . '<link rel="alternate" hreflang="' . $lang . '" href="' . $url . '" />' . "\r\n";
                }

                $out .= "\r\n";

                /* Add the RSS links */
                foreach ( $langs as $lang ) {
                    $url = NoNull($this->settings['HomeURL']) . '/' . (($lang == $default) ? '' : $lang . '/') . 'rss.xml';
                    $out .= tabSpace(2) . '<link rel="alternate" hreflang="' . $lang . '" type="application/rss+xml" title="" href="' . $url . '">' . "\r\n";
                }
            }

            /* If we have a string, let's return it */
            if ( mb_strlen($out) > 0 ) { return $out; }
        }
        /* If we're here, there are no alternative languages */
        return '';
    }

    /**
     *  Function determines the "proper" path for the current page
     */
    private function _getProperPath() {
        $langs = array('en-us', 'es-es', 'es-mx', 'ja-jp', 'ko-kr', 'ru-ru');
        $parts = array();

        /* Collect the languages for the theme */
        if ( is_array($this->cache) && array_key_exists('location', $this->cache) ) {
            $src = THEME_DIR . '/' . strtolower($this->cache['location']) . '/lang';
            if ( file_exists($src) ) {
                foreach ( glob($src . "/*.json") as $filename) {
                    $key = str_replace(array($src, '.json', '/'), '', $filename);
                    if ( mb_strlen($key) == 5 && in_array($key, $langs) === false ) { $langs[] = $key; }
                }
            }
        }

        $keys = array( 'PgRoot' );
        for ( $i = 1; $i <= 9; $i++ ) {
            $keys[] = 'PgSub' . $i;
        }

        foreach ( $keys as $key ) {
            if ( array_key_exists($key, $this->settings) ) {
                $vv = strtolower(NoNull($this->settings[$key]));
                if ( mb_strlen($vv) > 0 && in_array($vv, $langs) === false ) { $parts[] = $vv; }
            }
        }

        /* Return the proper path */
        if ( is_array($parts) && count($parts) > 0 ) { return implode('/', $parts); }
        return '';
    }

    /** ********************************************************************** *
     *  Page Meta Functions
     ** ********************************************************************** */
    /**
     *  Function returns the description for a given page
     */
    private function _getMetaDescription($data) {
        $text = '';

        if ( is_array($data) && array_key_exists('location', $data) ) {
            $ThemeLocation = THEME_DIR . '/' . NoNull($data['location'], 'error');
            if ( file_exists("$ThemeLocation/custom.php") ) {
                if ( $this->custom === false ) {
                    require_once("$ThemeLocation/custom.php");
                    $ClassName = ucfirst(NoNull($data['location'], 'default'));
                    $this->custom = new $ClassName( $this->settings, $this->strings );
                }
                if ( method_exists($this->custom, 'getPageDescription') ) {
                    $text = $this->custom->getPageDescription($data);
                }
            }
        }

        /* If we're here, return the site-generic description */
        return NoNull($text, $data['description']);
    }

    /**
     *  Function returns the keywords for a given page
     */
    private function _getMetaKeys($data) {
        $text = '';

        if ( is_array($data) && array_key_exists('location', $data) ) {
            $ThemeLocation = THEME_DIR . '/' . NoNull($data['location'], 'error');
            if ( file_exists("$ThemeLocation/custom.php") ) {
                if ( $this->custom === false ) {
                    require_once("$ThemeLocation/custom.php");
                    $ClassName = ucfirst(NoNull($data['location'], 'default'));
                    $this->custom = new $ClassName( $this->settings, $this->strings );
                }
                if ( method_exists($this->custom, 'getPageKeywords') ) {
                    $text = $this->custom->getPageKeywords($data);
                }
            }
        }

        /* If we're here, return the site-generic keywords */
        return NoNull($text, $data['keywords']);
    }

    /** ********************************************************************** *
     *  Schema Functions
     ** ********************************************************************** */
    /**
     *  Function returns a complete JSON+LD Schema object if the theme supports it
     */
    private function _getSchemaMeta($data) {
        if ( is_array($data) && array_key_exists('location', $data) ) {
            $ThemeLocation = THEME_DIR . '/' . NoNull($data['location'], 'error');
            if ( file_exists("$ThemeLocation/custom.php") ) {
                if ( $this->custom === false ) {
                    require_once("$ThemeLocation/custom.php");
                    $ClassName = ucfirst(NoNull($data['location'], 'default'));
                    $this->custom = new $ClassName( $this->settings, $this->strings );
                }
                if ( method_exists($this->custom, 'getSchemaMeta') ) {
                    return $this->custom->getSchemaMeta($data);
                }
            }
        }
        return '';
    }

    /** ********************************************************************** *
     *  Additional Functions
     ** ********************************************************************** */
    /**
     *  Function returns the first guid-like string found in the URL
     */
    private function _getPrimaryGuid() {
        for ( $i = 1; $i <= 9; $i++ ) {
            $kk = 'PgSub' . $i;
            if ( array_key_exists($kk, $this->settings) ) {
                $vv = NoNull($this->settings[$kk]);
                if ( mb_strlen($vv) == 36 && mb_strpos($vv, '-') > 0 ) { return $vv; }                  /* Standard GUIDs */
                if ( mb_strlen($vv) == 32 && mb_strpos($vv, '-') === false ) { return $vv; }            /* MD5 Identifiers */
            }
        }

        /* If we're here, there is likely no Guid in the URL. Return an empty string */
        return '';
    }

    /**
     *  Function attempts to determine if the HTTP request is looking for a static resource
     *      and, if it is, updates $this->settings accordingly
     */
    private function _checkStaticResourceRequest() {
        $exts = array( 'css', 'html', 'xml', 'json', 'pdf',
                       'jpg', 'jpeg', 'svg', 'gif', 'png', 'tiff',
                       'xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx',
                       'mp3', 'mp4', 'm4a',
                       'rar', 'zip', '7z',
                      );
        $uri = NoNull($this->settings['ReqURI']);
        $ext = getFileExtension($uri);

        /* If the request is a resource, treat it as a 404 */
        if ( in_array($ext, $exts) ) {
            /* If we are in a files route, then let's check for permission */
            if ( NoNull($this->settings['Route']) == 'files' ) {
                require_once(LIB_DIR . '/files.php');
                $res = new Files($this->settings, $this->strings);
                $sOK = $res->requestResource();
                unset($res);

                /* If we have successfully sent the file, then exit */
                if ( $sOK ) { exit(); }
            }

            $this->settings['PgRoot'] = '404';
            $this->settings['status'] = 404;

            if ( is_array($this->settings['errors']) === false ) { $this->settings['errors'] = array(); }
            $this->settings['errors'][] = NoNull($this->strings['msg404Detail'], "Cannot Find Requested Resource");
        }
    }
}
?>