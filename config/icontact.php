<?php
if(IN_PRODUCTION) {
	$config['app_url'] = 'https://app.icontact.com/icp/';
} else {
	$config['app_url'] = 'https://app.beta.icontact.com/icp';
}
?>