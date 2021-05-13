<?php

/**
 * @author Jason F. Irwin
 *
 * Class Responds to the Data Route and Returns the Appropriate Data
 */
require_once(CONF_DIR . '/versions.php');
require_once(LIB_DIR . '/functions.php');
require_once(LIB_DIR . '/cookies.php');

class Kiroku {
    var $settings;
    var $strings;

    function __construct() {
        $GLOBALS['Perf']['app_s'] = getMicroTime();

        /* Check to ensure that config.php exists */
        if ( $this->_chkRequirements() ) {
            require_once(CONF_DIR . '/config.php');

            $sets = new cookies;
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

        // Check to Ensure the Visitor is not Overwhelming the Server(s) and Respond Accordingly
        if ( $this->_isValidRequest() && $this->_isValidAgent() ) {
            switch ( strtolower($this->settings['Route']) ) {
                case 'api':
                    require_once(LIB_DIR . '/api.php');
                    break;

                case 'hooks':
                    require_once(LIB_DIR . '/hooks.php');
                    break;

                default:
                    require_once(LIB_DIR . '/web.php');
                    break;
            }

            $data = new Route($this->settings, $this->strings);
            $rslt = $data->getResponseData();
            $type = $data->getResponseType();
            $code = $data->getResponseCode();
            $meta = $data->getResponseMeta();
            $more = ((method_exists($data, 'getHasMore')) ? $data->getHasMore() : false);
            unset($data);

        } else {
            if ( $this->_isValidAgent() ) {
                $code = $this->_isValidRequest() ? 420 : 422;
            } else {
                $code = 403;
            }
            $rslt = readResource( FLATS_DIR . "/templates/$code.html", $ReplStr);
        }

        // Return the Data in the Correct Format
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
        $roots = array( 'phpmyadmin', 'phpmyadm1n', 'phpmy',
                        'tools', 'typo3', 'xampp', 'www', 'web',
                        'wp-admin', 'wp-content', 'wp-includes', 'vendor',
                       );
        if ( in_array(strtolower(NoNull($this->settings['PgSub1'], $this->settings['PgRoot'])), $roots) ) { return false; }
        if ( strpos(strtolower(NoNull($this->settings['ReqURI'])), '.php') !== false ) { return false; }
        return true;
    }

    /**
     *  Function determines if the reported agent is valid for use or not. This is not meant to be a comprehensive list of
     *      unacceptable agents, as agent strings are easily spoofed.
     */
    private function _isValidAgent() {
        $excludes = array( 'ahrefsbot', 'mj12bot', 'mb2345browser', 'semrushbot', 'mmb29p', 'mbcrawler', 'blexbot', 'sogou web spider',
                           'serpstatbot', 'semanticscholarbot', 'yandexbot', 'yandeximages', 'gwene', 'barkrowler', 'yeti',
                           'seznambot', 'domainstatsbot', 'sottopop', 'megaindex.ru', '9537.53', 'seekport crawler', 'iccrawler',
                           'magpie-crawler', 'crawler4j', 'facebookexternalhit', 'turnitinbot', 'netestate',
                           'thither.direct', 'liebaofast', 'micromessenger', 'youdaobot', 'theworld', 'qqbrowser',
                           'dotbot', 'exabot', 'gigabot', 'slurp', 'keybot translation', 'searchatlas.com',
                           'bingbot/2.0', 'aspiegelbot', 'baiduspider', 'ruby',
                           'zh-cn;oppo a33 build/lmy47v', 'oppo a33 build/lmy47v;wv' );
        $agent = strtolower(NoNull($_SERVER['HTTP_USER_AGENT']));
        if ( $agent != '' ) {
            foreach ( $excludes as $chk ) {
                if ( mb_strpos($agent, $chk) !== false ) { return false; }
            }
        }
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