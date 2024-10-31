<?php

namespace BestPluginsWordPress;

use  Carbon_Fields\Container ;
use  Carbon_Fields\Field ;
class OneClickLoginAdmin
{
    private  $oneClickLogin ;
    private  $adminPageActive ;
    private  $oneClickLoginIgnoredUsersCache ;
    function __construct( $oneClickLogin )
    {
        $this->oneClickLogin = $oneClickLogin;
        $this->adminPageActive = array_key_exists( 'page', $_GET ) && $_GET['page'] === 'crb_carbon_fields_container_one_click_login.php';
    }
    
    public function init()
    {
        
        if ( \wp_doing_ajax() ) {
            $this->addAjaxEndpoint();
        } else {
            
            if ( is_admin() ) {
                # Create basic container so menu item will eb drawn.
                $container = $this->createAdminPage();
                # Determines if the menu should be hidden or not.
                $this->enqueueAssetsHideMenu();
                # Draw the rest of the admin page only if it is currently open.
                
                if ( $this->adminPageActive ) {
                    $this->createAdminPageTabs( $container );
                    $this->checkSuccessCode();
                    $this->checkErrorCode();
                    $this->enqueueAssets();
                    $this->localizeAssets();
                    $this->saveHook();
                    $this->removeLingeringIgnoredUsersAfterSaveHook();
                }
            
            }
        
        }
    
    }
    
    private function checkSuccessCode()
    {
        if ( !empty($_GET['success_code']) ) {
            \add_action( 'admin_notices', function () {
                printf( '<div class="notice notice-success"><p>' . $this->getSuccessMessage( $_GET['success_code'] ) . '</p></div>' );
            } );
        }
    }
    
    private function checkErrorCode()
    {
        if ( !empty($_GET['error_code']) ) {
            \add_action( 'admin_notices', function () {
                printf( '<div class="notice notice-error"><p>' . $this->getErrorMessage( $_GET['error_code'] ) . '</p></div>' );
            } );
        }
    }
    
    private function removeLingeringIgnoredUsersAfterSaveHook()
    {
        $self = $this;
        \add_filter(
            'carbon_fields_theme_options_container_saved',
            function () use( $self ) {
            # Skip cache as it will show stale values.
            $settings = $self->oneClickLogin->getSettings( true );
            
            if ( !empty($settings['domain']) ) {
                # Users that registered with One Click Login.
                $oneClickLoginRegisteredUsers = get_users( [
                    'fields'     => [ 'ID', 'user_email' ],
                    'meta_query' => [ [
                    'key'     => 'one_click_login_ignored_user',
                    'value'   => true,
                    'compare' => '=',
                ] ],
                ] );
                if ( !empty($oneClickLoginRegisteredUsers) ) {
                    foreach ( $oneClickLoginRegisteredUsers as $user ) {
                        $userEmailDomain = $self->oneClickLogin->getDomainFromEmail( $user->user_email );
                        # This users email domain is no longer part of any domain groups.
                        if ( !in_array( $userEmailDomain, array_column( $settings['domain'], 'domain_name' ) ) ) {
                            # Stop ignoring this user.
                            \delete_user_meta( $user->ID, 'one_click_login_ignored_user', 1 );
                        }
                    }
                }
            }
        
        },
            10,
            0
        );
    }
    
    public function ajaxResponse( $data )
    {
        
        if ( !$this->oneClickLogin->testing ) {
            echo  json_encode( $data ) ;
            wp_die();
        }
        
        return false;
    }
    
    private function addAjaxEndpoint()
    {
        \add_action( "wp_ajax_bpw_ocl_ajax_import", [ $this, 'ajaxImportCallbackAdmin' ] );
        \add_action( "wp_ajax_no_priv_bpw_ocl_ajax_import", [ $this, 'ajaxImportCallbackAdmin' ] );
    }
    
