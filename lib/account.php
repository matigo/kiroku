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
        $ReqType = NoNull(strtolower($this->settings['ReqType']));

        // Perform the Action
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

        switch ( $Activity ) {
            case 'avatar-list';
            case 'avatars':
                return $this->_getAvatarList();
                break;

            case 'preferences':
            case 'preference':
            case 'prefs':
                return $this->_getPreference();
                break;

            case 'timezone-list':
            case 'timezones':
                return $this->_getTimezoneList();
                break;

            case 'profile':
            case 'bio':
                return $this->_getPublicProfile();
                break;

            case 'me':
                if ( !$this->settings['_logged_in']) { return "You Need to Log In First"; }
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
        if ( !$this->settings['_logged_in'] ) { return "You Need to Log In First"; }

        switch ( $Activity ) {
            case 'forgot':
                return $this->_forgotPassword();
                break;

            case 'preference':
            case 'profile':
                return $this->_setMetaRecord();
                break;

            case 'set':
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
        if ( !$this->settings['_logged_in']) { return "You Need to Log In First"; }

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
    public function getPreference($Key) {
        $data = $this->_getPreference($Key);
        return $data['value'];
    }
    public function validateAccount( $data, $acctType = 'account.student' ) { return $this->_validateAccount( $data, $acctType ); }

    /** ********************************************************************* *
     *  Private Functions
     ** ********************************************************************* */
    /**
     *  Function returns a list of available avatars for a given Account
     */
    private function _getAvatarList() {
        $uniques = array( $this->settings['_avatar_file'] );
        $jsonFile = FLATS_DIR . '/resources/avatars.json';
        $SiteUrl = NoNull($this->settings['HomeURL']);
        $prefix = BASE_DIR . '/avatars/';
        $data = array();

        /* Collect the List of Avatars and, if the file exists, add it to the uniques array */
        if ( file_exists($jsonFile) ) {
            $json = json_decode(readResource($jsonFile), true);
            if ( is_array($json['icons']) ) {
                foreach ( $json['icons'] as $avatar ) {
                    if ( file_exists($prefix . $avatar) ) {
                        if ( in_array($avatar, $uniques) === false ) { $uniques[] = $avatar; }
                    }
                }
            }

            /* Now let's ensure we have a proper array of data constructed */
            foreach ( $uniques as $avatar ) {
                $selected = false;
                if ( $avatar == $this->settings['_avatar_file'] ) { $selected = true; }

                $data[] = array( 'name' => $avatar,
                                 'url'  => $SiteUrl . '/avatars/' . $avatar,
                                 'size' => filesize($prefix . $avatar),
                                 'selected' => $selected
                                );
            }

            /* If we have data, let's return it */
            if ( count($data) > 0 ) { return $data; }
        }

        /* If there is no data, then return just the default */
        return array( 'name' => 'default.png',
                      'url'  => $SiteUrl . '/avatars/default.png',
                      'size' => 0,
                      'selected' => true
                     );
    }

    /**
     *  Function returns a list of available Timezones for a given Account
     */
    private function _getTimezoneList() {
        $jsonFile = FLATS_DIR . '/resources/timezones.json';

        if ( file_exists($jsonFile) ) {
            $data = json_decode(readResource($jsonFile), true);
            if ( is_array($data) && count($data) > 0 ) { return $data; }
        }

        /* If we're here, there isn't a timezone file to read from */
        return false;
    }

    /** ********************************************************************* *
     *  Account Creation
     ** ********************************************************************* */
    private function _createAccount() {
        if ( !defined('DEFAULT_LANG') ) { define('DEFAULT_LANG', 'en-us'); }
        if ( !defined('SHA_SALT') ) {
            $this->_setMetaMessage("This system has not been configured. Cannot proceed.", 400);
            return false;
        }

        $CleanPass = NoNull($this->settings['pass'], $this->settings['password']);
        $CleanName = NoNull($this->settings['name'], $this->settings['login']);
        $CleanMail = NoNull($this->settings['mail'], $this->settings['email']);
        $CleanLang = NoNull($this->settings['lang'], DEFAULT_LANG);

        /* Ensure there are no bad characters in the account name */
        $CleanName = preg_replace("/[^a-zA-Z0-9]+/", '', $CleanName);

        /* Now let's do some basic validation */
        if ( mb_strlen($CleanPass) <= 6 ) {
            $this->_setMetaMessage( "Password is too weak. Please choose a better one.", 400 );
            return false;
        }

        if ( mb_strlen($CleanName) < 2 ) {
            $this->_setMetaMessage( "Nickname is too short. Please choose a longer one.", 400 );
            return false;
        }

        if ( mb_strlen($CleanMail) <= 5 ) {
            $this->_setMetaMessage( "Email address is too short. Please enter a correct address.", 400 );
            return false;
        }

        if ( validateEmail($CleanMail) === false ) {
            $this->_setMetaMessage( "Email address does not appear correct. Please enter a correct address.", 400 );
            return false;
        }

        // If we're here, we *might* be good. Create the account.
        $ReplStr = array( '[NAME]'   => sqlScrub($CleanName),
                          '[MAIL]'   => sqlScrub($CleanMail),
                          '[PASS]'   => sqlScrub($CleanPass),
                          '[LANG]'   => sqlScrub($CleanLang),
                          '[SALT]'   => sqlScrub(SHA_SALT),
                         );
        $sqlStr = prepSQLQuery( "CALL CreateAccount('[NAME]', '[PASS]', '[MAIL]', '[SALT]', '[DOMAIN]' );", $ReplStr );
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $SiteUrl = false;
            $AcctID = false;
            $Token = false;

            foreach ( $rslt as $Row ) {
                $SiteUrl = NoNull($Row['site_url']);
                $PaGuid = NoNull($Row['persona_guid']);
                $AcctID = nullInt($Row['account_id']);
            }
        }

        // What sort of return are we looking for?
        $url = NoNull($this->settings['HomeURL']) . '/welcome';
        switch ( strtolower($Redirect) ) {
            case 'web_redirect':
                if ( is_string($Token) ) {
                    $url .= '?token=' . $Token;
                } else {
                    $url = NoNull($this->settings['HomeURL']) . '/nodice';
                }
                redirectTo( $url, $this->settings );
                break;

            default:
                if ( is_string($Token) ) {
                    return array( 'token' => $Token,
                                  'url'   => NoNull($url),
                                 );
                } else {
                    $this->_setMetaMessage( "Could not create Account", 400 );
                    return false;
                }
        }

        // If We're Here, Something is Really Off
        return false;
    }

    /**
     *  Function Updates the Account fields available to an account-holder
     */
    private function _setAccountData() {
        $CleanDispName = NoNull($this->settings['display_name'], $this->settings['display_as']);
        $CleanTimezone = NoNull($this->settings['time_zone'], $this->settings['timezone']);
        $CleanDispLang = NoNull($this->settings['language_code'], $this->settings['language']);
        $CleanMail = NoNull($this->settings['email'], $this->settings['mail']);

        /* Perform some basic validation */
        $CleanDispLang = validateLanguage($CleanDispLang);
        if ( validateEmail($CleanMail) === false ) {
            $this->_setMetaMessage("Email address does not appear valid.", 400);
            return false;
        }

        /* Prepare the SQL Statement */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[DISPLAY_AS]' => sqlScrub($CleanDispName),
                          '[TIMEZONE]'   => sqlScrub(NoNull($CleanTimezone, 'UTC')),
                          '[LANGUAGE]'   => sqlScrub($CleanDispLang),
                          '[MAILADDR]'   => sqlScrub($CleanMail),
                         );

        $sqlStr = prepSQLQuery("CALL SetAccountData([ACCOUNT_ID], '[DISPLAY_AS]', '[MAILADDR]', '[TIMEZONE]', '[LANGUAGE]');", $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                return array( 'id'            => nullInt($Row['id']),
                              'login'         => NoNull($Row['login']),
                              'last_name'     => NoNull($Row['last_name']),
                              'first_name'    => NoNull($Row['first_name']),
                              'display_name'  => NoNull($Row['display_name']),
                              'email'         => NoNull($Row['email']),
                              'language_code' => NoNull($Row['language_code']),
                             );
            }
        }

        /* If we're here, then we could not update the database */
        $this->_setMetaMessage("Could not update the account record.", 400);
        return false;
    }

    /**
     *  Function creates/updates an Instructor or Student Account. If a record is invalid, an error is returned
     */
    private function _validateAccount( $data, $acctType = 'account.student' ) {
        if ( is_array($data) === false ) { return false; }
        $validPronouns = array('F', 'Hi', 'M', 'N', 'T', 'Zi');
        $validTypes = array('account.normal', 'account.student');
        if ( in_array(strtolower($acctType), $validTypes) ) { $acctType = 'account.student'; }

        $CleanLastName = NoNull($data['last_name'], NoNull($data['family_name'], $data['surname']));
        $CleanFirstName = NoNull($data['first_name'], $data['given_name']);
        $CleanID = NoNull($data['sfid'], $data['id']);

        if ( mb_strlen($CleanFirstName) <= 0 ) { return 'first_name'; }
        if ( mb_strlen($CleanLastName) <= 0 ) { return 'last_name'; }
        if ( mb_strlen($CleanID) < 15 ) { 'id'; }

        $CleanDisplayName = NoNull($data['display_name'], $data['print_name']);
        $CleanSamlGuid = NoNull($data['saml_guid'], $data['saml']);
        $CleanLang = validateLanguage(NoNull($data['lang_code'], $data['language']));

        /* Check if there is an Email address */
        $CleanMail = NoNull(NoNull($data['email'], $data['mail']));
        if ( validateEmail($CleanMail) === false ) { $CleanMail = ''; }

        /* Check if there is a defined pronoun */
        $CleanPronoun = ucfirst(NoNull($data['pronoun_code'], NoNull($data['pronoun'], $data['gender'])));
        if ( in_array($CleanPronoun, $validPronouns) === false ) { $CleanPronoun = 'T'; }

        $ReplStr = array( '[LOGIN]'        => sqlScrub($CleanID),
                          '[PASSWD]'       => getRandomString(18),
                          '[LAST_NAME]'    => sqlScrub($CleanLastName),
                          '[FIRST_NAME]'   => sqlScrub($CleanFirstName),
                          '[DISPLAY_NAME]' => sqlScrub($CleanDisplayName),
                          '[MAILADDR]'     => sqlScrub($CleanMail),
                          '[SHA_SALT]'     => sqlScrub(SHA_SALT),
                          '[TYPE]'         => sqlScrub($acctType),
                          '[SAML_GUID]'    => sqlScrub($CleanSamlGuid),
                          '[LANG]'         => sqlScrub($CleanLang),
                          '[PRONOUN]'      => sqlScrub($CleanPronoun),
                         );
        $sqlStr = prepSQLQuery("CALL ValidateAccount('[LOGIN]', '[PASSWD]', '[LAST_NAME]', '[FIRST_NAME]', '[DISPLAY_NAME]', '', '[SHA_SALT]', '[TYPE]', '[SAML_GUID]', '[LANG]');", $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            foreach ( $rslt as $Row ) {
                $id = nullInt($Row['account_id']);
                if ( $id > 0 ) {
                    $printName = NoNull($Row['print_name']);
                    $pronoun = NoNull($Row['pronoun'], 'T');

                    if ( $printName != $CleanDisplayName ) {
                        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($id),
                                          '[KEY]'        => 'print.name',
                                          '[VALUE]'      => sqlScrub($CleanDisplayName)
                                         );
                        $sqlStr = prepSQLQuery("CALL SetAccountMeta([ACCOUNT_ID], '[KEY]', '[VALUE]');", $ReplStr);
                        $sOK = doSQLQuery($sqlStr);
                    }
                    if ( $pronoun != $CleanPronoun ) {
                        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($id),
                                          '[KEY]'        => 'profile.pronoun',
                                          '[VALUE]'      => sqlScrub($CleanPronoun)
                                         );
                        $sqlStr = prepSQLQuery("CALL SetAccountMeta([ACCOUNT_ID], '[KEY]', '[VALUE]');", $ReplStr);
                        $sOK = doSQLQuery($sqlStr);
                    }
                    return $id;
                }
            }
        }

        /* If we're here, something's not quite right */
        return false;
    }

    /** ********************************************************************* *
     *  Password Management Functions
     ** ********************************************************************* */
    /**
     *  Function checks an email address is valid and sends an email to that address
     *      containing some links that allow them to sign into various 10C services.
     */
    private function _forgotPassword() {
        $CleanMail = NoNull($this->settings['email'], $this->settings['mail_addr']);


        // Return an Empty Array, Regardless of whether the data is good or not (to prevent email cycling)
        return array();
    }

    /** ********************************************************************* *
     *  Preferences
     ** ********************************************************************* */
    /**
     *  Function Sets a Person's Preference and Returns a list of preferences
     */
    private function _setMetaRecord() {
        $MetaPrefix = NoNull($this->settings['PgSub2'], $this->settings['PgSub1']);
        $CleanValue = NoNull($this->settings['value']);
        $CleanKey = NoNull($this->settings['type'], $this->settings['key']);
        if ( $MetaPrefix != '' && strpos($CleanKey, $MetaPrefix) === false ) { $CleanKey = $MetaPrefix . '.' . $CleanKey; }

        /* Ensure the Key is long enough */
        if ( strlen($CleanKey) < 3 ) {
            $this->_setMetaMessage("Invalid Meta Key Passed [$CleanKey]", 400);
            return false;
        }

        /* Ensure the Key follows protocol */
        if ( substr_count($CleanKey, '.') < 1 ) {
            $this->_setMetaMessage("Meta Key is in the wrong format [$CleanKey]", 400);
            return false;
        }

        /* Prep the SQL Statement */
        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[VALUE]'      => sqlScrub($CleanValue),
                          '[KEY]'        => sqlScrub($CleanKey),
                         );
        $sqlStr = prepSQLQuery("CALL SetAccountMeta([ACCOUNT_ID], '[KEY]', '[VALUE]');", $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();

            foreach ( $rslt as $Row ) {
                $data[] = array( 'key'          => NoNull($Row['key']),
                                 'value'        => NoNull($Row['value']),
                                 'created_at'   => date("Y-m-d\TH:i:s\Z", strtotime($Row['created_at'])),
                                 'created_unix' => strtotime($Row['created_at']),
                                 'updated_at'   => date("Y-m-d\TH:i:s\Z", strtotime($Row['updated_at'])),
                                 'updated_unix' => strtotime($Row['updated_at']),
                                );
            }

            /* If we have data, let's return it */
            if ( is_array($data) && count($data) > 0 ) { return $data; }
        }

        /* If we're here, something failed */
        $this->_setMetaMessage("Could not save Account Meta record", 400);
        return false;
    }

    private function _getPreference( $key = '' ) {
        $CleanType = NoNull($key, NoNull($this->settings['type'], $this->settings['key']));
        if ( $CleanType == '' ) {
            $this->_setMetaMessage("Invalid Type Key Passed", 400);
            return false;
        }

        $ReplStr = array( '[ACCOUNT_ID]' => nullInt($this->settings['_account_id']),
                          '[TYPE_KEY]'   => strtolower(sqlScrub($CleanType)),
                         );
        $sqlStr = readResource(SQL_DIR . '/account/getPreference.sql', $ReplStr);
        $rslt = doSQLQuery($sqlStr);
        if ( is_array($rslt) ) {
            $data = array();
            foreach ( $rslt as $Row ) {
                $data[] = array( 'type'         => NoNull($Row['type']),
                                 'value'        => NoNull($Row['value']),

                                 'created_at'   => date("Y-m-d\TH:i:s\Z", strtotime($Row['created_at'])),
                                 'created_unix' => strtotime($Row['created_at']),
                                 'updated_at'   => date("Y-m-d\TH:i:s\Z", strtotime($Row['updated_at'])),
                                 'updated_unix' => strtotime($Row['updated_at']),
                                );
            }

            // If We Have Data, Return it
            if ( count($data) > 0 ) { return (count($data) == 1) ? $data[0] : $data; }
        }

        // Return the Preference Object or an empty array
        return array();
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