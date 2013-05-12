<?php
/**
 * Customer API proof of concept example
 * 
 * @author		Sensson <info@sensson.net>
 * @copyright	2004-2013 Sensson
 * @license		GNU General Public License version 2 or later; see http://www.gnu.org/licenses/gpl-2.0.html
 * 
 */
 
/** 
 * Configuration
 * 
 * The URL is the endpoint of the customer-api.php file. 
 * The username is the e-mail address of the customer
 * The password is the customer's password
 */
$url = "";
$username = ""; 
$password = ""; 

/*
 * Process an API call
 * 
 * @param string $url			The API connection point
 * @param array  $postfields	Array with all parameters
 * 
 * @return string $decodedJson	Returns a json string
 */
function apiCall($url, $postfields) {
	$query_string = "";
	foreach ($postfields AS $k=>$v) $query_string .= "$k=".urlencode($v)."&";
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	$jsondata = curl_exec($ch);
	if (curl_error($ch)) die("Connection Error: ".curl_errno($ch).' - '.curl_error($ch));
	curl_close($ch);
	$decodedJson = json_decode($jsondata);
	return $decodedJson;
}

// get all domains
$postfields = array();
$postfields["username"] = $username;
$postfields["password"] = $password;
$postfields["action"] = "getclientsdomains";
$postfields["responsetype"] = "json";
$postfields["domain"] = "";
 
$resObj = apiCall($url, $postfields);
print_r($resObj);
 
?>