    public function ajaxImportCallbackAdmin()
    {
        
        if ( $this->oneClickLogin->canUsePremium() || $this->oneClickLogin->importFromEnv ) {
            
            if ( bpw_ocl_fs()->is__premium_only() || $this->oneClickLogin->importFromEnv ) {
                
                if ( !empty($_POST['import_data']) ) {
                    $importDataJSON = \wp_unslash( $_POST['import_data'] );
                    if ( !array_key_exists( 'nonce', $_POST ) || !\wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
                        return $this->ajaxResponse( [
                            'success'     => false,
                            'redirectUrl' => \get_admin_url() . 'admin.php?page=crb_carbon_fields_container_one_click_login.php&error_code=nonce_error',
                        ] );
                    }
                    $importData = json_decode( $importDataJSON );
                    
                    if ( !empty($importData) && is_object( $importData ) ) {
                        
                        if ( !empty($importData->settings) ) {
                            global  $wpdb ;
                            # Delete settings related to domains.
                            $results = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_bpw_ocl_groups%' ) );
                            # Delete other settings
                            $results = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name IN ('_bpw_ocl_hide_from_login', '_bpw_ocl_hide_from_admin', '_bpw_ocl_google_oauth_client_id', '_bpw_ocl_google_oauth_client_secret', '_bpw_ocl_query_string_parameter_for_unhiding')" );
                            # Import settings.
                            foreach ( $importData->settings as $data ) {
                                # Only allow importing of One Click Login options.
                                
                                if ( substr( $data->name, 0, 9 ) === '_bpw_ocl_' ) {
                                    $optionUpdated = \update_option( $data->name, $data->value, false );
                                    # When update_option fails to co-operate, add the option values manually.
                                    if ( !$optionUpdated ) {
                                        $results = $wpdb->query( $wpdb->prepare(
                                            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
                                            $data->name,
                                            $data->value,
                                            'no'
                                        ) );
                                    }
                                }
                            
                            }
                            # Delete user meta.
                            $results = $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'one_click_login_ignored_user'" );
                            # Import user meta.
                            if ( !empty($importData->ignored_users) ) {
                                foreach ( $importData->ignored_users as $emailAddress ) {
                                    $user = \get_user_by( 'email', $emailAddress );
                                    if ( !empty($user) && property_exists( $user, 'ID' ) ) {
                                        \update_user_meta( $user->ID, 'one_click_login_ignored_user', true );
                                    }
                                }
                            }
                            return $this->ajaxResponse( [
                                'success'     => true,
                                'redirectUrl' => \get_admin_url() . 'admin.php?page=crb_carbon_fields_container_one_click_login.php&success_code=import',
                            ] );
                        }
                    
                    } else {
                        return $this->ajaxResponse( [
                            'success'     => false,
                            'redirectUrl' => \get_admin_url() . 'admin.php?page=crb_carbon_fields_container_one_click_login.php&error_code=invalid_json',
                        ] );
                    }
                
                } else {
                    return $this->ajaxResponse( [
                        'success'     => false,
                        'redirectUrl' => \get_admin_url() . 'admin.php?page=crb_carbon_fields_container_one_click_login.php&error_code=import_data_missing',
                    ] );
                }
                
                # This should never happen.
                return $this->ajaxResponse( [
                    'success'     => false,
                    'redirectUrl' => \get_admin_url() . 'admin.php?page=crb_carbon_fields_container_one_click_login.php&error_code=unknown_error',
                ] );
            }
        
        } else {
            return $this->ajaxResponse( [
                'success'     => false,
                'redirectUrl' => \get_admin_url() . 'admin.php?page=crb_carbon_fields_container_one_click_login.php&error_code=import_premium_required',
            ] );
        }
    
    }
    
    private function getErrorMessage( $errorCode )
    {
        $errorMessage = 'Unknown error occurred.';
        $errorMessages = [
            'nonce_error'             => 'Invalide nonce.',
            'import_data_missing'     => 'Import data missing.',
            'unknown_error'           => 'Unknown error, try reloading the page.',
            'invalid_json'            => "Invalid JSON format.",
            'import_premium_required' => "You need to <a href='" . bpw_ocl_fs()->get_upgrade_url() . "'>upgrade to premium</a> to use the import feature.",
        ];
        if ( array_key_exists( $errorCode, $errorMessages ) ) {
            $errorMessage = $errorMessages[$errorCode];
        }
        $errorMessage = '<strong>ERROR - ' . $this->oneClickLogin->pluginName . '</strong>: ' . $this->oneClickLogin->translate( $errorMessage );
        return $errorMessage;
    }
    
    private function getSuccessMessage( $successCode )
    {
        $successMessage = 'Unknown error occurred.';
        $successMessages = [
            'import' => 'Settings and ignored users imported succesfully.',
        ];
        if ( array_key_exists( $successCode, $successMessages ) ) {
            $successMessage = $successMessages[$successCode];
        }
        $successMessage = '<strong>' . $this->oneClickLogin->pluginName . '</strong>: ' . $this->oneClickLogin->translate( $successMessage );
        return $successMessage;
    }
    
