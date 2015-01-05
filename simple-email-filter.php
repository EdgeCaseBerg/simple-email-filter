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

add_filter('registration_errors', 'sef_domain_check_for_registration', 10, 3);
add_filter('authenticate', 'sef_track_login_ip', 30, 3 );
add_filter('manage_users_columns', 'sef_add_last_ip_column');
add_action('manage_users_custom_column',  'sef_last_ip_column_value', 10, 3);

define('SEF_META_PREFIX', 'sef-');

function _sef_get_ip() {
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
    $ip = _sef_get_ip();
    foreach ($spamdomains as $domain) {
        if ( strpos($user_email, $domain) !== false ) {
            $errors->add( 'spam_error', __(st/'<strong>ERROR</strong>: Registration halted, please contact support.','mydomain') );
            error_log('SPAMALERT: [email:' . $user_email . ', IP Address: ' .  $ip .']');
        } else { 
            error_log('NEWUSER: [email:' . $user_email . ', IP Address: ' .  $ip .']');
        }
    }

    return $errors;
}

function sef_track_login_ip( $user, $username, $password ) {
	if (!is_null($user) && !is_wp_error($user)) {
		if ( update_user_meta($user->ID, SEF_META_PREFIX . 'ipaddr', _sef_get_ip()) === false) {
			error_log('SEF: Failed to save ip meta data for user ' . $user->ID);
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