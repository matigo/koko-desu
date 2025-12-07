<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for Location listings
 */
require_once(LIB_DIR . '/functions.php');
require_once(LIB_DIR . '/markdown/MarkdownExtra.inc.php');
use Michelf\MarkdownExtra;

class Location {
    var $settings;
    var $strings;
    var $parser;

    function __construct( $settings, $strings = false ) {
        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $this->parser = false;
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = strtolower(NoNull($this->settings['ReqType'], 'GET'));

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
        return $this->_setMetaMessage("Unrecognized Request Type: " . strtoupper($ReqType), 404);
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'read'; }

        switch ( $Activity ) {
            case 'search':
            case 'list':
                break;

            case 'mine':
                break;

            case 'random':
            case 'today':
                return $this->_getLocationRandom();
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Nothing to do: [GET] $Activity", 404);
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'write'; }

        switch ( $Activity ) {
            case 'check':
                return $this->_checkLocationAttempt();
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Nothing to do: [POST] $Activity", 404);
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'scrub'; }

        switch ( $Activity ) {
            case 'scrub':
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        return $this->_setMetaMessage("Nothing to do: [DELETE] $Activity", 404);
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
    public function getLocationSitemap($typeList, $dogsOk = false, $kidsOk = false) { return $this->_getLocationSitemap($typeList, $dogsOk, $kidsOk); }
    public function isValidLocation($key = '') { return $this->_isValidLocation($key); }
    public function getTitleByKey($key = '') { return $this->_getTitleByKey($key); }

    /** ********************************************************************* *
     *  Cleaning Functions
     ** ********************************************************************* */
    /**
     *  Function does all the necessary cleaning of input values and returns a consistent, clean array
     */
    private function _getInputValues() {
        $CleanGuid = NoNull($this->settings['location_guid'], NoNull($this->settings['location'], $this->settings['guid']));
        $CleanDate = NoNull($this->settings['date'], $this->settings['day']);
        if ( validateDate($CleanDate) === false ) { $CleanDate = ''; }

        $CleanCountry = strtoupper(NoNull($this->settings['country_code'], $this->settings['country']));
        if ( mb_strlen($CleanCountry) != 2 ) { $CleanCountry = ''; }

        /* Determine the provided lookups */
        $CleanKeywords = str_ireplace(array(' ', '　', ',', ':', ';', '|'), ' ', NoNull($this->settings['keywords'], $this->settings['lookup']));
        $words = array();
        $ww = explode(' ', $CleanKeywords);
        if ( is_array($ww) && count($ww) > 0 ) {
            foreach ( $ww as $wd ) {
                $wd = strtolower(NoNull($wd));
                if ( mb_strlen($wd) > 0 && in_array($wd, $words) === false ) { $words[] = $wd; }
            }
        }

        /* Were we handed a key? */
        $CleanKey = NoNull($this->settings['location_key'], $this->settings['key']);
        if ( mb_strlen($CleanKey) != 6 ) { $CleanKey = ''; }

        /* Visitor Info */
        $CleanVisitor = NoNull($this->settings['_visitor_id'], $this->settings['visitor_id']);

        /* Coordinates */
        $CleanLongitude = nullInt($this->settings['longitude'], $this->settings['long']);
        $CleanLatitude = nullInt($this->settings['latitude'], $this->settings['lat']);

        if ( $CleanLongitude < -180 || $CleanLongitude > 180 ) { $CleanLongitude = 0; }
        if ( $CleanLatitude < -90 || $CleanLatitude > 90 ) { $CleanLatitude = 0; }

        /* Time and Hints */
        $CleanSeconds = nullInt($this->settings['second_count'], $this->settings['seconds']);
        $CleanHints = nullInt($this->settings['hint_count'], $this->settings['hints']);

        if ( $CleanSeconds > 999 || $CleanSeconds < 0 ) { $CleanSeconds = -1; }
        if ( $CleanHints > 999 || $CleanHints < 0 ) { $CleanHints = -1; }

        /* Boolean Checks */
        $IsPublished = YNBool(NoNull($this->settings['is_published'], $this->settings['published']));
        $IsVisible = YNBool(NoNull($this->settings['is_visible'], $this->settings['visible']));

        /* Are there any filters or limits? */
        $CleanCount = nullInt($this->settings['count'], $this->settings['limit']);
        $CleanPage = nullInt($this->settings['page']);

        if ( $CleanCount > 250 ) { $CleanCount = 250; }
        if ( $CleanCount <= 0 ) { $CleanCount = 25; }
        if ( $CleanPage < 0 ) { $CleanPage = 0; }

        /* Return the array */
        return array( 'guid'  => NoNull($CleanGuid),
                      'date'  => NoNull($CleanDate),
                      'key'   => NoNull($CleanKey),

                      'country_code' => NoNull($CleanCountry),

                      'longitude' => nullInt($CleanLongitude),
                      'latitude'  => nullInt($CleanLatitude),

                      'seconds' => nullInt($CleanSeconds),
                      'hints'   => nullInt($CleanHints),

                      'is_published' => YNBool($IsPublished),
                      'is_visible'   => YNBool($IsVisible),

                      'count' => $CleanCount,
                      'page'  => $CleanPage,
                     );
    }

    /** ********************************************************************* *
     *  Read & List Functions
     ** ********************************************************************* */
    /**
     *  Function returns a random Location record (Ideally, one that hasn't been done yet)
     */
    private function _getLocationRandom() {
        $inputs = $this->_getInputValues();
        setGlobalObject('api_req_valid', 1);

        /* Ensure we have a country code specified */
        if ( mb_strlen(NoNull($inputs['country_code'])) != 2 ) { $inputs['country_code'] = 'JP'; }

        /* Collect the random Location */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[COUNTRY_CODE]' => sqlScrub($inputs['country_code'])
                         );
        $sqlStr = readResource(SQL_DIR . '/location/getLocationRandom.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = false;

            foreach ( $rslt as $Row ) {
                $id = nullInt($Row['id']);
                if ( $id > 0 ) {
                    $data = $this->_getLocationById($id);
                }
            }

            /* If we have valid data, let's return it */
            if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
        }

        /* We do not want to be here */
        return $this->_setMetaMessage("Could not find a random Location record", 404);
    }


