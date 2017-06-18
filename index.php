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

// Include class.xmSession.php that contains definition for session wrapper
require_once("class.xmSession.php");

// Start output buffering on
ob_start();

// Start the initial session
session_start();

// Set timezone to be UTC
date_default_timezone_set("UTC");

// First off, check if the application is being used by someone not typing the actual server name in the header
if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier) {
    // Transfer user to same page, served over HTTPS and full-domain name
    header("Location: https://" . $global_siteCookieQualifier . $_SERVER["REQUEST_URI"]);
    exit();
}   //  End if (strtolower($_SERVER["HTTP_HOST"]) != $global_siteCookieQualifier)

$memCache = new Memcache;
$memCache->connect('localhost', 11211);

// Error header message, if any; that we may need to display
$errorHeaderMessage = "";
$errorMessage = "";
$errorMask = 0;

$pageVisited = false;
$noLog = 0;

$loginNameStyle = "boldLabel";
$loginName = "";
$rememberMe = "";
$statusInt = -1;
$failedLogin = false;
$loginAttempts = 0;
$salt = "";
$rememberMe = "";
$userInt = 0;
$active = false;
$loginCount = 0;
$timeZone = 0;
$invalidPassword = false;

$infoMessage = "";

$actualSessionId = session_id();

$xmSession = $memCache->get($actualSessionId);

// Set a new session key if one wasn't there before
if (!$xmSession) {

    $xmSession = new xmSession();
    
    // Set new GUID as identifier for session key
    $xmSession->setSessionKey(createMyGuid());
        
    $appSettings = $memCache->get("xmSettings");

    // This is the first user accessing the application
    if (!$appSettings) {

        // ********* Call Web Service to get settings ********** //
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "services/getSettings.php");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('ApiKey: $$API_KEY$$'));        // $$ API_KEY $$
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

//        if (isset($_COOKIE["PHPSESSID"])) {
//            curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
//        }   //  End if (isset($_COOKIE["PHPSESSID"]))

        session_write_close();

        $settings = curl_exec($ch);

        curl_close($ch);
        // ********* End Web Service to get settings ********** //
        
        $curlyBracePosition = strpos($settings, "{\"response\":", 0);

        if ($curlyBracePosition > 0) {
            $settings = substr($settings, $curlyBracePosition);
        }   //  End if ($curlyBracePosition > 0)

        $settingsJson = json_decode($settings, true);
        $response = $settingsJson["response"];

        $errorCode = intval($response["errorCode"]);
                
        if ($errorCode > 0) {

            $xmSession->setErrorMessage("The server encountered a problem processing your request.");

            // ********* Call Web Service to send error email ********** //
            $mailData = array();
            $mailData["to"] = "$$ADMIN_EMAIL_ADDRESS$$";            // $$ ADMIN_EMAIL_ADDRESS $$
            $mailData["subject"] = "Exception in XM App";
            $mailData["body"] = $response["error"];

            $requestJson = array();
            $requestJson["mail"] = $mailData;

            $postData = json_encode($requestJson);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,"$$MAIL_API_URL$$");       // $$ MAIL_API_URL $$
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'ApiKey: $$MAIL_API_KEY$$'              // $$ MAIL_API_KEY $$
                       ));

            curl_setopt($ch, CURLOPT_SSLVERSION, 6);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode($postData));

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

            session_write_close();

            $response = curl_exec($ch);

            curl_close($ch);
            // ********* End Web Service to send error email ********** //

            // Set session data in memCache with a 20-minute timeout
            $memCache->set($actualSessionId, $xmSession, false, 1200);
          
            // Transfer user to error page
            header("Location: " . $global_siteUrl . "error.php");
            exit();
        } else {            
            $appSettings = array();

            $settings = $response["settings"];
            
            foreach ($settings as &$settingPair) {
                $key = key($settingPair);
                $appSettings[$key] = $settingPair[$key];
            }   //  End foreach ($settings as &$settingPair)
            
            // Set settings data in memCache with application scope
            $memCache->set("xmSettings", $appSettings);
        }   //  End if (!is_array($response));
        // ********* End Web Service to get settings ********** //
    }   //  End if (!$appSettings)
}   //  End if (!$xmSession)

