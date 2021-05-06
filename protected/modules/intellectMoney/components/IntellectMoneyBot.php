<?php
/**
 *	работа с IntellectMoney ботом через форму
 *
 * получение данных для береброса на смс, метод getPayParams
 * проверка данных после оплаты, метод
 */


class IntellectMoneyBot
{
	public static $lastError;

	public static function log($msg)
	{
		Tools::log('IntellectMoney: '.$msg, null, null, 'IntellectMoney');
	}

	/**
	 * @param string $msgUser
	 * @param array $params
	 * @param Sender|bool $sender
	 * @param string $msgAdmin
	 * @return array
	 */
	public static function cardError($msgUser, $params, $sender = false, $msgAdmin = '')
	{
		self::$lastError = $msgUser;

		if(!$msgAdmin)
			$msgAdmin = $msgUser;

		if($sender)
			$msgAdmin .= ', httpCode '.$sender->info['httpCode'][0];

		$msgAdmin .= Tools::arr2Str($params);

		self::log($msgAdmin);

		return [];
	}

	/**
	 * Метод для получения данных для оплаты на банковскую карту через сервис IntellectMoney
	 *
	 * работает только с физическими картами, виртуальные не поддерживаются сервисом
	 *
	 * @param array $params
	 *
	 	$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
		$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
		$params['proxyType'] = 'http';
		$params['email'] = 'ronsmals@tutanota.com';
		$params['successUrl'] = 'https://google.com';
		$params['failUrl'] = 'https://ya.ru';
		$params['cardNumber'] = '5246029706290881';
		$params['cardM'] = '04';
		$params['cardY'] = '22';
		$params['cardCvv'] = '275';
		$params['cardHolder'] = 'Ivan Ivanov'; //вводил от фонаря
		$params['amount'] = 100;
	 *
	 *
	 * возвращает
	 *array(2) {
	 	  ["url"]=> string(42) "https://payments.mtsbank.ru/mdpayacs/pareq"
		  ["postArr"]=>
		  array(3) {
			  ["PaReq"]=> string(448) "eJxVUttuwjAM/RXEBzS..."
			  ["MD"]=> string(0) ""
			  ["TermUrl"]=> string(64) "https://securepay.rsb.ru/mdpaympi/MerchantServer/msgid/108785570"
		  }
	  }
	 */
	public static function getPayParams($params, $account)
	{
		return self::cardError('error1', $account, false, 'IntellectMoneyBot : test');
		die;

		//ответ от банка не сразу приходит, приходится повторять запросы
		$tryCount = 15;
		$tryInterval = 2; //пауза между запросами на проверку результата от банка

		if(!is_array($params))
			return self::cardError('error0', $params, false, 'IntellectMoneyBot : не заданы параметры $params');

		//для тестов забил статичные данные
//		$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
//		$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
//		$params['proxyType'] = 'http';
//		$params['email'] = 'GeorgeFrank12@tutanota.com';
//		$params['successUrl'] = 'https://google.com';
//		$params['failUrl'] = 'https://ya.ru';
//		$params['cardNumber'] = '5246029706290881';
//		$params['cardM'] = '04';
//		$params['cardY'] = '22';
//		$params['cardCvv'] = '275';
//		$params['cardHolder'] = 'Ivan Ivanov';
//		$params['amount'] = 50;

		if(!preg_match('!([\d]{4})([\d]{4})([\d]{4})([\d]{4})!', $params['cardNumber'], $matches))
		{
			return self::cardError('error1', $params, false, 'IntellectMoneyBot : неверные данные params[cardNumber]');
		}

		$formatedCardNumber = $matches[1].'+'.$matches[2].'+'.$matches[3].'+'.$matches[4];
		$formatedCardHolder = urlencode(mb_strtolower($params['cardHolder']));

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPayIntellectMoney/'))
		{
			mkdir($runtimePath.'cardPayIntellectMoney/');
		}

		if(!file_exists($runtimePath.'cardPayIntellectMoney/cookie/'))
		{
			mkdir($runtimePath.'cardPayIntellectMoney/cookie/');
		}

		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPayIntellectMoney/cookie/'.md5($params['browser'].$params['proxy']).'.txt';

		$sender->pause = 0;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'] ? $params['proxyType'] : 'http';

//		0)
		/**
		 * @var IntellectAccount $account
		 */
		$url = 'https://intellectmoney.ru/ru/enter/acceptpay/userPaymentForm/?route[UserPaymentForm]=Form/Get'.
				'&FormId='.$account->form_id.'&FormType=IMAccount&AccountId='.$account->internal_account_id.'&PaymentWriter=Seller'.
			'&PaymentName=%CF%EE%EF%EE%EB%ED%E5%ED%E8%E5%20%EA%EE%F8%E5%EB%FC%EA%E0&PaymentTip=&Amount=&ButtonName=Pay'.
			'&IsCommentField=&CommentName=&CommentTip=&IsFIOField=&IsEmailField=&IsPhoneField=&SuccessUrl=';

		$sender->additionalHeaders = [
			'Host: intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = utf8_decode($sender->send($url, null, $params['proxy']));

		if(preg_match('!name="csrf_token" value="(.+?)"!iu', $content, $matches))
			$csrfToken = utf8_encode($matches[1]);
		else
			return self::cardError('error2.0', $params, $sender, 'error2.0 parse content: '.$content);

		if(preg_match('!name="orderId" value="(\d+)"!iu', $content, $matches))
			$orderId = utf8_encode($matches[1]);
		else
			return self::cardError('error2.1', $params, $sender, 'error2.1 parse content: '.$content);

		if(preg_match('!name="EshopId" value="(\d+)"!iu', $content, $matches))
			$eshopId = utf8_encode($matches[1]);
		else
			return self::cardError('error2.2', $params, $sender, 'error2.2 parse content: '.$content);

//		1)
		//TODO: заменить параметры UserField*, serviceName, для других форм будет свое
		$url = 'https://merchant.intellectmoney.ru/ru/';
		$postData = 'EshopId='.$eshopId.'&orderId='.$orderId.
			'&UserFieldName_0=%CF%E5%F0%E5%E2%EE%E4+%E2+%EA%EE%F8%E5%EB%E5%EA'.
			'&UserField_0='.$account->internal_account_id.'&UserFieldName_9=UserPaymentFormId'.
			'&UserField_9='.$account->form_id.'&FormType=IMAccount&recipientCurrency=RUB'.
			'&recipientAmount='.$params['amount'].'&serviceName=%CF%EE%EF%EE%EB%ED%E5%ED%E8%E5+%EA%EE%F8%E5%EB%FC%EA%E0'.
			'&csrf_token='.$csrfToken;

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Content-Length: '.strlen($postData),
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

//		2)
		$url = 'https://merchant.intellectmoney.ru/callback';
		$postData = 'CallBack%5Bphone%5D=true&CallBack%5Bname%5D=true&CallBack%5Bcomment%5D=false&CallBack%5Bemail%5D=false&CallBack%5Blang%5D=ru';

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

//		3)
		//TODO: заменить параметры UserField*, ServiceName, для других форм будет свое
		$url = 'https://merchant.intellectmoney.ru/ru/index.php';
		$postData = 'route%5Bmerchant%5D=Step1%2FcreateInvoice&merchant%5BInputType'.
			'%5D=&EshopId='.$eshopId.'&OrderId='.$orderId.
			'&UserFieldName_0=%D0%9F%D0%B5%D1%80%D0%B5%D0%B2%D0%BE%D0%B4+%D0%B2+%D0%BA%D0%BE%D1%88%D0%B5%D0%BB%D0%B5%D0%BA'.
			'&UserField_0='.$account->internal_account_id.'&UserFieldName_9=UserPaymentFormId&UserField_9='.$account->form_id.
			'&RecipientCurrency=RUB&UserRecipientAmountWithSpace='.formatAmount($params['amount'], 2).
			'&RecipientAmount='.$params['amount'].
			'&ServiceName=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5+%D0%BA%D0%BE%D1%88%D0%B5%D0%BB%D1%8C%D0%BA%D0%B0'.
			'&Email='.urlencode($account->email);

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

//		4)
		if(preg_match('!\[InvoiceId\]=(\d+)!iu', $content, $matches))
			$invoiceId = $matches[1];
		else
			return self::cardError('error3', $params, $sender, 'error3 content: '.$content);

		$url = 'https://merchant.intellectmoney.ru/ru/?route[merchant]=step1/invoice&merchant[InvoiceId]='.
			$invoiceId.'&merchant[ReturnFrom]=Create';

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, null, $params['proxy']);

//		5)
		$url = 'https://merchant.intellectmoney.ru/ru/index.php?route[merchant]=step2/Acquiring&merchant[InvoiceId]='.$invoiceId;
		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, null, $params['proxy']);

