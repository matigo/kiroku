<?php

/**
 * @author Jason F. Irwin
 *
 * Class Responds to the Web Data Route and Returns the Appropriate Data
 */
require_once(CONF_DIR . '/versions.php');
require_once(CONF_DIR . '/config.php');
require_once( LIB_DIR . '/functions.php');
require_once( LIB_DIR . '/cookies.php');
require_once( LIB_DIR . '/site.php');

class Route extends Kiroku {
    var $settings;
    var $strings;
    var $custom;
    var $site;

    function __construct( $settings, $strings ) {
        $this->settings = $settings;
        $this->strings = $strings;
        $this->custom = false;
        $this->site = new Site($this->settings);

        /* Ensure the Asset Version.id Is Set */
        if ( defined('CSS_VER') === false ) {
            $ver = filemtime(CONF_DIR . '/versions.php');
            if ( nullInt($ver) <= 0 ) { $ver = nullInt(APP_VER); }
            define('CSS_VER', $ver);
        }
    }

    /* ************************************************************************************** *
     *  Function determines what needs to be done and returns the appropriate HTML Document.
     * ************************************************************************************** */
    public function getResponseData() {
        $ThemeLocation = NoNull($this->settings['theme'], 'default');
        $ReplStr = $this->_getReplStrArray();
        $this->settings['status'] = 200;

        $html = readResource(FLATS_DIR . '/templates/500.html', $ReplStr);
        $ThemeFile = THEME_DIR . '/error.html';
        $LoggedIn = false;

        // Collect the Site Data - Redirect if Invalid
        $data = $this->site->getSiteData();
        if ( is_array($data) ) {
            $RedirectURL = NoNull($_SERVER['HTTP_REFERER'], $data['protocol'] . '://' . $data['HomeURL']);
            $PgRoot = strtolower(NoNull($this->settings['PgRoot']));

            // Is There an HTTPS Upgrade Request?
            $Protocol = getServerProtocol();

            // Determine if a Redirect is Required
            if ( strtolower($_SERVER['SERVER_NAME']) != NoNull($data['HomeURL']) ) { $data['do_redirect'] = true; }
            if ( $Protocol != $data['protocol'] ) {
                $suffix = '/' . NoNull($this->settings['PgRoot']);
                if ( $suffix != '' ) {
                    for ( $i = 1; $i <= 9; $i++ ) {
                        $itm = NoNull($this->settings['PgSub' . $i]);
                        if ( $itm != '' ) { $suffix .= "/$itm"; }
                    }
                }

                // Redirect to the Appropriate URL
                redirectTo( $data['protocol'] . '://' . NoNull(str_replace('//', '/', $data['HomeURL'] . $suffix), $this->settings ) );
            }

            // Is this a JSON Request?
            $CType = NoNull($_SERVER["CONTENT_TYPE"], 'text/html');
            if ( strtolower($CType) == 'application/json' ) { $this->_handleJSONRequest($data); }

            // Are We Signing In?
            if ( $PgRoot == 'validatetoken' && NoNull($this->settings['token']) != '' ) {
                $this->settings['remember'] = false;
                $data['do_redirect'] = true;
            }

            // Are We Signed In and Accessing Something That Requires Being Signed In?
            if ( $this->settings['_logged_in'] ) {
                switch ( $PgRoot ) {
                    case 'signout':
                    case 'logout':
                        require_once(LIB_DIR . '/auth.php');
                        $auth = new Auth($this->settings);
                        $sOK = $auth->performLogout();
                        unset($auth);

                        redirectTo( $RedirectURL, $this->settings );
                        break;

                    case 'receive':
                    case 'collect':
                        $this->_handleZIPRequest();
                        break;

                    case 'settings':
                    case 'messages':
                        if ( NoNull($this->settings['_access_level'], 'read') != 'write' ) {
                            $this->settings['status'] = 403;
                            redirectTo( $RedirectURL . '/403', $this->settings );
                        }
                        break;

                    default:
                        /* Do Nothing Here */
                }

            } else {
                /* Is there a redirect required while not signed in? */
                switch ( $PgRoot ) {
                    case 'signout':
                    case 'logout':
                        redirectTo( $RedirectURL, $this->settings );
                        break;

                    default:
                        /* Do Nothing Here */
                }
            }

            // Is There a Language Change Request?
            if ( strtolower(NoNull($this->settings['PgRoot'])) == 'lang' ) {
                $val = NoNull($this->settings['PgSub1'], $this->settings['_language_code']);
                if ( $val != '' ) {
                    if ( $val != NoNull($this->settings['_language_code']) ) {
                        setcookie('DispLang', $val, 3600, "/", NoNull(strtolower($_SERVER['SERVER_NAME'])) );

                        // If We're Signed In on a 10C site, set the Language
                        if ( NoNull($this->settings['token']) != '' ) {
                            require_once(LIB_DIR . '/account.php');
                            $acct = new Account($this->settings);
                            $isOK = $acct->setAccountLanguage($val);
                            unset($acct);
                        }
                    }
                    $data['do_redirect'] = true;
                }
            }

            // Perform the Redirect if Necessary
            $suffix = ( YNBool($this->settings['remember']) ) ? '?remember=Y' : '';
            if ( $data['do_redirect'] ) { redirectTo( $RedirectURL . $suffix, $this->settings ); }

            // Load the Requested HTML Content
            $html = $this->_getPageHTML( $data );
        }

        // Return the HTML With the Appropriate Headers
        unset($this->strings);
        unset($this->custom);
        unset($this->site);
        return $html;
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
        return is_array(NoNull($this->settings['errors'])) ? $this->settings['errors'] : false;
    }

