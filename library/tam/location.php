<?php

/**
 * TAM PHP Library for Location Interface
 * https://code.telcoassetmarketplace.com/devcommunity/index.php/menudocumentation/menuapireference/menulocationinterface
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

require_once dirname(__FILE__) . "/../oauth/OAuthRequester.php";

class LocationApi
{
	static function getCoord ($oauth_token, $options = array())
	{   
		$tokenResultParams = array('oauth_token'=> $oauth_token);
	
		$curlOptions = array(
			CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json')
				);
			
		foreach ($options as $key => $option) 
		{
			$curlOptions[$key] = $option;
		}
		
		$request = new OAuthRequester(TAM_API_GET_LOCATION_URL, 'GET', $tokenResultParams);
		$result = $request->doRequest(0, $curlOptions);
		if ($result['code'] == 200) 
		{
			// now we parse the json response from the API call
			$jsonResponse = json_decode($result['body']);
			
			return $jsonResponse;
		} else 
		{
			return null;
		}
	}

}

?>