// Check for existence of demo_noLog in the $_SESSION bag
$noLog = $xmSession->getNoLog();

if ($noLog === 1) {
    $xmSession->setNoLog(0);
}   //  End if ($xmSession->getNoLog() === 1)

// Log the access made to this application if timeZone has been set from the index page
if (($noLog === 0) && ($xmSession->getUserLogged() === 0)) {

    $xmSession->setUserLogged(1);

    if ((array_key_exists("logging", $appSettings)) &&
        ($appSettings["logging"] == "1")) {
        
        // ********* Call Web Service to edit details about this access request ********** //
        $ch = curl_init();

        $elements = array();
        $elements["ipAddress"]     = $_SERVER["REMOTE_ADDR"];
        $elements["userAgent"]     = $_SERVER["HTTP_USER_AGENT"];
        
        $referer = "";

        if (key_exists("HTTP_REFERER", $_SERVER)) {
            $referer = $_SERVER["HTTP_REFERER"];
        }   //  End if (key_exists("HTTP_USER_AGENT", $_SERVER)
        
        $elements["referer"]       = $referer;
        $elements["sessionKey"]    = $xmSession->getSessionKey();

        $inputJson = array();
        $inputJson["request"]      = $elements;
 
        curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "logAccess.php");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('ApiKey: $$API_KEY$$'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(json_encode($inputJson)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        if (isset($_COOKIE["PHPSESSID"])) {
            curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
        }   //  End if (isset($_COOKIE["PHPSESSID"]))

        session_write_close();

        $logOutput = curl_exec($ch);
            
        curl_close($ch); 

        $curlyBracePosition = strpos($logOutput, "{\"response\":", 0);

        if ($curlyBracePosition > 0) {
            $logOutput = substr($logOutput, $curlyBracePosition);
        }   //  End if ($curlyBracePosition > 0)

        $logJson = json_decode($logOutput, true);
        $response = $logJson["response"];
        
        $errorCode = intval($response["errorCode"]);
        
        if ($errorCode > 0) {

            $xmSession->setErrorMessage("The server encountered a problem processing your request.");

            // ********* Call Web Service to send error email ********** //
            $mailData = array();
            $mailData["to"] = $appSettings["errorEmail"];
            $mailData["subject"] = "Exception in XM App";
            $mailData["body"] = $response["error"];

            $requestJson = array();
            $requestJson["mail"] = $mailData;

            $postData = json_encode($requestJson);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,"$$MAIL_API_URL$$");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'ApiKey: $$MAIL_API_KEY$$'
                       ));

            curl_setopt($ch, CURLOPT_SSLVERSION, 6);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode($postData));

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

            session_write_close();

            $response = curl_exec($ch);

            curl_close($ch);
            // ********* End Web Service to send error email ********** //

            // Set session data in memCache with a 20-minute timeout
            $memCache->set($actualSessionId, $xmSession, false, 1200);
          
            // Transfer user to error page
            header("Location: " . $global_siteUrl . "error.php");
            exit();
        } else {
            $xmSession->setLogId(intval($response["logId"]));
        }   //  End if (!is_array($message))
        // ********* End Web Service to edit details about this access request ********** //
    }   //  End if ((array_key_exists("logging", $appSettings)) &&
}   //  End if (($noLog === 0) && ($xmSession->getUserLogged() === 0))

$sessionKey = $xmSession->getSessionKey();

// Check if user has a valid block-out session active, by examining demo_loginCount value
$loginCount = $xmSession->getLoginCount();

// Five times, all bets are off
if ($loginCount === 5) {

    $lockTime = $xmSession->getLockTime();

    // That should have a non-null value
    if ($lockTime != null) {

        $currentTime = time();

        // Subtracting lockTime from current time should be more than 10 minutes
        if ($currentTime - $lockTime > 600) {
            $xmSession->setLockTime(null);
            $xmSession->setLoginCount(0);
        } else {
            $failedLogin = true;
            $errorMask = 2;
            $errorMessage = "You have tried too many times. Please close this browser window and try again after a few minutes!";
        }   //  End if ($currentTime - $lockTime > 600)
    }   //  End if ($lockTime != null)
}   //  End if ($loginCount == 5)

