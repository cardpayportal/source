<?php
class TestPublicController extends Controller
{
	public function filters()
	{
		//чтобы работало без авторизации
		return [];
	}

	public function actionIndex()
	{

	}

	public function actionCardPayment()
	{
//		$paymentType = SimTransaction::PAYMENT_TYPE_YANDEX;
//		$paymentType = SimTransaction::PAYMENT_TYPE_MTS;
//		$paymentType = SimTransaction::PAYMENT_TYPE_TELE2;
		$paymentType = SimTransaction::PAYMENT_TYPE_A3;

		$apiKey = 'DXPRYWYKFJTBKCGG';
		$user = User::getModel(['api_key'=>$apiKey]);

		$params = ($_POST['params']) ? $_POST['params'] : $_SESSION['params'];
		$result = [];

		if($_POST['submit'])
		{
			$_SESSION['params'] = $params;

			$orderId = SimTransaction::generateOrderId(6);

			if(!$params['browser'])
				$params['browser'] = $_SERVER['HTTP_USER_AGENT'];

			$params['headers'] = [
				'Accept: '.$_SERVER['HTTP_ACCEPT'],
				'Accept-Language: '.$_SERVER['HTTP_ACCEPT_LANGUAGE'],
			];


			if($url = SimTransaction::getPayUrl($user->id, $params['amount'],
				$orderId, '', '', $paymentType, $params['phone']))
			{
				if(SimTransaction::PAYMENT_METHOD == 'form')
				{
					$result['url'] = $url;
					$result['method'] = 'get';
				}
				elseif(SimTransaction::PAYMENT_METHOD == 'bank')
				{
					$model = SimTransaction::getModel(['client_order_id'=>SimTransaction::$someData['orderId'],
						'user_id'=>$user->id]);

					$params['orderId'] = $model->order_id;
					$params['checkUrl'] = 'https://moneytransfer.life/test.php?r=testPublic/checkOrder&orderId='.$params['orderId'];
					$params['referer'] = $_SERVER['HTTP_HOST'];

					if($arr = SimTransaction::getBankUrl($params))
					{
						$result = $arr;
						$result['method'] = 'post';
					}
					else
					{
						$model->delete();
						$this->error(SimTransaction::$lastError);
					}
				}
			}
			else
				$this->error(SimTransaction::$lastError);
		}
		else
		{
			if(!$params['browser'])
				$params['browser'] = $_SERVER['HTTP_USER_AGENT'];
		}

		$this->render('cardPayment', [
			'params' => $params,
			'redirParams' => $result,
		]);
	}


	private function getPayParamsMts($params)
	{
		$payParams = [];

		return $payParams;
	}

	public function actionCheckOrder($orderId = '')
	{
		$params = $_POST;

		if(!$params)
			die('error1');

		if(!$model = SimTransaction::getModel(['order_id'=>$orderId]))
			die('orderId not found');

		if($_SESSION['params']['proxy'])
			$params['proxy'] = $_SESSION['params']['proxy'];

		if($_SESSION['params']['browser'])
			$params['browser'] = $_SESSION['params']['browser'];

		$result = $model->checkOrder($params);

		if($result['status'] == SimTransaction::STATUS_SUCCESS)
			echo 'оплачено';
		elseif($result['status'] == SimTransaction::STATUS_ERROR)
			echo 'ошибка: '.$result['msg'];
		elseif($result['status'] == SimTransaction::STATUS_WAIT)
			echo 'заявка в ожидании';
		else
		{
			prrd($result);
		}

		echo '<br> <a  href="'.url('testPublic/cardPayment').'">назад</a>';
	}


	//стата по Sim
	public function actionSimStats()
	{
		$apiKey = $_GET['apiKey'];

		if(!$user = User::getModel(['api_key'=>$apiKey]))
			die('неверный apiKey');

		$timestampStartMin = time() - 3600*24*7;

		$type = ($_GET['type']) ? $_GET['type'] : 'all'; //all,card,bank,sim,list


		$dateStart = ($_GET['dateStart']) ? $_GET['dateStart'] : date('d.m.Y 00:00');
		$dateEnd = ($_GET['dateEnd']) ? $_GET['dateEnd'] : date('d.m.Y H:i', time() + 3600*24);

		$timestampStart = @strtotime($dateStart);
		$timestampEnd = @strtotime($dateEnd);

		if(!$timestampStart or !$timestampEnd or $timestampStart >= $timestampEnd or $timestampStart < $timestampStartMin)
			die('неверно указана дата');

		$transactions = SimTransaction::getModels($timestampStart, $timestampEnd, $user->client_id, $user->id, 'in');

		$stats = [
			'countAll' => 0,
			'countSuccess' => 0,
			'countError' => 0,
			'countWait' => 0,
			'successAmount' => 0,
		];

		$cardStats = [];
		$bankStats = [];
		$phoneStats = [];
		$transList = [];
		$browserStats = [];
		$proxyStats = [];

		foreach($transactions as $model)
		{
			//test пропуск тех, которые в банк не перешли
			if(!$model['pay_params'])
				continue;

			if($model->error == 'отменен по таймауту')
				continue;

			$stats['countAll'] ++;

			if($model->status === SimTransaction::STATUS_SUCCESS)
				$stats['countSuccess']++;
			elseif($model->status === SimTransaction::STATUS_ERROR)
				$stats['countError']++;
			elseif($model->status === SimTransaction::STATUS_WAIT)
				$stats['countWait']++;

			$payParams = $model->payParams;

			if($model->status === SimTransaction::STATUS_SUCCESS)
			{
				$payStr = " (оплачено в {$model->datePayStr})";
				$stats['successAmount'] += $model->amount;
			}
			elseif($model->status === SimTransaction::STATUS_ERROR)
				$payStr = " (ошибка: {$model->error})";
			else
				$payStr = '';

			$transStr = 'orderId '.$model->client_order_id .' '.$model->status
				.' '.$model->dateAddStr.' '.$model->amountStr.' ['
				.formatCard($payParams['cardNumber']).'] ('.$payParams['phoneNumber'].')'.$payStr;

			$cardStats[formatCard($payParams['cardNumber'])][] = $transStr;
			$bankStats[substr($payParams['cardNumber'], 0, 6)][] = $transStr;
			$phoneStats[$payParams['phoneNumber']][] = $transStr;

			$transStr = 'orderId '.$model->client_order_id .' '.$model->status
				.' '.$model->dateAddStr.' '.$model->amountStr.' ['
				.formatCard($payParams['cardNumber']).'] ('.$payParams['phoneNumber'].')'.$payStr;

			$transList[] = $transStr;

			$browserStats[$payParams['browser']][] = $transStr.' proxy '.$model->proxy;
			$proxyStats[$model->proxy][] = $transStr.' browser '.$payParams['browser'];
		}

		$successPercent = 0;

		if($stats['countAll'] > 0)
			$successPercent = $stats['countSuccess'] / $stats['countAll'] * 100;

		echo "$dateStart - $dateEnd <br>";
		echo "<br> Успешных: {$stats['countSuccess']} из {$stats['countAll']} "
			." (".formatAmount($successPercent, 0)."%), на сумму: ".formatAmount($stats['successAmount'], 0)." руб"
			.", ошибка: {$stats['countError']}"
			.", ожидание{$stats['countWait']}";

		if($type == 'card' or $type == 'all')
		{
			echo "<br> ############################# КАРТЫ #######################################################<br><br>";

			foreach($cardStats as $key=>$transArr)
				echo "<br> $key <br>      ".implode("<br>      ", $transArr) . "<br><br>";
		}

		if($type == 'bank' or $type == 'all')
		{
			echo "<br> ############################# БАНКИ #######################################################<br><br>";

			foreach ($bankStats as $key => $transArr)
				echo "<br> $key <br>      " . implode("<br>      ", $transArr) . "<br><br>";
		}

		if($type == 'sim' or $type == 'all')
		{
			echo "<br> ############################# СИМКИ #######################################################<br><br>";

			foreach ($phoneStats as $key => $transArr)
				echo "<br> $key <br>      " . implode("<br>      ", $transArr) . "<br><br>";
		}

		if($type == 'list' or $type == 'all')
		{
			echo "<br> ############################# ПЛАТЕЖИ #######################################################<br><br>";

			echo "<br>".implode("<br><br>", $transList) . "<br><br>";
		}

		if($type == 'browser')
		{
			echo "<br> ############################# БРАУЗЕР #######################################################<br><br>";

			foreach($browserStats as $key=>$transArr)
				echo "<br> $key <br>      ".implode("<br>      ", $transArr) . "<br><br>";
		}

		if($type == 'proxy')
		{
			echo "<br> ############################# ПРОКСИ #######################################################<br><br>";

			foreach($proxyStats as $key=>$transArr)
				echo "<br> $key <br>      ".implode("<br>      ", $transArr) . "<br><br>";
		}
	}

