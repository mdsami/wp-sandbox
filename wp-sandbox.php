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

//init plugin and run
add_action( 'init', 'wp_sandbox_init' );
function wp_sandbox_init() {
    global $table_prefix,$wpdb ;
    if(session_id() == ''){
        session_start();
    }

    if(@$_SESSION['table_prefix'] == $table_prefix){
        die('Cheating huh?');
    }
    //Delete old sessions that logged in for a long time
    wp_sandbox_deleteOldSessions();

    //Set table_prefix by user ip address
    if(@$_SESSION['table_prefix']!=''){
        $_SESSION['table_prefix'] = str_replace(":","_",wp_sandbox_get_ip_address().'_');
        $_SESSION['table_prefix'] = str_replace(".","_",$_SESSION['table_prefix']);
        $_SESSION['table_prefix'] = '_'.$_SESSION['table_prefix'];
        if(@$_SESSION['table_prefix'] == $table_prefix){
            die('Cheating huh?');
        }
        $_SESSION['table_prefix'] = esc_sql($_SESSION['table_prefix']);
        if(@$_SESSION['sandboxUnlocked']!==true){
            $wpdb->set_prefix($_SESSION['table_prefix']);
        }
    }

    //Create test tables for each user
    if ( is_admin() && @$_SESSION['table_prefix']=='') {
        $_SESSION['table_prefix'] = str_replace(":","_",wp_sandbox_get_ip_address().'_');
        $_SESSION['table_prefix'] = str_replace(".","_",$_SESSION['table_prefix']);
        $_SESSION['table_prefix'] = '_'.$_SESSION['table_prefix'];
        $result = $wpdb->get_results("show tables like '".$wpdb->prefix."%'",ARRAY_N);
        foreach($result as $row ){
            $name = substr($row[0],strlen($wpdb->prefix));
            $wpdb->get_results("CREATE TABLE IF NOT EXISTS `".$_SESSION['table_prefix'].$name."` LIKE ".$wpdb->prefix.$name);
            $wpdb->get_results("INSERT ignore `".$_SESSION['table_prefix'].$name."` SELECT * FROM ".$wpdb->prefix.$name);
        }

        //Set start time of test for each user
        $wpdb->get_results("INSERT ignore into ".$_SESSION['table_prefix']."options(option_name,option_value) values('sandboxStartTime',UNIX_TIMESTAMP())");
        $table_prefix  = $_SESSION['table_prefix'];
        if(@$_SESSION['sandboxUnlocked']!==true){
            $wpdb->set_prefix($table_prefix);
        }
    }
    //Restrict access to the edit files
    if(@$_SESSION['sandboxUnlocked']!==true){
        wp_sandbox_setPermission();
    }
    wp_cache_flush();
}

//Restrict access to the edit files
function wp_sandbox_setPermission(){
    if(!defined('DISALLOW_FILE_EDIT')){
        define( 'DISALLOW_FILE_EDIT', true );
    }else{
        echo "Please set DISALLOW_FILE_EDIT to TRUE";
    }
    if(!defined('DISALLOW_FILE_MODS')){
        define( 'DISALLOW_FILE_MODS', true );
    }else{
        echo "Please set DISALLOW_FILE_MODS to TRUE";
    }
    if(!defined('AUTOMATIC_UPDATER_DISABLED')){
        define( 'AUTOMATIC_UPDATER_DISABLED', true );
    }else {
        echo "Please set AUTOMATIC_UPDATER_DISABLED to TRUE";
    }

}

// Remove Restricted pages from navbar
add_action('admin_menu', 'wp_sandbox_remove_menus');
function wp_sandbox_remove_menus(){
    global $menu;
    if(@$_SESSION['sandboxDriveUnlocked']!==true){
        $restricted = json_decode(get_option('sandboxDriveRestrict','[]'));
        foreach ($restricted as $key => $value) {
            $restricted[$key] = __($value);
        }
        end ($menu);
        while (prev($menu)){
            $value = explode(' ',$menu[key($menu)][0]);
            if(in_array($value[0] != NULL?$value[0]:"" , $restricted)){unset($menu[key($menu)]);}
        }
    }
}


//Fix reauth bug!
if(!function_exists('auth_redirect')) {
    function auth_redirect(){}
}


// Restrict access to defined pages for sandbox-user
add_action( 'admin_init', 'wp_sandbox_restrict_admin_with_redirect' );
function wp_sandbox_restrict_admin_with_redirect() {
    $restricted = json_decode(get_option('sandboxDriveRestrict','[]'));
    $restrictions[] = '/wp-admin/ms-delete-site.php';
    if(in_array('Posts',$restricted)){
        $restrictions[] = '/wp-admin/edit.php';
        $restrictions[] = '/wp-admin/post-new.php';
        $restrictions[] = '/wp-admin/edit-tags.php';
        $restrictions[] = '/wp-admin/edit-tag-form.php';
    }

    if(in_array('Pages',$restricted)){
        $restrictions[] = '/wp-admin/edit.php?post_type=page';
        $restrictions[] = '/wp-admin/post-new.php?post_type=page';
    }

    if(in_array('Comments',$restricted)){
        $restrictions[] = '/wp-admin/edit-comments.php';
    }

    if(in_array('Appearance',$restricted)){
        $restrictions[] = '/wp-admin/themes.php';
        $restrictions[] = '/wp-admin/widgets.php';
        $restrictions[] = '/wp-admin/nav-menus.php';
        $restrictions[] = '/wp-admin/theme-editor.php';
    }

    if(in_array('Plugins',$restricted)){
        $restrictions[] = '/wp-admin/plugins.php';
        $restrictions[] = '/wp-admin/plugin-install.php';
    }

    if(in_array('Users',$restricted)){
        $restrictions[] = '/wp-admin/users.php';
        $restrictions[] = '/wp-admin/ms-users.php';
        $restrictions[] = '/wp-admin/user-new.php';
    }

    if(in_array('Tools',$restricted)){
        $restrictions[] = '/wp-admin/tools.php';
        $restrictions[] = '/wp-admin/import.php';
        $restrictions[] = '/wp-admin/export.php';
    }

    if(in_array('Setting',$restricted)){
        $restrictions[] = '/wp-admin/options_general.php';
        $restrictions[] = '/wp-admin/options-writing.php';
        $restrictions[] = '/wp-admin/options-reading.php';
        $restrictions[] = '/wp-admin/options-privacy.php';
        $restrictions[] = '/wp-admin/options-permalink.php';
    }

    foreach ( $restrictions as $restriction ) {
        if ( ! current_user_can( 'manage_network' ) && strpos($_SERVER['PHP_SELF'],$restriction) !== false ) {
            wp_redirect( admin_url() );
            exit;
        }
    }
}



//Restrict access to the delete media files
add_action('media_row_actions','wp_sandbox_users_own_attachments', 2, 1);
function wp_sandbox_users_own_attachments( $wp_query_obj ) {
    unset($wp_query_obj['delete']);
    return $wp_query_obj;
}