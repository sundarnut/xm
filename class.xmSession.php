<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// class xmSession - store all session information in variables here
//
// Revisions:
//     1. Sundar Krishnamurthy          sundar_k@hotmail.com       04/25/2017      Initial file created.


class xmSession {

    //  Set to a key for this session, this is not Session ID
    private $sessionKey;

    // Timezone of browser accessing website
    private $timeZone;

    // Set to true first time this page is accessed, so we don't rewrite logons back or track this access request on subsequent iterations
    private $userLogged;

    // User ID of this user logging in
    private $userId;

    // First name [space] last name
    private $fullName;

    // First name
    private $firstName;

    // Email address used to login to the system for this user
    private $email;

    // Salt for this user saved in the session
    private $salt;

    // Password hash we read from the database
    private $passwordHash;

    // Whether this user account is active
    private $active;

    // Status bitmask
    private $status;

    // Whether this user has cookies dispatched, or not
    private $cookies;

    // What is the userKey we dispatch in the cookie?    
    private $userKey;

    // If we dispatched a cookie to the user with user key, read this back
    private $cookieUserKey;

    // If we dispatched a password to the user for rememberance, read this back
    private $cookiePasswordHash;

    // No logging is to be done for users logging out and going back to index page
    private $noLog;

    // Number of times user has tried to login
    private $loginCount;

    // When was this user locked out earlier?
    private $lockTime;

    // Error message if something happened on a page
    private $errorMessage;

    // What is the next URL that we need to redirect this user to?
    private $nextUrl;

    // Display message if something needs to be shown
    private $displayMessage;

    // What is the logId read for user stumbling in to this website?
    private $logId;
    
    // Default Constructor
    function __construct() {
        
        $this->sessionKey = null;
        $this->timeZone = 0;
        $this->userLogged = 0;
        $this->userId = 0;
        $this->fullName = null;
        $this->firstName = null;
        $this->email = null;
        $this->salt = null;
        $this->passwordHash = null;
        $this->active = false;
        $this->status = 0;
        $this->cookies = false;
        $this->userKey = null;
        $this->cookieUserKey = null;
        $this->cookiePasswordHash = null;
        $this->noLog = 0;
        $this->loginCount = 0;
        $this->lockTime = null;
        $this->errorMessage = null;
        $this->displayMessage = null;
        $this->nextUrl = null;
        $this->logId = 0;
    }   //  End function __construct()


    // Get the session key
    public function getSessionKey() {
        return $this->sessionKey;
    }   //  End public function getSessionKey()

    // Get the time zone
    public function getTimeZone() {
        return $this->timeZone;
    }   //  End public function getTimeZone()

    // Get the flag whether this user logged in earlier or not
    public function getUserLogged() {
        return $this->userLogged;
    }   //  End public function getUserLogged()

    // Get the userId
    public function getUserId() {
        return $this->userId;
    }   //  End public function getUserId()

    // Get the full name
    public function getFullName() {
        return $this->fullName;
    }   //  End public function getFullName()

    // Get the first name
    public function getFirstName() {
        return $this->firstName;
    }   //  End public function getFirstName()

    // Get the Email
    public function getEmail() {
        return $this->email;
    }   //  End public function getEmail()

    // Get the salt
    public function getSalt() {
        return $this->salt;
    }   //  End public function getSalt()

    // Get the Password Hash
    public function getPasswordHash() {
        return $this->passwordHash;
    }   //  End public function getPasswordHash()

    // Get the Active Flag
    public function getActive() {
        return $this->active;
    }   //  End public function getActive()

    // Get the Status Flag
    public function getStatus() {
        return $this->status;
    }   //  End public function getStatus()

    // Get the Cookies Flag
    public function getCookies() {
        return $this->cookies;
    }   //  End public function getCookies()

    // Get the User Key
    public function getUserKey() {
        return $this->userKey;
    }   //  End public function getUserKey()

    // Get the User Key we read from cookie
    public function getCookieUserKey() {
        return $this->cookieUserKey;
    }   //  End public function getCookieUserKey()

    // Get the Password Hash we read from cookie
    public function getCookiePasswordHash() {
        return $this->cookiePasswordHash;
    }   //  End public function getCookiePasswordHash()

    // Get the no-log value we set prior
    public function getNoLog() {
        return $this->noLog;
    }   //  End public function getNoLog()

