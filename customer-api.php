<?php
/**
 * Customer API proof of concept
 * 
 * This code depends on a few settings. You need two custom fields: the first
 * is a tick box for API access and the other a textbox with comma separated IP's to
 * allow IP based access levels. These settings are configurable below as
 * well as the main API access point and username and password. Customer details
 * will be checked later on. And yes: this script can use the internal API as well
 * although it has not been implemented at this point. 
 * 
 * @author		Sensson <info@sensson.net>
 * @copyright	2004-2013 Sensson
 * @license		GNU General Public License version 2 or later; see http://www.gnu.org/licenses/gpl-2.0.html
 * 
 */

/** 
 * Configuration
 * 
 * set custom fields, we'd rather have this automated
 * based on the custom field names (which is possible with some
 * database queries) but this code is a proof of concept with
 * the existing API. 
 */
$customFieldApi 	= "";
$customFieldApiIP 	= "";

/**
 * API access point
 */
$url = ""; // url to the original api
$username = ""; // api username of the admin that will run the commands
$password = ""; // api password

/**
 * Supported API calls, a full list can be found here:
 * http://docs.whmcs.com/API:Functions
 * 
 * Customers have unlimited access to these calls once API
 * access has been enabled for them
 */
$supportedCalls[] = "getclientsproducts";
$supportedCalls[] = "getclientsdomains";

/**
 * Customer API code starts here, no further changes should be
 * necessary at this point.
 */
if(!$_POST) {
	$return['result'] = "error";
	$return['message'] = "Invalid API request received.";
	echo json_encode($return);
	die();
}
if(!in_array($_POST['action'], $supportedCalls)) {
	$return['result'] = "error";
	$return['message'] = "This API function does not exit.";
	echo json_encode($return);
	die();
}
if(strlen($customFieldApi) == 0 OR strlen($customFieldApiIP) == 0 OR strlen($url) == 0 OR strlen($username) == 0 OR strlen($password) == 0) {
	$return['result'] = "error";
	$return['message'] = "Internal server error. Please contact your supplier.";
	echo json_encode($return);
	die();
}

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

$postfields = array();
$postfields["username"] = $username;
$postfields["password"] = md5($password);
$postfields["action"] = "getclients";
$postfields["responsetype"] = "json";
$postfields["search"] = $_POST['username'];

$resObj = apiCall($url, $postfields);

// check if API call was successful, if not, we most likely found a problem somewhere
// with the existing API and we don't want that error message to be displayed to the
// customer, instead, give them a relatively friendly error message
if($resObj->result == "error") {
	// log the error somewhere, preferably mysql
	
	// return the error to the customer
	$result['result'] = "error";
	$result['message'] = "The API encountered an internal server error. Please try again later.";
	echo json_encode($result);
	die();
}
 
// get the client id
if($resObj->numreturned == 1) {
	$clientObj = $resObj->clients->client[0];
	$clientId = $clientObj->id;
	
	// check if the customer is allowed to access the api
	// check if the user is allowed to access the api
	$postfields = array();
	$postfields["username"] = $username;
	$postfields["password"] = md5($password);
	$postfields["action"] = "getclientsdetails";
	$postfields["responsetype"] = "json";
	$postfields["clientid"] = $clientId;
	
	$clientData = apiCall($url, $postfields);
	$clientApiIPs = explode(",", $clientData->$customFieldApiIP);
	
	if($clientData->$customFieldApi != "on") {
		$response['result'] = "error";
		$response['message'] = "Authentication Failed. API access not allowed. Please contact your supplier.";
		echo json_encode($response);
		die();
	}
	if(!in_array($_SERVER['REMOTE_ADDR'], $clientApiIPs)) {
		$response['result'] = "error";
		$response['message'] = "Authentication Failed. API access not allowed from " . $_SERVER['REMOTE_ADDR'] . ". Please contact your supplier.";
		echo json_encode($response);
		die();
	}
}
else {
	$response['result'] = "error";
	$response['message'] = "Authentication Failed";
	echo json_encode($response);
}

// get the password
$postfields = array();
$postfields["username"] = $username;
$postfields["password"] = md5($password);
$postfields["responsetype"] = "json";
$postfields["action"] = "getclientpassword";
$postfields["userid"] = "{$clientId}";

$resObj = apiCall($url, $postfields);
if($resObj->result == "success") {
	$clientPass = explode(":", $resObj->password);
	// customer password == $_POST['password]
	$customerPassword = md5($clientPass[1] . $_POST['password']);
	if($customerPassword != $clientPass[0]) {
		$response['result'] = "error";
		$response['message'] = "Authentication Failed";
		echo json_encode($response);
		die();
	}
	
	// execute the API call for the customer	
	$postfields = array();
	$postfields["username"] = $username;
	$postfields["password"] = md5($password);
	$postfields["responsetype"] = "json";
	$postfields["action"] = $_POST['action'];
	$postfields["clientid"] = $clientId;
	
	// loop through postfields, secure them, and make a new array with only those values that matter
	// do not overwrite clientid, responsetype, username password
	foreach($_POST as $key => $value) {
		if($key != "clientid" && $key != "responsetype" && $key != "username" && $key != "password" && $key != "action") {
			// only accept alphanumeric characters
			if(ctype_alnum($key)) {
				$postfields[$key] = $value;
			}
		}
	}

	$resObj = apiCall($url, $postfields);
	echo json_encode($resObj);
}
?>