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


function my_simple_domain_check($errors, $sanitized_user_login, $user_email) {
    //fill out this list with any problem domains
    $spamdomains = array(
        '.li',
        '.website',
        '.ru'
    );
    $ip = isset($_SERVER['X-Forwarded-For']) ? $_SERVER['X-Forwarded-For'] : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
    foreach ($spamdomains as $domain) {
        if ( strpos($user_email, $domain) !== false ) {
            $errors->add( 'spam_error', __('<strong>ERROR</strong>: Registration halted, please contact support.','mydomain') );
            error_log('SPAMALERT: [email:' . $user_email . ', IP Address: ' .  $ip .']');
        } else { 
            error_log('NEWUSER: [email:' . $user_email . ', IP Address: ' .  $ip .']');
        }
    }

    return $errors;
}

add_filter('registration_errors', 'my_simple_domain_check', 10, 3);