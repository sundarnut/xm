<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: test_memCached.php - test memCached, verify that it is loaded and communicates well
//
// Input JSON:
//    None
//
// Output JSON:
//    None
//
// Output JSON:
//    None
//
// Functions:
//    None
//
// Query Parameters:
//    s: key value that will let this test execute fine
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
//     1. Sundar Krishnamurthy          sundar_k@hotmail.com       06/17/2017      Initial file created.


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
if ((!isset($_GET["s"])) || ($_GET["s"] != "$$TEST_QUERY_KEY$$")) {             // $$ TEST_QUERY_KEY $$
    exit();
}   //  End if ((!isset($_GET["s"])) || ($_GET["s"] != "$$TEST_QUERY_KEY$$"))           // $$ TEST_QUERY_KEY $$
	
// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier)
	
// ********* Test writing and reading from memCached ********** //

$memCache = new Memcache;
$memCache->connect('localhost', 11211);

// Set session data in memCache with a 20-minute timeout
$memCache->set("testKey", "testValue 1234567890", false, 60);

$key = $memCache->get("testKey");

print("Found key: $key");

// ********* End test ********* //
ob_end_flush();
?>
