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
//defined( 'ABSPATH' ) or die( 'Access not allowed!' );


//Create WP Sandbox menu
function wp_sandbox_admin_add_page() {
    global $my_plugin_hook;
    $my_plugin_hook = add_menu_page('Wp  Sandbox','Wp  Sandbox','manage_options','wp-sandbox','wp_sandbox_options_page','',81);
}
add_action('admin_menu', 'wp_sandbox_admin_add_page');

//testing time for  users in sec
$sandboxeLiveSec = get_option('sandboxTime',20)*60;


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
    if(@$_SESSION['sandboxUnlocked']!==true){
        $restricted = json_decode(get_option('sandboxRestrict','[]'));
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


////Fix reauth bug!
//if(!function_exists('auth_redirect')) {
//    function auth_redirect(){}
//}


// Restrict access to defined pages for sandbox-user
add_action( 'admin_init', 'wp_sandbox_restrict_admin_with_redirect' );
function wp_sandbox_restrict_admin_with_redirect() {
    $restricted = json_decode(get_option('sandboxRestrict','[]'));
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


//Delete old sessions that logged in for a long time
function wp_sandbox_deleteOldSessions(){
    global $wpdb,$table_prefix,$sandboxeLiveSec;

    $result = $wpdb->get_results("show tables like '%options'",ARRAY_N);
    foreach($result as $row ){
        $name = $row[0];
        $deleteSessions = $wpdb->get_results("select * from `".$name."` where option_name='sandboxStartTime' and option_value + $sandboxLiveSec < UNIX_TIMESTAMP() ");
        if($deleteSessions){
            $sessionName = substr($name,0,-8);
            if($sessionName != $table_prefix) {
                $tables = $wpdb->get_results("show tables like '$sessionName%'", ARRAY_N);
                foreach ($tables as $table) {
                    $tableName = $table[0];
                    $wpdb->get_results("drop table IF EXISTS `$tableName`", ARRAY_N);
                }
            }
        }
    }
    $result = $wpdb->get_results("show tables like '".$_SESSION['table_prefix']."%'",ARRAY_N);
    if(!$result){
        $_SESSION['table_prefix'] = '';
    }
}



//Remove Media tabs
add_filter('media_view_strings','wp_sandbox_remove_media_tabs');
function wp_sandbox_remove_media_tabs($strings) {
    unset($strings["insertFromUrlTitle"]);
    unset($strings["setFeaturedImageTitle"]);
    unset($strings["createGalleryTitle"]);
    unset($strings["uploadFilesTitle"]);
    return $strings;
}



//Restrict test  user's upload for security reasons
add_filter( 'wp_handle_upload_prefilter', 'wp_sandbox_only_upload_for_admin' );
function wp_sandbox_only_upload_for_admin( $file ) {
    if ( ! current_user_can( 'upload_file' ) ) {
        $file['error'] = 'You can\'t upload without admin privileges!';
    }
    return $file;
}

//create a timing counter into adminbar area
function wp_sandbox_js(){
    global $sandboxLiveSec;
    if(@$_SESSION['sandboxUnlocked']===true){
        return;
    }
    $time = get_option('sandboxStartTime');
    $time = $time + $sandboxLiveSec - time() ;
    $mins = floor($time / 60);
    $secs = floor($time % 60);
    ?>
    <script type='text/javascript'>
        window.addEventListener("load", function(){
            var div = document.createElement('div'),
                adminBar = document.getElementById('wpadminbar');
            if(adminBar != undefined){
                div.style.float = 'right';
                div.style.color = '#fff';
                div.style.fontWeight = 'bold';
                div.style.fontSize = '13px';
                div.setAttribute('id','sandboxTimer');
                adminBar.appendChild(div);
            }else{
                document.body.appendChild(div);
                div.style.position = "fixed";
                div.style.left = "50%";
                div.style.top = "0px";
                div.style.transform = "translateX(-50%)";
                div.setAttribute('id','sandboxTimer');
                div.style.background = "#900";
            }
        });
        function startTimer(duration, display) {
            var timer = duration, minutes, seconds;
            var interval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;
                if(display){
                    display.innerHTML = minutes + ":" + seconds + " Remaining for sandbox";
                }

                if (--timer < 0) {
                    window.onbeforeunload = null;
                    window.location = '<?php echo home_url()?>';
                    clearInterval(interval);
                    //timer = duration;
                }
            }, 1000);
        }

        window.onload = function () {
            var time = <?php echo esc_attr($time);?>,
                display = document.querySelector('#sandboxDriveTimer');
            startTimer(time, display);
        };
    </script>
<?php
}
add_action( 'admin_print_scripts', 'wp_sandbox_js' );

//Retrive User Ip Address
function wp_sandbox_get_ip_address(){
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
        if (array_key_exists($key, $_SERVER) === true){
            foreach (explode(',', $_SERVER[$key]) as $ip){
                $ip = trim($ip); // just to be safe
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                    return $ip;
                }
            }
        }
    }
    return '127.0.0.1';
}

