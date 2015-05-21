<?php

class PushedConfig {

	public $group = 'pushed';

	public $page = array(
		'name' => 'pushed',
		'title' => '<h1>Pushed Worpress Push Notifications</h1>',
		'intro_text' => '<div class="welcome-panel" style="margin-right:20px;padding:15px;"><div style="float:left;"><a href="https://pushed.co" target="_blank"><img src="https://s3-eu-west-1.amazonaws.com/pushed.co/assets/pushed/media/pushed_hello.png" height="100px;"></a></div><h3>Pushed Wordpress plugin allows you to send notifications to your subscribers every time you publish or update a Wordpress post.</h3> Integration between Wordpress and Pushed is <b>free</b> and <b>effortless</b>.<br/><a href="https://account.pushed.co/signup" target="_blank">Sign Up in Pushed</a> and request a <a href="https://account.pushed.co/" target="_blank">Developer Account</a>. It will only take 5 minutes!<div style="clear:both;"></div>Here you have a <a href="https://pushed.co/#get-started" target="_blank">complete guide to get started</a>.<br/>If you need further assistance, please do not hesitate <a href="https://about.pushed.support/">contacting us</a>, we\'ll be glad to help.</div>',
		'menu_title' => 'Pushed'
	);

	public $sections = array(
		'application_access' => array(
			'title' => 'Pushed Target Settings',
			'description' => 'Please configure the following settings below (you\'ll find this settings on your <a href="https://account.pushed.co/" target="_blank">Pushed Developer Panel</a>):',
			'fields' => array(
				'target_type' => array(
					'label' => 'Source',
					'description' => 'Select the type of your source (App or Channel).',
					'type' => 'select',
					"options" => array('app' => 'App', 'channel' => 'Channel'),
					"default" => "app"
				),
				'target_alias' => array(
					'label' => 'Pushed Source Alias',
					'description' => 'Your Pushed Source Alias (If you\'re sending to a Pushed Channel enter the Channel alias).',
					'type' => 'text',
					"default" => ""
				),
				'app_key' => array(
					'label' => 'Pushed App Key',
					'description' => 'Your Pushed App Key.',
					'type' => 'text',
					"default" => ""
				),
				'app_secret' => array(
					'label' => 'Pushed App Secret',
					'description' => 'Your Pushed App Secret.',
					'type' => 'text',
					"default" => ""
				),
			)
		)
	);
}


class PushedSectionHelper {

	protected $_sections;

	public function __construct($sections) {
		$this->_sections = $sections;
	}

	public function section_legend($value) {
		echo sprintf("%s",$this->_sections[$value['id']]['description']);
	}

	public function input_text($value) {
		$api_class = '';
		if ($value['name'] == 'pushed_api_token') {
			$api_class = 'pushed-api-token-input';
		}
		$options = get_option($value['name']);
		$default = (isset($value['default'])) ? $value['default'] : null;

		echo sprintf('<input class="%s" id="%1$s" type="text" name="%2$s[text_string]" value="%3$s" size="40" /> %4$s%5$s',
			$api_class,
			$value['name'],
			(!empty ($options['text_string'])) ? $options['text_string'] : $default,
			(!empty ($value['suffix'])) ? $value['suffix'] : null,
			(!empty ($value['description'])) ? sprintf("<br /><em>%s</em>", __($value['description'], 'pushed')) : null);
	}

	public function input_select($value) {

		$options = get_option($value['name']);
		$default = (isset($value['default'])) ? $value['default'] : null;
		$selected = !empty($options) && !empty($options['text_string']) ? $options['text_string'] : $default;

		echo sprintf('<select name="%s[text_string]">', $value['name']);
		foreach ($value['options'] as $optionValue => $optionText):
			echo sprintf('<option value="%1$s"  %2$s> %3$s </option>',
				$optionValue,
				$selected == $optionValue ? 'selected' : '',
				$optionText);
		endforeach;
		echo '</select>';

		if (!empty ($value['description']))
			echo sprintf("<br /><em>%s</em>", __($value['description'], 'pushed'));
	}

