<?php

namespace BestPluginsWordPress;

class OneClickLogin
{
    public  $version ;
    public  $testing ;
    public  $assetPath ;
    public  $pluginName ;
    public  $oneClickLoginAdmin ;
    public  $wordPressVersionPast5 ;
    private  $oauthState ;
    private  $settingsCache ;
    private  $passwordLength ;
    private  $hideByDefaultCache ;
    function __construct()
    {
        $this->testing = false;
        $this->importFromEnv = false;
        $this->oauthState = null;
        $this->version = '1.24.0';
        $this->settingsCache = [];
        $this->passwordLength = 64;
        $this->pluginName = 'One Click Login';
        $this->assetPath = dirname( \plugin_dir_url( __FILE__ ) ) . '/assets';
        $this->wordPressVersionPast5 = \version_compare( \get_bloginfo( 'version' ), '5.0', '>=' );
    }
    
    public function init()
    {
        
        if ( $GLOBALS['pagenow'] === 'wp-login.php' && !empty($_GET['code']) && !empty($_GET['state']) ) {
            $self = $this;
            $oauthState = ( !empty($_COOKIE['OneClickLoginState']) ? $_COOKIE['OneClickLoginState'] : false );
            # Unset cookie as it's only meant to be used once.
            $this->oauthState = null;
            $_COOKIE['OneClickLoginState'] = null;
            setcookie( 'ShowOneClickLogin', '', time() - 3600 );
            setcookie( 'OneClickLoginState', '', time() - 3600 );
            \add_action( 'init', function () use( $self, $oauthState ) {
                $self->loginAttempt( $oauthState );
                $self->loadAdminPage();
            } );
        } else {
            
            if ( \is_admin() || $this->testing ) {
                # Carbon_Fields::boot() must be called before init.
                $self = $this;
                \add_action( 'after_setup_theme', function () use( $self ) {
                    $self->loadCarbonFields();
                    $self->loadAdminPage();
                } );
                # For phpunit.
                if ( $this->testing ) {
                    $self->loadAdminPage();
                }
            }
            
            
            if ( $this->oneClickLoginCanBeShown() ) {
                $this->enqueueAssets();
                $this->addOneClickLoginButton();
            }
        
        }
        
        $this->preventNormalLogin();
        $this->preventPasswordReset();
        $this->preventPasswordChange();
    }
    
    public function canUsePremium()
    {
        return false;
    }
    
    private function oneClickLoginCanBeShown()
    {
        # If the user is on the login page.
        
        if ( $GLOBALS['pagenow'] === 'wp-login.php' ) {
            # If no client id has been set.
            if ( !$this->getClientId() ) {
                return false;
            }
            # Get the unhiding query string parameter.
            $queryParameter = $this->getUnhideQueryStringParameter();
            # If the One Click Login is not hidden.
            # Or the unhide query parameter is empty.
            
            if ( !$this->getHideByDefault() || empty($queryParameter) ) {
                return true;
            } else {
                
                if ( !empty($_COOKIE['ShowOneClickLogin']) && $_COOKIE['ShowOneClickLogin'] === $queryParameter ) {
                    return true;
                } else {
                    
                    if ( $this->getHideByDefault() && isset( $_GET[$queryParameter] ) && !empty($_GET[$queryParameter]) ) {
                        setcookie( 'ShowOneClickLogin', $queryParameter, time() + 15 * 60 );
                        return true;
                    } else {
                        
                        if ( $this->getHideByDefault() && isset( $_GET['redirect_to'] ) && stristr( urldecode( $_GET['redirect_to'] ), $queryParameter . '=' ) ) {
                            setcookie( 'ShowOneClickLogin', $queryParameter, time() + 15 * 60 );
                            return true;
                        }
                    
                    }
                
                }
            
            }
        
        }
        
        return false;
    }
    
    private function loadCarbonFields()
    {
        
        if ( $this->wordPressVersionPast5 ) {
            require_once 'lib/carbon-fields3/vendor/autoload.php';
        } else {
            require_once 'lib/carbon-fields2/vendor/autoload.php';
        }
        
        \Carbon_Fields\Carbon_Fields::boot();
    }
    
