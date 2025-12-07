<?php

/**
 * @author Jason F. Irwin
 *
 * Class Responds to the Data Route and Returns the Appropriate Data
 */
require_once(CONF_DIR . '/versions.php');
require_once(LIB_DIR . '/functions.php');
require_once(LIB_DIR . '/cookies.php');

class Midori {
    var $settings;
    var $strings;

    function __construct() {
        $GLOBALS['Perf']['app_s'] = getMicroTime();

        /* Check to ensure that config.php exists */
        if ( $this->_chkRequirements() ) {
            require_once(CONF_DIR . '/config.php');

            $sets = new Cookies();
            $this->settings = $sets->cookies;
            $this->strings = getLangDefaults($this->settings['_language_code']);
            unset( $sets );
        }
    }

    /* ********************************************************************* *
     *  Function determines what needs to be done and returns the
     *      appropriate JSON Content
     * ********************************************************************* */
    function buildResult() {
        $ReplStr = $this->_getReplStrArray();
        $rslt = readResource(FLATS_DIR . '/templates/500.html', $ReplStr);
        $type = 'text/html';
        $meta = false;
        $code = 500;

        /* Check to ensure the visitor meets the validation criteria and respond accordingly */
        if ( $this->_isValidRequest() ) {
            switch ( strtolower($this->settings['Route']) ) {
                case 'api':
                    require_once(LIB_DIR . '/api.php');
                    break;

                case 'webhooks':
                case 'webhook':
                case 'hooks':
                case 'hook':
                    require_once(LIB_DIR . '/hook.php');
                    break;

                default:
                    require_once(LIB_DIR . '/web.php');
                    break;
            }

            /* Ensure the Timezone is properly set */
            $useTZ = NoNull($this->settings['_timezone'], TIMEZONE);
            date_default_timezone_set($useTZ);

            /* Based on the Route, Perform the Necessary Operations */
            $data = new Route($this->settings, $this->strings);
            $rslt = $data->getResponseData();
            $type = $data->getResponseType();
            $code = $data->getResponseCode();
            $meta = $data->getResponseMeta();
            $more = ((method_exists($data, 'getHasMore')) ? $data->getHasMore() : false);
            unset($data);

        } else {
            $code = $this->_isValidRequest() ? 420 : 422;
            $rslt = readResource( FLATS_DIR . "/templates/$code.html", $ReplStr);
        }

        /* Ensure the Site.id is semi-accurate */
        if ( nullInt($GLOBALS['site_id']) > 1 && nullInt($this->settings['_site_id'], 1) != nullInt($GLOBALS['site_id'], 1) ) {
            $this->settings['_site_id'] = nullInt($GLOBALS['site_id'], 1);
        }

        /* Return the Data in the Correct Format */
        formatResult($rslt, $this->settings, $type, $code, $meta, $more);
    }

    /**
     *  Function Constructs and Returns the Language String Replacement Array
     */
    private function _getReplStrArray() {
        $httpHost = NoNull($_SERVER['REQUEST_SCHEME'], 'http') . '://' . NoNull($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']);
        $rVal = array( '[SITEURL]' => NoNull($this->settings['HomeURL'], $httpHost),
                       '[RUNTIME]' => getRunTime('html'),
                      );
        foreach ( $this->strings as $Key=>$Val ) {
            $rVal["[$Key]"] = NoNull($Val);
        }

        $strs = getLangDefaults();
        if ( is_array($strs) ) {
            foreach ( $strs as $Key=>$Val ) {
                $rVal["[$Key]"] = NoNull($Val);
            }
        }

        // Return the Array
        return $rVal;
    }

    /** ********************************************************************** *
     *  Bad Behaviour Functions
     ** ********************************************************************** */
    /**
     *  Function determines if the request is looking for a WordPress, phpMyAdmin, or other
     *      open-source package-based attack vector and returns an abrupt message if so.
     */
    private function _isValidRequest() {
        $excludes = array( 'phpmyadmin', 'phpmyadm1n', 'phpmy', 'pass', 'tools', 'typo3', 'xampp', 'www', 'web',
                           'wp-admin', 'wp-content', 'wp-includes', 'vendor', 'config', 'dockerfile',
                           'storage', 'logs', 'log',
                           '.aws', '.env', '.git', '.gitignore', '.ds_store', '.py', '.svn', '.vscode',
                           '.dockerignore',
                           'app.js', 'config.js',
                           'application.yml', 'wlwmanifest.xml',
                           'settings.yaml',
                          );
        $keys = array('PgRoot', 'PgSub1', 'PgSub2', 'PgSub3');
        foreach ( $keys as $kk ) {
            $vv = strtolower(NoNull($this->settings[$kk]));
            if ( mb_strlen($vv) > 0 && in_array($vv, $excludes) ) { return false; }
        }

        /* Check for any protected file extensions */
        $exts = array('conf', 'env', 'ini', 'php', 'sql', 'md');
        foreach ( $exts as $ext ) {
            if ( strpos(strtolower(NoNull($this->settings['ReqURI'])), '.' . $ext) !== false ) { return false; }
        }

        /* Check the Path for stupid stuff */
        $flags = array('../..', '0x');
        foreach ( $flags as $flag ) {
            if ( strpos(strtolower(NoNull($this->settings['ReqURI'])), $flag) !== false ) { return false; }
        }

        /* If we're here, we're probably okay */
        return true;
    }

    /**
     *  Function Looks for Basics before allowing anything to continue
     */
    private function _chkRequirements() {
        /* Confirm the Existence of a config.php file */
        $cfgFile = CONF_DIR . '/config.php';
        if ( file_exists($cfgFile) === false ) {
            $ReplStr = $this->_getReplStrArray();
            $ReplStr['[msg500Title]'] = 'Missing Config.php';
            $ReplStr['[msg500Line1]'] = 'No <code>config.php</code> file found!';
            $ReplStr['[msg500Line2]'] = 'This should not happen unless the system is in the midst of being built for the first time ...';
            $rslt = readResource(FLATS_DIR . '/templates/500.html', $ReplStr);

            formatResult($rslt, $this->settings, 'text/html', 500, false);
            return false;
        }

        /* If we're here, it's all good */
        return true;
    }
}
?>