<?php

/**
 * @property string login - логин без +
 * @property string pass
 *
 * todo: data('ticket') походу тоже не вечный, надо обновлять
 */
class QiwiBot
{
	const ERROR_AUTH = 'error_auth';
	const ERROR_AUTH_COUNT = 'error_auth_count';
	const ERROR_IS_REGISTERED = 'error_is_gegistered';
	const ERROR_PASSWORD_EXPIRED = 'password_expired';
	const ERROR_NO_MONEY = 'not_enough_money';
	const ERROR_SEND_MONEY_TO_LIMIT = 'send_money_to_limit';
	const ERROR_BAN = 'ban';
	const ERROR_WRONG_AMOUNT = 'wrong_amount';//неверная сумма
	const ERROR_WRONG_WALLET = 'wrong_wallet';//неверный номер(sendmoney)
	const ERROR_WAIT = 'server is busy, wait';	//сервер занят, повторите платеж позже
	const ERROR_WRONG_CARD_NUMBER = 'wrong_card_number';
	const ERROR_SMS_ENABLED = 'sms_enabled';//включена смс
	const ERROR_LIMIT_OUT = 'limit_out';//превышен лимит исходящих транзакций
	const ERROR_REPEAT_REQUEST = 'repeat_request'; // повторите запрос(тех ошибка в киви либо капча неверная)
	const ERROR_CAPTCHA = 'captcha';
	const ERROR_PASSPORT_EXPIRED = 'passport_expired';
	const ERROR_PASSPORT_NOT_VERIFIED = 'not_verified';
	const ERROR_PASSPORT_MAX_COUNT = 'max_count';	//максимум заиденчено
	const ERROR_IDENT_CLOSED = 'ident_closed';	//закрыта идентификация в приложении

	const COMMISSION = 0.01; //минимальная комиссия
	const COMMISSION_FULL = 0.02; //максимальная комиссия
	const MIN_AMOUNT = 1;	//минимальная сумма перевода

	const TRANSACTION_STATUS_SUCCESS = 'success';
	const TRANSACTION_STATUS_ERROR = 'error';
	const TRANSACTION_STATUS_WAIT = 'wait';
	const TRANSACTION_TYPE_IN = 'in';
	const TRANSACTION_TYPE_OUT = 'out';

	const TRANSACTION_ERROR_LIMIT = 'limit_out';

	const STATUS_ANONIM = 'anonim';
	const STATUS_HALF = 'half';
	const STATUS_FULL = 'full';

	//мультивалютность
	const CURRENCY_RUB = 'RUB';
	const CURRENCY_KZT = 'KZT';

	const OPERATION_TYPE_CONVERT = 'convert';

	const CLIENT_SOFTWARE = 'WEB v4.32.1';
	const APPLICATION_ID = '0ec0da91-65ee-496b-86d7-c07afc987007';
	const APLICATION_SECRET = '66f8109f-d6df-49c6-ade9-5692a0b6d0a1';

	const VOUCHER_AMOUNT_MIN = 1;
	const VOUCHER_AMOUNT_MAX = 15000;
	const VOUCHER_COMMISSION_PERCENT = 0.01;
	const VOUCHER_COMMISSION_AMOUNT = 50;	//50р


	public $error = '';
	public $errorCode = 0;	//0 - все в порядке, 1 - ошибка авторизации
	public $balance = false;	//последний полученный баланс
	public $sender;

	protected $login;
	protected $pass;
	public $lastContent;	//контент полученый с посл запроса(чтобы не посылать зря)
	protected $maxAmount = 15000; 	//максимальный обьем одного платежа
	public $timeout = 50;
	protected $workDir;	//рабочая папка бота
	protected $usersDir;	//папка с настройками пользователей
	protected $userDir;	//папка с настройками пользователя
	protected $captchaDir; //папка с капчей, для каждого юзера свой файл
	public $proxy;	//строка с прокси (123.123.123.123:8080)
	public $isCommission = false; //если комиссия на переводы включилась то
	public $browser;	//строка с userAgent
	public $pauseMin = 0.1;
	public $pauseMax = 0.5;
	protected $additional;
	protected $lastCaptchaCode = '';

	public $dateLastRequest; //дата успешного запроса к боту(httpCode !== 0)

	protected $_status = '';


	public $commission = 0; //текущая комиссия

	public $estmatedTransactions;	//заполняется на sendMoney() - последние полсанные но неизвестные платежи


	/*
	 * $additional = array(
	 * 	'withoutAuth'=> true|false  без авторизации при создании объекта - для регистрации кошелька
	 * 	'pauseMin' => 0.2 минимальная пауза перед запросом
	 *  'pauseMax' => 3 максимальная пауза перед запросом
	 *
	 * 	'testHeaderUrl'=>'http://....' урл который будет подставляться в каждый запрос для проверки заголовков
	 * 	'captchaCode'=>'длинный код рекапчи гугла',
	 * )
	 *
	 */
	public function __construct($login, $pass, $proxy=false, $browser=false, $additional = array())
	{
		$this->login = str_replace('+','',$login);
		$this->pass = $pass;

		$this->workDir = dirname(__FILE__).'/'.__CLASS__.'/';
		$this->usersDir = $this->workDir.'users/';
		$this->userDir = $this->usersDir.$this->login.'/';
		$this->captchaDir = $this->workDir.'captcha/';
		$this->proxy = $proxy;
		$this->browser = $browser;
		$this->additional = $additional;
		$this->lastCaptchaCode = $additional['captchaCode'];

		$this->estmatedTransactions = array();

		$this->initUser();

		if(!$additional['withoutAuth'])
		{
			if($this->checkAuth())
			{
				$this->dateLastRequest = time();
				return true;
			}
			else
			{
				if($this->sender->info['httpCode'][0] !== 0)
					$this->dateLastRequest = time();

				return false;
			}
		}
	}

	/**
	 * подтверждение запроса на идентификацию
	 * $number - последние 4 цифры номера паспорта
	 */
	public function confirmIdentify($number=false, $sms=false)
	{
		if($sms)
		{
			$this->sender->additionalHeaders = array(
				'X-Requested-With: XMLHttpRequest',
				'Accept: application/json, text/javascript, */*; q=0.01',
			);

			$url = 'https://qiwi.com/user/confirmation/confirm.action';
			$postData = 'identifier='.$this->data('identifier').'&type=ACCOUNT_IDENTIFICATION&code='.$sms.'&data%5B\'id\'%5D='.$this->data('identify_number').'&data%5B\'code\'%5D='.$number;

			$content = $this->request($url, $postData, false, 'https://qiwi.com/person/account/identification/confirm.action?id='.$this->data('identify_number'));

			if($json = json_decode($content, true))
			{
				if($json['code']['value']=='0')
				{
					//нужно перезайти в акк чтобы изменился статус
					if($this->logOut())
						return true;
					else
						$this->error = 'не удалось выйти из аккаунта';
				}
				else
					$this->error = 'ошибка: '.$json['message'];
			}
			else
			{
				$this->error = 'json error 9';
			}
		}
		else
		{
			$content = $this->request('https://qiwi.com/settings/account/idfnlink.action');

			if(preg_match('!/person/account/identification/confirm\.action\?id=(\d+)"!', $content, $res))
			{
				$this->data('identify_number', $res[1]);

				$content = 'https://qiwi.com/person/account/identification/confirm.action?id='.$res[1];

				$this->sender->additionalHeaders = array(
					'X-Requested-With: XMLHttpRequest',
					'Accept: application/json, text/javascript, */*; q=0.01',
				);

				$url = 'https://qiwi.com/user/person/account/identification/identify.action';
				$postData = 'id='.$res[1].'&code='.$number;

				$content = $this->request($url, $postData, false, 'https://qiwi.com/person/account/identification/confirm.action?id='.$res[1]);


				if($json = json_decode($content, true))
				{
					if($json['identifier'])
					{
						$this->data('identifier', $json['identifier']);

						$url = 'https://qiwi.com/user/confirmation/form.action';
						$postData = 'identifier='.$json['identifier'].'&type=ACCOUNT_IDENTIFICATION';

						$content = $this->request($url, $postData, false, 'https://qiwi.com/person/account/identification/confirm.action?id='.$res[1]);

						if(preg_match('!value="ACCOUNT_IDENTIFICATION"!', $content))
							return true;
						else
							$this->error = 'ошибка идентификации: '.$json['message'];
					}
					else
						$this->error = 'не найден identifier';
				}
				else
				{
					$this->error = 'json error 7';
				}

			}
			else
			{
				$this->error = 'не найдено подтверждение идентификации';
			}
		}
	}

	/**
	 * есть ли неподтвержденная идентификация
	 * return true/false
	 */
	public function hasUnconfirmedIdentify()
	{
		$status = $this->getStatus();

		if($status=='half' or $status=='anonim')
		{
			$content = $this->request('https://qiwi.com/settings/account/idfnlink.action');

			if(mb_strpos($content, '<button data-href="/person/account/identification/confirm.action', 0, 'utf-8'))
			{
				return true;
			}
			elseif(mb_strpos($content, 'У вас нет неподтвержденных заявок на идентификацию', 0, 'utf-8'))
			{
				return false;
			}
			else
			{
				$this->error = 'ошибка в запросе, код: '.$this->sender->info['httpCode'][0].' strLen: '.strlen($content);
				return null;
			}
		}
		else
			return false;
	}

	/**
	 * статус валидации
	 * anonim - синий
	 * half - желтый
	 * full - зеленый

	public function getStatus($recursive=false)
	{
	if($this->_status)
	return $this->_status;

	$status = false;

	if($recursive)
	$content = $this->request('https://qiwi.com/settings/options/wallet.action');
	else
	$content = $this->lastContent;


	if(
	preg_match('!Ваш статус: Полностью идентифицирован!', $content)
	or(preg_match('!fa-check-circle full!', $content))
	or(preg_match('!identify_status identify_status_full!', $content))
	)
	$this->_status = self::STATUS_FULL;
	elseif(preg_match('!<span class="person-data-save">Персональные данные сохранены</span>!uis', $content))
	$this->_status = self::STATUS_HALF;
	elseif(preg_match('!data-target="_self">Ввести данные</a>!', $content))
	$this->_status = self::STATUS_ANONIM;
	else
	{
	if(!$recursive)
	{
	return $this->getStatus(true);
	}
	else
	{
	$this->error = 'ошибка получения статуса: strLen'.strlen($content);
	return false;
	}
	}

	return $this->_status;
	}
	 */

	/**
	 * статус валидации, работает по новой ссылке
	 * @param bool $withCache
	 * @return string|bool
	 */
	public function getStatus($withCache = false)
	{
		if($this->_status and $withCache)
			return $this->_status;

		$referer = 'https://qiwi.com/settings/identification';

		$token = $this->getAccessToken();

		if(!$token)
			return false;

		$this->sender->additionalHeaders = array(
			'Accept: application/vnd.qiwi.v2+json',
			'Content-Type: application/json',
			'client-software: '.self::CLIENT_SOFTWARE,
			'origin: https://qiwi.com',
			'authorization: TokenHeadV2 '.$token,
		);

		$url = 'https://edge.qiwi.com/identification/v1/persons/'.$this->login.'/identification';

		$content = $this->request($url, false, null, $referer);

		if($json = @json_decode($content, true))
		{
			if($json['type'])
			{
				if($json['type'] === 'SIMPLE' or $json['type'] === 'ANONYMOUS')
				{
					$this->_status = self::STATUS_ANONIM;
					return self::STATUS_ANONIM;
				}
				elseif($json['type'] === 'VERIFIED')
				{
					$this->_status = self::STATUS_HALF;
					return self::STATUS_HALF;
				}
				elseif($json['type'] === 'FULL')
				{
					$this->_status = self::STATUS_FULL;
					return self::STATUS_FULL;
				}
				else
				{
					$this->error = 'ошибка контента, статус не опознан: '.Tools::arr2Str($json['type']);
					return false;
				}
			}
			else
			{
				$this->error = 'bad json: '.Tools::arr2Str($json).' (code: '.$this->sender->info['httpHeader'][0].')';
				return false;
			}
		}
		else
		{
			$this->error = 'bad content: '.$content.' (code: '.$this->sender->info['httpHeader'][0].')';
			return false;
		}
	}

