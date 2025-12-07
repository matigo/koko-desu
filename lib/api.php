<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for API Handling
 */
require_once( LIB_DIR . '/functions.php');

class Route extends Midori {
    var $settings;
    var $strings;

    function __construct( $settings, $strings = false ) {
        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $GLOBALS['site_id'] = nullInt($this->settings['site_id'], 1);
    }

    /** ********************************************************************** *
     *  Public Functions
     ** ********************************************************************** */

    /**
     * Function performs the requested Method Activity and Returns the Results
     *      in an array.
     */
    public function getResponseData() {
        $PgRoot = strtolower(NoNull($this->settings['PgRoot']));
        $data = false;

        /* Do we know the Site.ID? */
        $this->_checkSiteID();

        /* Validate the Page Root */
        $ReplStr = array( 'accounts' => 'account', 'file' => 'files', 'generic' => 'generics', 'locations' => 'location', 'sites' => 'site', 'webmentions' => 'webmention' );
        if ( array_key_exists($PgRoot, $ReplStr) ) { $PgRoot = $ReplStr[$PgRoot]; }

        /* If the API Endpoint Exists (and is Active), Perform the Action */
        $Resource = LIB_DIR . '/' . $PgRoot . '.php';
        if ( file_exists($Resource) ) {
            require_once( $Resource );
            $ClassName = ucfirst($PgRoot);
            $res = new $ClassName( $this->settings, $this->strings );
            $data = $res->performAction();
            $this->settings['errors'] = $res->getResponseMeta();
            $this->settings['status'] = $res->getResponseCode();
            if ( method_exists($res, 'getHasMore') ) {
                $this->settings['more'] = $res->getHasMore();
            }
            unset($res);

        } else {
            $this->settings['errors'] = array( 'Invalid API Endpoint' );
            $this->settings['status'] = 400;
        }

        /* Return the Array */
        return $this->_returnData($data);
    }

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
     *  Function Returns the "HasMore" Meta Value if it Exists
     */
    public function getHasMore() {
        if ( array_key_exists('more', $this->settings) ) {
            return BoolYN(YNBool($this->settings['more']));
        } else {
            return false;
        }
    }

    /** ********************************************************************** *
     *  Private Functions
     ** ********************************************************************** */
    /**
     *  Function sets the Site.id if it is not already known
     */
    private function _checkSiteID() {
        $sid = nullInt(getGlobalObject('site_id'));
        if ( $sid <= 0 ) {
            $domain = NoNull(str_replace(array('https://', 'http://'), '', NoNull($_SERVER['SERVER_NAME'], $this->settings['HomeURL'])));
            if ( mb_strlen($domain) <= 3 ) { return 0; }

            $CacheKey = 'site_' . md5($domain);
            $data = getCacheObject($CacheKey);
            if ( is_array($data) === false ) {
                $ReplStr = array( '[DOMAIN]' => sqlScrub($domain) );
                $sqlStr = readResource(SQL_DIR . '/system/getSiteId.sql', $ReplStr);
                $rslt = doSQLQuery($sqlStr);
                if ( is_array($rslt) ) {
                    foreach ( $rslt as $Row ) {
                        $data = array( 'site_id' => nullInt($Row['site_id']) );
                    }

                    /* Save the Data if Valid */
                    if ( is_array($data) ) { setCacheObject($CacheKey, $data); }
                }
            }

            /* Set the Site.id */
            $sid = nullInt($data['site_id']);
            setGlobalObject('site_id', $sid);
        }

        /* Return the Site.id */
        return nullInt($sid);
    }

    /**
     *  Function Sets a Message in the Meta Field
     */
    private function _setMetaMessage( $msg, $code = 0 ) {
        if ( is_array($this->settings['errors']) === false ) { $this->settings['errors'] = array(); }
        if ( NoNull($msg) != '' ) { $this->settings['errors'][] = NoNull($msg); }
        if ( $code > 0 && nullInt($this->settings['status']) == 0 ) { $this->settings['status'] = nullInt($code); }
        return false;
    }

    /**
     *  Function Returns a Formatted Array
     */
    private function _returnData( $data ) {
        $PgRoot = strtolower(NoNull($this->settings['PgRoot']));
        $valids = array( 'account', 'auth', 'contact', 'system', 'webmention' );
        $code = 0;

        /* Do we have an API-level Permission Override? */
        $isOK = YNBool(getGlobalObject('api_req_valid'));

        /* If We're Not Logged In, The Request is Invalid */
        if ( $isOK === false ) {
            if ( $this->settings['_logged_in'] === false && in_array($PgRoot, $valids) === false ) {
                $this->_setMetaMessage( "You Need to Log In First" );
                $data = array();
                $code = 403;
            }
        }

        /* If the Data Is Not An Array, Handle The Issue */
        if ( is_array($data) === false && $code == 200 ) {
            if ( is_bool($data) ) {
                $this->_setMetaMessage( "Invalid API Request" );
                $code = 404;

            } else {
                if ( is_string($data) ) {
                    $mtxt = NoNull($data, NoNull($message, "Invalid Request"));
                    $code = 400;

                } else {
                    $mtxt = "Invalid API Endpoint";
                    $code = 404;
                }
            }
        }

        // Set the Status and Return the Array of Data or an Unhappy Boolean
        if ( $code > 0 ) { $this->settings['status'] = nullInt($code); }
        return $data;
    }
}
?>