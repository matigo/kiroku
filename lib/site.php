<?php

/**
 * @author Jason F. Irwin
 * @copyright 2015
 *
 * Class contains the rules and methods called for Site Settings & Creation
 */
require_once( LIB_DIR . '/functions.php');

class Site {
    var $settings;
    var $cache;

    function __construct( $Items ) {
        $this->settings = $Items;
        $this->cache = array();
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = NoNull(strtolower($this->settings['ReqType']));
        $rVal = false;

        // Check the User Token is Valid
        if ( !$this->settings['_logged_in']) { return "You Need to Log In First"; }

        // Perform the Action
        switch ( $ReqType ) {
            case 'get':
                $rVal = $this->_performGetAction();
                break;

            case 'post':
            case 'put':
                $rVal = $this->_performPostAction();
                break;

            case 'delete':
                $rVal = $this->_performDeleteAction();
                break;

            default:
                // Do Nothing
        }

        // Return The Array of Data or an Unhappy Boolean
        return $rVal;
    }

    private function _performGetAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        $rVal = false;

        switch ( $Activity ) {
            case 'info':
                $rVal = array();
                break;

            default:
                // Do Nothing
        }

        // Return the Array of Data or an Unhappy Boolean
        return $rVal;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        $rVal = false;

        switch ( $Activity ) {
            case 'set':
            case '':
                return $this->_setSiteData();
                break;

            default:
                // Do Nothing
        }

        // Return the Array of Data or an Unhappy Boolean
        return $rVal;
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        $rVal = false;

        switch ( $Activity ) {
            case '':
                $rVal = array( 'activity' => "[DELETE] /site/$Activity" );
                break;

            default:
                // Do Nothing
        }

        // Return the Array of Data or an Unhappy Boolean
        return $rVal;
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

    /** ********************************************************************* *
     *  Public Functions
     ** ********************************************************************* */
    public function getSiteData() { return $this->_getSiteData(); }
    public function getCacheFolder() { return $this->_getCacheFolder(); }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    /**
     *  Function Returns the Cache Folder for a Given Channel
     */
    private function _getCacheFolder() {
        $SiteID = nullInt($this->settings['site_id']);
        $rVal = false;

        if ( $SiteID <= 0 ) {
            $SiteURL = sqlScrub( NoNull($this->settings['site_url'],$_SERVER['SERVER_NAME']) );
            $ReplStr = array( '[SITE_URL]' => strtolower($SiteURL) );
            $sqlStr = readResource(SQL_DIR . '/site/getCacheFolder.sql', $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $this->settings['channel_id'] = nullInt($Row['channel_id']);
                    $this->settings['site_id'] = nullInt($Row['site_id']);
                }
            }
        }

        // Construct the Cache Folder Name
        if ( nullInt($this->settings['site_id']) > 0 ) {
            $rVal = intToAlpha($this->settings['site_id']);
        }

