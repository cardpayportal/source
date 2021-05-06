<?php

function toLog($msg, $isFatal=null, $sendSms=null, $category=null)
{
	return Tools::log($msg, $isFatal, $sendSms, $category);
}

function toLogError($msg, $isFatal=null, $sendSms=null)
{
	return Tools::log($msg, $isFatal, $sendSms, 'error');
}

function toLogRuntime($msg, $isFatal=null, $sendSms=null)
{
	return Tools::log($msg, $isFatal, $sendSms, 'runtime');
}

function notice($text, $to=false)
{
	return Tools::notice($text, $to);
}


function url($path, $params=array())
{		
	$url = Yii::app()->createUrl($path, $params);
		
	$url = str_replace('%23', '#', $url);
		
	return $url;
}

/**
 * локальная ссылка в текущем контроллере
 */
function urlController($path, $params=array())
{		
	$url = Yii::app()->controller->createUrl($path, $params);
		
	$url = str_replace('%23', '#', $url);
		
	return $url;
}

function absUrl($path, $params=array())
{
	return Yii::app()->createAbsoluteUrl($path, $params);
}

function cfg($name)
{
	return Yii::app()->params[$name];
}

/**
 * @param string $name
 * @param bool|false $value
 * @return string|bool
 */
function config($name, $value=false)
{
	return Config::val($name, $value);
}

function mb_ucfirst($str, $enc = 'utf-8') 
{ 
	return mb_strtoupper(mb_substr($str, 0, 1, $enc), $enc).mb_substr($str, 1, mb_strlen($str, $enc), $enc); 
}

/**
 * округляет в меньшую сторону до $numCount знаков после запятой
 */
function floorAmount($amount, $numCount = 2)
{
	if($numCount < 0)
		$numCount = 0;
		
	$amount = (str_replace(',', '.', $amount))*1;
		
	return floor($amount * pow(10, $numCount)) / pow(10, $numCount);
}

/**
 * округляет в большую сторону
 */
function ceilAmount($amount, $numCount = 2)
{
	if($numCount < 0)
		$numCount = 0;
		
	$amount = floatval(str_replace(',', '.', $amount));
		
	return ceil($amount * pow(10, $numCount)) / pow(10, $numCount);
}

/**
 * форматирует число
 * @param float $amount
 * @param int $numCount
 * @param string $thousandsSep
 * @return string
 */
function formatAmount($amount, $numCount = 2, $thousandsSep = ' ')
{
	$result = number_format($amount, $numCount, '.', ' ');
	
	if(strpos($result, '.')!==false)
	{
		$result = rtrim($result, '0');
		
		if(strpos($result, '.')+1 == strlen($result))
			$result = rtrim($result, '.');
	}
	
	return $result;
}


function generateCode($symbols = null, $len = 4)
{
	if($symbols === null)
		$symbols = '0123456789';
	
	$result = '';
		
	for($i=1;$i<=$len;$i++)
		$result .= $symbols{rand(0, strlen($symbols)-1)};
			
	return $result;
}

function noticeAdmin($msg)
{
	toLog('уведомление админу: '.$msg);
	sms(cfg('admin_phone'), $msg);
}

function sms($phone, $msg)
{
	$config = cfg('sms');
		
	$smsUrl = 'https://smsc.ru/sys/send.php?login='.rawurlencode($config['login']).'&psw='.rawurlencode($config['pass']).'&sender='.rawurlencode($config['sender']).'&charset=utf-8&phones='.rawurlencode(rawurldecode(trim($phone))).'&mes='.rawurlencode(rawurldecode($msg));
	
	$logMsg = 'смс на '.$phone.': '.$msg;
		
	$sender = new Sender;
	$sender->followLocation = false;
	$content = $sender->send($smsUrl);
		
	if(preg_match('!^OK - \d+ SMS, ID - \d+!', $content, $res))
	{
		toLog('yii::sms(): '.$logMsg);
		return true;
	}
	elseif(preg_match('!^ERROR = \d+ \(.+?\), ID - \d+!', $content, $res))
		$error = $res[1];
	else
		$error = 'sms error на '.$phone.' : '.$content;
		
	if($error)
	{
		toLog('yii::sms()_1: '.$error);
		notice($error);
		return false;
	}
}

function buildQuery(array $arParams)
{
	return http_build_query($arParams, '', '&');
}

function cryptor($data, $key)
{
		//$data - может быть строкой, массивом или объектом
		$key = hash_hmac('sha256', $key, $key);
		$encrypt = serialize($data);
		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC), MCRYPT_DEV_URANDOM);
		$key = pack('H*', $key);
		$mac = hash_hmac('sha256', $encrypt, substr(bin2hex($key), -32));
		$passcrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $encrypt.$mac, MCRYPT_MODE_CBC, $iv);
		$encoded = base64_encode($passcrypt).'|'.base64_encode($iv);
		return $encoded;
}

function decryptor($data, $key)
{
	//$data - может быть строкой
	$key = hash_hmac('sha256', $key, $key);
	$decrypt = explode('|', $data);
	$decoded = base64_decode($decrypt[0]);
	$iv = base64_decode($decrypt[1]);
	if(strlen($iv)!==mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC)){ return false; }
	$key = pack('H*', $key);
	$decrypted = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $decoded, MCRYPT_MODE_CBC, $iv));
	$mac = substr($decrypted, -64);
	$decrypted = substr($decrypted, 0, -64);
	$calcmac = hash_hmac('sha256', $decrypted, substr(bin2hex($key), -32));
	if($calcmac!==$mac){ return false; }
	$decrypted = unserialize($decrypted);
	return $decrypted;
}

function arr2str($array)
{
	return Tools::arr2Str($array);
}

function utf8($text)
{
	return str_replace(chr(194).chr(160), ' ', $text);
}

function validateCard($number)
{
	$validator = new CardValidator();

	return $validator->Luhn($number);
}

function shortText($str, $len=false, $id=false, $type=false)
{
	return Tools::shortText($str, $len=false, $id=false, $type=false);
}


function prrd($data)
{
	print_r($data);
	die;
}

function runtimeLog($label)
{
	return Tools::runtimeLog($label);
}

function toLogStoreApi($msg, $isFatal=null, $sendSms=null)
{
	return Tools::log($msg, $isFatal, $sendSms, 'storeApi');
}

function toLogEcommApi($msg, $isFatal=null, $sendSms=null)
{
	return Tools::log($msg, $isFatal, $sendSms, 'ecommApi');
}

function toLogSecurity($msg, $isFatal=null, $sendSms=null)
{
	return Tools::log($msg, $isFatal, $sendSms, 'security');
}

/**
 * @param string $cardNumber
 * @return string 1234 1234 1234 1234
 */
function formatCard($cardNumber)
{
	$cardNumber = preg_replace('![^\d]!', '', $cardNumber);
	$cardNumber = substr($cardNumber, 0, 4).' '
		.substr($cardNumber, 4, 4).' '
		.substr($cardNumber, 8, 4).' '
		.substr($cardNumber, 12, 4);

	return $cardNumber;
}