// Set session data in memCache with a 20-minute timeout
$memCache->set($actualSessionId, $xmSession, false, 1200);

// Next, if this form has been posted back, verify if the HTTP_REFERER matches current page URL
if (($_SERVER["REQUEST_METHOD"] === "POST") && (postedFromSame($_SERVER["HTTP_REFERER"]) === true)) {

    $currentTime = time();

    // Login value is whatever was entered: foobar
    // Trim it to remove spaces, if JavaScript might not have done it prior
    $loginName = trim($_POST["txtUsername"]);

    $loginName = "";
    
    // Get the password entered by the user - no need to escape this as it gets sha256 hashed
    $userPassword = $_POST["txtPassword"];

    // Verify that $loginName is furnished
    if ($loginName === "") {
        $errorMessage = "<ul><li>Please enter your registered user ID.</li></ul>";
    } else {

        $loginNameChars = str_split($loginName);

        foreach ($loginNameChars as &$c) {

            if (!((($c >= 'a') && ($c <= 'z')) || (($c >= 'A') && ($c <= 'Z')) || (($c >= '0') && ($c <= '9')))) {

                // Periods and underscores allowed - we dont give this away
                if (($c != '_') && ($c != '.')) {
                    $errorMessage = "<ul><li>Please enter a valid user ID.</li></ul>";
                    break;
                }   //  End if ((c != '_') && (c != '.'))
            }   //  End if (!((($c >= 'a') && ($c <= 'z')) || (($c >= 'A') && ($c <= 'Z')) || (($c >= '0') && ($c <= '9'))))
        }   //  End foreach ($loginNameChars as &$c)
    }   //  End if ($loginName === "")

    if ($errorMessage != "") {
        $errorMask = 1;
        $loginNameStyle = "boldRedLabel";
    } else {

        // ********* Call Web Service to get login details for this user ********** //
        $ch = curl_init();

        $elements = array();
        $elements["identity"]      = $loginName;
        $elements["sessionKey"]    = $xmSession->getSessionKey();
        $elements["logAttempt"]    = 1;
        
        $inputJson = array();
        $inputJson["request"] = $elements;
 
        curl_setopt($ch, CURLOPT_URL, $global_siteUrl . "getUserData.php");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('ApiKey: $$API_KEY$$'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode(json_encode($inputJson)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        if (isset($_COOKIE["PHPSESSID"])) {
            curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $_COOKIE["PHPSESSID"]);
        }   //  End if (isset($_COOKIE["PHPSESSID"]))

        session_write_close();

        $userDataJson = curl_exec($ch);
        
        curl_close($ch);

        $curlyBracePosition = strpos($userDataJson, "{\"response\":", 0);

        if ($curlyBracePosition > 0) {
            $userDataJson = substr($userDataJson, $curlyBracePosition);
        }   //  End if ($curlyBracePosition > 0)
        
        $userData = json_decode($userDataJson, true);
        $response = $userData["response"];
        
        // If we didn't receive an array back, something failed
        if (!is_array($response)) {

            die($response);
        
            $xmSession->setErrorMessage($response);

            // Set session data in memCache with a 20-minute timeout
            $memCache->set($actualSessionId, $xmSession, false, 1200);

            // Transfer user to error page
            header("Location: " . $global_siteUrl . "error.php");
            exit();
        } else {           
            $xmSession->setLogId(intval($response[0]["logId"]));
        }   //  End if (!is_array($message))
        // ********* End Web Service to edit details about this access request ********** //



    }   //  End if ($errorMessage != "")
}   //  End if (($_SERVER["REQUEST_METHOD"] === "POST") && (postedFromSame($_SERVER["HTTP_REFERER"]) === true))

// Check if we found a userActive and userStatus session variable pair defined, should be if user is specifically asking
// for this page, post successful login
if ($xmSession->getUserId() > 0) {

    // User is active and has a status as user or admin
    if (($xmSession->getActive() === true) && ($xmSession->getStatus() > 0)) {
        // Transfer user to welcome page, as we found valid sessions - the user has already logged in
        header("Location: " . $global_siteUrl . "welcome.php");
    } else {
        // Transfer user to noaccess page - this user does not have access
        header("Location: " . $global_siteUrl . "noaccess.php");
    }   //  End if (($xmSession->getActive() === true) && ($xmSession->getStatus() > 0))

    exit();
}   //  End if ($xmSession->getUserId() > 0) {

if ($errorMessage != "") {
    $errorHeaderMessage = "Please correct these errors below";
} else if ($xmSession->getDisplayMessage() != null) {
    $infoMessage = $xmSession->getDisplayMessage();
}   //  End if ($errorMessage != "")
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>XM: Login</title>
    <link rel="stylesheet" type="text/css" href="_static/main.css" />

    <link href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>

    <script type="text/javascript" language="JavaScript" src="_static/scripts.js"></script>
  </head>
  <body class="bodyContent"<?php print(($xmSession->getUserLogged() === 1) ? (" onload=\"loadLoginPage('" . $xmSession->getSessionKey() . "');\"") : ""); ?>>
    <?php include_once("header.php"); ?>
    <form name="loginForm" method="POST" action="<?php print($global_siteUrl); ?>index.php">
      <div id="errorSection" <?php if ($errorMessage == "") { print(" style=\"display: none;\""); }?>>
        <div class="errorPanel" style="width: 200px">
          <span class="boldLabel" id="errorHeaderSpan"><?php print($errorHeaderMessage); ?>:</span><br/>
          <span class="inputLabel" id="errorText"><?php if ($errorMessage != "") { print($errorMessage); }?></span>
        </div>
        <div class="fillerPanel20px">&nbsp;</div>
      </div>
      <div id="infoSection" style="display: <?php print(($infoMessage == "") ? "none" : "block"); ?>">
        <div class="infoPanel" style="width: 200px">
          <span class="inputLabel" id="infoText"><?php print($infoMessage); ?></span>
        </div>
        <div class="fillerPanel20px">&nbsp;</div>
      </div>

      <div class="loginPanel" <?php if ($failedLogin === true) { print(" style=\"display: none;\""); }?>>
        <p>
          <span class="columnHeader">Sign in</span>
        </p>
        <table class="loginTable">
          <tbody>
            <tr>
              <td class="commonInput" style="background-color: #EEEEEE;">
                <span class="<?php print($loginNameStyle); ?>" id="loginNameSpan">&nbsp;User ID</span>
              </td>
            </tr>
            <tr>
              <td class="commonInput">
                <span class="inputLabel">&nbsp;</span><input type="text" class="inputText" name="txtUsername" id="txtUsername" placeholder="Your user ID" maxlength="32" style="width:150px" value="<?php print(htmlspecialchars($loginName)); ?>" required/>
              </td>
            </tr>
            <tr>
              <td class="commonInput" style="background-color: #EEEEEE;">
                <span class="boldLabel">&nbsp;Password</span>
              </td>
            </tr>
            <tr>
              <td class="commonInput">
                <span class="inputLabel">&nbsp;</span><input type="password" class="inputText" name="txtPassword" id="txtPassword" placeholder="Password" maxlength="48" style="width:150px" required/>
                <input type="hidden" id="errorMask" name="errorMask" value="<?php print($errorMask); ?>"/>
              </td>
            </tr>
              <td style="text-align: center; background-color: #EEEEEE;">
                <input type="submit" class="submitButton" id="btnSubmit" name="btnSubmit" value="Login" onclick="return validateLoginForm();"/><br/>
              </td>
            </tr>
            <tr>
              <td class="commonInput">
                <span class="inputLabel"><br/>&nbsp;New users, <a href="<?php print($global_siteUrl); ?>requestInvite.php" class="forever">request</a> an invitation.<br/>
                &nbsp;Forgot <a href="<?php print($global_siteUrl); ?>forgotUsername.php" class="forever">user ID</a>/<a href="<?php print($global_siteUrl); ?>forgotPassword.php" class="forever">password</a>.</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="fillerPanel40px">&nbsp;</div>
    </form>
<?php
    if ($failedLogin === true) {
      print("<div class=\"fillerPanel40px\">&nbsp;</div>");
    }   //  End if ($failedLogin === true)

    include_once("footer.php");
?>
  </body>
</html>
<?php
ob_end_flush();
?>