	public function input_submit($value) {
		echo sprintf('<input type="submit" name="Submit" value="%s" class="button button-primary"/>', $value);
	}

	public function form_start($action) {
		echo sprintf('<form method="POST" action="%s">', $action);
	}

	public function form_end() {
		echo '</form>';
	}
}


class PushedSettings {

	protected $_config;

	public function __construct() {
		$this->_config = get_class_vars('PushedConfig');
		$this->_section = new PushedSectionHelper($this->_config['sections']);
		$this->initialize();
	}

	protected function initialize() {

		if (!function_exists('add_action')) {
			return;
		}

		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_menu', array($this, 'admin_add_page'));

		if (!function_exists('add_filter')) {
			return;
		}

		$filter = 'plugin_action_links_' . basename(__DIR__) . '/pushed.php';
		add_filter($filter, array($this, 'admin_add_links'), 10, 4);
	}

	public function admin_add_links($links, $file) {

		$settings_link = sprintf('<a href="options-general.php?page=%s">%s</a>',
			$this->_config['page']['name'],
			__('Settings')
		);
		array_unshift($links, $settings_link);
		return $links;
	}

	public function admin_init() {

		wp_register_script('pushed_js', plugins_url('/js/pushed.js', __FILE__), array(), '1.0');
		wp_register_style('pushed_css', plugins_url('/css/pushed.css', __FILE__), array(), '1.0');

		foreach ($this->_config['sections'] as $key => $section):
			add_settings_section(
				$key,
				__($section['title'], 'pushed'),
				array($this->_section, 'section_legend'),
				$this->_config['page']['name'],
				$section
			);

			foreach ($section['fields'] as $field_key => $field_value):

				if ($field_value['type'] == 'select') {
					$function = array($this->_section, 'input_select');
				} else {
					$function = array($this->_section, 'input_text');
				}

				/** Validate input settings */
				$callback = 'pushed_input_settings_validation';

				add_settings_field(
					$this->_config['group'] . '_' . $field_key,
					__($field_value['label'], 'pushed'),
					$function,
					$this->_config['page']['name'],
					$key,
					array_merge(
						$field_value,
						array('name' => $this->_config['group'] . '_' . $field_key)
					)
				);
				register_setting(
					$this->_config['group'],
					$this->_config['group'] . '_' . $field_key,
					$callback
				);
			endforeach;
		endforeach;
	}

	public function admin_add_page() {

		$args = array(
			__($this->_config['page']['title'], 'pushed'),
			__($this->_config['page']['menu_title'], 'pushed'),
			'manage_options',
			$this->_config['page']['name'],
			array($this, 'options_page')
		);
		call_user_func_array('add_options_page', $args);
	}

	public function options_page() {

		echo sprintf('<h2>%s</h2><p>%s</p>',
			__($this->_config['page']['title'], 'pushed'),
			__($this->_config['page']['intro_text'], 'pushed')
		);
		$this->_section->form_start('options.php');

		settings_fields($this->_config['group']);

		do_settings_sections($this->_config['page']['name']);
		$this->_section->input_submit(__('Save changes', 'pushed'));
		$this->_section->form_end();
	}
}

function pushed_input_settings_validation($input) {

	 /** Create our array for storing the validated options */
	$output = array();

	/** Loop through each of the incoming options */
	foreach( $input as $key => $value ) {
		 
		/** Check to see if the current option has a value. If so, process it. */
		if( isset( $input[$key] ) ) {
		 
			/** Strip all HTML and PHP tags and properly handle quoted strings */
			$output[$key] = strip_tags( stripslashes( $input[ $key ] ) );
			 
		}
		 
	}

	/** Return the array processing any additional functions filtered by this action */
	return apply_filters( 'sandbox_theme_validate_input_examples', $output, $input );

}

new PushedSettings();