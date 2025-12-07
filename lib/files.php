<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called to manage files
 */
require_once( LIB_DIR . '/functions.php');

class Files {
    var $settings;
    var $strings;
    var $cache;

    function __construct( $settings, $strings = false ) {
        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
        $this->cache = array();

        /* Prep the Class */
        $this->_populateClass();
    }

    /**
     *  Function Populates the Initial Values Required by the Class
     */
    private function _populateClass() {
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']) );
        $sqlStr = readResource(SQL_DIR . '/files/getFileLimits.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                $this->settings['bucket_size'] = nullInt($Row['upload_limit']);
                $this->settings['bucket_used'] = nullInt($Row['upload_bytes']);

                /* Let's also set the amount remaining */
                $this->settings['bucket_remain'] = nullInt($Row['upload_limit']) - nullInt($Row['upload_bytes']);
                if ( nullInt($this->settings['bucket_remain']) < 0 ) { $this->settings['bucket_remain'] = 0; }
            }
        }
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
        if ( mb_strlen($Activity) == 36 ) { $Activity = 'send'; }

        switch ( $Activity ) {
            case 'list':
                return $this->_getFilesList();
                break;

            case 'send':
                return $this->_sendFile();
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
        $rVal = false;

        switch ( $Activity ) {
            case 'receive':
            case 'recieve':
            case 'upload':
            case '':
                return $this->_setFile();
                break;

            default:
                /* Do Nothing */
        }

        /* If we're here, there's nothing */
        $this->_setMetaMessage("Nothing to do: [POST] $Activity", 404);
        return false;
    }

    private function _performDeleteAction() {
        $Activity = NoNull(strtolower($this->settings['PgSub1']));
        if ( nullInt($this->settings['PgSub1']) > 0 ) { $Activity = 'scrub'; }
        $rVal = false;

        switch ( $Activity ) {
            case 'scrub':
                return $this->_deleteFile();
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
    public function getFileById( $id, $version = 0 ) { return $this->_getFileById( $id, $version ); }
    public function requestResource() { return $this->_requestResource(); }

    /** ********************************************************************* *
     *  Settings and Input Functions
     ** ********************************************************************* */
    /**
     *  Function returns a consistent array of values that can be used for file settings and preferences
     */
    private function _getInputValues() {
        $CleanGuid = NoNull($this->settings['file_guid'], $this->settings['file']);
        if ( mb_strlen($CleanGuid) != 36 ) { $CleanGuid = ''; }

        $CleanName = NoNull($this->settings['file_name'], $this->settings['name']);
        $CleanMime = NoNull($this->settings['file_mime'], $this->settings['mime']);

        /* Determine whether the file should be public or private */
        $validACL = array('private', 'public-read', 'public-read-write');
        $CleanAccess = strtolower(NoNull($this->settings['access'], $this->settings['acl']));
        if ( in_array($CleanAccess, $validACL) === false ) { $CleanAccess = 'private'; }

        /* Do we have a text-based expiration date? */
        $ExpiresAt = NoNull($this->settings['expires_at'], $this->settings['expires_on']);
        $setExpiresAt = '';
        if ( mb_strlen($ExpiresAt) >= 10 ) {
            $exp_unix = strtotime($ExpiresAt);
            if ( is_bool($exp_unix) === false) {
                $setExpiresAt = date('Y-m-d H:i:59', $exp_unix);
            }
        }

        /* Do we have a unix-based expiration date? This overrides any supplied text-based value */
        $ExpiresUnix = nullInt($this->settings['expires_unix']);
        if ( $ExpiresUnix > time() ) { $setExpiresAt = date('Y-m-d H:i:59', $ExpiresUnix); }

        /* Is there a password? If so, the access must be private */
        $CleanPassword = NoNull($this->settings['password'], $this->settings['pass']);
        if ( mb_strlen($CleanPassword) > 0 ) {
            $CleanPassword = hash('sha256', $CleanPassword, false);
            $CleanAccess = 'private';
        }

        /* Return an Array */
        return array( 'file_guid'  => ((mb_strlen($CleanGuid) == 36) ? $CleanGuid : ''),
                      'file_name'  => NoNull($CleanName),
                      'file_mime'  => NoNull($CleanMime),

                      'access'     => NoNull($CleanAccess, 'private'),
                      'expires_at' => NoNull($setExpiresAt),
                      'password'   => NoNull($CleanPassword),
                     );
    }

    /** ********************************************************************* *
     *  File Request Functions
     ** ********************************************************************* */
    /**
     *  Function checks to see if the requested resource is valid and accessible to the requestor. If the
     *      resource is not available, an unhappy boolean is returned to trigger a 404.
     *
     *  Note: 404's are preferrable to 403's, as it will (theoretically) reduce access attempts
     */
    private function _requestResource() {
        if ( !defined('CDN_PATH') ) { define('CDN_PATH', ''); }
        if ( !defined('USE_S3') ) { define('USE_S3', 0); }
        $inputs = $this->_getInputValues();
        $isValid = false;
        $srcName = '';
        $srcMime = '';

        /* Determine the Location */
        $Location = NoNull($this->settings['PgRoot']);

        /* Determine the File Name */
        $FileName = '';
        for ( $i = 9; $i >= 1; $i-- ) {
            if ( mb_strlen($FileName) <= 0 ) {
                $FileName = NoNull($this->settings['PgSub' . $i]);
            }
        }
        $BaseName = str_replace(array('_original', '_medium', '_small', '_thumb'), '', $FileName);

        /* Determine the Owner */
        $OwnerId = nullInt(strtok($FileName, '-'));
        if ( $OwnerId < 0 ) { $OwnerId = 0; }

        /* Perform some basic validation */
        if ( mb_strlen($FileName) < 24 ) { return false; }
        if ( mb_strlen($Location) < 7 ) { return false; }
        if ( $OwnerId <= 0 ) { return false; }

        /* Check to see if the file is accessible to the current account holder */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[PASSWORD]'   => sqlScrub($inputs['password']),
                          '[FILE_NAME]'  => sqlScrub($BaseName),
                          '[LOCATION]'   => sqlScrub($Location),
                          '[OWNER]'      => nullInt($OwnerId)
                         );
        $sqlStr = readResource(SQL_DIR . '/files/checkResourceAccess.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                if ( YNBool($Row['can_access']) ) {
                    $srcName = NoNull($Row['public_name']);
                    $srcMime = NoNull($Row['mimetype']);
                    $isValid = true;
                }
            }
        }

