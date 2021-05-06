<?php
$url = 'https://apiapi.pw/index.php?r=apiManager';
$key = 'GFJBHAAEJRQXABGH';
$secret = 'zBTE4X2Jyv968SYpWwBWwBRnYpGRYfYT';



$postData = [
	'key' => $key,
	'method' => 'getCardPayBankParams',
	'amount' => rand(100,200),
	'cardNumber' => '4890494701893547',
	'cardM' => '06',
	'cardY' => '21',
	'cardCvv' => '357',
	'browser' => 'Mozilla/5.0 (Microsoft; Windows NT 5.1; rv:56.0.1) Gecko/21500303 Firefox/56.1',
	'headers' => [
		'Accept: */*',
		'Accept-Language: ru-RU,ru;q=0.3,en-US;q=0.2,en;q=0.5',
		'Accept-Encoding: gzip',
	],
];

$postData['hash'] = hashData($postData, $secret);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

print_r($response);


function hashData($params, $secret)
{
	$result = '';

	foreach($params as $key => $val)
		if(!is_array($val))
			$result .= $val;

	return md5($result.$secret);
}
?>