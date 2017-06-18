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
function loadLandingPage(sessionKey) {

    if (sessionKey !== "") {
        // Construct Date object
        var currentDate = new Date();

        var xhr = new XMLHttpRequest();
        xhr.open("POST", global_fqdn + "updateTimezone.php", true);

        //Send the proper header information along with the request
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

//       xhr.onreadystatechange = function() {
//           if (xhr.readyState == 4 && xhr.status == 200) {
//
//            }
//        };

        xhr.send("{\"request\":{\"sessionKey\":\"" + sessionKey + 
                 "\",\"timeZone\":" + (-currentDate.getTimezoneOffset() / 60).toString() + "}}");
    }

    var txtElement = $("#txtUsername");

    if (txtElement === null) {
        txtElement = $("#txtPassword");
    }

    if (txtElement !== null) {
        txtElement.focus();
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

    var errorMessage = null;

    // Read data on the login text field, trim it to remove whitespace on either side
    var loginElement = document.getElementById("txtUsername");
    var loginValue = fasttrim(loginElement.value);

    // Locate loginNameSpan element
    var loginSpanElement = document.getElementById("loginNameSpan");

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

        // Update error message to add this warning
        errorMessage = "<ul><li>Please enter your registered user ID.</li></ul>";
    } else {
        
        for (i = 0; i < loginValue.length; i++) {
            var c = loginValue[i];

            if (!(((c >= 'a') && (c <= 'z')) || ((c >= 'A') && (c <= 'Z')) || ((c >= '0') && (c <= '9'))))  {
                if ((c != '.') && (c != '_')) {
                    errorMask = 1;
                    break;
                }
            }
        }

        if (errorMask === 1) {
            errorMessage = "<ul><li>Please enter a valid user ID.</li></ul>";
        }
    }

    if ((errorMask === 0) && (loginSpanElement.className = "redBoldLabel")) {
        // Set className back as boldLabel
        loginSpanElement.className = "boldLabel";
    } else if (errorMask > 0) {
        // Set className as boldRedLabel
        loginSpanElement.className = "boldRedLabel";
    }

    // In case you found errors, display error message block
    if (errorMask === 1) {
        // Add closing tag to make errorMessage displayable
        errorMessage += "</ul>";

        // Display error section
        errorSectionElement.style.display = "block";

        // Get section for errorHeaderSpan, set boiler-plate text for header
        document.getElementById("errorHeaderSpan").innerHTML = "Please correct these errors below:";

        // Locate errorText element, set innerHTML to message we constructed above
        var errorTextElement = document.getElementById("errorText");
        errorTextElement.innerHTML = errorMessage;

        document.getElementById("errorMask").value = errorMask;

        loadLoginPage("");
    } else {
        // Reset error message
        errorMessage = "";

        // Hide error section
        errorSectionElement.style.display = "none";
    }

    var txtPassword = $("#txtPassword").val();

    if ((errorMask === 0) && (txtPassword === "")) {
        $("#txtPassword").focus();
    }
	
    return (errorMask === 0) && (txtPassword !== "");
}
