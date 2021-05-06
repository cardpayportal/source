<?php
use Facebook\WebDriver\Firefox\FirefoxPreferences;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\Remote\WebDriverBrowserType;
use Facebook\WebDriver\Cookie;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Firefox\FirefoxDriver;
use Facebook\WebDriver\Firefox\FirefoxProfile;
use Facebook\WebDriver\Firefox;
use Facebook\WebDriver\WebDriverCapabilities;
use Facebook\WebDriver\WebDriverPlatform;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;

//use Symfony\Component\Config\Definition\Exception\Exception;
//use JonnyW\PhantomJs\Client;
/**
 * @property string id
 * @property string cards
 * @property array cardsArr
 * @property string phones
 * @property array phonesArr
 * @property string settings
 * @property array settingsArr
 * @property int date_add
 * @property string payment_type
 * @property string proxy
 * @property string browser
 * @property Facebook\WebDriver\Remote\RemoteWebDriver $driver
 * @property string session_id
 */
class SimBot extends Model
{
	const SCENARIO_ADD = 'add';

	//todo: добавить парсер user agent  строки чтобы выбирать по типу
	//const DEFAULT_BROWSER = 'firefox';
	const DEFAULT_PROXY_TYPE = 'http';	//http|socks5
	const DEFAULT_BROWSER = 'firefox';

	const OS_WINDOWS = 'windows';
	const OS_LINUX = 'linux';
	const OS_MACOS = 'macos';

	const ACTIVE_BOT_MAX = 10;	//максимум запущеных ботов
	const CARD_MAX = 5;			//максимум карт на одного бота
	//const PHONE_MAX = 5;		//максимум телефонов на бота

