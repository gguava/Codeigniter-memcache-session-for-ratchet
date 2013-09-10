Codeigniter-memcache-session-for-ratchet
========================================


<p>
<h2>Let ci support memcache session</h2>
i use ci_memsess,get it from:<br/>
https://github.com/davidwisch/ci_memsess
<br/>

attention: "Copy config/appconfig.php to the config/ directory in your application folder." maybe some error
,you should Copy config/appconfig.php to the config/config.php
</p>

<p>
<h2>Let ratchet support memcache session</h2>
include Session.ratchet.php<br/>
example:<br/>
$session=new CI_Session();<br/>
$session->session_id=xxsid;//you must let ratchet know the session_id,i use ajax to post the session_id<br/>
echo  $session->get("xxx");<br/>

</p>