	//тест селениум бота
	public function actionFingerprint()
	{
		$start = time();
		session_write_close();

		$botIds = ['yandex0042'/*, 'yandex0820', 'yandex1242'*/];
		$url = 'https://browserleaks.com/canvas';
//		$url = 'https://intoli.com/blog/making-chrome-headless-undetectable/chrome-headless-test.html';
//		$url = 'http://188.138.57.110/requestInfo.php';
//		$url = 'https://browserleaks.com/webgl';
//		$url = 'https://money.yandex.ru/phone';


		$regExp = '!<td colspan="4" id="crc"><span class="true">.+?</span>(.+?)</td>!';

		foreach($botIds as $id)
		{
			$bot = SimBot::getBot(['botId'=>$id, 'cardNumber'=>'ff', 'phoneNumber'=>'dsf',
				'paymentType'=>'yandex']);

			if(!$bot)
				die('error bot '.$id);

			$content = $bot->request($url);
			preg_match($regExp, $content, $match);
			echo $content;

//			$fingerprint = trim($match[1]);
//			echo "<br> bot $id: $fingerprint";
		}

		echo "<br> время работы ".(time() - $start).' сек';


	}

	public function actionYandex($botId)
	{
		session_write_close();
		set_time_limit(180);

		$start = time();


		$url = 'https://money.yandex.ru/phone';
//		$url = 'http://188.138.57.110/requestInfo.php';
//		$url1 = 'https://whoer.net';
//		$url = 'https://browserleaks.com/canvas';
//		$url = 'https://intoli.com/blog/making-chrome-headless-undetectable/chrome-headless-test.html';
//		$url = 'https://browserleaks.com/ip';
//		$url = 'https://browserleaks.com/javascript';
//		$url = 'https://browserleaks.com/proxy';

		if(!$bot = SimBot::getBot(['botId'=>$botId]))
			die('error');


		$content = $bot->request($url);
		$content = $bot->request($url);

		for($i=1; $i<=1; $i++)
		{
			$content = $bot->request($url);
			//sleep(rand(1, 4));
		}

		echo "\n".strlen($content);
//		echo "\n".strlen($content);

//		if(strlen($content) < 170000)
//			echo $content;

		$end = time();
		echo "\n время работы ".($end - $start).' сек';
	}


