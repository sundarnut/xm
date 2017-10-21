<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: logEmailDispatch.php - Log an email dispatch operation to the DB
//
// Input JSON:
// {"from":"sundar@somewebsite.com",
//  "to":"sundar@someotherwebsite.com,sundar@foobar.com",
//  "subject":"Some subject",
//  "dump":true}
//
// Output JSON:
//   {"errorCode":0,
//    "logId":33,
//    "query":"SQL queries run"}
//
// Output JSON:
//   {"errorCode":1,
//    "error":"Long exception stack trace"}
//
// Functions:
//    None
//
// Query Parameters:
//    None
//
// Custom Headers:
//    ApiKey: Must contain magic value for this service to be employed
//
// Session Variables:
//    None
//
// Stored Procedures:
//    logEmailDispatch - stored procedure to log the use case where we have successfully sent a mail
//
// JavaScript functions:
//    None
//
// Revisions:
//    1. Sundar Krishnamurthy          sundar_k@hotmail.com       10/21/2017      Initial file created.

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
if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier)

// Authorized client that is asking for settings for a user landing on the page for the first time
if (($_SERVER["REQUEST_METHOD"] === "POST") &&
    (isset($_SERVER["HTTP_APIKEY"])) &&
    ($_SERVER["HTTP_APIKEY"] === "$$API_KEY$$") &&                     // $$ API_KEY $$
    ($_SERVER["SERVER_ADDR"] === $_SERVER["REMOTE_ADDR"])) {

    $postBody = utf8_decode(urldecode(file_get_contents("php://input")));

    // We found a valid body to process
    if ($postBody !== "") {

        $logId        = 0;
        $dump         = false;
        $query        = null;

        $errorCode    = 0;
        $errorMessage = null;

        $logRequest   = json_decode($postBody, true);

        // Check if dump was a part of the input json set
        if (array_key_exists("dump", $logRequest)) {

            $dump = boolval($logRequest["dump"]);

            // Remove dump from array, create json string for rest of input
            unset($logRequest["dump"]);
            $postBody = json_encode($logRequest);
        }   // End if (array_key_exists("dump", $mailRequest))

        // Add Email to the database, with ready flag
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
                // 
                $useMessage = mysqli_real_escape_string($con, $postBody);

                // This is the query we will run to insert mail dispatch message into the DB
                $query      = "call logEmailDispatch('$useMessage');";

                // Result of query
                $result     = mysqli_query($con, $query);

                // Unable to fetch result, display error message
                if (!$result) {
                    $errorCode     = 3;
                    $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                    $errorMessage .= ("Whole query: " . $query);
                } else if ($row = mysqli_fetch_assoc($result)) {
                    $logId = intval($row["logId"]);

                    // Free result
                    mysqli_free_result($result);
                }   //  End if (!$result)
            }   //  End if (!$db_selected)

            // Close connection
            mysqli_close($con);
        }   //  End if (!$con)

        $responseJson              = array();
        $responseJson["errorCode"] = $errorCode;

        // Some error occured
        if ($errorMessage !== null) {
            $responseJson["error"] = $errorMessage;
        } else {
            // Add logId if successful
            $responseJson["logId"] = $logId;

            if ($dump === true) {
                $responseJson["query"] = $query;
            }   //  End if ($dump === true)
        }   //  End if ($errorMessage !== null)

        // Send result back
        header('Content-Type: application/json; charset=utf-8');
        print(utf8_encode(json_encode($responseJson)));
    }   //  End if ($postBody !== "")
}   //  End if (($_SERVER["REQUEST_METHOD"] === "POST") &&   

ob_end_flush();
?>
