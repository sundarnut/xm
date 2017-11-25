<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: setUserLoginDetails.php - set the current status for logging this user in, and tracking information for future access
//
// Input JSON:
//   {"userId":"47.33.41.130",
//    "logId":"Long browser string",
//    "cookie":"Some big cookie hash",
//    "sessionKey":"a02cb1f0377a3164c819ed8979a10a60",
//    "browserHash":"Browser Hash String",
//    "expires":"2017-12-31 23:59:59",
//    "dump":true}
//
// Output JSON:
//   {"errorCode":0,
//    "loginId":1564,
//    "expires":"2017-12-31 23:59:59"}
//
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
//    setSession - set the data for a session key and value pair
//
// JavaScript functions:
//    None
//
// Revisions:
//     1. Sundar Krishnamurthy          sundar_k@hotmail.com       11/24/2017      Initial file created.

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

// Authorized client that is accessing home-page, we need to log all meta-data one-time
if (($_SERVER["REQUEST_METHOD"] === "POST") &&
    (isset($_SERVER["HTTP_APIKEY"])) &&
    ($_SERVER["HTTP_APIKEY"] === "$$API_KEY$$") &&                     // $$ API_KEY $$
    ($_SERVER["SERVER_ADDR"] === $_SERVER["REMOTE_ADDR"])) {

    $postBody = utf8_decode(urldecode(file_get_contents("php://input")));

    // We found a valid body to process
    if ($postBody !== "") {
        $query         = null;
        $bitmask       = 0;
        $errorCode     = 0;
        $errorMessage  = null;

        $userId        = null;
        $logId         = null;
        $cookieHash    = null;
        $sessionKey    = null;
        $browserHash   = null;
        $expires       = null;
        $dump          = false;

        $outputJson    = array();

        $request       = json_decode($postBody, true);

        // userId is a mandatory parameter
        if (array_key_exists("userId", $request)) {
            $userId = intval($request["userId"]);

            // Verify if this value is a positive number
            if ($userId > 0) {
                $bitmask = 1;
            }   //  End if ($userId > 0)
        }   //  End if (array_key_exists("userId", $request))

        // and so is logId
        if (array_key_exists("logId", $request)) {
            $logId = intval($request["logId"]);

            // Verify if this value is a positive number
            if ($logId > 0) {
                $bitmask |= 2;
            }   //  End if ($logId > 0)
        }   //  End if (array_key_exists("logId", $request))

        // Store the MD5 hash of incoming cookie
        if (array_key_exists("cookie", $request)) {
            $inputCookie = $request["cookie"];

            // Verify if this value is 32-characters
            if (strlen($inputCookie) === 32) {
                $cookieHash = hash("md5", $inputCookie);
                $bitmask |= 4;
            }   //  End if (strlen($inputCookie) === 32)
        }   //  End if if (array_key_exists("logId", $request))

        // Check if sessionKey is furnished too
        if (array_key_exists("sessionKey", $request)) {
            $sessionKey = $request["sessionKey"];

            // Verify if this value is a positive number
            if (strlen($sessionKey) === 32) {
                $bitmask |= 8;
            }   //  End if (strlen($sessionKey) === 32)
        }   //  End if (array_key_exists("sessionKey", $request))

        // Check if browserString is furnished, ideally it should be there
        if (array_key_exists("browserString", $request)) {
            $browserString = $request["browserString"];

            // Verify if this value is really defined
            if (($browserString !== "") && ($browserString !== null)) {
                $browserHash = hash("md5", $browserString);
                $bitmask |= 16;
            }   //  End if (($browserString !== "") && ($browserString !== null))
        }   //  End if (array_key_exists("browserString", $request))

        // Lastly the expires tag
        if (array_key_exists("expires", $request)) {
            $expires = $request["expires"];

            if ($expires !== "") {
                $bitmask |= 32;
            }   //  End if ($expires !== "")
        }   //  End if (array_key_exists("expires", $request))

        if (array_key_exists("dump", $request)) {
            $dump = boolval($request["dump"]);
        }   //  End if (array_key_exists("dump", $request))

        // We have valid data coming for everything
        if ($bitmask === 63) {

            // Update sessionId and incoming data
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
                    $useSessionKey  = mysqli_real_escape_string($con, $sessionKey);

                    if (strlen($useSessionKey) > 32) {
                        $useSessionKey = substr($useSessionKey, 0, 32);
                    }   //  End if (strlen($useSessionKey) > 32)

                    $useExpires = mysqli_real_escape_string($con, $expires);

                    // This is the query we will log user access into the DB
                    $query = "call setLoginDetails($userId,$logId,'$cookieHash','$useSessionKey','$browserHash','$useExpires');";

                    // Result of query
                    $result = mysqli_query($con, $query);

                    // Unable to fetch result, display error message
                    if (!$result) {
                        $errorCode     = 3;
                        $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                        $errorMessage .= ("Whole query: " . $query);
                    } else if ($row = mysqli_fetch_assoc($result)) {
                        $outputJson["loginId"] = intval($row["loginId"]);
                        $outputJson["expires"] = $expires;

                        // Free result
                        mysqli_free_result($result);
                    }   //  End if (!$result)
                }   //  End if (!$db_selected)

                // Close connection
                mysqli_close($con);
            }   //  End if (!$con)

            $outputJson["errorCode"] = $errorCode;

            if ($errorMessage !== null) {
                $outputJson["error"] = $errorMessage;
            }   //  End if ($errorMessage !== null)

            if (($dump === true) && ($query !== null)) {
                $outputJson["query"] = $query;
            }   //  End if (($dump === true) && ($query !== null))

            // Send result back
            header('Content-Type: application/json; charset=utf-8');
            print(utf8_encode(json_encode($outputJson)));
        } else {
            mail("$$ADMIN_EMAIL$$", "Error in setUserLoginDetails.php", "Not all necessary json parameters furnished (63): " . $bitmask . "<br/>" . $postBody); //      $$ ADMIN_EMAIL $$
        }   //  End if ($bitmask === 63)
    }   //  End if ($postBody !== "")
}   //  End if (($_SERVER["REQUEST_METHOD"] === "POST") &&

ob_end_flush();
?>