    /** ********************************************************************** *
     *  Private Functions
     ** ********************************************************************** */
    /**
     *  Function Returns an Array With the Appropriate Content
     */
    private function _getPageHTML( $data ) {
        $ThemeLocation = THEME_DIR . '/' . NoNull($data['location'], 'error');
        if ( file_exists("$ThemeLocation/base.html") === false ) {
            $ThemeLocation = THEME_DIR . '/error';
            $data['location'] = 'error';
        }
        $this->_getLanguageStrings($data['location']);
        $ReplStr = $this->_getPageMetadataArray($data);

        /* Populate the Appropriate Language Strings */
        if ( is_array($this->strings) ) {
            foreach ( $this->strings as $Key=>$Value ) {
                $ReplStr["[$Key]"] = NoNull($Value);
            }
        }

        /* If there is a custom theme class, collect the Page HTML from there */
        if ( file_exists("$ThemeLocation/custom.php") ) {
            if ( $this->custom === false ) {
                require_once("$ThemeLocation/custom.php");
                $ClassName = ucfirst(NoNull($data['location'], 'default'));
                $this->custom = new $ClassName( $this->settings, $this->strings );
            }
            if ( method_exists($this->custom, 'getPageHTML') ) {
                $this->settings['errors'] = $this->custom->getResponseMeta();
                $this->settings['status'] = $this->custom->getResponseCode();
                $ReplStr['[PAGE_HTML]'] = $this->custom->getPageHTML($data);
            }

        } else {
            $ReqFile = $this->_getContentPage($data);
            $ReplStr['[PAGE_HTML]'] = readResource($ReqFile, $ReplStr);
        }

        /* Set the Output HTML */
        $html = readResource( THEME_DIR . "/" . $data['location'] . "/base.html", $ReplStr );

        /* Get the Run-time */
        $runtime = getRunTime('html');

        /* Return the Completed HTML Page Content */
        return str_replace('[GenTime]', $runtime, $html);
    }

    /**
     *  Function parses and handles requests for ZIP Files (generally exports)
     */
    private function _handleZIPRequest() {
        $ZipDIR = TMP_DIR . '/export/' . strtolower(NoNull($this->settings['PgSub1']));
        $ZipFile = NoNull($this->settings['PgSub2']);

        /* If No File is Specified, Find the first ZIP in the Directory */
        if ( $ZipFile == '' ) {
            foreach (glob("$ZipDIR/*.zip") as $fileName) {
                if ( $ZipFile == '' ) { $ZipFile = NoNull($fileName); }
            }
        }

        /* If we have a file and it appears valid, send it */
        if ( $ZipFile != '' && file_exists($ZipFile) && filesize($ZipFile) > 0 ) { sendZipFile($ZipFile); }
    }