    public function getSettings( $flushCache = false )
    {
        if ( $flushCache ) {
            $this->settingsCache = [];
        }
        # Check if the settings have already been fetched from the database.
        
        if ( !property_exists( $this, 'settingsCache' ) || empty($this->settingsCache) ) {
            global  $wpdb ;
            $settings = [];
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", '_bpw_ocl_groups%' ) );
            
            if ( !empty($results) ) {
                foreach ( $results as $result ) {
                    # Skip non-important settings.
                    if ( stristr( $result->option_name, '|||' ) ) {
                        continue;
                    }
                    # Skip non-important settings.
                    if ( $result->option_value === '_' ) {
                        continue;
                    }
                    # Skip empty settings. I'm not sure why Carbon Fields keeps these?
                    if ( substr( $result->option_name, -6 ) === '_empty' ) {
                        continue;
                    }
                    # Reset.
                    $matches = null;
                    # Example option_name: _bpw_ocl_groups|bpw_ocl_domain_name|1|0|value.
                    # The regex will match 'bpw_ocl_domain_name' and '1'.
                    preg_match( '/_bpw_ocl_groups\\|([^\\|]+)\\|(\\d)\\|\\d\\|value$/', $result->option_name, $matches );
                    
                    if ( !empty($matches) && array_key_exists( 1, $matches ) && array_key_exists( 2, $matches ) ) {
                        $settingName = str_replace( 'bpw_ocl_', '', $matches[1] );
                        $settingKey = $matches[2];
                    } else {
                    }
                    
                    
                    if ( !empty($settingName) && isset( $settingKey ) && !empty($result->option_value) ) {
                        if ( !array_key_exists( $settingKey, $settings ) ) {
                            $settings[$settingKey] = [];
                        }
                        # There can be multiple email addreses so they are stored in an array.
                        
                        if ( $settingName === 'email_addresses' ) {
                            if ( !array_key_exists( $settingName, $settings[$settingKey] ) ) {
                                $settings[$settingKey][$settingName] = [];
                            }
                            $settings[$settingKey][$settingName][] = $result->option_value;
                        } else {
                            $settings[$settingKey][$settingName] = $result->option_value;
                        }
                    
                    }
                
                }
                # Group settings by domain / whitelist.
                $settingsCleaned = [];
                # Drop settings with empty or invalid domain name.
                foreach ( $settings as $key => $setting ) {
                    if ( !empty($setting['group_type']) ) {
                        
                        if ( $setting['group_type'] === 'domain' ) {
                            
                            if ( empty($setting['domain_name']) || !preg_match( '/^@[^.]+\\..+/', $setting['domain_name'] ) ) {
                                unset( $settings[$key] );
                            } else {
                                # Clean up the array just in case.
                                if ( isset( $setting['whitelist'] ) ) {
                                    unset( $setting['whitelist'] );
                                }
                                if ( isset( $setting['email_addresses'] ) ) {
                                    unset( $setting['email_addresses'] );
                                }
                            }
                            
                            if ( !array_key_exists( 'domain', $settingsCleaned ) ) {
                                $settingsCleaned['domain'] = [];
                            }
                            $settingsCleaned['domain'][] = $setting;
                        } else {
                            if ( $setting['group_type'] === 'whitelist' ) {
                            }
                        }
                    
                    }
                }
                # Store settings to runtime cache.
                $this->settingsCache = $settingsCleaned;
            }
        
        }
        
        return $this->settingsCache;
    }
    
    public function getSettingsForEmail( $email )
    {
        $settings = $this->getSettings();
        if ( !empty($settings) ) {
            # Then check if there's a domain for this email address.
            
            if ( !empty($settings['domain']) ) {
                $emailDomain = $this->getDomainFromEmail( $email );
                foreach ( $settings['domain'] as $setting ) {
                    if ( $setting['group_type'] === 'domain' && $setting['domain_name'] === $emailDomain ) {
                        return $setting;
                    }
                }
            }
        
        }
        return false;
    }
    
    private function getClientId()
    {
        global  $wpdb ;
        $clientId = false;
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_bpw_ocl_google_oauth_client_id' ) );
        if ( !empty($results) && array_key_exists( 0, $results ) ) {
            if ( property_exists( $results[0], 'option_value' ) ) {
                $clientId = $results[0]->option_value;
            }
        }
        return $clientId;
    }
    