    private function enqueueAssets()
    {
        $version = $this->oneClickLogin->version;
        $assetPath = $this->oneClickLogin->assetPath;
        \add_action( 'admin_enqueue_scripts', function () use( $version, $assetPath ) {
            \wp_enqueue_script(
                'bpw_ocl_admin_js',
                $assetPath . '/js/one_click_login_admin.js',
                [ 'jquery' ],
                $version
            );
        } );
        add_action( 'admin_enqueue_scripts', function () use( $version, $assetPath ) {
            wp_enqueue_style(
                'bpw_ocl_admin_css',
                $assetPath . '/css/one_click_login_admin.css',
                [],
                $version
            );
        } );
    }
    
    private function enqueueAssetsHideMenu()
    {
        $version = $this->oneClickLogin->version;
        $assetPath = $this->oneClickLogin->assetPath;
        if ( !$this->oneClickLoginAdminPageCanBeShown() ) {
            \add_action( 'admin_enqueue_scripts', function () use( $version, $assetPath ) {
                \wp_enqueue_style(
                    'bpw_ocl_admin_hide_css',
                    $assetPath . '/css/one_click_login_admin_hide.css',
                    [],
                    $version
                );
            } );
        }
    }
    
    private function localizeAssets()
    {
        \add_action( 'admin_enqueue_scripts', function () {
            $variables = [
                'premium'                         => $this->oneClickLogin->canUsePremium(),
                'registered_with_one_click_login' => $this->oneClickLogin->translate( 'Registered with One Click Login' ),
                'add_group'                       => $this->oneClickLogin->translate( 'Add Group' ),
                'add_email_address'               => $this->oneClickLogin->translate( 'Add Email Address' ),
                'nonce'                           => \wp_create_nonce( 'ajax-nonce' ),
                'ajax_url'                        => \admin_url( 'admin-ajax.php' ),
                'wordpress_version_past_5'        => $this->oneClickLogin->wordPressVersionPast5,
            ];
            // If there's error or success messages on the url, set the form action to the default one so the url will be fixed after saving.
            if ( !empty($_GET['error_code']) || !empty($_GET['success_code']) ) {
                $variables['carbon_fields_form_action'] = get_admin_url() . 'admin.php?page=crb_carbon_fields_container_one_click_login.php';
            }
            \wp_localize_script( 'bpw_ocl_admin_js', 'bpw_ocl', $variables );
        } );
    }
    
    private function oneClickLoginAdminPageCanBeShown()
    {
        $settings = $this->oneClickLogin->getSettings();
        $queryParameter = $this->oneClickLogin->getUnhideQueryStringParameter();
        # If the user is on the login page.
        if ( \is_admin() ) {
            # If the One Click Login admin page is not hidden.
            
            if ( !$this->getHideFromAdmin() || empty($queryParameter) ) {
                return true;
            } else {
                
                if ( $this->getHideFromAdmin() && isset( $_GET[$queryParameter] ) && !empty($_GET[$queryParameter]) ) {
                    return true;
                } else {
                    if ( $this->getHideFromAdmin() && $this->adminPageActive ) {
                        return true;
                    }
                }
            
            }
        
        }
        return false;
    }
    
