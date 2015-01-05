<?php 
/**
 * @package simple-email-filter.php
 * @version 0.1
 */
/*
Plugin Name: Simple Email Filter
Plugin URI: https://github.com/EdgeCaseBerg/simple-email-filter
Description: Deny's registration to user's with emails that match a filter
Author: Ethan J. Eldridge
Version: 0.1
Author URI: http://ethanjoachimeldridge.info
*/

/* Registrar and Login's */
add_filter('registration_errors', 'sef_domain_check_for_registration', 10, 3);
add_filter('authenticate', 'sef_track_login_ip', 30, 3 );

/* User List Page Modifications */
add_filter('manage_users_columns', 'sef_add_last_ip_column');
add_action('manage_users_custom_column',  'sef_last_ip_column_value', 10, 3);
add_filter('user_row_actions', 'sef_userlist_actions',10, 2);

/* Add menu pages in admin area */
add_action('admin_menu', 'sef_register_subpages');

/* Conf */
if (!defined('SEF_META_PREFIX')) define('SEF_META_PREFIX', 'sef-');
if (!defined('SEF_BAN_MESSAGE')) defined('SEF_BAN_MESSAGE', 'It seem\'s you\'ve messed up and are banned, contact the site owner');

function __sef_get_ip() {
	return isset($_SERVER['X-Forwarded-For']) ? 
				$_SERVER['X-Forwarded-For'] : 
				(isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? 
					$_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
}

function sef_domain_check_for_registration($errors, $sanitized_user_login, $user_email) {
    //fill out this list with any problem domains
    $spamdomains = array(
        '.li',
        '.website',
        '.ru'
    );
    $ip = __sef_get_ip();
    foreach ($spamdomains as $domain) {
        if ( strpos($user_email, $domain) !== false ) {
            $errors->add( 'spam_error', __(st/'<strong>ERROR</strong>: Registration halted, please contact support.','sef_domain') );
            error_log('SPAMALERT: [email:' . $user_email . ', IP Address: ' .  $ip .']');
        } else { 
            error_log('NEWUSER: [email:' . $user_email . ', IP Address: ' .  $ip .']');
        }
    }

    return $errors;
}

function sef_track_login_ip($user, $username, $password ) {
	if (!is_null($user) && !is_wp_error($user)) {
		if ( update_user_meta($user->ID, SEF_META_PREFIX . 'ipaddr', __sef_get_ip()) === false) {
			error_log('SEF: Failed to save ip meta data for user ' . $user->ID);
		}

		if ( get_user_meta($user->ID, SEF_META_PREFIX . 'banned')) {
			$user = new WP_Error( ':(', __( SEF_BAN_MESSAGE, "sef_domain" ) );
		}
	}
    return $user;
}


function sef_add_last_ip_column($columns) {
    $columns['last_ip'] = 'Last Known IP';
    return $columns;
}
 

function sef_last_ip_column_value($value, $column_name, $user_id) {
	if ( 'last_ip' == $column_name )
		return get_user_meta($user_id, SEF_META_PREFIX . 'ipaddr', true);
    return $value;
}



function sef_userlist_actions($actions, $user_object ) {
	if (get_user_meta($user_object->ID, SEF_META_PREFIX . 'banned', true)) {
		$actions['unban user'] = "<a class='' href='" . admin_url( 'admin.php?page=unban-user&user='.$user_object->ID) . "'>" . __( 'UnBan User' ) . "</a>";
	} else {
		$actions['ban user'] = "<a class='' href='" . admin_url( 'admin.php?page=ban-user&user='.$user_object->ID) . "'>" . __( 'Ban User' ) . "</a>";
	}
    return $actions;
}

function sef_register_subpages() {
   
	add_submenu_page( 
          null   //or 'options.php' 
        , 'User has been banned'
        , 'Ban User' 
        , 'manage_options'
        , 'ban-user'
        , '__ban_user_page'
    );

    add_submenu_page( 
          null   //or 'options.php' 
        , 'User has been unbanned'
        , 'UnBan User' 
        , 'manage_options'
        , 'unban-user'
        , '__unban_user_page'
    );
}

function __ban_user_page() {
	if ( current_user_can( 'manage_options' ) && isset($_GET['user']) ) {
		$user_id = intval($_GET['user']);
		if (update_user_meta($user_id, SEF_META_PREFIX .'banned', true) === false ) {
			error_log('Could not ban user [id:' . $user_id . '] for some reason.');
			echo '<div class="error">Could not ban user</div>';
		} else {
			echo '<div class="updated">User banned</div>';
		}
		echo '<br><p>Go back to the <a href="' . admin_url('users.php') . '">list</a> page</p>';
	}
}

function __unban_user_page() {
	if ( current_user_can( 'manage_options' ) && isset($_GET['user']) ) {
		$user_id = intval($_GET['user']);
		if (update_user_meta($user_id, SEF_META_PREFIX .'banned', false) === false ) {
			error_log('Could not unban user [id:' . $user_id . '] for some reason.');
			echo '<div class="error"><p>Could not unban user</p></div>';
		} else {
			echo '<div class="updated"><p>User unbanned</p></div>';
		}
		echo '<br><p>Go back to the <a href="' . admin_url('users.php') . '">list</a> page</p>';
	}
}