    // Get the Login attempt count
    public function getLoginCount() {
        return $this->loginCount;
    }   //  End public function getLoginCount()

    // Get the last timestamp when this user was locked out
    public function getLockTime() {
        return $this->lockTime;
    }   //  End public function getLockTime()

    // Get the error message, if set prior
    public function getErrorMessage() {
        return $this->errorMessage;
    }   //  End public function getErrorMessage()

    // Get the next URL, if set prior
    public function getNextUrl() {
        return $this->nextUrl;
    }   //  End public function getNextUrl()

    // Get the display message, if set prior
    public function getDisplayMessage() {
        return $this->displayMessage;
    }   //  End public function getDisplayMessage()

    // Get the logId, if set prior
    public function getLogId() {
        return $this->logId;
    }   //  End public function getLogId()


    // Set the session key
    public function setSessionKey($sessionKey) {
        $this->sessionKey = $sessionKey;
    }   //  End public function setSessionKey($sessionKey)

    // Set the session key
    public function setTimeZone($timeZone) {
        $this->timeZone = $timeZone;
    }   //  End public function setTimeZone($timeZone)

    // Set the userLogged flag
    public function setUserLogged($userLogged) {
        $this->userLogged = $userLogged;
    }   //  End public function setUserLogged($userLogged)

    // Set the userId
    public function setUserId($userId) {
        $this->userId = $userId;
    }   //  End public function setUserId($userId)

    // Set the full name
    public function setFullName($fullName) {
        $this->fullName = $fullName;
    }   //  End public function setFullName($fullName)

    // Set the first name
    public function setFirstName($firstName) {
        $this->firstName = $firstName;
    }   //  End public function setFirstName($firstName)

    // Set the email
    public function setEmail($email) {
        $this->email = $email;
    }   //  End public function setEmail($email)

    // Set the salt
    public function setSalt($salt) {
        $this->salt = $salt;
    }   //  End public function setSalt($salt)

    // Set the password hash
    public function setPasswordHash($passwordHash) {
        $this->passwordHash = $passwordHash;
    }   //  End public function setPasswordHash($passwordHash)

    // Set the active flag
    public function setActive($active) {
        $this->active = $active;
    }   //  End public function setActive($active)

    // Set the status flag
    public function setStatus($status) {
        $this->status = $status;
    }   //  End public function setStatus($status)

    // Set the flag if cookies are allowed
    public function setCookies($cookies) {
        $this->cookies = $cookies;
    }   //  End public function setCookies($cookies)

    // Set the flag for user key
    public function setUserKey($userKey) {
        $this->userKey = $userKey;
    }   //  End public function setUserKey($userKey)

    // Set the value we read from the cookie for user key
    public function setCookieUserKey($cookieUserKey) {
        $this->cookieUserKey = $cookieUserKey;
    }   //  End public function setCookieUserKey($cookieUserKey)

    // Set the value we read from the cookie for password hash
    public function setCookiePasswordHash($cookiePasswordHash) {
        $this->cookiePasswordHash = $cookiePasswordHash;
    }   //  End public function setCookiePasswordHash($cookiePasswordHash)

    // Set the no logging flag
    public function setNoLog($noLog) {
        $this->noLog = $noLog;
    }   //  End public function setNoLog($noLog)

    // Set the HTTP Referer
    public function setReferer($referer) {
        $this->referer = $referer;
    }   //  End public function setReferer($referer)

    // Set the Login Count
    public function setLoginCount($loginCount) {
        $this->loginCount = $loginCount;
    }   //  End public function setLoginCount($loginCount)

    // Set the Lock Time
    public function setLockTime($lockTime) {
        $this->lockTime = $lockTime;
    }   //  End public function setLockTime($lockTime)

    // Set the Error Message
    public function setErrorMessage($errorMessage) {
        $this->errorMessage = $errorMessage;
    }   //  End public function setErrorMessage($errorMessage)

    // Set the Next URL
    public function setNextUrl($nextUrl) {
        $this->nextUrl = $nextUrl;
    }   //  End public function setNextUrl($nextUrl)
    
    // Set the Display Message
    public function setDisplayMessage($displayMessage) {
        $this->displayMessage = $displayMessage;
    }   //  End public function setDisplayMessage($displayMessage)
    
    // Set the Log ID
    public function setLogId($logId) {
        $this->logId = $logId;
    }   //  End public function setLogId($logId)
}   //  End class xmSession

?>