		//обработка платежа
		$url = 'https://merchant.intellectmoney.ru/ru/index.php';
		$postData = 'route%5Bmerchant%5D=Step2%2FAcquiring&merchant%5BInvoiceId%5D='.$invoiceId.
			'&merchant%5BAcquiringAction%5D=AcquiringPaymentCreate'.'&merchant%5BPan%5D='.
			$formatedCardNumber.'&merchant%5BExpiredMonth%5D='.$params['cardM'].'&merchant%5BExpiredYear%5D='.
			$params['cardY'].'&merchant%5BCardHolder%5D='.$formatedCardHolder.
			'&merchant%5BCvv%5D='.$params['cardCvv'];

		$content = $sender->send($url, $postData, $params['proxy']);

		if(!preg_match('!Обработка платежа, ожидайте!iu', $content, $matches))
			return self::cardError('error4', $params, $sender, 'error4 content: '.$content);

//		6)
		for($currentTry = 0; $currentTry < $tryCount; $currentTry += 1)
		{
			sleep($tryInterval);
			$url = 'https://merchant.intellectmoney.ru/ru/index.php';
			$postData = 'route%5Bmerchant%5D=step2%2FAcquiring&merchant%5BInvoiceId%5D='.$invoiceId.
				'&merchant%5BAcquiringAction%5D=AcquiringPaymentCheckState';

			$sender->additionalHeaders = [
				'Host: merchant.intellectmoney.ru',
				'User-Agent: '.$params['browser'],
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Content-Type: application/x-www-form-urlencoded',
				'X-Requested-With: XMLHttpRequest',
				'Content-Length: '.strlen($postData),
				'DNT: 1',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, $postData, $params['proxy']);

			if(preg_match('!Обработка платежа, ожидайте!iu', $content, $matches))
				continue;
			elseif(preg_match('!name="trans_id" value="(.+?)"!iu', $content, $matches))
			{
				$transId = $matches[1];
				break;
			}
			else
				return self::cardError('error5', $params, $sender, 'error5 content: '.$content);
		}

