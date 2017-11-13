/****************************** Module Header ******************************
 * Module Name:  xm website
 * Project:      xm website to track everyday expenses
 *
 * Main CSS
 *
 * Scripts file for all JavaScript related functions
 *
 * 1. loadLandingPage - Load landing page, set form element to current time zone, submit form
 * 2. equalsIgnoreCase page - compare two strings for case
 * 3. fasttrim - remove whitespace characters, front and back
 * 4. validateLoginForm - Validate login form before posting to server
 *
 * Revisions:
 *     1. Sundar Krishnamurthy          sundar_k@hotmail.com       06/10/2017      Initial file created.
***************************************************************************/


// Actual URL of our application - FQDN
var global_fqdn = "$$SITE_URL$$";                      // $$ SITE_URL $$

// 1. Index page
// Used in:
//    index.php
function loadLandingPage() {

    // Construct Date object
    var currentDate = new Date();
    var sessionKey = $("#sessionKey").val();
    var errorMask = parseInt($("#errorMask").val());

    if ((sessionKey.length == 32) && (errorMask == 0)) {

        sessionKey = sessionKey.replace("\"", "\\\"");

        var xhr = new XMLHttpRequest();
        xhr.open("POST", global_fqdn + "services/updateTimeZone.php", true);

        // Send the proper header information along with the request
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

//        xhr.onreadystatechange = function() {
//             if ((xhr.readyState == 4) && (xhr.status == 200)) {
//                 alert(xhr.responseText);
//              }
//          };

        xhr.send("{\"sessionKey\":\"" + sessionKey +
                 "\",\"timezone\":" + (-currentDate.getTimezoneOffset() / 60).toString() + "}");
    }

    if (errorMask == 2) {
        $("#txtPassword").focus();
    } else {
        $("#txtUsername").focus();
    }
}

// 2. Utility function U1 - compare two strings for case
function equalsIgnoreCase(arg1, arg2) {
    return (arg1.toLowerCase() === arg2.toLowerCase());
}

// 3. Utility function U2 - remove whitespace characters, front and back
function fasttrim(str) {
    str = str.replace(/^\s\s*  /, ''),
                  ws = /\s/,
                  i = str.length;

    while (ws.test(str.charAt(--i)))
        ;

    return str.slice(0, i + 1);
}

// 4. Validate login form before posting to server
function validateLoginForm() {

    // Return value, default error message is <ul>
    var errorMask = 0;

    var errorMessage = "<ul>";

    // Read data on the login text field, trim it to remove whitespace on either side
    var loginElement = document.getElementById("txtUsername");
    var loginValue = fasttrim(loginElement.value);

    // Locate errorSection block, update display to block (from none)
    var errorSectionElement = document.getElementById("errorSection");

    // If the values don't match, replace it with the trimmed version
    if (loginValue != loginElement.value) {
        loginElement.value = loginValue;
    }

    // If the data field was blank, error has occured and update message
    if (loginValue === "") {
        // We found an error
        errorMask = 1;
        errorMessage += "<li>Please enter your username.</li>";
    } else {

        for (i = 0; i < loginValue.length; i++) {
            var c = loginValue[i];

            if (!(((c >= 'a') && (c <= 'z')) || ((c >= 'A') && (c <= 'Z')) || ((c >= '0') && (c <= '9'))))  {
                if ((c != '.') && (c != '_') && (c != '-')) {
                    errorMask = 1;
                    errorMessage += "<li>Please enter a valid username.</li>";
                    break;
                }
            }
        }
    }

    var txtPassword = $("#txtPassword").val();

    if (txtPassword === "") {
        errorMask |= 2;
        errorMessage += "<li>Please enter your password.</li>";
    }

    document.getElementById("errorMask").value = errorMask;

    // In case you found errors, display error message block
    if (errorMask > 0) {
        errorMessage += "</ul>";

        // Display error section
        errorSectionElement.style.display = "block";

        // Get section for errorHeaderSpan, set boiler-plate text for header
        document.getElementById("errorHeaderSpan").innerHTML = "Please correct these errors below:";

        // Locate errorText element, set innerHTML to message we constructed above
        var errorTextElement = document.getElementById("errorText");
        errorTextElement.innerHTML = errorMessage;
    } else {
        // Reset error message
        errorMessage = "";

        // Hide error section
        errorSectionElement.style.display = "none";
    }

    loadLandingPage();

    return (errorMask === 0);
}
