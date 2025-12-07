<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for Accounts
 */
require_once( LIB_DIR . '/functions.php');

class Account {
    var $settings;
    var $strings;
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
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        $ReqType = NoNull(strtolower($this->settings['ReqType']));

        /* Do not allow unauthenticated requests */
        if ( in_array($Activity, array('forgot')) === false && !$this->settings['_logged_in'] ) {
            return $this->_setMetaMessage("You need to sign in to use this API endpoint", 403);
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

        /* If we're here, then there's nothing to return */
        return false;
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'profile'; }

        switch ( $Activity ) {
            case 'profile':
            case 'me':
                return $this->_getProfile();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, then there's nothing to return */
        return false;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'set'; }

        switch ( $Activity ) {
            case 'create':
                return $this->_createAccount();
                break;

            case 'forgot':
                return $this->_forgotPassword();
                break;

            case 'preference':
            case 'welcome':
                return $this->_setMetaRecord();
                break;

            case 'set':
            case 'me':
            case '':
                return $this->_setAccountData();
                break;

            default:
                // Do Nothing
        }

        /* If we're here, then there's nothing to return */
        return $rVal;
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'unset'; }

        switch ( $Activity ) {
            case '':
                /* Do Nothing */
                break;

            default:
                // Do Nothing
        }

        /* If we're here, then there's nothing to return */
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
     *  Public Functions
     ** ********************************************************************* */
    public function createFromStripe($data) { return $this->_createFromStripe($data); }
    public function getAccountDetails( $AccountId = 0 ) { return $this->_getAccountById( $AccountId ); }

    /** ********************************************************************* *
     *  Cleaning Functions
     ** ********************************************************************* */
    /**
     *  Function does all the necessary cleaning of input values and returns a consistent, clean array
     */
    private function _getInputValues() {
        $validGenders = array('M', 'F', '-');
        $CleanGender = strtoupper(NoNull($this->settings['gender'], $this->settings['sex']));
        $CleanNick = strtolower(NoNull($this->settings['login'], NoNull($this->settings['nickname'], $this->settings['nick'])));
        $CleanGuid = '';

        /* If we are using a "me", ensure the Account.guid is properly identified */
        if ( NoNull($this->settings['PgSub2'], $this->settings['PgSub1']) == 'me' ) {
            $CleanGuid = NoNull($this->settings['_account_guid']);
        }

        /* Return an Array of possible values */
        return array( 'acct_guid'  => NoNull($CleanGuid, NoNull($this->settings['account_guid'], $this->settings['guid'])),
                      'acct_type'  => NoNull($this->settings['account_type'], $this->settings['type']),

                      'nickname'   => preg_replace("/[^a-zA-Z0-9]+/", '', $CleanNick),
                      'password'   => NoNull($this->settings['password'], NoNull($this->settings['account_password'], $this->settings['account_pass'])),
                      'mail'       => NoNull($this->settings['email'], NoNull($this->settings['mail'], $this->settings['mail_addr'])),
                      'avatar'     => NoNull($this->settings['avatar_file'], $this->settings['avatar']),

                      'display_as' => NoNull($this->settings['display_name'], NoNull($this->settings['display_as'], $this->settings['displayas'])),
                      'last_name'  => NoNull($this->settings['last_name'], NoNull($this->settings['last_ro'], $this->settings['lastname'])),
                      'first_name' => NoNull($this->settings['first_name'], NoNull($this->settings['first_ro'], $this->settings['firstname'])),
                      'gender'     => ((in_array($CleanGender, $validGenders)) ? $CleanGender : 'M'),

                      'locale'     => validateLanguage(NoNull($this->settings['locale_code'], $this->settings['locale'])),
                      'timezone'   => NoNull($this->settings['time_zone'], $this->settings['timezone']),
                     );
    }

    /** ********************************************************************* *
     *  Account Management
     ** ********************************************************************* */
    /**
     *  Function creates an account and returns an Object or an unhappy boolean
     */
    private function _createAccount( $idOnly = false ) {
        if ( !defined('DEFAULT_LANG') ) { define('DEFAULT_LANG', 'en_US'); }
        if ( !defined('TIMEZONE') ) { define('TIMEZONE', 'UTC'); }
        if ( !defined('SHA_SALT') ) { return $this->_setMetaMessage("This system has not been configured. Cannot proceed.", 400); }
        $inputs = $this->_getInputValues();

        /* Now let's do some basic validation */
        if ( mb_strlen(NoNull($inputs['password'])) <= 6 ) { return $this->_setMetaMessage( "Password is too weak. Please choose a better one.", 400 ); }
        if ( mb_strlen(NoNull($inputs['mail'])) <= 5 ) { return $this->_setMetaMessage( "Email address is too short. Please enter a correct address.", 400 ); }
        if ( validateEmail(NoNull($inputs['mail'])) === false ) { return $this->_setMetaMessage( "Email address does not appear correct. Please enter a correct address.", 400 ); }

        /* Ensure some sensible defaults exist */
        if ( mb_strlen(NoNull($inputs['timezone'])) < 5 ) { $inputs['timezone'] = TIMEZONE; }
        if ( mb_strlen(NoNull($inputs['locale'])) < 5 ) { $inputs['locale'] = DEFAULT_LANG; }

        /* If we're here, we *should* be good. Create the account. */
        $ReplStr = array( '[NICKNAME]'   => sqlScrub($inputs['nickname']),
                          '[EMAIL]'      => sqlScrub($inputs['mail']),
                          '[DISPLAY_AS]' => sqlScrub(NoNull($inputs['display_as'], $inputs['first_name'])),
                          '[FIRST_NAME]' => sqlScrub($inputs['first_name']),
                          '[LAST_NAME]'  => sqlScrub($inputs['last_name']),

                          '[PASSWORD]'   => sqlScrub($inputs['password']),
                          '[SHA_SALT]'   => sqlScrub(SHA_SALT),

                          '[GENDER]'     => sqlScrub($inputs['gender']),
                          '[LOCALE]'     => sqlScrub($inputs['locale']),
                          '[TIMEZONE]'   => sqlScrub($inputs['timezone']),
                          '[TYPE]'       => sqlScrub($inputs['acct_type'], 'account.basic'),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/setAccountCreate.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                if ( mb_strlen(NoNull($Row['account_guid'])) == 36 ) {
                    if ( YNBool($idOnly) ) {
                        return nullInt($Row['account_id']);
                    } else {
                        $data = $this->_getAccountById($Row['account_id']);
                        if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
                    }
                }
            }
        }

        /* If we're here, we could not create an account for some reason */
        return $this->_setMetaMessage("Could not create account", 400);
    }

    /**
     *  Function creates (or updates) an account based on the data provided by a Stripe Webhook package
     */
    private function _createFromStripe( $data = array() ) {
        if ( is_array($data) === false || mb_strlen(NoNull($data['id'])) < 10 ) { return false; }
        if ( array_key_exists('id', $data) === false || mb_strlen(NoNull($data['id'])) < 10 ) { return false; }
        if ( strtolower(NoNull($data['object'])) != 'customer' ) { return false; }

        /* Set the variables */
        $this->settings['display_name'] = NoNull($data['name']);
        $this->settings['account_type'] = 'account.basic';
        $this->settings['password'] = getRandomString(30);
        $this->settings['mail'] = NoNull($data['email']);

        $locale = '';
        if ( array_key_exists('preferred_locales', $data) && is_array($data['preferred_locales']) ) {
            foreach ( $data['preferred_locales'] as $opt ) {
                if ( mb_strlen($locale) < 2 ) { $locale = NoNull($opt); }
            }
        }
        $this->settings['locale_code'] = NoNull($locale, DEFAULT_LANG);

        $id = $this->_createAccount(true);
        if ( nullInt($id) > 0 ) {
            /* Set lots of AccountMeta */
            if ( mb_strlen(NoNull($data['id'])) > 10 ) { $sOK = $this->_setMetaRecord('stripe', 'id', $data['id']); }
            if ( mb_strlen(NoNull($data['name'])) > 10 ) { $sOK = $this->_setMetaRecord('stripe', 'name', $data['name']); }
            if ( mb_strlen(NoNull($data['phone'])) > 10 ) { $sOK = $this->_setMetaRecord('contact', 'phone', $data['phone']); }
            if ( mb_strlen(NoNull($data['email'])) > 10 ) { $sOK = $this->_setMetaRecord('contact', 'email', $data['email']); }

            if ( array_key_exists('address', $data) && is_array($data['address']) ) {
                if ( mb_strlen(NoNull($data['address']['postal_code'])) > 0 ) { $sOK = $this->_setMetaRecord('address', 'postal_code', $data['address']['postal_code']); }
                if ( mb_strlen(NoNull($data['address']['country'])) > 0 ) { $sOK = $this->_setMetaRecord('address', 'country', $data['address']['country']); }
                if ( mb_strlen(NoNull($data['address']['state'])) > 0 ) { $sOK = $this->_setMetaRecord('address', 'state', $data['address']['state']); }
                if ( mb_strlen(NoNull($data['address']['line1'])) > 0 ) { $sOK = $this->_setMetaRecord('address', 'line1', $data['address']['line1']); }
                if ( mb_strlen(NoNull($data['address']['line2'])) > 0 ) { $sOK = $this->_setMetaRecord('address', 'line2', $data['address']['line2']); }
                if ( mb_strlen(NoNull($data['address']['city'])) > 0 ) { $sOK = $this->_setMetaRecord('address', 'city', $data['address']['city']); }
            }

            /* Return a happy string */
            return 'OK';
        }

        /* If we're here, something is really not right */
        return $this->_setMetaMessage("Supplied Account information is incomplete", 400);
    }

    /**
     *  Function Updates the Account fields available to an account-holder
     */
    private function _setAccountData() {
        if ( !defined('DEFAULT_LANG') ) { define('DEFAULT_LANG', 'en_US'); }
        if ( !defined('TIMEZONE') ) { define('TIMEZONE', 'UTC'); }
        if ( !defined('SHA_SALT') ) { return $this->_setMetaMessage("This system has not been configured. Cannot proceed.", 400); }
        $inputs = $this->_getInputValues();

        /* Now let's do some basic validation */
        if ( mb_strlen(NoNull($inputs['password'])) > 0 && mb_strlen(NoNull($inputs['password'])) <= 6 ) {
            return $this->_setMetaMessage( "Password is too weak. Please choose a better one.", 400 );
        }

        if ( mb_strlen(NoNull($inputs['mail'])) <= 5 ) {
            return $this->_setMetaMessage( "Email address is too short. Please enter a correct address.", 400 );
        }

        if ( validateEmail(NoNull($inputs['mail'])) === false ) {
            return $this->_setMetaMessage( "Email address does not appear correct. Please enter a correct address.", 400 );
        }

        /* Ensure some sensible defaults exist */
        if ( mb_strlen(NoNull($inputs['timezone'])) < 5 ) { $inputs['timezone'] = TIMEZONE; }
        if ( mb_strlen(NoNull($inputs['locale'])) < 5 ) { $inputs['locale'] = DEFAULT_LANG; }

        /* If we're here, we *should* be good. Create the account. */
        $ReplStr = array( '[ACCOUNT_ID]'   => nullInt($this->settings['_account_id']),
                          '[ACCOUNT_GUID]' => sqlScrub($this->settings['_account_guid']),

                          '[EMAIL]'      => sqlScrub($inputs['mail']),
                          '[DISPLAY_AS]' => sqlScrub(NoNull($inputs['display_as'], $inputs['first_name'])),
                          '[FIRST_NAME]' => sqlScrub($inputs['first_name']),
                          '[LAST_NAME]'  => sqlScrub($inputs['last_name']),

                          '[GENDER]'     => sqlScrub($inputs['gender']),
                          '[LOCALE]'     => sqlScrub($inputs['locale']),
                          '[TIMEZONE]'   => sqlScrub($inputs['timezone']),
                          '[TYPE]'       => sqlScrub($inputs['account_type']),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/setAccountData.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                if ( mb_strlen(NoNull($Row['account_guid'])) == 36 ) {
                    $data = $this->_getAccountById($Row['account_id']);
                    if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
                }
            }
        }

        /* If we're here, then we could not update the database */
        return $this->_setMetaMessage("Could not update the account record.", 400);
    }

    /**
     *  This function is similar to getProfile, but uses an Account.id and will return *generic* account data that can be cached for faster access
     */
    private function _getAccountById( $id ) {
        if ( !defined('DEFAULT_LANG') ) { define('DEFAULT_LANG', 'en_US'); }
        if ( !defined('TIMEZONE') ) { define('TIMEZONE', 'UTC'); }
        if ( $id <= 0 ) { return false; }

        /* Collect the Account information */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[LOOKUP_ID]'  => nullInt($id),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/getAccountById.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                $meta = false;
                if ( YNBool($Row['has_meta']) ) {
                    $meta = $this->_getAccountMeta($Row['account_id']);
                }

                return array( 'guid'    => NoNull($Row['account_guid']),
                              'type'    => NoNull($Row['account_type']),

                              'email'   => ((nullInt($Row['account_id']) == nullInt($this->settings['_account_id'])) ? NoNull($Row['email']) : '********************' ),

                              'display_name' => NoNull($Row['display_name']),
                              'family_name'  => NoNull($Row['last_name']),
                              'first_name'   => NoNull($Row['first_name']),
                              'gender'       => NoNull($Row['gender']),

                              'locale'       => NoNull($Row['locale_code']),
                              'timezone'     => NoNull($Row['timezone']),
                              'is_admin'     => YNBool($Row['is_admin']),

                              'created_at'   => apiDate($Row['created_unix'], 'Z'),
                              'created_unix' => apiDate($Row['created_unix'], 'U'),
                              'updated_at'   => apiDate($Row['updated_unix'], 'Z'),
                              'updated_unix' => apiDate($Row['updated_unix'], 'U'),
                             );
            }
        }

        /* If we are here, then no account information could be found */
        return $this->_setMetaMessage("Could not find requested account", 404);
    }

    /**
     *  Function returns a Meta object for an Account or an unhappy boolean
     */
    private function _getAccountMeta( $id ) {
        if ( nullInt($id) <= 0 ) { return false; }

        /* Collect the meta data */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($id) );
        $sqlStr = readResource(SQL_DIR . '/account/getAccountMeta.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = buildMetaArray($rslt);

            /* So long as the data appears valid, let's return it */
            if ( is_array($data) && count($data) > 0 ) { return $data; }
        }

        /* If we are here, there may not be any *visible* metadata for this Account */
        return false;
    }

    /** ********************************************************************* *
     *  Password Management Functions
     ** ********************************************************************* */
    /**
     *  Function checks an email address is valid and sends an email to that address
     *      containing some links that allow them to sign into the system.
     */
    private function _forgotPassword() {
        $CleanMail = strtolower(NoNull($this->settings['email'], $this->settings['mail_addr']));

        if ( validateEmail($CleanMail) ) {
            $ReplStr = array( '[MAIL_ADDR]' => sqlScrub($CleanMail) );
            $sqlStr = readResource(SQL_DIR . '/account/chkPasswordReset.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                if ( count($rslt) <= 0 ) { $this->_setMetaMessage("Could not find supplied email address.", 404); }

                foreach ( $rslt as $Row ) {
                    $sOK = false;

                    /* If we are permitted to email, let's do so */
                    if ( YNBool($Row['can_email']) ) {
                        if ( defined('DEFAULT_LANG') === false ) { define('DEFAULT_LANG', 'en-us'); }
                        $locale = strtolower(str_replace('_', '-', NoNull($Row['locale_code'], DEFAULT_LANG)));
                        $template = 'email.forgot_' . NoNull($locale, 'en-us');

                        /* Obtain a properly-structured authentication token */
                        require_once(LIB_DIR . '/auth.php');
                        $auth = new Auth($this->settings);
                        $Token = $auth->buildAuthToken($Row['token_id'], $Row['token_guid']);
                        unset($auth);

                        /* Do not allow a bad authentication token to be returned */
                        if ( mb_strlen($Token) <= 36 ) {
                            return $this->_setMetaMessage("Invalid Authentication Token generated.", 500);
                        }

                        $ReplStr = array( '[FIRST_NAME]' => NoNull($Row['display_name'], $Row['first_name']),
                                          '[AUTH_TOKEN]' => $Token,
                                          '[APP_NAME]'   => NoNull($this->strings['friendly_name'], APP_NAME),
                                          '[HOMEURL]'    => NoNull($this->settings['HomeURL']),
                                         );

                        /* Construct the Message array */
                        $msg = array( 'from_addr' => NoNull(MAIL_ADDRESS),
                                      'from_name' => NoNull($this->strings['friendly_name'], APP_NAME),
                                      'send_from' => NoNull(MAIL_ADDRESS),
                                      'send_to' => NoNull($Row['email']),
                                      'html'    => '',
                                      'text'    => '',
                                     );

                        /* Read the HTML template (if exists) */
                        if ( file_exists(FLATS_DIR . '/templates/' . $template . '.html') ) {
                            $msg['html'] = readResource(FLATS_DIR . '/templates/' . $template . '.html', $ReplStr);
                        } else {
                            $msg['html'] = readResource(FLATS_DIR . '/templates/email.forgot_en-us.html', $ReplStr);
                        }

                        /* Read the TXT template (if exists) */
                        if ( file_exists(FLATS_DIR . '/templates/' . $template . '.txt') ) {
                            $msg['text'] = readResource(FLATS_DIR . '/templates/' . $template . '.txt', $ReplStr);
                        } else {
                            $msg['text'] = readResource(FLATS_DIR . '/templates/email.forgot_en-us.txt', $ReplStr);
                        }

                        /* Send the Message */
                        require_once(LIB_DIR . '/email.php');
                        $mail = new Email($this->settings);
                        $sOK = $mail->sendMail($msg);
                        unset($mail);

                        /* If there's an error, report it */
                        if ( $sOK === false ) { $this->_setMetaMessage("Could not send email", 400); }
                    }

                    /* Return an array */
                    return array( 'is_valid' => ((nullInt($Row['id']) > 0) ? true : false) );
                }
            } else {
                $this->_setMetaMessage("Could not find supplied email address.", 404);
            }

        } else {
            $this->_setMetaMessage("Invalid Email Address provided", 400);
        }

        /* Return an Empty Array, Regardless of whether the data is good or not (to prevent email cycling) */
        return array( 'is_valid' => false );
    }

    /** ********************************************************************* *
     *  Preferences
     ** ********************************************************************* */
    /**
     *  Function Sets a Person's Preference and Returns a list of preferences
     */
    private function _setMetaRecord($prefix = '', $key = '', $value = '') {
        $MetaPrefix = NoNull($prefix, NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        $CleanValue = NoNull($value, $this->settings['value']);
        $CleanKey = NoNull($key, $this->settings['key']);

        if ( mb_strlen($MetaPrefix) > 0 && strpos($CleanKey, $MetaPrefix) === false ) { $CleanKey = $MetaPrefix . '.' . $CleanKey; }

        /* Ensure the Key is long enough */
        if ( strlen($CleanKey) < 3 ) { return $this->_setMetaMessage("Invalid Meta Key Passed [$CleanKey]", 400); }

        /* Ensure the Key follows protocol */
        if ( substr_count($CleanKey, '.') < 1 ) { return $this->_setMetaMessage("Meta Key is in the wrong format [$CleanKey]", 400); }

        /* Prep the SQL Statement */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[VALUE]'      => sqlScrub($CleanValue),
                          '[KEY]'        => sqlScrub($CleanKey),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/setAccountMeta.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                return array( 'key'          => NoNull($Row['key']),
                              'value'        => NoNull($Row['value']),

                              'created_at'   => apiDate($Row['created_at'], 'Z'),
                              'created_unix' => apiDate($Row['created_at'], 'U'),
                              'updated_at'   => apiDate($Row['updated_at'], 'Z'),
                              'updated_unix' => apiDate($Row['updated_at'], 'U'),
                             );
            }
        }

        /* If we're here, something failed */
        return $this->_setMetaMessage("Could not save Account Meta record", 400);
    }

    /** ********************************************************************* *
     *  Password Management Functions
     ** ********************************************************************* */
    /**
     *  Function Updates an Account's password
     */
    private function _setPassword() {
        if ( !defined('SHA_SALT') ) { return $this->_setMetaMessage("This system has not been configured. Cannot proceed.", 400); }
        $inputs = $this->_getInputValues();

        /* Now let's do some basic validation */
        if ( mb_strlen(NoNull($inputs['password'])) <= 6 ) { return $this->_setMetaMessage( "Password is too weak. Please choose a better one.", 400 ); }

        /* If we're here, we *should* be good. Create the account. */
        $ReplStr = array( '[ACCOUNT_ID]'   => nullInt($this->settings['_account_id']),
                          '[ACCOUNT_GUID]' => sqlScrub($this->settings['_account_guid']),
                          '[PASSWORD]'     => sqlScrub($inputs['password']),
                          '[SHA_SALT]'     => sqlScrub(SHA_SALT),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/setAccountPassword.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                return array( 'guid'         => NoNull($Row['account_guid']),
                              'updated_at'   => apiDate($Row['updated_unix'], 'Z'),
                              'updated_unix' => apiDate($Row['updated_unix'], 'U'),
                             );
            }
        }

        /* If we're here, we couldn't update the password */
        return $this->_setMetaMessage("Could not update account password", 400);
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