        // Return the Cache Folder or an Unhappy Boolean
        return $rVal;
    }

    /**
     *  Function Collects the Site Data and Returns an Array
     */
    private function _getSiteData() {
        $SitePass = NoNull($this->settings['site_pass'], $this->settings['site-pass']);
        $SiteURL = sqlScrub( NoNull($this->settings['site_url'],$_SERVER['SERVER_NAME']) );
        $cdnUrl = getCdnUrl();
        if ( is_array($this->cache[strtolower($SiteURL)]) ) { return $this->cache[strtolower($SiteURL)]; }

        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[SITE_TOKEN]' => sqlScrub(mb_substr(NoNull($this->settings['site_token']), 0, 256)),
                          '[SITE_PASS]'  => sqlScrub(mb_substr($SitePass, 0, 512)),
                          '[SITE_URL]'   => strtolower($SiteURL),
                          '[REQ_URI]'    => sqlScrub(mb_substr(NoNull($this->settings['ReqURI'], '/'), 0, 512)),
                         );
        $sqlStr = prepSQLQuery("CALL SiteGetData( '[SITE_URL]' );", $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                if ( NoNull($Row['site_token']) != '' && $SitePass != '' ) {
                    $LifeSpan = time() + COOKIE_EXPY;
                    setcookie( 'site_token', NoNull($Row['site_token']), $LifeSpan, "/", NoNull(strtolower($_SERVER['SERVER_NAME'])) );
                }

                $this->cache[strtolower($SiteURL)] = array( 'HomeURL'         => NoNull($Row['site_url']),
                                                            'api_url'         => getApiUrl(),
                                                            'cdn_url'         => getCdnUrl(),

                                                            'name'            => NoNull($Row['site_name']),
                                                            'description'     => NoNull($Row['description']),
                                                            'keywords'        => NoNull($Row['keywords']),
                                                            'summary'         => NoNull($Row['summary']),
                                                            'location'        => NoNull($Row['theme']),

                                                            'license'         => NoNull($Row['license'], 'CC BY-NC-ND'),
                                                            'is_default'      => YNBool($Row['is_default']),

                                                            'site_id'         => nullInt($Row['site_id']),
                                                            'site_guid'       => NoNull($Row['site_guid']),
                                                            'site_icon'       => NoNull($Row['site_icon']),
                                                            'site_banner'     => NoNull($Row['site_banner']),
                                                            'site_version'    => NoNull($Row['version']),
                                                            'updated_at'      => date("Y-m-d\TH:i:s\Z", strtotime($Row['updated_at'])),
                                                            'updated_unix'    => strtotime($Row['updated_at']),

                                                            'page_title'      => NoNull($Row['page_title']),

                                                            'color'           => NoNull($Row['site_color'], 'auto'),
                                                            'font-family'     => NoNull($Row['font_family']),
                                                            'font-size'       => NoNull($Row['font_size']),

                                                            'protocol'        => (YNBool($Row['https'])) ? 'https' : 'http',
                                                            'https'           => YNBool($Row['https']),
                                                            'do_redirect'     => YNBool($Row['do_redirect']),
                                                           );
            }
        }

        // Return the Site Data
        return $this->cache[strtolower($SiteURL)];
    }

    /**
     *  Function Records Site, SiteMeta, and Channel Data. Then Returns the getSiteDataByID Array
     */
    private function _setSiteData() {
        // Perform Some Basic Error Checking
        if ( NoNull($this->settings['channel_guid'], $this->settings['channel-guid']) == '' ) { $this->_setMetaMessage("Invalid Channel GUID Supplied", 400); }
        if ( NoNull($this->settings['site_name'], $this->settings['site-name']) == '' ) { $this->_setMetaMessage("Invalid Site Name Supplied", 400); }
        $isWebReq = YNBool(NoNull($this->settings['web-req'], $this->settings['webreq']));

        $Visibility = 'visibility.public';
        $SitePass = '';
        if ( YNBool(NoNull($this->settings['site_locked'], $this->settings['site-locked'])) ) {
            $Visibility = 'visibility.password';

            $SitePass = NoNull($this->settings['site_pass'], $this->settings['site-pass']);
            if ( mb_strlen($SitePass) <= 6 ) {
                $this->_setMetaMessage("Supplied Site Password is Far Too Weak", 400);
                return false;
            }
            if ( $SitePass == str_repeat('*', 12) ) { $SitePass = ''; }
        }

        // Determine if the Theme is valid
        $validThemes = array( 'anri', 'resume', 'default', 'gtd' );
        $siteTheme = strtolower(NoNull($this->settings['site_theme'], $this->settings['site-theme']));
        if ( in_array($siteTheme, $validThemes) === false ) {
            $siteTheme = 'anri';
        }

        // Determine if the Font Family is valid
        $validFontFam = array( 'font-family.auto', 'font-family.lato', 'font-family.librebaskerville', 'font-family.open-sans', 'font-family.ubuntu', 'font-family.quicksand' );
        $fontFamily = strtolower(NoNull($this->settings['font_family'], $this->settings['font-family']));
        if ( in_array($fontFamily, $validFontFam) === false ) {
            $fontFamily = 'font-family.auto';
        }
        $fontFamily = str_replace('font-family.', '', $fontFamily);

        // Determine if the Font Size is valid
        $validFontSize = array( 'font-size.xs', 'font-size.sm', 'font-size.md', 'font-size.lg', 'font-size.xl', 'font-size.xx' );
        $fontSize = strtolower(NoNull($this->settings['font_size'], $this->settings['font-size']));
        if ( in_array($fontSize, $validFontSize) === false ) {
            $fontSize = 'font-size.md';
        }
        $fontSize = str_replace('font-size.', '', $fontSize);

        // Determine if the Dark theme is enabled, disabled, or auto
        $validColour = array( 'theme.auto', 'theme.dark', 'theme.light' );
        $ColourTheme = strtolower(NoNull($this->settings['site_color'], $this->settings['site-color']));
        if ( in_array($ColourTheme, $validColour) === false ) {
            $ColourTheme = 'theme.auto';
        }
        $ColourTheme = str_replace('theme.', '', $ColourTheme);

        // Get a Site.ID Value
        $ReplStr = array( '[CHANNEL_GUID]' => sqlScrub(NoNull($this->settings['channel_guid'], $this->settings['channel-guid'])),
                          '[ACCOUNT_ID]'   => nullInt($this->settings['_account_id']),
                          '[SITE_NAME]'    => sqlScrub(NoNull($this->settings['site_name'], $this->settings['site-name'])),
                          '[SITE_DESCR]'   => sqlScrub(NoNull($this->settings['site_descr'], $this->settings['site-descr'])),
                          '[SITE_KEYS]'    => sqlScrub(NoNull($this->settings['site_keys'], $this->settings['site-keys'])),
                          '[SITE_THEME]'   => sqlScrub($siteTheme),
                          '[SITE_COLOR]'   => sqlScrub($ColourTheme),
                          '[SITE_FFAMILY]' => sqlScrub($fontFamily),
                          '[SITE_FSIZE]'   => sqlScrub($fontSize),
                          '[PRIVACY]'      => sqlScrub($Visibility),
                          '[SITE_PASS]'    => sqlScrub($SitePass),

                          '[SHOW_GEO]'     => BoolYN(YNBool(NoNull($this->settings['show_geo'], $this->settings['show-geo']))),
                          '[SHOW_NOTE]'    => BoolYN(YNBool(NoNull($this->settings['show_note'], $this->settings['show-note']))),
                          '[SHOW_BLOG]'    => BoolYN(YNBool(NoNull($this->settings['show_article'], $this->settings['show-article']))),
                          '[SHOW_BKMK]'    => BoolYN(YNBool(NoNull($this->settings['show_bookmark'], $this->settings['show-bookmark']))),
                          '[SHOW_LOCS]'    => BoolYN(YNBool(NoNull($this->settings['show_location'], $this->settings['show-location']))),
                          '[SHOW_QUOT]'    => BoolYN(YNBool(NoNull($this->settings['show_quotation'], $this->settings['show-quotation']))),
                          '[SHOW_PHOT]'    => BoolYN(YNBool(NoNull($this->settings['show_photo'], $this->settings['show-photo']))),
                         );
        $sqlStr = prepSQLQuery("CALL SetSiteData( [ACCOUNT_ID], '[CHANNEL_GUID]',
                                                 '[SITE_NAME]', '[SITE_DESCR]', '[SITE_KEYS]', '[SITE_THEME]', '[SITE_COLOR]', '[SITE_FFAMILY]', '[SITE_FSIZE]',
                                                 '[PRIVACY]', '[SITE_PASS]',
                                                 '[SHOW_GEO]', '[SHOW_NOTE]', '[SHOW_BLOG]', '[SHOW_BKMK]', '[SHOW_LOCS]', '[SHOW_QUOT]', '[SHOW_PHOT]');", $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                if ( nullInt($Row['version_id']) <= 0 ) {
                    if ( $isWebReq ) { redirectTo($this->settings['HomeURL'] . '/403', $this->settings); }
                }
            }
        }

        // If This is a Web Request, Redirect the Visitor
        if ( $isWebReq ) { redirectTo($this->settings['HomeURL'], $this->settings); }

        // Get the Updated Information
        $rVal = $this->_getSiteData();

        // Return the Information
        return $rVal;
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