<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: test_setUserLoginData.php - test web service to log user accessing this page
//
// Input JSON:
//   {"userId":2,
//    "logId":31,
//    "cookie":"444760c019414e1894029ac7139daf74",
//    "sessionKey":"a02cb1f0377a3164c819ed8979a10a60",
//    "browserHash":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36",
//    "expires":"2017-12-31 23:59:59",
//    "dump":true
//   }
//
// Output JSON:
//   {"errorCode":0,
//    "loginId":1564,
//    "expires":"2017-12-31 23:59:59"}
//
//   {"errorCode":2,
//    "error":"Could not connect to the database."}
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
//    1. Sundar Krishnamurthy          sundar_k@hotmail.com       10/10/2017      Initial file created.


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
if ((!isset($_GET["s"])) || ($_GET["s"] !== "$$TEST_QUERY_KEY$$")) {     // $$ TEST_QUERY_KEY $$
    exit();
}   //  End if ((!isset($_GET["s"])) || ($_GET["s"] !== "$$TEST_QUERY_KEY$$"))      // $$ TEST_QUERY_KEY $$

// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier)

// STEP 1 - Set all the fields for login details
// ********* Call Web Service to set user login data ********** //
$elements                  = array();
$elements["userId"]        = 2;
$elements["logId"]         = 13;
$elements["cookie"]        = "0000456789abcdefghij0123456789ab";
$elements["browserString"] = "Long Browser String";
$elements["sessionKey"]    = "1d635defabb04eea9c510fe66636457b";
$elements["expires"]       = "2017-12-31 23:59:59";
$elements["dump"]          = true;

$ch                        = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/setUserLoginDetails.php");

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "ApiKey: $$API_KEY$$",           // $$ API_KEY $$
    "Content-Type: application/x-www-form-urlencoded",
    "Accept: application/json"));

curl_setopt($ch, CURLOPT_SSLVERSION, 6);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(utf8_encode(json_encode($elements))));

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

session_write_close();

$response = curl_exec($ch);

curl_close($ch);

$setResponse         = json_decode(utf8_decode($response), true);
$errorCode           = intval($setResponse["errorCode"]);

$outputJson          = array();
$testCase            = array();

$testCase["success"] = ($errorCode === 0);

// Set data for test display (success or failure)
if ($errorCode === 0) {
    $testCase["loginId"]   = $setResponse["loginId"];
    $testCase["expires"]   = $setResponse["expires"];
    $testCase["query"]     = $setResponse["query"];
} else {
    $testCase["errorCode"] = setResponse["errorCode"];
    $testCase["message"]   = $setResponse["error"];
}   //  End if ($errorCode === 0)

$outputJson[] = $testCase;

print(json_encode($outputJson));

ob_end_flush();
?>
