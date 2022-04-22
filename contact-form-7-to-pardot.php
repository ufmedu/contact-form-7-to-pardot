<?php
/*
Author: Universidad Francisco Marroquín
Author URI: https://ufm.edu
Description: Contact Form 7 Leads to Pardot Form Handlers
Domain Path:
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true
Plugin Name: Contact Form 7 to Pardot
Plugin URI: https://github.com/ufmedu/contact-form-7-to-pardot
Requires at least: 5.6
Requires PHP: 5.6
Text Domain: contact-form-7-to-pardot
Version: 0.4.22.1
*/

defined('ABSPATH') or die('Hi there! I\'m just a plugin, not much I can do when called directly.');

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

add_action('init', function(){
	if(!class_exists('Puc_v4_Factory')){
		require_once(plugin_dir_path(__FILE__) . 'includes/plugin-update-checker-4.11/plugin-update-checker.php');
	}
	Puc_v4_Factory::buildUpdateChecker('https://github.com/ufmedu/contact-form-7-to-pardot', __FILE__, 'contact-form-7-to-pardot');
	$at_least_one = false;
	$utm_params = ['utm_campaign', 'utm_content', 'utm_id', 'utm_medium', 'utm_source', 'utm_term'];
	foreach($utm_params as $utm_param){
		if(isset($_GET[$utm_param])){
			$at_least_one = true;
			break;
		}
	}
	if(!$at_least_one){
		return;
	}
	$cookie_prefix = 'contact_form_7_to_pardot_';
	$past = time() - YEAR_IN_SECONDS;
	foreach($utm_params as $utm_param){
		if(isset($_COOKIE[$cookie_prefix . $utm_param . '_' . COOKIEHASH])){
			setcookie($cookie_prefix . $utm_param . '_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN);
		}
	}
	$cookie_lifetime = time() + (14 * DAY_IN_SECONDS);
	$secure = ('https' === parse_url(home_url(), PHP_URL_SCHEME));
	foreach($utm_params as $utm_param){
		if(isset($_GET[$utm_param])){
			$value = $_GET[$utm_param];
			if($value){
				$value = wp_unslash($value);
				$value = esc_attr($value);
				setcookie($cookie_prefix . $utm_param . '_' . COOKIEHASH, $value, $cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN, $secure);
			}
		}
	}
});

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

add_action('wpcf7_mail_sent', function($contact_form){
	$url = $contact_form->pref('contact_form_7_to_pardot_endpoint_url');
	if(!wpcf7_is_url($url)){
		return;
	}
	$submission = WPCF7_Submission::get_instance();
	if(!$submission){
		return;
	}
	$body = $submission->get_posted_data();
	$cookie_prefix = 'contact_form_7_to_pardot_';
	$utm_params = ['utm_campaign', 'utm_content', 'utm_id', 'utm_medium', 'utm_source', 'utm_term'];
	foreach($utm_params as $utm_param){
		if(isset($_COOKIE[$cookie_prefix . $utm_param . '_' . COOKIEHASH])){
			$body[$utm_param] = $_COOKIE[$cookie_prefix . $utm_param . '_' . COOKIEHASH];
		} else {
			$body[$utm_param] = '';
		}
	}
	$args = [
		'body' => apply_filters('contact_form_7_to_pardot_posted_data', $body, $contact_form, $submission),
		'headers' => [
			'Referer' => $submission->get_meta('url'),
		],
		'timeout' => 30,
	];
	$response = wp_remote_post($url, $args);
	if(is_wp_error($response)){
		$submission->set_response($response->get_error_message());
        $submission->set_status('aborted');
		return;
	}
	$code = wp_remote_retrieve_response_code($response);
	if($code < 200 or $code >= 300){
		$message = wp_remote_retrieve_response_message($response);
        if(!$message){
            $message = get_status_header_desc($code);
        }
        if(!$message){
            $message = __('Something went wrong.');
        }
		$submission->set_response($message);
        $submission->set_status('aborted');
		do_action('contact_form_7_to_pardot_failed', $contact_form, $response, $submission);
		return;
    }
	do_action('contact_form_7_to_pardot_sent', $contact_form, $response, $submission);
});
