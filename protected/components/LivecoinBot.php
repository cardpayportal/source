<?php
class LivecoinBot
{
	const DOMAIN = 'https://www.livecoin.net';

	private $_login;
	private $_pass;

	//путь к временному файлу js для авторизации
	private $_jsEncryptorFile;
	//образец файла(заменить {exponent}, {modulus}, {pass})
	private $_jsEncryptorFileSample;

	private $_sender;
	private $_proxy;
	private $_userDir;

	public $error;
	public $errorCode;

	public function __construct($login, $pass, $proxy, $browser)
	{
		$this->_login = $login;
		$this->_pass = $pass;
		$this->_jsEncryptorFile = DIR_ROOT.'protected/runtime/livecoinEncryptor'.rand(999,99999999).'.js';
		$this->_jsEncryptorFileSample = __DIR__.'/'.__CLASS__.'/encryptorSample.js';

		$this->_userDir = __DIR__.'/'.__CLASS__.'/users/'.str_replace('@', '_', $this->_login).'/';

		$this->_sender = new Sender;
		$this->_sender->followLocation = false;
		$this->_sender->cookieFile = $this->_userDir.'cookie.txt';
		$this->_sender->browser = $browser;
		$this->_proxy = $proxy;
		$this->_sender->additionalHeaders = [
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
		];

		if(!$this->_initUser())
			return false;

		if(!$this->isAuth())
			$this->auth();
	}

	/**
	 * @param null $content
	 * @return bool|null
	 */
	public function isAuth($content = null)
	{
		if($content === null)
			$content = $this->_request(self::DOMAIN.'/ru/site/login');

		//проверка догрузился ли контент
		if(!preg_match('!<div class="footer_el_title"><span>О нас</span></div>!', $content))
		{
			$this->error = 'неверный контент: htpCode='.$this->_sender->info['httpCode'][0].' | '.__LINE__;
			return null;
		}

		return !preg_match('!Авторизация</title>!iu', $content);
	}

	/**
	 * @return bool
	 */
	public function auth()
	{
		$content = $this->_request(self::DOMAIN.'/ru/site/login');

		//токен
		if(!preg_match('!"YII_CSRF_TOKEN":"(.+?)"!', $content, $res))
		{
			//print_r($this->_sender);
			//echo $content;
			$this->error = 'errorAuth1';
			return false;
		}

		$token = $res[1];

		//капча
		if(!preg_match('!<img id="yw0" src="(/.+?)" alt="" />!isu', $content, $res))
		{
			$this->error = 'errorAuth2';
			return false;
		}

		$captchaContent = $this->_request(self::DOMAIN.$res[1]);

		$captchaCode = Tools::recognize(['imageContent'=>$captchaContent]);


		if(!$captchaCode)
		{
			$this->error = 'errorAuth3';
			echo "\n".Tools::$error;
			return false;
		}

		$headers = [
			'Accept: application/json, text/javascript, */*; q=0.01',
			'X-Requested-With: XMLHttpRequest',
		];

		$postData = http_build_query([
			'ajaxMethod' => 'GetSessionSecurityAttributes',
			'captchaCode' => $captchaCode,
			'YII_CSRF_TOKEN' => $token,
		]);

		$content = $this->_request(self::DOMAIN.'/site/login', $postData, $headers);

		if(!$attributes = @json_decode($content, true))
		{
			echo "\n".$content;
			$this->error = 'errorAuth4';
			return false;
		}

		//получим jsEncryptor ключ
		$jsEncryptorKey = $this->_jsEncryptor($attributes['modulus'], $attributes['exponent']);

		//сама авторизация
		$postData = http_build_query([
			'username' => $this->_login,
			'password' => $jsEncryptorKey,
			'stat' => '',//'1440|900|24|24|Mozilla/5.0 (Macintosh; Intel Mac OS X; rv:53.0.1) Gecko/20500302 Firefox/53.0.1|true|ru-RU|MacIntel||30.0.0.154|f10d085606cbb60a81ec1cd77ead32e6',
			'rememberMe' => '1',
			'captchaCode' => $captchaCode,
			'ajaxMethod' => 'LoginByPassword',
			'YII_CSRF_TOKEN' => $token,
		]);

		$content = $this->_request(self::DOMAIN.'/site/login', $postData);

		if(preg_match('!"logged":true!', $content))
			return true;
		else
		{
			$this->error = 'error auth5, contnt: '.$content;
			return false;
		}
	}

	/**
	 * @param string $modulus
	 * @param string $exponent
	 * @return string|bool
	 */
	private function _jsEncryptor($modulus, $exponent)
	{
		$jsContent = str_replace(['{exponent}', '{modulus}', '{pass}'],
			[$exponent, $modulus, $this->_pass], file_get_contents($this->_jsEncryptorFileSample));

		if(file_put_contents($this->_jsEncryptorFile, $jsContent) === false)
		{
			$this->error = 'error jsEncryptor save file';
			return false;
		}

		$val = trim(exec('phantomjs '.$this->_jsEncryptorFile));

		//хоть какая то проверка значения
		if(strlen($val) !== 256)
		{
			$this->error = 'error phantomjs';
			return false;
		}

		return $val;
	}

	private function _request($url, $postData = null, $headers = [])
	{
		if($headers)
			$this->_sender->additionalHeaders = $headers;

		$content = $this->_sender->send($url, $postData, $this->_proxy);

		//случайная задержка при запросах
		usleep(rand(500000, 2000000));

		return $content;
	}

	/**
	 * создание папки юзера
	 */
	private function _initUser()
	{
		if(!file_exists($this->_userDir))
		{
			if(!mkdir($this->_userDir))
			{
				$this->error = 'ошибка создания директории пользователя';
				return false;
			}

			chmod($this->_userDir, 0777);
		}

		if(!file_exists($this->_sender->cookieFile))
		{
			if(file_put_contents($this->_sender->cookieFile, '') === false)
			{
				$this->error = 'ошибка создания cookie';
				return false;
			}
		}

		return true;
	}

	public function getBalance()
	{
		$content = $this->_request(self::DOMAIN.'/ru/trade/index');

		if(preg_match('!<span class="balance">(.+?)</span>!', $content, $res))
		{
			$this->error = 'error balance, content: '.$content;
			return false;
		}

		return trim(str_replace(['~'], [''], $res[1])) * 1;
	}


}