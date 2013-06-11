<?php
$t = array();

$usernames['localhost0']['username'] = "Server";
$usernames['localhost1']['username'] = "Server1";
$usernames['localhost2']['username'] = "Server2";
$usernames['localhost3']['username'] = "Server3";
echo "<pre>";
print_r($usernames);
echo "</pre>";

foreach ($usernames as $error)
{
	foreach ($error as $error1){
		$test = $error1;

	}
}
for ($i=0; $i < count($usernames); $i++) { 
	$test = implode(' ', $usernames['localhost'.$i]);
}

echo $test
?>