	/**
	 * step=false  -  возвращает постоянный гуглАпи код для рекапчи (из тега <iframe)
	 *
	 *
	 * $step - шаг регистрации:
	'register_sms' - смс при регистрации, $params = array('sms'=>'123456')
	 *
	 * $params -  массив параметров. свой для каждого шага
	 *
	 * $params = array(
	 * 	'captchaCode'=>'dsfafadfq23f32fq23fмногоЦифрБукв'
	 * )
	 *
	 */
	public function register($step=false, array $params=array())
	{
		if(!$step)
		{
			//начало регистрации
			if($this->request('https://qiwi.com/register/form.action'))
			{
				if(!strpos($this->getCurrentCookie(), 'test.for.third.party.cookie'))
					$this->setCookie('qiwi.com	FALSE	/	TRUE	0	test.for.third.party.cookie	yes');

				return $this->getGoogleCaptchaKey();
			}
			else
			{
				$this->error = 'регистрация: ошибка контента (step=0) (httpCode='.$this->sender->info['httpCode'][0].')';
				return false;
			}


			/*
			//вытащить капчу
			if(preg_match('!<img src="(/register/captcha\.action[^"]+)"!', $content, $res))
			{
				$imgData = $this->request('https://qiwi.com'.$res[1]);

				if($filePath = $this->saveCaptcha($imgData))
				{
					return $filePath;
				}
				else
					return false;
			}
			else
			{
				$this->error = 'капча не найдена: ';
				return false;
			}
			*/

		}
		elseif($step=='captcha')
		{
			//юзер ввел капчу
			if($params['captchaCode'])
			{
				$this->sender->additionalHeaders = array(
					'Accept: application/json',
					'content-type: application/x-www-form-urlencoded',
					'client-software: '.self::CLIENT_SOFTWARE,
					'origin: https://qiwi.com',
				);

				$url = 'https://qiwi.com/oauth/authorize';
				$postData = 'response_type=urn%3Aqiwi%3Aoauth%3Aresponse-type%3Aconfirmation-id&client_id=sso.qiwi.com&username=%2B'.$this->login.'&recaptcha='.trim($params['captchaCode']);
				//response_type=urn%3Aqiwi%3Aoauth%3Aresponse-type%3Aconfirmation-id&client_id=sso.qiwi.com&username=%2B79617463843&recaptcha=03AJIzXZ7VQ8qDrk7fE7fMdTql8fvXB_lWGfbxH8H_BrwwG__HjqlpI8RdbpAH0As4mOEV8g2s6fj5Nofz7kFPoa0EqVlzgG5XKy25TODacGMwnlfLWu-rUhNWb1abB6SnkTjUQV2Y9QoHEaG86Fp0YRb3M6YHcU8_vX64vFbsVMPOn2o48K58GLU3QEEppcivFT6h7MTkJ1i9GotuMjUU09Ky3iUQ7DgLyYNOcZEZ6klXgsClIVNwuRHhrLInqBGoHz7D9ynM7xGyNloDf5qmxsv-lugR13A0I_HwFHP3W32sQLQnYSzHjItfnI_MoHOMFsO0WuQHmyAq
				$referer = 'https://qiwi.com/';

				$content = $this->request($url, $postData, false, $referer);

				//контент в случае неверной капчи {"error":"invalid_recaptcha","error_description":"Invalid verification recaptcha. Response: {success=false, challenge_ts=2016-08-30T00:47:46Z, hostname=qiwi.com}","error_code":"1301","user_message":"Кажется, что-то пошло не так. Повторите попытку позже."}

				if(preg_match('!\{"confirmation_id":"(\d+)"\}!', $content, $res))
				{
					$this->data('confirmation_id', $res[1]);

					return true;
				}
				else
				{
					//ошибка отправки капчи: неверная капча(может истекли 2 минуты), ошибка генерации какогото кода
					if(
						strpos($content, 'Verification code generation failed')!==false
						or
						strpos($content, 'Invalid verification recaptcha')!==false
					)
					{
						$this->errorCode = self::ERROR_REPEAT_REQUEST;
					}
					else
					{

					}

					$msg =  'error register (step=0), strLen: '.strLen($content).' (httpCode='.$this->sender->info['httpCode'][0].' )';
					$this->error = $msg;

					return false;

				}
			}

		}
		elseif($step=='register_sms')
		{
			//юзер ввел смс регистрации
			if($params['sms'])
			{
				if($identifier = $this->data('confirmation_id'))
				{
					$url = 'https://qiwi.com/oauth/authorize';
					$postData = 'response_type=code&client_id=sso.qiwi.com&username=%2B'.$this->login.'&vcode='.$params['sms'].'&confirmation_id='.$identifier;
					$referer = 'https://qiwi.com/';

					$this->sender->additionalHeaders = array(
						'Accept: application/json',
						'content-type: application/x-www-form-urlencoded',
						'client-software: '.self::CLIENT_SOFTWARE,
						'origin: https://qiwi.com',
					);

					$content = $this->request($url, $postData, false, $referer);


					if(preg_match('!"token_id":"(\d+)","unregistered_user":"1","create_password":"1"!', $content, $res))
					{
						$postData = 'response_type=code&client_id=sso.qiwi.com&username=%2B'.$this->login.'&password='.$this->pass.'&token_id='.$res[1];

						$content = $this->request($url, $postData, false, $referer);

						//код для последующего залогинивания, но он нам не нужен
						if(preg_match('!"code":"(\w+)"!', $content))
							return true;

					}
					elseif(preg_match('!"token_id":"\d+","unregistered_user":"0","create_password":"1"!', $content))
					{
						$this->errorCode = self::ERROR_IS_REGISTERED;
						$this->error = 'возможно юзер уже зарегистрирован:';
					}
					elseif(strpos($content, 'Max number 3 of verification code input attempts is reached')!==false)
					{
						$this->errorCode = self::ERROR_REPEAT_REQUEST;
						$this->error = 'слишком много ошибок при вводе смс-кода(либо смс устарело): ';
					}
					else
					{
						$this->error = 'ошибка проверки смс-кода: ';
					}

				}
				else
				{
					$this->error = 'не найден identifier';
					return false;
				}
			}
			else
			{
				$this->error = 'json error 3';
				return false;
			}
		}
	}

	/**
	 * есть ли неотключенная защита
	 */
	public function hasLockedSecurity()
	{
		$content = $this->request('https://qiwi.com/settings/options/security.action');

		if(strpos($content, '{"data":{"type":"SMS_CONFIRMATION","value":false}}'))
		{
			if(preg_match('!<div class="toggle" style="display:none;" data-container-name="option-disabled">\s+<div class="pseudo-checkbox" data-action="update" data-container-name="option-update" data-params=\'{"data":{"type":"SMS_CONFIRMATION","value":true}}\'>!is', $content))
			{
				return true;
			}
			else
			{
				$this->error = 'httpCode='.$this->sender->info['httpCode'][0].' strlen:'.strlen($content);
				return false;
			}
		}
		else
		{
			$this->error = 'ошибка контента hasLockedSecurity()';

			if(preg_match('!Страница недоступна для авторизованных пользователей!iu', $content))
			{
				$this->error .= 'страница недоступна для авторизованных пользователей (чищу куки)';
				self::clearCookie($this->login);
			}
		}
	}

	/**
	 * отключение смс-подтверждений
	 * требуется ввести смс
	 *
	 *
	 * todo: Cookie: JSESSIONID=B79085653FB4F295F71CB36C0B2A776F.node-s1535; node=6712688ea458a444f23c1538d9b3ea7d; BIGipServerqiwicom=2016022026.20480.0000; test.for.third.party.cookie=yes; BIGipServeronline-user=2049641994.20480.0000; BIGipServerqiwi_magnolia_public=2452229130.20480.0000; __promo_session=0_1472642986704
	 */
	public function securityUnlock($sms=false)
	{
		if(!$this->hasLockedSecurity())
			return true;

		$this->request('https://qiwi.com/settings/options/security.action');

		$this->sender->additionalHeaders = array(
			'Accept: application/json, text/javascript, */*;',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
		);

		$referer = 'https://qiwi.com/settings/options/security.action';

		if($sms)
		{
			if($identifier = $this->data('identifier'))
			{
				$url = 'https://qiwi.com/user/confirmation/confirm.action';
				$postData = 'identifier='.$identifier.'&type=SMS_CONFIRMATION&code='.$sms;

				$content = $this->request($url, $postData, false, $referer);

				//echo "\n".$content.' post:'.$postData;

				if($json = json_decode($content, true))
				{
					if($json['code']['value']=='0')
					{
						return true;
					}
					else
						$this->error = 'ошибка: '.$json['message'];
				}
				else
					$this->error = 'json error 6';
			}
			else
				$this->error = 'не найден identifier в securityUnlock()';
		}
		else
		{
			$url = 'https://qiwi.com/user/person/change/security.action';
			$postData = 'type=SMS_CONFIRMATION&value=false';

			$content = $this->request($url, $postData, false, $referer);

			//echo "\n".$content.' post:'.$postData;

			if($json = json_decode($content, true) and $token = $json['data']['token'])
			{

				$postData = 'type=SMS_CONFIRMATION&value=false&token='.$token;

				$content = $this->request($url, $postData, false, $referer);

				//echo "\n".$content.' post:'.$postData;

				if($json = json_decode($content, true) and $identifier = $json['identifier'])
				{
					$url = 'https://qiwi.com/user/confirmation/form.action';
					$postData = 'type=SMS_CONFIRMATION&value=false&token='.$token.'&identifier='.$identifier;

					$content = $this->request($url, $postData, false, $referer);

					//echo "\n".$content.' post:'.$postData;

					if(preg_match('!<input type="hidden" name="identifier" value="(\d+)"!', $content, $res))
					{
						return $this->data('identifier', $res[1]);
					}
					else
						$this->error = 'не найден identifier в securityUnlock()';
				}
				else
					$this->error = 'json error 5';
			}
			else
				$this->error = 'json error 4';

		}
	}

	/**
	 * $imgData - контент картинки
	 */
	protected function saveCaptcha($imgData)
	{
		$captchaFile = $this->captchaDir.$this->login.'.png';

		$saveRes = file_put_contents($captchaFile, $imgData);

		if(!$saveRes)
		{
			$this->error = 'ошибка сохранения файла '.$captchaFile;
			return false;
		}


		return $captchaFile;
	}

	/**
	 * возвращает статистику пополнений кошелька за последние 30 дней
	 */
	public function inStats()
	{
		$dateFrom = date('d.m.Y', (time()-3600*24*30));
		$dateTo = date('d.m.Y');


		$url = 'https://qiwi.com/report/list.action?daterange=true&start='.$dateFrom.'&finish='.$dateTo;

		$content = $this->request($url, '', false, $url);

		$payments = $this->getHistory($content);

		$result = array(
			'balance'=>$this->balance,
			'operations'=>array(),
		);

		foreach($payments as $payment)
		{
			if($payment['type']=='in')
			{
				$result['operations'][] = array(
					'amount'=>$payment['amount'],
					'date'=>$payment['date'],
				);
			}
		}

		return $result;
	}


	public function getBalance()
	{
		$tryCount = 3;

		for($i=1; $i<=$tryCount; $i++)
		{
			if($this->updateBalance()===true)
				return $this->balance;

			sleep(rand(1, 4));
		}

		$this->error = 'http_code: '.$this->sender->info['httpCode'][0].' header: '.Tools::arr2Str($this->sender->info['header'][0]);
		$this->balance = false;

		return $this->balance;
	}

	/**
	 * задать-загрузить настройки виртуального юзера для бота
	 * юзер-агент
	 * прокси
	 * куки
	 */
	protected function initUser()
	{
		clearstatcache();


		//папка аккаунта
		if(!file_exists($this->userDir))
		{
			if(!mkdir($this->userDir))
				toLog('error create '.basename($this->userDir), 1);

			chmod($this->userDir, 0777);
		}

		//файл конфига
		$fileConfig = $this->userDir.'config.json';

		$configTpl = array(
			'proxy'=>'',
			'browser'=>'',
		);

		if(!file_exists($fileConfig))
		{
			if(file_put_contents($fileConfig, json_encode($configTpl))===false)
				toLog('error create '.basename($fileConfig), 1);

			chmod($fileConfig, 0777);
		}

		//куки
		$fileCookie = $this->userDir.'cookie.txt';

		if(!file_exists($fileCookie))
		{
			if(file_put_contents($fileCookie, '')===false)
			{
				toLog('error write '.$fileCookie);
				die('error write '.basename($fileCookie));
			}

			chmod($fileCookie, 0777);

		}

		$config = json_decode(file_get_contents($fileConfig), 1);

		if($config['proxy'] != $this->proxy)
			$config['proxy'] = $this->proxy;

		if($config['browser'] != $this->browser)
			$config['browser'] = $this->browser;

		if(!$config['proxy'] or !$config['browser'])
			toLog('нет прокси или браузера у +'.$this->login, true);

		if(file_put_contents($fileConfig, json_encode($config))===false)
		{
			toLog('error write '.$fileConfig);
			die('error write '.basename($fileConfig));
		}


		//если при создании объекта был указан прокси то слать запросы через него
		if(!$this->proxy)
			$this->proxy = $config['proxy'];

		if(!$this->browser)
			$this->browser = $config['browser'];



		$this->sender = new Sender;
		$this->sender->useCookie = true;
		$this->sender->browser = $config['browser'];
		$this->sender->cookieFile = $fileCookie;
		$this->sender->followLocation = false;
		$this->sender->timeout = $this->timeout;

	}

	protected function getProxy()
	{
		if($content = file_get_contents($this->workDir.'proxy.txt'))
		{
			$explode = explode(Tools::getSep($content), $content);
		}
		else
			$explode = array();

		if($explode)
			return $explode[array_rand($explode)];
		else
			return '';
	}

	protected function getBrowser()
	{
		if($content = file_get_contents($this->workDir.'browser.txt'))
		{
			$explode = explode("\r\n", $content);
		}
		else
			$explode = array();

		if($explode)
			return $explode[array_rand($explode)];
		else
			return '';
	}



