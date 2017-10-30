<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: checkForMail.php - Run on schedule to check if one or more emails are available to be picked up and dispatched
//
// Output JSON:
// {
//  "apiKey": "a7881524298e413ba00396c24379e3fa",
//  "to": "john.doe@abc.com",
//  "from": "Jane Doe",
//  "fromEmail": "jane@someemail.com",
//  "replyTo": "jane@foobar.com",
//  "subject": "Get Organized Reminder",
//  "body": "I tried telling you many times, please get organized<\/b>.",
//  "cc": "jane1@foo.com,jane2@bar.com",
//  "bcc": "jane@somesite.com",
//  "importance": true,
//  "attachments": [{
//      "name": "dot.gif",
//      "data": "Long base 64 string",
//      "size": 950
//   }, {
//      "name": "helloworld.txt",
//      "data": "aGVsbG8sd29ybGQ=",
//      "size": 11
//   }]
// }
//
// Functions:
//    None
//
// Query Parameters:
//    None
//
// Custom Headers:
//    None
//
// Session Variables:
//    None
//
// Stored Procedures:
//    getEmailToSend - get the next email that you can dispatch
//
// JavaScript functions:
//    None
//
// Revisions:
//    1. Sundar Krishnamurthy          sundar_k@hotmail.com       10/21/2017      Initial file created.

// Include functions.php that contains all our functions
require_once("../functions.php");

// Start output buffering on
ob_start();

// Start the initial session
session_start();

// First off, check if the code is run via crontab
if (empty($_SERVER["REMOTE_ADDR"])) {

    $processFlag    = true;

    // Process until you find more emails
    while ($processFlag === true) {

        $errorCode      = 0;
        $errorMessage   = null;

        $mail           = null;
        $mailId         = 0;
        $hasAttachments = false;

        // Check if any email is available
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

                // Find out current time plus six hours
                $dateTimeNow      = new DateTime(gmdate("Y-m-d\TH:i:s\Z"));
                $dateTimeSixHours = $dateTimeNow->add(new DateInterval("PT6H5M"));
                $checkTime        = $dateTimeSixHours->format("Y-m-d H:i:s");

                // This is the query we will run to find eligible mails
                $query            = "call getEmailToSend('$checkTime');";

                // Result of query
                $result           = mysqli_query($con, $query);

                // Unable to fetch result, display error message
                if (!$result) {
                    $errorCode     = 3;
                    $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                    $errorMessage .= ("Whole query: " . $query);
                } else if ($row = mysqli_fetch_assoc($result)) {

                    $mail           = array();
                    $mail["mailId"] = intval($row["mailId"]);

                    if ($row["sender"] !== null) {
                        $mail["from"] = $row["sender"];
                    }   //  End if ($row["sender"] !== null)
            
                    if ($row["senderEmail"] !== null) {
                        $mail["fromEmail"] = $row["senderEmail"];
                    }   //  End if ($row["senderEmail"] !== null)

                    $mail["to"]      = $row["recipients"];
                    $mail["subject"] = $row["subject"];

                    if ($row["subjectPrefix"] !== null) {
                        $mail["prefix"] = $row["subjectPrefix"];
                    }   //  End if ($row["subjectPrefix"] !== null)

                    if ($row["ccRecipients"] !== null) {
                        $mail["cc"] = $row["ccRecipients"];
                    }   //  End if ($row["ccRecipients"] !== null)

                    if ($row["bccRecipients"] !== null) {
                        $mail["bcc"] = $row["bccRecipients"];
                    }   //  End if ($row["bccRecipients"] !== null)

                    if ($row["replyTo"] !== null) {
                        $mail["replyTo"] = $row["replyTo"];
                    }   //  End if ($row["replyTo"] !== null)

                    $mail["body"] = $row["body"];

                    $hasAttachments     = intval($row["hasAttachments"]);
                    $mail["importance"] = intval($row["importance"]);

                    // Replace 1 with true, 0 for false to store value of importance
                    if ($mail["importance"] === 1) {
                        $mail["importance"] = true;
                    } else {
                        $mail["importance"] = false;
                    }   //  End if ($mail["importance"] === 1)

                    // Free result
                    mysqli_free_result($result);
                } else {
                    $processFlag = false;
                }   //  End if (!$result)
            }   //  End if (!$db_selected)

            // Close connection
            mysqli_close($con);
        }   //  End if (!$con)

        // Now, process attachments that may be part of the mail we need to dispatch
        if ($hasAttachments === true) {

            $attachments = array();

            // Connect to DB to fetch all attachments
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
                    // This is the query we will run to find all attachments
                    $query = "call getAttachmentsForEmail(" . $mail["mailId"] . ");";

                    // Result of query
                    $result = mysqli_query($con, $query);

                    // Unable to fetch result, display error message
                    if (!$result) {
                        $errorCode     = 3;
                        $errorMessage  = "Invalid query: " . mysqli_error($con) . "<br/>";
                        $errorMessage .= ("Whole query: " . $query);
                    } else {
                        while ($row = mysqli_fetch_assoc($result)) {

                            $attchment              = array();
                            $attachment["filename"] = $row["filename"];
                            $attachment["size"]     = intval($row["filesize"]);
                            $attachment["data"]     = base64_encode($row["attachment"]);

                            $attachments[]          = $attachment;
                        }   //  End while ($row = mysqli_fetch_assoc($result))

                        // Free result
                        mysqli_free_result($result);
                    }   //  End if (!$result)
                }   //  End if (!$db_selected)

                // Close connection
                mysqli_close($con);
            }   //  End if (!$con)

            $mail["attachments"] = $attachments;
        }   //  End if ($hasAttachments === true) 

        if ($errorCode === 0) {
            // Call mailservice.php with this payload
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/mailservice.php");

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "ApiKey: $$VALID_MAIL_API_KEY$$",           // $$ VALID_MAIL_API_KEY $$
                "Content-Type: application/x-www-form-urlencoded",
                "Accept: application/json"));

            curl_setopt($ch, CURLOPT_SSLVERSION, 6);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(json_encode($mail)));

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

            session_write_close();

            $response = curl_exec($ch);

            curl_close($ch);

            if ($mail["mailId"] > 0) {

                // Delete this email
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
                        // This is the query we will run to delete this email
                        $query  = "call deleteEmail(" . $mail["mailId"] . ",'$$MAILER_MAIL$$');";     // $$ MAILER_MAIL $$
 
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
            }   //  End if ($mail["mailId"] > 0)
        }   //  End if ($errorCode === 0)

        // Some error occured
        if ($errorCode > 0) {

            mail("$$YOUR_EMAIL_ADDRESS$$", "[XM] Error Occured: " . $errorCode, $errorMessage);     // $$ YOUR_EMAIL_ADDRESS $$
            $processFlag = false;

        }   //  End if ($errorCode > 0)
    }   //  while ($processFlag === true)
}   //  End if (empty($_SERVER["REMOTE_ADDR"]))

ob_end_flush();
?>
