<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// index.php - home page for xm website
//    1. Load the settings from DB
//    2. Log the access being made for any user, one time (if settings say for you to do that)
//
//
// Navigate to:
//     1. noaccess.php if cookie found was for user that has her access revoked or terminated in the system
//     3. welcome.php if user has already logged in
//
// QueryString Parameters:
//     1. nl: if set to 1, we avoid writing access event to userAccess table
//
// Cookies:
//     xm_1:   Key for user ID for this client if a prior cookie was saved, from users table
//     xm_2:   SHA-256 hash of the password's hash for this user
//
// Session Variables:
//     xm_session - big object with members to store our session data
//
// Web Services Employed:
//
//
// Stored Procedures:
//     None
//
// JavaScript methods:
// loadLandingPage: set the currentTimeZone form member and post the form
//
// CSS variables:
//    bodyContent, loadingLabel, fillerPanel200px
//
// Revisions:
//     1. Sundar Krishnamurthy          sundar_k@hotmail.com       06/18/2017      Initial file created.
//


ini_set('session.cookie_httponly', TRUE);           // Mitigate XSS
ini_set('session.session.use_only_cookies', TRUE);  // No session fixation
ini_set('session.cookie_lifetime', FALSE);          // Avoid XSS, CSRF, Clickjacking
ini_set('session.cookie_secure', TRUE);             // Never let this be propagated via HTTP/80

// Include functions.php that contains all our functions and constants
require_once("functions.php");

// Include ipfunctions.php that contains code to convert IPv4 and v6 to decimal(39,0) and vice-versa.
require_once("ipfunctions.php");

// Include class.xmSession.php that contains code to store individual session data
require_once("class.xmSession.php");

// Start output buffering on
ob_start();

// Start the initial session
session_start();

// Set timezone to be UTC
date_default_timezone_set("UTC");

$errorMask          = 0;
$errorHeaderMessage = 0;
$errorMessage       = null;
$infoMessage        = null;

$formPosted         = false;
$xmSession          = null;
$logId              = 0;

// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) !== $global_siteCookieQualifier)

// First call being made for app
if (!isset($_SESSION["xm_session"])) {
    $xmSession = new xmSession;

    // Set a sessionKey for this request
    $xmSession->setSessionKey(createMyGuid());

    // Store this object back in session
    $_SESSION["xm_session"] = $xmSession;

    // Log incoming entry in to accessLogs table
    // STEP 1 - Call logUserAccess.php Web Service
    // ********* Call Web Service to set access log metadata **********
    $ch = curl_init();

    $elements                  = array();
    $elements["ipAddress"]     = $_SERVER["REMOTE_ADDR"];
    $elements["referer"]       = $_SERVER["HTTP_REFERER"];
    $elements["browserString"] = $_SERVER["HTTP_USER_AGENT"];
    $elements["sessionId"]     = $xmSession->getSessionKey();

    $ch                        = curl_init();

    curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/logUserAccess.php");

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

    $logResponse = json_decode(utf8_decode($response), true);

    $errorCode = intval($logResponse["errorCode"]);

    if ($errorCode > 0) {
        $errorMessage = $logResponse["error"];

        mail("$$YOUR_EMAIL_ADDRESS$$", "index.php error " . $errorCode, $errorMessage);    // $$ YOUR_EMAIL_ADDRESS $$
    } else {
        $logId = intval($logResponse["logId"]);

        // Send email if the logId received is a multiple of 100
        if (($logId % 100) === 0) {
            mail("$$YOUR_EMAIL_ADDRESS$$", "logId: " . $logId, "Usage statistics");       // $$ YOUR_EMAIL_ADDRESS $$
        }   //  End if (($logId % 10) === 0)
    }   //  End if ($errorCode > 0)
    // *************** END Step 1 ************************

}   //  End if (!isset($_SESSION["xm_session"]))

// Fetch xm_session object for xmSession
$xmSession = $_SESSION["xm_session"];

// Next, if this form has been posted back
if (($_SERVER["REQUEST_METHOD"] === "POST") &&
    (postedFromSame($_SERVER["HTTP_REFERER"]) === true)) {

    $formPosted = true;

}   //  End if (($_SERVER["REQUEST_METHOD"] === "POST") &&
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>XM: Manage your money</title>
    <link rel="stylesheet" type="text/css" href="_static/main.css" />

    <link href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>

    <script type="text/javascript" language="JavaScript" src="_static/scripts.js"></script>
  </head>
  <body class="bodyContent"
<?php
    if ($formPosted === false) {
        print(" onload=\"loadLandingPage();\"");
    }   //  End if ($formPosted === false)
?>>
    <?php include_once("header.php"); ?>
    <form name="timeZoneForm" method="POST" action="<?php print($global_siteUrl); ?>index.php">
      <input type="hidden" id="currentTimeZone" name="currentTimeZone" value="0"/>
      <input type="hidden" id="sessionKey" name="sessionKey" value="<?php
      if ($logId > 0) {
          print($xmSession->getSessionKey());
      }
      ?>"/>
      <span class="loadingLabel">&nbsp;&nbsp;Loading...</span><br/>&nbsp;
    </form>
    <div class="fillerPanel40px">&nbsp;</div>
    <?php include_once("footer.php"); ?>
  </body>
</html>
<?php
ob_end_flush();
?>
