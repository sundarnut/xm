<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: addEmail.php - Add an email to the queue, to be sent when the timestamp comes in focus
//
// Input JSON:
// {
//  "apiKey": "a7881524298e413ba00396c24379e3fa",
//  "to": "john.doe@abc.com",
//  "from": "Jane Doe",
//  "fromEmail": "jane@someemail.com",
//  "replyTo":"jane@foobar.com",
//  "subject": "Get Organized Reminder",
//  "body": "I tried telling you many times, please get organized<\/b>.",
//  "cc": "jane1@foo.com,jane2@bar.com",
//  "bcc": "jane@somesite.com",
//  "importance": true,
//  "timestamp": "2017-12-16 13:00:00",
//  "attachments": [{
//      "name": "dot.gif",
//      "data": "Long base 64 string"
//   }, {
//      "name": "helloworld.txt",
//      "data": "aGVsbG8sd29ybGQ="
//   }]
// }
//
// Output JSON:
//   {"errorCode":0,
//    "id":1}
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
//    addEmail - save the email with timestamp for subsequent propagation
//    addEmailNew - save the email with minimal, necessary fields. Same drill
//
// JavaScript functions:
//    None
//
// Revisions:
//    1. Sundar Krishnamurthy          sundar_k@hotmail.com       10/09/2017      Initial file created.

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
        $mailId       = 0;

        $errorCode    = 0;
        $errorMessage = null;
        $dump         = false;
        $mainQuery    = null;

        $fieldMask    = 0;

        $to           = null;
        $subject      = null;
        $body         = null;
        $apiKey       = null;
        $from         = null;
        $fromEmail    = null;
        $replyTo      = null;
        $cc           = null;
        $bcc          = null;
        $ready        = true;
        $prefix       = null;
        $importance   = false;
        $timestamp    = null;
        $attachments  = null;

        $mailRequest  = json_decode($postBody, true);

        // to is a mandatory field
        if (array_key_exists("to", $mailRequest)) {

            $to = verifyEmails($mailRequest["to"], 4096);

            // TODO: Regex verify that these are email addresses
            if ($to !== "") {
                $fieldMask = 1;
            }   //  End if ($to != "")
        }   // End if (array_key_exists("to", $mailRequest))

        // So are subject,
        if (array_key_exists("subject", $mailRequest)) {
            $subject = trim($mailRequest["subject"]);

            if ($subject !== "") {
                $fieldMask |= 2;
            }   //  End if ($subject != "")
        }   // End if (array_key_exists("subject", $mailRequest))

        // and body
        if (array_key_exists("body", $mailRequest)) {
            $body = trim($mailRequest["body"]);
 
            if ($body !== "") {
                $fieldMask |= 4;
            }   //  End if ($body !== "")
        }   // End if (array_key_exists("body", $mailRequest))

        // Without an API Key, you can't employ this service
        if (array_key_exists("apiKey", $mailRequest)) {
            $apiKey = trim($mailRequest["apiKey"]);

            // Process 16 byte API keys, that's it!
            if (strlen($apiKey) === 32) {
                $fieldMask |= 8;
            }   //  End if (strlen($apiKey) === 32)
        }   // End if (array_key_exists("apiKey", $mailRequest))

        // Proceed only if four mandatory fields found
        if ($fieldMask === 15) {

            // Read all other fields, if present
            // Optional fromEmail field, force set from field initially to this too
            if (array_key_exists("fromEmail", $mailRequest)) {
                $fromEmail = $from = verifyEmails($mailRequest["fromEmail"], 128, true);
            }   // End if (array_key_exists("fromEmail", $mailRequest))
          
            // Optional from field
            if (array_key_exists("from", $mailRequest)) {
                $from = trim($mailRequest["from"]);
            }   // End if (array_key_exists("from", $mailRequest))

            // Optional reply-to field
            if (array_key_exists("replyTo", $mailRequest)) {
                $replyTo = verifyEmails($mailRequest["replyTo"], 128, true);
            }   // End if (array_key_exists("replyTo", $mailRequest))

            // Optional cc field
            if (array_key_exists("cc", $mailRequest)) {
                $cc = verifyEmails($mailRequest["cc"], 4096);
            }   // End if (array_key_exists("cc", $mailRequest))

            // Optional bcc field
            if (array_key_exists("bcc", $mailRequest)) {
                $bcc = verifyEmails($mailRequest["bcc"], 4096);
            }   // End if (array_key_exists("bcc", $mailRequest))

            // Optional importance field
            if (array_key_exists("importance", $mailRequest)) {
                $importance = boolval($mailRequest["importance"]);
            }   // End if (array_key_exists("importance", $mailRequest))

            // Optional dump field
            if (array_key_exists("dump", $mailRequest)) {
                $dump = boolval($mailRequest["dump"]);
            }   // End if (array_key_exists("dump", $mailRequest))

            // Optional prefix field
            if (array_key_exists("prefix", $mailRequest)) {
                $prefix = trim($mailRequest["prefix"]);
            }   // End if (array_key_exists("prefix", $mailRequest))

            // Optional timestamp field
            if (array_key_exists("timestamp", $mailRequest)) {
                // TODO: Regex to check if this is a valid date-time
                $inputTimestamp = trim($mailRequest["timestamp"]);

                // Only process valid dates and times (UTC)
                if (strtotime($inputTimestamp) !== false) {
                    $timestamp = $inputTimestamp;
                }   //  End if (strtotime($inputTimestamp) !== false)
            }   // End if (array_key_exists("timestamp", $mailRequest))

            if (array_key_exists("attachments", $mailRequest)) {
                $attachments = $mailRequest["attachments"];

                if (count($attachments) > 0) {
                    $ready = false;
                }   //  End if (count($attachments) > 0)
            }   //  End if (array_key_exists("attachments", $mailRequest))

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

                    // To field
                    $useTo = mysqli_real_escape_string($con, $to);

                    if (strlen($useTo) > 4096) {
                        // Locate the last comma, skip this and the email address following it 
                        $commaPosition = strrpos($useTo, ",");
                        $useTo         = substr($useTo, 0, $commaPosition);
                    }   //  End if (strlen($useTo) > 4096)

                    // Subject field
                    $useSubject = mysqli_real_escape_string($con, $subject);

                    if (strlen($useSubject) > 255) {
                        $useSubject = substr($useSubject, 0, 255);
                    }   //  End if (strlen($useSubject) > 255)
       
                    // Body
                    $useBody = mysqli_real_escape_string($con, $body);

                    // Add upper bounds on email body, 65536 bytes
                    if (strlen($useBody) > 65536) {
                        $useBody = substr($useBody, 0, 65536);
                    }   //  End if (strlen($useBody) > 65536)               

                    // API Key field
                    $useApiKey = mysqli_real_escape_string($con, $apiKey);

                    if (strlen($useApiKey) > 32) {
                        $useApiKey = substr($useApiKey, 0, 32);
                    }   //  End if (strlen($useApiKey) > 32)

                    // From field
                    $useFrom = "null";

                    if (($from !== "") && ($from !== null)) {
                        $useFrom = mysqli_real_escape_string($con, $from);

                        if (strlen($useFrom) > 128) {
                            $useFrom = substr($useFrom, 0, 128);
                        }   //  End if (strlen($useFrom) > 128)

                        $useFrom = "'" . $useFrom . "'";
                    }   //  End if (($from !== "") && ($from !== null))

                    // FromEmail field
                    $useFromEmail = "null";

                    if (($fromEmail !== "") && ($fromEmail !== null)) {
                        $useFromEmail = mysqli_real_escape_string($con, $fromEmail);

                        if (strlen($useFromEmail) > 128) {
                            $useFromEmail = substr($useFromEmail, 0, 128);
                        }   //  End if (strlen($useFromEmail) > 128)

                        $useFromEmail = "'" . $useFromEmail . "'";
                    }   //  End if (($fromEmail !== "") && ($fromEmail !== null))

                    // replyTo field
                    $useReplyTo = "null";

                    if (($replyTo !== "") && ($replyTo !== null)) {
                        $useReplyTo = mysqli_real_escape_string($con, $replyTo);

                        if (strlen($useReplyTo) > 128) {
                            $useReplyTo = substr($useReplyTo, 0, 128);
                        }   //  End if (strlen($useReplyTo) > 128)

                        $useReplyTo = "'" . $useReplyTo . "'";
                    }   //  End if (($replyTo !== "") && ($replyTo !== null))

                    $useCc = "null";

                    if (($cc !== "") && ($cc !== null)) {
                        $useCc = mysqli_real_escape_string($con, $cc);

                        if (strlen($useCc) > 4096) {
                            // Locate the last comma, skip this and the email address following it 
                            $commaPosition = strrpos($useCc, ",");
                            $useCc         = substr($useCc, 0, $commaPosition);
                        }   //  End if (strlen($useCc) > 4096)

                        $useCc = "'" . $useCc . "'";
                    }   //  End if (($cc !== "") && ($cc !== null)) {

                    $useBcc = "null";

                    if (($bcc !== "") && ($bcc !== null)) {
                        $useBcc = mysqli_real_escape_string($con, $bcc);

                        if (strlen($useBcc) > 4096) {
                            // Locate the last comma, skip this and the email address following it 
                            $commaPosition = strrpos($useBcc, ",");
                            $useBcc        = substr($useBcc, 0, $commaPosition);
                        }   //  End if (strlen($useBcc) > 4096)

                        $useBcc = "'" . $useBcc . "'";
                    }   //  End if (($bcc !== "") && ($bcc !== null)) {

                    // Prefix field
                    $usePrefix = "null";

                    if (($prefix !== "") && ($prefix !== null)) {
                        $usePrefix = mysqli_real_escape_string($con, $prefix);

                        if (strlen($usePrefix) > 64) {
                            $usePrefix = substr($usePrefix, 0, 64);
                        }   //  End if (strlen($usePrefix) > 64)

                        $usePrefix = "'" . $usePrefix . "'";
                    }   //  End if (($prefix !== "") && ($prefix !== null))

                    // Timestamp fields
                    $useTimestamp = "null";

                    if (($timestamp !== "") && ($timestamp !== null)) {
                        $useTimestamp = "'" . mysqli_real_escape_string($con, $timestamp) . "'";
                    }   //  End if (($timestamp !== "") && ($timestamp !== null))

                    $useReady          = $ready ? 1 : 0;
                    $useHasAttachments = $ready ? 0 : 1;
                    $useImportance     = $importance ? 1 : 0;

                    // This is the query we will run to insert sessionId, key and value into the DB
                    $mainQuery = "call addEmail('$useApiKey',$useFrom,$useFromEmail,'$useTo',$useCc,$useBcc,$useReplyTo,'$useSubject'," . 
                                 "$usePrefix,'$useBody',$useReady,$useHasAttachments,$useImportance,$useTimestamp);";

                    // Result of query
                    $result = mysqli_query($con, $mainQuery);

                    // Unable to fetch result, display error message
                    if (!$result) {
                        $errorCode     = 3;
                        $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                        $errorMessage .= ("Whole query: " . $mainQuery);
                    } else if ($row = mysqli_fetch_assoc($result)) {
                        $mailId = intval($row["mailId"]);

                        // Free result
                        mysqli_free_result($result);
                    }   //  End if (!$result)
                }   //  End if (!$db_selected)

                // Close connection
                mysqli_close($con);
            }   //  End if (!$con)

            // We have a valid email, with attachments
            if (($mailId > 0) && ($ready === false)) {

                // Process each attachment
                foreach ($attachments as &$attachment) {

                    // Verify that name and data fields are furnished
                    if ((array_key_exists("filename", $attachment)) &&
                        (array_key_exists("data", $attachment))) {

                        $filename = $attachment["filename"];

                        $rawData = base64_decode($attachment["data"]);

                        $hexData = bin2hex($rawData);
                        $length = strlen($hexData) / 2;

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

                                $useFilename = mysqli_real_escape_string($con, $filename);

                                // Trim super-long filenames
                                if (strlen($useFilename) > 1024) {
                                    $useFilename = substr($useFilename, 0, 1024);
                                }   //  End if (strlen($useSubject) > 255)

                                // This is the query we will run to add this attachment
                                $query = "call addMailAttachment($mailId,'$useFilename',$length,X'$hexData');";

                                // Result of query
                                $result = mysqli_query($con, $query);

                                // Unable to fetch result, display error message
                                if (!$result) {
                                    $errorCode     = 3;
                                    $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                                    $errorMessage .= ("Whole query: " . $query);
                                } else if ($row = mysqli_fetch_assoc($result)) {
                                    $mailAttachmentId = intval($row["mailAttachmentId"]);

                                    // Free result
                                    mysqli_free_result($result);
                                }   //  End if (!result)
                            }   //  End if (!$db_selected)
                        }   //  End if (!$con)
                    } else {
                        $errorCode = 5;
                        $errorMessage = "One or more of the mandatory fields: filename and data not found. " .
                                        "Incomplete attachments in valid email, that was dispatched";
                    }   //  End if ((array_key_exists("name", $attachment)) &&
                }   //  End foreach ($attachments as &$attachment)

                // Update ready flag for this email
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

                        // This is the query we will run to mark email as ready
                        $query = "call markEmailAsReady($mailId)";

                        // Result of query
                        $result = mysqli_query($con, $query);

                        // Unable to fetch result, display error message
                        if (!$result) {
                            $errorCode     = 3;
                            $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                            $errorMessage .= ("Whole query: " . $query);
                        } else if ($row = mysqli_fetch_assoc($result)) {
                            $mailId = intval($row["mailId"]);

                            // Free result
                            mysqli_free_result($result);
                        }   //  End if (!$result)
                    }   //  End if (!$db_selected)

                    // Close connection
                    mysqli_close($con);
                }   //  End if (!$con)
            }   //  End if (($mailId > 0) && ($ready === false))
        } else {
            $errorCode = 4;
            $errorMessage = "One or more of the mandatory fields: to, subject, body and apiKey were not found.";
        }   //  End if ($fieldMask === 15)

        $responseJson              = array();
        $responseJson["errorCode"] = $errorCode;

        // Attach mySql query to email
        if (($dump === true) && ($errorCode === 0)) {
            $responseJson["query"] = $mainQuery;
        }   //  End if (($dump === true) && ($errorCode === 0))

        // Add mailId in any case
        $responseJson["mailId"] = $mailId;

        // Some error occured
        if ($errorMessage !== null) {
            $responseJson["error"] = $errorMessage;
        }   //  End if ($errorMessage !== null)

        // Send result back
        header('Content-Type: application/json; charset=utf-8');
        print(utf8_encode(json_encode($responseJson)));

    }   //  End if ($postBody !== "")
}   //  End if (($_SERVER["REQUEST_METHOD"] === "POST") &&   

ob_end_flush();
?>