	/**
	 * * $dayCount - кол-во дней, за которые нужно получить платежи
	 * использует переменную historyBug в data.json
	 * historyBug = 1 - нужно грузить историю по частям: time()-3600*24*$dayCount до вчера + со вчера до сегодня
	 *
	 * @param int $dayCount
	 * @param bool $allCurrencies	мультивалютность(иначе только рубли)
	 * @return array|false
	 */
	public function getLastPayments($dayCount = 0, $allCurrencies=false)
	{

		$dateFrom = date('d.m.Y' ,time()-3600*24*$dayCount);
		$dateTo = date('d.m.Y');

		if($dayCount == 0)
			$url = 'https://qiwi.com/report/list.action?type=1';
		else
			$url = 'https://qiwi.com/report/list.action?daterange=true&start='.$dateFrom.'&finish='.$dateTo;//'https://qiwi.com/report/list.action?type=1';

		$content2 = $this->request($url, false, false, 'https://qiwi.com/payment/history');

		if($this->sender->info['httpCode'][0] == 302)
		{
			self::clearCookie($this->login);
			toLog('ошибка получения платежей 302: чищу куки: '.$this->login);
			return false;
		}


		$history2 = $this->getHistory($content2, $allCurrencies);

		//соединить в обратном порядке
		//todo: дописать для разных вариаций
		if($history2!==false)
		{
			$result = $history2;

			return $result;
		}
		else
		{
			return false;
		}
	}

	/**
	 * общая сумма на вывод с банкомата
	 */
	public function withdrawAmount()
	{
		$payments = $this->withdrawHistory();

		$amount = 0;

		if($payments!==false)
		{
			foreach($payments as $payment)
				$amount += $payment['amount'];

			return $amount;
		}
		else
			return false;
	}

	/**
	 * получение списка операций на вывод с банкомата
	 */
	public function withdrawHistory()
	{
		$url = 'https://qiwi.com/report/list.action?type=3';
		$content = $this->request($url, '', false, $url);

		$payments = $this->getHistory($content);

		$result = array();

		if($payments!==false)
		{
			foreach($payments as $payment)
			{
				if(mb_strpos($payment['wallet'], ', карта', 0, 'utf-8')!==false and $payment['type']=='out')
					$result[] = $payment;
			}
		}
		else
			return false;

		return $result;
	}

	/**
	 * список всех платежей
	 *
	 * array(
	 * 	'id'=>'',//ID платежа в киви
	 * 	'type'=>'',	//тип (in, out)
	 * 	'wallet'=>'', //кошелек
	 * 	'comment'=>'', //коммент к платежу (cash - снятия с банкомата)
	 * 	'amount'=>'', //сумма включая комиссию
	 * 	'commission'=>0,	//комиссия
	 * 	'status'=>success,wait,error
	 * 	'timestamp'=>2137213612,//метка времени
	 * 	'date'=>22.12.2016,//форматированная дата
	 * 	'error'=>'',
	 * 	'errorCode'=>'',
	 * 	'currency'=>'RUB|KZT', //этот элемент доступен только если включен $allCurrencies
	 * )
	 *
	 * @param string $content
	 * @param bool $allCurrencies если true то парсит все валюты а не только рубли и выдает 'currency'=>'...',
	 * @return array|false
	 */
	protected function getHistory($content, $allCurrencies = false)
	{

		if($this->sender->info['httpCode'][0] != 200)
		{
			$this->error = 'код ответа: '.$this->sender->info['httpCode'][0].' ';
			return false;
		}

		if(!preg_match('!</html>!', $content))
		{
			$this->error = 'контент не догружен (нет </html>)';
			return false;
		}


		//$this->updateBalance($content);
		$arResult = array();

		$contentAll = $content;

		$dom = phpQuery::newDocument($content);

		if($dom)
		{
			if($divs = $dom->find('div[data-container-name=item].status_SUCCESS,div[data-container-name=item].status_PROCESSED,div[data-container-name=item].status_ERROR'))
			{
				foreach($divs as $div)
				{
					$pq = pq($div);

					$content = $pq->html();

					if(strpos($pq->attr('class'), 'status_SUCCESS')!==false)
						$status = 'success';
					elseif(strpos($pq->attr('class'), 'status_PROCESSED')!==false)
						$status = 'wait';
					elseif(strpos($pq->attr('class'), 'status_ERROR')!==false)
						$status = 'error';
					else
						toLog('error in payment status: '.$pq->attr('class'), 1);

					$errorCode = '';

					if($status=='error')
					{
						//текст ошибки
						if(preg_match('!<a href="\#" class="error" data-action="item-error" data-params=\'\{"message":"(.+?)"\}\'>!', $content, $res))
						{
							$error = trim($res[1]);

							if(preg_match('!Ежемесячный лимит платежей и переводов для статуса!iu', $error))
								$errorCode = self::TRANSACTION_ERROR_LIMIT;
						}
						else
							$error = '';
					}
					else
						$error = '';

					$comment = '';

					//если снятие
					if(preg_match('!<div class="provider">\s+<span>(.+?)</span>!us', $content, $res))
						$provider = $res[1];
					else
					{
						$provider = '';
					}

					$type = '';

					$isCash = 0;

					if(mb_strpos($provider, 'QVP: Снятие наличных в банкомате', 0, 'utf-8')!==false)
					{
						//при выводе с карты комиссии не указано и сумма в другом блоке
						if(preg_match('!<div class="originalExpense">\s+<span>(.+?)</span>!s', $content, $res))
						{
							$amountText = $res[1];
							$type = 'out';
							//$isCash = 1;

						}
						else
						{
							$this->error = 'не найден <div class="originalExpense">'.$content;
							return false;
						}
					}
					else
					{
						if(preg_match('!expenditure">\s+<div class="cash">(.+?)</div>!s', $content, $res))
						{
							$amountText = $res[1];
							$type = 'out';
						}
						elseif(preg_match('!income">\s+<div class="cash">(.+?)</div>!s', $content, $res))
						{
							$amountText = $res[1];
							$type = 'in';
						}
						else
						{
							$this->error = 'error payment type: '.$content;
							return false;
						}
					}

					$currency = '';

					//парсим только рубли, если не включен $allCurrencies
					if($allCurrencies)
					{
						die($amountText);
					}
					else
					{
						if(!preg_match('!руб\.!u', $amountText))
						{
							//toLog('currency error found: '.$this->login.': '.$amountText);
							continue;
						}
					}

					$amount = $this->parseAmount($amountText);

					if($amount===false)
					{
						$this->error = 'wrong amount: '.$amountText;
						return false;
					}


					//откуда или куда перевод
					if(preg_match('!<span class="opNumber">(.+?)</span>!u', $content, $res))
						$wallet = $res[1];
					else
					{
						$this->error = 'span class="opNumber"> not found on: '.$content;
						return false;
					}

					//комментарий
					if(!$comment)
					{
						if(preg_match('!<div class="comment">(.*?)</div>!su', $content, $res))
							$comment = $res[1];
						else
						{
							$this->error = 'div class="comment"> not found on: '.$content;
							return false;
						}
					}

					//дата
					if(preg_match('!<span class="date">(.+?)</span>!s', $content, $res))
						$date = trim($res[1]);
					else
					{
						$this->error = 'span class="date"> not found on: '.$content;
						return false;
					}

					//время
					if(preg_match('!<span class="time">(.+?)</span>!s', $content, $res))
						$time = trim($res[1]);
					else
					{
						$this->error = 'span class="time"> not found on: '.$content;
						return false;
					}

					//id
					if(preg_match('!<div class="transaction">(.+?)</div>!s', $content, $res))
						$id = trim($res[1]);
					elseif(preg_match('!href="/report/cheque\.action\?transaction=(\d+)&amp;direction=OUT" class="cheque"!is', $content, $res))
					{
						$id = trim($res[1]);
					}
					elseif(preg_match('!<div class="transaction" data-action="item-extra" data-params=\'\{"data":\{"txn":(\d+)\}\}\'>!is', $content, $res))
					{
						//снятие с карты
						$id = trim($res[1]);
					}
					else
					{
						$this->error = 'div class="transaction"> not found on: '.$content;
						return false;
					}

					$commission = 0;

					if(preg_match('!<div class="commission">(.+?)</div>!s', $content, $res))
					{
						$commissionStr = trim($res[1]);

						if(!empty($commissionStr))
						{
							$commission = $this->parseAmount($commissionStr);

							if($commission===false)
								toLog('error parse amount on commission', 1);
						}

					}
					else
					{
						$this->error = 'div class="commission"> not found on: '.$content;
						return false;
					}


					if($comment=='cash1')
					{
						//проставить комиссии
						if(!$commission)
						{
							if($amount==10250 or $amount==10000)
							{
								$amount = 10000;
								$commission = 250;
							}
							elseif($amount==5150  or $amount==5000)
							{
								$amount = 5000;
								$commission = 150;
							}
							elseif($amount==4640 or $amount==4500)
							{
								$amount = 4500;
								$commission = 140;
							}
							elseif($amount==4125)
							{
								$amount = 4000;
								$commission = 125;
							}
							else
							{
								$this->error = 'неизвестная сумма снятия ('.$amount.'): '.$content;
								return false;
							}
						}
					}

					$timestamp = strtotime($date.' '.$time);

					$arr = array(
						'id'=>$id,
						'type'=>$type,
						'status'=>$status,
						'amount'=>$amount,
						'commission'=>$commission,
						'wallet'=>$wallet,
						'timestamp'=>$timestamp,
						'date'=>date('d.m.Y H:i', $timestamp),
						'comment'=>$comment,
						'error'=>$error,
						'errorCode'=>$errorCode,
						'is_cash'=>(preg_match('!, карта \d{4}\*\*\*\*\d{4}!iu', $wallet)) ? 1 : 0,
					);

					if($allCurrencies)
						$arr['currency'] = $currency;

					$arResult[] = $arr;

				}

				Tools::multisort($arResult, 'timestamp', SORT_DESC);


			}
			else
			{
				//toLog('no history '.$content);
				return $arResult;
			}
		}
		else
		{
			toLog('no dom on getHistory');
			return false;
		}

		return $arResult;
	}

	protected function parseAmount($strContent)
	{
		$strContent = trim(strip_tags($strContent));

		if(preg_match('!^([\d,\. ]+)(руб\.|)!ui', $strContent, $res))
		{

			$balance = str_replace(' ', '', trim($res[1]));

			if(strpos($balance, '.') and strpos($balance, ','))
				$balance = str_replace(',', '', $balance);
			else
				$balance = str_replace(',', '.', $balance);

			return $balance*1;
		}
		else
			toLog('error parse balance: '.$strContent, 1);
	}

	protected function checkAuth()
	{
		if($this->isAuth())
			return true;
		else
		{
			if($this->error)
				return false;

			$this->sender->pause = 0;
			$result = $this->auth();
			$this->sender->pause = $this->pause;

			return $result;
		}

	}