    private function saveHook()
    {
        $premiumVersion = $this->oneClickLogin->canUsePremium();
        \add_filter(
            'carbon_fields_should_save_field_value',
            function ( $save, $newValue, $saveObject ) use( $premiumVersion ) {
            $reflectionObjectOuter = new \ReflectionObject( $saveObject );
            # Get the field name from the object. The value is protected so it has to be get with a reflection class.
            $reflectionPropertyBaseName = $reflectionObjectOuter->getProperty( 'base_name' );
            $reflectionPropertyBaseName->setAccessible( true );
            $saveFieldName = $reflectionPropertyBaseName->getValue( $saveObject );
            
            if ( stristr( $saveFieldName, 'bpw_ocl_tmp_' ) !== false ) {
                # Get the default value from the object. The value is protected so it has to be get with a reflection class.
                $reflectionPropertyPreviousValue = $reflectionObjectOuter->getProperty( 'default_value' );
                $reflectionPropertyPreviousValue->setAccessible( true );
                $previousValue = $reflectionPropertyPreviousValue->getValue( $saveObject );
                # No need to do anything if the previous and new values are the same.
                
                if ( $newValue !== $previousValue ) {
                    # Get the user id from the field name, for example bopw_gl_tmp_15.
                    $userId = str_replace( 'bpw_ocl_tmp_', '', $saveFieldName );
                    
                    if ( empty($newValue) ) {
                        # Stop ignoring this user.
                        \delete_user_meta( $userId, 'one_click_login_ignored_user' );
                    } else {
                        # Start ignoring this user.
                        \update_user_meta( $userId, 'one_click_login_ignored_user', true );
                    }
                
                }
                
                # Prevent saving for bpw_ocl_tmp_ fields.
                return false;
            } else {
                
                if ( $saveFieldName === 'bpw_ocl_import' || $saveFieldName === 'bpw_ocl_export' ) {
                    # Prevent saving for bpw_ocl_import and bpw_ocl_export fields.
                    return false;
                } else {
                    
                    if ( $saveFieldName === 'bpw_ocl_groups' ) {
                        $reflectionPropertyPreviousValue = $reflectionObjectOuter->getProperty( 'value_tree' );
                        $reflectionPropertyPreviousValue->setAccessible( true );
                        $valueTree = $reflectionPropertyPreviousValue->getValue( $saveObject );
                        
                        if ( !$premiumVersion ) {
                            # You can only save one domain with the free version.
                            
                            if ( count( $valueTree ) > 1 ) {
                                return false;
                            } else {
                                # If the default role is set to something else than subscriber.
                                if ( isset( $valueTree[0]['bpw_ocl_default_role'][0]['value'] ) && $valueTree[0]['bpw_ocl_default_role'][0]['value'] !== 'subscriber' ) {
                                    return false;
                                }
                                # If the prevent password usage is turned on.
                                if ( isset( $valueTree[0]['bpw_ocl_prevent_password_usage'][0]['value'] ) && $valueTree[0]['bpw_ocl_prevent_password_usage'][0]['value'] === 'yes' ) {
                                    return false;
                                }
                                # There's only one domain added.
                                # Make sure it's @gmail.com.
                                if ( isset( $valueTree[0]['bpw_ocl_domain_name'][0]['value'] ) && $valueTree[0]['bpw_ocl_domain_name'][0]['value'] === '@gmail.com' ) {
                                    return true;
                                }
                                return false;
                            }
                        
                        } else {
                            
                            if ( !empty($valueTree) ) {
                                $uniqueDomains = [];
                                # Loop through the given domains.
                                foreach ( $valueTree as $value ) {
                                    
                                    if ( !empty($value['bpw_ocl_domain_name'][0]['value']) ) {
                                        $domain = $value['bpw_ocl_domain_name'][0]['value'];
                                        # Check for duplicate domains.
                                        # Do not save duplicates.
                                        
                                        if ( !in_array( $domain, $uniqueDomains ) ) {
                                            $uniqueDomains[] = $domain;
                                        } else {
                                            return false;
                                        }
                                    
                                    }
                                
                                }
                            }
                        
                        }
                    
                    }
                
                }
            
            }
            
            # Do not modify any other fields.
            return $save;
        },
            10,
            3
        );
    }
    
    private function createAdminPage()
    {
        return Container::make( 'theme_options', $this->oneClickLogin->translate( 'One Click Login' ) )->where( 'current_user_role', 'IN', [ 'administrator' ] )->set_icon( 'dashicons-lock' )->set_page_file( 'crb_carbon_fields_container_one_click_login.php' );
    }
    
    private function createAdminPageTabs( $container )
    {
        $container->add_tab( $this->oneClickLogin->translate( 'Settings' ), $this->getAdminSettingsTabContent() )->add_tab( $this->oneClickLogin->translate( 'Ignored users' ), $this->getAdminIgnoredUsersTabContent() )->add_tab( $this->oneClickLogin->translate( 'Instructions' ), $this->getAdminInstructionsTabContent() )->add_tab( $this->oneClickLogin->translate( 'Import / Export' ), $this->getAdminImportExportTabContent() );
    }
    
