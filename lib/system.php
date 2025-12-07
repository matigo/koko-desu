<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for System Operations
 */
require_once( LIB_DIR . '/functions.php');

class System {
    var $settings;
    var $strings;
    var $cache;

    private $routinesFile;
    private $triggersFile;
    private $columnsFile;

    function __construct( $settings, $strings = false ) {
        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $this->cache = array();

        /* Ensure the Configuration Directory exists ... which is should */
        if ( defined('CONF_DIR') === false ) { define('CONF_DIR', BASE_DIR . '/../conf'); }
        $this->routinesFile = CONF_DIR . '/db_routines.json';
        $this->triggersFile = CONF_DIR . '/db_triggers.json';
        $this->columnsFile = CONF_DIR . '/db_columns.json';
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

            case 'delete':
                return $this->_performDeleteAction();
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
            case 'build':
                return $this->_buildDBVersion();
                break;

            case 'dbcheck':
            case 'dbhash':
                return $this->_checkDBVersion();
                break;

            default:
                // Do Nothing
        }

        /* If We're Here, There's No Matching Activity */
        return false;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case '':
                return array( 'activity' => "[POST] /system/$Activity" );
                break;

            default:
                // Do Nothing
        }

        /* If We're Here, There's No Matching Activity */
        return false;
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));

        switch ( $Activity ) {
            case '':
                $rVal = array( 'activity' => "[DELETE] /system/$Activity" );
                break;

            default:
                // Do Nothing
        }

        /* If We're Here, There's No Matching Activity */
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

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    /**
     *  Function Builds the DB Schema Check Files that should be made part of
     *      the release after updates have been made to the database
     */
    private function _buildDBVersion() {
        if ( defined('DB_NAME') === false || NoNull(DB_NAME) == '' ) {
            $this->_setMetaMessage("No Database has been defined. Cannot continue.", 400);
            return false;
        }

        if ( in_array($this->settings['_account_type'], array('account.admin', 'account.global')) === false ) {
            $this->_setMetaMessage("You must be an administrator to use this API endpoint", 403);
            return false;
        }

        /* Build the Columns Definition File */
        $ReplStr = array( '[DB_NAME]' => sqlScrub(DB_NAME) );
        $sqlStr = readResource(SQL_DIR . '/system/getColumnSchema.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();
            foreach ( $rslt as $Row ) {
                $data[] = array( 'table_name'  => NoNull($Row['table_name']),
                                 'column_name' => NoNull($Row['column_name']),
                                 'default'     => NoNull($Row['default']),
                                 'is_nullable' => YNBool($Row['is_nullable']),
                                 'column_type' => NoNull($Row['column_type']),
                                 'charset'     => NoNull($Row['charset']),
                                 'collation'   => NoNull($Row['collation']),
                                 'extra'       => NoNull($Row['extra']),
                                );
            }

            /* If we have data, save it to the config directory */
            if ( is_array($data) && count($data) >= 1 ) {
                if ( checkDIRExists( CONF_DIR ) ) {
                    $fh = fopen($this->columnsFile, 'w');
                    fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fclose($fh);
                }
            }
        }

        /* Build the Triggers Definition File */
        $sqlStr = readResource(SQL_DIR . '/system/getTriggerSchema.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();
            foreach ( $rslt as $Row ) {
                $data[] = array( 'name'       => NoNull($Row['name']),
                                 'table_name' => NoNull($Row['table_name']),
                                 'event'      => NoNull($Row['event']),
                                 'timing'     => NoNull($Row['timing']),
                                 'charset'    => NoNull($Row['charset']),
                                 'collation'  => NoNull($Row['collation']),
                                 'sha512'     => NoNull($Row['sha512']),
                                );
            }

            /* If we have data, save it to the config directory */
            if ( is_array($data) && count($data) >= 1 ) {
                if ( checkDIRExists( CONF_DIR ) ) {
                    $fh = fopen($this->triggersFile, 'w');
                    fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fclose($fh);
                }
            }
        }

        /* Build the Routines Definition File */
        $sqlStr = readResource(SQL_DIR . '/system/getRoutineSchema.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();
            foreach ( $rslt as $Row ) {
                $data[] = array( 'name'   => NoNull($Row['name']),
                                 'type'   => NoNull($Row['type']),
                                 'sha512' => NoNull($Row['sha512']),
                                );
            }

            /* If we have data, save it to the config directory */
            if ( is_array($data) && count($data) >= 1 ) {
                if ( checkDIRExists( CONF_DIR ) ) {
                    $fh = fopen($this->routinesFile, 'w');
                    fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fclose($fh);
                }
            }
        }

        /* Now generate a quick confirmation value */
        $routineSha = hash_file ('sha512', $this->routinesFile, false);
        $triggerSha = hash_file ('sha512', $this->triggersFile, false);
        $columnsSha = hash_file ('sha512', $this->columnsFile, false);

        if ( is_string($routineSha) && is_string($columnsSha) ) {
            return array( 'db_version' => sha1($columnsSha . triggerSha . $routineSha),
                          'routines'   => $routineSha,
                          'triggers'   => $triggerSha,
                          'columns'    => $columnsSha
                         );

        } else {
            $this->_setMetaMessage("Error encountered when building DB Version details", 500);
        }

        /* If we're here, there was a failure */
        return false;
    }

    /**
     *  Function Collects the Server Hash and Reports Whether It's Valid or Not
     */
    private function _checkDBVersion() {
        if ( defined('DB_NAME') === false || NoNull(DB_NAME) == '' ) {
            $this->_setMetaMessage("No Database has been defined. Cannot continue.", 400);
            return false;
        }

        if ( in_array($this->settings['_account_type'], array('account.admin', 'account.global')) === false ) {
            $this->_setMetaMessage("You must be an administrator to use this API endpoint", 403);
            return false;
        }

        /* Verify the Requisite validation files exist */
        if ( file_exists($this->routinesFile) === false ) {
            $this->_setMetaMessage("There is no db_routines.json file to compare the database against.", 403);
            return false;
        }

        if ( file_exists($this->triggersFile) === false ) {
            $this->_setMetaMessage("There is no db_triggers.json file to compare the database against.", 403);
            return false;
        }

        if ( file_exists($this->columnsFile) === false ) {
            $this->_setMetaMessage("There is no db_columns.json file to compare the database against.", 403);
            return false;
        }

        /* Collect the Column Definitions from the CONF directory */
        $cols = json_decode(file_get_contents($this->columnsFile), true);

        /* Collect the Column Definitions from the DB and Compare */
        if ( is_array($cols) ) {
            $ReplStr = array( '[DB_NAME]' => sqlScrub(DB_NAME) );
            $sqlStr = readResource(SQL_DIR . '/system/getColumnSchema.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $key = NoNull($Row['table_name']) . '.' . NoNull($Row['column_name']);
                    foreach ( $cols as &$col ) {
                        $kk = NoNull($col['table_name']) . '.' . NoNull($col['column_name']);
                        if ( $key == $kk ) {
                            if ( NoNull($Row['default']) != NoNull($col['default']) ) {
                                if ( array_key_exists('reasons', $col) === false ) { $col['reasons'] = array(); }
                                $col['reasons'][] = 'Column default value does not match';
                            }
                            if ( NoNull($Row['column_type']) != NoNull($col['column_type']) ) {
                                if ( array_key_exists('reasons', $col) === false ) { $col['reasons'] = array(); }
                                $col['reasons'][] = 'Column type does not match';
                            }
                            if ( BoolYN($Row['is_nullable']) !== BoolYN($col['is_nullable']) ) {
                                if ( array_key_exists('reasons', $col) === false ) { $col['reasons'] = array(); }
                                $col['reasons'][] = 'Column nullability does not match';
                            }
                            if ( NoNull($Row['charset']) != NoNull($col['charset']) ) {
                                if ( array_key_exists('reasons', $col) === false ) { $col['reasons'] = array(); }
                                $col['reasons'][] = 'Column character set does not match';
                            }
                            if ( NoNull($Row['collation']) != NoNull($col['collation']) ) {
                                if ( array_key_exists('reasons', $col) === false ) { $col['reasons'] = array(); }
                                $col['reasons'][] = 'Column collation does not match';
                            }
                            if ( NoNull($Row['extra']) != NoNull($col['extra']) ) {
                                if ( array_key_exists('reasons', $col) === false ) { $col['reasons'] = array(); }
                                $col['reasons'][] = 'Column extra meta does not match';
                            }

                            /* State that the column has been checked */
                            $col['checked'] = true;
                            break;
                        }
                    }
                }
            }
        }

        /* Collect the Triggers Definitions from the CONF directory */
        $trigs = json_decode(file_get_contents($this->triggersFile), true);

        /* Collect the Triggers Definitions from the DB and Compare */
        if ( is_array($trigs) ) {
            $sqlStr = readResource(SQL_DIR . '/system/getTriggerSchema.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $key = NoNull($Row['table_name']) . '.' . NoNull($Row['name']);
                    foreach ( $trigs as &$trig ) {
                        $kk = NoNull($trig['table_name']) . '.' . NoNull($trig['name']);
                        if ( $key == $kk ) {
                            if ( NoNull($Row['event']) != NoNull($trig['event']) ) {
                                if ( array_key_exists('reasons', $trig) === false ) { $trig['reasons'] = array(); }
                                $trig['reasons'][] = 'Trigger event type does not match';
                            }
                            if ( NoNull($Row['timing']) != NoNull($trig['timing']) ) {
                                if ( array_key_exists('reasons', $trig) === false ) { $trig['reasons'] = array(); }
                                $trig['reasons'][] = 'Trigger timing does not match';
                            }
                            if ( NoNull($Row['charset']) != NoNull($trig['charset']) ) {
                                if ( array_key_exists('reasons', $trig) === false ) { $trig['reasons'] = array(); }
                                $trig['reasons'][] = 'Trigger character set does not match';
                            }
                            if ( NoNull($Row['collation']) != NoNull($trig['collation']) ) {
                                if ( array_key_exists('reasons', $trig) === false ) { $trig['reasons'] = array(); }
                                $trig['reasons'][] = 'Trigger collation does not match';
                            }
                            if ( NoNull($Row['sha512']) != NoNull($trig['sha512']) ) {
                                if ( array_key_exists('reasons', $trig) === false ) { $trig['reasons'] = array(); }
                                $trig['reasons'][] = 'Trigger SHA512 does not match';
                            }

                            /* State that the column has been checked */
                            $trig['checked'] = true;
                            break;
                        }
                    }
                }
            }
        }

        /* Collect the Routines Definitions from the CONF directory */
        $procs = json_decode(file_get_contents($this->routinesFile), true);

        /* Collect the Routines Definitions from the DB and Compare */
        if ( is_array($procs) ) {
            $sqlStr = readResource(SQL_DIR . '/system/getRoutineSchema.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $key = NoNull($Row['name']);
                    foreach ( $procs as &$proc ) {
                        $kk = NoNull($proc['name']);
                        if ( $key == $kk ) {
                            if ( NoNull($Row['type']) != NoNull($proc['type']) ) {
                                if ( array_key_exists('reasons', $proc) === false ) { $proc['reasons'] = array(); }
                                $proc['reasons'][] = 'Routine type does not match';
                            }
                            if ( NoNull($Row['sha512']) != NoNull($proc['sha512']) ) {
                                if ( array_key_exists('reasons', $proc) === false ) { $proc['reasons'] = array(); }
                                $proc['reasons'][] = 'Routine SHA512 does not match';
                            }

                            /* State that the column has been checked */
                            $proc['checked'] = true;
                            break;
                        }
                    }
                }
            }
        }

        /* Collect the Routines Definitions from the CONF directory */
        $procs = json_decode(file_get_contents($this->routinesFile), true);

        /* Collect the Routines Definitions from the DB and Compare */
        if ( is_array($procs) ) {
            $sqlStr = readResource(SQL_DIR . '/system/getRoutineSchema.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $key = NoNull($Row['name']);
                    foreach ( $procs as &$proc ) {
                        $kk = NoNull($proc['name']);
                        if ( $key == $kk ) {
                            if ( NoNull($Row['type']) != NoNull($proc['type']) ) {
                                if ( array_key_exists('reasons', $proc) === false ) { $proc['reasons'] = array(); }
                                $proc['reasons'][] = 'Routine type does not match';
                            }
                            if ( NoNull($Row['sha512']) != NoNull($proc['sha512']) ) {
                                if ( array_key_exists('reasons', $proc) === false ) { $proc['reasons'] = array(); }
                                $proc['reasons'][] = 'Routine SHA512 does not match';
                            }

                            /* State that the column has been checked */
                            $proc['checked'] = true;
                            break;
                        }
                    }
                }
            }
        }

        /* Return the Result */
        $tables = array();
        $data = array( 'counts' => array( 'tables'   => 0,
                                          'columns'  => count($cols),
                                          'routines' => count($procs),
                                          'triggers' => count($trigs),
                                         ),
                       'issues' => array()
                      );

        foreach ( $cols as $col ) {
            $table_name = NoNull($col['table_name']);
            if ( in_array($table_name, $tables) === false ) { $tables[] = $table_name; }

            if ( array_key_exists('checked', $col) === false ) {
                $data['issues'][] = 'Column `' . $table_name . '`.`' . NoNull($col['column_name']) . '` [' . NoNull($col['column_type']) . '] not found.';
            }
            if ( array_key_exists('reasons', $col) ) {
                foreach ( $col['reasons'] as $reason ) {
                    $data['issues'][] = 'Column `' . $table_name . '`.`' . NoNull($col['column_name']) . '` -> ' . NoNull($reason);
                }
            }
        }

        foreach ( $trigs as $trig ) {
            if ( array_key_exists('checked', $trig) === false ) {
                $data['issues'][] = 'Trigger [' . NoNull($trig['name']) . '] not found.';
            }
            if ( array_key_exists('reasons', $trig) ) {
                foreach ( $trig['reasons'] as $reason ) {
                    $data['issues'][] = 'Trigger [' . NoNull($trig['name']) . '] -> ' . NoNull($reason);
                }
            }
        }

        foreach ( $procs as $proc ) {
            if ( array_key_exists('checked', $proc) === false ) {
                $data['issues'][] = ucfirst(NoNull($proc['type'], 'procedure')) . ' [' . NoNull($proc['name']) . '] not found.';
            }
            if ( array_key_exists('reasons', $proc) ) {
                foreach ( $proc['reasons'] as $reason ) {
                    $data['issues'][] = ucfirst(NoNull($proc['type'], 'procedure')) . ' [' . NoNull($proc['name']) . '] -> ' . NoNull($reason);
                }
            }
        }

        /* Add the Tables count */
        $data['counts']['tables'] = count($tables);

        /* Return an array of results */
        return $data;
    }

    /**
     *  Function Checks If There Are Updates to Perform
     */
    private function _checkForUpdates() {

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