	protected function auth()
	{
		//toLog('auth '.$this->login.' : '.$this->getMyIp());

		//$this->sender->pause = 0;
		//

		$content = $this->request('https://qiwi.com/');


		//установить куку котоорая ставится js

		if(!strpos($this->getCurrentCookie(), 'test.for.third.party.cookie'))
			$this->setCookie('qiwi.com	FALSE	/	TRUE	0	test.for.third.party.cookie	yes');

		if(!strpos($this->getCurrentCookie(), '_gat_owox'))
			$this->setCookie('qiwi.com	FALSE	/	TRUE	0	_gat_owox	1');

		if(!strpos($this->getCurrentCookie(), '_gat_qiwiban'))
			$this->setCookie('qiwi.com	FALSE	/	TRUE	0	_gat_qiwiban	1');

		if(!strpos($this->getCurrentCookie(), 'ref'))
			$this->setCookie('qiwi.com	FALSE	/	TRUE	0	ref	newsite_index_1');

		if(!strpos($this->getCurrentCookie(), 'new_payment_history'))
			$this->setCookie('qiwi.com	FALSE	/	TRUE	0	new_payment_history	0');


		$content = $this->request('https://sso.qiwi.com/app/proxy?v=1');


		$this->sender->additionalHeaders = array(
			'Content-Type: application/json',
			'Accept: application/vnd.qiwi.sso-v1+json',
			'Origin: https://qiwi.com',
		);

		if($this->lastCaptchaCode)
		{
			$captcha = $this->lastCaptchaCode;
			$this->lastCaptchaCode = '';
		}
		else
			$captcha = '';

		//1
		$url = 'http://sso.qiwi.com/cas/tgts';
		$postData = '{"login":"+'.$this->login.'","password":"'.$this->pass.'","captcha":"'.$captcha.'"}';
		$referer = 'https://qiwi.com/';

		$content = $this->request($url, $postData, false, $referer);

		if(!preg_match('!"ticket":"(TGT[^"]+)"!', $content, $res))
		{

			if(
				preg_match('!"code":"1","message":"Введите символы!isu', $content)
				or
				preg_match('!message":"Ваш кошелек заблокирован!isu', $content)
				or
				preg_match('!message":"Неправильный номер телефона или пароль"!isu', $content)
				or
				preg_match('!message":"Неверный логин или пароль"!isu', $content)
			)
			{

				if(cfg('antiban23'))
				{
					// антибан23
					$currentDate = time();
					$needDateStart = strtotime(date('d.m.Y 22:50'));
					$needDateEnd = strtotime(date('d.m.Y 23:59'));

					if(
						($currentDate >= $needDateStart and $currentDate <= $needDateEnd)
						and (preg_match('!message":"Неверный логин или пароль"!isu', $content) or preg_match('!message":"Неправильный номер телефона или пароль"!isu', $content))
					)
					{
						$this->error = 'antiban23';
						return false;
					}
				}

				$this->errorCode = self::ERROR_BAN;
				$this->error = 'error auth (httpCode='.$this->sender->info['httpCode'][0].') '.$this->sender->info['proxy'][0].': strlen:'.strlen($content);
			}
			else
			{

				$this->error = '+'.$this->login.': 1 step auth: '.$this->sender->info['proxy'][0].$this->sender->info['header'][0].' httpCode: '.$this->sender->info['httpCode'][0];

				if(preg_match('!Докажите, что вы не робот!iu', $content))
				{
					$this->error .= ' (ip: '.$this->getMyIp().')';
					$this->errorCode = self::ERROR_CAPTCHA;
				}

				if(!preg_match('!Сервис временно недоступен!iu', $content))
					$this->error .= ' strlen:'.strlen($content);
			}

			return false;
		}

		//используется в getApiToken()
		$this->data('ticket', $res[1]);

		//2
		$url = 'http://sso.qiwi.com/cas/sts';
		$postData = '{"ticket":"'.$res[1].'","service":"https://qiwi.com/j_spring_cas_security_check"}';
		$referer = 'https://sso.qiwi.com/app/proxy?v=1';

		$content = $this->request($url, $postData, false, $referer);

		if(!preg_match('!"ticket":"([^"]+)"!', $content))
		{

			$this->error = '2 step auth: ';

			$this->error .= $this->sender->info['proxy'][0].$this->sender->info['header'][0];

			if(!preg_match('!Сервис временно недоступен!iu', $content))
				$this->error .= ' strlen:'.strlen($content);

			return false;
		}


		//3

		//18.04.2018
		if(!strpos($this->getCurrentCookie(), 'CASTGC'))
			$this->setCookie('sso.qiwi.com	FALSE	/	TRUE	0	CASTGC	'.$this->data('ticket'));

		$referer = 'https://qiwi.com/';

		$content = $this->request($url, $postData, false, $referer);

		if(!preg_match('!"ticket":"(ST[^"]+)"!', $content, $res))
		{

			$this->error = '3 step auth: '.$this->sender->info['proxy'][0].$this->sender->info['header'][0];

			if(preg_match('!Сервис временно недоступен!iu', $content))
				$this->error .= 'сервис временно недоступен';
			else
				$this->error .= ' strlen:'.strlen($content);

			return false;
		}

		//4
		$this->sender->additionalHeaders = array(
			'Accept: application/json',
			'x-requested-with: XMLHttpRequest',
			'content-type: application/x-www-form-urlencoded',
			'origin: https://qiwi.com',
			'client-software: '.self::CLIENT_SOFTWARE,
		);

		$url = 'https://qiwi.com/j_spring_cas_security_check';
		$postData = 'ticket='.$res[1];
		$referer = 'https://qiwi.com/';

		$content = $this->request($url, $postData, false, $referer);


		if(!$json = json_decode($content, true))
		{

			//мозможно текущий прокси забанен в киви
			$this->error = '+'.$this->login.' 4 step auth: httpCode='.$this->sender->info['httpCode'][0].' '
				.$this->sender->info['proxy'][0]
				.'. browser: '.$this->browser.'. strlen: '.strlen($content);

			//if(!preg_match('!Сервис временно недоступен!iu', $content))
			//	$this->error .= ' content:'.$content;
			//self::clearCookie($this->login);

			return false;
		}


		if($json['code']['value']=='0')
		{
			return true;
		}
		else
		{
			if($json['code']['value']=='3')
			{
				//превышен лимит авторизаций
				$this->errorCode = self::ERROR_AUTH_COUNT;
				$this->error = 'превышен лимит авторизаций';

				toLog('превышен лимит авторизаций: +'.$this->login.' (httpCode='.$this->sender->info['httpCode'][0].'): ');
			}
			elseif($this->sender->info['httpCode'][0]==200)
			{
				if(preg_match('!Техническая ошибка!u', $content))
				{
					$this->error = 'Техническая ошибка при авторизации';
				}
				elseif(preg_match('!"code":"1","message":"Введите символы!u', $content))
				{

					$this->errorCode = self::ERROR_BAN;
					$this->error = 'error auth1 (httpCode='.$this->sender->info['httpCode'][0].'): strlen: '.strlen($content);
				}
				else
				{

					$this->errorCode = self::ERROR_AUTH;
					$this->error = 'error auth2 (httpCode='.$this->sender->info['httpCode'][0].'): strlen:'.strlen($content);

					toLog('возможно бан: +'.$this->login.' (httpCode='.$this->sender->info['httpCode'][0].'): strlen: '.strlen($content));
				}
			}
			else
			{
				toLog('error auth3 +'.$this->login.' (httpCode='.$this->sender->info['httpCode'][0].'): strlen: '.strlen($content), 1);
			}
		}

		return false;
	}

	public function isAuth($content = false)
	{
		$url = 'https://qiwi.com/report.action';

		if(!$content)
			$content = $this->request($url);

		if($this->isPasswordExpired($content))
			return false;

		if(strpos($content, '<a href="#" data-action="logout">') and !preg_match('!<button data-action="login" class="signinBtn">Войти</button>!iu', $content))
		{
			//toLog($this->login.' authorized : '.$this->getMyIp());
			return true;
		}
		elseif(preg_match('!<button data-action="login" class="signinBtn">Войти</button>!iu', $content))
			return false;
		else
			return false;
	}

	protected function updateBalance()
	{
		$this->sender->additionalHeaders = array();

		$content = $this->request('https://qiwi.com/report.action');

		if(preg_match('!Счет QIWI, RUB\s+</div>\s+<div class="account_current_amount">\s+(.+?)\s+<span class="account_currency_!isu', $content, $res))
		{
			$amount = trim($res[1]);

			$amount = str_replace(array('&nbsp;', ',', ' '), array('', '.', ''), $amount);

			$amount = $amount*1;

			$this->balance = str_replace(',', '.', $amount);

			return true;
		}
		else
		{
			$this->balance = false;
			return false;
		}
	}

	/**
	 * передать киви другому польлзователю
	 * $wallet = +....
	 * $amount - целое число
	 */
	public function sendMoney($wallet, $amount, $comment='')
	{
		//обнулить последние платежи
		$this->estmatedTransactions = array();

		//разбить сумму если превышает максимальную
		$transactions = array();

		$maxAmountCount = floor($amount/$this->maxAmount);

		for($i=1; $i<=$maxAmountCount; $i++)
		{
			if($this->isCommission)
				$transactions[] = $this->getAmountForTransaction($this->maxAmount);
			else
				$transactions[] = $this->maxAmount;
		}

		$ostatok = $amount - ($maxAmountCount*$this->maxAmount);

		if($ostatok)
		{
			if($this->isCommission)
				$transactions[] = $this->getAmountForTransaction($ostatok);
			else
				$transactions[] = $ostatok;
		}

		$successAmount = 0;

		$functionName = 'transaction';

		if(preg_match(cfg('regExpYandexWallet'), $wallet))
			$functionName = 'transactionYandex';

		foreach($transactions as $amount)
		{
			$this->sender->timeOut = 60;

			if($sendAmount = $this->$functionName($wallet, $amount, $comment))
			{
				$successAmount += $sendAmount;

				if(count($transactions) > 1)
					sleep(rand(5, 10));
			}
			else
				break;
		}

		$this->sender->timeOut = $this->timeout;

		return str_replace(',','.',$successAmount);
	}

	protected function transaction($qiwiWallet, $amount, $comment)
	{
		if(strpos($qiwiWallet, '+')!==0)
		{
			$this->error = 'киви кошелек должен начинаться с +';
			return false;
		}

		$qiwiWallet = ltrim($qiwiWallet, '+');
		$amount = str_replace(',', '.', $amount);

		$decs = '';

		//toLog("debug: {$this->login} => {$qiwiWallet} $amount руб", true);

		if(strpos($amount, '.')!==false)
		{
			$decs = substr($amount, strpos($amount, '.')+1, 2);

			if(strlen($decs)<2)
				$decs .= '0';

			if($decs<10)
				$decs = '';
		}

		$amount = floorAmount($amount, 0);

		if($decs)
			$sendAmount = $amount.'.'.$decs;
		else
			$sendAmount = $amount;

		$this->sender->additionalHeaders = array(
			'Content-Type: application/json; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Accept: application/vnd.qiwi.v2+json',
		);

		$url = 'https://qiwi.com/payment/form.action?provider=99';
		$referer = '';

		$this->request($url, false, false, $referer);

		$this->sender->additionalHeaders = array(
			'Accept: application/vnd.qiwi.v2+json',
			'Content-Type: application/json',
			'X-Requested-With: XMLHttpRequest',
		);

		$url = 'https://qiwi.com/user/sinap/api/terms/99/payments/proxy.action';
		//{"id":"1453861277217","sum":{"amount":3,"currency":"643"},"source":"account_643","paymentMethod":{"type":"Account","accountId":"643"},"comment":"","fields":{"account":"+79999623406","_meta_pay_partner":""}}
		$transactionId = self::timestamp();
		$strPost = '{"id":"'.$transactionId.'","sum":{"amount":'.$sendAmount.',"currency":"643"},"source":"account_643","paymentMethod":{"type":"Account","accountId":"643"},"comment":"'.$comment.'","fields":{"account":"+'.$qiwiWallet.'","sinap-form-version":"qw::99, 12","browser_user_agent_crc":"'.$this->generateBrowserCrc().'"}}';//,"browser_user_agent_crc":"d50efb54"
		$referer = 'https://qiwi.com/payment/form.action?provider=99&state=confirm';

		$paymentTimestamp = time();

		$content = $this->request($url, $strPost, false, $referer);

		$json = @json_decode($content, 1);

		if(preg_match('!"transaction":{"id":"\d+","state":\{"code":"Accepted"\}!', $content, $res))
		{
			return $sendAmount;
		}
		else
		{
			if($json)
			{
				if(isset($json['data']['body']['message']))
					$message = $json['data']['body']['message'];
				elseif(isset($json['data']['message']))
					$message = $json['data']['message'];
				else
					$message = $json['message'];

				if(preg_match('!Сервер занят, повторите запрос через минуту!ui', $content))
				{
					$this->error = 'Сервер занят, повторите запрос через минуту';
					return false;
				}
				else
				{
					if(preg_match('!Пользователь временно заблокирован!ui', $message))
					{
						$this->errorCode = self::ERROR_BAN;
					}
					elseif(
						preg_match('!Кошелек временно заблокирован службой безопасности!ui', $message)
						or
						preg_match('!Проведение платежа запрещено СБ!ui', $message)
						or
						preg_match('!Ограничение на исходящие платежи!ui', $message)
						or
						preg_match('!Персона заблокирована!ui', $message)
					)
					{
						$this->errorCode = self::ERROR_BAN;
					}
					elseif(preg_match('!Платеж не проведен из-за ограничений у получателя!uis', $message))
					{
						$this->errorCode = self::ERROR_SEND_MONEY_TO_LIMIT;
					}
					elseif($this->sender->info['httpCode'][0]==0)
					{
						toLog('ПРОВЕРИТЬ!!! sendMoney error +'.$this->login.' => '.$qiwiWallet.': '.$amount.' (httpCode=0)');
					}
					elseif(preg_match('!Пул номеров страны не активен!ui', $message))
					{
						$this->errorCode = self::ERROR_WRONG_WALLET;
					}
					elseif(preg_match('!Сумма платежа меньше минимальной!ui', $message))
					{
						$this->errorCode = self::ERROR_WRONG_AMOUNT;
					}
					elseif(
						preg_match('!Недостаточно средств!ui', $message)
						or
						preg_match('!Сумма платежа больше максимальной!ui', $message)
					)
					{
						$this->errorCode = self::ERROR_NO_MONEY;
					}
					elseif(preg_match('!AwaitingSMSConfirmation!ui', $content))
					{
						$this->errorCode = self::ERROR_SMS_ENABLED;
					}
					elseif(preg_match('!Ежемесячный лимит платежей и переводов для статуса!ui', $message))
					{
						$this->errorCode = self::ERROR_LIMIT_OUT;
					}
					elseif(preg_match('!Техническая ошибка!ui', $message))
					{
						//тут платеж может пройти и не пройти: надо проверить по истории
						sleep(rand(10, 15));

						$payment = $this->getLastPayment(2, 'out');

						//пробуем еще раз получить платеж
						if($payment === false)
							$payment = $this->getLastPayment(2, 'out');

						if(
							preg_match("!$qiwiWallet!", $payment['wallet'])
							and $payment['amount'] == $sendAmount
							and $payment['comment'] == $comment
							and ($payment['status'] === self::TRANSACTION_STATUS_SUCCESS or $payment['status'] === self::TRANSACTION_STATUS_WAIT)
							and $payment['timestamp'] > $paymentTimestamp - 120
						)
						{
							return $sendAmount;
						}
					}
					else
					{
						$this->errorCode = false;
					}
				}

				$this->error = 'ошибка платежа +'.$this->login.' => +'.$qiwiWallet.': '.Tools::arr2Str($json);

				$msg = $this->error;

				if($this->errorCode)
					$msg .= ' (errorCode = '.$this->errorCode.')';

				return false;

			}
			elseif($this->sender->info['httpCode'][0] == 0)
			{
				//если не получен ответ запишем предполагаемую сумму
				$this->estmatedTransactions[] = array(
					'id'=>$transactionId,
					'amount'=>$sendAmount,
				);
			}

			return false;
		}
	}

