<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: logAccess.php - log user access being performed for this user
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
//    None
//
// Custom Headers:
//     ApiKey: Must contain magic value for this service to be employed
//
// Session Variables:
//     None
//
// Stored Procedures:
//     logUserAccess - log the user access parameters
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

// Include ipfunctions.php that contains functions for IP address conversion
require_once("../ipfunctions.php");

// Start output buffering on
ob_start();

// Start the initial session
session_start();

// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: http://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier)

// Authorized client that is asking for settings for a user landing on the page for the first time
if ((isset($_SERVER["HTTP_APIKEY"])) &&
    ($_SERVER["HTTP_APIKEY"] === "$$API_KEY$$")) {             // $$ API_KEY $$

    $postBody = urldecode(file_get_contents("php://input"));

    // We found a valid body to process
    if ($postBody != "") {

        $logId        = 0;
        $errorCode    = 0;
        $errorMessage = null;

        $requestData  = json_decode($postBody, true);
        $request      = $requestData["request"];

        $ipAddress    = $request["ipAddress"];
        $userAgent    = $request["userAgent"];
        $referer      = $request["referer"];
        $sessionKey   = $request["sessionKey"];

        // Update access log in DB
        // Connect to DB
        $con = mysqli_connect($global_dbServer, $global_dbUsername, $global_dbPassword);

        // Unable to connect, display error message
        if (!$con) {
            $errorCode    = 1;
            $errorMessage = "Could not connect to database server.";
        } else {

            // DB selected will be selected Database on server
            $db_selected = mysqli_select_db($con, $global_dbName);

            // Unable to use DB, display error message
            if (!$db_selected) {
                $errorCode    = 2;
                $errorMessage = "Could not connect to the database.";
            } else {

                $useIpAddress  = "null";
                $useUserAgent  = "null";
                $useReferer    = "null";
                $useSessionKey = "null";

                if ($ipAddress != "") {
                    $useIpAddress = inet_ptod($ipAddress);
                }   //  End if ($ipAddress != "")

                if ($userAgent != "") {
                    $useUserAgent = mysqli_real_escape_string($con, $userAgent);

                    if (strlen($useUserAgent) > 256) {
                        $useUserAgent = substr($useUserAgent, 0, 256);
                    }   //  End if (strlen($useUserAgent) > 256)

                    $useUserAgent = "'" . $useUserAgent . "'";
                }   //  End if ($userAgent != "")

                if ($referer != "") {
                    $useReferer = mysqli_real_escape_string($con, $referer);

                    if (strlen($useReferer) > 256) {
                        $useReferer = substr($useReferer, 0, 256);
                    }   //  End if (strlen($useReferer) > 256)

                    $useReferer = "'" . $useReferer . "'";
                }   //  End if ($referer != "")

                if ($sessionKey != "") {
                    $useSessionKey = mysqli_real_escape_string($con, $sessionKey);

                    if (strlen($useSessionKey) > 32) {
                        $useSessionKey = substr($useSessionKey, 0, 32);
                    }   //  End if (strlen($useSessionKey) > 32)

                    $useSessionKey = "'" . $useSessionKey . "'";
                }   //  End if ($sessionKey != "")

                // This is the query we will run to insert user metadata one time into the DB
                $query = "call logUserAccess($useIpAddress,$useUserAgent,$useReferer,$useSessionKey);";

                // Result of query
                $result = mysqli_query($con, $query);

                // Unable to fetch result, display error message
                if (!$result) {
                    $errorCode     = 3;
                    $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                    $errorMessage .= ("Whole query: " . $query);
                } else if ($row = mysqli_fetch_assoc($result)) {

                    $logId   = intval($row["logId"]);

                    // Free result
                    mysqli_free_result($result);
                }   //  End if (!$result)
            }   //  End if (!$db_selected)

            // Close connection
            mysqli_close($con);
        }   //  End if (!$con)

        $outputJson           = array();
        $logJson              = array();
        $logJson["errorCode"] = $errorCode;

        if ($errorMessage === null) {
            $logJson["logId"] = $logId;
        } else {
            $logJson["error"] = $errorMessage;
        }   //  End if ($errorMessage === null)

        $outputJson["response"] = $logJson;

        // Send result back
        print(json_encode($outputJson));

    }   //  End if ($postBody != "")
}   //  End if ((isset($_SERVER["HTTP_APIKEY"])) &&

ob_end_flush();
?>
