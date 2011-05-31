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

include_once "./include/defines.php";
include_once "../../library/tam/sms.php";
include_once "../../library/tam/location.php";


// The user id of the application user, 
// 	in this example we assume that there is only one user using the application
// This is used by the OAuthStore to store OAuth crendentials (e.g. access token) 
// 	that can be used again in the future for API calls 
//	without having to do the whole authorization flow again
$usrId = 0;

$curlOptions = array(
			CURLOPT_SSL_VERIFYPEER => SSL_VERIFIER);
			
// Note: do not use "Session" storage in production. Prefer a database
// storage, such as MySQL.
Common::initOAuth("Session", Common::getServerOptions(), $curlOptions);

try
{
	// STEP 1:  Get the temporary access token and token secret from TAM's incoming SMS request
	$body = @file_get_contents('php://input');
	$parsedBody = json_decode($body);		
	$oauthAccessToken = $parsedBody->access_token;
	$oauthTokenSecret = $parsedBody->token_secret;
	
	//only for debug
	//error_log("Access Token: " . $oauthAccessToken . "", 0);
	//error_log("Token Secret: " . $oauthTokenSecret . "", 0);
	
	// STEP 2:  Get the SMS message from the incoming SMS
	$smsMessage = $parsedBody->body;
	$stringToken = explode(' ', $smsMessage, 2);
	$yelpKeyword = $stringToken[1];
	
	// STEP 3: Add access token to the OAuth Store
	$store = OAuthStore::instance();
    $store->addServerToken(TAM_CONSUMER_KEY, 'access', $oauthAccessToken, $oauthTokenSecret, $usrId);

	// STEP 4:  Get current geo Location.			
	$jsonResponse = LocationApi::getCoord($usrId);			
	if (is_null($jsonResponse) || $jsonResponse->status->code != 0) 
	{
		echo 'Error occured while get the location: ' . $jsonResponse->status->message;
		//only for debug
		//error_log('Error occured while get the location: ' . $jsonResponse->status->message . '', 0);
	} 
	else 
	{
		$location = array (
			'latitude' => $jsonResponse->body->latitude,
			'longitude' => $jsonResponse->body->longitude,			
		);
		echo 'Location successfully retrieved';		
		//only for debug	
		//error_log('Location successfully retrieved ('. 'latitude  : ' . $location['latitude'] . ', longitude : ' . $location['longitude'] . ')', 0);
	}
	
	// STEP 5:  Get the YELP business review info
	$yelpResult = get_yelp_info( $yelpKeyword, $location, 10, 3 );
	if ($yelpResult['http_code'] == 200) 
	{
		$smsReply = build_sms_message($yelpResult['content']);
		//only for debug
		//error_log('Response from Yelp: ' . $smsReply . '', 0);
			
	} else 
	{
		$smsReply = 'Sorry, problem was found. Try again later.';
		//only for debug	
		//error_log('Error occured while get YELP info ', 0);
	}

	// STEP 6:  Send or reply the SMS to the subscriber using TAM Send SMS API.			
	$jsonResponse = SMSApi::sendSMS($usrId, $smsReply);
			
	if (is_null($jsonResponse) || $jsonResponse->status->code != 0) 
	{
		echo 'Error occured while sending SMS: ' . $jsonResponse->status->message;
		//only for debug
		//error_log('Error occured while sending SMS: ' . $jsonResponse->status->message . '', 0);
	} 
	else 
	{
		echo 'SMS successfully sent';
		//only for debug	
		//error_log('SMS successfully sent', 0);
	}
	
}
catch(OAuthException2 $e) {
	echo "OAuthException:  " . $e->getMessage();
	//error_log("OAuthException:  " . $e->getMessage()."", 0);
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
   	//only for debug
	//error_log("yelp URL: " . $url . "", 0);

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
		$msgContent = $msgContent . $count . ". " . $business->name . ', ' . $business->address1 . ' ' . $business->address2 . '\n';
	endforeach;
	
	if ($count == 0)
	{
		$smsMessage = 'We found no matches near You. Sorry.';
	} else
	{
		$smsMessage = 'We found ' . $count . ' matches near You:\n' . $msgContent;
	}
	
	return $smsMessage;
}
?>