	/*
	 * возвращает последний платеж за 2 дня из истории
	 *
	 * если ошибка то false
	 * если все ок и не найден платеж то array()
	 * $type = in|out|any
	 */
	protected function getLastPayment($dayCount, $type=false)
	{
		$result = array();

		$payments = $this->getLastPayments($dayCount);

		if($payments === false)
			return false;

		if(!$payments)
			return $result;

		foreach($payments as $payment)
		{
			if($type and $payment['type'] === $type)
				return $payment;
			elseif(!$type)
				return $payment;
		}

		return $result;
	}

	protected function request($strUrl, $postData=false, $proxy=false, $referrer=false, $withPause=true)
	{
		$tryCount = 3;

		if($proxy)
			$proxyStr = $proxy;
		elseif($this->proxy)
			$proxyStr = $this->proxy;
		else
			$proxyStr = '';

		if($withPause)
		{
			usleep($this->getPause());
		}


		if(!$this->sender->additionalHeaders)
			$this->sender->additionalHeaders = array(
				//'Accept: text/html,application/xhtml+xml,application/xml;q=0.'.rand(5, 9).',*/*;q=0.'.rand(5, 8),
				'Accept: */*',
			);

		$this->sender->additionalHeaders = array_merge($this->sender->additionalHeaders, array(
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: ru-RU,ru;q=0.'.rand(6, 8).',en-US;q=0.'.rand(3, 5).',en;q=0.'.rand(2, 3),
			'DNT: 1',
			'Connection: keep-alive',
			'Cache-Control: max-age=0',
		));


		//режим тестирования заголовков
		if($this->additional['testHeaderUrl'])
		{
			echo $this->sender->send($this->additional['testHeaderUrl'], $postData, $proxyStr, $referrer);
			echo $this->getCurrentCookie();
		}

		//повтор запроса если ответ 0
		for($i=1; $i<=$tryCount; $i++)
		{
			$this->lastContent = $this->sender->send($strUrl, $postData, $proxyStr, $referrer);

			if(in_array($this->sender->info['httpCode'][0], array(0, 404, 502)) === false)
				break;

			sleep(rand(1, 4));
		}

		//utf-8 неразрывный пробел
		$this->lastContent = utf8($this->lastContent);

		return $this->lastContent;
	}

	/*
	 * отдает или сохраняет произвольную информацию для каждого юзера в файле data.json
	 */
	protected function data($key, $val=null)
	{
		if(!$key)
		{
			$this->error = 'не указан $key в data()';
			return false;
		}


		$file = $this->userDir.'data.json';

		if(!file_exists($file))
		{
			file_put_contents($file, json_encode(array()));
			chmod($file, 0777);
		}

		$json = json_decode(file_get_contents($file), true);

		if($val===null)
		{
			//чтение
			return $json[$key];
		}
		else
		{
			//запись
			$json[$key] = $val;

			return file_put_contents($file, json_encode($json));
		}
	}

	/**
	 * выход из аккаунта
	 */
	public function logOut()
	{
		$this->sender->additionalHeaders = array(
			'X-Requested-With: XMLHttpRequest',
			'Accept: application/json, text/javascript, */*; q=0.01',
		);

		$content = $this->request('https://qiwi.com/auth/logout.action', 'exit', false);

		if($json = json_decode($content, true))
		{
			$content = $this->request('https://qiwi.com/auth/logout.action', 'token='.$json['data']['token'], false);

			$json = json_decode($content, true);

			if($json['code']['value']=='0')
				return true;
		}
	}

//	/**
//	 * вбить паспортные данные в аккаунт (частичная идентификация)
//	 *
//	 * $person = array(
//	 * 	'second_name'=>'Фамилия',
//	 * 	'first_name'=>'Имя',
//	 * 	'third_name'=>'Отчество',
//	 * 	'birth'=>'01.12.1990',
//	 * 	'passport'=>array('series'=>'1234', 'number'=>'123123'),
//	 * 	'inn'=>'123456789123',
//	 * 	'snils'=>'123456789123',
//	 * );
//	 */
//	public function identify(array $person)
//	{
//		$this->errorCode = 0;
//		//test для переидента
//		//$status = $this->getStatus();
//
//		//if($status == self::STATUS_HALF or $status == self::STATUS_FULL)
//		//	return true;
//
//		$url = 'https://qiwi.com/user/person/wallet/save.action';
//		$postData = 'lastName='.$person['second_name'].'&firstName='.$person['first_name'].'&middleName='.$person['third_name'].'&birthDateString='.$person['birth'].'&passport='.$person['passport']['series'].$person['passport']['number'].'&inn=&snils='.$person['snils'].'&oms=&doc=1';
//
//		$this->sender->additionalHeaders = array(
//			'X-Requested-With: XMLHttpRequest',
//			'Accept: application/json, text/javascript, */*; q=0.01',
//		);
//
//		$content = $this->request($url, $postData, null, 'https://qiwi.com/settings/options/wallet/edit.action');
//
//		if($json = json_decode($content, 1) and $json['data']['token'])
//		{
//			$url = 'https://qiwi.com/user/person/wallet/save.action';
//			$postData = 'inn=&middleName='.$person['third_name'].'&lastName='.$person['second_name'].'&oms=&doc=1&snils='.$person['snils'].'&birthDateString='.$person['birth'].'&passport='.$person['passport']['series'].$person['passport']['number'].'&firstName='.$person['first_name'].'&token='.$json['data']['token'];
//
//			$content = $this->request($url, $postData, false, 'https://qiwi.com/settings/options/wallet/edit.action');
//
//
//			if($json = json_decode($content, 1) and $json['code']['value']=='0')
//			{
//				toLog(Tools::arr2Str($json));
//				$this->_status = self::STATUS_HALF;
//				return true;
//			}
//			else
//			{
//				$str = ($json) ? $json['message'] : $content;
//
//				if(preg_match('!Паспорт с этим номером недействителен!ui', $str))
//					$this->errorCode = self::ERROR_PASSPORT_EXPIRED;
//
//				$this->error = 'identify json error2: '.$str.' '.$this->sender->info['httpCode'][0].' person: '.Tools::arr2Str($person);
//
//				return false;
//			}
//		}
//		else
//		{
//			$this->error = 'json error1: strlen: '.strlen($content);
//
//			return false;
//		}
//	}


	public function identify(array $person)
	{
		$this->errorCode = 0;

		$status = $this->getStatus();

		if(!$status)
			return false;

		if($status == self::STATUS_HALF or $status == self::STATUS_FULL)
			return true;

		if(!$accessToken = $this->getAccessToken())
			return false;

		$this->request('https://qiwi.com/settings/identification/form');

		$this->sender->additionalHeaders = array(
			'Accept: application/json',
			'content-Type: application/json',
			'client-software: '.self::CLIENT_SOFTWARE,
			'origin: https://qiwi.com',
			'authorization: TokenHeadV2 '.$accessToken,
		);
		$this->request('https://edge.qiwi.com/identification/v1/persons/'.$this->login.'/identification');


		$this->sender->additionalHeaders = array(
			'Accept: application/json',
			'content-Type: application/json',
			'client-software: '.self::CLIENT_SOFTWARE,
			'origin: https://qiwi.com',
			'authorization: TokenHeadV2 '.$accessToken,
		);



		$url = 'https://edge.qiwi.com/identification/v1/persons/'.$this->login.'/identification';
		$params = array(
			'birthDate'=>date('Y-m-d', strtotime($person['birth'])),
			'firstName'=>mb_ucfirst($person['first_name'], 'utf-8'),
			'inn'=>null,
			'lastName'=>mb_ucfirst($person['second_name'], 'utf-8'),
			'middleName'=>mb_ucfirst($person['third_name'], 'utf-8'),
			'oms'=>null,
			'passport'=>$person['passport']['series'].$person['passport']['number'],
			'snils'=>substr($person['snils'], 0, 3).'-'
				.substr($person['snils'], 3, 3)
				.'-'.substr($person['snils'], 6, 3)
				.' '.substr($person['snils'], 9, 2),
		);

		$postData = json_encode($params);


		$content = $this->request($url, $postData, null, 'https://qiwi.com/settings/identification/form');

		if(preg_match('!"type":"VERIFIED"!', $content, $res))
		{
			$this->_status = self::STATUS_HALF;
			return true;
		}
		else
		{
			if(preg_match('!"type":"SIMPLE"!ui', $content))
				$this->errorCode = self::ERROR_PASSPORT_NOT_VERIFIED;

			if(preg_match('!Паспорт с этим номером недействителен!ui', $content))
				$this->errorCode = self::ERROR_PASSPORT_EXPIRED;

			if(preg_match('!По этим данным можно идентифицировать не больше 5 кошельков!ui', $content))
				$this->errorCode = self::ERROR_PASSPORT_MAX_COUNT;


			$this->error = 'ident error : strlen '.strlen($content).' (code: '.$this->sender->info['httpHeader'][0].')';

			return false;
		}



	}

	/**
	 * смена пароля (требуется смс подтверждение)
	 */
	public function changePass($newPass, $sms=false)
	{
		if($sms)
		{
			if($identifier = $this->data('identifier'))
			{
				$this->sender->additionalHeaders = array(
					'X-Requested-With: XMLHttpRequest',
					'Accept: application/json, text/javascript, */*; q=0.01',
				);

				$url = 'https://qiwi.com/user/confirmation/confirm.action';
				$postData = 'identifier='.$identifier.'&type=PASSWORD_CHANGE&code='.$sms.'&data%5B\'oldPassword\'%5D='.$this->pass.'&data%5B\'newPassword\'%5D='.$newPass.'&data%5B\'period\'%5D=4';

				$content = $this->request($url, $postData, false, 'https://qiwi.com/settings/options/password.action');

				if($json = json_decode($content, true))
				{
					if($json['code']['value']=='0')
					{
						return true;
					}
					else
						$this->error = 'ошибка: '.$json['message'];
				}
				else
					$this->error = 'json error 6';
			}
			else
				$this->error = 'не найден identifier в changePass()';
		}
		else
		{
			$content = $this->request('https://qiwi.com/user/person/change/form/password.action');

			$this->sender->additionalHeaders = array(
				'X-Requested-With: XMLHttpRequest',
				'Accept: application/json, text/javascript, */*; q=0.01',
			);

			$content = $this->request('https://qiwi.com/user/person/change/password.action', 'change_password=yes', false, 'https://qiwi.com/settings/options/password.action');

			if($json = json_decode($content, true) and $json['identifier'])
			{
				$this->data('identifier', $json['identifier']);

				$postData = 'identifier='.$this->data('identifier').'&type=PASSWORD_CHANGE';

				$content = $this->request('https://qiwi.com/user/confirmation/form.action', $postData, false, 'https://qiwi.com/settings/options/password.action');

				if(strpos($content, '<input type="text" class="vcode" name="code"'))
				{
					return true;
				}
				else
					$this->error = 'preg error 1: strlen: '.strlen($content);

			}
			else
				$this->error = 'json error 1: strlen: '.strlen($content);
		}
	}

	/**
	 * очистка всех кук для выбранного аккаунта
	 */
	public static function clearCookie($login)
	{
		$dir = dirname(__FILE__).'/'.__CLASS__.'/users/';
		$files = scandir($dir);

		$login = trim($login, '+');

		foreach($files as $file)
		{
			if($file=='..' or $file=='.' or !is_dir($dir.$file))
				continue;

			$cookieFile = $dir.$file.'/cookie.txt';

			if($file==$login)
			{

				if(file_put_contents($cookieFile, '')===false)
					return false;
			}
		}

		return true;
	}

	public static function clearAllCookies()
	{
		$dir = dirname(__FILE__).'/'.__CLASS__.'/users/';
		$files = scandir($dir);

		foreach($files as $file)
		{
			if($file=='..' or $file=='.' or !is_dir($dir.$file))
				continue;

			$cookieFile = $dir.$file.'/cookie.txt';

			if(file_put_contents($cookieFile, '')===false)
			{
				toLog('error write file '.basename($cookieFile));
				return false;
			}

		}

		return true;
	}


	/**
	 * заблокирована ли карта
	 */
	public function hasBlockedCard()
	{
		$content = $this->request('https://qiwi.com/qvp/report.action');

		if(preg_match('!<h4 class="miniTitle">Выберите карту:</h4>!ui', $content))
		{
			if(preg_match('!data-params=\'\{"data":\{&quot;id&quot;:&quot;(\d+)&quot;\}\}\'!', $content, $res))
			{
				$content = $this->request('https://qiwi.com/user/qvp/content/info.action', 'id='.$res[1]);

				if(preg_match('!<h2>Информация о карте</h2>!ui', $content))
				{
					if(preg_match('!Карта заблокирована!ui', $content))
						return true;
					else
						return false;
				}
				else
					$this->error = 'error content2';
			}
			else
				$this->error = 'no_card';
		}
		else
			$this->error = 'error content1';
	}

	public function isPasswordExpired($content=false)
	{
		if(!$content)
			$content = $this->request('https://qiwi.com/report.action');


		if(preg_match('!Внимание\! Срок действия вашего пароля истек\.!ui', $content))
		{
			$this->error = 'Внимание! Срок действия вашего пароля истек.';
			$this->errorCode = self::ERROR_PASSWORD_EXPIRED;

			return true;
		}
	}

	public function deleteCaptcha()
	{
		$file = $this->captchaDir.$this->login.'.png';

		if(file_exists($file))
		{
			return unlink($file);
		}
		else
			return true;
	}

