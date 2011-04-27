<?php

/**
 * TAM: Example of receiving SMS and search for Yelp Info through TAM API using temporary token
 *
 * @author Mikhael Harswanto, Tri Nugroho
 *
 * 
 * Copyright (c) 2011 Nokia Siemens Networks
 * 
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

include_once "./library/oauth/OAuthStore.php";
include_once "./library/oauth/OAuthRequester.php";
include_once "./library/tam/sms.php";
include_once "./library/tam/location.php";

define("SSL_VERIFIER", false); // set this to true for production use

define("YELP_HOST", "http://api.yelp.com");
define("YELP_BUSINESS_URL", YELP_HOST . "/business_review_search");
define("YELP_YWSID", "xxxxxxxxxxxxxxxxxxxxxxxx");  // this is your YWSID from Yelp.com

define("APP_HOST", "http://yoursite.com");
define("APP_URL", APP_HOST . "/yelp-app");
define("APP_CALLBACK_URL", APP_URL . "/callback.php");

define("TAM_CONSUMER_KEY", "xxxxxxxxxxxxxxx"); // this is your application consumer key
define("TAM_CONSUMER_SECRET", "xxxxxxxxxxxxxxx"); // this is your application consumer secret
define("TAM_OAUTH_HOST", "https://www.telcoassetmarketplace.com");
define("TAM_REQUEST_TOKEN_URL", TAM_OAUTH_HOST . "/api/1/oauth/request_token");
define("TAM_AUTHORIZE_URL", TAM_OAUTH_HOST . "/web/authorize");
define("TAM_ACCESS_TOKEN_URL", TAM_OAUTH_HOST . "/api/1/oauth/access_token");
define("TAM_API_URL", TAM_OAUTH_HOST . "/api/1");
define("TAM_API_SEND_SMS_URL", TAM_API_URL . "/sms/send");
define("TAM_API_GET_LOCATION_URL", TAM_API_URL . "/location/getcoord");

define('OAUTH_TMP_DIR', function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : realpath($_ENV["TMP"]));

$options = array(
	'consumer_key' => TAM_CONSUMER_KEY, 
	'consumer_secret' => TAM_CONSUMER_SECRET,
	'server_uri' => TAM_OAUTH_HOST,
	'request_token_uri' => TAM_REQUEST_TOKEN_URL,
	'authorize_uri' => TAM_AUTHORIZE_URL,
	'access_token_uri' => TAM_ACCESS_TOKEN_URL
);
OAuthStore::instance("Session", $options);
// Note: do not use "Session" storage in production. Prefer a database
// storage, such as MySQL.

$curlOptions = array(
			CURLOPT_SSL_VERIFYPEER => SSL_VERIFIER);

try
{
	// STEP 1:  Get the temporary access token and token secret from TAM's incoming SMS request
	$body = @file_get_contents('php://input');
	$parsedBody = json_decode($body);		
	$oauthAccessToken = $parsedBody->access_token;
	$oauthTokenSecret = $parsedBody->token_secret;
		
	// STEP 2:  Get the SMS message from the incoming SMS
	$smsMessage = $parsedBody->body;
	$stringToken = explode(' ', $smsMessage, 2);
	$yelpKeyword = $stringToken[1];
	
	// STEP 3: Add access token to the OAuth Store
	$store = OAuthStore::instance();
    $opts = array();
	$store->addServerToken(TAM_CONSUMER_KEY, 'access', $oauthAccessToken, $oauthTokenSecret, 0, $opts);

	// STEP 4:  Get current geo Location.			
	$jsonResponse = LocationApi::getLocationCoord($oauthAccessToken, $curlOptions);			
	if (is_null($jsonResponse) || $jsonResponse->status->code != 0) 
	{
		echo 'Error occured while get the location: ' . $jsonResponse->status->message;
	} 
	else 
	{
		$location = array (
			'latitude' => $jsonResponse->body->latitude,
			'longitude' => $jsonResponse->body->longitude,			
		);
		echo 'Location successfully retrieved';		
	}
	
	// STEP 5:  Get the YELP business review info
	$yelpResult = get_yelp_info( $yelpKeyword, $location, 10, 3 );
	if ($yelpResult['http_code'] == 200) 
	{
		$smsReply = build_sms_message($yelpResult['content']);
			
	} else 
	{
		$smsReply = "Sorry, problem was found. Try again later.";
	}

	// STEP 6:  Send or reply the SMS to the subscriber using TAM Send SMS API.			
	$jsonResponse = SMSApi::sendSMS($oauthAccessToken, $smsReply, null, $curlOptions);
			
	if (is_null($jsonResponse) || $jsonResponse->status->code != 0) 
	{
		echo 'Error occured while sending SMS: ' . $jsonResponse->status->message;
	} 
	else 
	{
		echo 'SMS successfully sent';
	}
	
}
catch(OAuthException2 $e) {
	echo "OAuthException:  " . $e->getMessage();
	var_dump($e);
}

function get_yelp_info( $keywords, $location, $radius, $limit )
{
    $options = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => false,    // don't return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        CURLOPT_USERAGENT      => "spider", // who am i
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
    );
        
    $httpQuery = array (
    	'term'   => urlencode($keywords),
    	'lat'    => $location['latitude'],
    	'long'   => $location['longitude'],
    	'radius' => $radius,
    	'limit'  => $limit,
    	'ywsid'  => YELP_YWSID
    );
    	
    $url = YELP_BUSINESS_URL . '?' . http_build_query($httpQuery);   	

    $ch      = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $content = curl_exec( $ch );
    $err     = curl_errno( $ch );
    $errmsg  = curl_error( $ch );
    $header  = curl_getinfo( $ch );
    curl_close( $ch );

    $header['errno']   = $err;
    $header['errmsg']  = $errmsg;
    $header['content'] = $content;
    return $header;
}

function build_sms_message( $yelpJsonResponse )
{
	$obj = json_decode($yelpJsonResponse); // Convert JSON from yelp return string
	$msgContent = "";
	$count = 0;
 
	foreach($obj->businesses as $business):
		$count = $count + 1;
		$msgContent = $msgContent . $count . ". " . $business->name . ", " . $business->address1 . " " . $business->address2 . "\n";
	endforeach;
	
	if ($count == 0)
	{
		$smsMessage = "We found no matches near You. Sorry.";
	} else
	{
		$smsMessage = "We found " . $count . " matches near You:\n" . $msgContent;
	}
	
	return $smsMessage;
}
?>
