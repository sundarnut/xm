<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: test_getSettings.php - get list of configured settings in DB
//
// Input JSON:
//    None
//
// Output JSON:
//   {"response:{
//      "errorCode":0,
//      "settings":[
//      {"logging":"1"},
//      {"errorEmail":"sundar_k@hotmail.com"}
//   ]}}
//
// Output JSON:
//   {"response":{
//      "errorCode":1,
//      "error":"Long exception stack trace"
//   }}
//
// Functions:
//    None
//
// Query Parameters:
//    None
//
// Custom Headers:
//     ApiKey: Must contain magic value for this service to be employed
//
// Session Variables:
//     None
//
// Stored Procedures:
//    getSettings - get list of all settings in DB
//
// JavaScript functions:
//    None
//
// Revisions:
//     1. Sundar Krishnamurthy          sundar_k@hotmail.com       06/10/2017      Initial file created.


ini_set('session.cookie_httponly', TRUE);           // Mitigate XSS
ini_set('session.session.use_only_cookies', TRUE);  // No session fixation
ini_set('session.cookie_lifetime', FALSE);          // Avoid XSS, CSRF, Clickjacking
ini_set('session.cookie_secure', TRUE);             // Never let this be propagated via HTTP/80

// Include functions.php that contains all our functions
require_once("../functions.php");

// Start output buffering on
ob_start();

// Start the initial session
session_start();

// Break out of test if key not present in incoming request
if ((!isset($_GET["s"])) || ($_GET["s"] != "$$TEST_QUERY_KEY$$")) {    // $$ TEST_QUERY_KEY $$
    exit();
}   //  End if ((!isset($_GET["s"])) || ($_GET["s"] != "$$TEST_QUERY_KEY$$"))    // $$ TEST_QUERY_KEY $$

// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier)
	
// ********* Call Web Service to get settings ********** //
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/getSettings.php");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'ApiKey: $$API_KEY$$',           // $$ API_KEY $$
    'Accept: application/json'));

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

// if (isset($_COOKIE["PHPSESSID"])) {
//    curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
// }   //  End if (isset($_COOKIE["PHPSESSID"]))

session_write_close();

$settings = curl_exec($ch);

curl_close($ch);

$curlyBracePosition = strpos($settings, "{\"response\":", 0);

if ($curlyBracePosition > 0) {
    $settings = substr($settings, $curlyBracePosition);
}   //  End if ($curlyBracePosition > 0)

$settingsJson = json_decode($settings, true);
$response = $settingsJson["response"];

print_r($response);

ob_end_flush();
?>
