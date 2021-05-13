<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called for the Author site template
 */
require_once(LIB_DIR . '/functions.php');

class Author {
    var $settings;
    var $strings;

    function __construct( $settings, $strings = false ) {
        if ( defined('DEFAULT_LANG') === false ) { define('DEFAULT_LANG', 'en-us'); }

        $this->settings = $settings;
        $this->strings = ((is_array($strings)) ? $strings : getLangDefaults($this->settings['_language_code']));
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
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

    /**
     *  Function Returns Whether the Dataset May Have More Information or Not
     */
    public function getHasMore() {
        return BoolYN($this->settings['has_more']);
    }

    /** ********************************************************************* *
     *  Public Functions
     ** ********************************************************************* */
    public function getPageHTML( $data ) { return $this->_getPageHTML($data); }
    public function getSiteNav( $data ) { return ''; }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    private function _getPageHTML( $data ) {
        // Construct the String Replacement Array
        $HomeUrl = NoNull($this->settings['HomeURL']);
        $Theme = NoNull($data['location'], 'templates');

        /* Set the Welcome Line */
        $welcomeLine = str_replace('{display_name}', $this->settings['_display_name'], $this->strings['welcomeLine']);

        /* Determine the Body Classes */
        $BodyClass = ' font-' . NoNull($this->settings['_fontfamily'], 'auto') .
                     ' font-' . NoNull($this->settings['_fontsize'], 'md') .
                     ' theme-' . NoNull($this->settings['_colour'], 'auto');

        /* Construct the Primary Return Array */
        $ReplStr = array( '[FONT_DIR]'        => $HomeUrl . "/themes/$Theme/fonts",
                          '[CSS_DIR]'         => $HomeUrl . "/themes/$Theme/css",
                          '[IMG_DIR]'         => $HomeUrl . "/themes/$Theme/img",
                          '[JS_DIR]'          => $HomeUrl . "/themes/$Theme/js",
                          '[HOMEURL]'         => $HomeUrl,

                          '[CSS_VER]'         => CSS_VER,
                          '[GENERATOR]'       => GENERATOR . " (" . APP_VER . ")",
                          '[APP_NAME]'        => APP_NAME,
                          '[APP_VER]'         => APP_VER,
                          '[LANG_CD]'         => NoNull($this->settings['_language_code'], $this->settings['DispLang']),
                          '[PGSUB_1]'         => NoNull($this->settings['PgSub1']),

                          '[SITE_URL]'        => $this->settings['HomeURL'],
                          '[SITE_NAME]'       => $data['name'],
                          '[SITEDESCR]'       => $data['description'],
                          '[SITEKEYWD]'       => $data['keywords'],

                          '[ACCOUNT_TYPE]'    => NoNull($this->settings['_account_type'], 'account.guest'),
                          '[AVATAR_URL]'      => NoNull($this->settings['HomeURL']) . '/avatars/' . $this->settings['_avatar_file'],
                          '[WELCOME_LINE]'    => NoNull($welcomeLine),
                          '[DISPLAY_NAME]'    => NoNull($this->settings['_display_name'], $this->settings['_first_name']),
                          '[FIRST_NAME]'      => NoNull($this->settings['_first_name']),
                          '[LAST_NAME]'       => NoNull($this->settings['_last_name']),
                          '[MAIL_ADDR]'       => NoNull($this->settings['_email']),
                          '[TIMEZONE]'        => NoNull($this->settings['_timezone'], 'UTC'),
                          '[LANGUAGE]'        => NoNull($this->settings['_language_code'], DEFAULT_LANG),

                          '[BODY_CLASSLIST]'  => NoNull($BodyClass),
                          '[PREF_COLOUR]'     => NoNull($this->settings['_colour'], 'auto'),
                          '[PREF_FONTFAMILY]' => NoNull($this->settings['_fontfamily'], 'auto'),
                          '[PREF_FONTSIZE]'   => NoNull($this->settings['_fontsize'], 'md'),
                         );

        /* Add the Language Strings */
        foreach ( $this->strings as $Key=>$Value ) {
            $ReplStr["[$Key]"] = NoNull($Value);
        }

        /* Add any Sections that might be needed */
        $sections = array('header');
        foreach ( $sections as $section ) {
            $Key = 'SECTION_' . strtoupper($section);
            $ReplStr["[$Key]"] = $this->_getPageSection($section, $ReplStr);
        }

        /* Is there a Function-specific HTML page to read? */
        $PgRoot = NoNull($this->settings['PgRoot']);
        if ( $PgRoot != '' ) {
            $ClsFile = LIB_DIR . '/' . $PgRoot . '.php';

            if ( file_exists($ClsFile) ) {
                require_once($ClsFile);
                $ClassName = ucfirst($PgRoot);
                $rsp = new $ClassName( $this->settings, $this->strings );
                if ( method_exists($rsp, 'getHTMLContent') ) {
                    $this->settings['errors'] = $rsp->getResponseMeta();
                    $this->settings['status'] = $rsp->getResponseCode();
                    $ReplStr['[CONTENT]'] = $rsp->getHTMLContent();
                }
                unset($rsp);
            }
        }

        /* Return the Completed HTML */
        $ResFile = $this->_getResourceFile();
        return readResource($ResFile, $ReplStr);
    }

    /**
     *  Function determines which resource file to return
     */
    private function _getResourceFile() {
        $publics = array('forgot', 'signin', 'login', '400', '401', '403', '420', '427', '');
        $rewrite = array( 'signin' => 'login' );
        $ResDIR = __DIR__ . '/resources';
        $PgRoot = strtolower(NoNull($this->settings['PgRoot'], 'main'));

        /* If the page requested has a different file name, "rewrite" the PgRoot value */
        if ( array_key_exists($PgRoot, $rewrite) ) {
            $PgRoot = strtolower(NoNull($rewrite[$PgRoot], 'error'));
        }

        // Which Page Should be Returned?
        $ReqPage = 'page-' . $PgRoot . '.html';

        /* If the account is not signed in, kick them over to an "Access Denied" page */
        if ( in_array(NoNull($this->settings['PgRoot']), $publics) === false && $this->settings['_logged_in'] !== true ) {
            $this->settings['status'] = 403;
            $ReqPage = 'page-403.html';
        }

        /* If we are signed in and going back to the login page, redirect to the main landing page */
        if ( $this->settings['_logged_in'] === true && NoNull($this->settings['PgRoot']) == 'login' ) {
            redirectTo($this->settings['HomeURL'], $this->settings);
        }

        /* Confirm the Page Exists and Return the proper Resource path */
        if ( file_exists("$ResDIR/$ReqPage") === false ) {
            $this->settings['status'] = 404;
            $ReqPage = 'page-404.html';
        }

        if ( $this->settings['_logged_in'] !== true && $ReqPage != 'page-403.html' ) { $ReqPage = 'page-login.html'; }
        return "$ResDIR/$ReqPage";
    }

    /** ********************************************************************* *
     *  Navigation and Section Rendering Functions
     ** ********************************************************************* */
    private function _getPageSection( $section, $ReplStr = array() ) {
        if ( $this->settings['_logged_in'] ) {
            if ( is_array($ReplStr) === false ) { $ReplStr = array(); }
            $ResDIR = __DIR__ . '/resources';
            $ResFile = 'section-' . NoNull($section) . '.html';
            if ( file_exists("$ResDIR/$ResFile") ) { return readResource("$ResDIR/$ResFile", $ReplStr); }
        }
        return '';
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
    }
}
?>