    /**
     *  Function Parses and Handles Requests that Come In with an Application/JSON Header
     */
    private function _handleJSONRequest( $site ) {
        $Action = strtolower(NoNull($this->settings['PgSub1'], $this->settings['PgRoot']));
        $format = strtolower(NoNull($_SERVER['CONTENT_TYPE'], 'text/plain'));
        $valids = array( 'application/json' );
        $meta = array();
        $data = false;
        $code = 401;

        if ( in_array($format, $valids) ) {
            switch ( $Action ) {
                case 'profile':
                    require_once(LIB_DIR . '/account.php');
                    $acct = new Account( $this->settings, $this->strings );
                    $data = $acct->getPublicProfile();
                    $meta = $acct->getResponseMeta();
                    $code = $acct->getResponseCode();
                    unset($acct);
                    break;

                default:
                    require_once(LIB_DIR . '/posts.php');
                    $post = new Posts( $this->settings, $this->strings );
                    $data = $post->getPageJSON( $site );
                    $meta = $post->getResponseMeta();
                    $code = $post->getResponseCode();
                    unset($post);
            }
        }

        // If We Have an Array of Data, Return It
        if ( is_array($data) ) { formatResult($data, $this->settings, 'application/json', $code, $meta); }
    }

    /**
     *  Collect the Language Strings that Will Be Used In the Theme
     *  Note: The Default Theme Language is Loaded First To Reduce the Risk of NULL Descriptors
     */
    private function _getLanguageStrings( $Location ) {
        $ThemeLocation = THEME_DIR . '/' . $Location;
        if ( file_exists("$ThemeLocation/base.html") === false ) { $ThemeLocation = THEME_DIR . '/error'; }
        $rVal = array();

        /* Collect the Default Langauge Strings */
        $LangFile = "$ThemeLocation/lang/" . strtolower(DEFAULT_LANG) . '.json';
        if ( file_exists( $LangFile ) ) {
            $json = readResource( $LangFile );
            $items = objectToArray(json_decode($json));

            if ( is_array($items) ) {
                foreach ( $items as $Key=>$Value ) {
                    $rVal["$Key"] = NoNull($Value);
                }
            }
        }

        /* Is Multi-Lang Enabled And Required? If So, Load It */
        $LangCode = NoNull($this->settings['DispLang'], $this->settings['_language_code']);
        if ( ENABLE_MULTILANG == 1 && (strtolower($LangCode) != strtolower(DEFAULT_LANG)) ) {
            $LangFile = "$ThemeLocation/lang/" . strtolower($LangCode) . '.json';
            if ( file_exists( $LangFile ) ) {
                $json = readResource( $LangFile );
                $items = objectToArray(json_decode($json));

                if ( is_array($items) ) {
                    foreach ( $items as $Key=>$Value ) {
                        $rVal["$Key"] = NoNull($Value);
                    }
                }
            }
        }

        /* Do We Have a Special File for the Page? */
        $LangFile = "$ThemeLocation/lang/" . NoNull($this->settings['PgRoot']) . '_' . strtolower(DEFAULT_LANG) . '.json';
        if ( file_exists( $LangFile ) ) {
            $json = readResource( $LangFile );
            $items = objectToArray(json_decode($json));

            if ( is_array($items) ) {
                foreach ( $items as $Key=>$Value ) {
                    $rVal["$Key"] = NoNull($Value);
                }
            }
        }

        $LangCode = NoNull($this->settings['DispLang'], $this->settings['_language_code']);
        if ( ENABLE_MULTILANG == 1 && (strtolower($LangCode) != strtolower(DEFAULT_LANG)) ) {
            $LangFile = "$ThemeLocation/lang/" . NoNull($this->settings['PgRoot']) . '_' . strtolower($LangCode) . '.json';
            if ( file_exists( $LangFile ) ) {
                $json = readResource( $LangFile );
                $items = objectToArray(json_decode($json));

                if ( is_array($items) ) {
                    foreach ( $items as $Key=>$Value ) {
                        $rVal["$Key"] = NoNull($Value);
                    }
                }
            }
        }

        // Update the Language Strings for the Class
        if ( is_array($rVal) ) {
            foreach ( $rVal as $Key=>$Value ) {
                $this->strings["$Key"] = NoNull($Value);
            }
        }
    }

