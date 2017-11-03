<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: test_mailservice.php - test web service to add email
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

$timestamp = "2017-11-02 22:00:00";

// Break out of test if key not present in incoming request
if (isset($_GET["t"])) {
    $timestamp = $_GET["t"];
}   //  End if (isset($_GET["t"]))

// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier)

// STEP 1 - Set value and verify set works
// ********* Call Web Service to set settings ********** //
$ch = curl_init();

$elements               = array();
$elements["to"]         = "$$TARGET_EMAIL_ADDRESS$$";     // $$ TARGET_EMAIL_ADDRESS $$
$elements["from"]       = "$$YOUR_NAME$$";                // $$ YOUR_NAME $$
$elements["fromEmail"]  = "$$YOUR_EMAIL_ADDRESS$$";       // $$ YOUR_EMAIL_ADDRESS $$
$elements["subject"]    = "Get Organized Reminder";
$elements["body"]       = "I tried telling you many times, please <b>get organized</b> Write ஆயுத எழுத்து.";
$elements["cc"]         = "$$CC_EMAIL_ADDRESS$$";         // $$ CC_EMAIL_ADDRESS $$
$elements["bcc"]        = "$$BCC_EMAIL_ADDRESS$$";        // $$ BCC_EMAIL_ADDRESS $$
$elements["importance"] = true;
$elements["timestamp"]  = $timestamp;
$elements["replyTo"]    = "$$YOUR_EMAIL_ADDRESS$$";       // $$ YOUR_EMAIL_ADDRESS $$
$elements["dump"]       = true;
$elements["prefix"]     = "$$SOME_UNICODE_STRING$$";      // $$ SOME_UNICODE_STRING $$

$attachments = array();
$attachments[] = array(
    "filename" => "dot.gif",
    "data" => "R0lGODlhMgAyAPcAAAAAAAAAMwAAZgAAmQAAzAAA/wArAAArMwArZgArmQArzAAr/wBVAABVMwBVZgBVmQBVzABV/wCAAACAMwCAZgCAmQCAzACA/wCqAACqMwCqZgCqmQCqzA" .
               "Cq/wDVAADVMwDVZgDVmQDVzADV/wD/AAD/MwD/ZgD/mQD/zAD//zMAADMAMzMAZjMAmTMAzDMA/zMrADMrMzMrZjMrmTMrzDMr/zNVADNVMzNVZjNVmTNVzDNV/zOAADOAMzOAZjOAmTOAzDOA/zOqADOqM" .
               "zOqZjOqmTOqzDOq/zPVADPVMzPVZjPVmTPVzDPV/zP/ADP/MzP/ZjP/mTP/zDP//2YAAGYAM2YAZmYAmWYAzGYA/2YrAGYrM2YrZmYrmWYrzGYr/2ZVAGZVM2ZVZmZVmWZVzGZV/2aAAGaAM2aAZmaAmWaA" .
               "zGaA/2aqAGaqM2aqZmaqmWaqzGaq/2bVAGbVM2bVZmbVmWbVzGbV/2b/AGb/M2b/Zmb/mWb/zGb//5kAAJkAM5kAZpkAmZkAzJkA/5krAJkrM5krZpkrmZkrzJkr/5lVAJlVM5lVZplVmZlVzJlV/5mAAJm" .
               "AM5mAZpmAmZmAzJmA/5mqAJmqM5mqZpmqmZmqzJmq/5nVAJnVM5nVZpnVmZnVzJnV/5n/AJn/M5n/Zpn/mZn/zJn//8wAAMwAM8wAZswAmcwAzMwA/8wrAMwrM8wrZswrmcwrzMwr/8xVAMxVM8xVZsxVmc" .
               "xVzMxV/8yAAMyAM8yAZsyAmcyAzMyA/8yqAMyqM8yqZsyqmcyqzMyq/8zVAMzVM8zVZszVmczVzMzV/8z/AMz/M8z/Zsz/mcz/zMz///8AAP8AM/8AZv8Amf8AzP8A//8rAP8rM/8rZv8rmf8rzP8r//9VA" .
               "P9VM/9VZv9Vmf9VzP9V//+AAP+AM/+AZv+Amf+AzP+A//+qAP+qM/+qZv+qmf+qzP+q///VAP/VM//VZv/Vmf/VzP/V////AP//M///Zv//mf//zP///wAAAAAAAAAAAAAAACH5BAEAAPwALAAAAAAyADIA" .
               "AAj/APcJHEiwoMGDCBMqXEjQnsOHEBlKZAgRnj2LGC8+nMhR4MOMH0Nq3NhRoUaLDkGqTKmx5EGIJ1mOHIkSpUOXA2PatFjOW7mfPsvRZGkRp0iN38qR+6mU6dKfMmva6xhT48+nTa9qXfot6s2JIuFpHZu" .
               "V6VhyXiV6LYsVq9agT7sOXVjVXtKybPGafSrzosmdS922Nbs1r9SvBofuzfuThg7HNAiznWvwMFzCTyGTebxZx+Os37BSbjjzbE+mnr14prGZtePPbZPKTGwztOSfnF+rXu1Zx27To/eFFFt46ePer11v3u" .
               "1aMtqRBEGSdaqDjHId2KZlx6a7d+CmXWuS/7b6lJztcpB7S5v2StqrbKmyr7auA3PInNJvl+PseVo2bKn8F1827e3mWVNxzeQRS7Z9Zxxrx7nnnyv+xcdeasctRpRA+TnXmmfSwIfNKxOKmI0080VWloLCs" .
               "YRgWY61lo2A2VAoIonZ8KfDYBvuA5JbWvGmAyomajcge9K89plW4ZEUUlJLBdXYcjpkMw2Fr7hiZXuvYJOjbwe21eOPmCl13HFetrfliP6lp9pdfNm0oFVZuQVmfwNaqKYr1Xk32UkeSTWdUt1VeaWJJB7X" .
               "nFPlNInYTHe9iFty/dV4oWf01QfkcDm5+N1eGGaKHJgxqoigXChFN1KkkqLHG32qfa9I36dX0TTeQy/CaSaEZ2qWYZkiFQQSq0DuSmWfjr2IlaNTCSvTpw4yimGyxULJKW0QsVpmYXk52FdCVSnLGKODCiU" .
               "VPOD2ZZauutbJKF8sJnSuPdGSW2xc5tpK0U72LHavUpEuBVNRYK0E5HdSakUUdGAdJVZ5zmU1k3glzetiYXINlSpOwvHr1cB9+cXxnIfNW3JLI6talU1hkZTyS16t1OzLFA2FMs0456xzRwEBADs=");

$attachments[] = array(
    "filename" => "helloworld.txt",
    "data" => "aGVsbG8sd29ybGQ=");

$elements["attachments"] = $attachments;

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/mailservice.php");

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "ApiKey: $$VALID_MAIL_API_KEY$$",           // $$ VALID_MAIL_API_KEY $$
    "Content-Type: application/x-www-form-urlencoded",
    "Accept: application/json"));

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

print($response);

ob_end_flush();
?>
