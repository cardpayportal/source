<?php
/**
 * @property Sender $_sender
 *
 * пример создания:
 * $tele2Bot = new Tele2Bot('79776321808','asdHSDg211qQ');
 */
class Tele2Bot
{

	const PAYMENT_DIRECTION_IN = 'in';
	const PAYMENT_DIRECTION_OUT = 'out';

	const PAYMENT_STATUS_SUCCESS = 'success';
	const PAYMENT_STATUS_WAIT = 'wait';
	const PAYMENT_STATUS_ERROR = 'error';

	private $_login = '';
	private $_formatedLogin = ''; //7+977+632-18-08
	private $_pass = '';
	private $_sender = null;	//Sender
	private $_requestInfo = array();	//Sender
	private $_lastContent = '';	//Sender
	private $_proxy;
	private $_workDir;    //рабочая папка бота
	private $_usersDir;    //папка с настройками пользователей
	private $_userDir;    //папка с настройками пользователя
	private $_fileConfig; //файл с настройками
	private $_config; //переменная с текущими конфигами

	public $errorMsg = '';
	public $errorCode = '';

	public function __construct($login, $pass)
	{
		$this->_login = $login;
		$this->_pass = $pass;

		$this->_workDir = DIR_ROOT.'protected/runtime/' . __CLASS__ . '/';
		$this->_usersDir = $this->_workDir . 'users/';
		$this->_userDir = $this->_usersDir . $this->_login . '/';
		$this->captchaDir = $this->_workDir.'captcha/';

		return $this->_initUser();
	}

	protected function _initUser()
	{
		clearstatcache();

		//папка бота
		if (!file_exists($this->_workDir)) {
			if (!mkdir($this->_workDir))
				toLog('error create ' . basename($this->_workDir));

			chmod($this->_workDir, 0777);
		}

		//папка пользователей
		if (!file_exists($this->_usersDir)) {
			if (!mkdir($this->_usersDir))
				toLog('error create ' . basename($this->_usersDir));

			chmod($this->_usersDir, 0777);
		}

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

		$config = json_decode(file_get_contents($this->_fileConfig), 1);

		if (file_put_contents($this->_fileConfig, json_encode($config)) === false)
		{
			toLog('error write ' . $this->_fileConfig);
			return false;
		}


		$this->_sender = new Sender();
		$this->_sender->browser = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:66.0) Gecko/20100101 Firefox/66.0';
		$this->_sender->followLocation = false;
		$this->_sender->pause = 1;
		$this->_sender->timeout = 30;
		$this->_proxy = '4zq0TiSU6H:EkaterinaUrahova@77.220.205.254:47930';
		$this->_sender->cookieFile = $fileCookie;

