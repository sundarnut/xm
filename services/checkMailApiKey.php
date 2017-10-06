<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: checkMailApiKey.php - check if the furnished API key is valid in the DB
//
// Input JSON:
//   {"mailApiKey":"a02cb1f0377a3164c819ed8979a10a60"}
//
// Output JSON:
//   {"errorCode":0,
//    "active":1}
//
//   {"errorCode":1,
//    "error":"Long exception message"}
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
//   checkMailApiKey - check if the furnished Mail API key is active in the DB
//
// JavaScript functions:
//   None
//
// Revisions:
//     1. Sundar Krishnamurthy          sundar_k@hotmail.com       10/05/2017      Initial file created.
//     2. Sundar Krishnamurthy          sundar_k@hotmail.com       10/06/2017      Initial file created.

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

// We need a session variable that may be saved to this table, look up with sessionID and key
if (($_SERVER["REQUEST_METHOD"] === "POST") &&
    (isset($_SERVER["HTTP_APIKEY"])) &&
    ($_SERVER["HTTP_APIKEY"] === "$$API_KEY$$") &&                     // $$ API_KEY $$
    ($_SERVER["SERVER_ADDR"] === $_SERVER["REMOTE_ADDR"])) {

    $postBody = utf8_decode(urldecode(file_get_contents("php://input")));

    // We found a valid body to process
    if ($postBody !== "") {
        $active       = 0;
        $errorCode    = 0;
        $errorMessage = null;

        $request      = json_decode($postBody, true);

        $mailApiKey   = $request["mailApiKey"];

        // We have valid data coming for mailApiKey
        if (strlen($mailApiKey) === 32) {

            // Update session key and value in DB for sessionId
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

                    $useMailApiKey = mysqli_real_escape_string($con, $mailApiKey);

                    if (strlen($useMailApiKey) > 32) {
                        $useMailApiKey = substr($useMailApiKey, 0, 32);
                    }   //  End if (strlen($useMailApiKey) > 32)

                    // This is the query we will run to check the validity of mailApiKey in the DB
                    $query = "call checkMailApiKey('$useMailApiKey');";

                    // Result of query
                    $result = mysqli_query($con, $query);

                    // Unable to fetch result, display error message
                    if (!$result) {
                        $errorCode     = 3;
                        $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                        $errorMessage .= ("Whole query: " . $query);
                    } else if ($row = mysqli_fetch_assoc($result)) {

                        if ($row["active"] != null) {
                            $active = intval($row["active"]);
                        }   //  End if ($row["active"] != null)

                        // Free result
                        mysqli_free_result($result);
                    }   //  End if (!$result)
                }   //  End if (!$db_selected)

                // Close connection
                mysqli_close($con);
            }   //  End if (!$con)

            $responseJson              = array();
            $responseJson["errorCode"] = $errorCode;

            if ($errorMessage === null) {
                $responseJson["active"] = $active;
            } else {
                $responseJson["error"] = $errorMessage;
            }   //  End if ($errorMessage === null)

            // Send result back
            header('Content-Type: application/json; charset=utf-8');
            print(utf8_encode(json_encode($responseJson)));

        }   //  End if (strlen($mailApiKey) === 32) {
    }   //  End if ($postBody !== "")
}   //  End if (($_SERVER["REQUEST_METHOD"] === "POST") &&

ob_end_flush();
?>
