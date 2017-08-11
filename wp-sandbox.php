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
    $wp_roles->add_role('sandbox-user', 'My Custom Role', $adm->capabilities);





}