		if(!isset($transId))
			return self::cardError('error6', $params, $sender, 'error6 content: '.$content);

//		7)
		$url = 'https://securepay.rsb.ru/ecomm2/ClientHandler';
		$postData = 'trans_id='.urlencode($transId);

		$sender->additionalHeaders = [
			'Host: securepay.rsb.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

		if(preg_match('!name="xid" value="(.+?)"!iu', $content, $matches))
			$xid = $matches[1];
		else
			return self::cardError('error7.0', $params, $sender, 'error7.0 content: '.$content);

		if(preg_match('!name="merchantID" value="(.+?)"!iu', $content, $matches))
			$merchantID = $matches[1];
		else
			return self::cardError('error7.1', $params, $sender, 'error7.1 content: '.$content);

		if(preg_match('!name="digest" value="(.+?)"!iu', $content, $matches))
			$digest = $matches[1];
		else
			return self::cardError('error7.2', $params, $sender, 'error7.2 content: '.$content);

		if(preg_match('!name="purchAmount" value="(.+?)"!iu', $content, $matches))
			$purchAmount = $matches[1];
		else
			return self::cardError('error7.3', $params, $sender, 'error7.3 content: '.$content);

		if(preg_match('!name="cardType" value="(.*?)"!iu', $content, $matches))
			$cardType = $matches[1];
		else
			self::cardError('error7.4', $params, $sender, 'error7.4 content: '.$content);

		if(preg_match('!name="deviceCategory" value="(.+?)"!iu', $content, $matches))
			$deviceCategory = $matches[1];
		else
			return self::cardError('error7.5', $params, $sender, 'error7.5 content: '.$content);

		if(preg_match('!name="exponent" value="(.+?)"!iu', $content, $matches))
			$exponent = $matches[1];
		else
			return self::cardError('error7.6', $params, $sender, 'error7.6 content: '.$content);

		if(preg_match('!name="description" value="(.+?)"!iu', $content, $matches))
			$description = $matches[1];
		else
			return self::cardError('error7.7', $params, $sender, 'error7.7 content: '.$content);

		if(preg_match('!name="currency" value="(.+?)"!iu', $content, $matches))
			$currency = $matches[1];
		else
			return self::cardError('error7.8', $params, $sender, 'error7.8 content: '.$content);

		if(preg_match('!name="MD"  value="(.*?)"!iu', $content, $matches))
			$md = $matches[1];
		else
			return self::cardError('error7.9', $params, $sender, 'error7.9 content: '.$content);

		if(preg_match('!name="PAN" value="(\d+?)"!iu', $content, $matches))
			$pan = $matches[1];
		else
			return self::cardError('error7.10', $params, $sender, 'error7.10 content: '.$content);


		if(preg_match('!name="expiry" value="(\d+?)"!iu', $content, $matches))
			$expiry = $matches[1];
		else
			return self::cardError('error7.11', $params, $sender, 'error7.11 content: '.$content);

//		8)
		$url = 'https://securepay.rsb.ru/mdpaympi/MerchantServer';
		$postData = 'version=2.0&cardType='.$cardType.'&PAN='.$pan.'&expiry='.$expiry.
			'&Ecom_Payment_Card_ExpDate_Year=&Ecom_Payment_Card_ExpDate_Month='.'&deviceCategory='.
			$deviceCategory.'&purchAmount='.$purchAmount.'&exponent='.$exponent.'&description='.
			urlencode($description).'&currency='.$currency.'&merchantID='.$merchantID.'&xid='.urlencode($xid).
			'&okUrl=https%3A%2F%2Fsecurepay.rsb.ru%3A443%2Fecomm2%2FClientHandler&failUrl=https%3A%2F%2F'.
			'securepay.rsb.ru%3A443%2Fecomm2%2FClientHandler&MD='.urlencode($md).'&recurFreq=&recurEnd=&digest='.
			urlencode($digest);

		$sender->additionalHeaders = [
			'Host: securepay.rsb.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

		if(preg_match('!name="mdErrorMsg" value="(.+?)"!iu', $content, $matches))
			return self::cardError('error8.0', $params, $sender, 'error8.0 : '.$content);

		if(preg_match('!method="POST" action="(.+?)"!iu', $content, $matches))
			$action = $matches[1];
		else
			return self::cardError('error8.1', $params, $sender, 'error8.1 : '.$content);

		if(preg_match('!name="PaReq" value="(.+?)"!iu', $content, $matches))
			$paReq = $matches[1];
		else
			return self::cardError('error8.2', $params, $sender, 'error8.2 : '.$content);

		if(preg_match('!name="MD" value="(.*?)"!iu', $content, $matches))
			$md = $matches[1];
		else
			return self::cardError('error8.3', $params, $sender, 'error8.3 : '.$content);

		if(preg_match('!name="TermUrl" value="(.+?)"!iu', $content, $matches))
			$termUrl = $matches[1];
		else
			return self::cardError('error8.4', $params, $sender, 'error8.4 : '.$content);

		$result = [
			'url' => $action,
			'postArr' => [
				'PaReq' => $paReq,
				'MD' => $md,
				'TermUrl' => $termUrl,
			]
		];

		return $result;

	}

	/**
	 * метод для проверки платежа IntellectMoney
	 *
	 	$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
		$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
		$params['proxyType'] = 'http';
		$params['termUrl'] = 'https://securepay.rsb.ru/mdpaympi/MerchantServer/msgid/105622617';
		$params['PaRes']
		$params['MD']
	 *
	 *
	 * @param array $params
	 *
	 * @return array|bool
	 */
	public function checkPayment($params = [])
	{

		//попытки на проверку платежа
		$tryCount = 10;
		//пауза между запросами на проверку результата от банка
		$tryInterval = 2;

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPayIntellectMoney/'))
		{
			mkdir($runtimePath.'cardPayIntellectMoney/');
		}

		if(!file_exists($runtimePath.'cardPayIntellectMoney/cookie/'))
		{
			mkdir($runtimePath.'cardPayIntellectMoney/cookie/');
		}

		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPayIntellectMoney/cookie/'.md5($params['browser'].$params['proxy']).'.txt';

		$sender->pause = 0;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		$postArr = http_build_query([
			'PaRes' => $params['PaRes'],
			'MD' => $params['MD'],
		]);

		$sender->additionalHeaders = [
			'Host: securepay.rsb.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($params['termUrl'], $postArr, $params['proxy']);

		if(!preg_match_all('!name="(.+?)" value="(.*?)"!', $content, $matches))
			self::cardError('error0 check', $params, $sender, 'error0 check : '.$content);


//		2)
		$url = 'https://securepay.rsb.ru/ecomm2/ClientHandler';
		$formParams = [];

		foreach($matches[1] as $key=>$param)
		{
			$formParams[$param] = $matches[2][$key];
		}

		$sender->additionalHeaders = [
			'Host: securepay.rsb.ru',
			'User-Agent: '.$params['browser'],
			'Content-Type: application/x-www-form-urlencoded',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: : '.$params['termUrl'],
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$sendParams = [
			'_charset_' => $formParams['_charset_'],
			'version' => $formParams['version'],
			'merchantID' => $formParams['merchantID'],
			'xid' => $formParams['xid'],
			'mdStatus' => $formParams['mdStatus'],
			'mdErrorMsg' => $formParams['mdErrorMsg'],
			'txstatus' => $formParams['txstatus'],
			'iReqCode' => $formParams['iReqCode'],
			'iReqDetail' => $formParams['iReqDetail'],
			'vendorCode' => $formParams['vendorCode'],
			'eci' => $formParams['eci'],
			'cavv' => $formParams['cavv'],
			'cavvAlgorithm' => $formParams['cavvAlgorithm'],
			'MD' => $formParams['MD'],
			'PAResVerified' => $formParams['PAResVerified'],
			'PAResSyntaxOK' => $formParams['PAResSyntaxOK'],
			'digest' => $formParams['digest'],
			'sID' => $formParams['sID'],
		];

		$content = $sender->send($url, http_build_query($sendParams), $params['proxy']);

		if(preg_match('!>error:(.+?)<!', $content, $matches))
			return self::cardError('error1.0 check', $params, $sender, 'error1.0 check : '.$matches[1]);
		elseif(preg_match('!name="error" value="(.+?)"!', $content, $matches))
			return self::cardError('error1.1 check', $params, $sender, 'error1.1 check : '.$matches[1]);

		if(preg_match('!name="trans_id" value="(.+?)"!', $content, $matches))
			$transId = $matches[1];
		else
			return self::cardError('error2 check', $params, $sender, 'error2 check : '.$content);

		if(preg_match('!name="Ucaf_Cardholder_Confirm" value="(.+?)"!', $content, $matches))
			$ucafCardholderConfirm = $matches[1];
		else
			return self::cardError('error3 check', $params, $sender, 'error3 check : '.$content);

//		3)
		$url = 'https://ext.intellectmoney.ru/Gateway/RsBank';

		$postData = [
			'trans_id' => $transId,
			'Ucaf_Cardholder_Confirm' => $ucafCardholderConfirm,
		];

		$sender->additionalHeaders = [
			'Host: ext.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://securepay.rsb.ru/ecomm2/ClientHandler',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];
		//<script>window.location="https://merchant.intellectmoney.ru/ru/?route[merchant]=step2/Acquiring&merchant[InvoiceId]=3540515662&merchant[AcquiringAction]=AcquiringPaymentFrom3DS&MerchantVersion=new"</script>
		$content = $sender->send($url, http_build_query($postData), $params['proxy']);

		//location="https://merchant.intellectmoney.ru/ru/?route[merchant]=step2/Acquiring&merchant[InvoiceId]=3540515662&merchant[AcquiringAction]=AcquiringPaymentFrom3DS&MerchantVersion=new"
		if(preg_match('!location="(.+?)"!', $content, $matches))
			$location = $matches[1];
		else
			return self::cardError('error4 check', $params, $sender, 'error4 check : '.$content);

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://ext.intellectmoney.ru/Gateway/RsBank',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($location, false, $params['proxy']);

		if(preg_match('!name="route\[merchant\]" value="(.+?)"!', $content, $matches))
			$routeMerchant = $matches[1];
		else
			return self::cardError('error5.0 check', $params, $sender, 'error5.0 check : '.$content);

		if(preg_match('!name="merchant\[InvoiceId\]" value="(.+?)"!', $content, $matches))
			$merchantInvoiceId = $matches[1];
		else
			return self::cardError('error5.1 check', $params, $sender, 'error5.1 check : '.$content);

		if(preg_match('!name="merchant\[AcquiringAction\]" value="(.+?)"!', $content, $matches))
			$merchantAcquiringAction = $matches[1];
		else
			return self::cardError('error5.2 check', $params, $sender, 'error5.2 check : '.$content);

		if(preg_match('!name="merchant\[AcquiringPaymentProcessing\]" value="(.+?)"!', $content, $matches))
			$merchantAcquiringPaymentProcessing = $matches[1];
		else
			return self::cardError('error5.3 check', $params, $sender, 'error5.3 check : '.$content);

		if(preg_match('!name="csrf_token" value="(.+?)"!', $content, $matches))
			$csrfToken = $matches[1];
		else
			return self::cardError('error5.4 check', $params, $sender, 'error5.4 check : '.$content);

		$url = 'https://merchant.intellectmoney.ru/ru/index.php?route%5Bmerchant%5D='.urlencode($routeMerchant).
			'&merchant%5BInvoiceId%5D='.$merchantInvoiceId.'&merchant%5BAcquiringAction%5D='.urlencode($merchantAcquiringAction).
			'&merchant%5BAcquiringPaymentProcessing%5D='.$merchantAcquiringPaymentProcessing.'&csrf_token='.$csrfToken;

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: https://merchant.intellectmoney.ru/',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, false, $params['proxy']);

		if(preg_match('!href="(.+?)"!', $content, $matches))
			$urlParams = $matches[1];
		else
			return self::cardError('error6 check', $params, $sender, 'error6 check : '.$content);

		$url = 'https://merchant.intellectmoney.ru/ru/index.php'.$urlParams;

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: https://merchant.intellectmoney.ru/',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];


		for($i = 0; $i < $tryCount; $i++)
		{
			sleep($tryInterval);
			$content = $sender->send($url, false, $params['proxy']);

			if(preg_match('!Перевод выполнен успешно!', $content, $matches))
				return true;
			elseif(preg_match('!Обработка платежа, ожидайте!', $content, $matches))
				continue;
			else
				return self::cardError('error7 check', $params, $sender, 'error7 check : '.$content);
		}

		return self::cardError('error8 check', $params, $sender, 'error8 check : '.$content);

	}

	/**
	 * Оплата со счета IntellectMoney
	 *
	 * переводы во внутренней системе intellect money
	 * делаем перевод с обычного акка на наш магазин,
	 * расчет идет уже не через карту, а через баланс внутренний
	 *
	 * @return array(2) { ["status"]=> string(7) "success" ["senderBalance"]=> string(6) "223.88" }
	 */
	public static function internalTransfer($params)
	{
		//для тестов забил статичные данные
//		$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
//		$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
//		$params['proxyType'] = 'http';
//
//		$params['amount'] = '10.01'; // сумма с точностью 2 знака с разделителем "."
//		$params['eshopId'] = '458639'; //id нашего магазина
//		$params['eshopInn'] = '7733347366'; //id нашего магазина
//		$params['senderEmail'] = 'GeorgeFrank12@tutanota.com'; //email отправителя, должен быть зарегистрирован и иметь баланс на счету не меньше суммы перевода
//		$params['senderPass'] = 'JjlsHHFiew32k2NN_'; //pass отправителя, должен быть зарегистрирован и иметь баланс на счету не меньше суммы перевода
//		$params['senderCode'] = '1357'; //pinCode отправителя, должен быть зарегистрирован и иметь баланс на счету не меньше суммы перевода

		//ответ от банка не сразу приходит, приходится повторять запросы
		$tryCount = 15;

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
		{
			mkdir($runtimePath.'cardPay/');
		}

		if(!file_exists($runtimePath.'cardPay/cookie/'))
		{
			mkdir($runtimePath.'cardPay/cookie/');
		}

		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser'].$params['proxy']).'.txt';

		//очистить куки перед каждой заявкой
		//file_put_contents($sender->cookieFile, '');
		$sender->pause = 0;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

//		0)
		$url = 'https://intellectmoney.ru/payform.js?writer=now&serviceName=&serviceName_tip=&default_sum=&comment_tip='.
			'&successUrl=&inn='.$params['eshopInn'].'&btn_name=pay&eshopId='.$params['eshopId'];

		$sender->additionalHeaders = [
			'Host: intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, false, $params['proxy']);

		$updatedContent = str_replace('\\','', $content);

		if(preg_match('!name="orderId" value="(.+?)"!', $updatedContent, $matches) === false)
			return self::cardError('error0', $params, $sender, 'internalTransfer: error0 '.$content);

		$orderId = utf8($matches[1]);

//		1)
		$url = 'https://merchant.intellectmoney.ru/ru/';

		$postData = 'eshopId='.$params['eshopId'].'&orderId='.$orderId.'&recipientCurrency=RUB&Amount='.$params['amount'].
			'&recipientAmount='.$params['amount'].'&serviceName=+';

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send($url, $postData, $params['proxy']);

		if(!preg_match('!name="route\[merchant\]" value="(.+?)"!iu', $content, $matches))
			return self::cardError('error1', $params, $sender, 'internalTransfer: error1 '.$content);

		$routeMerchant = $matches[1];

		if(!preg_match('!name="OrderId" value="(.+?)"!iu', $content, $matches))
			return self::cardError('error2', $params, $sender, 'internalTransfer: error2 '.$content);

		$orderId = $matches[1];

		if(!preg_match('!name="UserRecipientAmountWithSpace" value="(.+?)"!iu', $content, $matches))
			return self::cardError('error3', $params, $sender, 'internalTransfer: error3 '.$content);

		$userRecipientAmountWithSpace = $matches[1];

		if(!preg_match('!name="RecipientAmount" value="(.+?)"!iu', $content, $matches))
			return self::cardError('error4', $params, $sender, 'internalTransfer: error4 '.$content);

		$recipientAmount = $matches[1];

		if(!preg_match('!name="csrf_token" value="(.+?)"!iu', $content, $matches))
			return self::cardError('error5', $params, $sender, 'internalTransfer: error5 '.$content);

		$csrfToken = $matches[1];

//		2)
		$url = 'https://merchant.intellectmoney.ru/ru/index.php';

		$route = [
			'route[merchant]' => 'PaymentWays/index',
			'merchant[EshopId]' => $params['eshopId'],
			'merchant[Amount]' => $recipientAmount,
			'merchant[Preference]' => '',
			'merchant[Frame]' => '',
		];

		$postData = http_build_query($route);
		//route%5Bmerchant%5D=PaymentWays%2Findex&merchant%5BEshopId%5D=458639&merchant%5BAmount%5D=100.01&merchant%5BPreference%5D=&merchant%5BFrame%5D=

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send($url, $postData, $params['proxy']);

//		3)
		$url = 'https://merchant.intellectmoney.ru/ru/index.php';

		$route = [
			'route[merchant]' => $routeMerchant,
			'merchant[InputType]' => '',
			'EshopId' => $params['eshopId'],
			'OrderId' => $orderId,
			'RecipientCurrency' => 'RUB',
			'UserRecipientAmountWithSpace' => $userRecipientAmountWithSpace,
			'RecipientAmount' => $recipientAmount,
			'ServiceName' => ' ',
			'Email' => $params['senderEmail'],
		];

		$postData = http_build_query($route);
		//route%5Bmerchant%5D=Step1%2FcreateInvoice&merchant%5BInputType%5D=&EshopId=458639&OrderId=2020010618065414&RecipientCurrency=RUB&UserRecipientAmountWithSpace=100.01&RecipientAmount=100.01&ServiceName=+&Email=GeorgeFrank12%40tutanota.com

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

		if(!preg_match('!href="(.+?)"!iu', $content, $matches))
			return self::cardError('error6', $params, $sender, 'internalTransfer: error6 '.$content);
//		4)
		$location = 'https://merchant.intellectmoney.ru/ru/'.$matches[1];

		parse_str($location, $getParams);

		$invoceId = $getParams["merchant"]["InvoiceId"];

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($location, false, $params['proxy']);

//		5)
		$url = 'https://merchant.intellectmoney.ru/ru/index.php';

		$postData = 'route%5Bmerchant%5D=PaymentWays%2Findex&merchant%5BInvoiceId%5D='.$invoceId.'&merchant%5BReturnFrom%5D=Create&merchant%5BFrame%5D=';
		//route%5Bmerchant%5D=PaymentWays%2Findex&merchant%5BInvoiceId%5D=3938723866&merchant%5BReturnFrom%5D=Create&merchant%5BFrame%5D=

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Content-Type: application/x-www-form-urlencoded',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

//		6)
		$url = 'https://merchant.intellectmoney.ru/ru/index.php?route[merchant]=step2/InnerPayment&merchant[InvoiceId]='.$invoceId;

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, false, $params['proxy']);

//		7)
		$url = 'https://merchant.intellectmoney.ru/ru/index.php';

		$route = [
			'merchant[twoFactorToken]' => '',
			'route[merchant]' => 'Step2/InnerPayment',
			'merchant[InvoiceId]' => $invoceId,
			'merchant[InnerPaymentAction]' => 'Authorization',
			'merchant[Login]' => $params['senderEmail'],
			'merchant[Password]' => $params['senderPass'],
		];

		$postData = http_build_query($route);
		//route%5Bmerchant%5D=Step1%2FcreateInvoice&merchant%5BInputType%5D=&EshopId=458639&OrderId=2020010618065414&RecipientCurrency=RUB&UserRecipientAmountWithSpace=100.01&RecipientAmount=100.01&ServiceName=+&Email=GeorgeFrank12%40tutanota.com

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

		if(!preg_match('!Баланс кошелька: <b>(.+?)<!iu', $content, $matches))
			return self::cardError('error7', $params, $sender, 'internalTransfer: error7 '.$content);

		//TODO: можно потом обновлять актуальный баланс отправителя
		$senderWalletBalance = $matches[1];

//		8)
		$route = [
			'route[merchant]' => 'Step2/InnerPayment',
			'merchant[InvoiceId]' => $invoceId,
			'merchant[UserToken]' => '',
			'merchant[PinCode]' => $params['senderCode'],
		];

		$postData = http_build_query($route);

		$content = $sender->send($url, $postData, $params['proxy']);

		if(preg_match('!location.href=\'/ru/enter/\'!iu', $content, $matches))
			return self::cardError('error8', $params, $sender, 'internalTransfer: error8 '.$content);
		elseif(preg_match('!Request Timeout!iu', $content, $matches))
			return self::cardError('error9', $params, $sender, 'internalTransfer: error9 '.$content);

		if(!preg_match('!href="(.+?)"!iu', $content, $matches))
			return self::cardError('error10', $params, $sender, 'internalTransfer: error10 '.$content);

//		9)
		$location = 'https://merchant.intellectmoney.ru/ru/'.$matches[1];

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];
		//Обработка платежа, ожидайте...
		$content = $sender->send($location, false, $params['proxy']);

		if(preg_match('!Успешная оплата заказа!iu', $content, $matches))
		{
			return [
				'status' => 'success',
				'senderBalance' => $senderWalletBalance, //в процессе перевода отображается сколько на балансе отправителя
			];
		}
		elseif(!preg_match('!Обработка платежа, ожидайте!iu', $content, $matches))
		{
			return self::cardError('error11', $params, $sender, 'internalTransfer: error11 '.$content);
		}
//		10)

		$url = 'https://merchant.intellectmoney.ru/ru/index.php';

		$route = [
			'route[merchant]' => 'step3/checkInvoiceState',
			'merchant[InvoiceId]' => $invoceId,
		];

		$postData = http_build_query($route);

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$status = '';

		for($i = 0; $i < $tryCount; $i++)
		{
			sleep(2);
			$content = $sender->send($url, $postData, $params['proxy']);

			if(preg_match('!Успешная оплата заказа!iu', $content, $matches))
			{
				$status = 'success';
				break;
			}
			elseif(preg_match('!Обработка платежа, ожидайте!iu', $content, $matches))
			{
				continue;
			}
			else
			{
				$status = 'error';
				break;
			}
		}

		return [
			'status' => $status,
			'senderBalance' => $senderWalletBalance, //в процессе перевода баланс отправителя
		];
	}
}