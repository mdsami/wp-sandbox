<?php

/*
Plugin Name: Wp Sandbox
Plugin URI: http://wincred.com/plugins
Description: Allows non-logged in users to view your wordpress admin area,
Version: 1.0
Author: mdsami
Author URI: http://mdsami.me
License: A "Slug" license name e.g. GPL2
*/


// Prohibit direct file accessing.
defined( 'ABSPATH' ) or die( 'Access not allowed!' );


//testing time for  users in sec
$wpSandbocSec = get_option('sandboxTime',20)*60;


//Add Action/filter Hook
register_activation_hook(__FILE__,'wp_sandbox_activation');
function wp_sandbox_activation(){
    global $wp_roles;
    if ( ! isset( $wp_roles ) ){
        $wp_roles = new WP_Roles();
    }

    $adm = $wp_roles->get_role('administrator');

    // Adding a new role with all admin caps.
    $wp_roles->add_role('sandbox-user-role', 'My Custom Role', $adm->capabilities);


    //Remove Critical roles from sandbox-user-role
    $wp_roles->remove_cap( 'sandbox-user-role', 'delete_others_posts' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'edit_others_posts' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'upload_file' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'update_plugins' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'update_themes' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'install_plugins' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'install_themes' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'delete_plugins' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'edit_plugins' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'edit_files' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'edit_users' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'create_users' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'delete_users' );
    $wp_roles->remove_cap( 'sandbox-user-role', 'unfiltered_html' );

    //Create user for sandbox
    if(!( username_exists('sandbox-test-user') )){
        wp_create_user( 'sandbox-test-user', 'test-password', 'user@exmaple.com' );
        wp_update_user( array ( 'ID' => username_exists('sandbox-test-user'), 'role' => 'sandbox-user-role' ) ) ;
    }

}

//Login visitors without username and password

add_action( 'init', 'wp_sandbox_login' );
function wp_sandbox_login(){
    global $wpdb;
    if (!( is_user_logged_in() )) {
        $creds = array(
            'user_login' => 'sandbox-test-user',
            'user_password' => 'test-password'
        );
        wp_signon( $creds, false );
    }
    if(@$_GET['sandbox'] == 'true'){
        wp_redirect(admin_url());
        exit();
    }
}