    private function getAdminInstructionsTabContent()
    {
        $imageURL = str_replace( '/src/', '/assets/img', plugin_dir_url( __FILE__ ) );
        $fields = [];
        $fields[] = Field::make( 'separator', 'bpw_ocl_instructions_separator', $this->oneClickLogin->translate( 'Instructions' ) )->set_help_text( $this->oneClickLogin->translate( "All instructions are in a video form:" ) );
        $fields[] = Field::make( 'separator', 'bpw_ocl_instructions_separator_1', $this->oneClickLogin->translate( 'How to create Google OAuth Client ID & Secret' ) );
        $fields[] = Field::make( 'html', 'bpw_ocl_instructions_1' )->set_html( '<iframe width="560" height="315" src="https://www.youtube.com/embed/eRSwpxu_2n4" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>' )->set_help_text( '<a href="https://youtu.be/eRSwpxu_2n4" target="_blank">https://youtu.be/eRSwpxu_2n4</a>' );
        $fields[] = Field::make( 'separator', 'bpw_ocl_instructions_separator_2', $this->oneClickLogin->translate( 'How to use One Click Login' ) );
        $fields[] = Field::make( 'html', 'bpw_ocl_instructions_2' )->set_html( '<iframe width="560" height="315" src="https://www.youtube.com/embed/blnUvT9ZGRk" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>' )->set_help_text( '<a href="https://youtu.be/blnUvT9ZGRk" target="_blank">https://youtu.be/blnUvT9ZGRk</a>' );
        return $fields;
    }
    
    private function getAllUsersByDomain()
    {
        $allUsers = \get_users( [
            'fields' => [ 'ID', 'display_name', 'user_email' ],
        ] );
        $allUsersByDomain = [];
        if ( !empty($allUsers) ) {
            foreach ( $allUsers as $user ) {
                $userEmailDomain = $this->oneClickLogin->getDomainFromEmail( $user->user_email );
                if ( !array_key_exists( $userEmailDomain, $allUsersByDomain ) ) {
                    $allUsersByDomain[$userEmailDomain] = [];
                }
                $allUsersByDomain[$userEmailDomain][] = $user;
            }
        }
        return $allUsersByDomain;
    }
    
    private function getAdminImportExportTabContent()
    {
        $fields = [];
        $helpTexts = [
            'export'                => $this->oneClickLogin->translate( 'There are two ways you can import these settings:<br>a) Paste it to the "Import settings" textarea and click "Save Changes".<br>b) Set it to <b>ONE_CLICK_LOGIN_IMPORT_ON_PLUGIN_ACTIVATION</b> environment variable on your system. Importing from environment variable only occurs once, during the initial activation of this plugin.' ),
            'import_premium_only'   => $this->oneClickLogin->translate( 'Import can only be used with a premium account.' ),
            'import_delete_warning' => '',
        ];
        
        if ( $this->oneClickLogin->canUsePremium() ) {
            $helpTexts['import_premium_only'] = '';
            $helpTexts['import_delete_warning'] = '<br><br>' . $this->oneClickLogin->translate( 'Warning: Clicking the button above will delete all current settings and import new ones.' );
        }
        
        $fields[] = Field::make( 'separator', 'bpw_ocl_import_separator', $this->oneClickLogin->translate( 'Import' ) );
        $fields[] = Field::make( 'textarea', 'bpw_ocl_import', $this->oneClickLogin->translate( 'Import settings' ) )->set_help_text( '<button class="button button-primary button-large bpw_ocl_import_settings">' . $this->oneClickLogin->translate( 'Import settings' ) . '</button>' . $helpTexts['import_delete_warning'] )->set_classes( [ 'bpw_ocl_import_settings_container' ] )->set_attribute( 'placeholder', $helpTexts['import_premium_only'] );
        $fields[] = Field::make( 'separator', 'bpw_ocl_export_separator', $this->oneClickLogin->translate( 'Export' ) );
        $fields[] = Field::make( 'textarea', 'bpw_ocl_export', $this->oneClickLogin->translate( 'Export settings' ) )->set_default_value( $this->getSettingsInJSON() )->set_help_text( $helpTexts['export'] );
        return $fields;
    }
    
