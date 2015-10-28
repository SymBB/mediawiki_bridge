# mediawiki_bridge

Insert this at the end of your LocalSettings.php file for each symbb system

  $wgAuth = new Symbb\MediawikiBridge\MultiAuthBridge(__DIR__.'/PATH TO ROOT DIR OF SYMBB/', 'http://YOUR-URL-TO-SYMBB.de');
  
  $wgAuth->setLoginErrorMessage('<b>Du brauchst ein Forenaccount</b><br />');
  
  $wgAuth->setAuthErrorMessage('Du bist nicht in der richtigen Forengruppe.');