		return $this->_logIn();
	}

	private function _request($url, $postData = false, $referer = '')
	{

		$this->_lastContent = $this->_sender->send($url, $postData, $this->_proxy, $referer);

		$this->_requestInfo = $this->_sender->info;

		return $this->_lastContent;
	}

	private function _logIn()
	{
		$authRes = $this->isAuth();

		if($authRes)
			return true;
		elseif($authRes === null)
		{
			toLogError('Ошибка tele2: '.arr2str($this->_requestInfo));
			return false;
		}

		$url = 'https://login.tele2.ru/ssotele2/wap/auth?csrf';
		$this->_sender->followLocation = true;
		$this->_sender->additionalHeaders = null;
		$this->_sender->additionalHeaders = [
			'Host: login.tele2.ru',
			'User-Agent: '.$this->_sender->browser,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',

		];
		$content = $this->_request($url);

		if(!preg_match('!value="(.+?)" name="_csrf" type="hidden"!iu', $content, $matches))
		{
			toLogError('Ошибка tele2 : step1');
			return false;
		}
		else
			$csrf = $matches[1];

		$this->_sender->additionalHeaders = null;
		//формат логина в запросе: 7+977+632-18-08
		if(preg_match('!(\d{1})(\d{3})(\d{3})(\d{2})(\d{2})!iu', $this->_login, $matches))
			$this->_formatedLogin = $matches[1].'+'.$matches[2].'+'.$matches[3].'-'.$matches[4].'-'.$matches[5];
		else
		{
			toLogError('Ошибка tele2 : ошибка форматирования логина');
			return false;
		}


		$url = 'https://login.tele2.ru/ssotele2/wap/auth/submitLoginAndPassword';
		$postData = '_csrf='.$csrf.'&authBy=BY_PASS&'.
			'pNumber=%2B'.$this->_formatedLogin.'&password='.$this->_pass.'&rememberMe=true';
		$this->_sender->additionalHeaders = [
			'Content-Type: application/x-www-form-urlencoded',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Host: login.tele2.ru',
			'Origin: https://login.tele2.ru',
			'Referer: https://login.tele2.ru/ssotele2/wap/auth?csrf',
			'Cache-Control: max-age=0',
		];

		$content = $this->_request($url, $postData);

		$authRes = $this->isAuth();

		if($authRes)
			return true;
		elseif($authRes === null)
		{
			toLogError('Ошибка tele2: '.arr2str($this->_requestInfo));
			return false;
		}
	}

	private function _logOut()
	{

	}

	/**
	 * @return true|false|null (успех|неудача|ошибка запроса-повторить)
	 */
	public function isAuth()
	{
		$url = 'https://login.tele2.ru/ssotele2/wap/profile/settings?validity_verify_code=10'.
			'+%D0%BC%D0%B8%D0%BD%D1%83%D1%82&baseUrl=https%3A%2F%2Flogin.tele2.ru%3A443%2Fssotele2';
		$this->_sender->additionalHeaders = null;

		$this->_sender->additionalHeaders = [
			'Host: login.tele2.ru',
			'User-Agent: '.$this->_sender->browser,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
		];

		$content = $this->_request($url);

		if(preg_match('!class="link-lg link-lg-small">Выйти<!iu', $content))
		{
			return true;
		}
		elseif($this->_requestInfo['httpCode'][0] == 302)
		{
			return false;
		}
		elseif($this->_requestInfo['httpCode'][0] == 200)
		{
			$this->errorMsg = 'authError contentLength='.strlen($content);
			return false;
		}
		else
		{
			$this->errorMsg = 'authError code='.$this->_requestInfo['httpCode'][0];
			return null;
		}
	}


	/**
	 * запрос истории
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param string $direction in|out
	 * @return array|bool
	 *
	 *
	Array(
	[amount] => 321
	[direction] => in
	[timestamp] =>
	[date] => 22.06.2019 08:54
	[label] =>
	[title] => Платеж через платежные системы
	[status] => success
	)
	 */
	public function getHistory($timestampStart = 0, $timestampEnd = 0)
	{
		$result = [];

		$params = [];

		if($timestampStart)
			$params['from'] = date('c', $timestampStart);
		else
			return false;

		if($timestampEnd)
			$params['till'] = date('c', $timestampEnd);
		else
			return false;

		if(!$this->isAuth())
			$this->_logIn();

		$this->_sender->additionalHeaders = null;
		$url = 'https://msk.tele2.ru/api/subscribers/'.$this->_login.'/payments?fromDate='.urlencode($params['from']).'&toDate='.urlencode($params['till']);

		$this->_sender->additionalHeaders = [
			'Host: msk.tele2.ru',
			'User-Agent: '.$this->_sender->browser,
			'Accept: application/json, text/plain, */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Tele2-User-Agent: web',
			'Cache-Control: no-cache',
		];

		if($response = json_decode($this->_request($url), 1))
		{
			if($response['meta']['status'] == 'OK')
			{
				foreach($response['data'] as $operation)
				{
					$status = self::PAYMENT_STATUS_SUCCESS;

					$arr = [
						'amount' => $operation['sum']['amount'],
						'direction' => ($operation['sum']['amount'] > 0) ? self::PAYMENT_DIRECTION_IN : self::PAYMENT_DIRECTION_OUT,
						'timestamp' => strtotime($operation['payDate']),
						'date' => date('d.m.Y H:i', strtotime($operation['payDate'])),
						'label' => $operation['description'],
						'title' => $operation['type'],
						'status' => $status,
					];

					$result[] = $arr;
				}
			}
			else
			{
				toLogError('Tele2Account error:  ошибка платежей '.Tools::arr2Str($response));
				return false;
			}

			return $result;
		}
		else
			return false;
	}
}