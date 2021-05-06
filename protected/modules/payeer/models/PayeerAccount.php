<?php

/**
 * Class PayeerAccount
 * @property int id
 * @property string login
 * @property string pass
 * @property string browser
 * @property string proxy
 * @property int user_id
 * @property string email_pass
 * @property int is_blocked
 * @property int date_check
 * @property int date_add
 * @property User user
 * @property float balance_ru
 * @property string secret_word
 * @property string master_key
 * @property int api_id
 * @property string api_secret_key
 * @property string sms_service_id
 * @property string sms_phone
 * @property int sms_phone_expire
 * @property int sms_last_time_message
 */
class PayeerAccount extends Model
{

	const SCENARIO_ADD = 'add';
	const SCENARIO_UPDATE = 'update';

	const MIN_AMOUNT  = 1;
	const MAX_AMOUNT  = 15000;

	private $_bot;
	private $_apiBot;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{payeer_account}}';
	}

	public function rules()
	{
		return [
			['login', 'unique', 'className'=>__CLASS__, 'attributeName'=>'login', 'message'=>'login уже был добавлен',
				'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD],
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD],
			['user_id', 'unique', 'className'=>__CLASS__, 'attributeName'=>'user_id', 'message'=>'user уже был добавлен',
				'on'=>self::SCENARIO_ADD],
			['login, pass, browser', 'length', 'min'=>1, 'max'=>200, 'allowEmpty'=>false],
			['login, pass, browser', 'length', 'min'=>1, 'max'=>200, 'allowEmpty'=>false],
			['email', 'default', 'value'=> ''],
			['email_pass', 'default', 'value'=> ''],
			['user_id', 'default', 'value'=> ''],
			['proxy', 'default', 'value'=> ''],
			['secret_word, master_key, api_id, api_secret_key', 'default', 'value'=> ''],
			['is_blocked', 'default', 'value'=>''],
			['date_check, date_add, sms_service_id, sms_phone, sms_phone_expire, sms_last_time_message', 'safe'],
			['balance_ru', 'numerical', 'allowEmpty'=>true],
		];
	}

	/**
	 * @param int $userId
	 * @return self
	 */
	public static function getModelByUserId($userId)
	{
		return self::model()->find("`user_id`=$userId");
	}

	/**
	 * @return int
	 * получаем кол-во свободных акков, на которые можно заменить старые
	 */
	public static function getCountFreeAccounts()
	{
		return count(self::model()->findAllByAttributes(['user_id'=>0]));
	}

	/**
	 * @return mixed
	 * получаем модель свободного аккаунта векс
	 */
	public static function getFreeAccount()
	{
		return self::model()->findByAttributes(['user_id'=>0]);
	}

	public function getSmsPhoneExpireStr()
	{
		return $this->sms_phone_expire ? date('d.m.y H:i', $this->sms_phone_expire) : '';
	}

	/**
	 * создаем реквизиты для оплаты
	 * @param $amount
	 *
	 * @return array|bool
	 */
	public function getPayUrlParams($amount)
	{
		//TODO: убрать после тестов
		if($this->user_id !== '309')//чисто для man11
			return false;

		if($amount < self::MIN_AMOUNT or $amount > self::MAX_AMOUNT)
		{
			self::$lastError = 'неверная сумма (должна быть от '.self::MIN_AMOUNT.' до '.self::MAX_AMOUNT.')';
			return false;
		}
		

		self::$someData['amountForPayeer'] = $amount;

		$bot = $this->getBot();

		$smsRegApi = new SmsRegApi($this->proxy);

		if($this->sms_phone and $this->sms_phone_expire > time())
			$smsParams['phone'] = $this->sms_phone;
		else
		{
			$smsParams = $smsRegApi->getPersonalNumber('ru', '3hours');
			if(!$smsParams['phone'])
			{
				toLogError('Номер для смс не получен login = '.$this->login);
				return false;
			}
			else
			{
				toLog('Взят новый номер '.arr2str($smsParams).' login = '.$this->login);
				$this->sms_phone = $smsParams['phone'];
				$this->sms_phone_expire = $smsParams['expire'];
				$this->save();
			}
		}

		$firstPayParamsArr = $bot->getPayParams($amount, $smsParams['phone']);

		if(!$firstPayParamsArr)
		{
			toLogError('Ошибка запроса получения платежных параметров шаг 1, '.$bot->error.' login = '.$this->login);
			return false;
		}

		$countTryGetSms = 5;
		$invoice = '';
		for($i = 0; $i < $countTryGetSms; $i++)
		{
			sleep(10);
			$smsHistory = $smsRegApi->getSmsHistory($smsParams['phone']);

			if(!$smsHistory)
			{
				toLogError('Ошибка получения истории смс login = '.$this->login);
				return false;
			}

			array_reverse($smsHistory);

			foreach($smsHistory as $item)
			{
				$matchStr =  '!(scheta|invoice) '.$firstPayParamsArr['mOrderid'].' (na|on) (summu|amount) ([\d.]+) RUB (vash sms-kod:|your SMS-code:) (\d+)!iu';
				if(preg_match($matchStr, $item['text'], $matches))
				{
					$amountSms = $matches[4];
					$smsCode = $matches[6];
					break;
				}
			}

			if($smsCode)
			{
				self::$lastError = self::$lastError.' Не найдена смс с кодом, номер = '.$smsParams['phone'].' account = '.$this->login;
				toLogError(self::$lastError);
				break;
			}

		}

		if(!$smsCode)
		{
			toLogError('Смс с кодом не найдена, invoice = '.$firstPayParamsArr['mOrderid'].
				' phone = '.$smsParams['phone'].' login = '.$this->login);
			return false;
		}

		if($firstPayParamsArr['amount'] !== $amountSms)
		{
			toLogError('Проверьте процент, возможно изменился, суммы не совпадают (ожидаемая =
				'.$firstPayParamsArr['amount'].', фактическая = '.$amountSms.') login = '.$this->login);
			return false;
		}

		$payParams = $bot->getPayParams($amount, $smsParams['phone'], $smsCode, $firstPayParamsArr);

		if(!$payParams)
		{
			self::$lastError = 'ошибка '.$bot->error.' account = '.$this->login;
			toLogError(self::$lastError);
			return false;
		}

		return $payParams;
	}

	private function getBot()
	{
		if($this->is_blocked)
			return false;

		if(!$this->_bot)
		{
			$this->_bot =  new PayeerBot($this->login, $this->pass, $this->proxy, $this->browser);
		}
		return $this->_bot;
	}

	private function getApiBot()
	{
		if($this->is_blocked)
			return false;

		if(!$this->_apiBot)
		{
			$this->_apiBot =  new PayeerApi($this->login, $this->api_id, $this->api_secret_key, $this->proxy);
		}
		return $this->_apiBot;
	}

	/**
	 * получение всей истории через парсинг страницы
	 * @return array
	 */
	public function getHistory()
	{
		$bot = $this->getBot();

		$history = $bot->getHistory();

		if(is_array($history))
		{
			if(!$this->updateHistory($history))
				return false;

			return array_reverse($history);
		}
		else
			return $history;
	}

	/**
	 * получение всей истории через парсинг страницы
	 * @return array
	 */
	public function getApiHistory($count, $from = '', $to = '')
	{
		$bot = $this->getApiBot();

		$history = $bot->getHistory($count, $from = '', $to = '');

		return $history;
	}

	/**
	 * @return array|bool
	 */
	public function getBalance()
	{
		$bot = $this->getBot();

		return $bot->getBalance();
	}

	/**
	 * @return array|bool
	 */
	public function getApiBalance()
	{
		$bot = $this->getApiBot();

		$balanceArr = $bot->getBalance();

		if($balanceArr['balance']['RUB']['BUDGET'])
			return $balanceArr['balance']['RUB']['BUDGET'];
		else
			return false;
	}

	/**
	 * @param array $accounts
	 * @return int
	 */
	public static function saveMany($accounts)
	{
		$done = 0;

		foreach ($accounts as $userId=>$params)
		{
			if($params['textarea'])
				$params = self::parseTextParams($params['textarea']);


			if(!$params['login'])
				continue;

			if(!$model = PayeerAccount::model()->find("`user_id`='$userId'"))
			{
				$model = new self;
				$model->scenario = self::SCENARIO_ADD;
				$model->user_id = $userId;
			}

			$model->attributes = $params;
			$model->sms_phone_expire = strtotime($params['sms_phone_expire']);

			if($model->save())
				$done++;
			else
				break;
		}

		return $done;
	}

	/**
	 * парсит строку с параметрами для акка
	 * @param $stringParams
	 * @return array
	 */
	private static function parseTextParams($stringParams)
	{
		die($stringParams);
	}

	/**
	 * @return self
	 */
	public static function getModelById($id)
	{
		return self::model()->findByPk($id);
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	public function getUser()
	{
		return User::getUser($this->user_id);
	}

	public function updateAccount()
	{
		$balance = $this->getBalance();

		if($balance !== false)
		{
			$this->balance_ru = $balance['ru'];
			$this->date_check = time();
			return $this->save();
		}
		else
		{
			self::$lastError = 'ошибка обновления аккаунта';
			return false;
		}
	}

	/**
	 * @param int $clientId
	 * @return int 				количество обновленных аккаунтов
	 */
	public static function updateClientAccounts($clientId)
	{
		$accounts = self::getClientAccounts($clientId);

		$result = 0;

		foreach($accounts as $account)
		{
			if($account->updateAccount())
			{
				$result++;
			}
			else
				self::$lastError .= '<br>ошибка обновления '.$account->login;
		}

		return $result;
	}

	/**
	 * @param int $clientId
	 * @return self[]
	 */
	public static function getClientAccounts($clientId)
	{
		$client = Client::getModel($clientId);

		if(!$client)
			return false;

		$result = [];

		foreach ($client->users as $user)
		{
			if($account = self::getModelByUserId($user->id))
				$result[] = $account;
		}

		return $result;
	}

	/**
	 *проверяем авторизован аккаунт или нет
	 */
	public function getAuthStatus()
	{
		$bot = $this->getBot();
		return $bot->isAuth;
	}

	/**
	 * @return Proxy|null
	 */
	public function getProxyObj()
	{
		if(!$this->proxy)
			return false;

		if(preg_match('!(([^:]+?):([^@]+?)@|)(.+?):(\d{2,7})!', $this->proxy, $res))
		{
			if($model = Proxy::model()->find("`ip`='$res[4]' and `port`='$res[5]'"))
				return $model;
			else
				return false;
		}
		else
			return false;
	}

	public static function getAmountForPayeer($amount)
	{
		//старый процент
		//return floorAmount($amount/1.08602152, 2);
		return round((1560-10.76)/1.08602152 + 0.0018, 2);
	}

	/**
	 * @param array $params
	 * @return array|bool
	 * проверяем оплачена ли наша заявка
	 */
	public function getTransactionStatus($params = [])
	{
		$bot = $this->getBot();
		return $bot->getTransactionStatus($params);
	}

	/**
	 * @return array|bool
	 */
	public function createApiParams()
	{
		$bot = $this->getBot();
		$params = $bot->createApiParams();

		if($params)
		{
			$this->api_id = $params['id'];
			$this->api_secret_key = $params['secretKey'];
			return $this->save();
		}
		else
		{
			self::$lastError = ' ошибка создания параметров Api ';
			return false;
		}
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
		$bot = $this->getBot();
		return $bot->sendQiwiMoneyRu($params);
	}

}