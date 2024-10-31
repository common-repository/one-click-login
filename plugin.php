<?php

/**
 * Plugin Name: One Click Login
 * Plugin URI:
 * Description: Allows users to login via their Google accounts (Gmail & G-Suite).
 * Version: 1.24.0
 * Author: Best Plugins WordPress
 */
namespace BestPluginsWordPress;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( function_exists( '\\BestPluginsWordPress\\bpw_ocl_fs' ) ) {
    \BestPluginsWordPress\bpw_ocl_fs()->set_basename( false, __FILE__ );
} else {
    // DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
    
    if ( !function_exists( '\\BestPluginsWordPress\\bpw_ocl_fs' ) ) {
        // Create a helper function for easy SDK access.
        function bpw_ocl_fs()
        {
            global  $bpw_ocl_fs ;
            
            if ( !isset( $bpw_ocl_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                $bpw_ocl_fs = fs_dynamic_init( array(
                    'id'             => '5827',
                    'slug'           => 'one-click-login',
                    'premium_slug'   => 'one-click-login-premium',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_34d84dbc1fd4db4e1647c58103b96',
                    'is_premium'     => false,
                    'premium_suffix' => 'Premium',
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'trial'          => array(
                    'days'               => 7,
                    'is_require_payment' => true,
                ),
                    'menu'           => array(
                    'slug'    => 'crb_carbon_fields_container_one_click_login.php',
                    'support' => false,
                ),
                    'is_live'        => true,
                ) );
            }
            
            return $bpw_ocl_fs;
        }
        
        // Init Freemius.
        \BestPluginsWordPress\bpw_ocl_fs();
        // Signal that SDK was initiated.
        do_action( 'BestPluginsWordPress\\bpw_ocl_fs_loaded' );
        require_once 'src/OneClickLogin.php';
        $oneClickLogin = new \BestPluginsWordPress\OneClickLogin();
        $oneClickLogin->init();
        register_activation_hook( __FILE__, function () use( $oneClickLogin ) {
            $importData = getenv( "ONE_CLICK_LOGIN_IMPORT_ON_PLUGIN_ACTIVATION" );
            if ( !empty($importData) ) {
                # Prevent importing if there's already a client_id in the database.
                
                if ( !get_option( '_bpw_ocl_google_oauth_client_id' ) ) {
                    require_once 'src/OneClickLoginAdmin.php';
                    $oneClickLoginAdmin = new \BestPluginsWordPress\OneClickLoginAdmin( $oneClickLogin );
                    $_POST['import_data'] = $importData;
                    $_POST['nonce'] = wp_create_nonce( 'ajax-nonce' );
                    $oneClickLogin->testing = true;
                    $oneClickLogin->importFromEnv = true;
                    $oneClickLoginAdmin->ajaxImportCallbackAdmin();
                    $oneClickLogin->testing = false;
                    $oneClickLogin->importFromEnv = false;
                    unset( $_POST['import_data'] );
                    unset( $_POST['nonce'] );
                    unset( $oneClickLoginAdmin );
                }
            
            }
        } );
    }

}

\BestPluginsWordPress\bpw_ocl_fs()->add_action( 'after_uninstall', function () {
    global  $wpdb ;
    # Delete settings related to domains.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_bpw_ocl_groups%' ) );
    # Delete other settings
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name IN ('_bpw_ocl_hide_from_login', '_bpw_ocl_hide_from_admin', '_bpw_ocl_google_oauth_client_id', '_bpw_ocl_google_oauth_client_secret')" );
    # Delete user meta.
    $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'one_click_login_ignored_user'" );
} );