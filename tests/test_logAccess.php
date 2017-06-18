<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: test_logAccess.php - test web service that logs a new attempt to access this application
//
// Input JSON:
// {"request":{
//    "ipAddress":"207.82.250.251",
//    "userAgent":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.1 Safari/603.1.30",
//    "referer":"https://www.bing.com/",
//    "sessionKey":"b9136f85a623458cb93bf3600b605583"
// }}
//
// Output JSON:
// {"response":{
//    "errorCode":0,
//    "logId":21
// }}
//
// Output JSON:
// {"response":{
//    "errorCode":3
//    "error":"Long exception stack trace"
// }}
//
// Functions:
//    None
//
// Query Parameters:
//    s: Magic key that should be present in query-string for this test to succeed
//
// Custom Headers:
//    None
//
// Session Variables:
//    None
//
// Stored Procedures:
//    None
//
// JavaScript functions:
//    None
//
// Revisions:
//    1. Sundar Krishnamurthy          sundar_k@hotmail.com       06/10/2017      Initial file created.


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
if ((!isset($_GET["s"])) || ($_GET["s"] != "$$TEST_QUERY_KEY$$")) {     // $$ TEST_QUERY_KEY $$
    exit();
}   //  End if ((!isset($_GET["s"])) || ($_GET["s"] != "$$TEST_QUERY_KEY$$"))      // $$ TEST_QUERY_KEY $$
	
// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier)
	
// ********* Call Web Service to get settings ********** //
$ch = curl_init();

$elements               = array();
$elements["ipAddress"]  = $_SERVER["REMOTE_ADDR"];
$elements["userAgent"]  = $_SERVER["HTTP_USER_AGENT"];

$referer = "";
        
if (key_exists("HTTP_REFERER", $_SERVER)) {
    $referer            = $_SERVER["HTTP_REFERER"];
}   //  End if (key_exists("HTTP_USER_AGENT", $_SERVER)
        
$elements["referer"]    = $referer;
$elements["sessionKey"] = "01234567890123456789012345678901";

$inputJson            = array();
$inputJson["request"] = $elements;

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/logAccess.php");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	  'ApiKey: $$API_KEY$$',           // $$ API_KEY $$
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'));

curl_setopt($ch, CURLOPT_SSLVERSION, 6);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(json_encode($inputJson)));

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

// if (isset($_COOKIE["PHPSESSID"])) {
//     curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
// }   //  End if (isset($_COOKIE["PHPSESSID"]))

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
// curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . $_COOKIE['PHPSESSID']);

session_write_close();

$response = curl_exec($ch);

curl_close($ch);

$curlyBracePosition = strpos($response, "{\"response\":", 0);

if ($curlyBracePosition > 0) {
    $response = substr($response, $curlyBracePosition);
}   //  End if ($curlyBracePosition > 0)

$logIdJson = json_decode($response, true);
$logResponse = $logIdJson["response"];

print_r($logResponse);

ob_end_flush();
?>