    private function getClientSecret()
    {
        global  $wpdb ;
        $clientSecret = false;
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_bpw_ocl_google_oauth_client_secret' ) );
        if ( !empty($results) && array_key_exists( 0, $results ) ) {
            if ( property_exists( $results[0], 'option_value' ) ) {
                $clientSecret = $results[0]->option_value;
            }
        }
        return $clientSecret;
    }
    
    private function getHideByDefault()
    {
        
        if ( !isset( $this->hideByDefaultCache ) ) {
            global  $wpdb ;
            $hideByDefault = false;
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_bpw_ocl_hide_from_login' ) );
            if ( !empty($results) && array_key_exists( 0, $results ) ) {
                if ( property_exists( $results[0], 'option_value' ) ) {
                    $hideByDefault = $results[0]->option_value;
                }
            }
            
            if ( !empty($hideByDefault) && $hideByDefault === 'yes' ) {
                $this->hideByDefaultCache = true;
            } else {
                $this->hideByDefaultCache = false;
            }
        
        }
        
        return $this->hideByDefaultCache;
    }
    
    private function loadAdminPage()
    {
        require_once 'OneClickLoginAdmin.php';
        $this->oneClickLoginAdmin = new \BestPluginsWordPress\OneClickLoginAdmin( $this );
        $this->oneClickLoginAdmin->init();
    }
    
    private function enqueueAssets()
    {
        $self = $this;
        \add_action( 'login_enqueue_scripts', function () use( $self ) {
            \wp_enqueue_style(
                'bpw_ocl_css',
                $self->assetPath . '/css/one_click_login.css',
                [],
                $self->version
            );
        } );
    }
    
    private function generateOAuthUrl()
    {
        $oauthValues = [
            'client_id'     => $this->getClientId(),
            'response_type' => 'code',
            'scope'         => 'openid+email+https://www.googleapis.com/auth/userinfo.profile',
            'redirect_uri'  => \wp_login_url(),
            'state'         => $this->oauthState,
            'nonce'         => \wp_create_nonce( 'one-click-login-nonce' ),
            'access_type'   => 'online',
        ];
        $url = 'https://accounts.google.com/o/oauth2/v2/auth?';
        foreach ( $oauthValues as $key => $value ) {
            $url .= strip_tags( $key ) . "=" . strip_tags( $value ) . "&";
        }
        $url = rtrim( $url, '&' );
        return $url;
    }
    
    private function addOneClickLoginButton()
    {
        $self = $this;
        # OAUTH TOKEN HAS TO BE GENERATED HERE, AS NO HEADERS HAVE BEEN SENT YET!
        # OAuth state will only be regenerated after it's been used once.
        
        if ( empty($_COOKIE['OneClickLoginState']) ) {
            $this->oauthState = bin2hex( random_bytes( 128 / 8 ) );
            if ( !empty($_GET['redirect_to']) && filter_var( $_GET['redirect_to'], FILTER_VALIDATE_URL ) ) {
                $this->oauthState .= '|' . base64_encode( $_GET['redirect_to'] );
            }
            setcookie( 'OneClickLoginState', $this->oauthState, time() + 15 * 60 );
        } else {
            $this->oauthState = $_COOKIE['OneClickLoginState'];
        }
        
        # Adds the One Click login button on top of the login form.
        \add_action(
            'login_message',
            function ( $message ) use( $self ) {
            $message .= "<a href='" . $self->generateOAuthUrl() . "'><div class='bpw_ocl_google-button bpw_ocl_login_button'><span class='bpw_ocl_google-logo'><img src='" . $self->assetPath . "/img/google.svg'></span><span class='bpw_ocl_google-text'>Sign in with Google</span></div></a>";
            
            if ( !empty($_GET['ocl_error_code']) ) {
                $message .= '<div id="login_error">' . $self->getErrorMessage( $_GET['ocl_error_code'] ) . '</div>';
            } else {
                if ( !empty($_GET['ocl_error_message']) ) {
                    $message .= '<div id="login_error">' . $self->getErrorMessage( false, $_GET['ocl_error_message'] ) . '</div>';
                }
            }
            
            return $message;
        },
            10,
            1
        );
    }
    