    /**
     *  Function Collects the Account-Type-specific Navigation Bar for the Site
     */
    private function _getSiteNav( $data, $type = 'main' ) {
        $ThemeLocation = THEME_DIR . '/' . $data['location'];
        $html = '';

        /* If we're not signed in, there cannot be an account-specific navigation bar */
        if ( $this->settings['_logged_in'] !== true ) { return $html; }

        // Is there a custom.php file in the theme that will provide the requisite data?
        $ThemeLocation = THEME_DIR . '/' . $this->settings['_theme'];
        if ( file_exists("$ThemeLocation/custom.php") ) {
            if ( $this->custom === false ) {
                require_once("$ThemeLocation/custom.php");
                $ClassName = ucfirst($this->settings['_theme']);
                $this->custom = new $ClassName( $this->settings, $this->strings );
            }
            if ( method_exists($this->custom, 'getSiteNav') ) {
                $this->settings['errors'] = $this->custom->getResponseMeta();
                $this->settings['status'] = $this->custom->getResponseCode();
                return $this->custom->getSiteNav($data);
            }
        }

        // Return the Completed HTML if it Exists
        return $html;
    }

    private function _getPageMetadataArray( $data ) {
        $HomeUrl = NoNull($data['protocol'] . '://' . $data['HomeURL']);
        $SiteUrl = $HomeUrl . '/themes/' . $data['location'];
        $HomeUrl = NoNull($data['protocol'] . '://' . $data['HomeURL']);
        $PgRoot = strtolower(NoNull($this->settings['PgRoot']));
        $ApiUrl = getApiUrl();
        $CdnUrl = getCdnUrl();

        // Get the Banner (if one exists)
        $banner_img = NoNull($data['banner_img']);
        if ( NoNull($banner_img) == '' ) { $banner_img = NoNull($HomeUrl . '/images/social_banner.png'); }

        /* Set the Welcome Line */
        $welcomeLine = str_replace('{display_name}', $this->settings['_display_name'], $this->strings['welcomeLine']);

        // Construct the Core Array
        $rVal = array( '[FONT_DIR]'     => $SiteUrl . '/fonts',
                       '[CSS_DIR]'      => $SiteUrl . '/css',
                       '[IMG_DIR]'      => $SiteUrl . '/img',
                       '[JS_DIR]'       => $SiteUrl . '/js',
                       '[HOMEURL]'      => NoNull($this->settings['HomeURL']),
                       '[API_URL]'      => NoNull($data['api_url'], $ApiUrl),
                       '[CDN_URL]'      => NoNull($data['cdn_url'], $CdnUrl),

                       '[CSS_VER]'      => getMetaVersion(),
                       '[GENERATOR]'    => GENERATOR . " (" . APP_VER . ")",
                       '[APP_NAME]'     => APP_NAME,
                       '[APP_VER]'      => APP_VER,
                       '[LANG_CD]'      => NoNull($this->settings['_language_code'], $this->settings['DispLang']),
                       '[ACCOUNT_TYPE]' => NoNull($this->settings['_account_type'], 'account.guest'),
                       '[AVATAR_URL]'   => NoNull($this->settings['HomeURL']) . '/avatars/' . $this->settings['_avatar_file'],
                       '[WELCOME_LINE]' => NoNull($welcomeLine),
                       '[DISPLAY_NAME]' => NoNull($this->settings['_display_name'], $this->settings['_first_name']),
                       '[UPDATED_AT]'   => NoNull($data['updated_at']),
                       '[PGSUB_1]'      => NoNull($this->settings['PgSub1']),
                       '[YEAR]'         => date('Y'),

                       '[TOKEN]'        => ((YNBool($this->settings['_logged_in'])) ? NoNull($this->settings['token']) : ''),

                       '[SITE_URL]'     => $this->settings['HomeURL'],
                       '[SITE_NAME]'    => $data['name'],
                       '[SITEDESCR]'    => $data['description'],
                       '[SITEKEYWD]'    => $data['keywords'],
                       '[SITE_COLOR]'   => NoNull($data['color'], 'auto'),

                       '[PAGE_URL]'     => $this->_getPageUrl(),
                       '[PAGE_CSS]'     => $this->_getPageCSS($data),
                       '[PAGE_TITLE]'   => $this->_getPageTitle($data),

                       '[META_TITLE]'   => $this->_getPageTitle($data, true),
                       '[META_DOMAIN]'  => NoNull($data['HomeURL']),
                       '[META_TYPE]'    => NoNull($data['page_type'], 'website'),
                       '[META_DESCR]'   => NoNull($data['description']),
                       '[CC-LICENSE]'   => $this->_getCCLicense(NoNull($data['license'], 'CC BY-NC-ND')),
                       '[BANNER_IMG]'   => $banner_img,

                       '[FONT_SIZE]'    => NoNull($data['font-size'], 'md'),
                      );

        // Return the Strings
        return $rVal;
    }

