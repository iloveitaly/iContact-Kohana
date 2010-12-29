<?php
if(IN_PRODUCTION) {
	$config['app_url'] = 'https://app.icontact.com/icp/';
} else {
	$config['app_url'] = 'https://app.beta.icontact.com/icp';
}

$config['field_list'] = "first_name,last_name,email,address,address_2,city,state,zip";
$config['fields_labels'] = "[fname],[lname],[email],[address1],[address2],[city],[state],[zip]\n";
?>