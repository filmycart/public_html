<?php
	$_h = curl_init();
	curl_setopt($_h, CURLOPT_HEADER, 1);
	curl_setopt($_h, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($_h, CURLOPT_HTTPGET, 1);
	curl_setopt($_h, CURLOPT_URL, 'https://mcom-connector.bcn.magento.com' );
	curl_setopt($_h, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
	curl_setopt($_h, CURLOPT_DNS_CACHE_TIMEOUT, 2 );

	$server_output = curl_exec($_h);

	var_dump(curl_exec($_h));
	var_dump(curl_getinfo($_h));
	var_dump(curl_error($_h));

	print"<pre>";
	print_r($server_output);
	exit;

	//
// A very simple PHP example that sends a HTTP POST to a remote site
//

/*$ch = curl_init();

curl_setopt($ch, CURLOPT_URL,"https://mcom-connector.bcn.magento.com");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, 
          http_build_query(array('postvar1' => 'value1')));

// Receive server response ...
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$server_output = curl_exec($ch);

curl_close($ch);

print"<pre>";
print_r($server_output);
exit;

// Further processing ...
if ($server_output == "OK") { ... } else { ... }*/

	/*var_dump(curl_exec($_h));
	var_dump(curl_getinfo($_h));
	var_dump(curl_error($_h));*/
?>