<?php

class WexCurlBot
{
	const ERROR_AUTH = 'error_auth';
	const ERROR_WITH_REQUEST = 'Ошибка запроса ';

	const TYPE_IN = 'Ввод';
	const TYPE_OUT = 'Вывод';
	const ACTION_RESEND = 'resend';
	const ACTION_CANCEL = 'cancel';

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
	public $timeout = 60;
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


	public function __construct($login, $pass, $proxy = false, $browser = false)
	{
		$this->_login = $login;
		$this->_pass = $pass;

		$this->_workDir = dirname(__FILE__) . '/' . __CLASS__ . '/';
		$this->_usersDir = $this->_workDir . 'users/';
		$this->_userDir = $this->_usersDir . $this->_login . '/';

		$this->proxy = $proxy;
		$this->browser = $browser;

		$this->_initUser();

		if(!$this->_isAuth())
			$this->_auth();
		else
			$this->isAuth = true;
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

		if (!file_exists($fileCookie)) {
			if (file_put_contents($fileCookie, '') === false)
			{
				toLog('error write ' . $fileCookie);
				return false;
			}
			chmod($fileCookie, 0777);
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
		$this->sender->useCookie = true;
		$this->sender->browser = $config['browser'];
		$this->sender->cookieFile = $fileCookie;
		$this->sender->followLocation = false;
		$this->sender->timeout = $this->timeout;
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
			$this->error('Не задан параметр explodeHeaders($str)');
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
	 * авторизация на сайте
	 * @return bool|int
	 */
	protected function _auth()
	{
		session_write_close();

		$cookieArr = $this->getWexCookie();

		if($cookieArr)
		{
			$this->writeCookieToFile($cookieArr);
			$this->isAuth = true;
			toLog('Получены cookie '.$this->_login);
		}
		else
		{
			//TODO: в однопоточном режиме будут засоряться логи
			//TODO: при неудачной попытке авторизации, поэтому пока убрал вывод в логи ошибок
			$this->isAuth = false;
			return false;
		}

	}

	/**
	 * проверка авторизации
	 * @return int
	 */
	protected function _isAuth()
	{
		session_write_close();
		$url = 'https://wex.nz/';

		$this->sender->additionalHeaders = null;
		$this->_setHeaders([
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Connection: keep-alive',
			'Host: wex.nz',
			'Upgrade-Insecure-Requests: 1',
			'User-Agent: '.$this->browser,
		]);

		$content = $this->request($url);

//		if(!$content)
//		{
//			$this->isBlocked();
//		}

		return preg_match('!Выйти!iu', $content);
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
	 * получаем историю
	 * @return bool
	 */
	public function wexHistory()
	{
		session_write_close();
		if($this->isAuth)
		{
			$url = 'https://wex.nz/';
			$this->sender->additionalHeaders = null;
			$this->sender->followLocation = false;
			$this->_setHeaders([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Host: wex.nz',
				'Upgrade-Insecure-Requests: 1',
				'User-Agent: '.$this->browser,
			]);
			$content = $this->request($url);

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка получения истории  Yandex step1 '.$this->_login);
				return false;
			}

			if(preg_match('!profile\("notifications", (.+?), 0\)!iu', $content, $matches))
			{
				$profileId = $matches[1];
			}
			else
			{
				$this->error('Ошибка получения истории Yandex step2 '.$this->_login);
				return false;
			}

			$url = 'https://wex.nz/ajax/billing';
			$postData = 'csrfToken='.$csrfToken.
				'&act=history&id='.$profileId.'&page=1&view=1&type=1';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: */*',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'Host: wex.nz',
				'Referer: https://wex.nz/profile',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
			]);
			$content = $this->request($url, $postData);

			if($this->lastHttpCode == 403)
			{
				$this->error('Ошибка получения истории HttpCode = 403 '.$this->_login);
				return false;
			}

			$transactionArr = [];

			if(preg_match('!История транзакций!iu', $content))
			{
				$mathcStr = "!<tr>\s+<td>#(\d+?)</td>\s+<td title='(Ввод|Вывод|Приход|Расход)'><b style='color:(green|red)'>[+-]{1}(.+?)</b>&nbsp;".
					"<b>(RUR|BTC|USD)</b></td>\s+<td style='word-break:break-all'>(.+?)</td>\s+".
					"<td title='(.+?)'>(.+?)<br><span class='small'>(.+?)</span></td>\s+<td title='(\w+?)'>".
					"<img\s+src='https://wex.nz/images/1px.png' class='main-s main-s-yes' alt='yes' /></td>\s+</tr>!iu";

				if(preg_match_all($mathcStr, $content, $matches))
				{
					foreach($matches[1] as $key=>$transaction)
					{
						$type = '';
						if($matches[2][$key] == 'Ввод')
							$type = 'in';

						$category = '';
						if(strip_tags($matches[6][$key]) == 'Payment from Yandex.Money')
							$category = 'yandex';

						$status = '';
						if($matches[10][$key] == 'Завершено')
							$status = 'success';
						//else
						//	$transactionArr[$key]['status'] = 'wrong';

						if($category === 'yandex' and $type === 'in' and $status === 'success')
						{
							$transactionArr[$key]['id'] = $transaction;
							$transactionArr[$key]['direction'] = $matches[2][$key];
							$transactionArr[$key]['amount'] = $matches[4][$key]*1;
							$transactionArr[$key]['currency'] = $matches[5][$key];
							$transactionArr[$key]['comment'] = strip_tags($matches[6][$key]);
							$transactionArr[$key]['date'] = DateTime::createFromFormat('d.m.y H:i:s', $matches[8][$key].' '.$matches[9][$key])->getTimestamp();
							$transactionArr[$key]['type'] = $type;
							$transactionArr[$key]['category'] = $category;
							$transactionArr[$key]['status'] = $status;
						}
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
	 * получаем историю подробную
	 * @return bool
	 */
	public function historyForAdmin($pageNum)
	{
		session_write_close();
		if($this->isAuth)
		{
			$url = 'https://wex.nz/';
			$this->sender->additionalHeaders = null;
			$this->sender->followLocation = false;
			$this->_setHeaders([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Host: wex.nz',
				'Upgrade-Insecure-Requests: 1',
				'User-Agent: '.$this->browser,
			]);
			$content = $this->request($url);

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка получения истории  Yandex step1 '.$this->_login);
				return false;
			}

			if(preg_match('!profile\("notifications", (.+?), 0\)!iu', $content, $matches))
			{
				$profileId = $matches[1];
			}
			else
			{
				$this->error('Ошибка получения истории Yandex step2 '.$this->_login);
				return false;
			}

			$url = 'https://wex.nz/ajax/billing';
			$postData = 'csrfToken='.$csrfToken.
				'&act=history&id='.$profileId.'&page='.$pageNum.'&view=1&type=1';

			//<a href="#funds/history/2" onclick="trans_history(1294942, 2, 1, 1)">2</a>

			//<a href="#funds/history/2" onclick="trans_history(1294942, 2, 1, 1)">2</a>


//			csrfToken: 0364ee48fefede9a2c3b923d35d8412f6d0401241c10c46b9f6792a089177964
//			act: history
//			id: 1294942
//			page: 2
//			view: 1
//			type: 1

			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: */*',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'Host: wex.nz',
				'Referer: https://wex.nz/profile',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
			]);
			$content = $this->request($url, $postData);


			if($this->lastHttpCode == 403)
			{
				$this->error('Ошибка получения истории HttpCode = 403 '.$this->_login);
				return false;
			}

			$transactionArr = [];

			if(preg_match('!История транзакций!iu', $content))
			{
				$mathcStr = "!<tr>\s+<td>#(\d+?)</td>\s+<td title='(Ввод|Вывод|Приход|Расход|Не подтверждено)'><b style='color:(green|red)'>[+-]{1}(.+?)</b>&nbsp;".
					"<b>(RUR|BTC|USD|ZEC|USDT)</b></td>\s+<td style='word-break:break-all'>(.+?)</td>\s+".
					"<td title='(.+?)'>(.+?)<br><span class='small'>(.+?)</span></td>\s+<td title='(.+?)'>".
					"<img\s+src='(.+?)' class='(.+?)' alt='(.+?)' /></td>\s+</tr>!iu";

				if(preg_match_all($mathcStr, $content, $matches))
				{
					$maxPage = 1;

					//если страниц истории много, то определяем сколько их всего
					if(preg_match_all("'#funds/history/(\d+)'", $content, $pages))
					{
						foreach($pages[1] as $key=>$page)
						{
							if($maxPage <= $pages[1][$key])
								$maxPage = $pages[1][$key];
						}
					}

					foreach($matches[1] as $key=>$transaction)
					{
						$type = $matches[2][$key];

						$category = '';
						if(strip_tags($matches[6][$key]) == 'Payment from Yandex.Money')
							$category = 'yandex';


//						if($category === 'yandex' and $type === 'in' and $status === 'success')
//						{
							$transactionArr[$key]['id'] = $transaction;
							$transactionArr[$key]['direction'] = $matches[2][$key];
							$transactionArr[$key]['amount'] = $matches[4][$key]*1;
							$transactionArr[$key]['currency'] = $matches[5][$key];

							$txidStr = '';
							if(preg_match('!<a href="https://blockchain.info/tx/(.+?)">!iu', $matches[6][$key], $txid))
								$txidStr = $txid[1];
							$transactionArr[$key]['comment'] = strip_tags($matches[6][$key]);
							$transactionArr[$key]['date'] = DateTime::createFromFormat('d.m.y H:i:s', $matches[8][$key].' '.$matches[9][$key])->getTimestamp();
							$transactionArr[$key]['type'] = $type;
							$transactionArr[$key]['category'] = $category;
							$transactionArr[$key]['status'] = $matches[10][$key];
							$transactionArr[$key]['txid'] = $txidStr;
							$transactionArr[$key]['pageCount'] = $maxPage;

//						}
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
	 * создаем ссылку для оплаты с Яндекса
	 * @param $sum
	 *
	 * @return bool|array
	 */
	public function getYandexPayUrlParams($sum)
	{
		session_write_close();

		return false;

		if($this->isAuth)
		{
			$url = 'https://wex.nz/';
			$this->sender->additionalHeaders = null;
			$this->sender->followLocation = false;
			$this->_setHeaders([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Host: wex.nz',
				'Upgrade-Insecure-Requests: 1',
				'User-Agent: '.$this->browser,
			]);
			$content = $this->request($url);

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка создания ссылки оплаты Yandex step1 '.$this->_login);
				return false;
			}

			$url = 'https://wex.nz/ajax/billing';
			$postData = 'csrfToken='.$csrfToken.'&act=deposit%2Frur';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: */*',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'DNT: 1',
				'Host: wex.nz',
				'Referer: https://wex.nz/profile',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',

			]);

			$content = $this->request($url, $postData);

			if(preg_match('!name="receiver" value="(.+?)"!iu', $content, $matches))
			{
				$receiver = $matches[1];
			}
			else
			{
				$this->error('Ошибка создания ссылки оплаты Yandex step2 '.$this->_login);
				return false;
			}

			if(preg_match('!name="targets" value="(.+?)"!iu', $content, $matches))
			{
				$targets = $matches[1];
			}
			else
			{
				$this->error('Ошибка создания ссылки оплаты Yandex step3 '.$this->_login);
				return false;
			}

			//отправляем деньги через яндекс
			$url = 'https://money.yandex.ru/quickpay/confirm.xml';
			$apiId = md5(round(microtime(true)*1000).'');
			$postData = 'receiver='.$receiver.
				'&sum='.$sum.'&formcomment=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5+%D1%81%D1%87%D0%B5%D1%82%D0%B0&short-dest=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5+%D1%81%D1%87%D0%B5%D1%82%D0%B0&quickpay-form=shop'.
				'&targets='.$targets.'&label='.$targets.'&successURL='.urlencode('http://ymprocessing.cc/sendmail/index.php?key=testtest&method=ConfirmPayment&apiId='.$apiId).'&paymentType=PC&submit-button=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B8%D1%82%D1%8C';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded',
				'Host: money.yandex.ru',
				'Referer: https://wex.nz/profile',
				'Upgrade-Insecure-Requests: 1',
				'User-Agent: '.$this->browser,
			]);

			$content = $this->request($url, $postData);

			if(preg_match('!href="(.+?)"!iu', $content, $matches))
			{
				$payUrl = htmlspecialchars_decode($matches[1]);
				return [
					'url' => $payUrl,
					'apiId' => $apiId,
				];
			}
			else
			{
				$this->error('Ошибка создания ссылки оплаты Yandex step4 '.$this->_login);
				return false;
			}
		}
	}


	/**
	 * @param $cookieArr
	 */
	protected function writeCookieToFile($cookieArr)
	{
		$string = '';
		$string .= "# Netscape HTTP Cookie File\n";
		$string .= "# http://curl.haxx.se/rfc/cookie_spec.html\n";

		for($i = 0; $i < count($cookieArr); $i++)
		{
			$cookie = $cookieArr[$i];
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

	/**
	 * @return bool
	 * получаем куки с авторизацией по апи
	 */
	public function getWexCookie()
	{
		session_write_close();

		$threadName = 'selenium';

		if(Tools::threader($threadName))
		{
			$currentProxy = $this->parseProxyStr($this->proxy);

			$url = 'http://94.140.125.237/selenium/index.php?key=testtest&method=GetCookies'.
				'&email='.$this->_login.
				'&pass='.$this->_pass.'&proxyIp='.$currentProxy['ip'].
				'&proxyPort='.$currentProxy['port'].'&proxyLogin='.$currentProxy['login'].'&proxyPass='.$currentProxy['pass'];

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

				if(isset($cookieArr['error']['mgs']))
				{
					$this->error($this->_login.' '.arr2str($cookieArr['error']['mgs']));
					return false;
				}
				elseif(isset($cookieArr['result']))
				{
					return $cookieArr['result'];
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
	 * @return bool
	 * получаем комсу при переводе с яда
	 */
	public function getYandexFee()
	{
		session_write_close();

		if($this->isAuth)
		{
			$url = 'https://wex.nz/';
			$this->sender->additionalHeaders = null;
			$this->sender->followLocation = false;
			$this->_setHeaders([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Host: wex.nz',
				'Upgrade-Insecure-Requests: 1',
				'User-Agent: '.$this->browser,
			]);
			$content = $this->request($url);

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка получения комиссии WEX step1'.$this->_login);
				return false;
			}

			$url = 'https://wex.nz/ajax/billing';
			$postData = 'csrfToken='.$csrfToken.'&act=deposit%2Frur';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: */*',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'DNT: 1',
				'Host: wex.nz',
				'Referer: https://wex.nz/profile',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',

			]);

			$content = $this->request($url, $postData);

			if(preg_match('!Комиссия составляет (.+?)%, без учета комиссии Яндекс.Денег!iu', $content, $matches))
			{
				return $yandexFee = $matches[1]/100;
			}
			else
			{
				$this->error('Ошибка получения комиссии WEX '.$this->_login);
				return false;
			}
		}
	}

	/**
	 * @return bool
	 * проверяем блокирован акк или нет
	 */
	public function isBlocked()
	{
		$url = 'https://wex.nz/verify/';
		$this->sender->additionalHeaders = null;
		$this->sender->followLocation = true;
		$this->_setHeaders([
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Connection: keep-alive',
			'Host: wex.nz',
			'Upgrade-Insecure-Requests: 1',
			'User-Agent: '.$this->browser,
		]);
		$content = $this->request($url);
		$this->sender->followLocation = false;

		if(preg_match('!Ваш аккаунт был заблокирован!iu', $content))
		{
			$this->error('Аккаунт заблокирован '.$this->_login);
			return true;
		}
		else
			return false;
	}

	/**
	 * @return array|bool
	 *
	 * получаем баланс в разных валютах
	 */
	public function getBalance()
	{
		session_write_close();

		if($this->isAuth)
		{
			//<b id="balance14">1573.1796</b>
			$url = 'https://wex.nz/';
			$this->sender->additionalHeaders = null;
			$this->sender->followLocation = false;
			$this->_setHeaders([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Host: wex.nz',
				'Upgrade-Insecure-Requests: 1',
				'User-Agent: '.$this->browser,
			]);
			$content = $this->request($url);

			//массив балансов
			$balanceArr = [];

			if($this->_isAuth())
			{
				//в рублях
				if(preg_match('!<b id="balance14">(.+?)</b>!iu', $content, $matches))
				{
					$balanceArr['ru'] = $matches[1]*1;
				}
				else
				{
					$balanceArr['ru'] = 0;
				}

				//в битке
				if(preg_match('!<b id="balance2">(.+?)</b>!iu', $content, $matches))
				{
					$balanceArr['btc'] = $matches[1]*1;
				}
				else
				{
					$this->error('Ошибка получения баланса BTC '.$this->_login);
					return false;
				}

				//в usd
				if(preg_match('!<b id="balance1">(.+?)</b>!iu', $content, $matches))
				{
					$balanceArr['usd'] = $matches[1]*1;
				}
				else
				{
					$balanceArr['usd'] = 0;
				}
				//в zec
				if(preg_match('!<b id="balance38">(.+?)</b>!iu', $content, $matches))
				{
					$balanceArr['zec'] = $matches[1]*1;
				}
				else
				{
					$balanceArr['zec'] = 0;
				}
				//в usdt
				if(preg_match('!<b id="balance39">(.+?)</b>!iu', $content, $matches))
				{
					$balanceArr['usdt'] = $matches[1]*1;
				}
				else
				{
					$balanceArr['usdt'] = 0;
				}
				//примерный общий в USD
				if(preg_match('!<b id="balance0">~(.+?)</b>!iu', $content, $matches))
				{
					$balanceArr['total'] = $matches[1]*1;
				}
				else
				{
					$balanceArr['total'] = 0;
				}
			}
			else
			{
				$this->error('Контент для баланса не получен '.$this->_login);
				return false;
			}

			return $balanceArr;
		}
	}

	/**
	 * покупка BTC за рубли
	 * @return bool|string
	 */
	public function buyBtcRu()
	{
		if($this->isAuth)
		{
			$url = 'https://wex.nz/exchange/btc_rur';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive', 'Host: wex.nz',
				'Referer: https://wex.nz/exchange/btc_usd',
				'Upgrade-Insecure-Requests: 1',
				'User-Agent: '.$this->browser,]
			);

			$content = $this->request($url);

			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка покупки BTC httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match("!input id='pair' value='(\d+?)' type='hidden'!iu", $content, $matches))
			{
				$pair = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки BTC '.$this->_login);

				return false;
			}

			if(preg_match("!<span id='min_price'>(.+?)</span> RUR!iu", $content, $matches))
			{
				$minPrice = $matches[1] * 1;
			}
			else
			{
				$this->error('Ошибка покупки BTC '.$this->_login);

				return false;
			}

			if(preg_match("!id='token' type='hidden' value='(.+?)'!iu", $content, $matches))
			{
				$token = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки BTC '.$this->_login);

				return false;
			}

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки BTC '.$this->_login);

				return false;
			}

			if(preg_match('!<b id="balance14">(.+?)</b>!iu', $content, $matches))
			{
				$balanceRu = $matches[1] * 1;
			}
			else
			{
				$this->error('Ошибка покупки BTC '.$this->_login);

				return false;
			}

			$btcCount = formatAmount(($balanceRu * 0.99 / $minPrice) , 8);
			$minPrice = $minPrice + 20000;

			$url = 'https://wex.nz/ajax/order';
			$postData = 'csrfToken='.$csrfToken.'&trade=buy&btc_count='.$btcCount.'&btc_price='.$minPrice.'&pair='.$pair.'&token='.$token;
			$this->sender->additionalHeaders = null;
			$this->_setHeaders(['Accept: application/json, text/javascript, */*; q=0.01', 'Accept-Encoding: gzip, deflate, br', 'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3', 'Connection: keep-alive', 'Content-Length: '.strlen($postData), 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8', 'Host: wex.nz', 'Referer: https://wex.nz/exchange/btc_rur', 'User-Agent: '.$this->browser, 'X-Requested-With: XMLHttpRequest',]);

			$content = $this->request($url, $postData);

			if($this->lastHttpCode == 200)
			{
				$responceArr = json_decode($content, 1);

				if($responceArr['error'] == 'y')
				{
					if(preg_match("!class='tcenter'>(.+?)<!iu", $responceArr['data'], $matches))
					{
						$errorMsg = strip_tags($matches[1]);
						$this->error('Ошибка покупки BTC '.$this->_login.' '.$errorMsg);

						return false;
					}
				}
				else
				{
					$result = strip_tags($responceArr['data']).' '.$this->_login;
					toLog($result);

					return $result;
				}
			}
			else
			{
				$this->error('Ошибка покупки BTC httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}
		}
	}

	/**
	 * покупка бакса за рубли
	 * @return bool|string
	 */
	public function buyUsdRu()
	{
		if($this->isAuth)
		{
			$url = 'https://wex.nz/exchange/usd_rur';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Encoding: gzip, deflate, br',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Connection: keep-alive', 'Host: wex.nz',
					'Referer: https://wex.nz/exchange/btc_usd',
					'Upgrade-Insecure-Requests: 1',
					'User-Agent: '.$this->browser,]
			);

			$content = $this->request($url);


			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка покупки USD httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match("!input id='pair' value='(\d+?)' type='hidden'!iu", $content, $matches))
			{
				$pair = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки USD нет pair '.$this->_login);

				return false;
			}

			if(preg_match("!<span id='min_price'>(.+?)</span> RUR!iu", $content, $matches))
			{
				$minPrice = $matches[1] * 1;
			}
			else
			{
				$this->error('Ошибка покупки USD нет minPrice'.$this->_login);

				return false;
			}

			if(preg_match("!id='token' type='hidden' value='(.+?)'!iu", $content, $matches))
			{
				$token = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки USD нет token '.$this->_login);

				return false;
			}

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки USD нет csrfToken '.$this->_login);

				return false;
			}

			if(preg_match('!<b id="balance14">(.+?)</b>!iu', $content, $matches))
			{
				$balanceRu = $matches[1] * 1;
			}
			else
			{
				$this->error('Ошибка покупки BTC '.$this->_login);

				return false;
			}

			$usdAmount = str_replace(' ', '', formatAmount(($balanceRu * 0.988 / $minPrice) , 8));

			$minPrice = $minPrice + 20;

			$url = 'https://wex.nz/ajax/order';
			$postData = 'csrfToken='.$csrfToken.'&trade=buy&btc_count='.$usdAmount.'&btc_price='.$minPrice.'&pair='.$pair.'&token='.$token;
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: application/json, text/javascript, */*; q=0.01',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'Host: wex.nz',
				'Referer: https://wex.nz/exchange/usd_rur',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
			]);

			$content = $this->request($url, $postData);

			if($this->lastHttpCode == 200)
			{
				$responceArr = json_decode($content, 1);

				if($responceArr['error'] == 'y')
				{
					if(preg_match("!class='tcenter'>(.+?)<!iu", $responceArr['data'], $matches))
					{
						$errorMsg = strip_tags($matches[1]);
						$this->error('Ошибка покупки USD '.$this->_login.' '.$errorMsg);

						return false;
					}
				}
				else
				{
					$result = strip_tags($responceArr['data']).' '.$this->_login;
					toLog($result);

					return $result;
				}
			}
			else
			{
				$this->error('Ошибка покупки USD httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}
		}
	}


	/**
	 * покупка ZEC за USD
	 * @return bool|string
	 */
	public function buyZecUsd()
	{
		if($this->isAuth)
		{
			$url = 'https://wex.nz/exchange/zec_usd';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Encoding: gzip, deflate, br',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Connection: keep-alive', 'Host: wex.nz',
					'Referer: https://wex.nz/exchange/usd_rur',
					'Upgrade-Insecure-Requests: 1',
					'User-Agent: '.$this->browser,]
			);

			$content = $this->request($url);

			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка покупки ZEC httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match("!input id='pair' value='(\d+?)' type='hidden'!iu", $content, $matches))
			{
				$pair = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки ZEC '.$this->_login);

				return false;
			}

			if(preg_match("!<span id='min_price'>(.+?)</span> USD!iu", $content, $matches))
			{
				$minPrice = $matches[1] * 1;
			}
			else
			{
				$this->error('Ошибка покупки ZEC '.$this->_login);

				return false;
			}

			if(preg_match("!id='token' type='hidden' value='(.+?)'!iu", $content, $matches))
			{
				$token = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки ZEC '.$this->_login);

				return false;
			}

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки ZEC '.$this->_login);

				return false;
			}

			if(preg_match('!<b id="balance1">(.+?)</b>!iu', $content, $matches))
			{
				$balanceUsd = $matches[1] * 1;
			}
			else
			{
				$this->error('Ошибка покупки ZEC '.$this->_login);

				return false;
			}

			$btcCount = str_replace(' ', '', formatAmount(($balanceUsd / $minPrice * 0.988) , 8));

			$minPrice = $minPrice + 20;

			$url = 'https://wex.nz/ajax/order';
			$postData = 'csrfToken='.$csrfToken.'&trade=buy&btc_count='.$btcCount.'&btc_price='.$minPrice.'&pair='.$pair.'&token='.$token;

			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: application/json,
				text/javascript, */*; q=0.01',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'Host: wex.nz', 'Referer: https://wex.nz/exchange/zec_usd',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
			]);

			$content = $this->request($url, $postData);

			if($this->lastHttpCode == 200)
			{
				$responceArr = json_decode($content, 1);

				if($responceArr['error'] == 'y')
				{
					if(preg_match("!class='tcenter'>(.+?)<!iu", $responceArr['data'], $matches))
					{
						$errorMsg = strip_tags($matches[1]);
						$this->error('Ошибка покупки ZEC '.$this->_login.' '.$errorMsg);

						return false;
					}
				}
				else
				{
					$result = strip_tags($responceArr['data']).' '.$this->_login;
					toLog($result);

					return $result;
				}
			}
			else
			{
				$this->error('Ошибка покупки ZEC httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}
		}
	}

	/**
	 * покупка USDT за USD
	 * @return bool|string
	 */
	public function buyUsdtUsd()
	{
		if($this->isAuth)
		{
			$url = 'https://wex.nz/exchange/usdt_usd';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Encoding: gzip, deflate, br',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Connection: keep-alive', 'Host: wex.nz',
					'Referer: https://wex.nz/exchange/usd_rur',
					'Upgrade-Insecure-Requests: 1',
					'User-Agent: '.$this->browser,]
			);

			$content = $this->request($url);

			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка покупки USDT httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match("!input id='pair' value='(\d+?)' type='hidden'!iu", $content, $matches))
			{
				$pair = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки USDT '.$this->_login);

				return false;
			}

			if(preg_match("!<span id='min_price'>(.+?)</span> USD!iu", $content, $matches))
			{
				$minPrice = $matches[1] * 1;
			}
			else
			{
				$this->error('Ошибка покупки USDT '.$this->_login);

				return false;
			}

			if(preg_match("!id='token' type='hidden' value='(.+?)'!iu", $content, $matches))
			{
				$token = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки USDT '.$this->_login);

				return false;
			}

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка покупки USDT '.$this->_login);

				return false;
			}

			if(preg_match('!<b id="balance1">(.+?)</b>!iu', $content, $matches))
			{
				$balanceUsd = $matches[1] * 1;
			}
			else
			{
				$this->error('Ошибка покупки USDT '.$this->_login);

				return false;
			}

			$btcCount = str_replace(' ', '', formatAmount(($balanceUsd / $minPrice * 0.98) , 8));

			$minPrice = $minPrice + 3;

			$url = 'https://wex.nz/ajax/order';
			$postData = 'csrfToken='.$csrfToken.'&trade=buy&btc_count='.$btcCount.'&btc_price='.$minPrice.'&pair='.$pair.'&token='.$token;

			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: application/json,
				text/javascript, */*; q=0.01',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'Host: wex.nz',
				'Referer: https://wex.nz/exchange/usdt_usd',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
			]);

			$content = $this->request($url, $postData);

			if($this->lastHttpCode == 200)
			{
				$responceArr = json_decode($content, 1);

				if($responceArr['error'] == 'y')
				{
					if(preg_match("!class='tcenter'>(.+?)<!iu", $responceArr['data'], $matches))
					{
						$errorMsg = strip_tags($matches[1]);
						$this->error('Ошибка покупки USDT '.$this->_login.' '.$errorMsg);

						return false;
					}
				}
				else
				{
					$result = strip_tags($responceArr['data']).' '.$this->_login;
					toLog($result);

					return $result;
				}
			}
			else
			{
				$this->error('Ошибка покупки USDT httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}
		}
	}

	/**
	 * @param $address
	 *
	 * @return array|bool
	 * запрос на вывод BTC
	 */
	public function withdrawBtc($address)
	{
		if($this->isAuth)
		{
			usleep($this->getPause());

			$url = 'https://wex.nz/exchange/btc_rur';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive', 'Host: wex.nz',
				'Referer: https://wex.nz/exchange/btc_usd',
				'Upgrade-Insecure-Requests: 1',
				'User-Agent: '.$this->browser,
			]);

			$content = $this->request($url);

			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка вывода BTC httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка вывода BTC не найден csrf-token'.$this->_login);

				return false;
			}

			if(preg_match("!id='token' type='hidden' value='(.+?)'!iu", $content, $matches))
			{
				$token = $matches[1];
			}
			else
			{
				$this->error('Ошибка вывода BTC не найден token '.$this->_login);

				return false;
			}

//			if(preg_match('!id="coin_fee">(.+?)<!iu', $content, $matches))
//			{
//				$coinFee = $matches[1] * 1;
//			}
//			else
//			{
//				$this->error('Ошибка вывода BTC не найден coin_fee '.$this->_login);
//
//				return false;
//			}

			//вычитаем сразу комсу за вывод
			if(preg_match('!<b id="balance2">(.+?)</b>!iu', $content, $matches))
			{
				$balanceBtc = formatAmount($matches[1] * 1, 8);
				$balanceBtc = $balanceBtc - 0.0003;
			}
			else
			{
				$this->error('Ошибка вывода BTC не найден id="balance2" '.$this->_login);

				return false;
			}

			$url = 'https://wex.nz/ajax/coins';
			$postData = 'csrfToken='.$csrfToken.'&act=withdraw'.'&sum='.$balanceBtc.'&address='.$address.'&coin_id=1'.'&token='.$token.'&otp=0';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders(['Accept: application/json, text/javascript, */*; q=0.01', 'Accept-Encoding: gzip, deflate, br', 'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3', 'Connection: keep-alive', 'Content-Length: '.strlen($postData), 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8', 'Host: wex.nz', 'Referer: https://wex.nz/exchange/btc_rur', 'User-Agent: '.$this->browser, 'X-Requested-With: XMLHttpRequest',]);

			$content = $this->request($url, $postData);

			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка вывода BTC httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match('!Письмо с дальнейшими инструкциями отправлено на вашу почту!iu', $content))
			{
				toLog('Создан запрос на вывод BTC = '.$balanceBtc.' address = '.$address.' email = '.$this->_login);

				return ['btc' => $balanceBtc, 'address' => $address,];
			}
			else
			{
				$this->error('Ошибка вывода BTC '.$this->_login.': '.strip_tags($content));

				return false;
			}
		}
	}

	/**
	 * @param $address
	 *
	 * @return array|bool
	 * запрос на вывод ZEC
	 */
	public function withdrawZec($address)
	{
		if($this->isAuth)
		{
			usleep($this->getPause());

			$url = 'https://wex.nz/exchange/zec_usd';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Encoding: gzip, deflate, br',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Connection: keep-alive', 'Host: wex.nz',
					'Referer: https://wex.nz/exchange/usd_rur',
					'Upgrade-Insecure-Requests: 1',
					'User-Agent: '.$this->browser,]
			);

			$content = $this->request($url);


			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка вывода ZEC httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка вывода ZEC не найден csrf-token'.$this->_login);

				return false;
			}

			if(preg_match("!id='token' type='hidden' value='(.+?)'!iu", $content, $matches))
			{
				$token = $matches[1];
			}
			else
			{
				$this->error('Ошибка вывода ZEC не найден token '.$this->_login);

				return false;
			}

//			if(preg_match('!id="coin_fee">(.+?)<!iu', $content, $matches))
//			{
//				$coinFee = $matches[1] * 1;
//			}
//			else
//			{
//				$this->error('Ошибка вывода BTC не найден coin_fee '.$this->_login);
//
//				return false;
//			}

			//вычитаем сразу комсу за вывод
			if(preg_match('!<b id="balance38">(.+?)</b>!iu', $content, $matches))
			{
				$balanceZec = str_replace(' ', '', formatAmount($matches[1] * 1, 8));
				$balanceZec = $balanceZec - 0.001;
			}
			else
			{
				$this->error('Ошибка вывода ZEC возможно баланс = 0 '.$this->_login);

				return false;
			}

			$url = 'https://wex.nz/ajax/coins';
			$postData = 'csrfToken='.$csrfToken.'&act=withdraw'.'&sum='.$balanceZec.'&address='.$address.'&coin_id=22'.'&token='.$token.'&otp=0';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: application/json, text/javascript, */*; q=0.01',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'Origin: https://wex.nz',
				'Referer: https://wex.nz/exchange/usd_rur',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
			]);

			$content = $this->request($url, $postData);

			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка вывода ZEC httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match('!Письмо с дальнейшими инструкциями отправлено на вашу почту!iu', $content))
			{
				toLog('Создан запрос на вывод ZEC = '.$balanceZec.' address = '.$address.' email = '.$this->_login);

				return ['zec' => $balanceZec, 'address' => $address,];
			}
			else
			{
				$this->error('Ошибка вывода ZEC '.$this->_login.': '.strip_tags($content));

				return false;
			}
		}
	}


	/**
	 * @param $address
	 *
	 * @return array|bool
	 * запрос на вывод USDT
	 */
	public function withdrawUsdt($address)
	{
		if($this->isAuth)
		{
			usleep($this->getPause());

			$url = 'https://wex.nz/exchange/usdt_usd';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Encoding: gzip, deflate, br',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Connection: keep-alive', 'Host: wex.nz',
					'Referer: https://wex.nz/exchange/usd_rur',
					'Upgrade-Insecure-Requests: 1',
					'User-Agent: '.$this->browser,]
			);

			$content = $this->request($url);


			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка вывода USDT httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка вывода USDT не найден csrf-token'.$this->_login);

				return false;
			}

			if(preg_match("!id='token' type='hidden' value='(.+?)'!iu", $content, $matches))
			{
				$token = $matches[1];
			}
			else
			{
				$this->error('Ошибка вывода USDT не найден token '.$this->_login);

				return false;
			}

//			if(preg_match('!id="coin_fee">(.+?)<!iu', $content, $matches))
//			{
//				$coinFee = $matches[1] * 1;
//			}
//			else
//			{
//				$this->error('Ошибка вывода USDT не найден coin_fee '.$this->_login);
//
//				return false;
//			}

			//вычитаем сразу комсу за вывод
			if(preg_match('!<b id="balance39">(.+?)</b>!iu', $content, $matches))
			{
				$balanceUsdt = str_replace(' ', '', formatAmount($matches[1] * 1, 2));
				$balanceUsdt = $balanceUsdt - 1;
			}
			else
			{
				$this->error('Ошибка вывода USDT возможно баланс = 0 '.$this->_login);

				return false;
			}

			$url = 'https://wex.nz/ajax/coins';
			$postData = 'csrfToken='.$csrfToken.'&act=withdraw'.'&sum='.$balanceUsdt.'&address='.$address.'&coin_id=23'.'&token='.$token.'&otp=0';

			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: application/json, text/javascript, */*; q=0.01',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'Origin: https://wex.nz',
				'Referer: https://wex.nz/exchange/usd_rur',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
			]);

			$content = $this->request($url, $postData);

			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка вывода USDT httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match('!Письмо с дальнейшими инструкциями отправлено на вашу почту!iu', $content))
			{
				toLog('Создан запрос на вывод USDT = '.$balanceUsdt.' address = '.$address.' email = '.$this->_login);

				return ['usdt' => $balanceUsdt, 'address' => $address,];
			}
			else
			{
				$this->error('Ошибка вывода USDT '.$this->_login.': '.strip_tags($content));

				return false;
			}
		}
	}

	/**
	 * @return bool
	 * по апи получаем ссылку подтверждения вывода, только последнюю и подтверждаем платеж
	 */
	public function confirmPaymentTutanota()
	{
		session_write_close();

		$threadName = 'selenium';

		if(Tools::threader($threadName))
		{
			$currentProxy = $this->parseProxyStr($this->proxy);

			$url = 'http://94.140.125.237/selenium/index.php?key=testtest&method=ConfirmPaymentTutanota'.
				'&email='.$this->_login.
				'&emailPass='.urlencode($this->emailPass).'&proxyIp='.$currentProxy['ip'].
				'&proxyPort='.$currentProxy['port'].'&proxyLogin='.$currentProxy['login'].'&proxyPass='.$currentProxy['pass'];

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
				$contentArr = json_decode($content, true);

				if(isset($contentArr['result']['confirmUrl']))
				{
					$url = $contentArr['result']['confirmUrl'];
					$this->sender->additionalHeaders = null;
					$this->_setHeaders([
						'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
						'Accept-Encoding: gzip, deflate, br',
						'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
						'Connection: keep-alive',
						'Host: wex.nz',
						'Upgrade-Insecure-Requests: 1',
						'User-Agent: '.$this->browser,
					]);

					$content = $this->request($url);

					if($this->lastHttpCode !== 200)
					{
						$this->error('Ошибка подтверждения вывода BTC httpCode = '.$this->lastHttpCode.' '.$this->_login);
						return false;
					}


					if(preg_match('!Вывод успешно подтвержден!', $content))
					{
						toLog('Вывод подтвержден '.$this->_login.' amount = '.$contentArr['result']['amount'].' address = '.$contentArr['result']['address']);
						return [
							'amount' => $contentArr['result']['amount'],
							'address' => $contentArr['result']['address'],
						];
					}

					elseif(preg_match('!Ошибка: Вывод уже подтвержден или отменен ранее!', $content))
					{
						$this->error('Ошибка: Вывод уже подтвержден или отменен ранее '.$this->_login);
						return false;
					}
					else
					{
						$this->error('Неизвестная ошибка вывода '.$this->_login);
						return false;
					}
				}
				elseif(isset($contentArr['error']['msg']))
				{
					$this->error($this->_login.' '.$contentArr['error']['msg']);
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
				$this->error('Ошибка получения ссылки подтверждения платежа по api '.$this->_login.' HttpCode = '.$this->lastHttpCode);
				return false;
			}
		}
		else
			toLog('поток уже запущен, пропускается '.$this->_login);

	}

	/**
	 * @param $transactionId
	 * @param $action
	 *
	 * @return bool
	 * отправляем повторное письмо с подтверждением вывода
	 * или отменяем вывод
	 */
	public function withdrawControl($transactionId, $action)
	{
		if($this->isAuth)
		{
			$url = 'https://wex.nz/exchange/btc_rur';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Encoding: gzip, deflate, br',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Connection: keep-alive', 'Host: wex.nz',
					'Referer: https://wex.nz/exchange/btc_usd',
					'Upgrade-Insecure-Requests: 1',
					'User-Agent: '.$this->browser,]
			);

			$content = $this->request($url);

			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка управления выводом httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка управления выводом не найден csrf-token '.$this->_login);
				return false;
			}

			if($action == self::ACTION_RESEND || $action == self::ACTION_CANCEL)
			{
				$url = 'https://wex.nz/ajax/billing';
				//csrfToken=13ed5c6cccecca39815a3846672888971de1810da2e96ba2ac0e9d932c01f7bf&act=sw_act&task=resend&id=716180651
				$postData = 'csrfToken='.$csrfToken.'&act=sw_act&task='.$action.'&id='.$transactionId;
				$this->sender->additionalHeaders = null;
				$this->_setHeaders([
					'Accept: */*',
					'Accept-Encoding: gzip, deflate, br',
					'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
					'Connection: keep-alive',
					'Content-Length: '.strlen($postData),
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'Host: wex.nz',
					'Referer: https://wex.nz/profile',
					'User-Agent: '.$this->browser,
					'X-Requested-With: XMLHttpRequest',
				]);

				$content = $this->request($url, $postData);

				if($this->lastHttpCode == 200)
				{
					if(preg_match("!Письмо с дальнейшими инструкциями отправлено на вашу почту!iu", $content))
					{
						$result = 'Повторно оправлено письмо подтверждения '.$this->_login;
						toLog($result);

						return true;
					}
					elseif(preg_match("!Транзакция успешно отменена!iu", $content))
					{
						$result = 'Транзакция #'.$transactionId.' успешно отменена '.$this->_login;
						toLog($result);

						return true;
					}
					elseif(preg_match("!class='tcenter'>(.+?)<!iu", $content, $matches))
					{
						$errorMsg = strip_tags($matches[1]);
						$this->error('Ошибка управления выводом  '.$this->_login.' '.$errorMsg);

						return false;
					}
					else
					{
						$this->error('Неизвестная ошибка управления выводом  '.$this->_login.' '.strip_tags($content));

						return false;
					}
				}
				else
				{
					$this->error('Ошибка управления выводом httpCode = '.$this->lastHttpCode.' '.$this->_login);

					return false;
				}
			}
			else
			{
				$this->error('Ошибка управления выводом '.$this->lastHttpCode.' '.$this->_login.' неверный параметр action');

				return false;
			}

		}
	}


	/**
	 * @return bool
	 * если при регистрации не была подтверждена почта,
	 * то делаем отправку письма с подтверждением привязки
	 */
	public function sendMessageToConfirmEmail()
	{
		if($this->isAuth)
		{
			$url = 'https://wex.nz/';
			$this->sender->additionalHeaders = null;
			$this->sender->followLocation = false;
			$this->_setHeaders([
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Host: wex.nz',
				'Upgrade-Insecure-Requests: 1',
				'User-Agent: '.$this->browser,
			]);
			$content = $this->request($url);

			if($this->lastHttpCode !== 200)
			{
				$this->error('Ошибка отправки письма с подтверждением привязки httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}

			if(!preg_match('!Ваш e-mail не подтвержден!iu', $content, $matches))
			{
				$this->error('Email уже подтвержден '.$this->_login);
				return false;
			}

			if(preg_match('!id="csrf-token" value="(.+?)"!iu', $content, $matches))
			{
				$csrfToken = $matches[1];
			}
			else
			{
				$this->error('Ошибка отправки письма с подтверждением привязки, не найден csrf-token '.$this->_login);
				return false;
			}

			$url = 'https://wex.nz/ajax/profile_edit';
			$postData = 'csrfToken='.$csrfToken.'&act=confirm_email';
			$this->sender->additionalHeaders = null;
			$this->_setHeaders([
				'Accept: */*',
				'Accept-Encoding: gzip, deflate, br',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Connection: keep-alive',
				'Content-Length: '.strlen($postData),
				'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
				'Host: wex.nz',
				'Referer: https://wex.nz/profile',
				'User-Agent: '.$this->browser,
				'X-Requested-With: XMLHttpRequest',
			]);

			$content = $this->request($url, $postData);

			if($this->lastHttpCode == 200)
			{
				if(preg_match("!Вам отправлено письмо!iu", $content))
				{
					$result = 'Отправка письма с подтверждением привязки '.$this->_login;
					toLog($result);

					return true;
				}
				else
				{
					$this->error('Неизвестная ошибка отправки письма с подтверждением привязки  '.$this->_login.' '.strip_tags($content));

					return false;
				}
			}
			else
			{
				$this->error('Ошибка отправки письма с подтверждением привязки httpCode = '.$this->lastHttpCode.' '.$this->_login);

				return false;
			}
		}
	}


	/**
	 * @return bool
	 * подтверждаем привязку почты tutanota к аккаунту wex
	 */
	public function confirmLinkMailTutanota()
	{
		session_write_close();

		$threadName = 'selenium';

		if(Tools::threader($threadName))
		{
			$currentProxy = $this->parseProxyStr($this->proxy);

			$url = 'http://94.140.125.237/selenium/index.php?key=testtest&method=ConfirmEmailLink'.
				'&email='.$this->_login.
				'&emailPass='.urlencode($this->emailPass).'&proxyIp='.$currentProxy['ip'].
				'&proxyPort='.$currentProxy['port'].'&proxyLogin='.$currentProxy['login'].'&proxyPass='.$currentProxy['pass'];

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
				$contentArr = json_decode($content, true);

				if(isset($contentArr['result']))
				{
					$url = $contentArr['result'];
					$this->sender->additionalHeaders = null;
					$this->_setHeaders([
						'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
						'Accept-Encoding: gzip, deflate, br',
						'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
						'Connection: keep-alive',
						'Host: wex.nz',
						'Upgrade-Insecure-Requests: 1',
						'User-Agent: '.$this->browser,
					]);

					$content = $this->request($url);

					if($this->lastHttpCode !== 200)
					{
						$this->error('Ошибка подтверждения привязки почты httpCode = '.$this->lastHttpCode.' '.$this->_login);
						return false;
					}

					if(preg_match('!Ваш E-mail успешно подтвержден', $content))
					{
						toLog('E-mail прикреплен '.$this->_login);
						$this->success('E-mail успешно прикреплен '.$this->_login);
					}

					elseif(preg_match('!Ошибка: Эта заявка уже была исполнена ранее!', $content))
					{
						$this->error('Ошибка: Эта заявка уже была исполнена ранее '.$this->_login);
						return false;
					}
					else
					{
						$this->error('Неизвестная подтверждения привязки почты '.$this->_login);
						return false;
					}
				}
				elseif(isset($contentArr['error']['msg']))
				{
					$this->error($this->_login.' '.$contentArr['error']['msg']);
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
				$this->error('Ошибка подтверждения привязки почты '.$this->_login.' HttpCode = '.$this->lastHttpCode);
				return false;
			}
		}
		else
			toLog('поток уже запущен, пропускается '.$this->_login);

	}

}