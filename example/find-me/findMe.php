<?php

/**
 * TAM: Example of Get Location Through TAM API
 *
 * @author Mikhael Harswanto
 *
 * 
 * Copyright (c) 2011 Nokia Siemens Networks
 * 
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

include_once "../../library/oauth/OAuthStore.php";

include_once "./include/defines.php";
include_once "../../library/tam/location.php";

// The user id of the application user, 
// 	in this example we assume that there is only one user using the application
// This is used by the OAuthStore to store OAuth crendentials (e.g. access token) 
// 	that can be used again in the future for API calls 
//	without having to do the whole authorization flow again
$usrId = 0;

// Note: do not use "Session" storage in production. Prefer a database
// storage, such as MySQL.
Common::initOAuth("Session", Common::getServerOptions());

$curlOptions = array(
				CURLOPT_SSL_VERIFYPEER => SSL_VERIFIER);

try
{
	//  STEP 1:  If we do not have an OAuth token yet, go get one
	if (empty($_GET["oauth_token"])) 
	{
		$getAuthTokenParams = array(
			'oauth_callback' => APP_CALLBACK_URL);
			
		// get a request token
		$tokenResultParams = OAuthRequester::requestRequestToken(TAM_CONSUMER_KEY, $usrId, 0, 'GET', $getAuthTokenParams, $curlOptions);

		//  redirect to the TAM authorization page, it will redirect back
		header("Location: " . TAM_AUTHORIZE_URL . "?oauth_token=" . $tokenResultParams['token']);
	} 
	else 
	{
		$oauthToken = $_GET["oauth_token"];
			
		if (!empty($_GET["oauth_verifier"])) 
		{
			//  STEP 2:  Get an access token
			$tokenResultParams = $_GET;
			
			try {
				OAuthRequester::requestAccessToken(TAM_CONSUMER_KEY, $oauthToken, $usrId, 'GET', $_GET, $curlOptions);
				
				$store	= OAuthStore::instance();
				// get the stored access token for this user
				$session = $store->getSecretsForSignature(TAM_ACCESS_TOKEN_URL, $usrId);
				
				// redirect back to this page but with access token passed as parameter
				header("Location: " . APP_HOST . "/" . $_SERVER['PHP_SELF'] . "?oauth_token=" . $session['token']);
			}
			catch (OAuthException2 $e)
			{
				var_dump($e);
				// Something wrong with the oauth_token.
				// Could be:
				// 1. Was already ok
				// 2. We were not authorized
				return;
			}
		} 
		else 
		{
			//  STEP 3:  Now we can use obtained access token for API calls
			
			$jsonResponse = LocationApi::getCoord($usrId, $curlOptions);
			
			if (is_null($jsonResponse) || $jsonResponse->status->code != 0) 
			{
				echo 'Error occured while getting location: ' . $jsonResponse->status->message;
			} 
			else 
			{
				echo 'Your location: ' . $jsonResponse->body->latitude . ', ' . $jsonResponse->body->longitude;
			}
		}
	}
}
catch(OAuthException2 $e) {
	echo "OAuthException:  " . $e->getMessage();
	var_dump($e);
}
?>