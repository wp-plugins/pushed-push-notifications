<?php

    /**
     * @package Pushed
     * @version 1.3.1
     */

    /**
    * Plugin Name: Pushed
    * Plugin URI: https://wordpress.org/plugins/pushed-push-notifications/
    * Description: Push notifications plugin for wordpress by Pushed
    * Author: Get Pushed Ltd
    * Author URI: https://pushed.co/
    * Version: 1.3.2
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

	error_reporting(E_ALL);
	ini_set('display_errors', 0);

	include_once('settings.php');
	require_once('lib/pushed.php');

	function pushed_add_meta_box() {
		wp_enqueue_style('pushed_css');
		wp_enqueue_script('pushed_js');
		add_meta_box(
			'pushed_section_id',
			__('<img src="https://s3-eu-west-1.amazonaws.com/pushed.co/media/favicon.png" alt="Pushed" title="Pushed" height="18px;" style="vertical-align:sub;"> Pushed Notification', 'pushed'),
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

	add_action('admin_init', 'pushed_add_meta_box');
	// do not use http://codex.wordpress.org/Plugin_API/Action_Reference/save_post
	// it can produce twice push send(if another plugins installed)
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
		$checkbox_label = sprintf('Send a push notification when the %s is published', htmlentities($post_type));
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
			'app_key' => get_option('pushed_app_key', array('text_string' => null))['text_string'],
			'app_secret' => get_option('pushed_app_secret', array('text_string' => null))['text_string'],
			'target_type' => get_option('pushed_target_type', array('text_string' => null))['text_string'],
			'target_alias' => get_option('pushed_target_alias', array('text_string' => null))['text_string'],
		);

		$pushed  = new Pushed($pushed_target_credentials);

		try {
			$pushed->push($post_title, $post_url);
			$status = 'Success';
		} catch (Exception $e) {
			$status = 'Failed: ' . $e->getMessage();
		}

		update_post_meta($post_id, 'pushed_api_request', $status);

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

		/*
		 * if update many posts, don't send push
		 */
		if (array_key_exists('post_status', $_GET) && $_GET['post_status']=='all') {
			return;
		}

		$message_content = null;
		$post_id = $post->ID;
		$post = null;

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
			if (empty($_POST['pushed_send_push'])) {
				return;
			}
			
			if (array_key_exists('pushed_message_content', $_POST)) {
				$message_content = $_POST['pushed_message_content'];
			}
			update_post_meta($post_id, 'pushed_message_content', $message_content);
		
		} else {
		
			$message_content = get_post_meta($post_id, 'pushed_message_content', true);
		
		}

		$message_content = stripslashes($message_content);
		$post = get_post($post_id);
		
		if (empty($message_content)) {
			$message_content = $post->post_title;
		}
		
		if ($post->post_status != 'publish') {
			return;
		}

		pushed_send_push_by_post($post->ID, $message_content, get_permalink($post_id));

	}