<?php

class PayeerBot
{
	const ERROR_AUTH = 'error_auth';
	const ERROR_WITH_REQUEST = 'Ошибка запроса ';

	const TYPE_IN = 'Ввод';
	const TYPE_OUT = 'Вывод';
	const ACTION_RESEND = 'resend';
	const ACTION_CANCEL = 'cancel';

	const MIN_AMOUNT  = 1;
	const MAX_AMOUNT  = 15000;

	public static $errors;
	public static $lastError;
	public static $lastErrorCode;

	public $error = '';
	public $sender;
	public $balance = false;	//последний полученный баланс

	protected $_login;
	protected $_pass;
	public $emailPass;
	public $lastContent;    //контент полученый с посл запроса(чтобы не посылать зря)
	public $lastHttpCode;	 //последний код ответа
	public $lastHeader;	 //последние заголовки ответа
	public $timeout = 140;
	protected $_workDir;    //рабочая папка бота
	protected $_usersDir;    //папка с настройками пользователей
	protected $_userDir;    //папка с настройками пользователя
	protected $_fileConfig; //файл с настройками
	protected $_config; //переменная с текущими конфигами
	public $proxy;    //строка с прокси (123.123.123.123:8080)
	public $browser;    //строка с userAgent
	public $pauseMin = 0.1;
	public $pauseMax = 0.5;
	public $isAuth = false;
	public $signFile;
	public $scriptTemplate;
	public $scriptNewJs;

	public $mShop;
	public $mOrderid;
	public $mAmount;
	public $mCurr;
	public $mDesc;
	public $mSign;
	public $email;
	public $ps;
	public $orderId;

	public function __construct($login, $pass, $proxy = false, $browser = false, $additional = array())
	{
		return false;
		$this->_login = $login;
		$this->_pass = $pass;

		$this->_workDir = dirname(__FILE__) . '/' . __CLASS__ . '/';
		$this->scriptTemplate = dirname(__FILE__) . '/' . __CLASS__ . '/scriptTemplate';
		$this->scriptNewJs = dirname(__FILE__) . '/' . __CLASS__ . '/script.new.js';
		$this->_usersDir = $this->_workDir . 'users/';
		$this->_userDir = $this->_usersDir . $this->_login . '/';
		$this->captchaDir = $this->_workDir.'captcha/';
		$this->proxy = $proxy;
		$this->browser = $browser;
		$this->lastCaptchaCode = $additional['captchaCode'];

		$this->_initUser();

		if(!$additional['withoutAuth'])
		{
			if(!$this->_isAuth())
				$this->_auth();
			else
				$this->isAuth = true;
		}
	}

	/**
	 * проверка авторизации
	 * @return int
	 */
	protected function _isAuth()
	{
		session_write_close();

		$this->_config = $this->_cfg();

		$url = 'https://payeer.com/ru/account/';
		$this->sender->additionalHeaders = null;
		$this->sender->useCookie = false;
		$this->_setHeaders([
			'Host: payeer.com',
			'User-Agent: '.$this->browser,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		]);
		$content = $this->request($url);

		return preg_match('!Выйти!iu', $content);
	}

	/**
	 * авторизация на сайте
	 * @return bool|int
	 */
	protected function _auth()
	{
		session_write_close();

		//TODO: убрать после теста, чтобы капча зря не разгадывалась
		if($this->_login == 'P1003558061')
			$recaptchaAnswer = $this->recaptcha('recaptcha');
		else
			$recaptchaAnswer = '';

		if(!$recaptchaAnswer)
		{
			toLogError('Captcha not resolved '.$this->login());
			return false;
		}

		//TODO: убрать после теста, чтобы капча зря не разгадывалась
		if($this->_login == 'P1003558061')
			$cookieArr = $this->getPayeerCookies($recaptchaAnswer);

		if($cookieArr)
		{
			$this->writeCookieToFile($cookieArr);
			$this->isAuth = true;
			toLog('Получены cookie '.$this->_login);
		}
		else
		{
			toLogError('Не получены cookie '.$this->_login);
			$this->isAuth = false;
			return false;
		}

	}


	protected function _initUser()
	{
		clearstatcache();

		//папка аккаунта
		if (!file_exists($this->_userDir)) {
			if (!mkdir($this->_userDir))
				toLog('error create ' . basename($this->_userDir));

			chmod($this->_userDir, 0777);
		}

		//файл конфига
		$this->_fileConfig = $this->_userDir . 'config.json';

		$configTpl = array(
			'proxy' => '',
			'browser' => '',
		);

		if (!file_exists($this->_fileConfig))
		{
			if(file_put_contents($this->_fileConfig, json_encode($configTpl)) === false)
				toLog('error create ' . basename($this->_fileConfig));

			chmod($this->_fileConfig, 0777);
		}

		//куки
		$fileCookie = $this->_userDir . 'cookie.txt';

		if (!file_exists($fileCookie))
		{
			if (file_put_contents($fileCookie, '') === false)
			{
				toLog('error write ' . $fileCookie);
				return false;
			}
			chmod($fileCookie, 0777);
		}

		//файл для вычисления sign
		$this->signFile = $this->_userDir . 'signFile';

		if (!file_exists($this->signFile))
		{
			if (file_put_contents($this->signFile, '') === false)
			{
				toLog('error write ' . $this->signFile);
				return false;
			}
			chmod($this->signFile, 0777);
		}

		$config = json_decode(file_get_contents($this->_fileConfig), 1);

		if ($config['proxy'] != $this->proxy)
			$config['proxy'] = $this->proxy;

		if ($config['browser'] != $this->browser)
			$config['browser'] = $this->browser;

		if (!$config['proxy'] or !$config['browser'])
		{
			toLog('нет прокси или браузера у ' . $this->_login);
			return false;
		}

		if (file_put_contents($this->_fileConfig, json_encode($config)) === false)
		{
			toLog('error write ' . $this->_fileConfig);
			return false;
		}

		//если при создании объекта был указан прокси то слать запросы через него
		if (!$this->proxy)
			$this->proxy = $config['proxy'];

		if (!$this->browser)
			$this->browser = $config['browser'];

		$this->sender = new Sender;
		$this->sender->useCookie = false;
		$this->sender->browser = $config['browser'];
		$this->sender->cookieFile = $fileCookie;
		$this->sender->followLocation = false;
		$this->sender->timeout = $this->timeout;
	}

	/*
	 * возвращает либо true/false если удалось сохранить данные или нет
	 * возвращает весь массив значений
	 */
	protected function _cfg($key = null, $value = null)
	{
		$array = json_decode(file_get_contents($this->_fileConfig), true);

		if($key === null and $value === null)
		{
			//вернуть массив
			return $array;
		}
		elseif($key !== null and $value!== null)
		{
			//записать значение, вернуть true или false
			$array[$key] = $value;

			$this->_config = $array;

			if(file_put_contents($this->_fileConfig, json_encode($array))!==false)
				return true;
			else
				die('error write file '.$this->_fileConfig);
		}
		else
			die('config error');
	}

	public function getGoogleCaptchaKey()
	{
		return '6Lf_2Q0TAAAAABzDzxrOMAFty0K_OLFDhlu7P7in';
	}

	/**
	 * пишем в логи и переменную error ошибки чтобы не дублировать
	 */
	public function error($error)
	{
		toLogError($error);
		self::$lastError .= $error.' ';
	}

	/**
	 * Пишем новый параметр в config.json или считываем существующий
	 * @param null $param
	 * @param null $value
	 * @return bool
	 */
	public function configParam($param = null, $value = null)
	{
		$config = json_decode(file_get_contents($this->_fileConfig), 1);

		if($param !== null and $value !== null)
		{
			$config[$param] = $value;
			if(file_put_contents($this->_fileConfig, json_encode($config)) === false)
			{
				toLog('error write ' . $this->_fileConfig);
				die('error write ' . basename($this->_fileConfig));
			}
			else
				return true;
		}
		elseif($param !== null and $value === null)
		{
			return $config[$param];
		}
	}