    /**
     *  Function returns a constructed Creative Commons license statement for the footer of a page
     */
    private function _getCCLicense( $license ) {
        $idx = array( '0'     => array( 'icon' => 'zero',  'text' => 'No Rights Reserved' ),
                      'by'    => array( 'icon' => 'by',    'text' => 'Attribution' ),
                      'nc'    => array( 'icon' => 'nc',    'text' => 'NonCommercial' ),
                      'nd'    => array( 'icon' => 'nd',    'text' => 'NoDerivatives' ),
                      'pd'    => array( 'icon' => 'pd',    'text' => 'PublicDomain' ),
                      'sa'    => array( 'icon' => 'sa',    'text' => 'ShareAlike' ),
                      'remix' => array( 'icon' => 'remix', 'text' => 'Remix' ),
                      'share' => array( 'icon' => 'share', 'text' => 'Share' ),
                     );
        $valids = array('CC0', 'CC BY', 'CC BY-SA', 'CC BY-ND', 'CC BY-NC', 'CC BY-NC-SA', 'CC BY-NC-ND');
        if ( in_array(strtoupper($license), $valids) === false ) {
            $license = 'CC BY-NC-ND';
        }

        $type = strtolower(NoNull(str_replace(array('CC', '4.0'), '', $license)));
        $icon = '<i class="fab fa-creative-commons"></i> ';
        $desc = '';

        $els = explode('-', $type);
        foreach ( $els as $el ) {
            $icon .= '<i class="fab fa-creative-commons-' . $idx[strtolower($el)]['icon'] . '"></i> ';
            if ( $desc != '' ) { $desc .= '-'; }
            $desc .= NoNull($idx[strtolower($el)]['text']);
        }

        // Return the License String
        return $icon . 'This work is licensed under a <a rel="license" href="http://creativecommons.org/licenses/' . $type . '/4.0/">Creative Commons ' . NoNull($desc) . ' 4.0 International License</a>.';
    }

    /**
     *  Function Checks the Array and Returns an HTML Value based on what it sees
     */
    private function _checkboxValue($data, $item) {
        $enabled = YNBool(BoolYN($data[$item]));

        if ( $enabled ) { return ' checked'; }
        return '';
    }

    private function _selectValue($data, $item, $val) {
        $value = NoNull($data[$item]);

        if ( strtolower($value) == strtolower($val) ) { return ' selected'; }
        return '';
    }

    /**
     *  Function Collects the Necessary Page Contents
     */
    private function _getContentPage( $data ) {
        $ResDIR = THEME_DIR . "/" . NoNull($data['location'], getRandomString(6));
        if ( file_exists("$ResDIR/base.html") === false ) { $data['location'] = 'error'; }
        $valids = array('forgot', 'rights', 'terms', 'tos');
        $pgName = NoNull($this->settings['PgRoot'], 'main');

        /* If we're not signed in and not visiting an exception page, show the login form */
        if ( $this->settings['_logged_in'] !== true && in_array(strtolower($this->settings['PgRoot']), $valids) === false ) {
            $pgName = 'login';
        }

        $ResDIR = THEME_DIR . "/" . $data['location'] . "/resources/";
        $rVal = 'page-' . NoNull($pgName, '404') . '.html';
        if ( file_exists($ResDIR . $rVal) === false ) { $rVal = 'page-404.html'; }

        if ( $rVal == 'page-404.html' ) { $this->settings['status'] = 404; }
        if ( $rVal == 'page-403.html' ) { $this->settings['status'] = 403; }

        // Return the Necessary Page
        return $ResDIR . $rVal;
    }

    /**
     *  Function Returns the Page Title
     */
    private function _getPageTitle( $data, $isMeta = false ) {
        $lblDefault = '';
        $lblName = 'page' . ucfirst(NoNull($this->settings['PgRoot'], $lblDefault));

        if ( $isMeta ) {
            $rslt = NoNull($this->strings[$lblName], NoNull($data['page_title'], $data['name']));
            return htmlspecialchars(strip_tags($rslt), ENT_QUOTES, 'UTF-8');
        } else {
            return NoNull($this->strings[$lblName], NoNull($data['page_title'], $data['name']));
        }
    }