	public function isEmailLinked()
	{
		$content = $this->request('https://qiwi.com/settings/options/security.action');

		if(strpos($content, '{"data":{"type":"SMS_CONFIRMATION","value":false}}'))
		{
			if(preg_match('!<div class="toggle" style="display:none;" data-container-name="option-disabled">\s+<div class="pseudo-checkbox" data-href="/settings/options/security/email.action" data-params=\'\{"data":\{"type":"EMAIL","value":true\}\}\'>!is', $content))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			$this->error = 'ошибка контента hasLockedSecurity(): ';
		}
	}

	public function linkEmail($email)
	{
		$this->sender->additionalHeaders = array(
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Accept: application/json, text/javascript, */*; q=0.01',
		);

		$url = 'https://qiwi.com/user/person/email/create/send.action';
		$postData = 'mail='.rawurlencode($email);//.'&confirmMail='.$email;
		$ref = 'https://qiwi.com/settings/options/security/email.action';

		$content = $this->request($url, $postData, false, $ref);

		if(preg_match('!"token":"(.+?)"!', $content, $res))
		{
			$token = $res[1];

			$postData .= '&token='.$token;
			$content = $this->request($url, $postData, false, $ref);

			if(preg_match('!"code":\{"value":"0"!', $content))
			{
				return true;
			}
			else
			{
				$this->error = 'error step 2: strlen:'.strlen($content).' httpCode='.$this->sender->info['httpCode'][0].'(email='.$email.')';

				if($json = json_decode($content) and isset($json['message']))
					$this->error .= ' error='.$json['message'];
			}
		}
		else
		{
			$this->error = 'error step 1: strlen: '.strlen($content);
		}
	}

	public function completeLinkEmail($emailCode)
	{
		$url = 'https://qiwi.com/settings/options/security/email/create.action?code='.$emailCode;
		$content = $this->request($url);

		if(preg_match('!<h2>Почта:.+?привязана</h2>!ui', $content, $res))
			return true;
		else
			$this->error = 'error completeLinkEmail: strlen: '.strlen($content);

		return false;
	}

	/**
	 * включены в настройках безопасности: Приложения Qiwi Visa Wallet
	 */
	public function isAppsEnabled()
	{
		$content = $this->request('https://qiwi.com/settings/options/security.action');

		if(strpos($content, '{"data":{"type":"SMS_CONFIRMATION","value":false}}'))
		{
			if(preg_match('!<div class="toggle" style="display:none;" data-container-name="option-disabled">\s+<div class="pseudo-checkbox" data-action="update" data-container-name="option-update" data-params=\'\{"data":\{"type":"TOKEN","value":true\}\}\'>.+?<h3>Приложения Visa QIWI Кошелька</h3>!isu', $content))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			$this->error = 'ошибка контента isAppsEnabled(): ';
			return null;
		}
	}

	public function disableApps()
	{
		$this->sender->additionalHeaders = array(
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
		);

		$url = 'https://qiwi.com/user/person/change/security.action';
		$postData = 'type=TOKEN&value=false';
		$ref = 'https://qiwi.com/settings/options/security.action';

		$content = $this->request($url, $postData, false, $ref);

		if(preg_match('!<input type="hidden" name="token" value="(.+?)"/>!', $content, $res))
		{
			$token = $res[1];

			$url = 'https://qiwi.com/user/person/change/security.action';
			$postData .= '&token='.$token;

			$content = $this->request($url, $postData, false, $ref);

			if(strpos($content, '"code":{"value":"0"')!==false)
			{
				return true;
			}
			else
				$this->error = 'error step 2: strlen: '.strlen($content);
		}
		else
			$this->error = 'error step 1: strlen: '.strlen($content);
	}

	/**
	 * включен ли доступ по пин-коду в настройках безопасности
	 */
	public function isPinEnabled()
	{
		//у украины нет доступа по пинкоду
		if(preg_match('!^380!', $this->login))
			return false;

		$content = $this->request('https://qiwi.com/settings/options/security.action');

		if(strpos($content, '{"data":{"type":"SMS_CONFIRMATION","value":false}}'))
		{
			if(preg_match('!<div class="toggle" style="display:none;" data-container-name="option-disabled">\s+<div class="pseudo-checkbox" data-action="update" data-container-name="option-update" data-params=\'\{"data":\{"type":"PIN","value":true\}\}\'>!is', $content))
			{
				return true;
			}
			else
			{
				return false;
			}

		}
		else
		{
			$this->error = 'ошибка контента isPinEnabled(): ';
		}
	}

	public function disablePin()
	{
		$this->sender->additionalHeaders = array(
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
		);

		$url = 'https://qiwi.com/user/person/change/security.action';
		$postData = 'type=PIN&value=false';
		$ref = 'https://qiwi.com/settings/options/security.action';

		$content = $this->request($url, $postData, false, $ref);

		if(preg_match('!<input type="hidden" name="token" value="(.+?)"/>!', $content, $res))
		{
			$token = $res[1];

			$url = 'https://qiwi.com/user/person/change/security.action';
			$postData .= '&token='.$token;

			$content = $this->request($url, $postData, false, $ref);

			if(strpos($content, '"code":{"value":"0"')!==false)
			{
				return true;
			}
			else
				$this->error = 'error step 2: strlen: '.strlen($content);
		}
		else
			$this->error = 'error step 1: strlen: '.strlen($content);
	}

	/**
	 * включены ли смс-платежи в безопасности
	 */
	public function isSmsPaymentEnabled()
	{
		$content = $this->request('https://qiwi.com/settings/options/security.action');

		if(strpos($content, '{"data":{"type":"SMS_CONFIRMATION","value":false}}'))
		{
			if(preg_match('!<div class="toggle" style="display:none;" data-container-name="option-disabled">\s+<div class="pseudo-checkbox" data-action="update" data-container-name="option-update" data-params=\'\{"data":\{"type":"SMS_PAYMENT","value":true\}\}\'>!is', $content))
			{
				return true;
			}
			else
			{
				return false;
			}

		}
		else
		{
			$this->error = 'ошибка контента isSmsPaymentEnabled(): ';
		}
	}

	public function disableSmsPayment()
	{
		$this->sender->additionalHeaders = array(
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
		);

		$url = 'https://qiwi.com/user/person/change/security.action';
		$postData = 'type=SMS_PAYMENT&value=false';
		$ref = 'https://qiwi.com/settings/options/security.action';

		$content = $this->request($url, $postData, false, $ref);

		if(preg_match('!<input type="hidden" name="token" value="(.+?)"/>!', $content, $res))
		{
			$token = $res[1];

			$url = 'https://qiwi.com/user/person/change/security.action';
			$postData .= '&token='.$token;

			$content = $this->request($url, $postData, false, $ref);

			if(strpos($content, '"code":{"value":"0"')!==false)
			{
				return true;
			}
			else
				$this->error = 'error step 2: strlen: '.strlen($content);
		}
		else
			$this->error = 'error step 1: strlen: '.strlen($content);
	}

	/**
	 * включены ли смс-платежи в безопасности
	 */
	public function isUssdEnabled()
	{
		$content = $this->request('https://qiwi.com/settings/options/security.action');

		if(strpos($content, '{"data":{"type":"SMS_CONFIRMATION","value":false}}'))
		{
			if(preg_match('!<div class="toggle" style="display:none;" data-container-name="option-disabled">\s+<div class="pseudo-checkbox" data-action="update" data-container-name="option-update" data-params=\'\{"data":\{"type":"CALL_CONFIRMATION","value":true\}\}\'>!is', $content))
				return true;
			else
				return false;
		}
		else
			$this->error = 'ошибка контента isUssdEnabled(): ';
	}

	public function disableUssd($sms=false)
	{
		$this->sender->additionalHeaders = array(
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
		);

		if($sms)
		{
			$url = 'https://qiwi.com/user/confirmation/confirm.action';
			$postData = 'identifier='.$this->data('identifier').'&type=CALL_CONFIRMATION&code='.$sms;
			$ref = 'https://qiwi.com/settings/options/security.action';

			$content = $this->request($url, $postData, false, $ref);

			if(strpos($content, '"code":{"value":"0"')!==false)
				return true;
			else
				$this->error = 'error step 3: strlen: '.strlen($content);
		}
		else
		{

			$url = 'https://qiwi.com/user/person/change/security.action';
			$postData = 'type=CALL_CONFIRMATION&value=false';
			$ref = 'https://qiwi.com/settings/options/security.action';

			$content = $this->request($url, $postData, false, $ref);

			if(preg_match('!<input type="hidden" name="token" value="(.+?)"/>!', $content, $res))
			{
				$token = $res[1];

				$url = 'https://qiwi.com/user/person/change/security.action';
				$postData .= '&token='.$token;

				$content = $this->request($url, $postData, false, $ref);

				if(preg_match('!"identifier":"(.+?)"!', $content, $res))
				{
					$this->data('identifier', $res[1]);

					$url = 'https://qiwi.com/user/confirmation/form.action';
					$postData = 'value=false&type=CALL_CONFIRMATION&token='.$token.'&identifier='.$this->data('identifier');
					$ref = 'https://qiwi.com/settings/options/security.action';

					$content = $this->request($url, $postData, false, $ref);

					if(strpos($content, '<div class="confirm">'))
						return true;
					else
						$this->error = 'error step 2: strlen: '.strlen($content);
				}
				else
					$this->error = 'error step 2: strlen:'.strlen($content);
			}
			else
				$this->error = 'error step 1: strlen:'.strlen($content);
		}
	}

	/*
	 * получение общей информации о кошельке и владельце
	 * (не доделано)
	 */
	public function info()
	{
		$content = 	$this->request('https://qiwi.com/settings/options/wallet.action');

		return $content;
	}

	protected function timestamp()
	{
		return round(microtime(true)*1000).'';
	}

	public function fixCurrency($currency = 'RUB')
	{
		$content = $this->request('https://qiwi.com/settings/account/report.action');

		//проверить верная ли страница
		if(
			preg_match('!<a href="#" class="primary">Основной</a>!', $content, $res)
			and
			$dom = Tools::dom($content)
		)
		{
			if($el = $dom->find('div.data ul.actions. a.primary', 0))
			{
				$text = $el->parent()->parent()->parent()->find('div.currency span', 0)->innertext;

				$this->sender->additionalHeaders = array(
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With: XMLHttpRequest',
				);

				//если текущая не отличается от нужной
				if($text == $currency)
					return true;
				elseif(!preg_match('!"id":"(\d+_RUB)"!', $content, $res))
				{
					$url = 'https://qiwi.com/user/person/account/create.action';
					$postData = 'currency='.$currency.'&agreement=true';
					$ref = 'https://qiwi.com/settings/account/form.action';

					$content = $this->request($url, $postData, false, $ref);

					if(preg_match('!<input type="hidden" name="token" value="(.+?)"/>!', $content, $res))
					{
						$token = $res[1];

						$postData = 'token='.$token.'&agreement=true&currency='.$currency;

						$content = $this->request($url, $postData, false, $ref);

						if(preg_match('!<span>Счет создан</span>!u', $content))
						{
							$content = $this->request('https://qiwi.com/settings/account/report.action');
							preg_match('!"id":"(\d+_RUB)"!', $content, $res);
						}
						else
						{
							$this->error = 'ошибка создания счета step 2: strlen:'.strlen($content);
							return false;
						}
					}
					else
						$this->error = 'ошибка создания счета step 1: strlen:'.strlen($content);


				}

				//назначаем счет основным
				$url = 'https://qiwi.com/user/person/account/setmain.action';
				$postData = 'id='.$res[1];
				$ref = 'https://qiwi.com/settings/account/form.action';

				$content = $this->request($url, $postData, false, $ref);

				if(preg_match('!<input type="hidden" name="token" value="(.+?)"/>!', $content, $res))
				{
					$token = $res[1];

					$postData .= '&token='.$token;

					$content = $this->request($url, $postData, false, $ref);

					if(strpos($content, '{"code":{"value":"0"')!==false)
					{
						return true;
					}
					else
					{
						$this->error = 'ошибка создания счета step 4: strlen:'.strlen($content);
					}
				}
				else
					$this->error = 'ошибка создания счета step 3: strlen:'.strlen($content);

				return false;
			}
			else
			{
				$this->error = 'content error2, httpCode='.$this->sender->info['httpCode'][0];
				return false;
			}
		}
		else
		{
			$this->error = 'content error1, httpCode='.$this->sender->info['httpCode'][0];
			return false;
		}
	}

	public static function getTransactionsInfo(array $transactions)
	{
		$result = array(
			'inAmount'=>0,
			'outAmount'=>0,
		);

		foreach($transactions as $trans)
		{
			if($trans['status'] === self::TRANSACTION_STATUS_SUCCESS)
			{
				if($trans['type'] === self::TRANSACTION_TYPE_IN)
					$result['inAmount'] += $trans['amount'];
				if($trans['type'] === self::TRANSACTION_TYPE_OUT)
					$result['outAmount'] += $trans['amount'];
			}
		}

		return $result;
	}


	/*
	 * возвращает необходимую паузу между запросами для функции usleep(): 1000000 - 1сек
	 */
	protected function getPause()
	{
		$dec = 10; //точность до десятых

		$min = intval($this->pauseMin * $dec);
		$max = intval($this->pauseMax * $dec);

		return intval(rand($min, $max)/$dec*1000000);
	}

	public function getCurrentCookie()
	{
		$fileCookie = $this->userDir.'cookie.txt';

		if(file_exists($fileCookie))
			return file_get_contents($fileCookie);
		else
			return '';
	}

	protected function setCookie($str)
	{
		$file = $this->userDir.'cookie.txt';

		$content = $this->getCurrentCookie();

		$result = $content.Tools::getSep($content).$str;

		return file_put_contents($file, $result);
	}

	public function getMyIp()
	{
		$url = cfg('my_ip_url');

		$content = $this->request($url, false, false, false, false);

		if($this->sender->info['httpCode'][0] == 200)
			return $content;
		else
			return false;
	}

	/*
	 * получает постоянный гуглАпи код для антикапчи
	 * todo:  сделать получение и автообновление кода в интервал времени
	 */
	public function getGoogleCaptchaKey()
	{
		return '6LfjX_4SAAAAAFfINkDklY_r2Q5BRiEqmLjs4UAC';
	}

	protected function generateBrowserCrc()
	{
		return substr(md5($this->browser), 0, 8);
	}

	public function checkPercent()
	{
		$content = $this->request('https://qiwi.com/transfer/email.action');
	}

	/*
	 * прибавляет комиссию к $amount
	 */
	public function getAmountWithCommission($amount)
	{
		if(!$this->isCommission)
			return $amount;

		if($this->isCommission == '1')
			$commission = self::COMMISSION;
		elseif($this->isCommission == '2')
			$commission = self::COMMISSION_FULL;
		else
			$commission = 0;

		return round($amount * (1 + $commission), 2);
	}

	/**
	 * вычитает комиссию из $amount
	 * @param float $amount
	 * @param string $type (qiwi|voucher)
	 * @return float
	 */
	public function getAmountForTransaction($amount, $type='qiwi')
	{
		$commission = 0;

		if($type == 'qiwi')
		{
			if(!$this->isCommission)
				return $amount;

			if($this->isCommission == '1')
				$commission = self::COMMISSION;
			elseif($this->isCommission == '2')
				$commission = self::COMMISSION_FULL;
			else
				$commission = 0;
		}
		elseif($type == 'voucher')
			$commission = self::VOUCHER_COMMISSION_PERCENT;

		$result = round($amount / (1 + $commission), 2);

		if($type == 'voucher' and $result - $amount < self::VOUCHER_COMMISSION_AMOUNT)
			$result = $amount + self::VOUCHER_COMMISSION_AMOUNT;

		return $result;
	}

	public function convert($currencyFrom, $currencyTo, $amountFrom)
	{

	}


	/**
	 * @param string $smsCode
	 * @return bool
	 */
	public function getApiToken($smsCode = '')
	{
		$referer = 'https://qiwi.com/api';

		if(!strpos($this->getCurrentCookie(), 'user_info'))
			$this->setCookie('qiwi.com	FALSE	/	TRUE	0	user_info	1');

		if(!$smsCode)
		{
			$this->request('https://qiwi.com/api');

			$this->sender->additionalHeaders = array(
				'Content-Type: application/json',
				'Origin: https://qiwi.com',
			);

			$url = 'https://sso.qiwi.com/cas/sts';
			$post = '{"ticket":"'.$this->data('ticket').'","service":"https://qiwi.com/j_spring_cas_security_check"}';

			$content = $this->request($url, $post, false, $referer);

			if(!preg_match('!"ticket":"(ST[^"]+)"!', $content, $res))
			{
				$this->error = 'error get token step1';

				if($json = @json_decode($content, true) and $json['message'])
					$this->error .= ': '.$json['message'];

				return false;
			}

			$this->sender->additionalHeaders = array(
				'Accept: application/json',
				'content-type: application/x-www-form-urlencoded',
				'origin: https://qiwi.com',
				'client-software: '.self::CLIENT_SOFTWARE,
			);

			$url = 'https://qiwi.com/oauth/authorize';

			$post = 'response_type=code&client_id=qiwi_wallet_api&client_software='.self::CLIENT_SOFTWARE.'&username='
				.$this->login.'&service_name=https%3A%2F%2Fqiwi.com%2Fj_spring_cas_security_check&ticket='
				.$res[1].'&scope=read_person_profile%20read_balance%20read_payment_history%20accept_payments';

			$content = $this->request($url, $post, false, $referer);

			if(preg_match('!\{"code":"(.+?)"\}!', $content, $res))
			{
				$this->data('apiTokenCode', $res[1]);
				return true;
			}
			else
			{
				$this->error = 'error get token step2';
				return false;
			}
		}
		else
		{

			$this->sender->additionalHeaders = array(
				'Accept: application/json',
				'content-type: application/x-www-form-urlencoded',
				'origin: https://qiwi.com',
				'client-software: '.self::CLIENT_SOFTWARE,
			);

			$url = 'https://qiwi.com/oauth/token';
			$post = 'grant_type=urn%3Aqiwi%3Aoauth%3Agrant-type%3Avcode&client_id=qiwi_wallet_api&code='.$this->data('apiTokenCode').'&vcode='.$smsCode;

			$content = $this->request($url, $post, false, $referer);

			if(preg_match('!"access_token":"(.+?)"!', $content, $res))
				return $res[1];
			else
			{
				$this->error = 'error get token step3';
				return false;
			}
		}
	}

	private function getAccessToken()
	{
		$token = $this->data('accessToken');

		if($token and time() < $this->data('accessTokenExpire'))
			return $token;

		$referer = 'https://qiwi.com/';

		$this->sender->additionalHeaders = array(
			'Accept: application/vnd.qiwi.sso-v1+json',
			'Content-Type: application/json',
			'origin: https://qiwi.com',
		);

		$url = 'https://sso.qiwi.com/cas/sts';
		$postData = '{"ticket":"'.$this->data('ticket').'","service":"https://qiwi.com/j_spring_cas_security_check"}';
		$content = $this->request($url, $postData, null, $referer);

		if(!preg_match('!"ticket":"(ST[^"]+)"!', $content, $res))
		{
			$this->error = 'no ticket ST: '.$content.' (code: '.$this->sender->info['httpHeader'][0].')';
			echo "\n чищу куки";
			self::clearCookie($this->login);
			return false;
		}

		//3
		$this->sender->additionalHeaders = array(
			'Accept: application/json',
			'content-type: application/x-www-form-urlencoded',
			'origin: https://qiwi.com',
			'client-software: '.self::CLIENT_SOFTWARE,
		);

		$url = 'https://qiwi.com/oauth/token';
		$postData = 'grant_type=sso_service_ticket&client_id=sso.qiwi.com&client_software='
			.self::CLIENT_SOFTWARE.'&service_name=https://qiwi.com/j_spring_cas_security_check&ticket='.$res[1];
		$content = $this->request($url, $postData, null, $referer);

		if(!preg_match('!"access_token":"([^"]+)".+?"expires_in":"(\d+)"!', $content, $res))
		{
			$this->error = 'no access_token: '.$content
				.' (code: '.$this->sender->info['httpHeader'][0].')';

			if(preg_match('!Token lifetime has expired!', $content))
				self::clearCookie($this->login);

			return false;
		}

		$accessToken = base64_encode('sso.qiwi.com:' . $res[1]);
		$expireTimestamp = time() + $res[2];

		$this->data('accessToken', $accessToken);
		$this->data('accessTokenExpire', $expireTimestamp);

		return $accessToken;
	}

	/**
	 * * $dayCount - кол-во дней, за которые нужно получить платежи
	 * использует переменную historyBug в data.json
	 * historyBug = 1 - нужно грузить историю по частям: time()-3600*24*$dayCount до вчера + со вчера до сегодня
	 *
	 * @param int $dayCount
	 * @param bool $allCurrencies	мультивалютность(иначе только рубли)
	 * @return array|false
	 */
	public function getLastPaymentsTest($dayCount = 0, $allCurrencies=false)
	{
		if(!$accessToken = $this->getAccessToken())
			return false;

		$dateStart = date('c' ,time()-3600*24*$dayCount);
		$dateEnd = date('c', time() + 3600*24);


		$this->sender->additionalHeaders = array(
			'Accept: application/vnd.qiwi.v2+json',
			'content-Type: application/json',
			'client-software: '.self::CLIENT_SOFTWARE,
			'origin: https://qiwi.com',
			'authorization: TokenHeadV2 '.$accessToken,
		);

		$url = 'https://edge.qiwi.com/payment-history/v2/persons/'.$this->login
			.'/payments?rows=50&startDate='.urlencode($dateStart).'&endDate='.urlencode($dateEnd);

		$content = $this->request($url, false, false, $url);

		if(!$paymentArr = @json_decode($content, true))
		{
			$this->error = 'history error : strlen: '.strlen($content).' (code: '.$this->sender->info['httpHeader'][0].')';
			return false;
		}

		prrd($paymentArr);

		return $this->getHistoryTest($paymentArr);
	}


	/**
	 * список всех платежей
	 *
	 * array(
	 * 	'id'=>'',//ID платежа в киви
	 * 	'type'=>'',	//тип (in, out)
	 * 	'wallet'=>'', //кошелек
	 * 	'comment'=>'', //коммент к платежу (cash - снятия с банкомата)
	 * 	'amount'=>'', //сумма включая комиссию
	 * 	'commission'=>0,	//комиссия
	 * 	'status'=>success,wait,error
	 * 	'timestamp'=>2137213612,//метка времени
	 * 	'date'=>22.12.2016,//форматированная дата
	 * 	'error'=>'',
	 * 	'errorCode'=>'',
	 * 	'currency'=>'RUB|KZT', //этот элемент доступен только если включен $allCurrencies
	 * )
	 *
	 * @param string $content
	 * @param bool $allCurrencies если true то парсит все валюты а не только рубли и выдает 'currency'=>'...',
	 * @return array|false
	 */
	protected function getHistoryTest($content, $allCurrencies = false)
	{

		if($this->sender->info['httpCode'][0] != 200)
		{
			$this->error = 'код ответа: '.$this->sender->info['httpCode'][0].' ';
			return false;
		}

		if(!preg_match('!</html>!', $content))
		{
			$this->error = 'контент не догружен (нет </html>)';
			return false;
		}


		//$this->updateBalance($content);
		$arResult = array();

		$contentAll = $content;

		$dom = phpQuery::newDocument($content);

		if($dom)
		{
			if($divs = $dom->find('div[data-container-name=item].status_SUCCESS,div[data-container-name=item].status_PROCESSED,div[data-container-name=item].status_ERROR'))
			{
				foreach($divs as $div)
				{
					$pq = pq($div);

					$content = $pq->html();

					if(strpos($pq->attr('class'), 'status_SUCCESS')!==false)
						$status = 'success';
					elseif(strpos($pq->attr('class'), 'status_PROCESSED')!==false)
						$status = 'wait';
					elseif(strpos($pq->attr('class'), 'status_ERROR')!==false)
						$status = 'error';
					else
						toLog('error in payment status: '.$pq->attr('class'), 1);

					$errorCode = '';

					if($status=='error')
					{
						//текст ошибки
						if(preg_match('!<a href="\#" class="error" data-action="item-error" data-params=\'\{"message":"(.+?)"\}\'>!', $content, $res))
						{
							$error = trim($res[1]);

							if(preg_match('!Ежемесячный лимит платежей и переводов для статуса!iu', $error))
								$errorCode = self::TRANSACTION_ERROR_LIMIT;
						}
						else
							$error = '';
					}
					else
						$error = '';

					$comment = '';

					//если снятие
					if(preg_match('!<div class="provider">\s+<span>(.+?)</span>!us', $content, $res))
						$provider = $res[1];
					else
					{
						$provider = '';
					}

					$type = '';

					$isCash = 0;

					if(mb_strpos($provider, 'QVP: Снятие наличных в банкомате', 0, 'utf-8')!==false)
					{
						//при выводе с карты комиссии не указано и сумма в другом блоке
						if(preg_match('!<div class="originalExpense">\s+<span>(.+?)</span>!s', $content, $res))
						{
							$amountText = $res[1];
							$type = 'out';
							//$isCash = 1;

						}
						else
						{
							$this->error = 'не найден <div class="originalExpense">'.$content;
							return false;
						}
					}
					else
					{
						if(preg_match('!expenditure">\s+<div class="cash">(.+?)</div>!s', $content, $res))
						{
							$amountText = $res[1];
							$type = 'out';
						}
						elseif(preg_match('!income">\s+<div class="cash">(.+?)</div>!s', $content, $res))
						{
							$amountText = $res[1];
							$type = 'in';
						}
						else
						{
							$this->error = 'error payment type: '.$content;
							return false;
						}
					}

					$currency = '';

					//парсим только рубли, если не включен $allCurrencies
					if($allCurrencies)
					{
						die($amountText);
					}
					else
					{
						if(!preg_match('!руб\.!u', $amountText))
						{
							//toLog('currency error found: '.$this->login.': '.$amountText);
							continue;
						}
					}

					$amount = $this->parseAmount($amountText);

					if($amount===false)
					{
						$this->error = 'wrong amount: '.$amountText;
						return false;
					}


					//откуда или куда перевод
					if(preg_match('!<span class="opNumber">(.+?)</span>!u', $content, $res))
						$wallet = $res[1];
					else
					{
						$this->error = 'span class="opNumber"> not found on: '.$content;
						return false;
					}

					//комментарий
					if(!$comment)
					{
						if(preg_match('!<div class="comment">(.*?)</div>!su', $content, $res))
							$comment = $res[1];
						else
						{
							$this->error = 'div class="comment"> not found on: '.$content;
							return false;
						}
					}

					//дата
					if(preg_match('!<span class="date">(.+?)</span>!s', $content, $res))
						$date = trim($res[1]);
					else
					{
						$this->error = 'span class="date"> not found on: '.$content;
						return false;
					}

					//время
					if(preg_match('!<span class="time">(.+?)</span>!s', $content, $res))
						$time = trim($res[1]);
					else
					{
						$this->error = 'span class="time"> not found on: '.$content;
						return false;
					}

					//id
					if(preg_match('!<div class="transaction">(.+?)</div>!s', $content, $res))
						$id = trim($res[1]);
					elseif(preg_match('!href="/report/cheque\.action\?transaction=(\d+)&amp;direction=OUT" class="cheque"!is', $content, $res))
					{
						$id = trim($res[1]);
					}
					elseif(preg_match('!<div class="transaction" data-action="item-extra" data-params=\'\{"data":\{"txn":(\d+)\}\}\'>!is', $content, $res))
					{
						//снятие с карты
						$id = trim($res[1]);
					}
					else
					{
						$this->error = 'div class="transaction"> not found on: '.$content;
						return false;
					}

					$commission = 0;

					if(preg_match('!<div class="commission">(.+?)</div>!s', $content, $res))
					{
						$commissionStr = trim($res[1]);

						if(!empty($commissionStr))
						{
							$commission = $this->parseAmount($commissionStr);

							if($commission===false)
								toLog('error parse amount on commission', 1);
						}

					}
					else
					{
						$this->error = 'div class="commission"> not found on: '.$content;
						return false;
					}


					if($comment=='cash1')
					{
						//проставить комиссии
						if(!$commission)
						{
							if($amount==10250 or $amount==10000)
							{
								$amount = 10000;
								$commission = 250;
							}
							elseif($amount==5150  or $amount==5000)
							{
								$amount = 5000;
								$commission = 150;
							}
							elseif($amount==4640 or $amount==4500)
							{
								$amount = 4500;
								$commission = 140;
							}
							elseif($amount==4125)
							{
								$amount = 4000;
								$commission = 125;
							}
							else
							{
								$this->error = 'неизвестная сумма снятия ('.$amount.'): '.$content;
								return false;
							}
						}
					}

					$timestamp = strtotime($date.' '.$time);

					$arr = array(
						'id'=>$id,
						'type'=>$type,
						'status'=>$status,
						'amount'=>$amount,
						'commission'=>$commission,
						'wallet'=>$wallet,
						'timestamp'=>$timestamp,
						'date'=>date('d.m.Y H:i', $timestamp),
						'comment'=>$comment,
						'error'=>$error,
						'errorCode'=>$errorCode,
						'is_cash'=>(preg_match('!, карта \d{4}\*\*\*\*\d{4}!iu', $wallet)) ? 1 : 0,
					);

					if($allCurrencies)
						$arr['currency'] = $currency;

					$arResult[] = $arr;

				}

				Tools::multisort($arResult, 'timestamp', SORT_DESC);


			}
			else
			{
				//toLog('no history '.$content);
				return $arResult;
			}
		}
		else
		{
			toLog('no dom on getHistory');
			return false;
		}

		return $arResult;
	}

	/**
	 * последние неактвированные ваучеры(сортировка: сначала самые новые)
	 * @param int $timestampStart
	 * @return array|false [['code'=>'DFDV7234623...', 'amount'=>233.23, 'date'=>'13.04.2016', 'timestamp'=>213123123]]
	 */
	public function getVouchers($timestampStart)
	{
		$result = array();

		$dateStart = date('d.m.Y', $timestampStart);
		$datEnd = date('d.m.Y', time()+3600*24);

		$url = 'https://qiwi.com/transfer/eggs/list.action?currentPage=1&maxItemsPerPage=24&from='
			.$dateStart.'&to='.$datEnd.'&status=WAITED&SortOrder=2';

		$content = $this->request($url);

		if(!preg_match('!<div class="qiwi-eggs">!', $content))
		{
			$this->error = 'getVouchers error content strlen: '.strlen($content);
			return false;
		}


		if(preg_match_all('!<div class="date">(.+?)</div>\s+<div class="summ">(.+?)</div>.+?Код ваучера: ([^\s]+)!isu', $content, $res))
		{
			foreach($res[3] as $key=>$code)
				$result[] = [
					'code'=>$code,
					'amount'=>$this->parseAmount($res[2][$key]),
					'date'=>$res[1][$key],
					'timestamp'=>strtotime($res[1][$key]),
				];
		}

		return $result;
	}

	/**
	 * @param float $amount
	 * @return bool
	 */
	public function createVoucher($amount)
	{
		if($amount < self::VOUCHER_AMOUNT_MIN or $amount > self::VOUCHER_AMOUNT_MAX)
		{
			$this->error = 'wrong amount';
			return false;
		}

		if($this->balance === false)
		{
			if($this->getBalance())
				return false;
		}

		$amountWithCommission = $this->getAmountForTransaction($amount);

		if($amountWithCommission > $this->balance)
		{
			$this->error = 'no money';
			return false;
		}

		if(!$accessToken = $this->getAccessToken())
			return false;

		$this->sender->additionalHeaders = array(
			'Accept: application/vnd.qiwi.v2+json',
			'content-Type: application/json',
			'client-software: '.self::CLIENT_SOFTWARE,
			'origin: https://qiwi.com',
			'authorization: TokenHeadV2 '.$accessToken,
			'Referer: https://qiwi.com/payment/form/22496',
		);

		$url = 'https://edge.qiwi.com/sinap/api/terms/22496/payments';

		$postArr = array(
			'id'=>self::timestamp().'',
			'sum'=>array(
				'amount'=>$amount,
				'currency'=>'643',
			),
			'paymentMethod'=>array(
				'accountId'=>'643',
				'type'=>'Account',
			),

			'comment'=>'',

			'fields'=>array(
				'sinap-form-version'=>'qw::22496, 3',
				'account'=>'708',
				'to_account_type'=>'undefind',
				'browser_user_agent_crc'=>$this->generateBrowserCrc(),
			),
		);

		$content = $this->request($url, json_encode($postArr));

		if(preg_match('!"code":"Accepted"!isu', $content))
			return true;
		else
		{
			$this->error = 'error createVoucher: '.strlen($content).' (code: '.$this->sender->info['httpHeader'][0].')';
			return false;
		}
	}


	public function activateVoucher($code)
	{
		$this->sender->additionalHeaders = array(
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Referer: https://qiwi.com/transfer/eggs/activate.action?code='.$code,
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
		);

		$url = 'https://qiwi.com/user/eggs/activate/content/activate.action';
		$content = $this->request($url, "code=$code");

		if(preg_match('!{"code":{"value":"0"!', $content))
			return true;
		else
		{
			if(preg_match('!"message":(.+?),!', $content, $res) and $json = @json_decode($content, true))
				$this->error = $json['message'];
			else
				$this->error = 'error activateVoucher strlen: '.strlen($content);

			return false;
		}
	}

	protected function transactionYandex($wallet, $amount, $comment = '')
	{
		$wallet = trim($wallet);
		$amount = str_replace(',', '.', $amount);

		if(!preg_match('!\d{14}!', $wallet))
		{
			$this->error = 'неверный номер счета яндекс';
			return false;
		}

		$decs = '';

		if(strpos($amount, '.')!==false)
		{
			$decs = substr($amount, strpos($amount, '.')+1, 2);

			if(strlen($decs)<2)
				$decs .= '0';

			if($decs<10)
				$decs = '';
		}

		$amount = floorAmount($amount, 0);

		if($decs)
			$sendAmount = $amount.'.'.$decs;
		else
			$sendAmount = $amount;

		$this->sender->additionalHeaders = array(
			'Content-Type: application/json; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Accept: application/vnd.qiwi.v2+json',
		);

		$url = 'https://qiwi.com/payment/form/26476';
		$referer = '';
		$this->request($url, false, false, $referer);

		if(!$token = $this->getAccessToken())
			return false;

		$this->sender->additionalHeaders = array(
			'Accept: application/vnd.qiwi.v2+json',
			'content-type: application/json',
			'X-Requested-With: XMLHttpRequest',
			'x-application-id: '.self::APPLICATION_ID,
			'x-application-secret: '.self::APLICATION_SECRET,
			'client-software: '.self::CLIENT_SOFTWARE,
			'authorization: TokenHeadV2 '.$token,
		);

		$url = 'https://edge.qiwi.com/sinap/api/terms/26476/payments';
		$transactionId = self::timestamp();

		$params = [
			'id'=>$transactionId,
			'sum'=>[
				'amount'=>$sendAmount,
				'currency'=>'643',
			],
			'paymentMethod'=>[
				'accountId'=>'643',
				'type'=>'Account',
			],
			'comment'=>$comment,
			'fields'=>[
				'sinap-form-version'=>'qw::26476, 8',
				'account'=>$wallet,
				'browser_user_agent_crc'=>$this->generateBrowserCrc()
			],
		];

		$referer = 'https://qiwi.com/payment/form/26476';

		$paymentTimestamp = time();

		$content = $this->request($url, json_encode($params), false, $referer);

		if(!$response = @json_decode($content, true))
		{
			$this->error = 'json decode error, strlen: '.strlen($content);
			return false;
		}


		if($response['transaction']['state']['code'] == 'Accepted')
			return $sendAmount;
		else
		{
			print_r($response);
			print_r($this->sender->info);
			die($content.' fff test yandex');
		}


		die($content);

		if(preg_match('!"transaction":{"id":"\d+","state":\{"code":"Accepted"\}!', $content, $res))
		{
			return $sendAmount;
		}
		else
		{
			if($json)
			{
				if(isset($json['data']['body']['message']))
					$message = $json['data']['body']['message'];
				elseif(isset($json['data']['message']))
					$message = $json['data']['message'];
				else
					$message = $json['message'];

				if(preg_match('!Сервер занят, повторите запрос через минуту!ui', $content))
				{
					$this->error = 'Сервер занят, повторите запрос через минуту';
					return false;
				}
				else
				{
					if(preg_match('!Пользователь временно заблокирован!ui', $message))
					{
						$this->errorCode = self::ERROR_BAN;
					}
					elseif(
						preg_match('!Кошелек временно заблокирован службой безопасности!ui', $message)
						or
						preg_match('!Проведение платежа запрещено СБ!ui', $message)
						or
						preg_match('!Ограничение на исходящие платежи!ui', $message)
						or
						preg_match('!Персона заблокирована!ui', $message)
					)
					{
						$this->errorCode = self::ERROR_BAN;
					}
					elseif(preg_match('!Платеж не проведен из-за ограничений у получателя!uis', $message))
					{
						$this->errorCode = self::ERROR_SEND_MONEY_TO_LIMIT;
					}
					elseif($this->sender->info['httpCode'][0]==0)
					{
						toLog('ПРОВЕРИТЬ!!! sendMoney error +'.$this->login.' => '.$wallet.': '.$amount.' (httpCode=0)');
					}
					elseif(preg_match('!Пул номеров страны не активен!ui', $message))
					{
						$this->errorCode = self::ERROR_WRONG_WALLET;
					}
					elseif(preg_match('!Сумма платежа меньше минимальной!ui', $message))
					{
						$this->errorCode = self::ERROR_WRONG_AMOUNT;
					}
					elseif(
						preg_match('!Недостаточно средств!ui', $message)
						or
						preg_match('!Сумма платежа больше максимальной!ui', $message)
					)
					{
						$this->errorCode = self::ERROR_NO_MONEY;
					}
					elseif(preg_match('!AwaitingSMSConfirmation!ui', $content))
					{
						$this->errorCode = self::ERROR_SMS_ENABLED;
					}
					elseif(preg_match('!Ежемесячный лимит платежей и переводов для статуса!ui', $message))
					{
						$this->errorCode = self::ERROR_LIMIT_OUT;
					}
					elseif(preg_match('!Техническая ошибка!ui', $message))
					{
						//тут платеж может пройти и не пройти: надо проверить по истории
						sleep(rand(10, 15));

						$payment = $this->getLastPayment(2, 'out');

						//пробуем еще раз получить платеж
						if($payment === false)
							$payment = $this->getLastPayment(2, 'out');

						if(
							preg_match("!$wallet!", $payment['wallet'])
							and $payment['amount'] == $sendAmount
							and $payment['comment'] == $comment
							and ($payment['status'] === self::TRANSACTION_STATUS_SUCCESS or $payment['status'] === self::TRANSACTION_STATUS_WAIT)
							and $payment['timestamp'] > $paymentTimestamp - 120
						)
						{
							return $sendAmount;
						}
					}
					else
					{
						$this->errorCode = false;
					}
				}

				$this->error = 'ошибка платежа +'.$this->login.' => +'.$wallet.': '.Tools::arr2Str($json);

				$msg = $this->error;

				if($this->errorCode)
					$msg .= ' (errorCode = '.$this->errorCode.')';

				return false;

			}
			elseif($this->sender->info['httpCode'][0] == 0)
			{
				//если не получен ответ запишем предполагаемую сумму
				$this->estmatedTransactions[] = array(
					'id'=>$transactionId,
					'amount'=>$sendAmount,
				);
			}

			return false;
		}
	}

}