    private function preventNormalLogin()
    {
    }
    
    private function passwordUsagePrevented( $email )
    {
        return false;
    }
    
    private function preventPasswordReset()
    {
    }
    
    private function preventPasswordChange()
    {
    }
    
    private function getUserInfo( $tokenData )
    {
        if ( !empty($tokenData) ) {
            
            if ( $tokenData->aud === $this->getClientId() ) {
                $userInfo = [
                    'user_email' => $tokenData->email,
                ];
                if ( !empty($tokenData->given_name) ) {
                    $userInfo['first_name'] = $tokenData->given_name;
                }
                if ( !empty($tokenData->family_name) ) {
                    $userInfo['last_name'] = $tokenData->family_name;
                }
                return $userInfo;
            }
        
        }
        return false;
    }
    
    private function verifyIdToken( $idToken )
    {
        
        if ( !empty($idToken) ) {
            $response = \wp_remote_get( 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $idToken );
            $jsonResult = \wp_remote_retrieve_body( $response );
            return json_decode( $jsonResult );
        }
        
        return false;
    }
    
    private function getIdToken( $code )
    {
        
        if ( !empty($code) ) {
            $clientId = $this->getClientId();
            $clientSecret = $this->getClientSecret();
            $redirectUri = \wp_login_url();
            
            if ( !empty($clientId) && !empty($clientSecret) && !empty($redirectUri) ) {
                $args = [
                    'code'          => $code,
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri'  => $redirectUri,
                    'grant_type'    => 'authorization_code',
                ];
                $response = \wp_remote_post( 'https://oauth2.googleapis.com/token', [
                    'body'    => $args,
                    'method'  => 'POST',
                    'headers' => [ 'Content-Type: application/x-www-form-urlencoded' ],
                ] );
                $responseBodyJSON = \wp_remote_retrieve_body( $response );
                $responseBody = json_decode( $responseBodyJSON );
                if ( !empty($responseBody) ) {
                    
                    if ( !empty($responseBody->error) ) {
                        return [
                            'success' => false,
                            'message' => "oclga_" . $responseBody->error,
                        ];
                    } else {
                        if ( !empty($responseBody->id_token) ) {
                            return [
                                'success' => true,
                                'value'   => $responseBody->id_token,
                            ];
                        }
                    }
                
                }
            }
        
        }
        
        return false;
    }
    
    private function generatePassword()
    {
        return \wp_generate_password( $this->passwordLength, true, true );
    }
    
    private function getErrorMessage( $errorCode, $overrideErrorMessage = false, $replaceString = false )
    {
        $errorMessage = 'Unknown error occurred.';
        
        if ( empty($overrideErrorMessage) ) {
            $errorMessages = [
                'one_click_login_failed'                => 'The login failed. Please try again.',
                'one_click_login_failed2'               => 'There was an error. Please try again.',
                'unable_to_find_user'                   => 'Unable to find the user. Please try again.',
                'login_prevented'                       => 'Normal login has been disabled for this email address. Please use "Sign in with Google" for this email address.',
                'invalid_email_address'                 => 'Invalid email address.',
                'password_change_prevent'               => 'Password change is not allowed for this email address.',
                'email_not_allowed_for_login'           => 'Login is not allowed for this email address.',
                'email_not_allowed_for_registration'    => 'Registration is not allowed for this email address.',
                'password_reset_prevented'              => 'Password reset is not allowed for this email address.',
                'nonce_error'                           => 'Invalid nonce.',
                'state_error'                           => 'Please try to log in again by clicking "Sign in with Google" button.',
                'unable_to_find_settings'               => 'Unable to find settings. Please try again.',
                'unknown_login_error'                   => 'Unknown login error',
                'one_click_login_failed_cookie'         => 'Cookie expired or missing. Please try to log in again by clicking "Sign in with Google" button.',
                'one_click_login_failed_nonce_mismatch' => 'Nonce mismatch. Please try to log in again by clicking "Sign in with Google" button.',
                'one_click_login_failed_nonce_missing'  => 'Nonce missing. Please try to log in again by clicking "Sign in with Google" button.',
            ];
            
            if ( $errorCode === 'login_prevented' && $this->getHideByDefault() ) {
                $queryParameter = $this->getUnhideQueryStringParameter();
                $errorMessages['login_prevented'] = str_replace( $this->pluginName, "<a href='" . \wp_login_url() . "?" . $queryParameter . "=1'>" . $this->pluginName . "</a>", $errorMessages['login_prevented'] );
            }
            
            if ( array_key_exists( $errorCode, $errorMessages ) ) {
                $errorMessage = $errorMessages[$errorCode];
            }
            if ( !empty($replaceString) ) {
                $errorMessage = str_replace( '%s', $replaceString, $errorMessage );
            }
        } else {
            $errorMessage = \esc_html( $overrideErrorMessage );
        }
        
        $errorMessage = '<div class="bpw_ocl_error"><strong>ERROR - ' . $this->pluginName . '</strong>: ' . $this->translate( $errorMessage ) . '</div>';
        return $errorMessage;
    }
    