    private function _getLocationByKey( $key = '' ) {
        if ( mb_strlen(NoNull($key)) != 6 ) { return $this->_setMetaMessage("Invalid Location Key provided", 400); }
        $idx = alphaToInt($key);

        /* If we have a valid-looking ID, collect the data */
        if ( $idx > 0 ) {
            $data = $this->_getLocationById($idx);
            if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
        }

        /* If we're here, there is no match */
        return $this->_setMetaMessage("There is no Location for the given key", 404);
    }

    /**
     *  Function collects a Location by the Id supplied
     */
    private function _getLocationById( $id = 0 ) {
        if ( nullInt($id) <= 0 ) { return false; }

        $ReplStr = array( '[ACCOUNT_ID]'  => NoNull($this->settings['_account_id']),
                          '[LOCATION_ID]' => nullInt($id),
                         );
        $sqlStr = readResource(SQL_DIR . '/location/getLocationById.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                return $this->_buildLocationObject($Row);
            }
        }

        /* We do not want to be here */
        return false;
    }

    /**
     *  Function constructs a standardized Location object based on the supplied array
     */
    private function _buildLocationObject( $data ) {
        if ( is_array($data) === false || mb_strlen(NoNull($data['guid'])) != 36 ) { return false; }
        $cdnUrl = getCdnUrl();

        /* If this belongs to the current account, say so */
        $is_yours = false;
        if ( YNBool($this->settings['_logged_in']) && nullInt($data['created_by']) == nullInt($this->settings['_account_id']) ) { $is_yours = true; }

        /* If we have metadata, collect it */
        $meta = false;
        if ( YNBool($data['has_meta']) ) {

        }

        /* Prep the Country/State/Region Values */
        $country = false;
        if ( mb_strlen(NoNull($data['country_code'])) == 2 ) {
            $country = array( 'code' => NoNull($data['country_code']),
                              'name' => NoNull($this->strings[$data['country_label']], $data['country_name']),
                             );
        }
        $state = false;
        if ( nullInt($data['state_id']) > 0 ) {
            $state = array( 'id'   => NoNull($data['state_id']),
                            'name' => NoNull($this->strings[$data['state_label']], $data['state_name']),
                           );
        }
        $region = false;
        if ( nullInt($data['region_id']) > 0 ) {
            $region = array( 'id'   => NoNull($data['region_id']),
                             'name' => NoNull($this->strings[$data['region_label']], $data['region_name']),
                            );
        }
        $city = false;

        /* If we have a note, construct it */
        $note = false;
        if ( nullInt($data['note_id']) > 0 ) {
            $note = array( 'guid' => NoNull($data['note_guid']),
                           'type' => NoNull($data['note_type']),
                           'text' => NoNull($data['note_text']),
                           'hash' => NoNull($data['note_hash']),
                           'is_private' => YNBool($data['note_private']),
                          );
        }

        /* Return a completed array */
        return array( 'guid'    => NoNull($data['guid']),
                      'name'    => NoNull($data['name']),
                      'src'     => $cdnUrl . '/' . NoNull($data['src']),
                      'key'     => intToAlpha($data['idx']),

                      'longitude' => nullInt($data['longitude']),
                      'latitude'  => nullInt($data['latitude']),

                      'country'     => $country,
                      'state'       => $state,
                      'region'      => $region,
                      'city'        => $city,

                      'photo_at'     => apiDate($data['photo_unix'], 'Z'),
                      'photo_unix'   => apiDate($data['photo_unix'], 'Z'),
                      'is_published' => YNBool($data['is_published']),
                      'is_visible'   => YNBool($data['is_visible']),
                      'is_yours'     => $is_yours,

                      'note'         => $note,
                      'meta'         => $meta,

                      'credit'       => array( 'guid' => NoNull($data['account_guid']),
                                               'name' => NoNull($data['account_name'], $data['first_name']),
                                              ),

                      'created_at'   => apiDate($data['created_unix'], 'Z'),
                      'created_unix' => apiDate($data['created_unix'], 'Z'),
                      'updated_at'   => apiDate($data['updated_unix'], 'Z'),
                      'updated_unix' => apiDate($data['updated_unix'], 'Z'),
                     );
    }

    /** ********************************************************************* *
     *  Attempt Verification Functions
     ** ********************************************************************* */
    /**
     *  Function checks if the person found the Location and returns an Attempt response
     */
    private function _checkLocationAttempt() {
        $inputs = $this->_getInputValues();
        setGlobalObject('api_req_valid', 1);

        /* Perform some sanity checking */
        if ( mb_strlen($inputs['key']) != 6 ) { return $this->_setMetaMessage("Please provide a proper Location key", 400); }
        if ( nullInt($inputs['longitude']) == 0 && nullInt($inputs['latitude']) == 0 ) {
            return $this->_setMetaMessage("Please provide a proper Latitude and Longitude value", 400);
        }
        $seconds = nullInt($inputs['seconds']);
        $hints = nullInt($inputs['hints']);

        /* Collect the Location object */
        $location = $this->_getLocationById(alphaToInt($inputs['key']));
        if ( is_array($location) ) {
            $score = 1000;
            $km = $this->_getDistanceInKm($inputs['latitude'], $inputs['longitude'], $location['latitude'], $location['longitude']);

            /* Deduct for Distance */
            if ( nullInt($km) > 0 ) {
                $kk = 15 * floor($km / 10);
                if ( $km > 750 ) { $kk = 800; }
                if ( $km > 999 ) { $kk = 1000; }
                if ( $kk < 1 ) { $kk = 0; }

                $score -= $kk;
            }

            /* Deduct for Seconds */
            if ( nullInt($seconds) > 0 ) {
                $ss = 5 * nullInt($seconds / 30);
                if ( $ss > 100 ) { $ss = 100; }
                if ( $ss < 10 ) { $ss = 0; }

                $score -= $ss;
            }

            /* Deduct for Hints */
            if ( nullInt($hints) > 0 ) {
                $hh = 5 * nullInt($hints);
                if ( $hh > 100 ) { $hh = 100; }
                if ( $hh < 0 ) { $hh = 0; }

                $score -= $hh;
            }

            /* Ensure the Score is semi-logical */
            if ( $score > 1000 ) { $score = 1000; }
            if ( $score < 0 ) { $score = 0; }

            /* Return an array */
            return array( 'key' => NoNull($inputs['key']),
                          'location'  => $location,
                          'distance'  => $km,
                          'score'     => floor($score),

                          'longitude' => $inputs['longitude'],
                          'latitude'  => $inputs['latitude'],
                          'seconds'   => nullInt($seconds),
                          'hints'     => nullInt($hints),
                         );
        }

        /* We don't want to be here */
        return $this->_setMetaMessage("Could not check Location Attempt data", 400);
    }

    /** ********************************************************************* *
     *  Class Functions
     ** ********************************************************************* */
    /**
     *  Function returns a boolean response stating if string is found in Location.state column
     */
    private function _getDistanceInKm($lat1, $lon1, $lat2, $lon2) {
        /* Earth's radius */
        $R = 6371;

        /* Get the Radians */
        $dLat = deg2rad($lat2-$lat1);
        $dLon = deg2rad($lon2-$lon1);
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);

        /* Get the C value */
        $a = sin($dLat/2) * sin($dLat/2) +
             sin($dLon/2) * sin($dLon/2) * cos($lat1) * cos($lat2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        /* Return the distance in KM */
        return $R * $c;
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