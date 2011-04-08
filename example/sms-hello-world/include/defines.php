<?php

define("SSL_VERIFIER", false); // set this to true for production use

define("APP_HOST", "http://localhost");
define("APP_URL", APP_HOST . "/telco-asset-marketplace-php/example/sms-hello-world");
define("APP_CALLBACK_URL", APP_URL . "/callback.php");

define("TAM_CONSUMER_KEY", "j6umi2wryijlsqf5"); // this is your application consumer key
define("TAM_CONSUMER_SECRET", "zp50ulo1q92txg92"); // this is your application consumer secret

define("TAM_OAUTH_HOST", "https://localhost:9443");
define("TAM_REQUEST_TOKEN_URL", TAM_OAUTH_HOST . "/api/1/oauth/request_token");
define("TAM_AUTHORIZE_URL", TAM_OAUTH_HOST . "/web/authorize");
define("TAM_ACCESS_TOKEN_URL", TAM_OAUTH_HOST . "/api/1/oauth/access_token");

define("TAM_API_URL", TAM_OAUTH_HOST . "/api/1");
define("TAM_API_SEND_SMS_URL", TAM_API_URL . "/sms/send");
define("TAM_API_GET_LOCATION_URL", TAM_API_URL . "/location/getcoord");

define('OAUTH_TMP_DIR', function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : realpath($_ENV["TMP"]));

?>