    private function getAdminSettingsTabContent()
    {
        # Required for wp_get_current_user().
        require_once ABSPATH . 'wp-includes/pluggable.php';
        $rolesObject = new \WP_Roles();
        $roles = array_reverse( $rolesObject->get_names() );
        $helpTexts = [
            'allow_new_user_registering'          => $this->oneClickLogin->translate( 'Non-premium version automatically sets subscriber role for all new users.' ),
            'domain_name'                         => $this->oneClickLogin->translate( 'Non-premium version can only use @gmail.com domain.' ),
            'prevent_password_usage'              => $this->oneClickLogin->translate( 'If enabled these users can\'t change their password, reset their password or use their username & password to log in. They can only use One Click Login.<br>For companies it is recommended to enable this as these users can\'t log in after they have been removed from the company (and their G-Suite account has been closed).<br><br><b>You may ignore this setting for selected users by visiting the <a href="#" class="one_click_login_ignored_users_tab">Ignored users</a> tab.</b>' ),
            'hide_by_default'                     => $this->oneClickLogin->translate( 'If enabled, One Click Login will only be visible when using either one of these URLs:<br>' ) . \wp_login_url() . '?<span class="visibility-query-parameter">gl</span>=1<br>' . admin_url() . '?<span class="visibility-query-parameter">gl</span>=1',
            'hide_from_admin'                     => $this->oneClickLogin->translate( 'If enabled, One Click Login admin page can only be visible when using this URL:<br>' ) . \admin_url() . '?<span class="visibility-query-parameter">gl</span>=1',
            'regenerate_password_on_login'        => $this->oneClickLogin->translate( 'When a member of this group logs in, their password will be regenerated with 64 random characters.' ),
            'query_string_parameter_for_unhiding' => $this->oneClickLogin->translate( 'This parameter must be written to the URL for the One Click Login to become visible. This method uses cookies.' ),
            'google_oauth_client_id'              => $this->oneClickLogin->translate( 'Video guide on how to generate Google OAuth Client ID on the ' ) . '<a href="#" class="one_click_login_instructions_tab">Instructions</a> ' . $this->oneClickLogin->translate( 'tab' ),
            'google_oauth_client_secret'          => $this->oneClickLogin->translate( 'Video guide on how to generate Google OAuth Client Secret on the ' ) . '<a href="#" class="one_click_login_instructions_tab">Instructions</a> ' . $this->oneClickLogin->translate( 'tab' ),
        ];
        
        if ( $this->oneClickLogin->canUsePremium() ) {
            $helpTexts['allow_new_user_registering'] = '';
            $helpTexts['domain_name'] = '';
        }
        
        # The maximum amount of domains for free and premium versions.
        $maxDomains = ( $this->oneClickLogin->canUsePremium() ? -1 : 1 );
        $fields = [];
        $fields[] = Field::make( 'separator', 'bpw_ocl_settings_separator', $this->oneClickLogin->translate( 'Settings' ) );
        if ( bpw_ocl_fs()->is_not_paying() ) {
            $fields[] = Field::make( 'html', 'bpw_ocl_free_user_promotion' )->set_html( $this->oneClickLogin->translate( "<p>Consider <a href='" . bpw_ocl_fs()->get_upgrade_url() . "'>upgrading to premium</a> version to enable all features.</p>" ) );
        }
        $fields[] = Field::make( 'text', 'bpw_ocl_google_oauth_client_id', $this->oneClickLogin->translate( 'Google OAuth 2.0 Client ID' ) )->set_attribute( 'placeholder', 'Example: 83958254458-qia410v4tijguo722f8j2ervasrivu2g.apps.googleusercontent.com' )->set_help_text( $helpTexts['google_oauth_client_id'] );
        $fields[] = Field::make( 'text', 'bpw_ocl_google_oauth_client_secret', $this->oneClickLogin->translate( 'Google OAuth 2.0 Client Secret' ) )->set_attribute( 'placeholder', 'Example: kjQahkFlSgVJFHhgVh2qPSdl' )->set_help_text( $helpTexts['google_oauth_client_secret'] );
        $fields[] = Field::make( 'checkbox', 'bpw_ocl_hide_from_admin', $this->oneClickLogin->translate( 'Hide One Click Login from admin sidebar ' ) )->set_option_value( 'yes' )->set_help_text( $helpTexts['hide_from_admin'] );
        $fields[] = Field::make( 'checkbox', 'bpw_ocl_hide_from_login', $this->oneClickLogin->translate( 'Hide One Click Login from login page' ) )->set_option_value( 'yes' )->set_help_text( $helpTexts['hide_by_default'] );
        $fields[] = Field::make( 'text', 'bpw_ocl_query_string_parameter_for_unhiding', $this->oneClickLogin->translate( 'Query string parameter for unhiding' ) )->set_default_value( 'gl' )->set_help_text( $helpTexts['query_string_parameter_for_unhiding'] )->set_classes( [ 'bpw_ocl_disable_for_non_premium' ] )->set_classes( [ 'bpw_ocl_visibility_query_string_parameter' ] )->set_conditional_logic( [
            'relation' => 'OR',
            [
            'field' => 'bpw_ocl_hide_from_admin',
            'value' => true,
        ],
            [
            'field' => 'bpw_ocl_hide_from_login',
            'value' => true,
        ],
        ] );
        $fields[] = Field::make( 'complex', 'bpw_ocl_groups', $this->oneClickLogin->translate( 'One Click Login groups' ) )->set_classes( [ 'bpw_ocl_domain_group' ] )->add_fields( [
            Field::make( 'separator', 'bpw_ocl_basic_separator', $this->oneClickLogin->translate( 'Basic features' ) ),
            Field::make( 'radio', 'bpw_ocl_group_type', $this->oneClickLogin->translate( 'Group type' ) )->set_options( [
            'domain'    => 'Domain',
            'whitelist' => 'Whitelist',
        ] )->set_classes( 'bpw_ocl_disable_for_non_premium' )->set_help_text( 'Domain type lets you add a certain email domain (e.g. @gmail.com or @mycompany.com).<br>Whitelist type lets you add predefined email addresses from various domains (e.g. support@mycompany.com and contact@theircompany.net).' ),
            Field::make( 'text', 'bpw_ocl_domain_name', $this->oneClickLogin->translate( 'Domain' ) )->set_attribute( 'placeholder', $this->oneClickLogin->translate( 'Example: @mycompany.com or @gmail.com' ) )->set_attribute( 'pattern', '^@[^.]+\\..+' )->set_help_text( $helpTexts['domain_name'] )->set_classes( [ 'bpw_ocl_gmail_default_value', 'bpw_ocl_disable_for_non_premium', 'bpw_ocl_domain_name' ] )->set_conditional_logic( [
            'relation' => 'AND',
            [
            'field'   => 'bpw_ocl_group_type',
            'value'   => 'domain',
            'compare' => '=',
        ],
        ] ),
            Field::make( 'complex', 'bpw_ocl_whitelist', $this->oneClickLogin->translate( 'Whitelist' ) )->set_classes( [ 'bpw_ocl_whitelist_group' ] )->add_fields( array( Field::make( 'text', 'bpw_ocl_email_address', $this->oneClickLogin->translate( 'Email address' ) )->set_attribute( 'placeholder', $this->oneClickLogin->translate( 'Example: mycompany@gmail.com or support@mycompany.com' ) )->set_attribute( 'pattern', "^[a-zA-Z0-9.!#\$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*\$" )->set_classes( [ 'bpw_ocl_email_address' ] ) ) )->set_conditional_logic( [
            'relation' => 'AND',
            [
            'field'   => 'bpw_ocl_group_type',
            'value'   => 'whitelist',
            'compare' => '=',
        ],
        ] ),
            Field::make( 'radio', 'bpw_ocl_allow_new_user_registering', $this->oneClickLogin->translate( 'This group can register new accounts?' ) )->add_options( [
            'no'  => $this->oneClickLogin->translate( 'No, they can only log in to their pre-existing accounts.' ),
            'yes' => $this->oneClickLogin->translate( 'Yes, they can log in and register new accounts.' ),
        ] ),
            Field::make( 'separator', 'bpw_ocl_premium_separator', $this->oneClickLogin->translate( 'Premium features' ) ),
            Field::make( 'select', 'bpw_ocl_default_role', $this->oneClickLogin->translate( 'Default role for new users.' ) )->set_options( $roles )->set_conditional_logic( [
            'relation' => 'AND',
            [
            'field'   => 'bpw_ocl_allow_new_user_registering',
            'value'   => 'yes',
            'compare' => '=',
        ],
        ] )->set_classes( 'bpw_ocl_disable_for_non_premium' )->set_default_value( 'subscriber' )->set_help_text( $helpTexts['allow_new_user_registering'] ),
            Field::make( 'radio', 'bpw_ocl_prevent_password_usage', $this->oneClickLogin->translate( 'This group must use One Click Login' ) )->add_options( [
            'no'  => $this->oneClickLogin->translate( 'No, they can also use their username and password.' ),
            'yes' => $this->oneClickLogin->translate( 'Yes, they can log in only with One Click Login and they can\'t use their username & password.' ),
        ] )->set_help_text( $helpTexts['prevent_password_usage'] )->set_classes( 'bpw_ocl_disable_for_non_premium' )->set_default_value( 'no' ),
            Field::make( 'radio', 'bpw_ocl_regenerate_password_on_login', $this->oneClickLogin->translate( 'Regenerate password on login' ) )->add_options( [
            'no'  => $this->oneClickLogin->translate( 'No, their original password is left untouched.' ),
            'yes' => $this->oneClickLogin->translate( 'Yes, when they login a new password will be randomly generated for them.' ),
        ] )->set_help_text( $helpTexts['regenerate_password_on_login'] )->set_classes( 'bpw_ocl_disable_for_non_premium' )->set_default_value( 'no' )->set_conditional_logic( [
            'relation' => 'AND',
            [
            'field'   => 'bpw_ocl_prevent_password_usage',
            'value'   => 'yes',
            'compare' => '=',
        ],
        ] )
        ] )->set_max( $maxDomains );
        return $fields;
    }
    
