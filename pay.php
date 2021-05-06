<?php
#pay.php
session_start();
error_reporting(0);
$url = 'https://apiapi.pw/index.php?r=apiManager';
$redirUrl = 'https://apiapi.pw/check.php';	//урл для возврата пользователя после ввода смс на странице банка (POST)


if($_POST['submit'])
{
	$_SESSION['key'] = $_POST['key'];
	$_SESSION['secret'] = $_POST['secret'];

	$postData = [
		'key' => $_SESSION['key'],
		'method' => 'getCardPayBankParams',
		'amount' => $_POST['amount'],
		'cardNumber' => $_POST['cardNumber'],
		'cardM' => $_POST['cardM'],
		'cardY' => $_POST['cardY'],
		'cardCvv' => $_POST['cardCvv'],
		'browser' => $_SERVER['HTTP_USER_AGENT'],
		'headers' => [
			$_SERVER['HTTP_ACCEPT'],
			$_SERVER['HTTP_ACCEPT_LANGUAGE'],
			$_SERVER['HTTP_ACCEPT_ENCODING'],
		],
	];

	$postData['hash'] = hashData($postData, $_SESSION['secret']);

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
	$response = json_decode(curl_exec($ch), true);
	curl_close($ch);

	if(!$redirParams = $response['result'])
		die($response['errorMsg']);

	$redirParams['postArr']['TermUrl'] = $redirUrl;

	//для скрипта проверки
	$_SESSION['orderId'] = $redirParams['orderId'];

	$formFields = '';

	foreach($redirParams['postArr'] as $key => $val)
		$formFields .= '<input type="hidden" name="'.$key.'" value="'.$val.'"/>';
	//редирект клиента в банк
	echo <<<EOD
<html>
<body>
<form method="post" action="{$redirParams['url']}" id="form">
$formFields
</form>
<script>
document.getElementById('form').submit();
</script>
</body>
</html>
EOD;
}
else
	echo <<<EOD
<html>
<body>
<form method="post" action="" id="form">
	<p>
		<b>API KEY</b><br>
		<input type="text" name="key">
	</p>
	<p>
		<b>API SECRET</b><br>
		<input type="text" name="secret">
	</p>
	<p>
		<b>Сумма (мин 100)</b><br>
		<input type="text" name="amount">
	</p>
	<p>
		<b>Номер карты (16 цифр)</b><br>
		<input type="text" name="cardNumber">
	</p>
	<p>
		<b>Месяц (2 цифры)</b><br>
		<input type="text" name="cardM">
	</p>
	<p>
		<b>Год (2 цифры)</b><br>
		<input type="text" name="cardY">
	</p>
	<p>
		<b>CVV (3 цифры)</b><br>
		<input type="text" name="cardCvv">
	</p>
	<input type="submit" name="submit" value="отправить"/>
</form>
</body>
</html>
EOD;



function hashData($params, $secret)
{
	$result = '';

	foreach($params as $key => $val)
		if(!is_array($val))
			$result .= $val;

	return md5($result.$secret);
}
?>