    /**
     *  Function Determines the Current Page URL
     */
    private function _getPageURL() {
        $rVal = $this->settings['HomeURL'];

        if ( NoNull($this->settings['PgRoot']) != '' ) { $rVal .= '/' . NoNull($this->settings['PgRoot']); }
        for ( $i = 1; $i <= 9; $i++ ) {
            if ( NoNull($this->settings['PgSub' . $i]) != '' ) {
                $rVal .= '/' . NoNull($this->settings['PgSub' . $i]);
            } else {
                return $rVal;
            }
        }

        // Return the Current URL
        return $rVal;
    }

    /**
     *  Function Determines if there is a page-specific CSS file that needs to be returned or not
     */
    private function _getPageCSS( $data ) {
        $cssFile = strtolower(NoNull($this->settings['PgRoot']));
        if ( $this->settings['_logged_in'] !== true ) { $cssFile = 'login'; }
        $cssFile .= '.css';

        $CssDIR = THEME_DIR . "/" . $data['location'] . "/css/";
        if ( file_exists($CssDIR . $cssFile) ) {
            $cssUrl = $this->settings['HomeURL'] . '/themes/' . NoNull($data['location'], 'admin') . '/css/';
            $cssVer = getMetaVersion();
            return "\r\n" . tabSpace(2) .
                   '<link rel="stylesheet" type="text/css" href="' . $cssUrl . $cssFile . '?ver=' . $cssVer . '" />';
        }

        return '';
    }

    /**
     *  Function Constructs and Returns the Language String Replacement Array
     */
    private function _getReplStrArray() {
        $rVal = array( '[SITEURL]' => NoNull($this->settings['HomeURL']),
                       '[RUNTIME]' => getRunTime('html'),
                      );
        foreach ( $this->strings as $Key=>$Val ) {
            $rVal["[$Key]"] = NoNull($Val);
        }

        // Return the Array
        return $rVal;
    }

    /** ********************************************************************** *
     *  Additional Functions
     ** ********************************************************************** */
    /**
     *  Function Collects the Agreements and Returns a Completed HTML Object if required
     */
    private function _getAgreementText( $ReplStr = array() ) {
        // Are we signed in and we need to sign a Confidentiality agreement?
        if ( $this->settings['_logged_in'] && $this->settings['_agreement_done'] !== true ) {
            $agreeDir = FLATS_DIR . '/agreements';
            $langs = array('en');

            if ( file_exists($agreeDir) ) {
                $excludes = array('base.html');
                foreach ( glob($agreeDir . "/*.html") as $file) {
                    $file = getFileName($file);
                    if ( in_array($file, $excludes) === false ) {
                        $parts = explode('_', $file);
                        $code = str_replace('.html', '', $parts[1]);
                        if ( in_array($code, $langs) === false ) { $langs[] = $code; }
                    }
                }
            }

            $blocks = '';
            $btns = '';

            foreach ( $langs as $code ) {
                $fileName = $agreeDir . '/region-' . strtolower(NoNull($this->settings['_region_code'], 'all')) . '_' . $code . '.html';
                if ( file_exists($fileName) === false ) { $fileName = $agreeDir . '/general_' . $code . '.html'; }
                $blocks .= readResource($fileName, $ReplStr);

                if ( $btns != '' ) { $btns .= "\r\n"; }
                $btns .= tabSpace(3) . '<button class="btn btn-lang-select' . ((NoNull($this->settings['_language_code'], 'en') == $code) ? ' btn-primary' : '') . '" data-value="' . $code . '">' .
                                        NoNull($this->strings['setLang' . strtoupper($code)], NoNull($this->strings['lblLang' . strtoupper($code)], $code)) .
                                       '</button>';
            }

            $ReplStr['[BLOCKS]'] = $blocks;
            $ReplStr['[LANG_SELECT]'] = $btns;
            return readResource($agreeDir . '/base.html', $ReplStr);
        }

        return '';
    }

    /**
     *  Function Determines the Current Page URL
     */
    private function _getCanonicalURL() {
        if ( NoNull($this->settings['PgRoot']) == '' ) { return ''; }

        $rVal = '/' . NoNull($this->settings['PgRoot']);
        for ( $i = 1; $i <= 9; $i++ ) {
            if ( NoNull($this->settings['PgSub' . $i]) != '' ) {
                $rVal .= '/' . NoNull($this->settings['PgSub' . $i]);
            } else {
                return $rVal;
            }
        }

        // Return the Canonical URL
        return $rVal;
    }
}
?>