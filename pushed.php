<?php

	/**
	 * @package Pushed
	 * @version 1.4
	 */

	/**
	* Plugin Name: Pushed
	* Plugin URI: https://wordpress.org/plugins/pushed-push-notifications/
	* Description: Push notifications plugin for wordpress by Pushed
	* Author: Get Pushed Ltd
	* Author URI: https://pushed.co/
	* Version: 1.4
	*
	* Copyright 2015 Get Pushed Ltd (email: hello@pushed.co)
	* This program is free software; you can redistribute it and/or modify
	* it under the terms of the GNU General Public License as published by
	* the Free Software Foundation; either version 2 of the License, or
	* (at your option) any later version.
	*
	* This program is distributed in the hope that it will be useful,
	* but WITHOUT ANY WARRANTY; without even the implied warranty of
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	* GNU General Public License for more details.
	*
	* You should have received a copy of the GNU General Public License
	* along with this program; if not, write to the Free Software
	* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
	*
	*/

	include_once('settings.php');
	require_once('lib/pushed.php');

	function pushed_add_meta_box() {
		wp_enqueue_style('pushed_css');
		wp_enqueue_script('pushed_js');
		add_meta_box(
			'pushed_section_id',
			__('<img src="'.plugins_url('assets/pushed_favicon_wordpress_edit_page.png', __FILE__).'" alt="Pushed" title="Pushed" height="18px;" style="vertical-align:sub;"> Pushed Notification', 'pushed'),
			'pushed_message_box',
			'post',
			'side',
			'high'
		);

		// add Pushed meta box for all custom post types
		$args = array(
			'public'   => true,
			'_builtin' => false
		);
		$output = 'names'; // names or objects, note names is the default
		$operator = 'and'; // 'and' or 'or'
		$post_types = get_post_types( $args, $output, $operator );
		foreach ( $post_types  as $post_type ) {
			add_meta_box(
				'pushed_section_id',
				__('Pushed Notification', 'pushed'),
				'pushed_message_box',
				$post_type,
				'side',
				'high'
			);
			/** Actions to listen */
			add_action('draft_to_publish', 'pushed_publish_post');
			add_action('pending_to_publish', 'pushed_publish_post');
			add_action('auto-draft_to_publish', 'pushed_publish_post');
			add_action('publish_to_publish', 'pushed_publish_post');

			add_action('draft_'. $post_type, 'pushed_save_post');
			add_action('pending_'. $post_type, 'pushed_save_post');
		}

	}

	// Add plugin notices
	add_action('admin_notices', 'pushed_admin_notices');

	// Add plugin metabox
	add_action('admin_init', 'pushed_add_meta_box');

	// do not use http://codex.wordpress.org/Plugin_API/Action_Reference/save_post
	// it can produce twice push send(if another plugins installed)
	add_action('new_to_publish', 'pushed_publish_post');
	add_action('draft_to_publish', 'pushed_publish_post');
	add_action('pending_to_publish', 'pushed_publish_post');
	add_action('auto-draft_to_publish', 'pushed_publish_post');
	add_action('publish_to_publish', 'pushed_publish_post');
	add_action('draft_post', 'pushed_save_post');
	add_action('pending_post', 'pushed_save_post');

	function pushed_message_box($post) {

		$action = null;
		if (!empty($_GET['action'])) {
			$action = htmlentities($_GET['action']);
		}

		wp_nonce_field(plugin_basename( __FILE__ ), 'pushed_post_nonce');
		$post_type = $post->post_type;
		$checkbox_label = sprintf('Send Pushed push notification %s', htmlentities($post_type));
		$textarea_placeholder = 'Enter the Push Notification content here, otherwise, the post title will be used. 140 characters allowed.';
		$checkbox_checked = 'checked="checked"';
		$message_content = '';

		if ($action == 'edit') {
			$checkbox_checked = '';
			$checkbox_label = sprintf('Send a push notification when the %s is updated.', htmlentities($post_type));
			$message_content = get_post_meta($post->ID, 'pushed_message_content', true);
		}
		$plugin_content = file_get_contents(plugin_dir_path(__FILE__) . '/html/pushed.html');
		echo sprintf($plugin_content,
			__($textarea_placeholder, 'pushed'),
			$message_content,
			$checkbox_checked,
			__($checkbox_label, 'pushed')
		);

	}

	function pushed_send_push_by_post($post_id, $post_title, $post_url) {

		$pushed_target_credentials = array(
			'app_key' => get_option('pushed_app_key'),
			'app_secret' => get_option('pushed_app_secret'),
			'target_type' => get_option('pushed_target_type'),
			'target_alias' => get_option('pushed_target_alias'),
		);

		foreach ($pushed_target_credentials as $key => $value) {
			if (!$value) {
				return false;
			} else {
				$pushed_target_credentials[$key] = $value['text_string'];
			}
		}

		$pushed = new Pushed($pushed_target_credentials);

		try {
			$pushed->push($post_title, $post_url);
			$status = 'Message succesfully sent to Pushed.';
		} catch (Exception $e) {
			$status = 'Failed: ' . $e->getMessage();
		}

		update_post_meta($post_id, 'pushed_api_request', $status);

		return $status;

	}

	function pushed_save_post($ID) {

		/*
		 * if update many posts, don't send push
		 */
		if (array_key_exists('post_status', $_GET) && $_GET['post_status']=='all') {
			return;
		}

		if (!empty($_POST)) {
			
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}

			if (!isset($_POST['pushed_post_nonce'])) {
				return;
			}
			if (!wp_verify_nonce($_POST['pushed_post_nonce'], plugin_basename( __FILE__ ))) {
				return;
			}
			if (array_key_exists('pushed_message_content', $_POST)) {
				$message_content = $_POST['pushed_message_content'];
			}

			update_post_meta($ID, 'pushed_message_content', $message_content);

		}

	}

	function pushed_publish_post($post) {

		// If update many posts, don't send push
		if (array_key_exists('post_status', $_GET) && $_GET['post_status'] == 'all') {
			return;
		}

		$safe_message_content = null;

		// Check this is a post request.
		if (empty($_POST)) {
			return;
		}

		// Check this is not an auto-save wordpress request.
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		// Check $_POST['pushed_post_nonce']
		if (!isset($_POST['pushed_post_nonce'])) {
			return;
		}
		
		if (!wp_verify_nonce($_POST['pushed_post_nonce'], plugin_basename( __FILE__ ))) {
			return;
		}

		// Check $_POST['pushed_send_push']
		if (!array_key_exists('pushed_send_push', $_POST)) {
			return;
		}

		$safe_send_push = sanitize_text_field($_POST['pushed_send_push']);

		if ($safe_send_push != 1){
			return;
		}

		if ($post->post_status != 'publish') {
			return;
		}

		// Check $_POST['pushed_message_content']
		$safe_message_content = sanitize_text_field($_POST['pushed_message_content']);
		// If there is no special message content set, grab the post title by default.
		if (array_key_exists('pushed_message_content', $_POST)) {
			$safe_message_content = empty($_POST['pushed_message_content']) ? $post->post_title : sanitize_text_field($_POST['pushed_message_content']);
		} else {
			$safe_message_content = $post->post_title;
		}		

		if (!$safe_message_content) {
			return;
		}

		if ($safe_message_content == '') {
			return;
		}

		// Un-quotes a quoted string
		$safe_message_content = stripslashes($safe_message_content);		

		// Limit to 140 characters, othwerwise it won't be accepted by Pushed API.
		if (strlen($safe_message_content) > 140) {
			$safe_message_content = substr($safe_message_content, 0, 140);
		}

		// Save safe_message_content as a post meta.
		update_post_meta($post->ID, 'pushed_message_content', $safe_message_content);

		$pushed_send_push_by_post = pushed_send_push_by_post($post->ID, $safe_message_content, get_permalink($post->ID));

		if (strpos($pushed_send_push_by_post, 'Failed') === true)
		{
			add_filter('redirect_post_location', 'add_error_notice', 99 ); // There was an error sending the notification.
		} else {
			if (!$pushed_send_push_by_post) {
				add_filter('redirect_post_location', 'add_error_credentials_notice', 99 ); // There is an error with the credentials.
			} else {
				add_filter('redirect_post_location', 'add_success_notice', 99 );
			}
		}

	}

	function add_error_notice($location) {

		remove_filter('redirect_post_location', 'add_notice_query_var', 99 );
		return add_query_arg(['pushed_error' => 'true'], $location );

	}

	function add_error_credentials_notice($location) {

		remove_filter('redirect_post_location', 'add_notice_query_var', 99 );
		return add_query_arg(['pushed_credentials_error' => 'true'], $location );

	}

	function add_success_notice($location) {

		remove_filter('redirect_post_location', 'add_notice_query_var', 99 );
		return add_query_arg(['pushed_success' => 'true'], $location );

	}

	function pushed_admin_notices() {
		
		if (isset($_GET['pushed_credentials_error'])) {
			$class = 'error';
			$message = 'Pushed credentials are not set. Please got to <a href="' . admin_url('options-general.php?page=pushed') . '">Pushed Settings</a> and enter your Pushed credentials.';
			pushed_admin_notices_format($message, $class);
		}

		if (isset($_GET['pushed_error'])) {
			$class = 'error';
			$message = 'Pushed message could not be sent. Please try again later or <a href="https://about.pushed.co/support">Contact Pushed</a>.';
			pushed_admin_notices_format($message, $class);
		}

		if (isset($_GET['pushed_success'])) {
			$class = 'updated';
			$message = 'Pushed message successfully sent.';
			pushed_admin_notices_format($message, $class);
		}

		remove_action('admin_notices', 'pushed_admin_notices');

	}

	function pushed_admin_notices_format($message = NULL, $class = NULL) {
		$favicon = '<img src="'.plugins_url('assets/pushed_favicon_wordpress_edit_page.png', __FILE__).'" alt="Pushed" title="Pushed" height="18px;" style="vertical-align:sub;">';
		$content = $favicon . '&nbsp;'. $message;
		echo '<div class="' . $class . '"> <p>' . $content . '</p></div>'; 
	}