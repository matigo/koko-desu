<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for Note handling
 */
require_once( LIB_DIR . '/functions.php');
require_once(LIB_DIR . '/markdown/MarkdownExtra.inc.php');
use Michelf\MarkdownExtra;

class Note {
    var $settings;
    var $strings;
    var $parser;
    var $cache;

    function __construct( $settings, $strings = false ) {
        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $this->parser = false;
        $this->cache = array();
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = strtolower(NoNull($this->settings['ReqType'], 'GET'));

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
        $ReqType = strtoupper($ReqType);
        $this->_setMetaMessage("Unrecognized Request Type: $ReqType", 404);
        return false;
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'read'; }

        switch ( $Activity ) {
            case 'read':
                return $this->_getNoteByGuid();
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        $this->_setMetaMessage("Nothing to do: [GET] $Activity", 404);
        return false;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'set'; }

        switch ( $Activity ) {

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        $this->_setMetaMessage("Nothing to do: [POST] $Activity", 404);
        return false;
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'scrub'; }

        switch ( $Activity ) {
            case '':
                $rVal = array( 'activity' => "[DELETE] /session/$Activity" );
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        $this->_setMetaMessage("Nothing to do: [DELETE] $Activity", 404);
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
    public function getNoteDetails( $note_id, $version = 0 ) { return $this->_getNoteById($note_id, $version); }

    /** ********************************************************************* *
     *  Reading Functions
     ** ********************************************************************* */
    /**
     *  Function returns a Note based on the Guid provided
     */
    private function _getNoteByGuid() {
        $CleanGuid = NoNull($this->settings['note_guid'], NoNull($this->settings['note'], $this->settings['guid']));

        /* Perform some basic validation */
        if ( mb_strlen($CleanGuid) > 0 && mb_strlen($CleanGuid) != 36 ) {
            $this->_setMetaMessage("Invalid Note.guid Provided", 400);
            return false;
        }

        /* Collect the Library */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[NOTE_GUID]'  => sqlScrub($CleanGuid),
                         );
        $sqlStr = readResource(SQL_DIR . '/note/getNoteByGuid.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();

            foreach ( $rslt as $Row ) {

                /* Only show the Session if it meets the visibility criteria */
                if ( YNBool($Row['is_visible']) ) {

                    $meta = false;
                    if ( YNBool($Row['has_meta']) ) {
                        $meta = $this->_getNoteMetaData($Row['note_id'], $Row['version']);
                    }

                    $note = false;
                    if ( nullInt($Row['note_id']) > 0 ) {
                        /* If the Markdown Parser hasn't been loaded yet, do so */
                        if ( $this->parser === false ) { $this->parser = new MarkdownExtra; }

                        $note = array( 'guid'   => NoNull($Row['note_guid']),
                                       'type'   => NoNull($Row['note_type']),
                                       'content' => array( 'text' => $this->_getPlainText($Row['note_text']),
                                                           'html' => $this->parser->defaultTransform($Row['note_text']),
                                                          ),
                                       'hash'   => NoNull($Row['note_hash']),
                                       'created_at'   => apiDate($Row['note_createdat'], 'Z'),
                                       'created_unix' => apiDate($Row['note_createdat'], 'U'),
                                       'updated_at'   => apiDate($Row['note_updatedat'], 'Z'),
                                       'updated_unix' => apiDate($Row['note_updatedat'], 'U'),
                                       'updated_by'   => $acct->getAccountDetails($Row['note_updatedby'], $Row['note_updatedby_version']),
                                      );
                    }

                    /* Assemble the Material record */
                    $record = array( 'guid'     => NoNull($Row['session_guid']),
                                     'type'     => NoNull($Row['type']),
                                     'week'     => nullInt($Row['session_week']),

                                     'start_at'     => apiDate($Row['start_at'], 'Z'),
                                     'start_unix'   => apiDate($Row['start_at'], 'U'),
                                     'until_at'     => apiDate($Row['until_at'], 'Z'),
                                     'until_unix'   => apiDate($Row['until_at'], 'U'),

                                     'goal'     => NoNull($Row['goal']),
                                     'legacy_id'    => NoNull($Row['legacy_id']),

                                     'is_locked'    => YNBool($Row['is_locked']),

                                     'title'    => NoNull($Row['title']),
                                     'description' => NoNull($Row['description']),
                                     'language' => $locale,
                                     'level'    => $level,

                                     'is_active'    => YNBool($Row['is_active']),
                                     'resource_url' => $cdnUrl . '/materials/' . NoNull(mb_substr('000000' . nullInt($Row['material_id']), -6)) . '/',

                                     'modules'  => $modules,
                                     'course'   => $course,
                                     'units'    => $units,
                                     'meta'     => $meta,


                                     'created_at'   => apiDate($Row['created_at'], 'Z'),
                                     'created_unix' => apiDate($Row['created_at'], 'U'),
                                     'updated_at'   => apiDate($Row['updated_at'], 'Z'),
                                     'updated_unix' => apiDate($Row['updated_at'], 'U'),
                                     'updated_by'   => $acct->getAccountDetails($Row['updated_by'], $Row['updatedby_version']),
                                    );

                    /* Ensure the record is cleaned up a bit */
                    if ( is_bool($record['start_at']) ) {
                        unset($record['start_unix']);
                        unset($record['start_at']);
                    }
                    if ( is_bool($record['until_at']) ) {
                        unset($record['until_unix']);
                        unset($record['until_at']);
                    }
                    if ( is_bool($record['language']) ) { unset($record['language']); }
                    if ( is_bool($record['level']) ) { unset($record['level']); }

                    /* Add the record to the output */
                    $data[] = $record;
                }
            }
            unset($acct);

            /* If we have data, let's return it */
            if ( is_array($data) && count($data) ) { return $data; }
        }

        /* If we're here, then there are no locations to return (which would be odd) */
        $this->_setMetaMessage("There are no locations available for your account", 404);
        return false;
    }