        /* If we cannot continue, let's exit (or show a 404?) */
        if ( $isValid === false ) { return false; }

        /* If we're here, we have access. Check that the resource exists on the web server. If it doesn't collect it from the bucket (if applicable) */
        $srcPath = CDN_PATH . "/$Location/$FileName";
        if ( file_exists($srcPath) === false ) {
            if ( YNBool(USE_S3) ) {
                $rsp = $this->_getFile($srcPath);
                if ( $rsp !== true ) { return false; }
            }
        }

        /* Send the Resource File */
        $sOK = sendResourceFile( $srcPath, $srcName, $srcMime, $this->settings );
        return $sOK;
    }

    /**
     *  Function retrieves a file from the S3 bucket and returns a boolean response
     */
    private function _getFile( $srcPath ) {
        if ( !defined('AWS_REGION_NAME') ) { define('AWS_REGION_NAME', ''); }
        if ( !defined('AWS_BUCKET_NAME') ) { define('AWS_BUCKET_NAME', ''); }
        if ( !defined('AWS_ACCESS_KEY') ) { define('AWS_ACCESS_KEY', ''); }
        if ( !defined('AWS_SECRET_KEY') ) { define('AWS_SECRET_KEY', ''); }
        if ( !defined('CDN_PATH') ) { define('CDN_PATH', ''); }
        if ( !defined('USE_S3') ) { define('USE_S3', 0); }

        if ( YNBool(USE_S3) ) {
            if ( mb_strlen(NoNull(AWS_REGION_NAME)) < 10 ) {
                $this->_setMetaMessage("Invalid AWS Region Name Provided", 401);
                return false;
            }
            if ( mb_strlen(NoNull(AWS_BUCKET_NAME)) < 10 ) {
                $this->_setMetaMessage("Invalid AWS Bucket Name Provided", 401);
                return false;
            }
            if ( mb_strlen(NoNull(AWS_ACCESS_KEY)) < 20 ) {
                $this->_setMetaMessage("Invalid AWS Access Key Provided", 401);
                return false;
            }
            if ( mb_strlen(NoNull(AWS_SECRET_KEY)) < 36 ) {
                $this->_setMetaMessage("Invalid AWS Secret Key Provided", 401);
                return false;
            }

            $s3Path = str_replace(CDN_PATH . '/', '', $srcPath);

            /* If we're here, we should have a proper set of configuration data for the S3 class */
            require_once(LIB_DIR . '/s3.php');
            $s3 = new S3(AWS_ACCESS_KEY, AWS_SECRET_KEY, true, 's3.amazonaws.com', AWS_REGION_NAME);

            $rsp = $s3->getObject(AWS_BUCKET_NAME, $s3Path, $srcPath);
            if ( is_object($rsp) ) { $rsp = objectToArray($rsp); }

            /* If we have a success code and the file exists, return a happy boolean */
            if ( file_exists($srcPath) && filesize($srcPath) > 0 ) { return true; }
        }

        /* If we're here, nothing was done */
        return false;
    }

    /** ********************************************************************* *
     *  File Upload Functions
     ** ********************************************************************* */
    /**
     *  Function Handles File Uploads and returns a confirmation array or an unhappy boolean
     */
    private function _setFile() {
        if ( !defined('AWS_REGION_NAME') ) { define('AWS_REGION_NAME', ''); }
        if ( !defined('AWS_BUCKET_NAME') ) { define('AWS_BUCKET_NAME', ''); }
        if ( !defined('AWS_ACCESS_KEY') ) { define('AWS_ACCESS_KEY', ''); }
        if ( !defined('AWS_SECRET_KEY') ) { define('AWS_SECRET_KEY', ''); }
        if ( !defined('CDN_PATH') ) { define('CDN_PATH', ''); }
        if ( !defined('USE_S3') ) { define('USE_S3', 0); }
        $inputs = $this->_getInputValues();
        $s3 = false;

        /* Verify the basics are in place */
        if ( mb_strlen(NoNull(CDN_PATH)) < 10 ) {
            $this->_setMetaMessage("Invalid File storage location defined", 500);
            return false;
        }

        /* Do we have space to upload? */
        if ( $this->settings['bucket_remain'] <= 0 ) {
            $cleanBytes = formatBytes($this->settings['bucket_remain'], 3);
            $this->_setMetaMessage("Insufficient Storage Remaining ($cleanBytes)", 400);
            return false;
        }

        /* If we have files, let's process them */
        if ( is_array($_FILES) && count($_FILES) > 0 ) {
            require_once(LIB_DIR . '/images.php');
            $ids = array();

            /* If We Should Use Amazon's S3, Activate the Class */
            if ( YNBool(USE_S3) ) {
                if ( mb_strlen(NoNull(AWS_REGION_NAME)) < 10 ) {
                    $this->_setMetaMessage("Invalid AWS Region Name Provided", 401);
                    return false;
                }
                if ( mb_strlen(NoNull(AWS_BUCKET_NAME)) < 10 ) {
                    $this->_setMetaMessage("Invalid AWS Bucket Name Provided", 401);
                    return false;
                }
                if ( mb_strlen(NoNull(AWS_ACCESS_KEY)) < 20 ) {
                    $this->_setMetaMessage("Invalid AWS Access Key Provided", 401);
                    return false;
                }
                if ( mb_strlen(NoNull(AWS_SECRET_KEY)) < 36 ) {
                    $this->_setMetaMessage("Invalid AWS Secret Key Provided", 401);
                    return false;
                }

                /* If we're here, we should have a proper set of configuration data for the S3 class */
                require_once(LIB_DIR . '/s3.php');
                $s3 = new S3(AWS_ACCESS_KEY, AWS_SECRET_KEY, true, 's3.amazonaws.com', AWS_REGION_NAME);
            }

            foreach ( $_FILES as $obj ) {
                $LocalPrefix = paddNumber($this->settings['_account_id'], 0) . '-' . time() . '-' . strtolower(getRandomString(6, true));
                $LocalPath = CDN_PATH . '/' . date('Y-m');

                $FileName = NoNull(basename($obj['name']));
                $FileSize = nullInt($obj['size']);
                $FileType = NoNull($obj['type']);
                $FileExt = $this->_getFileExtension($FileName, $FileType);

                if ( NoNull($FileType) == '' ) {
                    switch ( strtolower(NoNull($FileExt)) ) {
                        case 'jpeg':
                        case 'jpg':
                            $FileType = 'image/jpeg';
                            break;

                        case 'gif':
                            $FileType = 'image/gif';
                            break;

                        case 'png':
                            $FileType = 'image/png';
                            break;
                    }
                }

                /* Process the File if we have Space in the Bucket, otherwise Record a Size Error */
                if ( $FileSize > 0 && $FileSize <= nullInt($this->settings['bucket_remain']) ) {
                    $this->settings['bucket_remain'] -= $FileSize;

                    if ( isset($obj) ) {
                        $LocalName = $LocalPrefix . '.' . $this->_getFileExtension($FileName, $FileType);
                        $fullPath = $LocalPath . '/' . strtolower($LocalName);
                        $s3Path = date('Y-m') . '/' . strtolower($LocalName);
                        checkDIRExists($LocalPath);

                        $imgMeta = false;
                        $geoData = false;

                        if ( mb_strlen($FileName) > 0 ) {
                            // Shrink the File If Needs Be
                            if ( $this->_isResizableImage($FileType) ) {
                                $thumbName = $LocalPrefix . '_thumb.' . $FileExt;
                                $thumbPath = $LocalPath . '/' . strtolower($thumbName);

                                $propName = $LocalPrefix . '_medium.' . $FileExt;
                                $propPath = $LocalPath . '/' . strtolower($propName);

                                $origName = $LocalPrefix . '.' . $FileExt;
                                $origPath = $LocalPath . '/' . strtolower($origName);
                                move_uploaded_file($obj['tmp_name'], $origPath);

                                /* Upload the Original Image to S3 if Appropriate */
                                if ( YNBool(USE_S3) ) {
                                    $s3Path = date('Y-m') . strtolower("/$origName");
                                    $sOK = $s3->putObject($s3->inputFile($origPath, false), AWS_BUCKET_NAME, $s3Path, $inputs['access']);
                                }

                                /* Resize the Image */
                                $img = new Images();
                                $img->load($origPath, $FileType);
                                $geoData = $img->getGeolocation();
                                $imgMeta = $img->getPhotoMeta();

                                /* Ensure the Image Meta variable is properly set */
                                if ( is_array($imgMeta) === false || count($imgMeta) <= 0 ) { $imgMeta = array(); }

                                $imgWidth = $img->getWidth();
                                $isAnimated = $img->is_animated();
                                $imgMeta['image.animated'] = $img->is_animated();
                                unset($img);

                                if ( $isAnimated !== true ) {
                                    if ( $imgWidth > 960 ) {
                                        $img = new Images();
                                        $img->load($origPath, $FileType);
                                        $img->reduceToWidth(960);
                                        $isGood = $img->save($propPath);
                                        $imgMeta['image.reduced'] = $img->is_reduced();
                                        unset($img);

                                        if ( YNBool(USE_S3) ) {
                                            $s3Path = date('Y-m') . strtolower("/$propName");
                                            $sOK = $s3->putObject($s3->inputFile($propPath, false), AWS_BUCKET_NAME, $s3Path, $inputs['access']);
                                        }
                                    }
                                    if ( $imgWidth > 480 ) {
                                        $img = new Images();
                                        $img->load($origPath, $FileType);
                                        $img->reduceToWidth(480);
                                        $isGood = $img->save($thumbPath);
                                        $imgMeta['image.thumb'] = $img->is_reduced();
                                        unset($img);

                                        if ( YNBool(USE_S3) ) {
                                            $s3Path = date('Y-m') . strtolower("/$thumbName");
                                            $sOK = $s3->putObject($s3->inputFile($thumbPath, false), AWS_BUCKET_NAME, $s3Path, $inputs['access']);
                                        }
                                    }
                                }

                            } else {
                                $isGood = move_uploaded_file($obj['tmp_name'], $fullPath);

                                /* Upload the Original Image to S3 if Appropriate */
                                if ( YNBool(USE_S3) ) {
                                    $s3Path = date('Y-m') . strtolower("/$LocalName");
                                    $sOK = $s3->putObject($s3->inputFile($fullPath, false), AWS_BUCKET_NAME, $s3Path, $inputs['access']);
                                }
                            }

                            $fObj = $this->_setFileData( $fullPath, strtolower($FileName), strtolower($FileType), $geoData, $imgMeta );
                            if ( is_array($fObj) && mb_strlen(NoNull($fObj['guid'])) == 36 ) { $ids[] = $fObj; }
                        }
                    }
                }
            }

            /* Collect the File Data */
            if ( is_array($ids) && count($ids) > 0 ) {
                return array( 'files' => $ids );
            }
        }

        /* If We're Here, Nothing Was Found */
        $this->_setMetaMessage( "No valid files identified", 400 );
        return false;
    }

    /**
     *  Function Records the File and its Metadata to the Database and returns the appropriate Files object
     */
    private function _setFileData( $FilePath, $FileName, $MimeType, $geo = false, $meta = false ) {
        if ( !defined('USE_S3') ) { define('USE_S3', 0); }
        $inputs = $this->_getInputValues();

        /* Only record an upload if the file has been properly saved from its temporary location */
        if ( file_exists($FilePath) ) {
            $bytes = filesize($FilePath);
            $local = basename($FilePath);
            $hash = hash_file('sha256', $FilePath);

            $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                              '[LOCAL]'      => sqlScrub($local),
                              '[BYTES]'      => nullInt($bytes),
                              '[NAME]'       => sqlScrub($FileName),
                              '[HASH]'       => sqlScrub($hash),
                              '[PATH]'       => sqlScrub(date('Y-m')),
                              '[MIME]'       => sqlScrub($MimeType),

                              '[FILE_GUID]'  => sqlScrub($inputs['file_guid']),
                              '[EXPIRES_AT]' => sqlScrub($inputs['expires_at']),
                              '[ACCESS]'     => sqlScrub($inputs['access']),
                             );
            $sqlStr = readResource(SQL_DIR . '/files/setFileData.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                /* Append some info do the Meta array */
                if ( is_array($meta) === false || count($meta) <= 0 ) { $meta = array(); }
                $meta['access.password'] = NoNull($inputs['password']);
                $meta['access.acl'] = NoNull($inputs['access']);

                /* if we have successfully saved the File record, write the meta */
                foreach ( $rslt as $Row ) {
                    $file_guid = NoNull($Row['file_guid']);
                    $file_id = nullInt($Row['file_id']);
                    $version = nullInt($Row['version']);

                    /* Is there any geographic data to record? */
                    if ( is_array($geo) && count($geo) > 0 ) {
                        foreach ( $geo as $Key=>$Value ) {
                            if ( is_bool($Value) ) { $Value = BoolYN($Value); }
                            $ReplStr = array( '[FILE_ID]' => nullInt($file_id),
                                              '[KEY]'     => 'geo.' . sqlScrub($Key),
                                              '[VALUE]'   => sqlScrub($Value),
                                             );
                            $sqlStr = readResource(SQL_DIR .  '/files/setFileMeta.sql', $ReplStr);
                            $tslt = doSQLQuery($sqlStr);
                        }
                    }

                    /* Is there any metadata to record? */
                    if ( is_array($meta) && count($meta) > 0 ) {
                        foreach ( $meta as $Key=>$Value ) {
                            if ( is_bool($Value) ) { $Value = BoolYN($Value); }
                            $ReplStr = array( '[FILE_ID]' => nullInt($file_id),
                                              '[KEY]'     => sqlScrub($Key),
                                              '[VALUE]'   => sqlScrub($Value),
                                             );
                            $sqlStr = readResource(SQL_DIR .  '/files/setFileMeta.sql', $ReplStr);
                            $tslt = doSQLQuery($sqlStr);
                        }
                    }

                    /* Return the File.id */
                    if ( mb_strlen($file_guid) == 36 && $file_id > 0 ) { return $this->_getFileById($file_id); }
                }
            }
        }

        /* If We're Here, The Write Failed */
        return false;
    }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    /**
     *  Function returns an Array of Files Objects
     */
    private function _getFilesList() {
        $PageID = nullInt($this->settings['page']) - 1;
        if ( $PageID < 0 ) { $PageID = 0; }

        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[FILE_ID]'    => nullInt($this->settings['file_id'], $this->settings['PgSub1']),
                          '[PAGE]'       => ($PageID * 50),
                         );

        $sqlStr = readResource(SQL_DIR . '/files/getFilesList.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $cdn_prefix = '//' . AWS_REGION_NAME . '/' . intToAlpha($this->settings['_account_id']) . '/';
            $data = array();
            foreach ( $rslt as $Row ) {
                $is_deleted = YNBool($Row['is_deleted']);

                $data[] = array( 'file' => array( 'id'         => nullInt($Row['id']),
                                                  'name'       => (($is_deleted) ? false : NoNull($Row['name'])),
                                                  'size'       => (($is_deleted) ? false : nullInt($Row['size'])),
                                                  'mime'       => (($is_deleted) ? false : NoNull($Row['type'])),
                                                  'is_deleted' => $is_deleted,
                                                 ),

                                 'in_post'       => (($is_deleted) ? false : ((nullInt($Row['posts'])) ? true : false)),
                                 'in_meta'       => (($is_deleted) ? false : ((nullInt($Row['in_meta'])) ? true : false)),
                                 'is_avatar'     => (($is_deleted) ? false : ((nullInt($Row['is_avatar'])) ? true : false)),
                                 'has_metadata'  => (($is_deleted) ? false : YNBool($Row['has_meta'])),

                                 'cdn_url'       => (($is_deleted) ? false : $cdn_prefix . NoNull($Row['hash'])),

                                 'uploaded_at'   => (($is_deleted) ? false : NoNull($Row['uploaded_at'])),
                                 'uploaded_unix' => (($is_deleted) ? false : strtotime($Row['uploaded_at'])),
                                 'updated_at'    => NoNull($Row['updated_at']),
                                 'updated_unix'  => strtotime($Row['updated_at']),
                                );
            }

            // If We Have Data, Return It
            if ( count($data) > 0 ) { return $data; }
        }

        // If We're Here, There's Nothing
        return array();
    }

    /**
     *  Function Returns a File Object for a Given ID, or an Unhappy Boolean
     *
     *  Note: Caching is not done for the primary object here to ensure that permissions are always correctly checked
     */
    private function _getFileById( $file_id = 0, $version = 0 ) {
        $file_id = nullInt($file_id);
        $version = nullInt($version);

        if ( nullInt($file_id) <= 0 ) { return false; }
        if ( nullInt($version) < 0 ) { $version = 0; }

        /* Determine the Correct Cache Key (if the version is zero, check to see if we've already done a lookup in this HTTP request) */
        $CacheKey = 'file-' . substr('00000000' . $file_id, -8) . '-' . $version;
        if ( $version <= 0 ) {
            $propKey = getGlobalObject('prop-' . $CacheKey);
            if ( is_bool($propKey) === false && NoNull($propKey) != '' ) { $CacheKey = $propKey; }
        }

        /* Do we already have data for this File? */
        $data = getCacheObject($CacheKey);

        /* If we do not have any record, collect it */
        if ( is_array($data) === false ) {
            $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                              '[FILE_ID]'    => nullInt($file_id),
                             );
            $sqlStr = readResource(SQL_DIR . '/files/getFileData.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                $cdn_prefix = getCdnUrl();

                require_once(LIB_DIR . '/account.php');
                $acct = new Account($this->settings, $this->strings);

                foreach ( $rslt as $Row ) {
                    $propKey = 'file-' . substr('00000000' . nullInt($Row['file_id']), -8) . '-' . nullInt($Row['version']);
                    $meta = false;
                    if ( YNBool($Row['has_meta']) ) {
                        $meta = $this->_getFileMetaData($Row['file_id'], $Row['version']);
                    }

                    $url = $cdn_prefix . '/' . NoNull($Row['location']) . '/' . NoNull($Row['name']);
                    if ( YNBool($Row['has_password']) ) { $url = ''; }

                    $smallUrl = '';
                    $thumbUrl = '';
                    if ( YNBool($Row['has_password']) === false && $this->_isResizableImage($Row['mimetype']) ) {
                        if ( is_array($meta['image']) ) {
                            $FileExt = getFileExtension($Row['name']);
                            if ( mb_strlen($FileExt) < 2 ) { $FileExt = getRandomString(16); }

                            if ( YNBool($meta['image']['reduced']) ) { $smallUrl = $cdn_prefix . '/' . NoNull($Row['location']) . '/' . NoNull(str_replace(".$FileExt", "_medium.$FileExt", $Row['name'])); }
                            if ( YNBool($meta['image']['thumb']) ) { $thumbUrl = $cdn_prefix . '/' . NoNull($Row['location']) . '/' . NoNull(str_replace(".$FileExt", "_thumb.$FileExt", $Row['name'])); }
                        }
                    }

                    $data = array( 'guid'   => NoNull($Row['file_guid']),
                                   'type'   => NoNull($Row['mimetype']),
                                   'name'   => NoNull($Row['public_name']),
                                   'hash'   => NoNull($Row['hash']),
                                   'icon'   => $this->_getMimeTypeIcon($Row['mimetype']),
                                   'bytes'  => nullInt($Row['bytes']),

                                   'owner'  => $acct->getAccountDetails($Row['account_id'], $Row['account_version']),
                                   'meta'   => $meta,

                                   'original' => $url,
                                   'url'      => NoNull($smallUrl, $url),
                                   'thumb'    => $thumbUrl,

                                   'has_password' => YNBool($Row['has_password']),
                                   'is_readonly'  => YNBool($Row['is_readonly']),
                                   'is_public'    => YNBool($Row['is_public']),

                                   'expires_at'   => apiDate($Row['expires_at'], 'Z'),
                                   'expires_unix' => apiDate($Row['expires_at'], 'U'),

                                   'created_at'   => apiDate($Row['created_at'], 'Z'),
                                   'created_unix' => apiDate($Row['created_at'], 'U'),
                                   'updated_at'   => apiDate($Row['updated_at'], 'Z'),
                                   'updated_unix' => apiDate($Row['updated_at'], 'U'),
                                  );
                }
                unset($acct);

                /* Are there any fields to remove? */
                if ( is_array($data) ) {
                    if ( $data['meta'] === false ) { unset($data['meta']); }

                    if ( mb_strlen(NoNull($data['original'])) > 5 && NoNull($data['original']) == NoNull($data['url']) ) { $data['original'] = false; }
                    if ( NoNull($data['original']) == '' ) { unset($data['original']); }
                    if ( NoNull($data['thumb']) == '' ) { unset($data['thumb']); }
                    if ( NoNull($data['url']) == '' ) { unset($data['url']); }
                }

                /* Is the cache key different from the "Proper Key"? */
                if ( $CacheKey != $propKey ) { setGlobalObject('prop-' . $CacheKey, $propKey); }

                /* Cache the Data (if it's valid) */
                if ( mb_strlen($data['guid']) == 36 ) { setCacheObject($propKey, $data); }
            }
        }

        /* If we are a guest, remove some records */
        if ( in_array($this->settings['_account_type'], array('account.anonymous', 'account.guest')) ) {
            unset($data['owner']);
            unset($data['meta']);
        }

        /* Return the File Object or an unhappy boolean */
        if ( is_array($data) && mb_strlen($data['guid']) == 36 ) { return $data; }
        return false;
    }

    /**
     *  Function collects and returns the meta data for a given File in an array or an unhappy boolean
     */
    private function _getFileMetaData( $file_id, $version ) {
        $file_id = nullInt($file_id);
        $version = nullInt($version);

        /* Perform some basic validation */
        if ( $file_id <= 0 ) { return false; }

        /* Check to see if the meta data is already cached */
        $CacheKey = 'file-' . substr('00000000' . $file_id, -8) . '-meta-' . $version;
        $data = getCacheObject($CacheKey);

        /* If we do not have any applicable data, then collect it */
        if ( is_array($data) === false ) {
            $ReplStr = array( '[FILE_ID]' => nullInt($file_id) );
            $sqlStr = readResource(SQL_DIR . '/files/getFileMetaData.sql', $ReplStr);
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
                $CacheKey = 'file-' . substr('00000000' . $file_id, -8) . '-meta-' . $version;
                setCacheObject($CacheKey, $data);
            }
        }

        /* If we have data, return it. Otherwise, unhappy boolean */
        if ( is_array($data) && count($data) > 0 ) { return $data; }
        return false;
    }

    /**
     *  Function Marks a File as Deleted After Scrubbing it from S3
     */
    private function _deleteFile() {
        $FileID = nullInt($this->settings['file_id'], $this->settings['PgSub1']);
        if ( $FileID <= 0 ) { return false; }
        $isGood = false;
        $rVal = "Could Not Delete File.";

        // Confirm Ownership of the File
        $data = $this->_getFilesList();
        if ( is_array($data) === false ) { return "You Do Not Own This File."; }

        // Remove the File from S3 If It Exists
        if ( USE_S3 == 1 ) {
            $s3Path = str_replace('//' . AWS_REGION_NAME . '/', '', $data[0]['cdn_url']);
            if ( $s3Path != '' ) {
                require_once(LIB_DIR . '/s3.php');

                $s3 = new S3(AWS_ACCESS_KEY, AWS_SECRET_KEY, true, AWS_REGION_NAME);
                $isGood = $s3->deleteObject(CDN_URL, $s3Path);
                unset($s3);
            }
        }

        // If the File Deletion Was Successful, Update the Database
        if ( $isGood ) {
            $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                              '[FILE_ID]'    => $FileID,
                             );
            $sqlStr = readResource(SQL_DIR . '/files/deleteFileByID.sql', $ReplStr);
            $rslt = doSQLExecute($sqlStr);

            // Collect the File Data One Last Time
            $rVal = $this->_getFilesList();

        } else {
            return "Could Not Remove File from CDN. Please Contact Support.";
        }

        // Return a Happy Array of Data or an Unhappy Message
        return $rVal;
    }

    /** ********************************************************************* *
     *  Internal Functions
     ** ********************************************************************* */
    /**
     *  Function Returns the File Extension of a Given File
     */
    private function _getFileExtension( $FileName, $MimeType ) {
        $ext = NoNull(substr(strrchr($FileName,'.'), 1));
        if ( mb_strlen($ext) <= 0 ) {
            switch ( strtolower($MimeType) ) {
                case 'audio/x-mp3':
                case 'audio/mp3':
                    $ext = 'mp3';
                    break;

                case 'audio/x-mp4':
                case 'audio/mp4':
                case 'video/mp4':
                    $ext = 'mp4';
                    break;

                case 'audio/x-m4a':
                case 'audio/m4a':
                case 'video/m4a':
                    $ext = 'm4a';
                    break;

                case 'video/m4v':
                    $ext = 'm4v';
                    break;

                case 'audio/mpeg':
                    $ext = 'mpeg';
                    break;

                case 'image/jpeg':
                case 'image/jpg':
                    $ext = 'jpeg';
                    break;

                case 'image/x-windows-bmp':
                case 'image/bmp':
                    $ext = 'bmp';
                    break;

                case 'image/gif':
                    $ext = 'gif';
                    break;

                case 'image/png':
                    $ext = 'png';
                    break;

                case 'image/tiff':
                    $ext = 'tiff';
                    break;

                case 'video/quicktime':
                    $ext = 'mov';
                    break;

                case 'application/x-mpegurl':
                    $ext = 'm3u8';
                    break;

                case 'video/mp2t':
                    $ext = 'ts';
                    break;
            }
        }

        /* Return the File Extension */
        return $ext;
    }

    /**
     *  Function returns a Font-Awesome icon identifier given the supplied MIME Type
     */
    private function _getMimeTypeIcon( $MimeType ) {
        $MimeType = strtolower(NoNull($MimeType));
        $group = strtok($MimeType, '/');

        /* Does the MIME fit a common group? */
        switch ( $group ) {
            case 'audio':
                return 'file-audio';
                break;

            case 'image':
                return 'file-image';
                break;

            case 'video':
                return 'file-video';
                break;
        }

        /* Excel */
        if ( mb_strpos($MimeType, 'officedocument.spreadsheetml') > 0 ) { return 'file-powerpoint'; }
        if ( mb_strpos($MimeType, 'vnd.ms-excel') > 0 ) { return 'file-powerpoint'; }

        /* PowerPoint */
        if ( mb_strpos($MimeType, 'officedocument.presentationml') > 0 ) { return 'file-powerpoint'; }
        if ( mb_strpos($MimeType, 'vnd.ms-powerpoint') > 0 ) { return 'file-powerpoint'; }

        /* Word */
        if ( mb_strpos($MimeType, 'officedocument.wordprocessingml') > 0 ) { return 'file-powerpoint'; }
        if ( mb_strpos($MimeType, 'vnd.ms-word') > 0 ) { return 'file-powerpoint'; }

        switch ( $MimeType ) {
            case 'application/zip':
                return 'file-zipper';
                break;

            case 'application/pdf':
                return 'file-pdf';
                break;

            case 'application/javascript':
            case 'application/xhtml+xml':
            case 'application/xslt+xm':
            case 'application/json':
            case 'application/xml':
                return 'file-code';
                break;

            default:
                /* Skip to the end */
        }

        /* Return a default file icon */
        return 'file-lines';
    }

    /**
     *  Function determines if the MIME type matches a known, good, resizable image file type
     */
    private function _isResizableImage( $MimeType ) {
        $valids = array( 'image/jpg', 'image/jpeg', 'image/gif', 'image/x-gif', 'image/png', 'image/tiff', 'image/bmp', 'image/x-windows-bmp' );
        return in_array(strtolower($MimeType), $valids);
    }

    /**
     *  Function Returns a Boolean Stating Whether a Gif is Animated or Not
     */
    private function _isGifImage( $MimeType ) {
        $valids = array( 'image/gif', 'image/x-gif' );
        return in_array(strtolower($MimeType), $valids);
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