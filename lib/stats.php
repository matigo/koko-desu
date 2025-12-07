<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called to work with Statistics
 */
require_once( LIB_DIR . '/functions.php');

class Stats {
    var $settings;
    var $strings;

    function __construct( $settings, $strings = false ) {
        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = NoNull(strtolower($this->settings['ReqType']));

        /* Check the Account Token is Valid */
        if ( !$this->settings['_logged_in']) {
            return $this->_setMetaMessage("You need to sign in before using this API endpoint", 403);
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
                // Do Nothing
        }

        /* If we're here, there's nothing */
        return false;
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case 'clients':
            case 'client':
                return $this->_getSiteStatsByClients();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, there's nothing */
        return false;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case '':
                return false;
                break;

            default:
                // Do Nothing
        }

        /* If we're here, there's nothing */
        return false;
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case '':
                return false;
                break;

            default:
                // Do Nothing
        }

        /* If we're here, there's nothing */
        return false;
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

    /** ********************************************************************* *
     *  Public Properties & Functions
     ** ********************************************************************* */

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    private function _getSiteStatsByClients() {
        $CleanLimit = nullInt($this->settings['limit'], $this->settings['count']);
        $CleanDays = nullInt($this->settings['days_back'], $this->settings['days']);
        $CleanSite = nullInt($this->settings['site_id'], $this->settings['site']);

        /* Perform Some Basic Validation */
        if ( nullInt($CleanSite) <= 0 ) {
            $this->_setMetaMessage("Invalid Site identifier provided", 400);
            return false;
        }

        if ( $CleanLimit > 25 ) { $CleanLimit = 25; }
        if ( $CleanLimit <= 0 ) { $CleanLimit = 10; }

        if ( $CleanDays > 30 ) { $CleanDays = 30; }
        if ( $CleanDays <= 0 ) { $CleanDays = 14; }

        $ReplStr = array( '[SITE_ID]' => nullInt($CleanSite),
                          '[COUNT]'   => nullInt($CleanLimit),
                          '[DAYS]'    => nullint($CleanDays)
                         );
        $sqlStr = readResource(SQL_DIR . '/stats/getSiteStatsByClients.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();

            foreach ( $rslt as $Row ) {
                $data[] = array( 'id'       => nullInt($Row['client_id']),
                                 'name'     => NoNull($Row['name']),
                                 'code'     => NoNull($Row['short_code']),
                                 'pages'    => nullInt($Row['pages']),
                                 'visitors' => nullInt($Row['visitors']),
                                );
            }

            /* If we have data, let's return it */
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