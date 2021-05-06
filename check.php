<?php
#check.php
session_start();
error_reporting(0);
$url = 'https://apiapi.pw/index.php?r=apiManager';
$key = $_SESSION['key'];
$secret = $_SESSION['secret'];


$postData = [
	'key' => $key,
	'method' => 'checkCardPayBankStatus',
	"orderId" => $_SESSION['orderId'],
	"MD" => $_POST['MD'],
	"PaRes" => $_POST['PaRes'],
];

$postData['hash'] = hashData($postData, $secret);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if(!$result = $response['result'])
	die($response['errorMsg']);

if($result['status'] == 'success')
	echo 'оплачено';
elseif($result['status'] == 'error')
	echo 'ошибка: '.$result['msg'];
else
{
	echo 'исключение';
	print_r($result);
}

function hashData($params, $secret)
{
	$result = '';

	foreach($params as $key => $val)
		if(!is_array($val))
			$result .= $val;

	return md5($result.$secret);
}
?>