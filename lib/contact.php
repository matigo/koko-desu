<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for Contact and Communication functions
 */
require_once(LIB_DIR . '/functions.php');

class Contact {
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
            case 'list':
                return $this->_getMessageList();
                break;

            case 'read':
                return $this->_getMessage();
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
            case 'write':
            case 'send':
                return $this->_setMessage();
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
                return $this->_scrubMessage();
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

    /** ********************************************************************* *
     *  Cleaning Functions
     ** ********************************************************************* */
    /**
     *  Function does all the necessary cleaning of input values and returns a consistent, clean array
     */
    private function _getInputValues() {
        $CleanMessage = NoNull($this->settings['message'], $this->settings['content']);
        $CleanSubject = NoNull($this->settings['subject'], $this->settings['title']);
        $CleanName = NoNull($this->settings['full_name'], $this->settings['name']);
        $CleanMail = strtolower(NoNull($this->settings['email'], $this->settings['mail']));
        $CleanGuid = NoNull($this->settings['message_guid'], NoNull($this->settings['message'], $this->settings['guid']));

        /*Validate the email address */
        if ( validateEmail($CleanMail) === false ) { $CleanMail = ''; }

        /* Are there any filters or limits? */
        $CleanCount = nullInt($this->settings['count'], $this->settings['limit']);
        $CleanPage = nullInt($this->settings['page']);

        if ( $CleanCount > 1000 ) { $CleanCount = 1000; }
        if ( $CleanCount <= 0 ) { $CleanCount = 250; }
        if ( $CleanPage < 0 ) { $CleanPage = 0; }

        /* Return the array */
        return array( 'guid'    => NoNull($CleanGuid),

                      'message' => NoNull($CleanMessage),
                      'subject' => NoNull($CleanSubject),
                      'name'    => NoNull($CleanName),
                      'mail'    => NoNull($CleanMail),

                      'count'   => $CleanCount,
                      'page'    => $CleanPage,
                     );
    }

    /** ********************************************************************* *
     *  Read Functions
     ** ********************************************************************* */
    /**
     *  Function returns a list of messages for the current account
     */
    private function _getMessageList() {
        $inputs = $this->_getInputValues();
        $data = false;

        /* If we have valid data, let's return it. Otherwise, 404 */
        if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
        return $this->_setMetaMessage("Could not find requested report", 404);
    }

    /**
     *  Function returns a specific message
     */
    private function _getMessage() {
        $inputs = $this->_getInputValues();

        /* Ensure we have an identifier */
        if ( mb_strlen(NoNull($inputs['guid'])) != 36 ) { return $this->_setMetaMessage("Invalid Message ID supplied", 400); }

        /* Collect the message */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[GUID]'       => sqlScrub($inputs['guid']),
                         );
        $sqlStr = readResource(SQL_DIR . '/contact/getMessage.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                $data = $this->_buildMessage($Row);
                if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
            }
        }

        /* If we're here, the Guid is invalid */
        return $this->_setMetaMessage("Could not find requested message", 404);
    }

    /** ********************************************************************* *
     *  Write Functions
     ** ********************************************************************* */
    /**
     *  Function records a message and returns a summary or an unhappy boolean
     */
    private function _setMessage() {
        $inputs = $this->_getInputValues();
        $Reqs = array( 'message', 'name', 'mail' );

        /* Ensure the minimums are in place */
        foreach ( $Reqs as $key ) {
            $key = strtolower($key);
            if ( mb_strlen(NoNull($inputs[$key])) <= 0 ) { return $this->_setMetaMessage("There is no [$key] value to record", 400); }
        }

        /* Record the data */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[MESSAGE]'    => sqlScrub($inputs['message']),
                          '[SUBJECT]'    => sqlScrub($inputs['subject']),
                          '[NAME]'       => sqlScrub($inputs['name']),
                          '[MAIL]'       => sqlScrub($inputs['mail']),
                         );
        $sqlStr = readResource(SQL_DIR . '/contact/setMessage.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                $data = $this->_buildMessage($Row);

                /* If we have data, let's return it */
                if ( mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
            }
        }

        /* If we're here, the message could not be saved */
        return $this->_setMetaMessage("Could not record message", 400);
    }

    /** ********************************************************************* *
     *  Scrub Functions
     ** ********************************************************************* */


    /** ********************************************************************* *
     *  Formatting Functions
     ** ********************************************************************* */
    /**
     *  Function formats a dataset to a standardised message array
     */
    private function _buildMessage( $Row ) {
        if ( is_array($Row) === false || count($Row) <= 0 ) { return false; }
        $data = false;

        /* If we have an array that contains a Guid, we probably have a proper record to process */
        if ( is_array($Row) && mb_strlen(NoNull($Row['guid'])) == 36 ) {
            $data = array( 'guid'    => NoNull($Row['guid']),
                           'name'    => NoNull($Row['name']),
                           'subject' => NoNull($Row['subject']),
                           'message' => NoNull($Row['message']),
                           'is_private' => YNBool($Row['is_private']),
                           'hash'    => NoNull($Row['hash']),

                           'created_at'   => apiDate($Row['created_unix'], 'Z'),
                           'created_unix' => apiDate($Row['created_unix'], 'U'),
                           'updated_at'   => apiDate($Row['updated_unix'], 'Z'),
                           'updated_unix' => apiDate($Row['updated_unix'], 'U'),
                          );
        }

        /* Return an array or an unhappy boolean */
        if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
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