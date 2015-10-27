<?php

namespace Symbb\MediawikiBridge;

use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

/**
 * Class MultiAuthBridge
 * @package Symbb\MediawikiBridge
 */
class MultiAuthBridge extends \AuthPlugin {

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var string
     */
    protected $loginError = '<b>You need a account to login.</b><br />';

    /**
     * @var string
     */
    protected $authError = 'You are not a member of the required group.';

    /**
     * @var
     */
    protected $userId;

    /**
     * @var string
     */
    protected $pwAlgorithm = 'sha512';

    /**
     * @var bool
     */
    protected $encodeHashAsBase64   = true;

    /**
     * @var int
     */
    protected $iterations  = 5000;

    /**
     * @var array
     */
    protected $connections = array();

    /**
     *
     */
    function __construct() {
        // Set some MediaWiki Values
        // This requires a user be logged into the wiki to make changes.
        $GLOBALS['wgGroupPermissions']['*']['edit'] = false;

        // Specify who may create new accounts:
        $GLOBALS['wgGroupPermissions']['*']['createaccount'] = false;

        // Load Hooks
        $GLOBALS['wgHooks']['UserLoginForm'][] = array($this, 'onUserLoginForm', false);
        $GLOBALS['wgHooks']['UserLoginComplete'][] = $this;
        $GLOBALS['wgHooks']['UserLogout'][] = $this;
    }

    /**
     * @param $message
     */
    public function setLoginErrorMessage($message){
        $this->loginError = $message;
    }

    /**
     * @param $message
     */
    public function setAuthErrorMessage($message){
        $this->authError = $message;
    }


    /**
     * Add a user to the external authentication database.
     * Return true if successful.
     *
     * NOTE: We are not allowed to add users from the
     * wiki so this always returns false.
     *
     * @param User $user - only the name should be assumed valid at this point
     * @param string $password
     * @param string $email
     * @param string $realname
     * @return bool
     * @access public
     */
    public function addUser($user, $password, $email = '', $realname = '') {
        return false;
    }

    /**
     * Can users change their passwords?
     *
     * @return bool
     */
    public function allowPasswordChange() {
        return false;
    }