    /**
     *  Function collects and returns the meta data for a given Note in an array or an unhappy boolean
     */
    private function _getNoteMetaData( $note_id, $version ) {
        $note_id = nullInt($note_id);
        $version = nullInt($version);

        /* Perform some basic validation */
        if ( $note_id <= 0 ) { return false; }

        /* Check to see if the meta data is already cached */
        $CacheKey = 'note-' . substr('00000000' . $note_id, -8) . '-meta-' . $version;
        $data = getCacheObject($CacheKey);

        /* If we do not have any applicable data, then collect it */
        if ( is_array($data) === false ) {
            $ReplStr = array( '[NOTE_ID]' => nullInt($note_id) );
            $sqlStr = readResource(SQL_DIR . '/note/getNoteMetaData.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                if ( count($rslt) > 0 ) {
                    foreach ( $rslt as $Row ) {
                        if ( nullInt($Row['version']) > $version ) { $version = nullInt($Row['version']); }
                    }
                    $data = buildMetaArray($rslt);
                }
            }

            /* If we have some data, let's save it */
            if ( is_array($data) && count($data) > 0 ) {
                $CacheKey = 'note-' . substr('00000000' . $note_id, -8) . '-meta-' . $version;
                setCacheObject($CacheKey, $data);
            }
        }