	protected function request($strUrl, $postData = false, $proxy = false, $referrer = false, $withPause = true)
	{
		$tryCount = 3;

		if ($proxy)
			$proxyStr = $proxy;
		elseif ($this->proxy)
			$proxyStr = $this->proxy;
		else
			$proxyStr = '';

		if ($withPause) {
			usleep($this->getPause());
		}


		if (!$this->sender->additionalHeaders)
			$this->sender->additionalHeaders = array(
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.'.rand(5, 9).',*/*;q=0.'.rand(5, 8),
				'Accept: */*',
			);


//		$this->sender->additionalHeaders = array_merge($this->sender->additionalHeaders, array(
//			'Accept-Encoding: gzip, deflate, br',
//			'Accept-Language: ru-RU,ru;q=0.' . rand(6, 8) . ',en-US;q=0.' . rand(3, 5) . ',en;q=0.' . rand(2, 3),
//			'DNT: 1',
//			'Connection: keep-alive',
//		));


		//повтор запроса если ответ 0
		for ($i = 1; $i <= $tryCount; $i++)
		{
			$this->lastContent = $this->sender->send($strUrl, $postData, $proxyStr, $referrer);

			$headersArr = $this->explodeHeaders($this->sender->info['header'][0]);

			if($headersArr['Set-Cookie'])
			{
				if(preg_match('!PHPSESSID=(.+?);!iu', $headersArr['Set-Cookie'], $matches))
				{
					$this->_cfg('phpSessId', $matches[1]);
					$this->_config = $this->_cfg();
					$this->_setHeaders([
						'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					]);
				}
			}

			if(in_array($this->sender->info['httpCode'][0], array(0, 404, 502)) === false)
				break;

			sleep(rand(2, 5));
		}

		$this->lastHeader = $this->sender->info['header'][0];
		$this->lastHttpCode = $this->sender->info['httpCode'][0];

		//utf-8 неразрывный пробел
		$this->lastContent = utf8($this->lastContent);

		return $this->lastContent;
	}

	protected function getProxy()
	{
		if ($content = file_get_contents($this->_workDir . 'proxy.txt')) {
			$explode = explode(Tools::getSep($content), $content);
		} else
			$explode = array();

		if ($explode)
			return $explode[array_rand($explode)];
		else
			return '';
	}

	protected function getBrowser()
	{
		if($content = file_get_contents($this->_workDir . 'browser.txt'))
		{
			$explode = explode("\r\n", $content);
		}
		else
			$explode = array();

		if ($explode)
			return $explode[array_rand($explode)];
		else
			return '';
	}

	/*
	 * возвращает необходимую паузу между запросами для функции usleep(): 1000000 - 1сек
	 */
	protected function getPause()
	{
		$dec = 10; //точность до десятых

		$min = intval($this->pauseMin * $dec);
		$max = intval($this->pauseMax * $dec);

		return intval(rand($min, $max) / $dec * 1000000);
	}

	/**
	 * получаем массив из заголовков в строке
	 */
	public function explodeHeaders($str)
	{
		if($str)
		{
			$headersArr = explode(PHP_EOL, $str);

			foreach($headersArr as $headerStr)
			{
				if(preg_match('!(.+?):\s(.+)!iu', $headerStr, $matches))
					$arr[trim($matches[1])] = trim($matches[2]);
			}

			return $arr;
		}
		else
		{
			//$this->error('Не задан параметр explodeHeaders($str)');
		}

	}

	private function setLastError()
	{
		self::$errors = $this->getErrors();

		if(self::$errors)
		{
			self::$lastError = current(current(self::$errors));

			if(self::$lastErrorCode != self::ERROR_EXIST)
				toLogError('Model::lastError ('.__CLASS__.'): '.self::$lastError.'('.Tools::arr2Str($this).')');
		}
	}

	/*
	 * возвращает результат слияния статичных и дополнительных заголовков
	 */
	protected function _setHeaders($additionalHeaders)
	{

		$result = $this->sender->additionalHeaders;

		foreach($additionalHeaders as $additionalHeader)
		{
			if(!preg_match('!^(.+?):!iu', $additionalHeader, $matches))
				die('error parse: '.$additionalHeader);

			$headerName = $matches[1];

			$exists = false;

			if($result)
				foreach ($result as $keyStatic=>$staticHeader)
				{
					if(preg_match('!^'.$headerName.':!iu', $staticHeader))
					{
						$exists = true;
						$result[$keyStatic] = $additionalHeader;
						break;
					}
				}

			if(!$exists)
				$result[] = $additionalHeader;
		}

		$this->sender->additionalHeaders = $result;

	}


	/**
	 * очистка всех кук для выбранного аккаунта
	 */
	public static function clearCookie($email)
	{
		$dir = dirname(__FILE__).'/'.__CLASS__.'/users/';
		$files = scandir($dir);

		foreach($files as $file)
		{
			if($file=='..' or $file=='.' or !is_dir($dir.$file))
				continue;

			$cookieFile = $dir.$file.'/cookie.txt';

			if($file==$email)
			{

				if(file_put_contents($cookieFile, '')===false)
					return false;
			}
		}

		return true;
	}

	/**
	 * @return bool|mixed
	 */
	public function getMyIp()
	{
		$url = cfg('my_ip_url');

		$content = $this->request($url, false, false, false, false);

		if($this->sender->info['httpCode'][0] == 200)
			return $content;
		else
			return false;
	}

	/**
	 * @param $cookieArr
	 */
	protected function writeCookieToFile($cookieArr = [])
	{
		$string = '';
		$string .= "# Netscape HTTP Cookie File\n";
		$string .= "# http://curl.haxx.se/docs/http-cookies.html\n";
		$string .= "# This file was generated by libcurl! Edit at your own risk.\n";


		for($i = 0; $i < count($cookieArr); $i++)
		{
			$cookie = $cookieArr[$i];

			//пишем в конфиг phpsessionid
			if($cookie['name'] == 'PHPSESSID')
				$this->_cfg('phpSessId', $cookie['value']);

			if($cookie['domain'])
			{
				$cookie['access'] = ($cookie['domain'][0] == '.') ? "TRUE" : "FALSE";
			}

			$string .= ($cookie['httpOnly'] ? "#HttpOnly_" : "") .
				$cookie['domain'] . "\t" .
				$cookie['access'] . "\t" . $cookie['path'] . "\t" .
				($cookie['secure']? "TRUE" : "FALSE" ) . "\t" .
				($cookie['expiry'] ? $cookie['expiry']. "\t" : "" ) .
				$cookie['name'] . "\t" .
				$cookie['value'] . (($i == count($cookieArr)-1) ? "" : "\n");
		}

		$fileCookie = $this->_userDir . 'cookie.txt';

		$fd = fopen($fileCookie, 'w') or die("не удалось создать файл");

		if(fwrite($fd, $string) === false)
		{
			toLog('error write ' . $fileCookie);
			die('error write ' . basename($fileCookie));
		}
		fclose($fd);



	}


	/**
	 * @param $str
	 *
	 * @return array|bool
	 */
	private function parseProxyStr($str)
	{
		if(!preg_match('!(([^:]+?):([^@]+?)@|)(.+?):(\d{2,7})!', $str, $res))
		{
			$this->error('неверный формат прокси: '.$str);
			return false;
		}

		return array(
			'login'=>$res[2],
			'pass'=>$res[3],
			'ip'=>$res[4],
			'port'=>$res[5],
		);
	}

	public function recaptcha($type, $imageContent='')
	{
		$recaptchaCfg = cfg('recaptcha');
		$googleSiteKey = $this->getGoogleCaptchaKey();

		if($type == 'recaptcha')
		{
			$captchaId = Tools::anticaptcha('recaptcha', array(
				'step'=>'send',
				'googleApiKey'=>$googleSiteKey,
				'pageUrl'=>'https://payeer.com/ru/account/',
			));
		}
		elseif($type == 'image')
		{
			$captchaId = Tools::anticaptcha('image', array(
				'step'=>'send',
				'imageContent'=>$imageContent,
			));
		}

		$timeStart = time();

		if($captchaId)
		{
			sleep(10);

			$captchaCode = false;

			while(time() - $timeStart < $recaptchaCfg['maxTimeDefault'])
			{
				if($captchaCode = Tools::anticaptcha($type, array(
					'step'=>'get',
					'captchaId'=>$captchaId,
				)))
				{
					toLogRuntime($this->login.': капча распознана '.$captchaCode);
					return $captchaCode;
					//break;
				}
				elseif(Tools::$error == Tools::ANTICAPTCHA_NOT_READY)
				{
					//sleep($recaptchaCfg['sleepTime']);
				}
				else
				{
					$this->botError = $this->login.' ошибка распознавания капчи: '.Tools::$error;
					toLogError('Account::getBot(): '.$this->botError);
				}

				sleep($recaptchaCfg['sleepTime']);
			}

			if($captchaCode)
			{
				$this->botError = '';
				$this->botErrorCode = '';

				$additional['captchaCode'] = $captchaCode;

			}
			else
			{
				$this->botError = $this->login.' ошибка распознавания капчи (затрачено '.(time() - $timeStart).' сек) '.Tools::$error;
			}

		}
		else
		{
			$this->botError = $this->login.' captchaId  не получен от сервиса антикапчи ('.Tools::$error.')';
			toLogError('Account::getBot()_1: '.$this->botError);
		}
		die;
	}

	/*
	 * пишет уже отформатированную строку куки в текущий файл куков
	 * проверяет что имя этой куки еще не установлено в файле
	 * return bool
	 * ip	FALSE	/	FALSE	0	PHPSESSID
	 */
	protected function _setCookie($name, $value, $domain, $expiration = 0)
	{
		$cookieArr = explode(PHP_EOL, file_get_contents($this->_userDir . 'cookie.txt'));

		$nameExists = false;

		foreach($cookieArr as $key=>$row)
		{
			if(!trim($row))
				continue;

			$cols = explode("\t", $row);

			if(!$cols[5] or !$cols[6])
				continue;

			$cookie = array(
				'domain'=>$cols[0],
				'param1'=>'FALSE',
				'param2'=>'/',
				'param3'=>'TRUE',
				'expiration'=>$cols[4],
				'name'=>$cols[5],
				'value'=>$cols[6],
			);

			if($name == $cookie['name'])
			{
				$nameExists = true;

				$cookie['value'] = $value;
				$cookie['domain'] = $domain;
				$cookie['expiration'] = $expiration;

				$cookieArr[$key] = implode("\t", $cookie);
			}

		}

		if(!$nameExists)
			$cookieArr[] = "$domain\tFALSE\t/\tTRUE\t$expiration\t$name\t$value";

		return file_put_contents($this->_userDir . 'cookie.txt', implode(PHP_EOL, $cookieArr));
	}

	public function login()
	{
		session_write_close();
		$url = 'https://payeer.com/ru/account/';
		$this->sender->additionalHeaders = null;
		$this->sender->followLocation = false;
		$this->_setHeaders([
			'Host: payeer.com',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br'.
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'User-Agent: '.$this->browser,
		]);
		$content = $this->request($url);

		//title="Выйти">Выйти

		prrd($content);


		$cookieContent = file_get_contents($this->_userDir . 'cookie.txt');
		print_r('cookieContent после https://payeer.com/ru/account/ '.$cookieContent);

		$url = 'https://payeer.com/bitrix/components/payeer/system.auth.form/templates/index_list/ajax.php?cmd=Authorization&backurl=%252Fru%252Faccount%252F';
		$this->sender->additionalHeaders = null;
		$this->sender->followLocation = false;
		$this->_setHeaders([
			'Host: payeer.com',
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Cache-Control: max-age=0',
			'User-Agent: '.$this->browser,
		]);
		$content = $this->request($url);

		$cookieContent = file_get_contents($this->_userDir . 'cookie.txt');
		print_r('cookieContent после ajax.php '.$cookieContent);


		$contentArr = json_decode($content, 1);

		if(preg_match('!name="captcha_sid" value="(.+?)"!iu', $contentArr['main']['html'], $matches))
		{
			$captchaSid = $matches[1];
		}
		else
			prrd('error $captchaSid');

		if(preg_match('!id="sessid" value="(.+?)"!iu', $contentArr['main']['html'], $matches))
			$sessId = $matches[1];
		else
			prrd('error $sessId');

//		if(preg_match('!"sign" value="(.+?)"!iu', $contentArr['main']['html'], $matches))
//			$sign = $matches[1];
//		else
//			prrd('error $sign');

		if(preg_match('!id="id_captcha_img" src="(.+?)"!iu', $contentArr['main']['html'], $matches))
		{
			$imageUrl = $matches[1];
			$imageUrl = 'https://payeer.com'.$imageUrl;
		}
		else
			prrd('error $imageUrl');


		if(preg_match('!<script type="text\/javascript">var (.+?)<!iu', $contentArr['main']['html'], $matches))
		{
			$signContent = ' var '.$matches[1].' console.log($$$$$$); phantom.exit();';
		}
		else
			prrd('error $signContent');

		clearstatcache();
		$scriptTemplate = file_get_contents($this->scriptTemplate);
		file_put_contents($this->signFile, $scriptTemplate.$signContent);

//		$res = file_get_contents($this->signFile);
//		prrd($res);

		$sign = exec('phantomjs '.$this->signFile.' 2>&1');


		if(!strlen($sign) == 32)
		{
			toLogError('Ошибка получения sign');
			return false;
		}



		$sign = exec('phantomjs '.$this->scriptNewJs.' 2>&1');


		$this->sender->additionalHeaders = null;
		$this->sender->followLocation = false;

		$this->_setHeaders([
			'Host: payeer.com',
			'User-Agent: '.$this->browser,
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
		]);

		$imageContent = $this->request($imageUrl);

		$isImageCaptcha = false;

		if(1)//preg_match('!class="(payeer_captcha hide)"!iu', $contentArr['main']['html'], $matches))
		{
			//prrd($matches[1]);
			$isImageCaptcha = false;
			$recaptchaResponce = $this->recaptcha('recaptcha');
			if(!$recaptchaResponce)
				prrd('error $recaptchaResponce');
		}
		else
		{
			$isImageCaptcha = true;
			$imageCaptchaResponce = $this->recaptcha('image', $imageContent);
			if(!$imageCaptchaResponce)
				prrd('error $imageCaptchaResponce');
		}

		//die;

		//shell_exec('rm '.$this->_userDir . 'cookie.txt');

		//$cookieFileContent = file_put_contents($this->_userDir . 'cookie.txt');

		$url = 'https://payeer.com/bitrix/components/payeer/system.auth.form/templates/index_list/ajax.php';
		$this->sender->additionalHeaders = null;
		$this->sender->followLocation = false;

		$postData = 'captcha_sid='.$captchaSid.
			'&cmd=Authorization&sessid='.$sessId.'&sign='.$sign.'&backurl=%252Fru%252Faccount%252F'.
			'&email='.$this->_login.'&password='.$this->_pass.'&g-recaptcha-response='.$recaptchaResponce;

		if($isImageCaptcha)
			$postData = $postData.'&captcha='.$imageCaptchaResponce;
		else
			$postData = $postData.'&captcha=';

		print_r($postData);
		print_r('<br>');

		$this->_setHeaders([
			'Host: payeer.com',
			'User-Agent: '.$this->browser,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
		]);

		$content = $this->request($url, $postData);

		$cookieContent = file_get_contents($this->_userDir . 'cookie.txt');
		print_r('cookieContent после index_list/ajax '.$cookieContent);


		print_r($this->lastHttpCode);
		print_r($this->lastHeader);
		prrd($content);
	}


	/**
	 * @return bool
	 * получаем куки с авторизацией по апи
	 */
	public function getPayeerCookies($recaptchaAnswer = '')
	{
		session_write_close();

		$threadName = 'selenium';

		if(Tools::threader($threadName))
		{
			$url = 'http://94.140.125.237/selenium/index.php?key=testtest&method=GetPayeerCookies'.
				'&login='.$this->_login.
				'&pass='.$this->_pass.'&proxy='.urlencode($this->proxy).'&recaptchaAnswer='.$recaptchaAnswer;

			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: */*',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Content-Type: application/json; charset=UTF-8',
			]);

			$content = $this->request($url);

			if($this->lastHttpCode == 200)
			{
				$cookieArr = json_decode($content, true);

				if(isset($cookieArr['result']))
				{
					return $cookieArr['result'];
				}
				elseif(isset($cookieArr['error']['mgs']))
				{
					$this->error($this->_login.' '.arr2str($cookieArr['error']['mgs']));
					return false;
				}
				else
				{
					$this->error(strip_tags($content));
					return false;
				}
			}
			else
			{
				$this->error('Ошибка получения cookie по api '.$this->_login.' HttpCode = '.$this->lastHttpCode);
				return false;
			}
		}
		else
			toLog('поток уже запущен, пропускается '.$this->_login);

	}

	/**
	 * получение истории
	 *
	 */
	public function getHistory()
	{
		session_write_close();

		if($this->isAuth)
		{
			$this->_config = $this->_cfg();

			$url = 'https://payeer.com/ru/account/history/?_pjax=%23pjax-container';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/html, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/ru/account/',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-PJAX: true',
				'X-PJAX-Container: #pjax-container',
				'X-Requested-With: XMLHttpRequest',
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',

			]);
			$content = $this->request($url);

			$transactionArr = [];

			if(preg_match("!document\.title = 'История!iu", $content))
			{
				$mathcStr = '!<tr class="history-id-(\d+)">\s+<td class="tleft">(.+?)</td>'
					.'\s+<td class="dohod">\s+\+\s+([\d\.]+)\s+<i class="fa fa-rub"><i class="hide">₽</i></i>'
					.'\s+</td>\s+<td class="rashod">\s+(.+?)\s+</td>\s+<td>\s+'
					.'<span class=".+?"></span>\s+</td>\s+<td>\s+<a href="#" class="link" data-ajax=".+?" onClick!iu';

				if(preg_match_all($mathcStr, $content, $matches))
				{
					foreach($matches[1] as $key=>$transaction)
					{
						$transactionArr[$key]['id'] = $transaction;
						$transactionArr[$key]['date'] = time(str_replace(' Авг ', '.08.', $matches[2][$key]));
						$transactionArr[$key]['amount'] = $matches[3][$key]*1;
					}
				}

				return $transactionArr;
			}
			else
			{
				$this->error('Ошибка получения истории yandex '.$this->_login);
				return false;
			}
		}

	}


	/**
	 * @return bool|string
	 *
	 * получение баланса
	 */
	public function getBalance()
	{
		session_write_close();

		if($this->isAuth)
		{
			$this->_config = $this->_cfg();

			$url = 'https://payeer.com/ru/account/';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'DNT: 1',
				'Connection: keep-alive',
				'Upgrade-Insecure-Requests: 1',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
			]);

			$content = $this->request($url);

			if(!$content)
			{
				$message = ' Контент для баланса не получен '.$this->_login;
				toLogError($message);
				return false;
			}

			$matchStr = '!<span class="icon fa fa-rub"></span><span class="int">(.+?)</span><span class="pr">.(\d+)</span></li>!iu';

			if(preg_match($matchStr, $content, $matches))
			{
				$balanceRu = (str_replace(' ', '', $matches[1]).'.'.$matches[2])*1;
				return $balanceRu;
			}
			else
			{
				$message = ' Ошибка получения баланса '.$this->_login.': httpCode='.$this->sender->info['httpCode'][0];
				toLogError($message);
				return false;
			}
		}
		else
		{
			toLogError('не авторизован для получения баланса: login = '.$this->_login);
			return false;
		}
	}