    private function getOneClickLoginIgnoredUsers()
    {
        if ( empty($this->oneClickLoginIgnoredUsersCache) ) {
            $this->oneClickLoginIgnoredUsersCache = \get_users( [
                'fields'     => [ 'ID', 'user_email' ],
                'meta_query' => [ [
                'key'     => 'one_click_login_ignored_user',
                'value'   => true,
                'compare' => '=',
            ] ],
            ] );
        }
        return $this->oneClickLoginIgnoredUsersCache;
    }
    
    /*
    The admin list is built with Carbon Fields but the save operation is aborted before data gets saved to database.
    The data is not saved on options table (as Carbon Fields does by default) but instead to user_meta with a custom save hook ($this->saveHook()).
    The data is also significally more simplified vs. what Carbon Fields would save.
    */
    private function getAdminIgnoredUsersTabContent()
    {
        # Title
        $oneClickLoginIgnoredUsersFields = [ Field::make( 'separator', 'bpw_ocl_users_separator', $this->oneClickLogin->translate( 'Ignored users' ) ) ];
        
        if ( $this->oneClickLogin->canUsePremium() ) {
        } else {
            $oneClickLoginIgnoredUsersFields[] = Field::make( 'html', 'bpw_ocl_ignored_users_not_premium' )->set_html( $this->oneClickLogin->translate( "<p>You need to <a href='" . bpw_ocl_fs()->get_upgrade_url() . "'>upgrade to premium</a> to use this feature.</p>" ) );
        }
        
        return $oneClickLoginIgnoredUsersFields;
    }
    
