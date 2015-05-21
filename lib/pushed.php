<?php

class PushedInternalErrorException extends Exception {

}

class PushedBadRequestException extends Exception {

}

class Pushed {

	protected $settings = array('server' => 'https://api.pushed.co/1/');

	public function __construct($auth = array()) {
		
		$this->settings = array_merge($this->settings, $auth);
	
	}

	public function request($method, $content = array()) {		
		 
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->settings['server'] . $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array_merge($this->settings, $content));
		$output = curl_exec($ch);		
		$response = curl_getinfo($ch);
		
		/** Error in curl request */
		if ($output === FALSE) {
		    throw new PushedInternalErrorException('Connection to Pushed failed.');
		}
		
		/** Error in response code or type */
		if (empty($response['http_code']) || $response['content_type'] != 'application/json') {
			throw new PushedBadRequestException('Bad response format from Pushed.');
		}
		
		/** Parse Pushed JSON response */
		$output = json_decode($output, TRUE);
		
		/** If response is not a valid array */
		if (!is_array($output)) {
			throw new PushedBadRequestException('Failed to parse response from Pushed.');
		}
		
		/** If response is not OK */
		if ($response['http_code'] != 200) {
			throw new PushedBadRequestException(sprintf('Pushed responded with an error (%s): %s', $response['http_code'], $output['error']['message']) );
		}
		
		return $output;	

	}

	public function push($notification_content = NULL, $notification_url = NULL) {

		$content = array(
			'content' => $notification_content,
			'content_type' => 'url',
			'content_extra' => $notification_url,
		);

		return $this->request('push', $content);		
	}

}