<?php 
/**
 * @package simple-email-filter.php
 * @version 0.1
 */
/*
Plugin Name: Simple Email Filter & User Ban
Plugin URI: https://github.com/EdgeCaseBerg/simple-email-filter
Description: Deny's registration to user's with emails that match a filter and ban users from logging in
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

/* Setup settings page */
add_action( 'admin_menu', 'sef_add_admin_menu' );
add_action( 'admin_init', 'sef_settings_init' );


/* Conf */
if (!defined('SEF_META_PREFIX')) define('SEF_META_PREFIX', 'sef-');
if (!defined('SEF_BAN_MESSAGE')) define('SEF_BAN_MESSAGE', 'It seem\'s you\'ve messed up and are banned, contact the site owner');
if (!defined('SEF_DENY_MESSAGE')) define('SEF_DENY_MESSAGE', 'Registration halted, please contact support.');

function __sef_get_ip() {
	return isset($_SERVER['X-Forwarded-For']) ? 
				$_SERVER['X-Forwarded-For'] : 
				(isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? 
					$_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
}

function sef_domain_check_for_registration($errors, $sanitized_user_login, $user_email) {
    $spamdomains = get_option( 'sef_denied_domains' );
    $ip = __sef_get_ip();
    $emailParts = explode('@', $user_email);
    if (count($emailParts) == 2) {
    	$userDomain = $emailParts[1];
    	foreach ($spamdomains as $domain) {
        	if ( strpos($userDomain, $domain) !== false ) {
	            $errors->add( 'spam_error', __('<strong>ERROR</strong>: ' . SEF_DENY_MESSAGE,'sef_domain') );
            	error_log('SPAMALERT: [email:' . $user_email . ', IP Address: ' .  $ip .']');
        	}
    	}
    } else {
    	$errors->add( 'invalid email', __('<strong>ERROR</strong> Emails must have an @ sign'), 'sef_domain');
    }

    return $errors;
}

function sef_track_login_ip($user, $username, $password ) {
	if (!is_null($user) && !is_wp_error($user)) {
		update_user_meta($user->ID, SEF_META_PREFIX . 'ipaddr', __sef_get_ip());	

		$banned = get_user_meta($user->ID, SEF_META_PREFIX . 'banned', true);
		if ( $banned === true || intval($banned) === 1 ) {
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


function sef_add_admin_menu() { 
	add_options_page( 'Simple Email Filter & User Ban', 'Registration Denials', 'manage_options', 'simple_email_filter_and_user_ban', 'sef_options_page' );
}

function sef_settings_init() { 
	register_setting( 'pluginPage', 'sef_denied_domains' );

	add_settings_section(
		'sef_pluginPage_section', 
		__( 'Update denied registration domains', 'sef_domain' ), 
		'sef_denied_domains_callback', 
		'pluginPage'
	);

	add_settings_field( 
		'sef_text_field_0', 
		__( 'Set and remove domains from registration denial', 'sef_domain' ), 
		'sef_render_option_page', 
		'pluginPage', 
		'sef_pluginPage_section' 
	);
}

function sef_render_option_page() { 
	$options = get_option( 'sef_denied_domains' );
	if(!is_array($options)) {
		$options = array();
	}
	?>
		<input id="sef-add-btn" type="button" class="button secondary" value="Add">
		<input id="sef-domain-text" type='text' name='new-domain' value='' placeholder='Domain to Deny'>
		<ul id="sef-domain-list">
			<?php
			foreach ($options as $domain) {
				?>
				<li>
					<input name="sef_denied_domains[]" type="hidden" value="<?php echo $domain; ?>" />
					<button class="button">Remove </button>&nbsp;
					<?php echo $domain; ?>
				</li>
				<?php
			}
			?>
		</ul>
		<script type="text/javascript">
			jQuery(document).ready( function($){
				$('#sef-add-btn').click( function(evt){
					evt.preventDefault()
					var domain = $('#sef-domain-text').val()
					var newLI = $(
						'<li>' + 
							'<input name="sef_denied_domains[]" type="hidden" value="'  + domain + '" />' +
							'<button class="button">Remove </button>&nbsp;' +
							domain +

						'</li>'
					)
					newLI.hide()
					$('#sef-domain-list').append(
						newLI
					)
					newLI.fadeIn()
					$('#sef-domain-text').val('')
					return false
				})
				$('#sef-domain-list').on('click', 'button', function(evt){
					evt.preventDefault()
					$(this).parent().fadeOut(500, function(){ $(this).remove() })
					return false
				})
			})
		</script>
	<?php
}

function sef_denied_domains_callback() { 
	echo __( 'Deny domains from registering on your site, use the input field below and click update when done', 'sef_domain' );
}

function sef_options_page() { 
	?>
	<form action='options.php' method='post'>
		
		<h2>Simple Email Filter &amp; User Ban</h2>
		
		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );
		submit_button();
		?>
		
	</form>
	<?php
}