    private function getHideFromAdmin()
    {
        
        if ( !isset( $this->hideFromAdminCache ) ) {
            global  $wpdb ;
            $hideFromAdmin = false;
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_bpw_ocl_hide_from_admin' ) );
            if ( !empty($results) && array_key_exists( 0, $results ) ) {
                if ( property_exists( $results[0], 'option_value' ) ) {
                    $hideFromAdmin = $results[0]->option_value;
                }
            }
            
            if ( !empty($hideFromAdmin) && $hideFromAdmin === 'yes' ) {
                $this->hideFromAdminCache = true;
            } else {
                $this->hideFromAdminCache = false;
            }
        
        }
        
        return $this->hideFromAdminCache;
    }
    
    public function getSettingsInJSON()
    {
        global  $wpdb ;
        $settings = [
            'settings'      => [],
            'ignored_users' => [],
        ];
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", '_bpw_ocl_groups%' ) );
        if ( !empty($results) ) {
            foreach ( $results as $result ) {
                $settings['settings'][] = [
                    'name'  => $result->option_name,
                    'value' => $result->option_value,
                ];
            }
        }
        $results = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ('_bpw_ocl_hide_from_login', '_bpw_ocl_hide_from_admin', '_bpw_ocl_google_oauth_client_id', '_bpw_ocl_google_oauth_client_secret', '_bpw_ocl_query_string_parameter_for_unhiding')" );
        if ( !empty($results) ) {
            foreach ( $results as $result ) {
                $settings['settings'][] = [
                    'name'  => $result->option_name,
                    'value' => $result->option_value,
                ];
            }
        }
        # One Click Login ignored users.
        $oneClickLoginIgnoredUsers = $this->getOneClickLoginIgnoredUsers();
        if ( !empty($oneClickLoginIgnoredUsers) ) {
            $settings['ignored_users'] = array_column( $oneClickLoginIgnoredUsers, 'user_email' );
        }
        return json_encode( $settings );
    }

}