    private function isIgnoredUser( $userId )
    {
        return \get_user_meta( $userId, 'one_click_login_ignored_user', true );
    }
    
    private function logInUser( $email )
    {
        $user = \get_user_by( 'email', $email );
        # User not found.
        if ( empty($user) || !property_exists( $user, 'ID' ) ) {
            return [
                'success'   => false,
                'errorCode' => 'unable_to_find_user',
            ];
        }
        $settings = $this->getSettingsForEmail( $email );
        # Settings not found.
        # This should never happen.
        if ( empty($settings) ) {
            return [
                'success'   => false,
                'errorCode' => 'unable_to_find_settings',
            ];
        }
        # No need to actually log in the user when testing.
        
        if ( !$this->testing ) {
            \wp_clear_auth_cookie();
            \wp_set_current_user( $user->ID );
            \wp_set_auth_cookie( $user->ID );
        }
        
        
        if ( \is_user_logged_in() ) {
            $this->loginSuccess();
        } else {
            return [
                'success'   => false,
                'errorCode' => 'unknown_login_error',
            ];
        }
    
    }
    
    private function loginSuccess()
    {
        // Check if there's a base64 encoded redirect_to in the url.
        $redirectTo = false;
        if ( !empty($_GET['code']) && !empty($_GET['state']) ) {
            
            if ( stristr( $_GET['state'], '|' ) ) {
                $explode = explode( '|', $_GET['state'] );
                
                if ( !empty($explode[1]) ) {
                    $redirectTo = base64_decode( $explode[1] );
                    
                    if ( !empty($redirectTo) && filter_var( $redirectTo, FILTER_VALIDATE_URL ) ) {
                        \wp_redirect( $redirectTo );
                        exit;
                    }
                
                }
            
            }
        
        }
        # No redirect_to found, redirect to admin url.
        \wp_redirect( user_admin_url() );
        exit;
    }
    
    private function loginFailed( $errorCode = false, $errorMessage = false )
    {
        
        if ( !empty($errorCode) ) {
            \wp_redirect( \wp_login_url() . '?ocl_error_code=' . $errorCode );
        } else {
            \wp_redirect( \wp_login_url() . '?ocl_error_message=' . $errorMessage );
        }
        
        exit;
    }
    
