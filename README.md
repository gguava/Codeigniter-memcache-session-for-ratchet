Codeigniter-memcache-session-for-ratchet
========================================

Codeigniter memcache session for ratchet


Let ci support memcache session

i use ci_memsess,get it from:
https://github.com/davidwisch/ci_memsess 
attention: "Copy config/appconfig.php to the config/ directory in your application folder." maybe some error ,you should Copy config/appconfig.php to the config/config.php
Let ratchet support memcache session

include Session.ratchet.php
example:
$session=new CI_Session();
$session->session_id=xxsid;//you must let ratchet know the session_id,i use ajax to post the session_id
echo $session->get("xxx");
