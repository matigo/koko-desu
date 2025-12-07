<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for Site Management and Statistics
 */
require_once( LIB_DIR . '/functions.php');

class Site {
    var $settings;

    function __construct( $settings ) {
        $this->settings = $settings;
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = NoNull(strtolower($this->settings['ReqType']));

        /* Check the Account Token is Valid */
        if ( !$this->settings['_logged_in']) {
            $this->_setMetaMessage("You need to sign in before using this API endpoint", 403);
            return false;
        }

        /* Perform the Action */
        switch ( $ReqType ) {
            case 'get':
                return $this->_performGetAction();
                break;

            case 'post':
            case 'put':
                return $this->_performPostAction();
                break;

            case 'delete':
                return $this->_performDeleteAction();
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return false;
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case 'info':
                $rVal = array();
                break;

            case 'usage':
            case 'stats':
                return $this->_getSiteStats();
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return false;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case 'set':
            case '':
                return false;
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return false;
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case '':
                $rVal = array( 'activity' => "[DELETE] /site/$Activity" );
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return false;
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

    /** ********************************************************************* *
     *  Public Functions
     ** ********************************************************************* */
    public function getSiteData() { return $this->_getSiteData(); }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    /**
     *  Function Collects the Site Data and Returns an Array
     *
     *  IMPORTANT: getGlobalObject is being used rather than getCacheObject to reduce the risk
     *             of returning stale data
     */
    private function _getSiteData() {
        $SiteURL = sqlScrub( NoNull($this->settings['site_url'],$_SERVER['SERVER_NAME']) );
        $data = getGlobalObject('site_' . $SiteURL);

        /* Collect the Requisite Data if we don't already have it */
        if ( is_array($data) === false ) {
            /* Ensure there is a default Site.id Set */
            if ( defined('DEFAULT_SITEID') === false ) { define('DEFAULT_SITEID', 1); }

            $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                              '[DEFAULT_ID]' => nullInt(DEFAULT_SITEID, 1),
                              '[SITE_TOKEN]' => sqlScrub(mb_substr(NoNull($this->settings['site_token']), 0, 256)),
                              '[SITE_PASS]'  => sqlScrub(mb_substr($SitePass, 0, 512)),
                              '[SITE_URL]'   => strtolower($SiteURL),
                              '[REQ_URI]'    => sqlScrub(mb_substr(NoNull($this->settings['ReqURI'], '/'), 0, 512)),
                             );
            $sqlStr = readResource(SQL_DIR . '/site/getSiteData.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                $siteVersion = 0;
                $siteId = 0;

                foreach ( $rslt as $Row ) {
                    if ( NoNull($Row['site_token']) != '' && $SitePass != '' ) {
                        $LifeSpan = time() + COOKIE_EXPY;
                        setcookie( 'site_token', NoNull($Row['site_token']), $LifeSpan, "/", NoNull(strtolower($_SERVER['SERVER_NAME'])) );
                    }

                    $siteVersion = nullInt($Row['version']);
                    $siteId = nullInt($Row['site_id']);

                    /* Build the Array */
                    $data = array( 'HomeURL'        => NoNull($Row['site_url']),
                                   'api_url'        => getApiUrl(),
                                   'cdn_url'        => getCdnUrl(),

                                   'name'           => NoNull($Row['site_name']),
                                   'description'    => NoNull($Row['description']),
                                   'keywords'       => NoNull($Row['keywords']),
                                   'summary'        => NoNull($Row['summary']),
                                   'location'       => NoNull($Row['theme']),
                                   'language_code'  => NoNull($Row['language_code']),
                                   'locale'         => NoNull($Row['locale_code']),

                                   'license'        => NoNull($Row['license'], 'CC BY-NC-ND'),
                                   'is_default'     => YNBool($Row['is_default']),

                                   'site_id'        => nullInt($Row['site_id']),
                                   'site_guid'      => NoNull($Row['site_guid']),
                                   'site_version'   => nullInt($Row['version']),
                                   'updated_at'     => apiDate($Row['updated_at'], 'Z'),
                                   'updated_unix'   => apiDate($Row['updated_at'], 'U'),

                                   'banner_src'     => NoNull($Row['banner_src']),

                                   'color'          => NoNull($Row['theme_color'], 'auto'),
                                   'font-family'    => NoNull($Row['font_family'], 'default'),
                                   'font-size'      => NoNull($Row['font_size'], 'md'),

                                   'protocol'       => (YNBool($Row['https'])) ? 'https' : 'http',
                                   'https'          => YNBool($Row['https']),
                                   'do_redirect'    => YNBool($Row['do_redirect']),
                                  );
                }

                /* Collect any metadata that might exist */
                $meta = $this->_getSiteMeta( $siteId, $siteVersion );
                if ( is_array($meta) && count($meta) > 0 ) {
                    foreach ( $meta as $Key=>$Value ) {
                        $data[$Key] = $Value;
                    }
                }

                /* Save the Site data to In-Memory Cache */
                if ( $siteId > 0 ) { setGlobalObject('siteid', nullInt($data['site_id'])); }
                if ( is_array($data) && count($data) > 0 ) { setGlobalObject('site_' . $SiteURL, $data); }
            }
        }

        /* If we have data, let's return it. Otherwise, unhappy boolean. */
        if ( is_array($data) && count($data) > 0 ) { return $data; }
        return false;
    }

    /**
     *  Function returns all metadata for the current site
     */
    private function _getSiteMeta($siteId, $version) {
        if ( nullInt($version) < 0 ) { $version = 0; }
        if ( nullInt($siteId) < 0 ) { $siteId = 0; }
        $CacheKey = 'site_' . substr('00000000' . nullInt($siteId), -8) . '-' . nullInt($version);
        $data = getCacheObject($CacheKey);

        /* If we do not have data, query the database and cache the results */
        if ( is_array($data) === false || count($data) <= 0 ) {
            $ReplStr = array( '[SITE_ID]' => nullInt($siteId) );
            $sqlStr = readResource(SQL_DIR . '/site/getSiteMeta.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                $data = array();
                foreach ( $rslt as $Row ) {
                    $data[NoNull($Row['key'])] = NoNull($Row['value']);
                }
            }

            /* Save the results to cache */
            if ( is_array($data) && count($data) > 0 ) { setCacheObject($CacheKey, $data); }
        }

        /* If we have data, let's return it. Otherwise, unhappy boolean. */
        if ( is_array($data) && count($data) > 0 ) { return $data; }
        return false;
    }

    /** ********************************************************************* *
     *  Statistic Functions
     ** ********************************************************************* */
    /**
     *  Function returns usage details for a given Site.id. If no data is found,
     *      an unhappy boolean is returned
     */
    private function _getSiteStats() {
        $CleanDays = nullInt($this->settings['days_back'], $this->settings['days']);
        $CleanGuid = NoNull($this->settings['site_guid'], $this->settings['guid']);

        /* Ensure we have a proper Site.guid */
        if ( mb_strlen($CleanGuid) != 36 ) {
            $this->_setMetaMessage("Invalid Site.guid Provided", 400);
            return false;
        }

        /* Ensure the Date Range is not greater than one month */
        if ( $CleanDays > 30 ) { $CleanDays = 30; }
        if ( $CleanDays < 1 ) { $CleanDays = 14; }

        /* Collect the Data */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[SITE_GUID]'  => sqlScrub($CleanGuid),
                          '[DAYS]'       => nullInt($CleanDays),
                         );
        $sqlStr = readResource(SQL_DIR . '/site/getSiteStats.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();
            $idx = 0;

            foreach ( $rslt as $Row ) {
                $data[] = array( 'idx'         => $idx,
                                 'event_on'    => NoNull($Row['event_on']),
                                 'event_unix'  => nullInt($Row['event_unix']),

                                 'pages'       => nullInt($Row['pages']),
                                 'files'       => nullInt($Row['files']),
                                 'cids'        => nullInt($Row['cids']),
                                 'page_agents' => nullInt($Row['page_agents']),
                                 'file_agents' => nullInt($Row['file_agents'])
                                );
                $idx++;
            }

            /* If we have data, return it */
            if ( is_array($data) && count($data) > 0 ) { return $data; }
        }

        /* If we're here, there's nothing */
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