    private function loginAttempt( $oauthState )
    {
        if ( empty($oauthState) ) {
            $this->loginFailed( 'one_click_login_failed_cookie' );
        }
        
        if ( !empty($_GET['code']) && !empty($_GET['state']) ) {
            if ( empty($oauthState) || $_GET['state'] !== $oauthState ) {
                $this->loginFailed( 'state_error' );
            }
            $idTokenResponse = $this->getIdToken( $_GET['code'] );
            if ( $idTokenResponse['success'] === false ) {
                $this->loginFailed( false, $idTokenResponse['message'] );
            }
            
            if ( $idTokenResponse['success'] === true && !empty($idTokenResponse['value']) ) {
                $tokenData = $this->verifyIdToken( $idTokenResponse['value'] );
                if ( empty($tokenData->nonce) ) {
                    $this->loginFailed( 'one_click_login_failed_nonce_missing' );
                }
                if ( !wp_verify_nonce( $tokenData->nonce, 'one-click-login-nonce' ) ) {
                    $this->loginFailed( 'one_click_login_failed_nonce_mismatch' );
                }
                $userInfo = $this->getUserInfo( $tokenData );
            }
            
            # Unknown error. This should never happen.
            if ( empty($userInfo) || !is_array( $userInfo ) || !array_key_exists( 'user_email', $userInfo ) ) {
                $this->loginFailed( 'one_click_login_failed2' );
            }
            # Check if the email address is correct syntax.
            if ( !filter_var( $userInfo['user_email'], FILTER_VALIDATE_EMAIL ) ) {
                $this->loginFailed( 'invalid_email_address' );
            }
            # Check if this email address is allowed.
            if ( !$this->getSettingsForEmail( $userInfo['user_email'] ) ) {
                $this->loginFailed( 'email_not_allowed_for_login' );
            }
            # New user.
            
            if ( !email_exists( $userInfo['user_email'] ) ) {
                $result = $this->registerNewUser( $userInfo );
                # Error.
                if ( $result !== true && is_array( $result ) ) {
                    
                    if ( !empty($result['errorMessage']) ) {
                        $this->loginFailed( false, $result['errorMessage'] );
                    } else {
                        if ( !empty($result['errorCode']) ) {
                            $this->loginFailed( $result['errorCode'] );
                        }
                    }
                
                }
            }
            
            # Log in existing or new user.
            
            if ( !empty($userInfo['user_email']) ) {
                # If everything goes well no return is received.
                # The user is redirected to the admin url.
                $logInResult = $this->logInUser( $userInfo['user_email'] );
                if ( !empty($logInResult) && array_key_exists( 'success', $logInResult ) && $logInResult['success'] === false && !empty($logInResult['errorCode']) ) {
                    $this->loginFailed( $logInResult['errorCode'] );
                }
            }
        
        }
        
        # Unknown error. This should never happen.
        $this->loginFailed( 'one_click_login_failed' );
    }
    
    private function registerNewUser( $userInfo )
    {
        $settingsForEmail = $this->getSettingsForEmail( $userInfo['user_email'] );
        if ( empty($settingsForEmail) || !array_key_exists( 'allow_new_user_registering', $settingsForEmail ) || $settingsForEmail['allow_new_user_registering'] !== 'yes' ) {
            return [
                'success'   => false,
                'errorCode' => 'email_not_allowed_for_registration',
            ];
        }
        # Get the default role set for this email address. Default to 'subscriber' role.
        $userRole = 'subscriber';
        # Add all necessary data to $userInfo needed by wp_insert_user().
        $userInfo += [
            'user_login' => $userInfo['user_email'],
            'user_pass'  => $this->generatePassword(),
            'role'       => $userRole,
        ];
        # Create the user.
        $newUserId = \wp_insert_user( $userInfo );
        # Mark the user as registered with One Click Login. This can be seen on the "Ignored users" tab on the admin page.
        \update_user_meta( $newUserId, 'one_click_login_registered', true );
        # There was an error creating the user.
        if ( is_wp_error( $newUserId ) ) {
            return [
                'success'      => false,
                'errorMessage' => $newUserId->get_error_message() . ' (' . $newUserId->get_error_code() . ')',
            ];
        }
        return true;
    }
    
    public function getUnhideQueryStringParameter()
    {
        
        if ( !isset( $this->unhideQueryParameter ) ) {
            global  $wpdb ;
            $queryParameter = false;
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_bpw_ocl_query_string_parameter_for_unhiding' ) );
            if ( !empty($results) && array_key_exists( 0, $results ) ) {
                if ( property_exists( $results[0], 'option_value' ) ) {
                    $queryParameter = $results[0]->option_value;
                }
            }
            
            if ( !empty($queryParameter) ) {
                $this->unhideQueryParameter = $queryParameter;
            } else {
                $this->unhideQueryParameter = false;
            }
        
        }
        
        return $this->unhideQueryParameter;
    }
    
    public function getDomainFromEmail( $email )
    {
        if ( !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return false;
        }
        $explosion = explode( '@', $email );
        return "@" . array_pop( $explosion );
    }
    
    public function translate( $string )
    {
        return \__( $string, 'one_click_login_plugin' );
    }

}