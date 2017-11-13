<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// header.php - display at top of every page
//
// Cookies:
//    None
//
// Session Variables:
//    xmSession: Massive object to store all session variables
//
// Stored Procedures:
//    None
//
// JavaScript methods:
//    None
//
// CSS Variables:
//    forever, bannerContent, fillerPanel40px, errorPanel, boldLabel, inputLabel, fillerPanel20px
//
// Revisions:
//     1. Sundar Krishnamurthy          sundar_k@hotmail.com       06/10/2017      Initial file created.

require_once("class.xmSession.php");

$header_username   = "New User";
$header_loggedIn   = false;
$header_userActive = false;

// Check if first name is set
if (isset($_SESSION["xm_session"])) {

    $xmSession   = $_SESSION["xm_session"];
    $header_name = $xmSession->getFirstName();

    if ($header_name != null) {
        $header_username = $header_name;
        $header_loggedIn = true;

        // Check if user has ability to access this application after valid login
        if (($xmSession->getActive() === true) && ($xmSession->getStatus() > 0)) {
            $header_userActive = true;
        }   // End if (($xmSession->getActive() === true) && ($xmSession->getStatus() > 0))
    }   //  End if ($header_name != null)
}   //  End if (isset($_SESSION["xm_session"]))
?>
<a href="<?php
    print($global_siteUrl);
?>" border="0"><img style="position:absolute; top:5px; left:5px; width:150px; height:108px; border:0" alt="XM" src="images/xm.png" /></a>
<table width="100%" border="0" bgcolor="#BBBBBB">
  <tbody>
    <tr>
      <td align="right" valign="top" width="*">
        <span style="font-family: Calibri,Verdana,Arial; font-size: small">
          Welcome <span style="font-weight:bold;"><?php
    print($header_username);
?></span>
<?php
          if ($header_userActive) {
              printf("<a href = \"" . $global_siteUrl . "\" class=\"forever\">Home</a>&nbsp;|&nbsp;");
              printf("<a href = \"settings.php\" class=\"forever\">Settings</a>&nbsp;|&nbsp;");
              printf("<a href = \"logout.php\" class=\"forever\">Logout</a>");
          } // End if ($userActive)
?>
        </span>
      </td>
   </tr>
  </tbody>
</table>
<div class="bannerContent">
  <span style="margin-left: 165px; margin-top: 25px; font-family: Calibri,Verdana,Arial">XM</span>
<span style="font-size: small;"><p/></span>
<span style="margin-left:165px; font-size: small; font-family:Calibri,Verdana,Arial">Manage your money.</span>
</div>
<div class="fillerPanel40px">&nbsp;</div>
<noscript>
  <div class="errorPanel" style="width: 450px">
    <span class="boldLabel">Please enable JavaScript:</span><br/>
    <span class="inputLabel">This site might not function correctly if you have disabled JavaScript in your browser. Please change your settings to accommodate this.</span>
  </div>
  <div class="fillerPanel20px">&nbsp;</div>
</noscript>