	/**
	 * @param array $params
	 *
	 * @return array|bool
	 *
	 * статус транзакции
	 */
	public function getTransactionStatus($params = [])
	{
		session_write_close();

		$mHistoryId = $params['mHistoryId'];
		$mHistoryTm = $params['mHistoryTm'];
		$mCurOrderId = $params['mCurOrderId'];
		$mShopId = $params['mShopId'];

		$this->_config = $this->_cfg();
		$url = 'https://payeer.com/ajax/api/m2.php';
		$this->sender->additionalHeaders = null;
		$this->sender->useCookie = false;
		$postData = 'api%5Bid%5D='.$mShopId.'&api%5Blang%5D=ru&cmd=process&params%5B'.
			'm_historyid%5D='.$mHistoryId.'&params%5Bm_historytm%5D='.$mHistoryTm.'&'.
			'params%5Bm_curorderid%5D='.$mCurOrderId.'&params%5Blang%5D=ru';
		$this->_setHeaders([
			'Host: payeer.com',
			'User-Agent: '.$this->browser,
			'Accept: text/plain, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: https://payeer.com/merchant/?m_historyid='.$mHistoryId.'&m_historytm='.$mHistoryTm.'&m_curorderid='.$mCurOrderId.'&lang=ru',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: 168',
			'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		]);
		$content = $this->request($url, $postData);

		$status = '';

		if(!$content)
		{
			$message = ' контент статуса заявки не получен '.$this->_login;
			toLogError($message);
			self::$lastError = $message;
			return false;
		}

		if(preg_match('!<div class="info__descr gr-descr">\d+ от (.+?)</div>!iu', $content, $matches))
		{
			$time = strtotime(trim($matches[1]));
		}
		else
		{
			$message = ' платеж mHistoryId='.$mHistoryId.' еще не подтвержден1 '.$this->_login;
			toLogRuntime($message);
			return false;
		}

		if(preg_match('!Статус:</div>\s+<div class=".+?">\s+(\w+)\s+</div>!iu', $content, $matches))
		{
			$status = trim($matches[1]);
		}
		else
		{
			$message = ' платеж mHistoryId='.$mHistoryId.' еще не подтвержден2 '.$this->_login;
			toLogRuntime($message);
			return false;
		}

		if($status == 'Оплачен')
			return [
				'time' => $time,
				'status' => 'success',
			];
		else
			return false;


	}

	/**
	 * Перевод с payeer на payeer в RU
	 *
	 * входные параметры
	 * @param array $params
	 * Array
		(
			[amount] => 100 	//сумма перевода без вычита комсы
			[receiver] => P1003327009 //логин получателя
		)
	 *
	 * возвращает
	 * @return array|bool
	 * Array
		(
			[id] => 626898924 //id транзакции
			[amount] => 99.05 //сумма с вычитом комсы
		)
	 */
	public function sendQiwiMoneyRu($params = [])
	{
		session_write_close();

		if($this->isAuth)
		{
			if($params['amount'] > self::MAX_AMOUNT OR $params['amount'] < self::MIN_AMOUNT)
			{
				$message = ' неверная сумма перевода '.$params['amount'].'руб '.$this->_login;
				toLogError($message);
				self::$lastError = $message;
				return false;
			}

			if(!preg_match('!P[\d]{10}!iu', $params['receiver']))
			{
				$message = ' неверный формат логина получателя '.$params['receiver'].' login = '.$this->_login;
				toLogError($message);
				self::$lastError = $message;
				return false;
			}

			$this->_config = $this->_cfg();
			$url = 'https://payeer.com/ru/account/send/?_pjax=%23pjax-container';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;

			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/html, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/ru/account/',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-PJAX: true',
				'X-PJAX-Container: #pjax-container',
				'X-Requested-With: XMLHttpRequest',
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
			]);
			$content = $this->request($url);

			if(!$content)
			{
				$message = ' контент перевода средств не получен step1 login = '.$this->_login;
				toLogError($message);
				self::$lastError = $message;
				return false;
			}

			if(preg_match('!name="ps" value="(\d+)"!iu', $content, $matches))
			{
				$ps = trim($matches[1]);
			}
			else
			{
				$message = ' ошибка парсинга параметра ps при переводе login = '.$this->_login;
				toLogError($message);
				return false;
			}

			if(preg_match('!id="sessid" value="(.+?)"!iu', $content, $matches))
			{
				$sessId = trim($matches[1]);
			}
			else
			{
				$message = ' ошибка парсинга параметра sessId при переводе login = '.$this->_login;
				toLogError($message);
				return false;
			}
			if(preg_match('!"Комиссия: ([\d+.]+)%">Payeer!iu', $content, $matches))
			{
				$payeerFee = trim($matches[1])/100;
			}
			else
			{
				$message = ' ошибка парсинга параметра комсы при переводе login = '.$this->_login;
				toLogError($message);
				return false;
			}

			$amountReceive = $params['amount'] * (1 - $payeerFee);

			$url = 'https://payeer.com/bitrix/components/payeer/account.send/templates/list/ajax.php?action=output';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$postData = 'payout_method=1136053&sum_pay='.$params['amount'].'&curr_pay=RUB&'.
				'sum_receive='.$amountReceive.'&curr_receive=RUB&sum_receive_show=&curr_receive_show='.
				'&param_ACCOUNT_NUMBER='.$params['receiver'].'&comment=&'.
				'master_key=&block=0&ps='.$ps.'&sign=&output_type=list&'.
				'fee_0=N&sessid='.$sessId;
			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: application/json, text/javascript, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/ru/account/send/',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With: XMLHttpRequest',
				'Content-Length: '.strlen($postData),
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
			]);
			$content = $this->request($url, $postData);