	public $driver;
	public $cookieFile;
	private $runtimeDir;
	private $configDir;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{sim_bot}}';
	}

	public function rules()
	{
		return [
			['payment_type', 'in', 'range' => array_keys(SimTransaction::getPaymentTypeArr()), 'allowEmpty'=>false],
//			['id', 'length', 'max'=>20, 'min'=>1],
//			['id', 'unique', 'className' => __CLASS__, 'attributeName' => 'id', 'on'=>self::SCENARIO_ADD],
//			['login', 'match', 'pattern' => '!^(9\d{9})$!', 'allowEmpty' => false],
//			['login', 'unique', 'className' => __CLASS__, 'attributeName' => 'login', 'allowEmpty' => false],
//			['client_id', 'exist', 'className' => 'Client', 'attributeName' => 'id', 'allowEmpty' => false],
//			//['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id'],
//			//баланс будет пересчитываться внутри
//			//['balance', 'numerical', 'min'=>0, 'max'=>999999],
//			//['balance', 'default', 'value'=>0, 'setOnEmpty'=>true, 'on'=>self::SCENARIO_ADD],
//			['status', 'in', 'range' => array_keys(self::getStatusArr())],
		];
	}

	protected function beforeSave()
	{
		if ($this->scenario == self::SCENARIO_ADD) {
			$this->date_add = time();
		}
		return parent::beforeSave();
	}

	/**
	 *
	 * @param array $params[
	 * 	'paymentType' => 'yandex',
	 * 	'cardNumber' => '4444333322221111',
	 * 	'phoneNumber' => '9998887766',
	 * 	'botId' => 'yandex0101',
	 * ]
	 *
	 * @return self|bool
	 */
	public static function getBot($params)
	{
//		if(!$params['cardNumber'] or !$params['phoneNumber'] or !$params['paymentType'])
//			return self::error('техническая ошибка1', 'не указаны необходимые параметры', $params);

		$waitSec = 40;
		$waited = 0;	//сколько прождано

		for($i=1; $i<=$waitSec; $i++)
		{
			$activeBots = self::getActiveBots($params['paymentType']);

			if(count($activeBots) < self::ACTIVE_BOT_MAX)
				break;

			if($i === 1)
				self::log('таймер ожидания');

			sleep(1);
			$waited++;
		}

		if(!$bot = self::pickModel($params))
			if(!$bot = self::addBot($params))
				return false;

		$bot->updateProxy();

		//если бот занят то ждем пока освободится
		for($i=1; $i<=$waitSec; $i++)
		{
			if(!$bot->isActive())
				break;

			if($waited >= $waitSec)
			{
				self::log('слишком долго ждем бота '.$bot->id);
				break;
			}

			if($i === 1)
				self::log('таймер занятости бота '.$bot->id);

			sleep(1);
			$waited++;
		}

		if($bot->isActive())
			return self::error('техническая ошибка 16.2, повторите запрос позже'
				, 'бот '.$bot->id.' все еще занят2');

		//итого прождано максимум 120сек перед стартом

		if(!$bot->createSession())
			return false;

		$bot->addCard($params['cardNumber']);
		$bot->addPhone($params['phoneNumber']);
		$bot->save();

		return $bot;
	}

	private static function error($msgPublic = '', $msgPrivate = '', $params = [])
	{
		self::$lastError = $msgPublic;

		if(!$msgPrivate)
			$msgPrivate = $msgPublic;

		if($params)
			$msgPrivate .= Tools::arr2Str($params);

		self::log($msgPrivate);

		return false;
	}

	private static function log($msg)
	{
		Tools::log('SimBot: '.$msg, null, null, 'test');
	}

	/**
	 * выбор подходящей модели для запроса
	 * если явно указан botId  то вернуть именно его
	 * далее поиск по карте
	 * далее поиск по
	 * @param array $params[
	 * 		'cardNumber'=>'',
	 * 		'phoneNumber'=>'',
	 * 		'botId'=>'yandex0101',
	 * 		'paymentType'=>'mts|yandex',
	 * ]
	 * @return self|false
	 */
	private static function pickModel($params)
	{
		$model = false;

		if($params['botId'])
		{
			$model = self::getModel(['id'=>$params['botId']]);
		}
		elseif($params['cardNumber'])
		{
			$model = self::model()->find("`cards` LIKE '%{$params['cardNumber']}%'"
			."AND `payment_type`='{$params['paymentType']}'");

			if(!$model)
			{
				$models = self::model()->findAll("`payment_type`='{$params['paymentType']}'");
				/**
				 * @var self[] $models
				 */

				//test если есть свободная очередь и ботов в базе мало то лучше насоздавать новых
				$activeBots = self::getActiveBots($params['paymentType']);

				if(count($activeBots) < self::ACTIVE_BOT_MAX -1 and count($models) < self::ACTIVE_BOT_MAX*1.5)
					return false;

				shuffle($models);

				//ищем первый попавшийся бот с немаксимальным кол-вом карт и не активный в данный момент
				foreach($models as $model)
				{
					if(count($model->cardsArr) < self::CARD_MAX and !$model->isActive())
						break;
				}
			}
		}


		return $model;
	}

	/**
	 * генерация нового бота по параметрам
	 * @param array $params[
	 * 	'paymentType' => 'yandex',
	 * 	'cardNumber' => '4444333322221111',
	 * 	'phoneNumber' => '9998887766',
	 * ]
	 * @return self|bool
	 */
	private static function addBot(array $params)
	{
		if(self::model()->find("`cards` LIKE '%{$params['cardNumber']}%' AND `payment_type`='{$params['paymentType']}'"))
			return self::error('техническая ошибка1', 'бот с такой картой уже имеется', $params);

		$browser = self::getBrowser();
		$proxy = self::getProxy(self::DEFAULT_PROXY_TYPE, $params['paymentType']);
		$screen = self::getScreen();
		$os = self::getOs($browser);

		if(!$browser or !$proxy or !$screen or !$os)
		{
			if(YII_DEBUG)
			{
				echo $browser.' |'.$proxy.'|'.$screen.'|'.$os;
			}

			return self::error('техническая ошибка5', 'addBot: не указаны необходимые параметры: BROWSER: '
				.$browser.', PROXY: '.$proxy.', SCREEN: '.$screen.', OS: '.$os, $params);
		}

		$settings = [
			'browser' => $browser,
			'proxy' => $proxy,
			'screen' => $screen,
			'os' => $os,
		];

		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->payment_type = $params['paymentType'];
		$model->id = $model->generateId();
		$model->browser = $settings['browser'];
		$model->proxy = $settings['proxy'];
		$model->addCard("{$params['cardNumber']}");
		$model->addPhone($params['phoneNumber']);
		$model->settingsArr = $settings;

		if(!$model->save())
			return false;

		return $model;

	}

	public function addPhone($str)
	{
		if(!$str)
			return;

		$arr = $this->getPhonesArr();

		if(array_search($str, $arr) === false)
		{
			$arr[] = $str;
			$this->phonesArr = $arr;
		}
	}

	public function addCard($str)
	{
		if(!$str)
			return;

		$arr = $this->getCardsArr();

		if(array_search($str, $arr) === false)
		{
			$arr[] = $str;
			$this->cardsArr = $arr;
		}
	}

	/**
	 * todo: добавить парсер user agent  строки чтобы выбирать по типу
	 * @return string
	 */
	private static function getBrowser()
	{
		$browserFile = realpath(__DIR__.'/../').'/config/browser.txt';

		$browsers = file($browserFile);

		return trim($browsers[array_rand($browsers)]);
	}

	/**
	 * @param string $proxyStr
	 * @return array|bool
	 */
	private static function parseProxyStr($proxyStr)
	{
		if(preg_match('!(http|socks5)://(([^:]+?):([^@]+?)@|)(.+?):(\d{2,7})!', $proxyStr, $match))
		{
			return [
				'type'=>$match[1],
				'login'=>$match[3],
				'pass'=>$match[4],
				'ip'=>$match[5],
				'port'=>$match[6],
			];
		}

		return false;
	}

	/**
	 * уникальный прокси
	 * @param string $type прокси тип
	 * @param string $paymentType тип платежа

	 * @return string|false
	 */
 	private static function getProxy($type, $paymentType)
	{
		$proxyFile = realpath(__DIR__.'/../').'/config/proxy.txt';

		$proxies = file($proxyFile);

		foreach($proxies as $proxyStr)
		{
			$proxyStr = trim($proxyStr);

			if(!$parseArr = self::parseProxyStr($proxyStr))
				continue;

			if($parseArr['type'] == $type)
			{
				if(!self::model()->find("`proxy` LIKE '%{$parseArr['ip']}%' AND `payment_type`='$paymentType'"))
					return $proxyStr;
			}
		}

		return false;
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	/**
	 * разрешение экрана
	 * @return string
	 */
	private static function getScreen()
	{
		$file = realpath(__DIR__.'/../').'/config/screenResolution.txt';

		$arr = file($file);

		return trim($arr[array_rand($arr)]);
	}

	/**
	 * операционная система
	 * если из браузера получить не удалось то рандом
	 * @param string $browser
	 * @return string
	 */
	private static function getOs($browser = '')
	{
		$randArr = [
			self::OS_LINUX, self::OS_WINDOWS, self::OS_MACOS
		];

		if(preg_match('!linux!i', $browser))
			return self::OS_LINUX;
		elseif(preg_match('!windows!i', $browser))
			return self::OS_WINDOWS;
		elseif(preg_match('!Mac OS!i', $browser))
			return self::OS_MACOS;
		else
		{
			return $randArr[array_rand($randArr)];
		}
	}

	/**
	 * @param array $arr
	 */
	public function setPhonesArr(array $arr)
	{
		$this->phones = json_encode($arr);
	}

	/**
	 * @param array $arr
	 */
	public function setCardsArr(array $arr)
	{
		$this->cards = json_encode($arr);
	}

	/**
	 * @return array
	 */
	public function getCardsArr()
	{
		if(!$this->cards)
			return  [];

		return json_decode($this->cards, true);
	}

	public function getPhonesArr()
	{
		if(!$this->phones)
			return  [];

		return json_decode($this->phones, true);
	}

	/**
	 * @param string $cardStr
	 * @return array|bool
	 */
	private function parseCardStr($cardStr)
	{
		if(preg_match('!(\d{16}) (\d\d)/(\d\d) (\d\d\d)!', $cardStr, $match))
		{
			return [
				'number' => $match[1],
				'm' => $match[1],
				'y' => $match[1],
				'cvv' => $match[1],
			];
		}

		return false;
	}

	public function getSettingsArr()
	{
		if(!$this->settings)
			return [];

		return json_decode($this->settings, true);
	}

	public function setSettingsArr(array $arr)
	{
		$this->settings = json_encode($arr);
	}

	private function generateId()
	{
		for($i=1; $i<=1000; $i++)
		{
			$id = $this->payment_type.Tools::generateCode('0123456789', 4);

			if(!self::getModel(['id'=>$id]))
				return $id;
		}
	}

	private function createSession()
	{
		$this->runtimeDir = DIR_ROOT.'protected/runtime/sim';
		$this->configDir = realpath(__DIR__.'/../').'/config';

		Tools::threader($this->id);

		$this->cookieFile = $this->runtimeDir.'/cookie/'.$this->id.'.txt';

		if(!file_exists($this->cookieFile))
		{
			if(file_put_contents($this->cookieFile, '') === false)
				return self::error('техническая ошибка6', 'ошибка записи в '.$this->cookieFile);
		}

		if(!$this->startDriver())
			return false;;

//		$this->session_id = $this->driver->getCapabilities()->getCapability('sessionId');
//		echo 'sess: '.$this->session_id;
//		$this->save();

		register_shutdown_function(function($bot){
			/**
			 * @var SimBot $bot
			 */
			//test
			$bot->saveCookies();
			$bot->driver->close();
			$bot->driver->quit();
		}, $this);

		return true;
	}

	/**
	 * @return string|bool
	 */
	private function startProxy()
	{
		$params = [
			'method' => 'startProxy',
			'proxy' => $this->proxy,
			'proxyId' => $this->id,
		];

		if($result = self::hubApi($params))
			register_shutdown_function([$this, 'stopProxy']);

		return $result;
	}

	public function stopProxy()
	{
		$params = [
			'method' => 'stopProxy',
			'proxyId' => $this->id,
		];

		return self::hubApi($params);
	}

	private static function hubApi(array $params)
	{
		$sender = new Sender();
		$sender->useCookie = false;
		$sender->timeout = 60;
		$config = Yii::app()->getModule('sim')->config;
		$params['key'] = $config['selenoidApiKey'];

		$content = $sender->send($config['selenoidApiUrl'], http_build_query($params));
		$responseArr = @json_decode($content, true);

		if(!$responseArr)
			return self::error('техническая ошибка4', 'json error: '.$content);

		if($responseArr['errorMsg'])
			return self::error('техническая ошибка5', 'hubApi error: '.$responseArr['errorMsg']);

		return $responseArr['result'];
	}

	private function startDriver()
	{
//		if(!$localProxy = $this->startProxy())
//			return self::error('техническая ошибка 3', 'ошибка старта прокси '.self::$lastError);

		sleep(2);

//		$localProxyParams = self::parseProxyStr($localProxy);

		$hubConfig = $this->hubApi(['method' => 'getConfig']);
		$maxSessionCount = $hubConfig['maxSessionCount'];
		$hubUrl = $hubConfig['hubUrl'];
		$screenshotDir = $hubConfig['screenshotsPath'];
		$screenshotWebPath = $hubConfig['screenshotsWebPath'];

		$caps = new DesiredCapabilities([
			WebDriverCapabilityType::BROWSER_NAME => WebDriverBrowserType::CHROME,
			WebDriverCapabilityType::PLATFORM => WebDriverPlatform::ANY,
		]);


		$caps->setCapability('screenResolution', $this->settingsArr['screen']);

		$version = $this->settingsArr['browserVersion'];

		if($version)
			$caps->setCapability('version', $version);

		$caps->setCapability('timeZone', 'Europe/Moscow');
		$caps->setCapability('env', ["LANG=ru_RU.UTF-8", "LANGUAGE=ru:ru", "LC_ALL=ru_RU.UTF-8"]);
		$caps->setCapability('name', $this->id);

		$options = new ChromeOptions();

		$options->addArguments([
			'--user-agent='.$this->settingsArr['browser'],
//			'--headless',
//			'--disable-gpu',
			'--no-sandbox',
//			"--proxy-server={$localProxyParams['ip']}:{$localProxyParams['port']}",
			'--disable-bundled-ppapi-flash',
			'--start-maximized',
			'--lang=ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'--dns-prefetch-disable',
//			'--disable-extensions',
//			'--profile-directory=Default',
//			'--incognito',
//			'--disable-plugins-discovery',
//			'--disable-images',
			//для определения по железу
			'--disable-webgl',
			'--disable-3d-apis',

		]);

		//прокси с авторизацией
		$pluginFile = $this->startProxyPlugin();
		$options->addExtensions([$pluginFile]);

		//не говорим браузеру что мы бот
		$options->setExperimentalOption('excludeSwitches', ['enable-automation']);

		//canvas fingerprint
		$pluginFile = $this->configDir.'/fingerprintPlugin.zip';
		$options->addExtensions([$pluginFile]);

		//утечка ip по webRTC
		$pluginFile = $this->configDir.'/webrtcPlugin.zip';
		$options->addExtensions([$pluginFile]);


		$caps->setCapability(ChromeOptions::CAPABILITY, $options);

		//$caps->setCapability('enableVNC', true);

		$this->driver = RemoteWebDriver::create($hubUrl, $caps, 60000, 60000);
//		$sessionId = $this->driver->getCapabilities()->getCapability('sessionId');
//		$this->driver = RemoteWebDriver::createBySessionID($sessionId, $hubUrl);
		//$this->driver ->setSessionID($sessionId);

		return true;
	}

	private function saveCookies()
	{
		if($cookies = $this->driver->manage()->getCookies())
			return file_put_contents($this->cookieFile, serialize($cookies));
		else
			return true;
	}

	public function loadCookies()
	{
		$this->driver->manage()->deleteAllCookies();

		$cookies = unserialize(file_get_contents($this->cookieFile));

		if(is_array($cookies))
		{
			foreach($cookies as $cookie)
				$this->driver->manage()->addCookie($cookie);

			return true;
		}
		else
			return true;
	}

	public function deleteCookies()
	{
		return file_put_contents($this->cookieFile, '');
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public function request($url)
	{
		$this->driver->get($url);
		return $this->driver->getPageSource();
	}

	/**
	 * @param string $url
	 * @param array $postArr
	 * @return string
	 */
	public function requestPost($url, $postArr)
	{
		$form = '<form id="dynForm" action="'.$url.'" method="post">';

		foreach($postArr as $name=>$val)
			$form .= '<input type="hidden" name="'.$name.'" value="'.$val.'"/> ';

		$form .= '<input type="submit"/> </form>';

		$script ='
			document.body.innerHTML += \''.$form.'\'
			document.getElementById("dynForm").submit();
		';

		return $this->driver->executeScript($script);
	}

	/**
	 * ждет появления элемента $waitTimeout секунд, возвращает элемент
	 * @param string $xpath
	 * @param int $waitTimeout
	 * @return WebDriverElement
	 */
	public function findElement($xpath, $waitTimeout = 0)
	{
		$selector = WebDriverBy::xpath($xpath);

		if($waitTimeout)
			$this->driver->wait($waitTimeout)->until(WebDriverExpectedCondition::presenceOfAllElementsLocatedBy($selector));

		return $this->driver->findElement(WebDriverBy::xpath($xpath));
	}

//	public function clearScreenshots()
//	{
//		return Tools::clearDir($this->screenshotsDir);
//	}

	/**
	 * скроллит на элемент, вбивает текст
	 * @param WebDriverElement $element
	 * @param string $text
	 * @return bool
	 */
	public function sendKeys($element, $text)
	{
		if(!$element)
			return $this->error('element не передан');

		if(!$text)
			return $this->error('text не передан');

		$this->scrollTo($element);

		return $element->sendKeys($text);
	}

	/**
	 * @param WebDriverElement $element
	 * @return bool
	 */
	public function scrollTo($element)
	{
		//$scrollX = $element->getLocationOnScreenOnceScrolledIntoView()->getX();
		$scrollY = $element->getLocationOnScreenOnceScrolledIntoView()->getY();
		//echo "\n cords:" . var_dump($scrollY);
		return $this->driver->executeScript("window.scroll(0, $scrollY)");
	}


	/**
	 * вставить в текущую страницу js код (по ссылке или содержимое)
	 * @param string $codeStr
	 * @param string $codeSrc
	 */
	public function includeJs($codeStr = '', $codeSrc = '')
	{
		if(!$codeStr and !$codeStr)
			return;

		$code = 'var script = document.createElement("div");';

		if($codeStr)
			$code .= 'script.innerHTML = "'.addcslashes($codeStr, '"').'";';
		else
			$code .= 'script.src = "'.$codeSrc.'";';

		$code .= 'document.body.appendChild(script);';

		$this->driver->executeScript($code);
	}

	/**
	 * ajax-запрос со страницы сайта. требует наличия jquery
	 * @param string $url
	 * @param bool|string $postStr
	 * @return string
	 */
	public function requestAjax($url, $postStr = false)
	{
		$requestType = ($postStr !== false) ? 'POST' : 'GET';
		$postData = ($postStr) ? $postStr : '';

		$script = <<<EOD
			return $.ajax({
				type: '$requestType',
				url: '$url',
				data: '$postData',
				cache: false,
				timeout: 0,
				async: false,
			}).responseText;
EOD;

		$content = $this->driver->executeScript($script);

		return $content;
	}

	/**
	 * контент текущей страницы
	 */
	public function getCurrentContent()
	{
		return $this->driver->getPageSource();
	}

	public function getCurrentUrl()
	{
		return $this->driver->getCurrentURL();
	}

	/**
	 * клик по элементу
	 * скроллит страницу, наводит курсор мыши на координаты и кликает по ним
	 * @param WebDriverElement $element
	 * @return bool
	 */
	public function click($element)
	{
		$this->scrollTo($element);
		$this->driver->getMouse()->mouseMove($element->getCoordinates());
		return $this->driver->getMouse()->click();
	}

	/**
	 * запущен ли в данный момент
	 */
	private function isActive()
	{
		return Tools::threadExist($this->id);
	}

	//все запущеные боты
	public static function getActiveBots($paymentType)
	{
		return Tools::getThreads('!'.$paymentType.'\d+!');
	}

	private function startProxyPlugin()
	{
		$pluginFile = $this->runtimeDir.'/'.$this->id.'_proxy.zip';
		$manifestFile = $this->configDir.'/proxyPlugin/manifest.json';
		$backgroundFile = $this->configDir.'/proxyPlugin/background.js';;

		$proxyParams = self::parseProxyStr($this->proxy);

		$zip = new ZipArchive();
		$zip->open($pluginFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$zip->addFile($manifestFile, 'manifest.json');
		$background = file_get_contents($backgroundFile);
		$background = str_replace(['%proxy_host', '%proxy_port', '%username', '%password'],
			[$proxyParams['ip'], $proxyParams['port'], $proxyParams['login'], $proxyParams['pass']], $background);

		$zip->addFromString('background.js', $background);
		$zip->close();

		register_shutdown_function(function($fileName){
			unlink($fileName);
		}, $pluginFile);

		return $pluginFile;
	}

	//обновить прокси если нет в списке
	private function updateProxy()
	{
		$proxyParams = self::parseProxyStr($this->proxy);
		$proxyFile = realpath(__DIR__.'/../').'/config/proxy.txt';
		$proxyContent = file_get_contents($proxyFile);

		if(strpos($proxyContent, $proxyParams['ip']) === false)
		{
			$this->proxy = self::getProxy(self::DEFAULT_PROXY_TYPE, $this->payment_type);
			$settings = $this->settingsArr;
			$settings['proxy'] = $this->proxy;
			$this->settingsArr = $settings;
			$this->save();
		}

	}
}

