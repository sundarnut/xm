<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// File: mailservice.php - send mail or save mail to DB for later dispatch (when future timestamp is specified)
//
// Input JSON:
// {"to":"john.doe@abc.com",
//  "subject":"Get Organized reminder",
//  "body":"I tried telling you many times, please <b>get organized</b>."}
//
// Input JSON:
// {"to": "john.doe@abc.com",
//  "from": "Jane Doe",
//  "fromEmail": "jane@someemail.com",
//  "replyTo":"john@email.com"
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
//  from:       Sender name
//  fromEmail:  Sender email
//  replyTo:    Reply-to email
//  to:         Email address of recipient
//  subject:    Subject of email
//  body:       Body of email (can include HTML tags)
//  timestamp:  Optional timestamp when this mail ought to be sent
//  cc:         Comma separated email addresses for cc targets
//  bcc:        Comma separated email addresses for bcc targets
//  importance: 1 (if needed)
//  attachment: One or more files to be dispatched, each would have:
//  filename:   Actual name of file
//  data:       Long base 64 string
//
//
// JSON Response:
//    {"errorCode":0,
//     "mailId":520}
//
//    {"errorCode":1,
//     "error":"Long exception stack trace"}
//
// Web Services:
//     1. checkMailApiKey: check if this user has a valid API key (only for instant dispatches)
//     2. saveEmail: save this email to DB, so the dispatcher cron job can pick it up later
//
// Functions:
//     None
//
// Query Parameters:
//     None
//
// Custom Headers:
//     ApiKey: Must contain magic value for this service to be employed
//
// Session Variables:
//     None
//
// Stored Procedures:
//     None
//
// JavaScript functions:
//     None
//
// Revisions:
//     1. Sundar Krishnamurthy          sundar_k@hotmail.com       10/06/2017      Initial file created.

ini_set('session.cookie_httponly', TRUE);           // Mitigate XSS
ini_set('session.session.use_only_cookies', TRUE);  // No session fixation
ini_set('session.cookie_lifetime', FALSE);          // Avoid XSS, CSRF, Clickjacking
ini_set('session.cookie_secure', TRUE);             // Never let this be propagated via HTTP/80

require_once("../phpmailer/class.phpmailer.php");
require_once("../phpmailer/class.smtp.php");

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

