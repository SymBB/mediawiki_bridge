# mediawiki_bridge

Insert this at the end of your LocalSettings.php file for each phpbb3 system

$wgAuth->addSymbbSystem(
        'DATABASE HOST',
        'DATABASE_USER',
        'DATABASE_PASSWORD',
        'DATABASE_DATABASENAME',
        'symbb_', // prefix of your symbb tables
        array(), // phpbb groups | empty = all users have acces | array = only user with this group (name of the groups)
        '' // prefix for users, you need different prefixes for each phpbbsystem because two or more system can have the same usernames
);