<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: getSettings.php - get list of configured settings in DB
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
//     2. Sundar Krishnamurthy          sundar_k@hotmail.com       06/18/2017      Final updates, including UTF-8 encoding, application/json header


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

// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier)

// Authorized client that is asking for settings for a user landing on the page for the first time
if ((isset($_SERVER["HTTP_APIKEY"])) &&
    ($_SERVER["HTTP_APIKEY"] === "$$API_KEY$$") &&                     // $$ API_KEY $$
    ($_SERVER["SERVER_ADDR"] === $_SERVER["REMOTE_ADDR"])) {

    $settings     = null;
    $errorCode    = 0;
    $errorMessage = null;

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

            // This is the query we will run to fetch all settingsd
            $query = "call getEnabledSettings();";

            // Result of query
            $result = mysqli_query($con, $query);

            // Unable to fetch result, display error message
            if (!$result) {
                $errorCode     = 3;
                $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                $errorMessage .= ("Whole query: " . $query . ".");
            } else {

                $settings = array();

                // Iterate each row
                while ($row = mysqli_fetch_assoc($result)) {
                    $settings[] = array($row["name"] => $row["value"]);
                }   //  End while ($row = mysqli_fetch_assoc($result))

                // Free result
                mysqli_free_result($result);
            }   //  End if (!$result)
        }   //  End if (!$db_selected)

        // Close connection
        mysqli_close($con);
    }   //  End if (!$con)

    $outputJson                = array();
    $settingsJson              = array();
    $settingsJson["errorCode"] = $errorCode;

    if ($errorMessage === null) {
        $settingsJson["settings"] = $settings;
    } else {
        $settingsJson["error"]    = $errorMessage;
    }   //  End if ($errorMessage === null)

    $outputJson["response"] = $settingsJson;

    // Send result back
	header('Content-Type: application/json; charset=utf-8');
    print(utf8_encode(json_encode($outputJson)));
}   //  End if ((isset($_SERVER["HTTP_APIKEY"])) &&

ob_end_flush();
?>