//Create WP Sandbox page
function wp_sandbox_options_page(){
    ?>
    <div class="wrap">
        <?php
        if(isset($_POST['username'])){
            $user = wp_authenticate($_POST['username'],$_POST['password']);
            if(is_wp_error($user)){
                echo "Authentication Faild!";
            }else{
                $meta = (get_user_meta($user->ID,'wp_capabilities',true));
                if($meta['administrator']===true){
                    $_SESSION['sandboxUnlocked'] = true;
                }
            }
        }
        if(isset($_POST['active']) && @$_SESSION['sandboxUnlocked']===true){
            unset($_SESSION['sandboxUnlocked']);
            $unset = true;
        }
        if(isset($_POST['save']) && @$_SESSION['sandboxUnlocked']===true){
            $restricted = $_POST['restricted'];

            if(is_array($restricted)) {
                update_option('sandboxRestrict', json_encode($restricted));
            }
            if($_POST['time'] > 0) {
                $time = (int)$_POST['time'];
                update_option('sandboxTime', $time);
            }
        }
        if(isset($_POST['deactive']) && @$_SESSION['sandboxUnlocked']===true) {
            $user = wp_authenticate('test-drive-user','test-password');
            if(!is_wp_error($user)) {
                wp_delete_user($user->ID);
            }
            deactivate_plugins(plugin_basename(__FILE__));
        }
        $restricted = json_decode(get_option('sandboxRestrict','[]'));
        $time = get_option('sandboxTime',15);
        ?>
        <h1>Test Drive Sandbox</h1>
        <form action="" method="post">
            <?php
            if(@$_SESSION['sandboxUnlocked']===true){
                ?>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="deactive">Deactive</label></th>
                        <td>
                            <button name="deactive" type="submit" id="deactive" class="button button-danger">
                                <span class="dashicons dashicons-trash"></span> Deactive WP Sandbox
                            </button>
                        </td>
                    </tr>
                    <tbody>
                </table>
            <hr>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="time">Time for test</label></th>
                        <td>
                            <input name="time" type="number" id="time" value="<?php echo esc_attr($time);?>">
                            <p><span class="dashicons dashicons-backup"></span> in minutes</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Restricted areas:</label></th>
                        <td>
                            <label><input <?php echo in_array('Posts',$restricted)?'checked="checked"':''?> name="restricted[]" type="checkbox" id="Posts" value="Posts"> Posts</label><br>
                            <label><input <?php echo in_array('Pages',$restricted)?'checked="checked"':''?> name="restricted[]" type="checkbox" id="Pages" value="Pages"> Pages</label><br>
                            <label><input <?php echo in_array('Comments',$restricted)?'checked="checked"':''?> name="restricted[]" type="checkbox" id="Comments" value="Comments"> Comments</label><br>
                            <label><input <?php echo in_array('Appearance',$restricted)?'checked="checked"':''?> name="restricted[]" type="checkbox" id="Appearance" value="Appearance"> Appearance</label><br>
                            <label><input <?php echo in_array('Plugins',$restricted)?'checked="checked"':''?> name="restricted[]" type="checkbox" id="Plugins" value="Plugins"> Plugins</label><br>
                            <label><input <?php echo in_array('Users',$restricted)?'checked="checked"':''?> name="restricted[]" type="checkbox" id="Users" value="Users"> Users</label><br>
                            <label><input <?php echo in_array('Tools',$restricted)?'checked="checked"':''?> name="restricted[]" type="checkbox" id="Tools" value="Tools"> Tools</label><br>
                            <label><input <?php echo in_array('Settings',$restricted)?'checked="checked"':''?> name="restricted[]" type="checkbox" id="Settings" value="Settings"> Settings</label>
                            <p>We are also restrict visitors access to:
                                <b>Deactive plugins</b>, <b>Install plugins</b>, <b>Edit plugins</b>,
                                <b>Deactivate themes</b>, <b>Install themes</b>, <b>Edit theme</b>, <b>Upload Media</b>, <b>Delete Media</b>,
                                <b>Edit Media</b>, <b>Create User</b>, <b>Delete User</b>, <b>Edit User</b>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button name="save" type="submit" id="save" class="button button-primary">
                                <span class="dashicons dashicons-edit"></span> Save
                            </button>

                            <button name="active" type="submit" id="active" class="button">
                                <span class="dashicons dashicons-clock"></span> Save & Active
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            <?php
            }else{
            if(@$unset === true){
            ?>
                <script>
                    alert('Changes will affect after remaining time for test.');
                </script>
            <?php
            }
            ?>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="username">Admin Username</label></th>
                        <td>
                            <input name="username" type="text" id="username" value="" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="password">Admin Password</label></th>
                        <td>
                            <input name="password" type="password" id="password" value="" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button name="unlock" type="submit" id="unlock" class="button button-primary">
                                <span class="dashicons dashicons-unlock"></span> Unlock
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            <?php
            }
            ?>
        </form>
    </div>
<?php
}