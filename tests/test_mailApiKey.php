<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: test_mailApiKey.php - test web service that verifies if mailApiKeys furnished are valid or not
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
//    1. Sundar Krishnamurthy          sundar_k@hotmail.com       10/05/2017      Initial file created.

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

// STEP 1 - Positive use-case
// ********* Call Web Service with valid MailApiKey, verify active=1 ********** //
$ch                      = curl_init();
$elements                = array();
$elements["mailApiKey"]  = "$$VALID_MAIL_API_KEY$$";               // $$ VALID_MAIL_API_KEY $$

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/checkMailApiKey.php");

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

$checkResponse     = json_decode(utf8_decode($response), true);
$errorCode         = intval($checkResponse["errorCode"]);
$results["Test 1"] = "Failure";

if ($errorCode === 0) {
    $active = intval($checkResponse["active"]);

    if ($active === 1) {
       $results["Test 1"] = "Success";
    }  //  End if ($active === 1)
} else {
    $results["Test 1 Message"] = $checkResponse["error"];
}   //  End if ($errorCode === 0)


// STEP 2 - Negative use-case
// ********* Call Web Service with invalid MailApiKey, verify active=0 ********** //
$ch                      = curl_init();
$elements                = array();
$elements["mailApiKey"]  = "$$INVALID_MAIL_API_KEY$$";         // $$ INVALID_MAIL_API_KEY $$

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/checkMailApiKey.php");
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

$checkResponse     = json_decode(utf8_decode($response), true);
$errorCode         = intval($checkResponse["errorCode"]);
$results["Test 2"] = "Failure";

if ($errorCode === 0) {
    $active = intval($checkResponse["active"]);

    if ($active === 0) {
       $results["Test 2"] = "Success";
    }  //  End if ($active === 0)
} else {
    $results["Test 2 Message"] = $checkResponse["error"];
}   //  End if ($errorCode === 0)

print(json_encode($results));

ob_end_flush();
?>
