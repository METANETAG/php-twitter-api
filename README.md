# php-twitter-api

This is a simple PHP wrapper for the twitter API (version 1.1). 

This is a fork of the [twitter-api-php](https://github.com/J7mbo/twitter-api-php) from [J7mbo](https://github.com/J7mbo/).

It simplifies the whole API requesting thing a bit.

## Example

```php
try {
	$twitterApi = new ch\metanet\twitter\api\TwitterAPI('access_token', 'access_token_secret', 'consumer_key', 'consumer_key_secret');
	
	$twitterApi->performRequest('statuses/update', TwitterAPI::REQUEST_METHOD_POST, array(
		'status' => 'foo bar baz'
	));
} catch(Exception $e) {
	echo 'ERROR during API call: ' , $e->getMessage() , ' (Code: ' , $e->getCode() , ')';
}
```