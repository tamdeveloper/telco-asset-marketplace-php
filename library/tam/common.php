<?php

/**
 * TAM PHP Library Commons
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

define("TAM_HOST", "https://telcoassetmarketplace.com");
define("TAM_API_URL", TAM_HOST . "/api/1");

//OAuth Interfaces
define("TAM_REQUEST_TOKEN_URL", TAM_API_URL . "/oauth/request_token");
define("TAM_AUTHORIZE_URL", TAM_HOST . "/web/authorize");
define("TAM_ACCESS_TOKEN_URL", TAM_API_URL . "/oauth/access_token");

//Other Interfaces
define("TAM_API_SEND_SMS_URL", TAM_API_URL . "/sms/send");
define("TAM_API_GET_LOCATION_COORD_URL", TAM_API_URL . "/location/getcoord");

require_once dirname(__FILE__) . "/../oauth/OAuthStore.php";

class Common 
{
	static private $server = array(
									'consumer_key' => TAM_CONSUMER_KEY, 
									'consumer_secret' => TAM_CONSUMER_SECRET,
									'server_uri' => TAM_HOST,
									'request_token_uri' => TAM_REQUEST_TOKEN_URL,
									'authorize_uri' => TAM_AUTHORIZE_URL,
									'access_token_uri' => TAM_ACCESS_TOKEN_URL,
									'signature_methods' => 'HMAC-SHA1'
								);
								
	static private $initiated = false;								
								
	static function initOAuth ($store = 'MySQL', $options = array())
	{
		//  Init the OAuthStore
		$store = OAuthStore::instance($store, $options);
	
		if (!Common::$initiated) {
			try {
				if (!$store instanceof OAuthStoreSession)
				{
					$store->getServer(Common::$server['consumer_key'], null);
				}
			} catch (OAuthException2 $e) {
				// first check if server uri exist but with different/old consumer key
				try {
					$existing = $store->getServerForUri(Common::$server['server_uri'], null);
					
					//exist so we have to delete it first
					$store->deleteServer($existing['consumer_key'], null);
				} catch (OAuthException2 $e) {
				}
				
				// server not found, create it
				$store->updateServer(Common::$server, null);
			}
			Common::$initiated = true;
		}
		
		return $store;
	}
	
	static function resetOAuth () 
	{
		// to be called if the application has updated consumer key without the need of restarting the application server
		Common::$initiated = false;
	}
	
	static function getServerOptions()
	{
		return Common::$server;
	}
}

?>