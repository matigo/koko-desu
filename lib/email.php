<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for Email Functions
 */
require_once(LIB_DIR . '/functions.php');
require_once(LIB_DIR . '/phpmailer/Exception.php');
require_once(LIB_DIR . '/phpmailer/PHPMailer.php');
require_once(LIB_DIR . '/phpmailer/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Email {
    var $settings;
    var $strings;
    var $errors;

    function __construct( $Items ) {
        $this->settings = $Items;
        $this->strings = getLangDefaults($this->settings['_language_code']);
        $this->errors = array();
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = NoNull(strtolower($this->settings['ReqType']));

        /* Do not allow unauthenticated requests */
        if ( !$this->settings['_logged_in'] ) {
            return $this->_setMetaMessage("You need to sign in to use this API endpoint", 403);
        }

        if ( in_array($this->settings['_account_type'], array('account.admin', 'account.global')) === false ) {
            return $this->_setMetaMessage("You must be an administrator to use this API endpoint", 403);
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

            default:
                // Do Nothing
        }

        /* If we're here, there is no matching request type ... which would be weird */
        return false;
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case '':
                return array( 'activity' => "[GET] /email/$Activity" );
                break;

            default:
                // Do Nothing
        }

        /* If we're here, nothing was done */
        return false;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case 'send':
            case 'set':
            case '':
                return array( 'activity' => "[POST] /email/$Activity" );
                break;

            default:
                // Do Nothing
        }

        /* If we're here, nothing was done */
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

    /***********************************************************************
     *  Public Functions
     ***********************************************************************/
    public function sendMail( $data ) { return $this->_sendEmail($data); }

    /***********************************************************************
     *  Private Functions
     ***********************************************************************/
    /**
     * Function sends an email Using the information in $data and a template file and returns a Boolean response
     */
    private function _sendEmail( $data ) {
        if ( defined('MAIL_ADDRESS') === false ) { define('MAIL_ADDRESS', ''); }
        if ( defined('MAIL_SMTPAUTH') === false ) { define('MAIL_SMTPAUTH', 0); }
        if ( defined('MAIL_SMTPSECURE') === false ) { define('MAIL_SMTPSECURE', ''); }
        if ( defined('MAIL_MAILHOST') === false ) { define('MAIL_MAILHOST', ''); }
        if ( defined('MAIL_MAILPORT') === false ) { define('MAIL_MAILPORT', ''); }
        if ( defined('MAIL_USERNAME') === false ) { define('MAIL_USERNAME', ''); }
        if ( defined('MAIL_USERPASS') === false ) { define('MAIL_USERPASS', ''); }
        if ( defined('APP_NAME') === false ) { define('APP_NAME', ''); }

        /* Confirm that the basics exist, otherwise return an unhappy boolean */
        if ( mb_strlen(NoNull(MAIL_MAILHOST)) <= 0 || mb_strlen(NoNull(MAIL_MAILHOST)) <= 0 ||
             mb_strlen(NoNull(MAIL_MAILHOST)) <= 0 || mb_strlen(NoNull(MAIL_MAILHOST)) <= 0 ) {
            writeNote("Incomplete Email Configuration. Cannot continue.", true);
            $this->_setMetaMessage("Incomplete email configuration. Cannot continue.", 400);
            return false;
        }

        /* Clean up the strings and get an email address length counter */
        $mailAddr = NoNull($data['send_to']) . NoNull($data['send_cc']) . NoNull($data['send_bcc']);
        $mailHTML = NoNull($data['html']);
        $mailText = NoNull($data['text']);

        /* If we have what looks like a valid set of data, let's try to send it */
        if ( mb_strlen(NoNull($mailAddr)) > 5 && mb_strlen(NoNull($mailHTML, $mailText)) > 0 ) {
            $SendFrom = NoNull($data['send_from'], MAIL_ADDRESS);
            $SendName = NoNull($data['from_name'], APP_NAME);
            $ReplyTo = NoNull($data['from_addr'], MAIL_ADDRESS);

            /* Load the Mail library */
            $mail = new PHPMailer();
            $mail->IsSMTP();

            $mail->SMTPAuth   = YNBool(MAIL_SMTPAUTH);
            $mail->SMTPSecure = MAIL_SMTPSECURE;
            $mail->Host       = MAIL_MAILHOST;
            $mail->Port       = MAIL_MAILPORT;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_USERPASS;

            $mail->CharSet = 'UTF-8';
            $mail->SetFrom($SendFrom, $SendName);
            $mail->Subject = NoNull($data['subject']);
            $mail->AltBody = $mailText;
            $mail->Body = NoNull($mailHTML, $mailText);
            if ( mb_strlen(NoNull($mailHTML)) > 0 ) {
                $mail->isHTML(true);
            }

            /* Set the Recipient List with Unique Addresses (Going BCC->CC->TO for the sake of ensuring privacy) */
            $recipients = array();
            if ( mb_strlen(NoNull($data['send_bcc'])) > 0 ) {
                $addrs = explode(',', NoNull($data['send_bcc']) . ',');
                if ( is_array($addrs) ) {
                    foreach ( $addrs as $addr ) {
                        $addr = strtolower(NoNull($addr));
                        if ( validateEmail($addr) && in_array($addr, $recipients) === false ) {
                            $mail->AddBCC( $addr );
                            $recipients[] = $addr;
                        }
                    }
                }
            }

            if ( mb_strlen(NoNull($data['send_cc'])) > 0 ) {
                $addrs = explode(',', NoNull($data['send_cc']) . ',');
                if ( is_array($addrs) ) {
                    foreach ( $addrs as $addr ) {
                        $addr = strtolower(NoNull($addr));
                        if ( validateEmail($addr) && in_array($addr, $recipients) === false ) {
                            $mail->AddCC( $addr );
                            $recipients[] = $addr;
                        }
                    }
                }
            }

            if ( mb_strlen(NoNull($data['send_to'])) > 0 ) {
                $addrs = explode(',', NoNull($data['send_to']) . ',');
                if ( is_array($addrs) ) {
                    foreach ( $addrs as $addr ) {
                        $addr = strtolower(NoNull($addr));
                        if ( validateEmail($addr) && in_array($addr, $recipients) === false ) {
                            $mail->AddAddress( $addr );
                            $recipients[] = $addr;
                        }
                    }
                }
            }

            /* Send the Message! */
            $sOK = $mail->send();
            if ( $sOK === false ) {
                $this->_setMetaMessage("Could not send email -> " . NoNull($data['subject']) . " [" . count($recipients) . " Recipients]", 400);
                writeNote("Could not send email -> " . NoNull($data['subject']) . " [" . count($recipients) . " Recipients]", true );
            }
            unset($mail);

            /* If the send was successful, return a happy boolean */
            if ( $sOK ) { return true; }

        } else {
            /* Build the full recipient list */
            $SendTo = 'TO: ' . NoNull($data['send_to']);
            if ( NoNull($data['send_cc']) != '' ) { $SendTo .= ((mb_strlen($SendTo) > 0) ? '| ' : '') . 'CC: ' . NoNull($data['send_cc']); }
            if ( NoNull($data['send_bcc']) != '' ) { $SendTo .= ((mb_strlen($SendTo) > 0) ? '| ' : '') . 'BCC: ' . NoNull($data['send_bcc']); }

            /* Write the error to the log */
            writeNote("Mail Elements Incomplete!", true);
            writeNote("Send To: $SendTo", true);
            writeNote("HTML: $mailHTML", true);
            writeNote("Text: $mailText", true);
        }

        /* If We're Here, Something Was Not Quite Right */
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