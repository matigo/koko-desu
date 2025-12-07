<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for Webhook Handling
 */
require_once( LIB_DIR . '/functions.php');

class Route extends Midori {
    var $settings;
    var $strings;

    function __construct( $settings, $strings = false ) {
        if ( defined('USE_STRIPE') === false ) { define('USE_STRIPE', 0); }

        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $GLOBALS['site_id'] = nullInt($this->settings['site_id'], 1);
        $this->_checkSiteID();
    }

    /** ********************************************************************** *
     *  Public Functions
     ** ********************************************************************** */

    /**
     * Function performs the requested Method Activity and Returns the Results
     *      in an array.
     */
    public function getResponseData() {
        /* Determine which webhook path to follow */
        $path = strtolower(NoNull($this->settings['PgSub1'], $this->settings['PgRoot']));
        $out = '';

        /* Record the webhook */
        switch ( $path ) {
            case 'stripe':
                if ( YNBool(USE_STRIPE) === false ) { return $this->_setMetaMessage("Stripe Functionality is not enabled", 400); }

                /* Collect the POST JSON in its raw form */
                $payload = @file_get_contents('php://input');
                if ( mb_strlen($payload) <= 100 ) { return $this->_setMetaMessage("Incomplete payload recieved", 400); }

                $json = json_decode($payload, true);
                if ( is_object($json) ) { $json = objectToArray($json); }
                if ( is_array($json) === false ) { return $this->_setMetaMessage("Improper payload recieved", 400); }

                /* Handle the Event */
                $event = strtolower(NoNull($json['type']));

                switch ( $event ) {
                    case 'customer.created':
                    case 'customer.updated':
                        require_once(LIB_DIR . '/account.php');
                        $acct = new Account($this->settings, $this->strings);
                        $out = $acct->createFromStripe($json['data']['object']);
                        unset($acct);
                        break;

                    case 'invoice.payment_succeeded':
                    case 'invoice.paid':
                        require_once(LIB_DIR . '/billing.php');
                        $bill = new Billing($this->settings, $this->strings);
                        $out = $bill->createFromStripe($json['data']['object']);
                        unset($bill);
                        break;

                    default:
                        writeNote('Stripe Webhook [Unused]: ' . $event, true);
                        writeNote(json_encode($json, JSON_UNESCAPED_UNICODE), true);
                }
                break;

            default:
                /* Do Nothing */
        }

        /* Return a string */
        return NoNull($out, "No Dice");
    }

    /**
     *  Function Returns the Response Type (HTML / XML / JSON / etc.)
     */
    public function getResponseType() { return NoNull($this->settings['type'], 'application/json'); }

    /**
     *  Function Returns the Reponse Code (200 / 201 / 400 / 401 / etc.)
     */
    public function getResponseCode() { return nullInt($this->settings['status'], 200); }

    /**
     *  Function Returns any Error Messages that might have been raised
     */
    public function getResponseMeta() { return is_array($this->settings['errors']) ? $this->settings['errors'] : false; }

    /**
     *  Function Returns the "HasMore" Meta Value if it Exists
     */
    public function getHasMore() { return false; }

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
}
?>