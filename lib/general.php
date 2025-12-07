<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains general queries for mostly-static resources
 */
require_once( LIB_DIR . '/functions.php');

class General {
    var $settings;
    var $strings;
    var $parser;
    var $cache;

    function __construct( $settings, $strings = false ) {
        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $this->cache = array();
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
                // Do Nothing
        }

        /* If we're here, there's nothing */
        $this->_setMetaMessage("Could not recognize [" . strtoupper($ReqType) . "] Activity");
        return false;
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case 'avatars_list':
            case 'avatar_list':
            case 'avatars':
                return $this->_getAvatarList();
                break;

            case 'country_list':
            case 'countries':
                return $this->_getCountries();
                break;

            case 'timezone_list':
            case 'timezones':
            case 'timezone':
                return $this->_getTimezoneList();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, there's nothing */
        $this->_setMetaMessage("Nothing to do: [GET] $Activity", 404);
        return false;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            default:
                // Do Nothing
        }

        /* If we're here, there's nothing */
        $this->_setMetaMessage("Nothing to do: [POST] $Activity", 404);
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
        $this->_setMetaMessage("Nothing to do: [DELETE] $Activity", 404);
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
     *  Countries, States, and Postal Codes
     ** ********************************************************************* */
    /**
     *  Function returns a list of available avatars for a given Account
     */
    private function _getAvatarList() {
        $uniques = array( $this->settings['_avatar_file'] );
        $jsonFile = FLATS_DIR . '/resources/avatars.json';
        $SiteUrl = NoNull($this->settings['HomeURL']);
        $prefix = BASE_DIR . '/avatars/';
        $data = array();

        /* Collect the List of Avatars and, if the file exists, add it to the uniques array */
        if ( file_exists($jsonFile) ) {
            $json = json_decode(readResource($jsonFile), true);
            if ( is_array($json['icons']) ) {
                foreach ( $json['icons'] as $avatar ) {
                    if ( file_exists($prefix . $avatar) ) {
                        if ( in_array($avatar, $uniques) === false ) { $uniques[] = $avatar; }
                    }
                }
            }

            /* Now let's ensure we have a proper array of data constructed */
            foreach ( $uniques as $avatar ) {
                $selected = false;
                if ( $avatar == $this->settings['_avatar_file'] ) { $selected = true; }

                $data[] = array( 'name' => $avatar,
                                 'url'  => $SiteUrl . '/avatars/' . $avatar,
                                 'size' => filesize($prefix . $avatar),
                                 'selected' => $selected
                                );
            }

            /* If we have data, let's return it */
            if ( count($data) > 0 ) { return $data; }
        }

        /* If there is no data, then return just the default */
        return array( 'name' => 'default.png',
                      'url'  => $SiteUrl . '/avatars/default.png',
                      'size' => 0,
                      'selected' => true
                     );
    }

    /**
     *  Function returns a list of Countries
     */
    private function _getCountries() {
        $sqlStr = readResource(SQL_DIR . '/general/getCountryList.sql');
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();

            foreach ( $rslt as $Row ) {
                $data[] = array( 'code' => NoNull($Row['code']),
                                 'name' => NoNull($this->strings[$Row['label']], $Row['name']),
                                );
            }

            /* If we have data, let's return it */
            if ( is_array($data) && count($data) > 0 ) { return $data; }
        }

        /* If we're here, there is no Objective record for the provided GUID */
        if ( mb_strlen($guid) <= 0 ) { $this->_setMetaMessage("No Objectives Found", 404); }
        return false;
    }

    /**
     *  Function returns a list of available Timezones for a given Account
     */
    private function _getTimezoneList() {
        $jsonFile = FLATS_DIR . '/resources/timezones.json';

        if ( file_exists($jsonFile) ) {
            $data = json_decode(readResource($jsonFile), true);
            if ( is_array($data) && count($data) > 0 ) { return $data; }
        }

        /* If we're here, there isn't a timezone file to read from */
        return false;
    }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */

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