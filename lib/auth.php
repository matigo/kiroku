<?php

/**
 * @author Jason F. Irwin
 *
 * Class contains the rules and methods called to Authorize Accounts and Verify Tokens
 */
require_once( LIB_DIR . '/functions.php');

class Auth {
    var $settings;
    var $cache;

    function __construct( $Items ) {
        $this->_populateClass( $Items );
    }

    /** ********************************************************************* *
     *  Population
     ** ********************************************************************* */
    /**
     *  Function Populates the Class Using a Token if Supplied
     */
    private function _populateClass( $Items = array() ) {
        $data = ( is_array($Items) ) ? $this->_getBaseArray( $Items ) : array();
        if ( !defined('PASSWORD_LIFE') ) { define('PASSWORD_LIFE', 36525); }
        if ( !defined('TOKEN_PREFIX') ) { define('TOKEN_PREFIX', 'AKIRA_'); }
        if ( !defined('TOKEN_EXPY') ) { define('TOKEN_EXPY', 120); }

        // Set the Class Array Accordingly
        $this->settings = $data;
        $this->cache = false;
        unset($data);
    }

    /**
     *  Function Returns the Basic Array Used by the Authorization Class
     */
    private function _getBaseArray( $Items ) {
        $this->settings = array( 'HomeURL' => str_replace(array('https://', 'http://'), '', $Items['HomeURL']) );
        $Name = NoNull($Items['account_name'], NoNull($Items['account-name'], $Items['acctname']));
        $Pass = NoNull($Items['account_pass'], NoNull($Items['account-pass'], $Items['acctpass']));
        $isWebReq = NoNull($Items['web_req'], NoNull($Items['web-req'], $Items['webreq']));
        $isHTTPS = ( strpos($Items['HomeURL'], 'https://') !== false ? true : false);
        $data = $this->_getTokenData($Items['token']);

        return array( 'is_valid'     => ((is_array($data)) ? $data['_logged_in'] : false),

                      'token'        => NoNull($Items['token']),
                      'account_name' => NoNull($Name),
                      'account_pass' => NoNull($Pass),
                      'theme'        => 'admin',
                      'webreq'       => NoNull($isWebReq, 'N'),

                      'HomeURL'      => str_replace(array('https://', 'http://'), '', $Items['HomeURL']),
                      'ReqType'      => $Items['ReqType'],
                      'PgRoot'       => $Items['PgRoot'],
                      'PgSub1'       => $Items['PgSub1'],
                      'https'        => $isHTTPS,
                     );
    }

    /** ********************************************************************* *
     *  Perform Action Blocks
     ** ********************************************************************* */
    public function performAction() {
        $ReqType = NoNull(strtolower($this->settings['ReqType']));
        $rVal = false;

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
            case 'status':
                return $this->_checkTokenStatus();
                break;

            default:
                // Do Nothing
        }