    /**
     * Check if a username+password pair is a valid login.
     * The name will be normalized to MediaWiki's requirements, so
     * you might need to munge it (for instance, for lowercase initial
     * letters).
     *
     * @param string $username
     * @param string $password
     * @return bool
     * @access public
     */
    public function authenticate($username, $password) {
var_dump(1); die();
        $connections = $this->getConnections();

        foreach($connections as $connection){

            // Connect to the database.
            $mysqliCon    = $this->connect($connection);

            $username               = $this->removeConnectionUsernamePrefix($connection, $username);
            $username               = $this->canonicalize($username);

            //
            // Check Database for username and password.
            $fstrMySQLQuery = sprintf("SELECT `id`, `username_canonical`, `password`, `salt` FROM `%s` WHERE `username_canonical` LIKE '%s' LIMIT 1", $connection->getUserTable(), mysqli_real_escape_string($mysqliCon, $username));

            // Query Database.
            $mysqliResult = mysqli_query($fstrMySQLQuery, $mysqliCon) or die($this->mySQLError('Unable to view external table'));

            while ($result = mysqli_fetch_assoc($mysqliResult)) {
                // Use new phpass class
                $encoder = new MessageDigestPasswordEncoder($this->pwAlgorithm, $this->pwEncodeHashAsBase64, $this->pwIterations);
                $valid = $encoder->isPasswordValid($result['password'], $password, $result['salt']);

                /**
                 * Check if password submited matches the symbb password.
                 * Also check if user is a member of the phpbb group 'wiki'.
                 */
                if ($valid && $this->isMemberOfWikiGroup($username, $connection)) {
                    $this->userId = $result['id'];
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $string
     * @return null|string
     */
    public function canonicalize($string)
    {
        return null === $string ? null : mb_convert_case($string, MB_CASE_LOWER, mb_detect_encoding($string));
    }

    /**
     * @param $host
     * @param $user
     * @param $password
     * @param $database
     * @param string $prefix
     * @param array $groups
     * @param string $usernamePrefix
     * @throws \Exception
     */
    public function addSymbbSystem($host, $user, $password, $database, $prefix = 'symbb_', $groups = array(), $usernamePrefix = ''){

        foreach($this->connections as $conn){
            /**
             * @var $conn Connection
             */
            if($conn->getUsernamePrefix() == $usernamePrefix){
                throw new \Exception('The Usernameprefix ['.$usernamePrefix.'] is already definied!');
            }
        }

        $connection = new Connection();
        $connection->setHost($host);
        $connection->setUser($user);
        $connection->setPassword($password);
        $connection->setDatabase($database);
        $connection->setPrefix($prefix);
        $connection->setGroups($groups);
        $connection->setUsernameprefix($usernamePrefix);
        $this->connections[] = $connection;
    }

    /**
     * @return array
     */
    public function getConnections(){
        return $this->connections;
    }

    /**
     * Return true if the wiki should create a new local account automatically
     * when asked to login a user who doesn't exist locally but does in the
     * external auth database.
     *
     * If you don't automatically create accounts, you must still create
     * accounts in some way. It's not possible to authenticate without
     * a local account.
     *
     * This is just a question, and shouldn't perform any actions.
     *
     * NOTE: I have set this to true to allow the wiki to create accounts.
     *       Without an accout in the wiki database a user will never be
     *       able to login and use the wiki. I think the password does not
     *       matter as long as authenticate() returns true.
     *
     * @return bool
     * @access public
     */
    public function autoCreate() {
        return true;
    }

    /**
     * Check to see if external accounts can be created.
     * Return true if external accounts can be created.
     *
     * NOTE: We are not allowed to add users to phpBB from the
     * wiki so this always returns false.
     *
     * @return bool
     * @access public
     */
    public function canCreateAccounts() {
        return false;
    }

    /**
     * Connect to the database. All of these settings are from the
     * LocalSettings.php file. This assumes that the PHPBB uses the same
     * database/server as the wiki.
     *
     * @return \mysqli
     */
    private function connect(Connection $connection) {

        // Connect to database. I supress the error here.
        $mysqliCon = mysqli_connect($connection->getHost(), $connection->getUser(), $connection->getPassword(), true);

        // Check if we are connected to the database.
        if (!$mysqliCon) {
            $this->mySQLError('There was a problem when connecting to the phpBB database.<br /> Check your Host('.$connection->getHost().'), Username('.$connection->getUser().'), and Password settings.<br />');
        }

        // Select Database
        $db_selected = mysqli_select_db($connection->getDatabase(), $mysqliCon);

        // Check if we were able to select the database.
        if (!$db_selected) {
            $this->mySQLError('There was a problem when connecting to the phpBB database.<br />The database ' . $connection->getDatabase() . ' was not found.<br />');
        }

        mysqli_query("SET NAMES 'utf8'", $mysqliCon); // This is so utf8 usernames work. Needed for MySQL 4.1

        return $mysqliCon;
    }

    /**
     * This turns on debugging
     *
     */
    public function EnableDebug() {
        $this->debug = true;
        return;
    }

    /**
     * If you want to munge the case of an account name before the final
     * check, now is your chance.
     *
     * @return string
     */
    public function getCanonicalName($username) {
        $username   = $this->canonicalize($username);
        // At this point the username is invalid and should return just as it was passed.
        return $username;
    }

    /**
     * @param $user
     * @param bool|false $autocreate
     * @return bool
     * @throws \Exception
     */
    public function initUser(&$user, $autocreate = false) {
        $userData = $this->getUserData($user->mName);
        if($userData && !empty($userData['data'])){
            $user->mEmail = $userData['data']['email']; // Set Email Address.
            return true;
        }
        return false;
    }

    /**
     * @param $username
     * @return array|null
     * @throws \Exception
     */
    protected function getUserData($username){

        $connections = $this->getConnections();

        foreach($connections as $connection){

            /**
             * @var $connection Connection
             */

            // Connect to the database.
            $mysqliCon = $this->connect($connection);
            //
            $cleanUsername = $this->removeConnectionUsernamePrefix($connection, $username);
            $cleanUsername = $this->canonicalize($cleanUsername);

            // Check Database for username and email address.
            $query = sprintf("SELECT `username_canonical`, `email` FROM `%s` WHERE `username_canonical` LIKE '%s' LIMIT 1", $connection->getUserTable(), mysqli_real_escape_string($mysqliCon, $cleanUsername));
            $mysqliResult = mysqli_query($query, $mysqliCon) or die($this->mySQLError('Unable to view external table'));

            $groups = array();

            while ($result = mysqli_fetch_array($mysqliResult)) {

                $query = sprintf("SELECT `group_id` FROM `%s` WHERE `user_id` = %i", $connection->getUserGroupTable(), $result['id']);
                $mysqliGroupResult = mysqli_query($query, $mysqliCon) or die($this->mySQLError('Unable to view external table'));

                while ($groupId = mysqli_fetch_array($mysqliGroupResult)) {
                    $query = sprintf("SELECT `id`, `name` FROM `%s` WHERE `id` = %i", $connection->getGroupTable(), $groupId['group_id']);
                    $mysqliGroupDataResult = mysqli_query($query, $mysqliCon) or die($this->mySQLError('Unable to view external table'));
                    while ($group = mysqli_fetch_array($mysqliGroupDataResult)) {
                        $groups[] = $group;
                    }
                }

                return array(
                    'data' => $result,
                    'groups' => $groups
                );
            }
        }

        return null;
    }

    /**
     * @param Connection $connection
     * @param $username
     * @return string
     */
    public function removeConnectionUsernamePrefix(Connection $connection, $username){
        $prefix     = $connection->getUsernamePrefix();
        $prefix     = strtolower($prefix);
        if(!empty($prefix) && strpos($username, $prefix) === 0){
            $username = substr($username, strlen($prefix));
        }
        return $username;
    }

    /**
     * @param $username
     * @param Connection $connection
     * @return bool
     */
    private function isMemberOfWikiGroup($username, Connection $connection) {

        $groups = $connection->getGroups();

        // In LocalSettings.php you can control if being a member of a wiki
        // is required or not.
        if (empty($groups)) {
            return true;
        }

        $userdata = $this->getUserData($username);

        foreach ($groups as $WikiGrpName) {

            foreach($userdata['groups'] as $symbbGroup){
                if(strtolower($symbbGroup['name']) == strtolower($WikiGrpName)){
                    return true;
                }
            }
        }

        // Hook error message.
        $GLOBALS['wgHooks']['UserLoginForm'][] = array($this, 'onUserLoginForm', $this->authError);
        return false; // User is not in Wiki group.
    }

    /**
     * Modify options in the login template.
     *
     * NOTE: Turned off some Template stuff here. Anyone who knows where
     * to find all the template options please let me know. I was only able
     * to find a few.
     *
     * @param UserLoginTemplate $template
     * @access public
     */
    public function modifyUITemplate(&$template, &$type) {
        $template->set('usedomain', false); // We do not want a domain name.
        $template->set('create', false); // Remove option to create new accounts from the wiki.
        $template->set('useemail', false); // Disable the mail new password box.
    }

    /**
     * This prints an error when a MySQL error is found.
     *
     * @param $message
     * @throws \Exception
     */
    private function mySQLError($message) {
        throw new \Exception($message . '<br />' . 'MySQL Error Number: ' . mysql_errno() . '<br />' . 'MySQL Error Message: ' . mysql_error() . '<br /><br />');
    }

    /**
     * This is the hook that runs when a user logs in. This is where the
     * code to auto log-in a user to phpBB should go.
     *
     * Note: Right now it does nothing,
     *
     * @param object $user
     * @return bool
     */
    public function onUserLoginComplete(&$user) {
        // @ToDo: Add code here to auto log into the forum.
        return true;
    }

    /**
     * Here we add some text to the login screen telling the user
     * they need a phpBB account to login to the wiki.
     *
     * Note: This is a hook.
     * @param bool|false $errorMessage
     * @param $template
     * @return bool
     */
    public function onUserLoginForm($errorMessage = false, $template) {
        $template->data['link'] = $this->loginError;

        // If there is an error message display it.
        if ($errorMessage) {
            $template->data['message'] = $errorMessage;
            $template->data['messagetype'] = 'error';
        }
        return true;
    }

    /**
     * This is the Hook that gets called when a user logs out.
     *
     * @param $user
     * @return bool
     */
    public function onUserLogout(&$user) {
        // User logs out of the wiki we want to log them out of the form too.
        if (!isset($this->session)) {
            return true; // If the value is not set just return true and move on.
        }
        return true;
        // @todo: Add code here to delete the session.
    }

    /**
     * Set the domain this plugin is supposed to use when authenticating.
     *
     * NOTE: We do not use this.
     *
     * @param string $domain
     * @access public
     */
    public function setDomain($domain) {
        $this->domain = $domain;
    }

    /**
     * Set the given password in the authentication database.
     * As a special case, the password may be set to null to request
     * locking the password to an unusable value, with the expectation
     * that it will be set later through a mail reset or other method.
     *
     * Return true if successful.
     *
     * NOTE: We only allow the user to change their password via phpBB.
     *
     * @param $user User object.
     * @param $password String: password.
     * @return bool
     * @access public
     */
    public function setPassword($user, $password) {
        return true;
    }

    /**
     * Return true to prevent logins that don't authenticate here from being
     * checked against the local database's password fields.
     *
     * This is just a question, and shouldn't perform any actions.
     *
     * Note: This forces a user to pass Authentication with the above
     *       function authenticate(). So if a user changes their PHPBB
     *       password, their old one will not work to log into the wiki.
     *       Wiki does not have a way to update it's password when PHPBB
     *       does. This however does not matter.
     *
     * @return bool
     * @access public
     */
    public function strict() {
        return true;
    }

    /**
     * Update user information in the external authentication database.
     * Return true if successful.
     *
     * @param $user User object.
     * @return bool
     * @access public
     */
    public function updateExternalDB($user) {
        return true;
    }

    /**
     * When a user logs in, optionally fill in preferences and such.
     * For instance, you might pull the email address or real name from the
     * external user database.
     *
     * The User object is passed by reference so it can be modified; don't
     * forget the & on your function declaration.
     *
     * NOTE: Not useing right now.
     *
     * @param User $user
     * @access public
     * @return bool
     */
    public function updateUser(&$user) {
        return true;
    }

    /**
     * Check whether there exists a user account with the given name.
     * The name will be normalized to MediaWiki's requirements, so
     * you might need to munge it (for instance, for lowercase initial
     * letters).
     *
     * NOTE: MediaWiki checks its database for the username. If it has
     *       no record of the username it then asks. "Is this really a
     *       valid username?" If not then MediaWiki fails Authentication.
     *
     * @param string $username
     * @return bool
     * @access public
     */
    public function userExists($username) {

        $data = $this->getUserData($username);

        if(!empty($data)){
            return true;
        }

        return false; // Fail
    }

    /**
     * Check to see if the specific domain is a valid domain.
     *
     * @param string $domain
     * @return bool
     * @access public
     */
    public function validDomain($domain) {
        return true;
    }

    /**
     * @return bool
     */
    public function allowSetLocalPassword(){
        return false;
    }


}