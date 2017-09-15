<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: getSession.php - get the session data for a certain sessionId and variable
//
// Input JSON:
//   {"sessionId":"a02cb1f0377a3164c819ed8979a10a60",
//    "key":"foo"}
//
//   {"sessionId":"a02cb1f0377a3164c819ed8979a10a60",
//      "key":"foo",
//      delete:1}
//
// Output JSON:
//   {"errorCode":0,
//    "id":5,
//    "value":1}
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
//     ApiKey: Must contain magic value for this service to be employed
//
// Session Variables:
//     None
//
// Stored Procedures:
//    getSession - get the data for a session key and value pair
//
// JavaScript functions:
//    None
//
// Revisions:
//     1. Sundar Krishnamurthy          sundar_k@hotmail.com       09/04/2017      Initial file created.


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
        $id           = 0;
        $errorCode    = 0;
        $errorMessage = null;
        $delete       = 0;

        $request      = json_decode($postBody, true);

        $sessionId    = $request["sessionId"];
        $key          = $request["key"];

        if (array_key_exists("delete", $request)) {
            $delete = intval($request["delete"]);
        }   //  End if (in_array("delete", $request))

        // We have valid data coming for sessionId and key names
        if (($sessionId != "") &&
            ($key != "")) {

            $id    = 0;
            $value = "";

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

                    $useSessionId = mysqli_real_escape_string($con, $sessionId);

                    if (strlen($useSessionId) > 32) {
                        $useSessionId = substr($useSessionId, 0, 32);
                    }   //  End if (strlen($useSessionId) > 32)

                    $useKey = mysqli_real_escape_string($con, $key);

                    if (strlen($useKey) > 32) {
                        $useKey = substr($useKey, 0, 32);
                    }   //  End if (strlen($useKey) > 32)

                    // This is the query we will run to insert sessionId, key and value into the DB
                    $query = "call getSession('$useSessionId','$useKey',$delete);";

                    // Result of query
                    $result = mysqli_query($con, $query);

                    // Unable to fetch result, display error message
                    if (!$result) {
                        $errorCode     = 3;
                        $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                        $errorMessage .= ("Whole query: " . $query);
                    } else if ($row = mysqli_fetch_assoc($result)) {

                        if ($row["id"] != null) {
                            $id = intval($row["id"]);
                        }   //  End if ($row["id"] != null)

                        if ($row["value"] != null) {
                            $value = $row["value"];
                        } else if ($row["intValue"] != null) {
                            $value = intval($row["intValue"]);
                        }   //  End if ($row["value"] != null)

                        // Free result
                        mysqli_free_result($result);
                    }   //  End if (!$result)
                }   //  End if (!$db_selected)

                // Close connection
                mysqli_close($con);
            }   //  End if (!$con)

            $sessionJson              = array();
            $sessionJson["errorCode"] = $errorCode;

            if ($errorMessage === null) {
                $sessionJson["id"]    = $id;
                $sessionJson["value"] = $value;
            } else {
                $sessionJson["error"] = $errorMessage;
            }   //  End if ($errorMessage === null)

            // Send result back
            header('Content-Type: application/json; charset=utf-8');
            print(utf8_encode(json_encode($sessionJson)));
        }   //  End if (($sessionId != "") &&
    }   //  End if ($postBody !== "")
}   //  End if (($_SERVER["REQUEST_METHOD"] === "POST") &&

ob_end_flush();
?>
