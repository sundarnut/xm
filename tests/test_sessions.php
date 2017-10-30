<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: test_sessions.php - test web service that sets and gets a session key-value pair for a sessionId
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
//    1. Sundar Krishnamurthy          sundar_k@hotmail.com       09/15/2017      Initial file created.


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
if ((!isset($_GET["s"])) || ($_GET["s"] !== "dd798f82b6154f49932e243e79b53945")) {     // $$ TEST_QUERY_KEY $$
    exit();
}   //  End if ((!isset($_GET["s"])) || ($_GET["s"] !== "dd798f82b6154f49932e243e79b53945"))      // $$ TEST_QUERY_KEY $$

// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier)

// STEP 1 - Set value and verify set works
// ********* Call Web Service to set settings ********** //
$ch = curl_init();

$elements              = array();
$elements["sessionId"] = md5(session_id());
$elements["key"]       = "key01";
$elements["value"]     = "\"What's the frequency Kenneth?\"";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/setSession.php");

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'ApiKey: $$API_KEY$$',           // $$ API_KEY $$
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'));

curl_setopt($ch, CURLOPT_SSLVERSION, 6);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(utf8_encode(json_encode($elements))));

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

// if (isset($_COOKIE["PHPSESSID"])) {
//     curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
// }   //  End if (isset($_COOKIE["PHPSESSID"]))

session_write_close();

$response = curl_exec($ch);

curl_close($ch);

$setResponse = json_decode(utf8_decode($response), true);

$errorCode = intval($setResponse["errorCode"]);
$query = $setResponse["query"];

if ($errorCode === 0) {
    print("Step 1: id is " . $setResponse["id"] . "<br/>");
} else {
    die("Error occured in step 1: " . $setResponse["error"] . ". Aborting run!");
}   //  End if ($errorCode === 0)

// STEP 2 - Get value and verify get works, delete on fetch
// ********* Call Web Service to get settings ********** //
$ch                     = curl_init();

$elements              = array();
$elements["sessionId"] = md5(session_id());
$elements["key"]       = "key01";
$elements["delete"]    = true;

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/getSession.php");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'ApiKey: $$API_KEY$$',           // $$ API_KEY $$
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'));

curl_setopt($ch, CURLOPT_SSLVERSION, 6);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(utf8_encode(json_encode($elements))));

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

// if (isset($_COOKIE["PHPSESSID"])) {
//     curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
// }   //  End if (isset($_COOKIE["PHPSESSID"]))

session_write_close();

$response = curl_exec($ch);

curl_close($ch);

$getResponse = json_decode(utf8_decode($response), true);

$errorCode = intval($getResponse["errorCode"]);

if ($errorCode > 0) {
    die("Error occured in step 2: " . $getResponse["error"] . ". Aborting run!");
} else if ($getResponse["value"] === "\"What's the frequency Kenneth?\"") {
    print("Step 2: Values match up, success. ID Deleted.<br/>");
} else {
    print("Step 2: Values don't match up, found: " . $getResponse["value"] . ", failure.<br/>");
}   //  End if ($errorCode > 0)

// STEP 3 - Set value and verify set works for integer value
// ********* Call Web Service to set settings ********** //
$ch                    = curl_init();

$elements              = array();
$elements["sessionId"] = md5(session_id());
$elements["key"]       = "key02";
$elements["value"]     = 65536;

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/setSession.php");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'ApiKey: $$API_KEY$$',           // $$ API_KEY $$
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'));

curl_setopt($ch, CURLOPT_SSLVERSION, 6);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(utf8_encode(json_encode($elements))));

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

// if (isset($_COOKIE["PHPSESSID"])) {
//     curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
// }   //  End if (isset($_COOKIE["PHPSESSID"]))

session_write_close();

$response = curl_exec($ch);

curl_close($ch);

$getResponse = json_decode(utf8_decode($response), true);

$errorCode = intval($getResponse["errorCode"]);

if ($errorCode === 0) {
    print("Step 3: id is " . $getResponse["id"] . "<br/>");
} else {
    die("Error occured in step 3: " . $getResponse["error"] . ". Aborting run!");
}   //  End if ($errorCode === 0)

// STEP 4 - Get value and verify get works, delete on fetch
// ********* Call Web Service to get settings ********** //
$ch                    = curl_init();

$elements              = array();
$elements["sessionId"] = md5(session_id());
$elements["key"]       = "key02";
$elements["delete"]    = true;

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/getSession.php");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'ApiKey: $$API_KEY$$',           // $$ API_KEY $$
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'));

curl_setopt($ch, CURLOPT_SSLVERSION, 6);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(utf8_encode(json_encode($elements))));

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

// if (isset($_COOKIE["PHPSESSID"])) {
//     curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
// }   //  End if (isset($_COOKIE["PHPSESSID"]))

session_write_close();

$response = curl_exec($ch);

curl_close($ch);

$getResponse = json_decode(utf8_decode($response), true);

$errorCode = intval($getResponse["errorCode"]);

if ($errorCode > 0) {
    die("Error occured in step 4: " . $getResponse["error"] . ". Aborting run!");
} else if (intval($getResponse["value"]) === 65536) {
    print("Step 4: Values match up, success. ID Deleted.<br/>");
} else {
    print("Step 4: Values don't match up, found: " . $getResponse["value"] . ", failure.<br/>");
}   //  End if ($errorCode > 0)

ob_end_flush();

// STEP 5 - Get value and verify they've all been deleted!
// ********* Call Web Service to get settings ********** //
$ch = curl_init();

$elements              = array();
$elements["sessionId"] = md5(session_id());
$elements["key"]       = "key02";

$inputJson             = array();
$inputJson["request"]  = $elements;

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/getSession.php");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'ApiKey: $$API_KEY$$',           // $$ API_KEY $$
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'));

curl_setopt($ch, CURLOPT_SSLVERSION, 6);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(utf8_encode(json_encode($inputJson))));

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

// if (isset($_COOKIE["PHPSESSID"])) {
//     curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
// }   //  End if (isset($_COOKIE["PHPSESSID"]))

session_write_close();

$response = curl_exec($ch);

curl_close($ch);

$getResponse = json_decode(utf8_decode($response), true);

$errorCode = intval($getResponse["errorCode"]);

if ($errorCode > 0) {
    die("Error occured in step 5: " . $getResponse["error"] . ". Aborting run!");
} else if ($getResponse["value"] === null) {
    print("Step 5: Values match up and null found, success.<br/>");
} else {
    print("Step 5: Values don't match up, found: " . $getResponse["value"] . ", failure.<br/>");
}   //  End if ($errorCode > 0)

print("Test complete!");

ob_end_flush();
?>