	/**
	 * @param array $params
	 *
	 * Метод для получения данных для оплаты на банковскую карту через сервис IntellectMoney
	 */
	public function actionIntellect()
	{
		$params = [];

		if($_POST['submit'])
		{
			//для тестов забил статичные данные
			$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
			$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
			$params['proxyType'] = 'http';
			$params['email'] = 'ronsmals%40tutanota.com'; //@ заменяется на %40
			$params['successUrl'] = 'https://google.com';
			$params['failUrl'] = 'https://ya.ru';
			$params['cardNumber'] = '5246029706290881';
			$params['cardMonth'] = '04';
			$params['cardYear'] = '22';
			$params['cardCvv'] = '275';
			$params['cardHolder'] = 'Ivan Ivanov';
			$params['amount'] = 100;

			//ответ от банка не сразу приходит, приходится повторять запросы
			$tryCount = 15;

			if(!preg_match('!([\d]{4})([\d]{4})([\d]{4})([\d]{4})!', $params['cardNumber'], $matches))
	//			TODO: заменить exit на self::cardError при переносе в SimTransaction
	//			return self::cardError("errorCard", $params, false, 'Intellect errorCard, content:'.$content);
			{
				exit('errorCard');
			}

			$formatedCardNumber = $matches[1].'+'.$matches[2].'+'.$matches[3].'+'.$matches[4];
			$formatedCardHolder = urlencode(mb_strtolower($params['cardHolder']));

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
			$url = 'https://intellectmoney.ru/ru/enter/acceptpay/userPaymentForm/?route[UserPaymentForm]=Form/Get'.
				'&FormId=3339&FormType=IMAccount&AccountId=1675719508&PaymentWriter=Seller'.
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

			if(!preg_match('!name="csrf_token" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error0", $params, false, 'Intellect error0, content:'.$content);
			{
				exit('error 0');
			}

			$csrfToken = utf8_encode($matches[1]);

			if(!preg_match('!name="orderId" value="(\d+)"!iu', $content, $matches))
	//			return self::cardError("error1", $params, false, 'Intellect error1, content:'.$content);
			{
				exit('error 1');
			}

			$orderId = utf8_encode($matches[1]);

			if(!preg_match('!name="EshopId" value="(\d+)"!iu', $content, $matches))
	//			return self::cardError("error2", $params, false, 'Intellect error2, content:'.$content);
			{
				exit('error 2');
			}

			$eshopId = utf8_encode($matches[1]);

	//		1)
			$url = 'https://merchant.intellectmoney.ru/ru/';
			$postData = 'EshopId='.$eshopId.'&orderId='.$orderId.'&UserFieldName_0=%CF%E5%F0%E5%E2%EE%E4+%E2+%EA%EE%F8%E5%EB%E5%EA'.'&UserField_0=1675719508&UserFieldName_9=UserPaymentFormId'.'&UserField_9=3339&FormType=IMAccount&recipientCurrency=RUB'.'&recipientAmount='.$params['amount'].'&serviceName=%CF%EE%EF%EE%EB%ED%E5%ED%E8%E5+%EA%EE%F8%E5%EB%FC%EA%E0'.'&csrf_token='.$csrfToken;

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
			$url = 'https://merchant.intellectmoney.ru/ru/index.php';
			$postData = 'route%5Bmerchant%5D=Step1%2FcreateInvoice&merchant%5BInputType'.'%5D=&EshopId='.$eshopId.'&OrderId='.$orderId.'&UserFieldName_0=%D0%9F%D0%B5%D1%80%D0%B5%D0%B2%D0%BE%D0%B4+%D0%B2+%D0%BA%D0%BE%D1%88%D0%B5%D0%BB%D0%B5%D0%BA'.'&UserField_0=1675719508&UserFieldName_9=UserPaymentFormId&UserField_9=3339'.'&RecipientCurrency=RUB&UserRecipientAmountWithSpace='.formatAmount($params['amount'], 2).'&RecipientAmount='.$params['amount'].'&ServiceName=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5+%D0%BA%D0%BE%D1%88%D0%B5%D0%BB%D1%8C%D0%BA%D0%B0'.'&Email='.$params['email'];

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
			if(!preg_match('!\[InvoiceId\]=(\d+)!iu', $content, $matches))
	//			return self::cardError("error3", $params, false, 'Intellect error3, content:'.$content);
			{
				exit('error 3');
			}

			$invoiceId = $matches[1];

			$url = 'https://merchant.intellectmoney.ru/ru/?route[merchant]=step1/invoice&merchant[InvoiceId]='.$invoiceId.'&merchant[ReturnFrom]=Create';
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
			$postData = 'route%5Bmerchant%5D=Step2%2FAcquiring&merchant%5BInvoiceId%5D='.$invoiceId.'&merchant%5BAcquiringAction%5D=AcquiringPaymentCreate'.'&merchant%5BPan%5D='.$formatedCardNumber.'&merchant%5BExpiredMonth%5D='.$params['cardMonth'].'&merchant%5BExpiredYear%5D='.$params['cardYear'].'&merchant%5BCardHolder%5D='.$formatedCardHolder.'&merchant%5BCvv%5D='.$params['cardCvv'];

			$content = $sender->send($url, $postData, $params['proxy']);

			if(!preg_match('!Обработка платежа, ожидайте!iu', $content, $matches))
	//			return self::cardError("error4", $params, false, 'Intellect error4, content:'.$content);
			{
				exit('error4');
			}

	//		6)
			for($currentTry = 0; $currentTry < $tryCount; $currentTry += 1)
			{
				sleep(2);
				$url = 'https://merchant.intellectmoney.ru/ru/index.php';
				$postData = 'route%5Bmerchant%5D=step2%2FAcquiring&merchant%5BInvoiceId%5D='.$invoiceId.'&merchant%5BAcquiringAction%5D=AcquiringPaymentCheckState';

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
				{
					continue;
				}
				elseif(preg_match('!name="trans_id" value="(.+?)"!iu', $content, $matches))
				{
					$transId = $matches[1];
					break;
				}
				else
				{
	//				return self::cardError("error wait", $params, false, 'Intellect errorWait, content:'.$content);
					exit('error wait');
				}

			}

			if(!isset($transId))
	//			return self::cardError("error5", $params, false, 'Intellect error5, content:'.$content);
			{
				exit('error5');
			}

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

			if(!preg_match('!name="xid" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error6", $params, false, 'Intellect error6, content:'.$content);
			{
				exit('error6');
			}

			$xid = $matches[1];

//			var_dump('$xid='.$xid);

			if(!preg_match('!name="merchantID" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error7", $params, false, 'Intellect error7, content:'.$content);
			{
				exit('error7');
			}

			$merchantID = $matches[1];

//			var_dump('$merchantID='.$merchantID);

			if(!preg_match('!name="digest" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error8", $params, false, 'Intellect error8, content:'.$content);
			{
				exit('error8');
			}

			$digest = $matches[1];

//			var_dump('$digest='.$digest);

			if(!preg_match('!name="purchAmount" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error9", $params, false, 'Intellect error9, content:'.$content);
			{
				exit('error9');
			}

			$purchAmount = $matches[1];

			if(!preg_match('!name="cardType" value="(.*?)"!iu', $content, $matches))
	//			return self::cardError("error10", $params, false, 'Intellect error10, content:'.$content);
			{
				exit('error10');
			}

			$cardType = $matches[1];

			if(!preg_match('!name="deviceCategory" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error11", $params, false, 'Intellect error11, content:'.$content);
			{
				exit('error11');
			}

			$deviceCategory = $matches[1];

			if(!preg_match('!name="exponent" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error12", $params, false, 'Intellect error12, content:'.$content);
			{
				exit('error12');
			}

			$exponent = $matches[1];

			if(!preg_match('!name="description" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error13", $params, false, 'Intellect error13, content:'.$content);
			{
				exit('error13');
			}

			$description = $matches[1];

			if(!preg_match('!name="currency" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error14", $params, false, 'Intellect error14, content:'.$content);
			{
				exit('error14');
			}

			$currency = $matches[1];

			if(!preg_match('!name="MD"  value="(.*?)"!iu', $content, $matches))
	//			return self::cardError("error15", $params, false, 'Intellect error15, content:'.$content);
			{
				exit('error15');
			}

			$md = $matches[1];

			if(!preg_match('!name="PAN" value="(\d+?)"!iu', $content, $matches))
	//			return self::cardError("error16", $params, false, 'Intellect error16, content:'.$content);
			{
				exit('error16');
			}

			$pan = $matches[1];


			if(!preg_match('!name="expiry" value="(\d+?)"!iu', $content, $matches))
	//			return self::cardError("error17", $params, false, 'Intellect error17, content:'.$content);
			{
				exit('error17');
			}

			$expiry = $matches[1];

	//		8)
			$url = 'https://securepay.rsb.ru/mdpaympi/MerchantServer';
			$postData = 'version=2.0&cardType='.$cardType.'&PAN='.$pan.'&expiry='.$expiry.'&Ecom_Payment_Card_ExpDate_Year=&Ecom_Payment_Card_ExpDate_Month='.'&deviceCategory='.$deviceCategory.'&purchAmount='.$purchAmount.'&exponent='.$exponent.'&description='.urlencode($description).'&currency='.$currency.'&merchantID='.$merchantID.'&xid='.urlencode($xid).'&okUrl=https%3A%2F%2Fsecurepay.rsb.ru%3A443%2Fecomm2%2FClientHandler&failUrl=https%3A%2F%2Fsecurepay.rsb.ru%3A443%2Fecomm2%2FClientHandler&MD='.urlencode($md).'&recurFreq=&recurEnd=&digest='.urlencode($digest);

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
	//			return self::cardError("error18", $params, false, 'Intellect error18, content:'.$matches[1]);
			{
				exit('error18: '.$matches[1]);
			}

			//https://payments.mtsbank.ru/mdpayacs/pareq
			if(!preg_match('!method="POST" action="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error19", $params, false, 'Intellect error19, content:'.$content);
			{
				exit('error19');
			}

			$action = $matches[1];

			if(!preg_match('!name="PaReq" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error20", $params, false, 'Intellect error20, content:'.$content);
			{
				exit('error20');
			}

			$paReq = $matches[1];

			if(!preg_match('!name="MD" value="(.*?)"!iu', $content, $matches))
	//			return self::cardError("error21", $params, false, 'Intellect error21, content:'.$content);
			{
				exit('error21');
			}

			$md = $matches[1];

			if(!preg_match('!name="TermUrl" value="(.+?)"!iu', $content, $matches))
	//			return self::cardError("error22", $params, false, 'Intellect error22, content:'.$content);
			{
				exit('error22');
			}

			$termUrl = $matches[1];

			if(!preg_match('!msgid/(\d+)!', $termUrl, $matches))
			{
				exit('error23');
			}

			$termUrlNumber = $matches[1];


			$result = [
				'url' => $action,
				'postArr' => [
					'PaReq' => $paReq,
					'MD' => $md,
					'TermUrl' => 'https://moneytransfer.life/index.php?r=testPublic/CheckPaymentIntellect&num='.$termUrlNumber//$termUrl,
				]
			];
			$result['method'] = 'post';

		}

		$this->render('cardPayment', [
			'params' => $params,
			'redirParams' => $result,
		]);

//		var_dump($params);die;
	}

	public function actionGetToken()
	{
		$urlGetToken = "https://api.intellectmoney.ru/personal/user/getUserToken";
//		$postData = array(
//			"Login" => "sityacc@protonmail.com",// Логин для входа в личный кабинет IntellectMoney
//			"Password" => "87HIYuoewhr^^^89kj"// Пароль для входа в личный кабинет IntellectMoney
//		);

		$postData = array(
			"Login" => "GeorgeFrank12@tutanota.com",// Логин для входа в личный кабинет IntellectMoney
			"Password" => "JjlsHHFiew32k2NN_"// Пароль для входа в личный кабинет IntellectMoney
		);

		$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
		$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
		$params['proxyType'] = 'http';

		set_time_limit(120);

		$sender = new Sender();
		$sender->useCookie = false;

		$sender->pause = 0;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		$sender->additionalHeaders = [
			'Host: api.intellectmoney.ru',
			'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
			'User-Agent: '.$params['browser'],
		];

		$requestResult = $sender->send($urlGetToken, http_build_query($postData), $params['proxy']);
		$result = simplexml_load_string($requestResult);
		$userToken = $result->Result->UserToken;

		var_dump($result);die;
	}

	public function actionIntellectForm()
	{
		print_r('<iframe frameborder="0" allowtransparency="true" scrolling="no" src="https://intellectmoney.ru/ru/enter/acceptpay/userPaymentForm/?route[UserPaymentForm]=Form/Get&FormId=3339&FormType=IMAccount&AccountId=1675719508&PaymentWriter=Seller&PaymentName=%CF%EE%EF%EE%EB%ED%E5%ED%E8%E5%20%EA%EE%F8%E5%EB%FC%EA%E0&PaymentTip=&Amount=&ButtonName=Pay&IsCommentField=&CommentName=&CommentTip=&IsFIOField=&IsEmailField=&IsPhoneField=&SuccessUrl=" width="460" height="162"></iframe>');die;
	}


	public function actionIntellectFormShop()
	{
		print_r('<script async type="text/javascript">
          document.write(\'<script type="text/javascript" src="https://intellectmoney.ru/payform.js?writer=now&serviceName=&serviceName_tip=&default_sum=&comment_tip=&successUrl=&inn=7733347366&btn_name=pay&eshopId=458639"><\/script>\');
</script>');die;
	}


	//метод для проверки платежа IntellectMoney
	public function actionCheckPaymentIntellect($params = [])
	{
//		$referer = getenv("HTTP_REFERER");

//		var_dump($referer);die;

		//для тестов забил статичные данные
		$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
		$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
		$params['proxyType'] = 'http';

		parse_str($_SERVER['REQUEST_URI'], $getParams);
		$params['termUrl'] = 'https://securepay.rsb.ru/mdpaympi/MerchantServer/msgid/'.$getParams['num'];

		var_dump($params['termUrl']);

		$result = [
			'status' => '',
			'msg' => '',	//если ошибка
		];

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


		//TODO: заменить после тестов на переменные из файла или бд
		$rawRequest = file_get_contents('php://input');
//		$params = $_POST;
//
//		$postArr = [
//			'PaRes' => $params['PaRes'],
//			'MD' => $params['MD'],
//		];

//		var_dump($postArr);die;

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

		$content = $sender->send($params['termUrl'], $rawRequest, $params['proxy']);

		if(!preg_match_all('!name="(.+?)" value="(.*?)"!', $content, $matches))
		{
			var_dump($content);
			exit('error 1');
		}

//		var_dump($matches);die;

		//2
		$url = 'https://securepay.rsb.ru/ecomm2/ClientHandler';

//		_charset_	UTF-8
//		version	2.0
//		merchantID	9295351704
//		xid	KRe9p36YZXCF1iLonIY6Pxnv514=
//		mdStatus	1
//		mdErrorMsg	Authenticated
//		txstatus	Y
//		iReqCode
//		iReqDetail
//		vendorCode
//		eci	02
//		cavv	jHh+kY1bxDZyCREAW8A+AGAAAAA=
//		cavvAlgorithm	3
//		MD
//		PAResVerified	true
//		PAResSyntaxOK	true
//		digest	5BOsTjAjbbZnn3uqspaYxn2UJDQ=
//		sID	2


		$formParams = [];

		foreach($matches[1] as $key=>$param)
		{
			$formParams[$param] = $matches[2][$key];
		}

		//_charset_=UTF-8&version=2.0&merchantID=9295351704&xid=KRe9p36YZXCF1iLonIY6Pxnv514%3D&mdStatus=1&mdErrorMsg=Authenticated&txstatus=Y&iReqCode=&iReqDetail=&vendorCode=&eci=02&cavv=jHh%2BkY1bxDZyCREAW8A%2BAGAAAAA%3D&cavvAlgorithm=3&MD=&PAResVerified=true&PAResSyntaxOK=true&digest=5BOsTjAjbbZnn3uqspaYxn2UJDQ%3D&sID=2

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

		/**
		_charset_	UTF-8
		version	2.0
		merchantID	9295351704
		xid	MwTz8UcBS1NQLepuGaDa6LUGJmc=
		mdStatus	1
		mdErrorMsg	Authenticated
		txstatus	Y
		iReqCode
		iReqDetail
		vendorCode
		eci	02
		cavv	jHh+kY1bxDZyCREAYKrWBVYAAAA=
		cavvAlgorithm	3
		MD
		PAResVerified	true
		PAResSyntaxOK	true
		digest	i1NFn+jb1CM+m0UD+NOFT9gImCs=
		sID	2
		 */

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
		{
			var_dump($matches[1]);
			exit('error 2.0');
		}
		elseif(preg_match('!name="error" value="(.+?)"!', $content, $matches))
		{
			var_dump('error: '.$matches[1]);
			exit('error 2.1');
		}

		if(!preg_match('!name="trans_id" value="(.+?)"!', $content, $matches))
		{
			var_dump($content);
			exit('error 2');
		}

		$transId = $matches[1];

		if(!preg_match('!name="Ucaf_Cardholder_Confirm" value="(.+?)"!', $content, $matches))
		{
			var_dump($content);
			exit('error 3');
		}

		$ucafCardholderConfirm = $matches[1];

		//3
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
		if(!preg_match('!location="(.+?)"!', $content, $matches))
		{

			var_dump('postData: '.http_build_query($postData));
			var_dump('headers: '.arr2str($sender->additionalHeaders));
			var_dump('content: '.$content);
			exit('error 4');
		}

		$location = $matches[1];

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

		/**
		 *
			<body OnLoad="document.forms[0].submit();">
			<form action="index.php" method="get" target="_parent">
			<input type="hidden" name="route[merchant]" value="step2/Acquiring">
			<input type="hidden" name="merchant[InvoiceId]" value="3540515662">
			<input type="hidden" name="merchant[AcquiringAction]" value="AcquiringPaymentCheckState">
			<input data-route="param" type="hidden" name="merchant[AcquiringPaymentProcessing]" value="1">
			<input type="hidden" name="csrf_token" value="efe560c6340a8a2c3cfc74127f199be5"></form>
			</body>
		 */

		$content = $sender->send($location, false, $params['proxy']);

		//
		if(!preg_match('!name="route\[merchant\]" value="(.+?)"!', $content, $matches))
		{
			var_dump($content);
			exit('error 5');
		}
		$routeMerchant = $matches[1];

		if(!preg_match('!name="merchant\[InvoiceId\]" value="(.+?)"!', $content, $matches))
			exit('error 6');
		$merchantInvoiceId = $matches[1];

		if(!preg_match('!name="merchant\[AcquiringAction\]" value="(.+?)"!', $content, $matches))
			exit('error 7');
		$merchantAcquiringAction = $matches[1];

		if(!preg_match('!name="merchant\[AcquiringPaymentProcessing\]" value="(.+?)"!', $content, $matches))
			exit('error 8');
		$merchantAcquiringPaymentProcessing = $matches[1];

		if(!preg_match('!name="csrf_token" value="(.+?)"!', $content, $matches))
			exit('error 9');
		$csrfToken = $matches[1];

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

		//ответ: <script>window.location.href="?route[merchant]=step3/CheckInvoiceState&merchant[InvoiceId]=3540515662"</script>
		$content = $sender->send($url, false, $params['proxy']);

		if(!preg_match('!href="(.+?)"!', $content, $matches))
		{
			var_dump($content);
			exit('error 10');
		}

		$urlParams = $matches[1];

		$url = 'https://merchant.intellectmoney.ru/ru/index.php'.$urlParams;

		//final part

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

		//попытки на проверку платежа
		$tryCount = 10;


		for($i = 0; $i < $tryCount; $i++)
		{
			sleep(2);
			$content = $sender->send($url, false, $params['proxy']);

			if(preg_match('!Перевод выполнен успешно!', $content, $matches))
				exit('Success payment');
			elseif(preg_match('!Обработка платежа, ожидайте!', $content, $matches))
			{
				continue;
			}
			else
			{
				var_dump($content);
				exit('Finish error');
			}
		}

		exit($matches[1]);

	}

	public function actionMerchantIntellectPayment()
	{
		//для тестов забил статичные данные
		$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
		$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
		$params['proxyType'] = 'http';
		$params['email'] = 'ronsmals%40tutanota.com'; //@ заменяется на %40
		$params['successUrl'] = 'https://google.com';
		$params['failUrl'] = 'https://ya.ru';
		$params['cardNumber'] = '5246029706290881';
		$params['cardMonth'] = '04';
		$params['cardYear'] = '22';
		$params['cardCvv'] = '275';
		$params['cardHolder'] = 'Ivan Ivanov';
		$params['amount'] = 100;

		//ответ от банка не сразу приходит, приходится повторять запросы
		$tryCount = 15;

		if(!preg_match('!([\d]{4})([\d]{4})([\d]{4})([\d]{4})!', $params['cardNumber'], $matches))
			//			TODO: заменить exit на self::cardError при переносе в SimTransaction
			//			return self::cardError("errorCard", $params, false, 'Intellect errorCard, content:'.$content);
		{
			exit('errorCard');
		}

		$formatedCardNumber = $matches[1].'+'.$matches[2].'+'.$matches[3].'+'.$matches[4];
		$formatedCardHolder = urlencode(mb_strtolower($params['cardHolder']));

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
		$url = 'https://eshop.intellectmoney.ru/ru/demo/paymentsold/';

		$sender->additionalHeaders = [
			'Host: eshop.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, false, $params['proxy']);

//		prrd($content);

		if(!preg_match('!"csrf_token" value="(.+?)"!', $content, $matches))
			exit('error1 ');

		$csrfToken = $matches[1];

//		prrd($csrfToken);


		$url = 'https://eshop.intellectmoney.ru/common/firstEntry.php';

		$sender->additionalHeaders = [
			'Host: eshop.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: https://eshop.intellectmoney.ru/',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'TE: Trailers'
		];

		//ответ bool, пока не понял для чего
		$content = $sender->send($url, false, $params['proxy']);


		$url = 'https://merchant.intellectmoney.ru/ru/';

//		$url = 'https://intellectmoney.ru/ru/enter/acceptpay/userPaymentForm/?route[UserPaymentForm]=Form/Get'.
//			'&FormId=3339&FormType=IMAccount&AccountId=1675719508&PaymentWriter=Seller'.
//			'&PaymentName=%CF%EE%EF%EE%EB%ED%E5%ED%E8%E5%20%EA%EE%F8%E5%EB%FC%EA%E0&PaymentTip=&Amount=&ButtonName=Pay'.
//			'&IsCommentField=&CommentName=&CommentTip=&IsFIOField=&IsEmailField=&IsPhoneField=&SuccessUrl=';

		/**
		 * <iframe frameborder="0" allowtransparency="true" scrolling="no"
		 * src="https://intellectmoney.ru/ru/enter/acceptpay/userPaymentForm/?route[UserPaymentForm]=Form/Get&FormId=3339&FormType=IMAccount&AccountId=1675719508&PaymentWriter=Seller&PaymentName=%CF%EE%EF%EE%EB%ED%E5%ED%E8%E5%20%EA%EE%F8%E5%EB%FC%EA%E0&PaymentTip=&Amount=&ButtonName=Pay&IsCommentField=&CommentName=&CommentTip=&IsFIOField=&IsEmailField=&IsPhoneField=&SuccessUrl=" width="460" height="162"></iframe>
		 */


		$postParams = 'email=sityacc%40protonmail.com&recipientAmount=10&payway=2&email=vashmail%40gmail.com&'.
			'recipientAmount=10&eshopId=452636&orderId=order_6072042&'.
			'serviceName=%C4%E5%EC%EE%ED%F1%F2%F0%E0%F6%E8%FF+%EF%F0%EE%F6%E5%F1%F1%E0+%EE%EF%EB%E0%F2%FB'.
			'&recipientCurrency=RUB&successUrl=&failUrl=&preference=bankcard&directinvoice='.
			'&userField_1=value_1&userField_2=value_2&csrf_token='.$csrfToken;

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'DNT: 1',
			'Referer: https://eshop.intellectmoney.ru/',
			'Content-Type: application/x-www-form-urlencoded',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postParams, $params['proxy']);

		//href="?route[merchant]=step1/invoice&merchant[InvoiceId]=3486714932&merchant[ReturnFrom]=Create"
		if(!preg_match('!href="(.+?)"!', $content, $matches))
			exit('error 2');

		$location = 'https://merchant.intellectmoney.ru'.$matches[1];

		parse_str($location, $getParams);

//		var_dump($getParams["merchant"]["InvoiceId"]);die;

		$url = 'https://merchant.intellectmoney.ru/ru/?route[merchant]=step2/Acquiring&merchant[InvoiceId]='. $getParams["merchant"]["InvoiceId"];

		$sender->additionalHeaders = [
			'Host: merchant.intellectmoney.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: https://merchant.intellectmoney.ru/',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'TE: Trailers'
		];

		//ответ bool, пока не понял для чего
		$content = $sender->send($url, false, $params['proxy']);

		var_dump($content);die;

	}

	/*
	 * получаем ответ от апи андрея
	 */
	public function actionConfirmPayment()
	{
//		$apiKey = $_GET['apiKey'];
//
//		if(!$user = User::getModel(['api_key'=>$apiKey]))
//			die('неверный apiKey');

		session_write_close();
		$rawRequest = file_get_contents('php://input');
		Tools::log('Confirm from andrey: '.$rawRequest, null, null, 'test');
		print_r('OK');
		die;
	}

	/**
	 * @param array $params
	 *
	 * Метод для получения данных для оплаты на банковскую карту через апи андрея
	 */
	public function actionPayCard()
	{
		$params = [];

		$params = ($_POST['params']) ? $_POST['params'] : $_SESSION['params'];
		$result = [];

		if($_POST['submit'])
		{
//			var_dump($_POST);die;
			$_SESSION['params'] = $params;

			if(!$params['browser'])
				$params['browser'] = $_SERVER['HTTP_USER_AGENT'];

			$params['headers'] = [
				'Accept: '.$_SERVER['HTTP_ACCEPT'],
				'Accept-Language: '.$_SERVER['HTTP_ACCEPT_LANGUAGE'],
			];

			//для тестов забил статичные данные
			$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
			$params['proxy'] = 'd5dUOyGOgd:pytivcev@193.164.16.160:41589';
			$params['proxyType'] = 'http';
			$params['successUrl'] = 'https://google.com';
			$params['failUrl'] = 'https://ya.ru';
			$params['cardNumber'] = '5246029706290881';
			$params['cardM'] = '04';
			$params['cardY'] = '22';
			$params['cardCvv'] = '275';
			$params['cardHolder'] = 'Ivan Ivanov';
			$params['amount'] = 100;
			$params['receiveCardNumber'] = '5101267768802631';


			$payment = new PaySol('5e0b252140aa7906d02b2628', '_brt-0zH65Gc_oqBYepgAO-XsNKf-LN1', 'BrfVkPvK42XEltyIzPMyDoK7CEeCVVc_');
			$orderResult = $payment->createOrder(
				$params['amount'],
				time(),//$orderId, пока для тестов так
				'processing',
				'https://moneytransfer.life/index.php?r=testPublic/ConfirmPayment',//$params['confirmUrl'],
				$params['successUrl'],
				$params['failUrl'],
				$params['cardNumber'],
				$params['cardM'].'/'.$params['cardY'],
				'ANONYMOUS CARD',
				$params['cardCvv'],
				$params['receiveCardNumber']
			);

			$termUrl = str_replace("http://0.0.0.0:9001", "https://senses.paymaster.name", $orderResult->acs->form->TermUrl);

			$result = [
				'url' => $orderResult->acs->action,
				'postArr' => [
					'_charset_' => $orderResult->acs->form->_charset_,
					'PaReq' => $orderResult->acs->form->PaReq,
					'MD' => $orderResult->acs->form->MD,
					'TermUrl' => $termUrl,//'https://moneytransfer.life/index.php?r=testPublic/CheckPaymentIntellect&num='.$termUrlNumber//$termUrl,
				]
			];
			$result['method'] = 'post';

		}

		$this->render('cardPayment', [
			'params' => $params,
			'redirParams' => $result,
		]);

	}

	/**
	 * Оплата со счета IntellectMoney
	 *
	 * переводы во внутренней системе intellect money
	 * делаем перевод с обычного акка на наш магазин,
	 * расчет идет уже не через карту, а через баланс внутренний
	 */
	public function actionIntellectInternalTransfer()
	{
		//для тестов забил статичные данные
		$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
		$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
		$params['proxyType'] = 'http';

		$params['amount'] = '30.01'; // сумма с точностью 2 знака с разделителем "."
		$params['eshopId'] = '458639'; //id нашего магазина
		$params['eshopInn'] = '7733347366'; //id нашего магазина
		$params['senderEmail'] = 'GeorgeFrank12@tutanota.com'; //email отправителя, должен быть зарегистрирован и иметь баланс на счету не меньше суммы перевода
		$params['senderPass'] = 'JjlsHHFiew32k2NN_'; //pass отправителя, должен быть зарегистрирован и иметь баланс на счету не меньше суммы перевода
		$params['senderCode'] = '1357'; //pinCode отправителя, должен быть зарегистрирован и иметь баланс на счету не меньше суммы перевода

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
			exit('error0');

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
			exit('error1');

		$routeMerchant = $matches[1];

		if(!preg_match('!name="OrderId" value="(.+?)"!iu', $content, $matches))
			exit('error2');

		$orderId = $matches[1];

		if(!preg_match('!name="UserRecipientAmountWithSpace" value="(.+?)"!iu', $content, $matches))
			exit('error3');

		$userRecipientAmountWithSpace = $matches[1];

		if(!preg_match('!name="RecipientAmount" value="(.+?)"!iu', $content, $matches))
			exit('error4');

		$recipientAmount = $matches[1];

		if(!preg_match('!name="csrf_token" value="(.+?)"!iu', $content, $matches))
			exit('error5');

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
			exit('error6');
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
			exit('error7');

		//TODO: можно потом обновлять актуальный баланс отправителя
		$senderWalletBalance = $matches[1];

		var_dump($senderWalletBalance);

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
			exit('error8');
		elseif(preg_match('!Request Timeout!iu', $content, $matches))
			exit('error9 Request Timeout');

		if(!preg_match('!href="(.+?)"!iu', $content, $matches))
			exit('error10');

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
			exit('Success payment');
		elseif(!preg_match('!Обработка платежа, ожидайте!iu', $content, $matches))
		{
//			var_dump($content);
			exit('error11');
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


		for($i = 0; $i < $tryCount; $i++)
		{
			sleep(2);
			$content = $sender->send($url, $postData, $params['proxy']);

			if(preg_match('!Успешная оплата заказа!iu', $content, $matches))
				exit('Success payment');
			elseif(preg_match('!Обработка платежа, ожидайте!iu', $content, $matches))
			{
				continue;
			}
			else
			{
				var_dump($content);
				exit('Finish error');
			}
		}

		var_dump($content);die;

	}

	public function actionMegafon()
	{
//		$str = '{"crypto":"OrPLyjABwTsbng6DboTLAvkFd44LSDW39a0Q+pXLLkK+iMufbZHjJtXWN2lBfHsYzT3jcQgV/+2IwjaYFjQ62G8AsSScxyvYyiTSPQHGqkI=","browser_encrypted":false,"apikey":"asdocnoj23ncosd03eunasdx8","refill_id":"79274820100","client_id":null,"action_client_id":null,"form_request_id":"9cdb1654-f3e2-4b97-a6ce-1b1da1499f89","sum":10000,"save_card":false,"user_checked_in_action":false,"bill_phone":"79274820100"}';
////
//
//		var_dump(json_decode($str, 1));die;
		//для теста берем статичные данные
		$cardYear = '2020';
		$cardMonth = '12';
		$cardNumber = '4890494707844221';
		$cvv = '305';

		$proxy = 'dpNS6XtyUo:proxmail1123123@91.107.119.79:42071';
//		$phone = '9372901797'; // 9774115613  9771915193
//			$userAgent = 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0';


		$userAgent = 'User-Agent: '.$_SERVER['HTTP_USER_AGENT'];

		$params = [];

		$amount = 100;
		$phoneNumber = '9274820100';

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'Megafon/'))
			mkdir($runtimePath.'Megafon/');

		if(!file_exists($runtimePath.'Megafon/cookie/'))
			mkdir($runtimePath.'Megafon/cookie/');

		$sender = new Sender;
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'Megafon/cookie/'.md5($cardNumber).'.txt';
		$sender->followLocation = true;


		//проверяем будет ли каптча, если да - в ответе будет ее id
		$url = 'https://moscow.megafon.ru/api/captcha/payonlineform/get/';
		$sender->additionalHeaders = [
			'Host: moscow.megafon.ru',
			$userAgent,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/json; charset=utf-8',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1'
		];

		$content = $sender->send($url, null, $proxy);
		$contentArr = json_decode($content, 1);

		$captchaCode = '';
		$captchaId = '';

		if($contentArr['captchaId'] !== 'null')
		{
			var_dump('captcha: '.$content);

			$captchaId = $contentArr['captchaId'];
//
			$captchaUrl = 'https://moscow.megafon.ru/api/captcha/payonlineform/'.$contentArr['captchaId'].'.png';
			$sender->additionalHeaders = [
				'Host: moscow.megafon.ru',
				$userAgent,
				'Accept: image/webp,*/*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://moscow.megafon.ru/pay/online_payment_credit_card/'
			];


			$captchaImageBase64 = $sender->send($captchaUrl, false, $proxy);

			//url для решения каптчи на сервисе антикаптчи
			$url = 'https://rucaptcha.com/in.php';

			$postData = [
				'method'=>'base64',
				'key'=>'9fa236677da7aef40ec2933d62305fe2',
				'body'=>base64_encode($captchaImageBase64),
				'json'=>1,
				'numeric'=>1,
			];

			$captchaResponce = $sender->send($url, http_build_query($postData), $proxy);

			$captchaResponceArr = json_decode($captchaResponce, 1);

			if(!$captchaResponceArr['status'] or !$captchaResponceArr['status'] == 1)
			{
				exit('Error solving captcha');
			}

			$requestIdCaptcha = $captchaResponceArr['request'];

			sleep(8);

			//получение разгаданной каптчи

			$url = 'https://rucaptcha.com/res.php?key='.$postData['key'].'&json=1&action=get&id='.$requestIdCaptcha;

			$captchaAnswer = @json_decode($content=$sender->send($url, null, $proxy), 1);

			if(!$captchaAnswer['status'] or $captchaAnswer['status'] != 1)
			{
				var_dump($content);
				exit('captcha not solved');
			}

			$captchaCode = $captchaAnswer['request'];

		}


		//парсим названия скриптов (с динамическими id в названии)
		$url = 'https://moscow.megafon.ru/xpayment.action';

//		$postData = 'number=9372901849&amount=100&lang=rus&__captcha%5Bcode%5D=&__captcha%5Bid%5D=&__captcha%5Bform%5D=payonlineform';

		$postArr = [
			'number' => $phoneNumber,
			'amount' => $amount,
			'lang' => 'rus',
			'__captcha[code]' => $captchaCode,
			'__captcha[id]' => $captchaId,
			'__captcha[form]' => 'payonlineform'
		];

		$postData = http_build_query($postArr);

		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: moscow.megafon.ru',
			$userAgent,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://moscow.megafon.ru/pay/online_payment_credit_card/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		//responce: {"redirect":"https:\/\/payment.megafon.ru\/vjet\/tmpl?form_request_id=9b56da2f-b3ce-421e-b76c-00c067a8fa40"}
		$content = $sender->send($url, $postData, $proxy);

		if(!$data=json_decode($content, 1) or !$data['redirect'])
		{
			print_r('error1 ');
			var_dump($content);die;
		}

		$url = $data['redirect'];
		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			$userAgent,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://moscow.megafon.ru/',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, null, $proxy);

		//нужен будет для создания crypto
		//https://payment.megafon.ru/vjet/tmpl?form_request_id=b3b89720-b7a2-4511-9266-d56d8d445e40&hash=eyJwYXJhbXMiOiIiLCJtc2lzZG4iOiIiLCJzaWduIjoiIn0=
		$userDataUrl = $sender->info["referer"][0];

		$sender->followLocation = false;

		$modContent = html_entity_decode($content);

		if(preg_match('!body data-options="(.+?)" data-localization!', $modContent, $matches))
		{
			$requestData = (json_decode($matches[1], 1));
		}
		else
			exit('error data-options, no $requestData received');


		$url = 'https://payment.megafon.ru/cryptogramma';

//		$browserParams = get_browser($userAgent);

		$postDataArr = [
			"fieldsData" => [
				"InPlat_cardNumber" => "$cardNumber",
				"InPlat_cardExpirationMonth" => "$cardMonth",
				"InPlat_cardExpirationYear" => "$cardYear",
				"InPlat_cardHolder" => "AAA BBB",
				"InPlat_cardCvv" => "$cvv",
			],

  			"userData" => [
				"screen" => [
					"availHeight" => 877,
					"availWidth" => 1395,
					"colorDepth" => 24,
					"pixelDepth" => 24,
					"height" => 900,
					"width" => 1440,
				],

				"navigator" => [
					"appName" => "Netscape",
					"userAgent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:67.0) Gecko/20100101 Firefox/67.0",
					"language" => "ru-RU",
					"platform" => "MacIntel",
					"oscpu" => "Intel Mac OS X 10.14",
					"cpuClass" => "",
					"vendor" => "",
					"vendorSub" => "",
					"product" => "Gecko",
					"productSub" => "20100101",
					"userLanguage" => "",
					"browserLanguage" => "",
					"systemLanguage" => "",
				],

				"plugins" => [],
				"timezone_offset" => -180,
				"time" => round(microtime(true)*1000),
				"url" => $userDataUrl,
			],
			  "apikey"=> $requestData['apikey'],
		];

//		var_dump(json_encode($postDataArr));die;

		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			$userAgent,
			'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:67.0) Gecko/20100101 Firefox/67.0',
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/json',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: '.$userDataUrl,
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$fullPostData = '{"data":"'.base64_encode(json_encode($postDataArr)).'"}';

		$contentArr = @json_decode($content = $sender->send($url, $fullPostData, $proxy), 1);

		if(!preg_match('!Операция выполнена успешно!', $content, $matches))
		{
			var_dump($content);
			exit('error get crypto');
		}

		$crypto = $contentArr['crypto'];

		if(!$crypto)
			exit('no crypto');

		//next step

		$url = 'https://payment.megafon.ru/vjet/cryptogramma/bs';

		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			$userAgent,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/text',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: '.$userDataUrl,
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'TE: Trailers'
		];



//		$requestData = [
//
//			"form_request_id" => "aa76034f-5ea8-4ddd-8d34-6a4841f26d1b",
//			"apikey" => "asdocnoj23ncosd03eunasdx8",
//			"template_path" => "megafon_topup",
//			"widget_url" => "https://payment.megafon.ru/vjet",
//			"api_url" => "https://api2.inplat.ru/cryptogramma",
//			"status" => NULL,
//			"payment_id" => "None",
//			"theme" => "blue",
//			"sum" => 100,
//			"limits" => [
//				[
//					"oper_type" => "bs",
//					"oper_sub_id" => 400,
//					"min" => 0.01,
//					"max" => 1000000,
//				],
//				[
//					"oper_type" => "card",
//					"oper_sub_id" => 200,
//					"min" => 0.01,
//					"max" => 1000000,
//				],
//			  	],
//			"allowed_url_def" => "*",
//			"locale" => "ru",
//			"recurrent" =>
//				  [
//					  "editable" => true,
//					  "checked" => true,
//				  ],
//			"refill_id" => "7 927 482 01 00",
//			"refill_id_not_editable" => false,
//			"client_id" => NULL,
//			"oper_types" => [ "bs" => "/local_card" ],
//			"pay_type" => "bs",
//			"google_pay_key" => "BJx7eSbZyxZt+HRPA0aazRcTjplfJ1lE8CEUVOyhpPYGXEg2zFnkS/JL9nSdM4MV9p/LIU7w+tMRjd2dcFxrR4c=",
//			"is_applepay" => true,
//			"pk" => NULL,
//			"sys_merc_id" => "15767989337445092080",
//			"sys_merc_name" => "payment.megafon.ru",
//			"case" => NULL,
//			"send_fiscal_check" => false,
//		];

		$refillId = preg_replace('![\s\|]!', '', $requestData['refill_id']);

		$postArr = [
			"crypto" => $crypto,
			"browser_encrypted" => false,
	  		"apikey" => $requestData['apikey'],
			"refill_id" => "$refillId",
			"client_id" => NULL,
	  		"action_client_id" => NULL,
	  		"form_request_id" => $requestData['form_request_id'],
			"sum" => $requestData['sum']*100,
	  		"save_card" => false,
	  		"user_checked_in_action" => false,
	  		"bill_phone" => "$refillId",
		];

		$contentArr = @json_decode($content = $sender->send($url, json_encode($postArr), $proxy), 1);

		if($contentArr['code'] !== 0 or !$contentArr['url'])
		{
			var_dump($content);
			exit('error get url form');
		}

		$sender->additionalHeaders = [
			'Host: api2.inplat.ru',
			$userAgent,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://payment.megafon.ru/',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($contentArr['url'], null, $proxy);

		//для теста будем вводить смс прямо в результатирующую форму
//		prrd($content);

		if(!preg_match('!name="live_update" action="(.+?)"!', $content, $matches))
		{
			var_dump($content);
			exit('error parse live_update');
		}

		$liveUpdate = $matches[1];

		if(!preg_match('!name="PaReq" value="(.+?)"!', $content, $matches))
		{
			var_dump($content);
			exit('error parse PaReq');
		}

		$paReq = $matches[1];
		$strOut = gzuncompress(base64_decode($paReq));
		$strOut = preg_replace('!<name>.+?</name>!', '<name>Payment</name>', $strOut);
		$updatedPareq = base64_encode(gzcompress($strOut));


		if(!preg_match('!name="MD" value="(.+?)"!', $content, $matches))
		{
			var_dump($content);
			exit('error parse MD');
		}

		$md = $matches[1];

		if(!preg_match('!name="TermUrl" value="(.+?)"!', $content, $matches))
		{
			var_dump($content);
			exit('error parse TermUrl');
		}

		$termUrl = $matches[1];

		$formArr = [
			'PaReq' => $updatedPareq, // обновленный paReq с нашим названием точки
			'MD' => $md,
			'TermUrl' => $termUrl,
		];

		$paymentForm = <<<EOD
		<!DOCTYPE html>
		<html>
		<head>
		<title>InPlat</title>
		<meta http-equiv="content-type" content="text/html; charset=UTF-8">
		</head>
		<body onload="document.forms['acs'].submit();">
		<form id="acs" name="live_update" action="$liveUpdate" method="post">
		<input type="hidden" name="PaReq" value="$updatedPareq">
		<input type="hidden" name="MD" value="$md">
		<input type="hidden" name="TermUrl" value="https://moneytransfer.life/test.php?r=testPublic/CheckPaymentMegafon&requestId={$requestData['form_request_id']}">
		</form>
		</body>
		</html>
EOD;
		print_r($paymentForm);die;

		var_dump('result arr ');
		var_dump($formArr);die;



	}

	//тут делаем передачу в банк в termUrl, но попутно можем парсить ответ
	public function actionCheckPaymentMegafon($requestId)
	{
		session_write_close();
		$rawRequest = file_get_contents('php://input');
//		toLogRuntime($rawRequest);die;
//		prrd($rawRequest);
		$referer = getenv("HTTP_REFERER");

		//так как у нас пост запрос от банка, get параметры не передаются обычным образом
		parse_str($_SERVER['REQUEST_URI'], $getParams);

		$proxy = 'dpNS6XtyUo:proxmail1123123@91.107.119.79:42071';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->useCookie = true;

		$userAgent = 'User-Agent: '.$_SERVER['HTTP_USER_AGENT'];

//		$requestId = parse_url($getParams['requestId']);
//		$termUrlHost = $termUrlArr['host'];

//		if($requestId == '')
//		{
//			toLogError('Empty requestId ');
//			die;
//		}


//		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: api2.inplat.ru',
			$userAgent,
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

		$url = 'https://api2.inplat.ru/finish?request_id='.$requestId;

		var_dump('url '.$url);

		$content = $sender->send($url, $rawRequest, $proxy);

//		var_dump('rawRequest: '.arr2str($rawRequest));
//		var_dump('request to https://api2.inplat.ru/finish?request_id= '.$content);

		$header = $sender->info['header'][0];

		if(!preg_match('!Location:\s*(.+)!i', $header, $match))
		{
			exit('error redirect');
		}
		//https://payment.megafon.ru/vjet/form/waiting?form_request_id=8d6e77ef-fb5f-48cf-bda6-9b40d12140c9&template_path=megafon_topup&locale=ru&orderId=8ed92815-10bf-4a54-8e64-ab54dddcc533&pid=634666491648927050
		$redirectUrl = trim($match[1]);

		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			$userAgent,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'TE: Trailers'
		];

		$content = $sender->send($redirectUrl, false, $proxy);

		/**
		 * array(4) {
		["code"]=>
		int(0)
		["message"]=>
		string(52) "Операция выполнена успешно. "
		["details"]=>
		array(10) {
		["status"]=>
		string(4) "auth"
		["hint"]=>
		string(0) ""
		["is_auth"]=>
		bool(false)
		["masked_pan"]=>
		string(12) "489049**4221"
		["apikey"]=>
		string(25) "asdocnoj23ncosd03eunasdx8"
		["amount"]=>
		int(10000)
		["is_aps"]=>
		bool(true)
		["is_new_card"]=>
		bool(true)
		["client_id"]=>
		int(79274820100)
		["refill_id"]=>
		int(79274820100)
		}
		["presult"]=>
		int(0)
		}
		 */

		sleep(rand(28, 35));

		$url = 'https://payment.megafon.ru/vjet/form/check';

		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			$userAgent,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'TE: Trailers'
		];

		$postData = 'form_request_id='.$requestId.'&template_path=megafon_topup';

		$content = $sender->send($url, $postData, $proxy);
		$contentArr = @json_decode($content, true);

		if(preg_match('!Операция выполнена успешно!', $contentArr['message'], $match))
		{
			var_dump($content);
			exit('Success payment');
		}
		else
		{
			var_dump($content);
			exit('Payment error');
		}

	}

}