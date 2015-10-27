# mediawiki_bridge

Insert this at the end of your LocalSettings.php file for each symbb system

$auth = new Symbb\MediawikiBridge\MultiAuthBridge();
$auth->setLoginErrorMessage('<b>You need a phpBB account to login.</b><br />');
$auth->setAuthErrorMessage('You are not a member of the required phpBB group.');
$auth->addSymbbSystem(
        'DATABASE HOST',
        'DATABASE_USER',
        'DATABASE_PASSWORD',
        'DATABASE_DATABASENAME',
        'symbb_', // prefix of your symbb tables
        array(), // phpbb groups | empty = all users have acces | array = only user with this group (name of the groups)
        '' // prefix for users, you need different prefixes for each phpbbsystem because two or more system can have the same usernames
); 