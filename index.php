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
$errorHeaderMessage = null;
$errorMessage       = null;
$infoMessage        = null;

$formPosted         = false;
$xmSession          = null;
$logId              = null;

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

        mail("$$ADMIN_EMAIL_ADDRESS$$", "index.php error " . $errorCode, $errorMessage);   // $$ ADMIN_EMAIL_ADDRESS $$
    } else {
        $logId = intval($logResponse["logId"]);

        // Send email if the logId received is a multiple of 100
        if (($logId % 100) === 0) {
            mail("$$ADMIN_EMAIL_ADDRESS$$", "logId: " . $logId, "Usage statistics");   // $$ ADMIN_EMAIL_ADDRESS $$
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
    $bitmask = 0;

    $username = null;
    $errorMessage = "<ul>";

    if (isset($_POST["txtUsername"])) {
        $username = trim($_POST["txtUsername"]);

        if ($username !== "") {

            $usernameChars = str_split($username);

            foreach($usernameChars as $c) {

                if (!((($c >= 'a') && ($c <= 'z')) || (($c >= 'A') && ($c <= 'Z')) || (($c >= '0') && ($c <= '9')))) {
                    if (($c != '.') && ($c != '_') && ($c != '-')) {
                        $errorMask = 1;
                        $errorMessage .= "<li>Please enter a valid username.</li>";
                        break;
                    }   //  End if (($c != '.') && ($c != '_') && ($c != '-'))
                }   //  End if (!((($c >= 'a') && ($c <= 'z')) || (($c >= 'A') && ($c <= 'Z')) || (($c >= '0') && ($c <= '9'))))
            }   //  End foreach($usernameChars as $c)

            if ($errorMask === 0) {
                $bitmask = 1;
            }   //  End if ($errorMask === 0)
        } else {
            $errorMask = 1;
            $errorMessage .= "<li>Please enter your username.</li>";
        }   //  End if ($username !== "")
    }   //  End if (isset($_POST["txtUsername"]))

    if (isset($_POST["txtPassword"])) {

        $password = trim($_POST["txtPassword"]);

        if ($password !== "") {
            $bitmask |= 2;
        } else {
            $errorMask |= 2;
            $errorMessage .= "<li>Please enter your password.</li>";
        }   //  End if (isset($_POST["txtPassword"]))
    }   //  End if (isset($_POST["txtUsername"]))

    if ($bitmask === 3) {

        $userId = null;

        // STEP 2 - Call getUserPassword.php Web Service
        // ********* Call Web Service to fetch password **********
        $ch = curl_init();

        $elements               = array();
        $elements["username"]   = $username;
        $elements["sessionKey"] = $xmSession->getSessionKey();

        $ch                     = curl_init();

        curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/getUserPassword.php");

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

        $loginResponse = json_decode(utf8_decode($response), true);
        $errorCode     = intval($loginResponse["errorCode"]);

        if ($errorCode > 0) {
            $errorMessage = $loginResponse["error"];

            mail("sundar@passion8cakes.com", "index.php error " . $errorCode, $errorMessage);
        } else if (array_key_exists("userId", $loginResponse)) {

            $userId   = intval($loginResponse["userId"]);
            $salt     = $loginResponse["salt"];
            $password = $loginResponse["password"];
            $active   = boolval($loginResponse["active"]);
            $status   = intval($loginResponse["status"]);

            die("UserId is ". $userId . "<br/>salt is " . $salt . "<br/>active: " . $active . "<br/>status: " . $status . "<br/>Password: " . $password);

        }   //  End if ($errorCode > 0)

        if ($userId === null) {
            $errorMask    = 1;
            $errorMessage = "<ul><li>Invalid username and/or password.</li>";
        }   //  End if ($userId === null)
        // *************** END Step 2 ************************
    } else {

    }   //  End if ($bitmask === 3) {
}   //  End if (($_SERVER["REQUEST_METHOD"] === "POST") &&

if ($errorMask > 0) {
    $errorMessage       .= "</ul>";
    $errorHeaderMessage  = "Please correct these errors below";
}   //  End if ($errorMask > 0)
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
  <body class="bodyContent" onload="loadLandingPage();">
    <?php include_once("header.php"); ?>
    <div id="errorSection" style="display: <?php
    print (($errorMask === 0) ? "none" : "block");
?>;">
      <div class="errorPanel" style="width: 450px">
        <span class="boldLabel" id="errorHeaderSpan"><?php
    print($errorHeaderMessage);
?>:</span><br/>
        <span class="inputLabel" id="errorText"><?php
    if ($errorMask > 0) {
        print($errorMessage);
    }   //  End if ($errorMask > 0)
?></span>
      </div>
      <div class="fillerPanel20px">&nbsp;</div>
    </div>

    <div id="infoSection" style="display: <?php
    print (($infoMessage === null) ? "none" : "block");
?>;">
      <div class="infoPanel" style="width: 450px">
        <span class="inputLabel" id="infoText"><?php
    print($infoMessage);
?></span>
      </div>
      <div class="fillerPanel20px">&nbsp;</div>
    </div>

    <form name="loginForm" method="POST" action="index.php">
      <div style="margin-left: 20px;">
        <input type="hidden" id="errorMask" name="errorMask" value="<?php
    print($errorMask);
?>"/>
        <input type="hidden" id="sessionKey" name="sessionKey" value="<?php
    if ($logId !== null) {
        print($xmSession->getSessionKey());
    }   //  End if ($logId !== null)
?>"/>
        <input type="text" placeholder="user-name" width="120" maxlength="24" name="txtUsername" id="txtUsername" value="<?php
    print(($username === null) ? "" : $username);
?>" /><br/>
        <input type="password" placeholder="password" width="120" maxlength="48" name="txtPassword" id="txtPassword" /><br/>
        <input type="Submit" value="Log-in" onclick="return validateLoginForm();"/>
      </div>
    </form>
    <div class="fillerPanel40px">&nbsp;</div>
    <?php include_once("footer.php"); ?>
  </body>
</html>
<?php
ob_end_flush();
?>