        /* If we have data, return it. Otherwise, unhappy boolean */
        if ( is_array($data) && count($data) > 0 ) { return $data; }
        return false;
    }

    /** ********************************************************************* *
     *  Writing Functions
     ** ********************************************************************* */
    /**
     *  Function creates or updates a Note record and returns an updated Note object
     *
     *  Note: Passing an empty string with a valid Note.id will mark a record as deleted
     */
    private function _setNote( $content, $type = 'note.normal', $private = false, $meta = false, $note_id = 0 ) {
        $validTypes = array('note.normal', 'note.secret', 'note.private', 'note.locked');
        $content = $this->_scrubContent($content);
        $private = YNBool($is_private);
        $note_id = nullInt($note_id);
        $type = strtolower(NoNull($type));

        /* Perform some basic validation */
        if ( in_array($type, $validTypes) === false ) { $type = 'note.normal'; }
        if ( mb_strlen($content) <= 0 && $note_id <= 0 ) { return false; }
        if ( is_array($meta) === false ) { $meta = false; }

        /* Prep the Write */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[CONTENT]'    => sqlScrub($content),
                          '[PRIVATE]'    => BoolYN($private),
                          '[NOTE_ID]'    => nullInt($note_id),
                          '[TYPE]'       => sqlScrub($type),
                         );
        $sqlStr = readResource(SQL_DIR . '/note/setNoteRecord.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                $note_id = nullInt($Row['note_id']);

                /* Write the meta data if we have any */
                if ( is_array($meta) && count($meta) > 0 ) { $sOK = $this->_setMetaRecords($note_id, $meta); }

                /* Return an updated Note Object */
                return $this->_getNoteById($Row['note_id'], $Row['note_version']);
            }
        }

        /* If we're here, we could not write the note */
        $this->_setMetaMessage("Could not write Note", 400);
        return false;
    }

    /**
     *  Function writes the meta data in $meta to the database and returns either a version or an unhappy boolean response
     *
     *  Note: Meta keys that are not included in the $meta object WILL NOT be removed from the database
     */
    private function _setMetaRecords( $note_id, $meta ) {
        if ( is_array($meta) === false )
        $note_id = nullInt($note_id);
        $version = 0;

        /* Perform some basic validation */
        if ( $note_id <= 0 ) { return false; }

        /* Run through the meta */
        foreach ( $meta as $Key=>$Value ) {
            $isOK = true;

            /* Perform some validation on the key */
            if ( mb_strlen($Key) <= 0 ) { $isOK = false; }
            if ( mb_strpos($Key, '.') === false ) { $isOK = false; }

            /* If things appear okay, record it to the database */
            if ( $isOK ) {
                $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                                  '[NOTE_ID]'    => nullInt($note_id),
                                  '[VALUE]'      => sqlScrub($Value),
                                  '[KEY]'        => sqlScrub($Key),
                                 );
                $sqlStr = readResource(SQL_DIR . '/note/setNoteMeta.sql', $ReplStr);
                $rslt = doSQLQuery($sqlStr);
                if ( is_array($rslt) ) {
                    foreach ( $rslt as $Row ) {
                        if ( nullInt($Row['version']) > $version ) { $version = nullInt($Row['version']); }
                    }
                }
            }
        }

        /* If we've updated some meta, let's return the version number */
        if ( $version > 0 ) { return $version; }

        /* If we're here, something failed */
        $this->_setMetaMessage("Could not save Note Meta record", 400);
        return false;
    }

    /** ********************************************************************* *
     *  Interntal Functions
     ** ********************************************************************* */
    /**
     *  Function returns a Note by Id if it exists and is visible, otherwise an unhappy boolean
     */
    private function _getNoteById( $note_id, $version = 0 ) {
        $note_id = nullInt($note_id);
        $version = nullInt($version);

        /* Perform a basic error check */
        if ( $note_id <= 0 ) { return false; }

        $CachePrefix = 'note-' . paddNumber($note_id, 8);
        $data = false;
        $uid = 0;

        /* Is there a "proper" version number for this Post? */
        $propVer = false;
        if ( $version <= 0 ) { $propVer = getGlobalObject($CachePrefix); }
        if ( is_bool($propVer) === false && nullInt($propVer) > 1000 ) {
            $version = nullInt($propVer);
        }

        /* Append the version to the prefix */
        $CacheKey = $CachePrefix . '-' . nullInt($version);

        /* If we have a valid version, check to see if we have the requested data in the cache */
        if ( $version > 1000 ) { $data = getCacheObject($CacheKey); }

        /* If we do not have data, collect it from the DB */
        if ( is_array($data) === false ) {
            $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                              '[NOTE_ID]'    => nullInt($note_id)
                             );
            $sqlStr = readResource(SQL_DIR . '/note/getNoteById.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                require_once(LIB_DIR . '/account.php');
                $acct = new Account($this->settings, $this->strings);

                foreach ( $rslt as $Row ) {
                    if ( nullInt($Row['version']) > $version ) { $version = nullInt($Row['version']); }
                    $uid = nullInt($Row['updated_by']);

                    $meta = false;
                    if ( YNBool($Row['has_meta']) ) {
                        $meta = $this->_getNoteMetaData($Row['note_id'], $Row['version']);
                    }

                    /* Construct the output array */
                    $data = array( 'guid'    => NoNull($Row['note_guid']),
                                   'type'    => NoNull($Row['note_type']),
                                   'content' => array( 'text' => $this->_getPlainText($Row['note_text']),
                                                       'html' => $this->_getMarkdownHTML($Row['note_text']),
                                                      ),

                                   'hash'    => NoNull($Row['note_hash']),
                                   'meta'    => $meta,

                                   'has_history'    => YNBool($Row['has_history']),
                                   'is_private'     => YNBool($Row['is_private']),

                                   'created_at'     => apiDate($Row['created_at'], 'Z'),
                                   'created_unix'   => apiDate($Row['created_at'], 'U'),
                                   'updated_at'     => apiDate($Row['updated_at'], 'Z'),
                                   'updated_unix'   => apiDate($Row['updated_at'], 'U'),
                                   'updated_by'     => $acct->getAccountDetails($Row['updated_by'], $Row['updated_by_version']),
                                  );
                }
                unset($acct);
            }

            /* Ensure the Cache Key is set to the proper Post version */
            if ( $version > 1000 ) { $CacheKey = $CachePrefix . '-' . $version; }

            /* If we have valid data, let's save it */
            if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { setCacheObject($CacheKey, $data); }

            /* Set the Post version for future queries of the same object (if required) */
            setGlobalObject($CachePrefix, nullInt($version));
        }

        /* Check if the Note is visible to the requesting account */
        if ( YNBool($data['is_private']) ) {
            if ( in_array($this->settings['_account_type'], array('account.global', 'account.admin')) === false && $uid != $this->settings['_account_id'] ) { $data = false; }
        }

        /* If we have valid data, return it. Otherwise, unhappy boolean. */
        if ( is_array($data) && mb_strlen(NoNull($data['guid'])) == 36 ) { return $data; }
        return false;
    }

    /** ********************************************************************* *
     *  Text Cleanup Functions
     ** ********************************************************************* */
    /**
     *  INTERNAL function takes the text it receives and cleans it up for consistency and clarity
     */
    private function _scrubContent( $text ) {
        /* Correct any tag and spacing issues that may exist */
        $splitOn = array( '<div>', '</div>', '<p>', '</p>', '<br>', '<br/>', "\r", "\n" );
        $text = str_ireplace($splitOn, "\n\n", $text);

        /* Handle <ul> and <ol> transformations */
        preg_match_all('/<ul>(.*?)<\/ul>/s', $text, $matches);
        if ( is_array($matches) ) {
            foreach ( $matches[0] as $idx=>$html ) {
                $ulli = str_replace(array("\n", "\r", '<li>', '</li>'), array('', '', '* ', "\n"), $matches[1][$idx]);
                $text = str_replace($matches[0][$idx], $ulli, $text);
            }
        }

        preg_match_all('/<ol>(.*?)<\/ol>/s', $text, $matches);
        if ( is_array($matches) ) {
            foreach ( $matches[0] as $idx=>$html ) {
                $olli = str_replace(array("\n", "\r", '<li>', '</li>'), array('', '', '1. ', "\n"), $matches[1][$idx]);
                $text = str_replace($matches[0][$idx], $olli, $text);
            }
        }

        /* Remove Inline Styling */
        $text = preg_replace('/(<[^>]*) style=("[^"]+"|\'[^\']+\')([^>]*>)/i', '$1$3', $text);

        /* Replace some of the HTML tags with Markdown formatting where possible */
        $mdSwap = array( '<strong>' => '**', '</strong>' => '**', '<b>' => '**', '</b>' => '**',
                         '<em>' => '*', '</em>' => '*', '<i>' => '*', '</i>' => '*',
                         '<u>' => '++', '</u>' => '++', '<del>' => '~~', '</del>' => '~~',
                         '<h1>' => '#', '</h1>' => "\n", '<h2>' => '##', '</h2>' => "\n", '<h3>' => '###', '</h3>' => "\n",
                         '<h4>' => '####', '</h4>' => "\n", '<h5>' => '#####', '</h5>' => "\n", '<h6>' => '######', '</h6>' => "\n",
                         '<span>' => '', '</span>' => '', '<code>' => '`', '</code>' => '`',
                        );
        $text = str_replace(array_keys($mdSwap), array_values($mdSwap), $text);

        /* Try to Handle Inline HTML */
        $text = NoNull(str_replace('<', '&lt;', $text));
        $ReplStr = array( '&lt;section' => '<section', '&lt;iframe' => '<iframe',
                          '&lt;str' => '<str', '&lt;del' => '<del', '&lt;pre' => '<pre',
                          '&lt;h1' => '<h1', '&lt;h2' => '<h2', '&lt;h3' => '<h3',
                          '&lt;h4' => '<h4', '&lt;h5' => '<h5', '&lt;h6' => '<h6',
                          '&lt;ol' => '<ol', '&lt;ul' => '<ul', '&lt;li' => '<li',
                          '&lt;b' => '<b', '&lt;i' => '<i', '&lt;u' => '<u',
                          '&lt;kbd' => '<kbd', '&lt;/kbd' => '</kbd', '<kbd> ' => '&lt;kbd> ',
                          '`<kbd>`' => '`&lt;kbd>`', '`<kbd></kbd>`' => '`&lt;kbd>&lt;/kbd>`',
                          '&amp;nbsp;' => '&nbsp;',
                         );
        $text = NoNull(str_replace(array_keys($ReplStr), array_values($ReplStr), $text));

        /* Let's work on some lines */
        $source = explode("\n", $text);
        $lines = array();
        $rslt = '';

        /* First we'll identify *proper* lines */
        $inCodeBlock = false;
        $lnCnt = 0;

        foreach ( $source as $line ) {
            /* Are we in a code block? */
            if ( mb_substr($line, 0, 3) == '```' ) {
                $inCodeBlock = !$inCodeBlock;
                $lnCnt = 0;
            }
            if ( $inCodeBlock ) {
                if ( NoNull($line) != '' || $lnCnt == 1 ) {
                    if ( NoNull($line) != '' ) { $lnCnt = 0; }
                    $lines[] = rtrim($line);
                }
                if ( NoNull($line) == '' ) { $lnCnt++; }

            } else {
                if ( NoNull($line) != '' ) {
                    $lines[] = NoNull($line);
                }
            }
        }

        /* Now we'll parse them for lists and other details */
        $inCodeBlock = false;
        $inTable = false;
        $idx = 0;

        foreach ( $lines as $line ) {
            $eol = "\n\n";

            /* Are we working with a Blockquote prefix? */
            if ( mb_substr($line, 0, 4) == '&gt;' ) {
                $line = '> ' . NoNull(mb_substr($line, 5));
            }

            /* If the current line *and* the next line are part of a list, only one newline is required */
            if ( $this->_checkLineIsList($line) ) {
                if ( $this->_checkLineIsList($lines[$idx + 1]) ) { $eol = "\n"; }
            }

            /* Are we working with a table? */
            if ( mb_strpos(NoNull($lines[$idx + 1]), '--') !== false && mb_strpos(NoNull($lines[$idx + 1]), '|') !== false ) { $inTable = true; }
            if ( NoNull($line) == '' || ($inTable && mb_strpos(NoNull($line), '|') === false ) ) {
                $rslt .= "\n";
                $inTable = false;
            }

            /* Are we in a code block? */
            if ( mb_substr($line, 0, 3) == '```' ) { $inCodeBlock = !$inCodeBlock; }
            if ( $inCodeBlock || $inTable ) { $eol = "\n"; }

            /* Add the Line to the Return */
            $rslt .= $line . $eol;
            $idx++;
        }

        /* Return the scrubbed text */
        return NoNull($rslt);
    }

    /**
     *  Function cleans up some weirdness with passed text items to ensure a plain text response
     */
    private function _getPlainText( $text = '' ) {
        if ( mb_strlen(NoNull($text)) <= 0 ) { return ''; }

        $splitOn = array( '<div>', '</div>', '<p>', '</p>', '<br>', '<br/>', "\r", "\n" );
        $text = str_ireplace($splitOn, "\n\n", $text);

        /* Ensure there are not long expanses of hard returns */
        for ( $i = 50; $i >= 3; $i-- ) {
            $text = str_ireplace(str_repeat("\n", $i), "\n\n", $text);
        }

        /* Return a Cleaned up Text string */
        return NoNull($text);
    }

    /**
     *  Function Converts a Text String to HTML Via Markdown
     */
    private function _getMarkdownHTML( $text, $showLinkURL = false ) {
        $illegals = array( '<' => '&lt;', '>' => '&gt;' );
        $Excludes = array("\r", "\n", "\t");
        $ValidateUrls = false;
        if ( defined('VALIDATE_URLS') ) { $ValidateUrls = YNBool(VALIDATE_URLS); }

        /* Confirm the string is semi-valid */
        $text = NoNull($text);
        if ( mb_strlen($text) <= 2 ) { return $text; }

        /* If the Markdown Parser hasn't been loaded yet, do so */
        if ( $this->parser === false ) { $this->parser = new MarkdownExtra; }

        /* Fix the Lines with Breaks Where Appropriate */
        $text = str_replace("\r", "\n", $text);
        $lines = explode("\n", $text);
        $inCodeBlock = false;
        $inTable = false;
        $fixed = '';
        $last = '';

        foreach ( $lines as $line ) {
            $thisLine = NoNull($line);
            if ( mb_strpos($thisLine, '```') !== false ) { $inCodeBlock = !$inCodeBlock; }
            if ( $inCodeBlock ) { $thisLine = $line; }
            $doBR = ( $fixed != '' && $last != '' && $thisLine != '' ) ? true : false;

            // Are we working with a table?
            if ( mb_strpos($thisLine, '--') !== false && mb_strpos($thisLine, '|') !== false ) { $inTable = true; }
            if ( NoNull($thisLine) == '' ) { $inTable = false; }

            // If We Have What Looks Like a List, Prep It Accordingly
            if ( nullInt(mb_substr($thisLine, 0, 2)) > 0 && nullInt(mb_substr($last, 0, 2)) > 0 ) { $doBR = false; }
            if ( mb_substr($thisLine, 0, 2) == '* ' && mb_substr($last, 0, 2) == '* ' ) { $doBR = false; }
            if ( mb_substr($thisLine, 0, 2) == '- ' && mb_substr($last, 0, 2) == '- ' ) { $doBR = false; }

            if ( mb_substr($thisLine, 0, 2) == '* ' && mb_substr($last, 0, 2) != '* ' && strlen($last) > 0 ) {
                $fixed .= "\n";
                $doBR = false;
            }
            if ( mb_substr($thisLine, 0, 2) == '- ' && mb_substr($last, 0, 2) != '- ' && strlen($last) > 0 ) {
                $fixed .= "\n";
                $doBR = false;
            }

            if ( nullInt(mb_substr($thisLine, 0, 2)) > 0 && $last == '' ) { $fixed .= "\n"; }
            if ( mb_substr($thisLine, 0, 2) == '* ' && $last == '' ) { $fixed .= "\n"; }
            if ( mb_substr($thisLine, 0, 2) == '- ' && $last == '' ) { $fixed .= "\n"; }
            if ( $inCodeBlock || mb_strpos($thisLine, '```') !== false ) { $doBR = false; }
            if ( $inTable ) { $doBR = false; }

            $fixed .= ( $doBR ) ? '<br>' : "\n";
            $fixed .= ( $inCodeBlock ) ? str_replace(array_keys($illegals), array_values($illegals), $line) : $thisLine;
            $last = NoNull($thisLine);
        }

        $postFix = array('<br><br>' => "\n\n" );
        $text = NoNull( str_replace(array_keys($postFix), array_values($postFix), $fixed) );

        /* Handle the Footnotes */
        $fnotes = '';
        if ( strpos($text, '[') > 0 ) {
            $notes = array();
            $pass = 0;

            while ( $pass < 100 ) {
                $inBracket = false;
                $btxt = '';
                $bidx = '';
                $bid = 0;
                for ( $i = 0; $i < strlen($text); $i++ ) {
                    if ( substr($text, $i, 1) == "[" ) {
                        $bracketValid = false;
                        if ( strpos(substr($text, $i, 6), '. ') > 0 ) { $bracketValid = true; }
                        if ( $bracketValid || $inBracket ) {
                            $inBracket = true;
                            $bid++;
                        }
                    }
                    if ( $inBracket ) { $btxt .= substr($text, $i, 1); }
                    if ( $inBracket && substr($text, $i, 1) == "]" ) {
                        $bid--;
                        if ( $bid <= 0 ) {
                            $n = count($notes) + 1;
                            $ntxt = substr($btxt, strpos($btxt, '. ') + 2);
                            $ntxt = substr($ntxt, 0, strlen($ntxt) - 1);
                            if ( NoNull($ntxt) != '' ) {
                                $text = str_replace($btxt, "<sup class=\"footnote\" data-idx=\"$n\">$n</sup>", $text);
                                $notes[] = NoNull($ntxt);
                                $btxt = '';
                                break;
                            }
                        }
                    }
                }
                $pass++;
            }

            if ( count($notes) > 0 ) {
                $idx = 1;

                foreach ( $notes as $note ) {
                    $note = NoNull( str_replace(array_keys($postFix), array_values($postFix), $note) );
                    $fnotes .= "<li class=\"footnote\" data-idx=\"$idx\">" . $this->parser->defaultTransform($note) . "</li>";
                    $idx++;
                }
            }
        }

        /* Handle Code Blocks */
        if (preg_match_all('/\```(.+?)\```/s', $text, $matches)) {
            foreach($matches[0] as $fn) {
                $cbRepl = array( '```' => '', '<code><br>' => "<code>", '<br></code>' => '</code>', "\n" => '<br>', ' ' => "&nbsp;" );
                $code = "<pre><code>" . str_replace(array_keys($cbRepl), array_values($cbRepl), $fn) . "</code></pre>";
                $code = str_replace(array_keys($cbRepl), array_values($cbRepl), $code);
                $text = str_replace($fn, $code, $text);
            }
        }

        /* Are there any underlines or strike-throughs? */
        $text = preg_replace('/\+\+(.+)\+\+/sU',"<u>$1</u>", $text);
        $text = preg_replace('/~~(.+)~~/sU',"<del>$1</del>", $text);

        /* Get the Markdown Formatted */
        $text = str_replace('\\', '&#92;', $text);
        $rVal = $this->parser->defaultTransform($text);
        for ( $i = 0; $i <= 5; $i++ ) {
            foreach ( $Excludes as $Item ) {
                $rVal = str_replace($Item, '', $rVal);
            }
        }

        /* Replace any Hashtags if they exist */
        $rVal = str_replace('</p>', '</p> ', $rVal);
        $words = explode(' ', " $rVal ");
        $out_str = '';
        foreach ( $words as $word ) {
            $clean_word = NoNull(strip_tags($word));
            $hash = '';

            if ( NoNull(substr($clean_word, 0, 1)) == '#' ) {
                $hash_scrub = array('#', '?', '.', ',', '!');
                $hash = NoNull(str_replace($hash_scrub, '', $clean_word));

                if ($hash != '' && mb_stripos($hash_list, $hash) === false ) {
                    if ( $hash_list != '' ) { $hash_list .= ','; }
                    $hash_list .= strtolower($hash);
                }
            }
            $out_str .= ($hash != '') ? str_ireplace($clean_word, '<span class="hash" data-hash="' . strtolower($hash) . '">' . NoNull($clean_word) . '</span> ', $word)
                                      : "$word ";
        }
        $rVal = NoNull($out_str);

        /* Format the URLs as Required */
        $url_pattern = '#(www\.|https?://)?[a-z0-9]+\.[a-z0-9]\S*#i';
        $fixes = array( 'http//'  => "http://",         'http://http://'   => 'http://',
                        'https//' => "https://",        'https://https://' => 'https://',
                        ','       => '',                'http://https://'  => 'https://',
                       );
        $splits = array( '</p><p>' => '</p> <p>', '<br>' => '<br> ' );
        $scrub = array('#', '?', '.', ':', ';');
        $words = explode(' ', ' ' . str_replace(array_keys($splits), array_values($splits), $rVal) . ' ');

        $out_str = '';
        foreach ( $words as $word ) {
            /* Do We Have an Unparsed URL? */
            if ( mb_strpos($word, '.') !== false && mb_strpos($word, '.') <= (mb_strlen($word) - 1) && NoNull(str_ireplace('.', '', $word)) != '' &&
                 mb_strpos($word, '[') === false && mb_strpos($word, ']') === false ) {
                $clean_word = str_replace("\n", '', strip_tags($word));
                if ( in_array(substr($clean_word, -1), $scrub) ) { $clean_word = substr($clean_word, 0, -1); }

                $url = ((stripos($clean_word, 'http') === false ) ? "http://" : '') . $clean_word;
                $url = str_ireplace(array_keys($fixes), array_values($fixes), $url);
                $headers = false;

                /* Ensure We Have a Valid URL Here */
                $hdParts = explode('.', $url);
                $hdCount = 0;

                /* Count How Many Parts We Have */
                if ( is_array($hdParts) ) {
                    foreach( $hdParts as $item ) {
                        if ( NoNull($item) != '' ) { $hdCount++; }
                    }
                }

                /* No URL Has Just One Element */
                if ( $hdCount > 0 ) {
                    if ( $ValidateUrls ) {
                        if ( $hdCount > 1 ) { $headers = get_headers($url); }
                        if ( is_array($headers) ) {
                            $okHead = array('HTTPS/1.0 200 OK', 'HTTPS/1.1 200 OK', 'HTTPS/2.0 200 OK',
                                            'HTTP/1.0 200 OK', 'HTTP/1.1 200 OK', 'HTTP/2.0 200 OK');
                            $suffix = '';
                            $rURL = $url;

                            // Do We Have a Redirect?
                            if ( count($headers) > 0 ) {
                                foreach ($headers as $Row) {
                                    if ( mb_strpos(strtolower($Row), 'location') !== false ) {
                                        $rURL = NoNull(str_ireplace('location:', '', strtolower($Row)));
                                        break;
                                    }
                                    if ( in_array(NoNull(strtoupper($Row)), $okHead) ) { break; }
                                }
                            }

                            $host = parse_url($rURL, PHP_URL_HOST);
                            if ( $host != '' && $showLinkURL ) {
                                if ( mb_strpos(strtolower($clean_word), strtolower($host)) === false ) {
                                    $suffix = " [" . strtolower(str_ireplace('www.', '', $host)) . "]";
                                }
                            }

                            $clean_text = $clean_word;
                            if ( mb_stripos($clean_text, '?') ) {
                                $clean_text = substr($clean_text, 0, mb_stripos($clean_text, '?'));
                            }

                            $word = str_ireplace($clean_word, '<a target="_blank" href="' . $rURL . '">' . $clean_text . '</a>' . $suffix, $word);
                        }

                    } else {
                        $hparts = explode('.', parse_url($url, PHP_URL_HOST));
                        $domain = '';
                        $parts = 0;
                        $nulls = 0;

                        for ( $dd = 0; $dd < count($hparts); $dd++ ) {
                            if ( NoNull($hparts[$dd]) != '' ) {
                                $domain = NoNull($hparts[$dd]);
                                $parts++;
                            } else {
                                $nulls++;
                            }
                        }

                        if ( $nulls == 0 && $parts > 1 && isValidTLD($domain) ) {
                            $host = parse_url($url, PHP_URL_HOST);
                            if ( $host != '' && $showLinkURL ) {
                                if ( mb_strpos(strtolower($clean_word), strtolower($host)) === false ) {
                                    $suffix = " [" . strtolower(str_ireplace('www.', '', $host)) . "]";
                                }
                            }

                            $clean_text = $clean_word;
                            if ( mb_stripos($clean_text, '?') ) {
                                $clean_text = substr($clean_text, 0, mb_stripos($clean_text, '?'));
                            }

                            $word = str_ireplace($clean_word, '<a target="_blank" href="' . $url . '">' . $clean_text . '</a>' . $suffix, $word);
                        }
                    }
                }
            }

            /* Output Something Here */
            $out_str .= " $word";
        }

        /* If We Have Footnotes, Add them */
        if ( $fnotes != '' ) { $out_str .= '<ol class="footnote-list">' . $fnotes . '</ol>'; }

        /* Fix any Links that Don't Have Targets */
        $rVal = str_ireplace('<a href="', '<a target="_blank" href="', $out_str);
        $rVal = str_ireplace('<a target="_blank" href="http://mailto:', '<a href="mailto:', $rVal);

        /* Do Not Permit Any Forbidden Characters to Go Back */
        $forbid = array( '<script'      => "&lt;script",    '</script'           => "&lt;/script",   '< script'     => "&lt;script",
                         '<br></p>'     => '</p>',          '<br></li>'          => '</li>',         '<br> '        => '<br>',
                         '&#95;'        => '_',             '&amp;#92;'          => '&#92;',         ' </p>'        => '</p>',
                         '&lt;iframe '  => '<iframe ',      '&gt;&lt;/iframe&gt' => '></iframe>',    '&lt;/iframe>' => '</iframe>',
                         '</p></p>'     => '</p>',          '<p><p>'             => '<p>',           '</p> <p>'     => '</p><p>',
                         '...'          => '…',

                         '<p><blockquote>' => '<blockquote>',
                         '<pre><code><br>' => '<pre><code>',
                        );
        for ( $i = 0; $i < 10; $i++ ) {
            $rVal = str_replace(array_keys($forbid), array_values($forbid), $rVal);
        }

        /* Return the Markdown-formatted HTML */
        return NoNull($rVal);
    }

    /**
     *  INTERNAL function determines if a line provided is a list item or not, returning a boolean value
     */
    private function _checkLineIsList( $line ) {
        /* First check to see if the line is an unsorted list */
        if ( mb_substr($line, 0, 2) == '* ' ) { return true; }

        /* If we're here, let's check to see if line is a numbered list */
        $listStartPos = mb_strpos($line, '. ');
        if ( $listStartPos >= 0 ) {
            $sub = mb_substr($line, 0, $listStartPos);
            if ( is_numeric($sub) && mb_strlen($sub) <= 5 ) {
                $prefix = nullInt($sub) . '.';
                if ( mb_strpos($line, $prefix) == 0 ) { return true; }
            }
        }

        /* If we're here, then the line is probably not a list */
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