			if(!$content)
			{
				$message = ' контент перевода средств не получен step 2 login = '.$this->_login;
				toLogError($message);
				self::$lastError = $message;
				return false;
			}

			$contentArr = json_decode($content, 1);

			if($contentArr['result'] !== '')
			{
				return [
					'id' => $contentArr['id'],
					'amount' => floorAmount($amountReceive, 2),
				];
			}
			else
			{
				$message = ' ошибка перевода '.arr2str($contentArr['error']).' login = '.$this->_login;
				toLogError($message);
				self::$lastError = $message;
				return false;
			}
		}
		else
		{
			toLogError('не авторизован для перевода средств: login = '.$this->_login);
			return false;
		}
	}

	/**
	 * Автоматическое создание параметров api для массовых выплат
	 *
	 * @return array|bool
	 * Array
		(
			[id] => 627163554
			[secretKey] => d9ieVFbtrlPS6iLz
		)
	 */
	public function createApiParams()
	{
		session_write_close();
		if($this->isAuth)
		{
			$timeParam = time().rand(101, 900);
			$this->_config = $this->_cfg();
			$url = 'https://payeer.com/bitrix/components/payeer/account.api/templates/.default/ajax.php?_='.$timeParam;
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;

			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/plain, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/ru/account/',
				'X-Requested-With: XMLHttpRequest',
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
			]);
			$content = $this->request($url);


			if(!$content)
			{
				$message = ' контент для создания параметров api не получен step1 login = '.$this->_login;
				toLogError($message);
				self::$lastError = $message;
				return false;
			}

			if(preg_match('!name="secret_key" value="(.+?)"!iu', $content, $matches))
			{
				$secretKey = trim($matches[1]);
			}
			else
			{
				$message = ' ошибка парсинга параметра secret_key при создании параметров api login = '.$this->_login;
				toLogError($message);
				return false;
			}

			if(preg_match('!id="sessid" value="(.+?)"!iu', $content, $matches))
			{
				$sessId = trim($matches[1]);
			}
			else
			{
				$message = ' ошибка парсинга параметра sessid при создании параметров api login = '.$this->_login;
				toLogError($message);
				return false;
			}

			$url = 'https://payeer.com/bitrix/components/payeer/account.api.add/templates/.default/ajax.php?action=add_api';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$postData = 'name=API&secret_key='.$secretKey.'&ip=*.*.*.*&master_key=&block=0&sessid='.$sessId;
			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: application/json, text/javascript, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/ru/account/',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With: XMLHttpRequest',
				'Content-Length: '.strlen($postData),
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			]);
			$content = $this->request($url, $postData);

			if(!$content)
			{
				$message = ' контент для создания параметров api не получен step2 login = '.$this->_login;
				toLogError($message);
				self::$lastError = $message;
				return false;
			}

			$contentArr = json_decode($content, 1);

			if($contentArr['id'] !== '')
			{
				return [
					'id' => $contentArr['id'],
					'secretKey' => $secretKey,
				];
			}
			else
			{
				$message = ' ошибка создания параметров api '.arr2str($contentArr['error']).' login = '.$this->_login;
				toLogError($message);
				self::$lastError = $message;
				return false;
			}
		}
		else
		{
			toLogError('не авторизован для создания параметров api: login = '.$this->_login);
			return false;
		}
	}

	/**
	 * получаем реквизиты для оплаты Yandex->Payeer
	 * @param $sum
	 *
	 * @return array|bool
	 * 	 Array
	(

	)
	 *
	 */
	public function getPayParamsYandex($sum)
	{
		session_write_close();

		//пока заморозил этот метод
		return false;
		if($this->isAuth)
		{
			if(!is_numeric($sum))
			{
				$this->error(' Проверьте сумму ');
				return false;
			}

			$this->_config = $this->_cfg();

			$url = 'https://payeer.com/ru/account/add/?_pjax=%23pjax-container';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/html, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/ru/account/',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-PJAX: true',
				'X-PJAX-Container: #pjax-container',
				'X-Requested-With: XMLHttpRequest',
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
			]);
			$content = $this->request($url);


			//попадаем на страницу пополнения

			if(!preg_match("!document.title = 'Пополнить!iu", $content))
			{
				$this->error(' Ошибка пополнения, возможно не авторизован, повторите запрос ');
				return false;
			}

			if(preg_match('!"sessid" value="(.+?)"!iu', $content, $matches))
			{
				$sessId = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга sessid при пополнении ');
				return false;
			}

			$url = 'https://payeer.com/bitrix/components/payeer/account.add/templates/.default/ajax.php?action=add';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$postData = 'sum='.$sum.'&curr=RUB&block=0&sessid='.$sessId;

			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: application/json, text/javascript, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/ru/account/add/',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With: XMLHttpRequest',
				'Content-Length: '.strlen($postData),
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'TE: Trailers',
			]);
			$content = $this->request($url, $postData);

			if(preg_match('!У нас возникли вопросы!iu', $content, $matches))
			{
				$this->error('!!! ЗАБЛОКИРОВАН !!! login = '.$this->_login);
				return false;
			}


			if(preg_match('!ORDER_ID=(.+?)"!iu', $content, $matches))
			{
				$orderId = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга orderId при пополнении ');
				return false;
			}

			$url = 'https://payeer.com/ru/account/input/process.php?ORDER_ID='.$orderId;
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/ru/account/add/',
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Upgrade-Insecure-Requests: 1',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'TE: Trailers',
			]);

			$content = $this->request($url);

			if(preg_match('!"m_shop" value="(.+?)"!iu', $content, $matches))
			{
				$mShop = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга mShop при пополнении ');
				return false;
			}

			if(preg_match('!"m_orderid" value="(.+?)"!iu', $content, $matches))
			{
				$mOrderid = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга mOrderid при пополнении ');
				return false;
			}

			if(preg_match('!"m_curr" value="(.+?)"!iu', $content, $matches))
			{
				$mCurr = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга mCurr при пополнении ');
				return false;
			}

			if(preg_match('!"m_desc" value="(.+?)"!iu', $content, $matches))
			{
				$mDesc = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга mDesc при пополнении ');
				return false;
			}

			if(preg_match('!"m_sign" value="(.+?)"!iu', $content, $matches))
			{
				$mSign = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга mSign при пополнении ');
				return false;
			}


			if(preg_match('!"m_sign" value="(.+?)"!iu', $content, $matches))
			{
				$mSign = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга mSign при пополнении ');
				return false;
			}


			if(preg_match('!"m_amount" value="(.+?)"!iu', $content, $matches))
			{
				$mAmount = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга mAmount при пополнении ');
				return false;
			}

			$url = 'https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.
				'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/ru/account/input/process.php?ORDER_ID='.$orderId,
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Upgrade-Insecure-Requests: 1',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'TE: Trailers',
			]);

			$content = $this->request($url);

			$url = 'https://payeer.com/ajax/api/m2.php';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$postData = 'api%5Bid%5D='.$mShop.'&api%5Blang%5D=ru&shop%5Bm_shop%5D='.$mShop.'&shop%5Bm_orderid%5D='.$mOrderid.'&shop%5Bm_amount%5D='.$mAmount.'&shop%5Bm_curr%5D='.$mCurr.'&shop%5Bm_desc%5D='.urlencode($mDesc).'&shop%5Bm_sign%5D='.$mSign.'&shop%5Blang%5D=ru';
			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/plain, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With: XMLHttpRequest',
				'Content-Length: '.strlen($postData),
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'TE: Trailers',

			]);

			$content = $this->request($url, $postData);

			//тут яд
			if(preg_match('!Яндекс Деньги</div>\s+<div class="ps_curr" id="pay-system-(.+?)">!iu', $content, $matches))
			{
				$ps = $matches[1];
				$currId = "curr[$matches[1]]";
			}
			else
			{
				$this->error(' Ошибка парсинга ps при пополнении ');
				return false;
			}

			//счет будет для яндекс
			$url = 'https://payeer.com/ajax/api/m2.php';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;

			$postData = 'api%5Bid%5D='.$mShop.'&api%5Blang%5D=ru&shop%5Bm_shop%5D='.
				$mShop.'&shop%5Bm_orderid%5D='.$mOrderid.'&shop%5Bm_amount%5D='.$mAmount.
				'&shop%5Bm_curr%5D='.urlencode($mCurr).'&shop%5Bm_desc%5D='.urlencode($mDesc).
				'&shop%5Bm_sign%5D='.urlencode($mSign).'&shop%5Blang%5D=ru&cmd=detail&form%5B'.
				$currId.'%5D='.$mCurr.'&form%5Bps%5D='.$ps;

			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/plain, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With: XMLHttpRequest',
				'Content-Length: '.strlen($postData),
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'TE: Trailers',

			]);

			$content = $this->request($url, $postData);

			if(preg_match('!name="order_email" type="hidden" value="(.+?)"!iu', $content, $matches))
			{
				$email = $matches[1];
			}
			else
			{
				$this->error(' Ошибка парсинга email при пополнении login = '.$this->_login);
				return false;
			}

			$url = 'https://payeer.com/ajax/api/m2.php';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;

			$postData = 'api%5Bid%5D='.$mShop.'&api%5Blang%5D=ru&shop%5Bm_shop%5D='.$mShop.
				'&shop%5Bm_orderid%5D='.$mOrderid.'&shop%5Bm_amount%5D='.$mAmount.
				'&shop%5Bm_curr%5D='.$mCurr.'&shop%5Bm_desc%5D='.urlencode($mDesc).
				'&shop%5Bm_sign%5D='.$mSign.'&shop%5Blang%5D=ru&cmd=confirm&'.
				'form%5Border_email%5D='.urlencode($email).'&form%5Bps%5D='.$ps.'&form%5Bps_curr%5D='.$mCurr;

			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/plain, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With: XMLHttpRequest',
				'Content-Length: '.strlen($postData),
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'TE: Trailers',

			]);

			$content = $this->request($url, $postData);

			if(preg_match('!m_historyid=(\d+)&m_historytm=(\d+)&m_curorderid=(\d+)"!iu', $content, $matches))
			{
				$mHistoryId = $matches[1];
				$mHistoryTm = $matches[2];
				$mCurorderId = $matches[3];
			}
			else
			{
				$this->error(' Ошибка парсинга mHistoryId при пополнении login = '.$this->_login);
				return false;
			}


			$url = 'https://payeer.com/api/merchant/m.php?lang=ru&m_historyid='.
				$mHistoryId.'&m_historytm='.$mHistoryTm.'&m_curorderid='.$mCurorderId;

			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;

			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/plain, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With: XMLHttpRequest',
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Upgrade-Insecure-Requests: 1',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'TE: Trailers',
			]);

			$content = $this->request($url);

			/*
			 * <script type="text/javascript">
				window.opener = null;

				var myMerchant = new jsMyMerchant({"id":"1262","lang":"ru"});
				$(document).ready(function()
				{
					myMerchant.process({"lang":"ru","m_historyid":"621478118","m_historytm":"1533373363","m_curorderid":"76934025"});});
				</script>
			 */


			$url = 'https://payeer.com/ajax/api/m2.php';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$postData = 'api%5Bid%5D='.$mShop.'&api%5Blang%5D=ru&cmd=process'.
				'&params%5Blang%5D=ru&params%5Bm_historyid%5D='.$mHistoryId.
				'&params%5Bm_historytm%5D='.$mHistoryTm.'&params%5Bm_curorderid%5D='.$mCurorderId;

			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/plain, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/api/merchant/m.php?lang=ru&m_historyid='.$mHistoryId.'&m_historytm='.$mHistoryTm.'&m_curorderid='.$mCurorderId,
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With: XMLHttpRequest',
				'Content-Length: '.strlen($postData),
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'TE: Trailers',
			]);

			$content = $this->request($url, $postData);

			prrd($content);//тут параметры яда, но пока нам не подходит вариант


			//доп запрос, хз че делает, возвращает ок
			$url = 'https://payeer.com/handlers/merchant.php?m_historyid='.
				$mHistoryId.'&m_historytm='.$mHistoryTm.'&m_curorderid='.$mCurorderId.'&lang=ru';
			$this->sender->additionalHeaders = null;
			$this->sender->useCookie = false;
			$this->_setHeaders([
				'Host: payeer.com',
				'User-Agent: '.$this->browser,
				'Accept: text/plain, */*; q=0.01',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru',
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With: XMLHttpRequest',
				'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
				'Connection: keep-alive',
				'Upgrade-Insecure-Requests: 1',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
				'TE: Trailers',
			]);

			$contentExtra = $this->request($url);

		}
	}

	/**
	 * получаем реквизиты для оплаты Qiwi->Payeer
	 * @param float $sum
	 * @param string $phone
	 * @param string|bool $sms
	 *
	 * @return array|bool
	 * 	 Array
	(
	[amount] => 133.96
	[number] => 79164891129
	[comment] => Order i_17501138 number 76998741
	//четыре параметра нужные для опредения статуса заявки
	[mShopId] => 1262
	[mHistoryId] => 623449202
	[mHistoryTm] => 1533666708
	[mCurOrderId] => 77202723
	)
	 *
	 */
	public function getPayParams($amount, $phone, $sms = false, $params = false)
	{
		session_write_close();

		$sum = PayeerAccount::getAmountForPayeer($amount);

		if($this->isAuth)
		{
			if(!is_numeric($sum))
			{
				$this->error(' Проверьте сумму ');
				return false;
			}

			if($sms === false)
			{
				$this->_config = $this->_cfg();

				$url = 'https://payeer.com/ru/account/add/?_pjax=%23pjax-container';
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;
				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/html, */*; q=0.01',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/ru/account/',
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-PJAX: true',
					'X-PJAX-Container: #pjax-container',
					'X-Requested-With: XMLHttpRequest',
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
				]);
				$content = $this->request($url);


				//попадаем на страницу пополнения

				if(!preg_match("!document.title = 'Пополнить!iu", $content))
				{
					$this->error(' Ошибка пополнения, возможно не авторизован, повторите запрос login = '.$this->_login);
					return false;
				}

				if(preg_match('!"sessid" value="(.+?)"!iu', $content, $matches))
				{
					$sessId = $matches[1];
				}
				else
				{
					$this->error(' Ошибка парсинга sessid при пополнении ');
					return false;
				}

				$url = 'https://payeer.com/bitrix/components/payeer/account.add/templates/.default/ajax.php?action=add';
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;
				$postData = 'sum='.$sum.'&curr=RUB&block=0&sessid='.$sessId;

				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: application/json, text/javascript, */*; q=0.01',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/ru/account/add/',
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With: XMLHttpRequest',
					'Content-Length: '.strlen($postData),
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',
				]);
				$content = $this->request($url, $postData);

				if(preg_match('!У нас возникли вопросы!iu', $content, $matches))
				{
					toLogError('!!! ЗАБЛОКИРОВАН !!! login = '.$this->_login);
					return false;
				}


				if(preg_match('!ORDER_ID=(.+?)"!iu', $content, $matches))
				{
					$orderId = $matches[1];
					$this->orderId = $orderId;
				}
				else
				{
					$this->error(' Ошибка парсинга orderId при пополнении login = '.$this->_login);
					return false;
				}

				$url = 'https://payeer.com/ru/account/input/process.php?ORDER_ID='.$orderId;
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;
				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/ru/account/add/',
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Upgrade-Insecure-Requests: 1',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',
				]);

				$content = $this->request($url);

				if(preg_match('!"m_shop" value="(.+?)"!iu', $content, $matches))
				{
					$mShop = $matches[1];
					$this->mShop = $mShop;
				}
				else
				{
					$this->error(' Ошибка парсинга mShop при пополнении ');
					return false;
				}

				if(preg_match('!"m_orderid" value="(.+?)"!iu', $content, $matches))
				{
					$mOrderid = $matches[1];
					$this->mOrderid = $mOrderid;
				}
				else
				{
					$this->error(' Ошибка парсинга mOrderid при пополнении ');
					return false;
				}

				if(preg_match('!"m_curr" value="(.+?)"!iu', $content, $matches))
				{
					$mCurr = $matches[1];
					$this->mCurr = $mCurr;
				}
				else
				{
					$this->error(' Ошибка парсинга mCurr при пополнении ');
					return false;
				}

				if(preg_match('!"m_desc" value="(.+?)"!iu', $content, $matches))
				{
					$mDesc = $matches[1];
					$this->mDesc = $mDesc;
				}
				else
				{
					$this->error(' Ошибка парсинга mDesc при пополнении ');
					return false;
				}


				if(preg_match('!"m_sign" value="(.+?)"!iu', $content, $matches))
				{
					$mSign = $matches[1];
					$this->mSign = $mSign;
				}
				else
				{
					$this->error(' Ошибка парсинга mSign при пополнении ');
					return false;
				}


				if(preg_match('!"m_amount" value="(.+?)"!iu', $content, $matches))
				{
					$mAmount = $matches[1];
					$this->mAmount = $mAmount;
				}
				else
				{
					$this->error(' Ошибка парсинга mAmount при пополнении ');
					return false;
				}

				$url = 'https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.
					'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru';
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;
				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/ru/account/input/process.php?ORDER_ID='.$orderId,
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Upgrade-Insecure-Requests: 1',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',
				]);

				$content = $this->request($url);

				$url = 'https://payeer.com/ajax/api/m2.php';
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;
				$postData = 'api%5Bid%5D='.$mShop.'&api%5Blang%5D=ru&shop%5Bm_shop%5D='.$mShop.'&shop%5Bm_orderid%5D='.$mOrderid.'&shop%5Bm_amount%5D='.$mAmount.'&shop%5Bm_curr%5D='.$mCurr.'&shop%5Bm_desc%5D='.urlencode($mDesc).'&shop%5Bm_sign%5D='.$mSign.'&shop%5Blang%5D=ru';
				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/plain, */*; q=0.01',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru',
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With: XMLHttpRequest',
					'Content-Length: '.strlen($postData),
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',

				]);

				$content = $this->request($url, $postData);

				if(preg_match('!QIWI<br>Wallet</div>\s+<div class="ps_curr" id="pay-system-(.+?)">!iu', $content, $matches))
				{
					$ps = $matches[1];
					$this->ps = $ps;
					$currId = "curr[$matches[1]]";
				}
				else
				{
					$this->error(' Ошибка парсинга ps при пополнении ');
					return false;
				}

				//счет будет для киви
				$url = 'https://payeer.com/ajax/api/m2.php';
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;

				$postData = 'api%5Bid%5D='.$mShop.'&api%5Blang%5D=ru&shop%5Bm_shop%5D='.
					$mShop.'&shop%5Bm_orderid%5D='.$mOrderid.'&shop%5Bm_amount%5D='.$mAmount.
					'&shop%5Bm_curr%5D='.urlencode($mCurr).'&shop%5Bm_desc%5D='.urlencode($mDesc).
					'&shop%5Bm_sign%5D='.urlencode($mSign).'&shop%5Blang%5D=ru&cmd=detail&form%5B'.
					$currId.'%5D='.$mCurr.'&form%5Bps%5D='.$ps;

				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/plain, */*; q=0.01',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru',
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With: XMLHttpRequest',
					'Content-Length: '.strlen($postData),
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',

				]);

				$content = $this->request($url, $postData);

				if(preg_match('!name="order_email" type="hidden" value="(.+?)"!iu', $content, $matches))
				{
					$email = $matches[1];
					$this->email = $email;
				}
				else
				{
					$this->error(' Ошибка парсинга email при пополнении login = '.$this->_login);
					return false;
				}

				//тут в запросе добавляется номер, сначала отправляем без смс

				$url = 'https://payeer.com/ajax/api/m2.php';
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;

				$postData = 'api%5Bid%5D='.$mShop.'&api%5Blang%5D=ru&shop%5Bm_shop%5D='.$mShop.
					'&shop%5Bm_orderid%5D='.$mOrderid.'&shop%5Bm_amount%5D='.$mAmount.
					'&shop%5Bm_curr%5D='.$mCurr.'&shop%5Bm_desc%5D='.urlencode($mDesc).
					'&shop%5Bm_sign%5D='.$mSign.'&shop%5Blang%5D=ru&cmd=confirm&'.
					'form%5Border_email%5D='.urlencode($email).'&form%5BACCOUNT_NUMBER%5D=%2B'.$phone.
					'&form%5Border_smscode%5D=&form%5Bps%5D='.$ps.'&form%5Bps_curr%5D='.$mCurr;

				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/plain, */*; q=0.01',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$mShop.'&m_orderid='.$mOrderid.'&m_amount='.$mAmount.'&m_curr='.$mCurr.'&m_desc='.urlencode($mDesc).'&m_sign='.$mSign.'&lang=ru',
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With: XMLHttpRequest',
					'Content-Length: '.strlen($postData),
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',
				]);

				$content = $this->request($url, $postData);

				$contentArr = json_decode($content, 1);

				if($contentArr['error'][0]['msg'] == 'Введите, пожалуйста, код из смс')
				{
					return [
						'mShop' =>$this->mShop,
						'mOrderid' =>$this->mOrderid,
						'mAmount' =>$this->mAmount,
						'mCurr' =>$this->mCurr,
						'mDesc' =>$this->mDesc,
						'mSign' =>$this->mSign,
						'email' =>$this->email,
						'ps' =>$this->ps,
						'amount' =>$amount,
					];
				}
				else
				{
					$this->error(' Ошибка запроса пополнения с смс '.$contentArr.' login = '.$this->_login);
					return false;
				}
			}
			else
			{
				if(!$params['mShop'] || !$params['mOrderid'] || !$params['mAmount'] || !$params['mCurr']
					|| !$params['mDesc'] || !$params['mSign'] || !$params['ps'] || !$params['email'])
				{
					$this->error(' Ошибка запроса пополнения с смс, не задан один из параметров (mShop, mOrderid, mAmount, mCurr, mDesc, mSign, email, ps) login = '.$this->_login);
					return false;
				}
				else
				{
					$this->mShop = $params['mShop'];
					$this->mOrderid = $params['mOrderid'];
					$this->mAmount = $params['mAmount'];
					$this->mCurr = $params['mCurr'];
					$this->mDesc = $params['mDesc'];
					$this->mSign = $params['mSign'];
					$this->ps = $params['ps'];
					$this->email = $params['email'];
				}

				//второй запрос уже с смс
				$url = 'https://payeer.com/ajax/api/m2.php';
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;

				$postData = 'api%5Bid%5D='.$this->mShop.'&api%5Blang%5D=ru&shop%5Bm_shop%5D='.$this->mShop.
					'&shop%5Bm_orderid%5D='.$this->mOrderid.'&shop%5Bm_amount%5D='.$this->mAmount.
					'&shop%5Bm_curr%5D='.$this->mCurr.'&shop%5Bm_desc%5D='.urlencode($this->mDesc).
					'&shop%5Bm_sign%5D='.$this->mSign.'&shop%5Blang%5D=ru&cmd=confirm&'.
					'form%5Border_email%5D='.urlencode($this->email).'&form%5BACCOUNT_NUMBER%5D=%2B'.$phone.
					'&form%5Border_smscode%5D='.$sms.'&form%5Bps%5D='.$this->ps.'&form%5Bps_curr%5D='.$this->mCurr;

				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/plain, */*; q=0.01',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$this->mShop.'&m_orderid='.$this->mOrderid.'&m_amount='.$this->mAmount.'&m_curr='.$this->mCurr.'&m_desc='.urlencode($this->mDesc).'&m_sign='.$this->mSign.'&lang=ru',
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With: XMLHttpRequest',
					'Content-Length: '.strlen($postData),
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',
				]);

				$content = $this->request($url, $postData);

				$contentArr = json_decode($content, 1);

				if(preg_match('!m_historyid=(\d+)&m_historytm=(\d+)&m_curorderid=(\d+)"!iu', $content, $matches))
				{
					$mHistoryId = $matches[1];
					$mHistoryTm = $matches[2];
					$mCurorderId = $matches[3];
				}
				else
				{
					$this->error(' Ошибка создания реквизитов, '.arr2str($contentArr).' login = '.$this->_login);
					return false;
				}


				$url = 'https://payeer.com/api/merchant/m.php?lang=ru&m_historyid='.
					$mHistoryId.'&m_historytm='.$mHistoryTm.'&m_curorderid='.$mCurorderId;

				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;

				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/plain, */*; q=0.01',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$this->mShop.'&m_orderid='.$this->mOrderid.'&m_amount='.$this->mAmount.'&m_curr='.$this->mCurr.'&m_desc='.urlencode($this->mDesc).'&m_sign='.$this->mSign.'&lang=ru',
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With: XMLHttpRequest',
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Upgrade-Insecure-Requests: 1',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',
				]);

				$content = $this->request($url);

				/*
				 * <script type="text/javascript">
					window.opener = null;

					var myMerchant = new jsMyMerchant({"id":"1262","lang":"ru"});
					$(document).ready(function()
					{
						myMerchant.process({"lang":"ru","m_historyid":"621478118","m_historytm":"1533373363","m_curorderid":"76934025"});});
					</script>
				 */


				$url = 'https://payeer.com/ajax/api/m2.php';
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;
				$postData = 'api%5Bid%5D='.$this->mShop.'&api%5Blang%5D=ru&cmd=process'.
					'&params%5Blang%5D=ru&params%5Bm_historyid%5D='.$mHistoryId.
					'&params%5Bm_historytm%5D='.$mHistoryTm.'&params%5Bm_curorderid%5D='.$mCurorderId;

				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/plain, */*; q=0.01',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/api/merchant/m.php?lang=ru&m_historyid='.$mHistoryId.'&m_historytm='.$mHistoryTm.'&m_curorderid='.$mCurorderId,
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With: XMLHttpRequest',
					'Content-Length: '.strlen($postData),
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',
				]);

				$content = $this->request($url, $postData);

				if(preg_match('!document.location = "(.+?)"!iu', $content, $matches))
				{
					$qiwiPayUrl = $matches[1];
				}
				else
				{
					$this->error(' Ошибка парсинга payParams при пополнении ');
					return false;
				}

				$url = 'https://payeer.com/handlers/merchant.php?m_historyid='.
					$mHistoryId.'&m_historytm='.$mHistoryTm.'&m_curorderid='.$mCurorderId.'&lang=ru';
				$this->sender->additionalHeaders = null;
				$this->sender->useCookie = false;
				$this->_setHeaders([
					'Host: payeer.com',
					'User-Agent: '.$this->browser,
					'Accept: text/plain, */*; q=0.01',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://payeer.com/api/merchant/m.php?m_shop='.$this->mShop.'&m_orderid='.$this->mOrderid.'&m_amount='.$this->mAmount.'&m_curr='.$this->mCurr.'&m_desc='.urlencode($this->mDesc).'&m_sign='.$this->mSign.'&lang=ru',
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With: XMLHttpRequest',
					'Cookie: PHPSESSID='.$this->_config['phpSessId'].'; BITRIX_SM_SOUND_LOGIN_PLAYED=Y',
					'Connection: keep-alive',
					'Upgrade-Insecure-Requests: 1',
					'Pragma: no-cache',
					'Cache-Control: no-cache',
					'TE: Trailers',
				]);

				$contentExtra = $this->request($url);

				$parts = parse_url($qiwiPayUrl);
				parse_str($parts['query'], $qiwiPayParams);
				$extraQiwiParams = $qiwiPayParams['extra'];

				if(preg_match('!Order i_(\d+) Счет (\d+) Внимание!iu', $extraQiwiParams["'comment'"], $matches))
				{
					$orderId = $matches[1];
					$number = $matches[2];
				}
				else
				{
					$this->error(' Ошибка парсинга коммента при оплате ');
					return false;
				}

				$modifiedComment = 'Order i_'.$orderId.' number '.$number;

				$encodedComment = rawurlencode($modifiedComment);
				$encodedComment = str_replace(['%21', '%2C'], ['!', ','], $encodedComment);

				$amountStr = trim($qiwiPayParams['amountInteger'] . ($qiwiPayParams['amountFraction'] ? '.'.$qiwiPayParams['amountFraction'] : '.00'));
				$amount = $amountStr * 1;


				$result = [
					'amount' => $amount,
					'number' => $extraQiwiParams["'account'"],
					'comment' => $modifiedComment,
					'orderId' => $this->mOrderid,
					'mShopId' => $this->mShop,
					'mHistoryId' => $mHistoryId,
					'mHistoryTm' => $mHistoryTm,
					'mCurOrderId' => $mCurorderId,
				];

				return $result;
			}
		}
	}
}