        // Return the Array of Data or an Unhappy String
        return $rVal;
    }

    private function _performPostAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        $rVal = false;

        switch ( $Activity ) {
            case 'login':
            case '':
                return $this->_performLogin();
                break;

            case 'signout':
            case 'logout':
                return $this->_performLogout();
                break;

            case 'reset':
                return $this->_requestPassReset();
                break;

            default:
                // Do Nothing
        }

        // Return the Array of Data or an Unhappy String
        return $rVal;
    }

    private function _performDeleteAction() {
        $Activity = strtolower(NoNull($this->settings['PgSub2'], $this->settings['PgSub1']));
        $rVal = false;

        switch ( $Activity ) {
            case '':
                return $this->_performLogout();
                break;

            default:
                // Do Nothing
        }

        // Return the Array of Data or an Unhappy Boolean
        return $rVal;
    }

    /** ********************************************************************* *
     *  Public Properties & Functions
     ** ********************************************************************* */
    public function isLoggedIn() { return BoolYN($this->settings['is_valid']); }
    public function performLogout() { return $this->_performLogout(); }
    public function getTokenData( $Token ) { return $this->_getTokenData($Token); }

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
     *  Class Functions
     ** ********************************************************************* */
    /**
     *  Function Returns Any Data That Might Be Associated With a Token
     */
    private function _getTokenData( $Token = '' ) {
        // If We Have the Data, Return It
        if ( array_key_exists('token_data', $GLOBALS) ) { return $GLOBALS['token_data']; }

        // Verifiy We Have a Token Value and Split It Accordingly
        if ( NoNull($Token) == '' ) { return false; }
        $data = explode('_', $Token);
        if ( count($data) != 3 ) { return false; }

        // Get the Maximum Age of an Account's Password (28.25 years by default)
        $PassAgeLimit = 10000;
        if ( defined('PASSWORD_LIFE') ) { $PassAgeLimit = nullInt(PASSWORD_LIFE, 10000); }

        // If the Prefix Matches, Validate the Token Data
        if ( NoNull($data[0]) == str_replace('_', '', TOKEN_PREFIX) ) {
            $ReplStr = array( '[TOKEN_ID]'     => alphaToInt($data[1]),
                              '[TOKEN_GUID]'   => sqlScrub($data[2]),
                              '[LIFESPAN]'     => nullInt(TOKEN_EXPY),
                             );
            $sqlStr = prepSQLQuery("CALL AuthTokenData([TOKEN_ID], '[TOKEN_GUID]', [LIFESPAN]);", $ReplStr);
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $reqPassChange = false;
                    $passAge = nullInt($Row['password_age']);
                    if ( $passAge > $PassAgeLimit ) { $reqPassChange = true; }

                    /* Construct the Output */
                    $rVal = array( '_account_id'    => nullInt($Row['account_id']),
                                   '_display_name'  => NoNull($Row['display_name']),
                                   '_first_name'    => NoNull($Row['first_name']),
                                   '_last_name'     => NoNull($Row['last_name']),
                                   '_email'         => NoNull($Row['login']),

                                   '_avatar_file'   => NoNull($Row['avatar'], 'default.png'),
                                   '_account_type'  => NoNull($Row['type'], 'account.unknown'),

                                   '_language_code' => NoNull($Row['language_code']),
                                   '_welcome_done'  => YNBool($Row['welcome_done']),
                                   '_theme'         => NoNull($Row['theme'], 'default'),
                                   '_timezone'      => NoNull($Row['timezone'], 'UTC'),

                                   '_fontfamily'    => NoNull($Row['pref_fontfamily'], 'auto'),
                                   '_fontsize'      => NoNull($Row['pref_fontsize'], 'auto'),
                                   '_colour'        => NoNull($Row['pref_colour'], 'auto'),

                                   '_pass_change'   => $reqPassChange,
                                   '_token_id'      => nullInt($Row['token_id']),
                                   '_token_guid'    => NoNull($Row['token_guid']),
                                   '_login_at'      => date("Y-m-d\TH:i:s\Z", strtotime($Row['login_at'])),
                                   '_login_unix'    => strtotime($Row['login_at']),
                                   '_logged_in'     => true,
                                  );
                }
            }
        }

        // Set the Cache and Return an Array of Data or an Unhappy Boolean
        $GLOBALS['token_data'] = $rVal;
        return $rVal;
    }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    /**
     *  Function Attempts to Log a User In with X-Auth (Username/Password Combination)
     *      and returns a Token or Unhappy Boolean
     */
    private function _performLogin() {
        if ( !defined('DEFAULT_LANG') ) { define('DEFAULT_LANG', 'en-us'); }
        $authMsg = '';
        $authRsp = 0;

        $AcctName = NoNull($this->settings['account_name']);
        $AcctPass = NoNull($this->settings['account_pass']);
        $isWebReq = YNBool(NoNull($this->settings['webreq']));
        $LangCd = NoNull(DEFAULT_LANG, 'en-us');
        $Token = false;

        // Ensure We Have the Data, and Check the Database
        if ( $AcctName != "" && $AcctPass != "" && $AcctName != $AcctPass ) {
            $ReplStr = array( '[USERADDR]' => sqlScrub($AcctName),
                              '[USERPASS]' => sqlScrub($AcctPass),
                              '[SHA_SALT]' => sqlScrub(SHA_SALT),
                              '[LIFESPAN]' => nullInt(TOKEN_EXPY),
                             );
            $sqlStr = prepSQLQuery( "CALL AuthLogin('[USERADDR]', '[USERPASS]', '[SHA_SALT]', [LIFESPAN]);", $ReplStr );
            $rslt = doSQLQuery($sqlStr);
            if ( is_array($rslt) ) {
                foreach ( $rslt as $Row ) {
                    $Token = TOKEN_PREFIX . intToAlpha($Row['token_id']) . '_' . NoNull($Row['token_guid']);
                    $LangCd = NoNull($Row['language_code']);
                }
            }
        }

        // Is this a Web Request? If So, Treat It As Such
        if ( $isWebReq ) {
            $url = (($this->settings['https']) ? 'https' : 'http') . '://' . $this->settings['HomeURL'];

            if ( is_string($Token) ) {
                $url .= "/validatetoken?token=$Token";
            } else {
                $url .= "/nodice";
            }
            redirectTo($url, $this->settings);
            return false;

        } else {
            /* API Response */
            if ( is_string($Token) ) {
                return array( 'token'   => $Token,
                              'lang_cd' => strtolower($LangCd),
                             );
            } else {
                $this->_setMetaMessage(NoNull($authMsg, "Unrecognised Credentials"), nullInt($authRsp, 401));
                return array();
            }
        }
    }

    /**
     *  Function Marks a Token Record as isDeleted = 'Y'
     */
    private function _performLogout() {
        $Token = NoNull($this->settings['token']);
        $rVal = false;
        if ( $Token != '' ) {
            $data = explode('_', $Token);
            if ( $data[0] == str_replace('_', '', TOKEN_PREFIX) ) {
                $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                                  '[TOKEN_ID]'   => alphaToInt($data[1]),
                                  '[TOKEN_GUID]' => sqlScrub($data[2]),
                                 );
                $sqlStr = prepSQLQuery( "CALL AuthLogout([TOKEN_ID], '[TOKEN_GUID]');", $ReplStr );
                $rslt = doSQLQuery($sqlStr);
                if ( is_array($rslt) ) {
                    foreach ( $rslt as $Row ) {
                        return array( 'account'      => false,
                                      'is_active'    => false,
                                      'updated_at'   => date("Y-m-d\TH:i:s\Z", strtotime($Row['updated_at'])),
                                      'updated_unix' => strtotime($Row['updated_at']),
                                     );
                    }
                }
            }
        }

        // Return the Reponse or an Unhappy Array
        if ( is_array($rVal) ) {
            return $rVal;

        } else {
            $this->_setMetaMessage("Unrecognised Token Reference", 400);
            return array();
        }
    }

    private function _checkTokenStatus() {
        /* If we have data, the token is valid, so let's return it */
        if ( array_key_exists('token_data', $GLOBALS) ) {
            return array( 'account_id'    => nullInt($GLOBALS['token_data']['_account_id']),
                          'type'          => NoNull($GLOBALS['token_data']['_account_type']),
                          'display_name'  => NoNull($GLOBALS['token_data']['_display_name']),
                          'language_code' => NoNull($GLOBALS['token_data']['_language_code']),

                          'logged_in'     => YNBool($GLOBALS['token_data']['_logged_in']),
                          'login_at'      => NoNull($GLOBALS['token_data']['_login_at']),
                          'login_unix'    => nullInt($GLOBALS['token_data']['_login_unix']),
                         );
        }

        // If We're Here, the Token is Invalid (or Expired)
        $this->_setMetaMessage("Invalid or Expired Token Supplied", 400);
        return array();
    }

    /**
     *  Record a Password Reset Request
     *
     *  NOTE: Generally there will not be any sort of message back to the front end if an
     *        email address or login name is correct or incorrect. Only if everything fits
     *        will a message be displayed. This is to reduce the chance that a script
     *        might run against the API to find valid emails ... not that an address is
     *        ever returned.
     */
    private function _requestPassReset() {
        $AcctKey = NoNull($this->settings['account_key'], $this->settings['acct-key']);

        /* Ensure We Have a Minimum Amount of Criteria */
        if ( mb_strlen($AcctKey) <= 3 ) {
            $this->_setMetaMessage("Please Enter a Valid Account ID", 400);
            return array();
        }

        /* Record the Request and Return some data if the request is valid */
        $ReplStr = array( '[ACCOUNT_KEY]' => sqlScrub($AcctKey) );
        $sqlStr = prepSQLQuery( "CALL GetPassResetDetails('[ACCOUNT_KEY]');", $ReplStr );
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $DispLang = DEFAULT_LANG;
            $DispName = '';
            $AcctGuid = '';
            $ResetKey = '';
            $SiteUrl = '';
            $MailTo = '';

            foreach ( $rslt as $Row ) {
                $mailaddr = NoNull($Row['email']);
                if ( validateEmail($mailaddr) ) {
                    $DispName = NoNull($Row['display_name'], $Row['first_name']);
                    $DispLang = NoNull($Row['language_code'], DEFAULT_LANG);
                    $AcctGuid = NoNull($Row['account_guid']);
                    $ResetKey = NoNull($Row['reset_key']);
                    $SiteUrl = Nonull($Row['site_url']);
                    $MailTo = NoNull($Row['email']);
                }
            }

            /* If We Have Valid Data, Build and Send an Email */
            if ( $ResetKey != '' && $MailTo != '' ) {
                $ReplStr = array( '[APP_NAME]' => NoNull(APP_NAME, 'Kiroku'),
                                  '[SUBJECT]'  => 'Forgot Your Password? No Problem!',

                                  '[ACCTGUID]' => NoNull($AcctGuid),
                                  '[RESETKEY]' => NoNull($ResetKey),
                                  '[SITEURL]'  => NoNull($SiteUrl),
                                  '[NAME]'     => NoNull($DispName),
                                 );
                if ( !file_exists(FLATS_DIR . "/templates/email.forgot_$DispLang.html") ) { $DispLang = DEFAULT_LANG; }
                if ( file_exists(FLATS_DIR . "/templates/email.forgot_$DispLang.html") ) {
                    $msgHtml = readResource(FLATS_DIR . "/templates/email.forgot_$DispLang.html", $ReplStr);
                    $msgText = readResource(FLATS_DIR . "/templates/email.forgot_$DispLang.txt", $ReplStr);

                    // Construct the Array for the Email Function
                    $data = array( 'from_name' => NoNull(APP_NAME, 'Kiroku'),
                                   'subject'   => 'Forgot Your Password? No Problem!',
                                   'send_to'   => $MailTo,
                                   'html'      => $msgHtml,
                                   'text'      => $msgText,
                                  );

                    require_once(LIB_DIR . '/email.php');
                    $mail = new Email($this->settings);
                    $isOK = $mail->sendMail($data);
                    if ( $mail->getResponseCode() != 200 ) {
                        $code = $mail->getResponseCode();
                        $mmsg = $mail->getResponseMeta();
                        foreach ( $mmsg as $msg ) {
                            $this->_setMetaMessage($msg, $code);
                        }
                    }
                    unset($mail);

                    // Return the Person's Name
                    return array( 'name' => NoNull($DispName) );
                }
            }
        }

        // Regardless of whether a message was sent or not, return nothing
        return array();
    }

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