// Authorized client that is seeking to send mail, and has furnished an API key
if (($_SERVER["REQUEST_METHOD"] === "POST") &&
    (isset($_SERVER["HTTP_APIKEY"]))) {

    $postBody = utf8_decode(urldecode(file_get_contents("php://input")));

    $apiKey   = $_SERVER["HTTP_APIKEY"];

    // We found a valid body to process, and API Key is 32 characters long
    if (($postBody !== "") && (strlen($apiKey) === 32)) {

        $timestamp     = null;
        $clientEmail   = null;

        $mailRequest   = json_decode($postBody, true);

        if (array_key_exists("timestamp", $mailRequest)) {

            $inputTimestamp = $mailRequest["timestamp"];

            if (strtotime($inputTimestamp) !== false) {

                $timestamp        = new DateTime($inputTimestamp);
                $dateTimeNow      = new DateTime(gmdate("Y-m-d\TH:i:s\Z"));
                $dateTimeSixHours = $dateTimeNow->add(new DateInterval("PT6H5M"));

                // Send mail out right away if it is due in the next six hours five minutes
                if ($timestamp <= $dateTimeSixHours) {
                    $timestamp = null;
                }   //  End if ($timestamp <= $dateTimeSixHours)
            }   //  End if (strtotime($timestamp) !== false)
        }   //  End if (array_key_exists("timestamp", $request)

        // If $timestamp === null, you have to send the mail ASAP
        if ($timestamp === null) {

            $active                 = 0;

            // First off verify if the API key is indeed valid and correct for dispatching mail ASAP
            $ch                     = curl_init();
            $elements               = array();
            $elements["mailApiKey"] = $apiKey;

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/checkMailApiKey.php");

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                             "ApiKey: $$API_KEY$$",           // $$ API_KEY $$
                             "Content-Type: application/x-www-form-urlencoded",
                             "Accept: application/json"));

            curl_setopt($ch, CURLOPT_SSLVERSION, 6);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(utf8_encode(json_encode($elements))));

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

            session_write_close();

            $response = curl_exec($ch);

            curl_close($ch);

            $checkResponse = json_decode(utf8_decode($response), true);
            $errorCode     = intval($checkResponse["errorCode"]);

            if ($errorCode === 0) {
                $active      = intval($checkResponse["active"]);
                $clientEmail = $checkResponse["email"];
            } else {
                $errorMessage = $checkResponse["errorMessage"];
            }   //  End if ($errorCode === 0)

            // Send email immediately, if you find a valid active flag for furnished API Key
            if ($active === 1) {

                $errorCode      = 0;

                // Define default values for all the fields that we will furnish to the DB
                $fromName       = "XM Site Mailer (do not reply)";
                $fromEmail      = "$$MAILER_EMAIL$$";    // $$ MAILER_EMAIL $$
                $replyTo        = "$$MAILER_EMAIL$$";    // $$ MAILER_EMAIL $$
                $ccAddress      = null;
                $bccAddress     = null;
                $attachments    = null;
                $toAddress      = null;
                $toAddressValue = null;
                $subject        = null;
                $body           = null;
                $queryMask      = 0;

                // Start processing the JSON string for data
                // To: field for the email is mandatory
                if (array_key_exists("to", $mailRequest)) {
                    $toAddressValue = $mailRequest["to"];
                    $queryMask      = 1;

                    $toAddress      = explode(",", $toAddressValue);
                }   //  End if (array_key_exists("to", $mailRequest))

                // Subject: field for the email is mandatory
                if (array_key_exists("subject", $mailRequest)) {
                    $subject    = $mailRequest["subject"];
                    $queryMask |= 2;
                }   //  if (array_key_exists("subject", $mailRequest))

                // Body: field for the email is mandatory
                if (array_key_exists("body", $mailRequest)) {
                    $body       = $mailRequest["body"];
                    $queryMask |= 4;
                }   //  End if (array_key_exists("body", $mailRequest))

                // Proceed only if you found all three mandatory parameters furnished
                if ($queryMask === 7) {

                    // From field if we need to send the mail as someone else
                    if (array_key_exists("from", $mailRequest)) {
                        $fromName = $mailRequest["from"];
                    }   //  End if (array_key_exists("from", $mailRequest)) {

                    // FromEmail field if we need to send the mail as someone else
                    if (array_key_exists("fromEmail", $mailRequest)) {
                        $fromEmail = $mailRequest["fromEmail"];
                    }   //  End if (array_key_exists("fromEmail", $mailRequest)) {

                    // Reply-To field if we need to reply back email address
                    if (array_key_exists("replyTo", $mailRequest)) {
                        $replyTo = $mailRequest["replyTo"];
                    }   //  End if (array_key_exists("replyTo", $mailRequest)) {

                    // Cc: field - we would need a new query to process additional parameters
                    if (array_key_exists("cc", $mailRequest)) {
                        $ccAddressValue = $mailRequest["cc"];
                        $ccAddress      = explode(",", $ccAddressValue);
                    }   //  End if (array_key_exists("cc", $mailRequest))

                    // Bcc: field - we would need a new query to process additional parameters
                    if (array_key_exists("bcc", $mailRequest)) {
                        $bccAddressValue = $mailRequest["bcc"];
                        $bccAddress      = explode(",", $bccAddressValue);
                    }   //  End if (array_key_exists("bcc", $mailRequest))

                    // Prefix field - add this in square braces, before every subject
                    if ((array_key_exists("prefix", $mailRequest)) && ($mailRequest["prefix"] != "")) {
                        $subject = "[" . $mailRequest["prefix"] . "] " . $subject;
                    }   //  End if ((array_key_exists("prefix", $mailRequest)) && ($mailRequest["prefix"] != ""))

                    if (array_key_exists("replyTo", $mailRequest)) {
                        $replyTo = $mailRequest["replyTo"];
                    }   //  End if (array_key_exists("replyTo", $mailRequest))

                    if (array_key_exists("attachments", $mailRequest)) {
                        $attachments = $mailRequest["attachments"];
                    }   //  End if (array_key_exists("attachments", $mailRequest))

                    $mail             = new PHPMailer();
                    $mail->SMTPDebug  = false;
                    $mail->isSMTP();
                    $mail->Host       = "$$EMAIL_SERVER$$";     // $$ EMAIL_SERVER $$

                    $mail->SMTPAuth   = true;
                    $mail->Username   = "$$MAILER_EMAIL$$";      // $$ MAILER_EMAIL $$
                    $mail->Password   = "$$MAILER_PASSWORD$$";       // $$ MAILER_PASSWORD $$

                    $mail->CharSet    = "UTF-8";
                    $mail->SMTPSecure = "tls";
                    $mail->Port       = 25;

                    // Set the values for mailer object, from to etc.
                    $mail->From       = $fromEmail;
                    $mail->FromName   = $fromName;
                    $mail->AddReplyTo($replyTo);
                    $mail->Subject    = $subject;
                    $mail->Body       = $body;
                    $mail->IsHTML(true);

                    foreach ($toAddress as &$toEmail) {
                        $mail->AddAddress(trim($toEmail));
                    }   //  End foreach ($toAddress as &$toEmail)

                    if ($ccAddress != null) {
                        foreach ($ccAddress as &$ccEmail) {
                            $mail->AddCC(trim($ccEmail));
                        }   //  End foreach ($ccAddress as &$ccEmail)
                    }   //  End foreach ($ccAddress as &$ccEmail)

                    if ($bccAddress != null) {
                        foreach ($bccAddress as &$bccEmail) {
                            $mail->AddBCC(trim($bccEmail));
                        }   //  End foreach ($bccAddress as &$bccEmail)
                    }   //  End foreach ($bccAddress as &$bccEmail)

                    if (array_key_exists("importance", $mailRequest)) {
                        $importance = boolval($mailRequest["importance"]);

                        if (($importance === true) || ($importance === 1)) {
                            $mail->Priority = 1;
                        }   //  End if (($importance === true)) || ($importance === 1))
                    }   //  End if (array_key_exists("importance", $mailRequest))

                    // We have attachments, add each one individually and dispatch this email
                    if (($attachments != null) && (is_array($attachments))) {

                        // Iterate and add each attachment
                        foreach($attachments as &$attachment) {

                            // Verify that FileName and Data fields are furnished
                            if ((array_key_exists("filename", $attachment)) &&
                                (array_key_exists("data", $attachment))) {

                                $filename = $attachment["filename"];
                                $mimeType = "application/octet-stream";

                                // We found a period in the filename
                                if ((strrchr($filename, ".")) != false) {
                                    $extension = strtolower(substr(strrchr($filename, '.'), 1));

                                    switch ($extension) {
                                        case "3gp": $mimeType = "video/3gpp"; break;
                                        case "7z": $mimeType  = "application/x-7z-compressed"; break;
                                        case "apk": $mimeType = "application/vnd.android.package-archive"; break;
                                        case "asm": case "s": $mimeType = "text/x-asm"; break;
                                        case "avi": $mimeType = "video/x-ms-video"; break;
                                        case "azw": $mimeType = "application/vnd.amazon.ebook"; break;
                                        case "bmp": $mimeType = "image/bmp"; break;
                                        case "c": $mimeType = "text/x-c"; break;
                                        case "cpp": $mimeType = "text/plain"; break;
                                        case "cab": $mimeType = "application/vnd.ms-cab-compressed"; break;
                                        case "class": $mimeType = "application/java-vm"; break;
                                        case "crl": $mimeType = "application/pkix-crl"; break;
                                        case "css": $mimeType = "text/css"; break;
                                        case "csv": $mimeType = "text/csv"; break;
                                        case "curl": $mimeType = "text/vnd.curl"; break;
                                        case "dmg": $mimeType = "application/x-apple-diskimage"; break;
                                        case "doc": $mimeType = "application/msword"; break;
                                        case "docx": $mimeType = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"; break;
                                        case "eml": $mimeType = "message/rfc822"; break;
                                        case "exe": $mimeType = "application/x-msdownload"; break;
                                        case "flv": $mimeType = "video/x-flv"; break;
                                        case "gif": $mimeType = "image/gif"; break;
                                        case "gtar": $mimeType = "application/x-gtar"; break;
                                        case "h": $mimeType = "text/plain"; break;
                                        case "htm": case "html": $mimeType = "text/html"; break;
                                        case "ico": $mimeType = "image/x-icon"; break;
                                        case "ics": $mimeType = "text/calendar"; break;
                                        case "jar": $mimeType = "application/java-archive"; break;
                                        case "java": $mimeType = "text/x-java-source,java"; break;
                                        case "jpe": case "jpg": case "jpeg": $mimeType = "image/jpeg"; break;
                                        case "js": $mimeType = "application/javascript"; break;
                                        case "json": $mimeType = "application/json"; break;
                                        case "m3u": $mimeType = "audio/x-mpegurl"; break;
                                        case "m4v": $mimeType = "audio/x-mpegurl"; break;
                                        case "movie": $mimeType = "video/x-sgi-movie"; break;
                                        case "mp3": $mimeType = "audio/mpeg"; break;
                                        case "mpga": $mimeType = "audio/mpeg"; break;
                                        case "mpp": $mimeType = "application/vnd.ms-project"; break;
                                        case "pdf": $mimeType = "application/pdf"; break;
                                        case "pki": $mimeType = "application/pkixcmp"; break;
                                        case "png": $mimeType = "image/png"; break;
                                        case "ppt": case "pptx": $mimeType = "application/vnd.ms-powerpoint"; break;
                                        case "ps1": $mimeType = "text/plain"; break;
                                        case "rtf": $mimeType = "application/rtf"; break;
                                        case "sh": $mimeType = "application/x-sh"; break;
                                        case "swf": $mimeType = "application/x-shockwave-flash"; break;
                                        case "tar": $mimeType = "application/x-tar"; break;
                                        case "tif": case "tiff": $mimeType = "image/tiff"; break;
                                        case "txt": $mimeType = "text/plain"; break;
                                        case "vcd": case "vcf": $mimeType = "text/x-vcard"; break;
                                        case "vsd": $mimeType = "application/vnd.visio"; break;
                                        case "vsdx": $mimeType = "application/vnd.visio2013"; break;
                                        case "wav": $mimeType = "audio/x-wav"; break;
                                        case "wm": $mimeType = "video/x-ms-wm"; break;
                                        case "wma": $mimeType = "audio/x-ms-wma"; break;
                                        case "wmv": $mimeType = "video/x-ms-wmv"; break;
                                        case "wri": $mimeType = "application/x-mswrite"; break;
                                        case "wsdl": $mimeType = "application/wsdl+xml"; break;
                                        case "xap": $mimeType = "application/x-silverlight-app"; break;
                                        case "xbm": $mimeType = "image/x-xbitmap"; break;
                                        case "xhtml": $mimeType = "application/xhtml+xml"; break;
                                        case "xls": case "xlsx": $mimeType = "application/vnd.ms-excel"; break;
                                        case "xml": $mimeType = "application/xml"; break;
                                        case "xop": $mimeType = "application/xop+xml"; break;
                                        case "xslt": $mimeType = "application/xslt+xml"; break;
                                        case "yaml": $mimeType = "text/yaml"; break;
                                        case "zip": $mimeType = "application/zip"; break;
                                    }   //  End switch ($extension)

                                    $mail->AddStringAttachment(base64_decode($attachment["data"]), $filename, "base64", $mimeType);
                                }   //  End if ((strrchr($filename, ".")) != false)
                            }   //  End if ((array_key_exists("filename", $attachment)) &&
                        }   //  End foreach($attachments as &$attachment)
                    }   //  End if (($attachments != null) && (is_array($attachments))) {

                    $mail->Send();
                } else {
                    $errorCode    = 4;
                    $errorMessage = "One or more of the mandatory fields: to, subject, and body were not found.";
                }   //  End if ($queryMask === 7)
            }   //  End if ($active === 1)

            $responseJson              = array();
            $responseJson["errorCode"] = $errorCode;

            if ($errorMessage === null) {
                $responseJson["message"] = "Sent mail to " . $toAddressValue;
            } else {
                $responseJson["error"] = $errorMessage;
            }   //  End if ($errorMessage === null)

            // Send result back
            // header('Content-Type: application/json; charset=utf-8');
            print(utf8_encode(json_encode($responseJson)));
        } else {
            $mailRequest["apiKey"] = $apiKey;

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/addEmail.php");

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                 "ApiKey: $$API_KEY$$",           // $$ API_KEY $$
                 "Content-Type: application/x-www-form-urlencoded",
                 "Accept: application/json"));

            curl_setopt($ch, CURLOPT_SSLVERSION, 6);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(utf8_encode(json_encode($mailRequest))));

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

            session_write_close();

            $response = curl_exec($ch);

            curl_close($ch);

            print($response);
        }   // End if ($timestamp === null) {
    }   //  End if (($postBody !== "") && (strlen($apiKey) === 32))
}   //  End if (($_SERVER["REQUEST_METHOD"] === "POST") &&

ob_end_flush();
?>
