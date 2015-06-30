<?php

namespace ch\metanet\twitter\api;

/**
 * Twitter-API-PHP : Simple PHP wrapper for the v1.1 API
 *
 * PHP version 5.3.10
 *
 * @category Awesomeness
 * @package  Twitter-API-PHP
 * @author   James Mallison <me@j7mbo.co.uk>
 * @license  MIT License
 * @link     http://github.com/j7mbo/twitter-api-php
 */
class TwitterAPI
{
	const DEFAULT_API_URL = 'https://api.twitter.com/1.1/';

	const REQUEST_METHOD_GET = 'GET';
	const REQUEST_METHOD_POST = 'POST';

	protected $oauthAccessToken;
	protected $oauthAccessTokenSecret;
	protected $consumerKey;
	protected $consumerSecret;
	protected $oauth;
	protected $curl;
	protected $apiUrl;

	/**
	 * @param string $accessToken
	 * @param string $accessTokenSecret
	 * @param string $consumerKey
	 * @param string $consumerSecret
	 * @param string $apiUrl
	 *
	 * @throws \Exception
	 */
	public function __construct($accessToken, $accessTokenSecret, $consumerKey, $consumerSecret, $apiUrl = self::DEFAULT_API_URL)
	{
		if(in_array('curl', get_loaded_extensions()) === false)
			throw new \Exception('You need to install cURL');

		$this->oauthAccessToken = $accessToken;
		$this->oauthAccessTokenSecret = $accessTokenSecret;
		$this->consumerKey = $consumerKey;
		$this->consumerSecret = $consumerSecret;
		$this->apiUrl = $apiUrl;

		$this->curl = curl_init();
	}

	/**
	 * Set post fields array, example: array('screen_name' => 'J7mbo')
	 *
	 * @param array $parameters Array of parameters to send to API
	 *
	 * @return array
	 */
	protected function prepareParameters(array $parameters)
	{
		if(isset($parameters['status']) && substr($parameters['status'], 0, 1) === '@') {
			$parameters['status'] = sprintf("\0%s", $parameters['status']);
		}

		return $parameters;
	}

	/**
	 * Generates a valid HTTP query string like: '?screen_name=john_doe'
	 *
	 * @param array $parameters Get key and value pairs as string
	 *
	 * @return string The generated query string
	 */
	protected function generateHttpQueryString(array $parameters)
	{
		return '?' . http_build_query($parameters, null, null, PHP_QUERY_RFC3986);
	}

	/**
	 * Build the Oauth object using params set in construct and additionals
	 * passed to this method. For v1.1, see: https://dev.twitter.com/docs/api/1.1
	 *
	 * @param string $url The API url to use. Example: https://api.twitter.com/1.1/search/tweets.json
	 * @param string $requestMethod Either POST or GETk
	 */
	protected function buildOAuth($url, $requestMethod)
	{
		$oauth = array(
			'oauth_consumer_key' => $this->consumerKey,
			'oauth_nonce' => uniqid(true),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => time(),
			'oauth_token' => $this->oauthAccessToken,
			'oauth_version' => '1.0'
		);

		$base_info = $this->buildBaseString($url, $requestMethod, $oauth);
		$composite_key = rawurlencode($this->consumerSecret) . '&' . rawurlencode($this->oauthAccessTokenSecret);
		$oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
		$oauth['oauth_signature'] = $oauth_signature;

		$this->oauth = $oauth;
	}

	/**
	 * Perform the actual data retrieval from the API
	 *
	 * @param string $uri The relative API URI to call
	 * @param string $requestMethod The request method the API method uses
	 * @param array $parameters Data for the API call (key-value pairs)
	 *
	 * @throws \Exception
	 * @return \stdClass|int json If $return param is true, returns json data.
	 */
	public function performRequest($uri, $requestMethod, $parameters = array())
	{
		if(in_array($requestMethod, array(self::REQUEST_METHOD_GET, self::REQUEST_METHOD_POST)) === false) {
			throw new \Exception('Request method must be either POST or GET');
		}

		$requestUrl = $this->apiUrl . $uri . '.json';

		$options = array(
			CURLOPT_HEADER => false,
			CURLOPT_URL => $requestUrl,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_CUSTOMREQUEST => $requestMethod
		);

		$preparedParameters = $this->prepareParameters($parameters);

		if(count($preparedParameters) > 0) {
			$options[CURLOPT_URL] .= $this->generateHttpQueryString($preparedParameters);
		}

		$this->buildOAuth($options[CURLOPT_URL], $requestMethod);

		$header = array(
			$this->buildAuthorizationHeader($this->oauth),
			'Content-Type: application/x-www-form-urlencoded'
		);

		$options[CURLOPT_HTTPHEADER] = $header;

		curl_setopt_array($this->curl, $options);
		$json = curl_exec($this->curl);

		if(curl_errno($this->curl)) {
			throw new \Exception('CURL: ' . curl_error($this->curl));
		}

		if(strlen($json) > 0) {
			$responseObj = json_decode($json);

			if(isset($responseObj->errors)) {
				$errorObj = $responseObj->errors[0];
				throw new \Exception($errorObj->message, $errorObj->code);
			}

			return $responseObj;
		}

		return false;
	}

	/**
	 * Generate the base string used by cURL
	 *
	 * @param string $baseURI
	 * @param string $method
	 * @param array $oauth
	 *
	 * @return string Built base string
	 */
	protected function buildBaseString($baseURI, $method, $oauth)
	{
		$return = array();
		$dataArr = array();

		$urlParts = parse_url($baseURI);

		if(isset($urlParts['query']) === true) {
			parse_str($urlParts['query'], $dataArr);
		}

		$dataArr += $oauth;

		ksort($dataArr);

		foreach($dataArr as $key => $value) {
			$return[] = rawurlencode($key) . '=' . rawurlencode($value);
		}

		$url = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'];

		$baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode(implode('&', $return));

		return $baseString;
	}

	/**
	 * Generate authorization header used by cURL
	 *
	 * @param array $oauth Array of oauth data generated by buildOauth()
	 *
	 * @return string $return Header used by cURL for request
	 */
	protected function buildAuthorizationHeader($oauth)
	{
		$return = 'Authorization: OAuth ';
		$values = array();

		foreach($oauth as $key => $value) {
			$values[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
		}

		$return .= implode(', ', $values);

		return $return;
	}

	/**
	 * Disable peer verification for SSL
	 *
	 * @param bool $verifyPeer
	 */
	public function setVerifyPeer($verifyPeer)
	{
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
	}

	public function __destruct()
	{
		curl_close($this->curl);
	}
}


/* EOF */