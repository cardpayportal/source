<?php

/**
 * Class StoreApi
 * todo: сохранение только успешных запросов, ошибочные писать в лог
 * todo: зашифровать ключ в бд
 * todo: продумать удаление кошелька в использованные (возможно если у него нет резервов и он перешел за черту)
 * todo: одноподочность через threader (1 поток на магазин)
 * todo: store_id убрать, сделать вместо него id(случайное число, длина 5 знаков)
 * todo: не логировать запросы на курс
 * todo: передавать какой то маркер что запрос кешированый
 * todo: проверка ecomm-кошельков платежом
 * todo: ускорить проверку ecomm-кошельков
 *
 * @property int id
 * @property int user_id

 * @property string withdraw_wallet
 * @property User user
 * @property StoreApiRequest requestModel
 * @property bool isEnabled
 * @property string statusStr
 * @property StoreApi $model
 * @property int withdraw_limit
 * @property int withdrawLimitVal	если неверный withdraw_limit то возвращает StoreApiWithdraw::AMOUNT_CURRENCY_MIN
 *
 */

class EcommApi extends Model
{
	const SCENARIO_ADD = 'add';
	const CRYPT_METHOD = MCRYPT_RIJNDAEL_256;
	const CRYPT_MODE = MCRYPT_MODE_ECB;

	const ERROR_PARAMS = 1;
	const ERROR_REQUEST_NUMBER = 2;
	const ERROR_REQUEST_METHOD = 3;
	const ERROR_AMOUNT = 5;
	const ERROR_PAYMENTS = 6;
	const ERROR_BTC_ADDRESS = 7;
	const ERROR_DEBUG = 8;
	const ERROR_ACCESS = 9;
	const ERROR_DECRYPT = 10;
	const ERROR_NO_WALLETS = 11;
	//const ЧАСТЫЕ_ЗАПРОСЫ = 12; // todo: реализовать паузу между запросами
	const ERROR_DATE = 13;
	const ERROR_STORE_ID = 14;
	const ERROR_CURRENCY = 15;
	const ERROR_WALLET_LIMIT = 16;
	const ERROR_WALLETS = 17;
	const ERROR_WALLET_ADD = 18;
	const ERROR_WITHDRAW_AMOUNT = 19;
	const ERROR_WITHDRAW_BALANCE = 20;	//недостаточно баланса для вывода
	const ERROR_SETTINGS = 21;	//неверные параметры

	const OLD_REQUEST_INTERVAL = 86400;		//удаление старых запросов из бд
	const STORE_COUNT_MAX = 300;			//максимальное число магазинов

	const GET_WALLETS_MIN_AMOUNT = 2;	//минимальная сумма для self::getWallets()
	const GET_WALLETS_MAX_AMOUNT = 500000;	//максимальная сумма для self::getWallets()

	const WALLET_STATUS_CHECK = 'check';	//статус непроверенного кошелька

	//todo: заменить на битфинекс
	const WITHDRAW_CODE_AMOUNT_MIN = WexBot::CODE_CREATE_MIN;
	const WITHDRAW_CODE_AMOUNT_MAX = 1000;

	const WITHDRAW_BTC_AMOUNT_MIN = WexBot::BTC_WITHDRAW_MIN;
	const WITHDRAW_BTC_AMOUNT_MAX = 0.5;


	public $requestParams = array();
	public $requestModel;

	public static $lastError = '';
	public static $lastErrorCode = 0;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID магазина',
			'user_id' => 'Пользователь',
			'withdraw_wallet' => 'BTC адрес',
		);
	}

	public function tableName()
	{
		return '{{ecomm_api}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('id', 'unique', 'className' => __CLASS__, 'attributeName' => 'id', 'on' => self::SCENARIO_ADD),
			array('user_id', 'required'),
			array('user_id', 'exist', 'className' => 'User', 'attributeName' => 'id'),
			array('user_id', 'unique', 'className' => __CLASS__, 'attributeName' => 'user_id', 'on' => self::SCENARIO_ADD),
			array('withdraw_wallet', 'match', 'pattern'=>cfg('btcAddressRegExp'), 'allowEmpty'=>true),
			array('withdraw_limit', 'numerical', 'min'=>StoreApiWithdraw::AMOUNT_RUB_MIN, 'max'=>999999,  'allowEmpty'=>true),
		);
	}

	public function beforeSave()
	{
		if(!$this->withdraw_limit)
			$this->withdraw_limit = StoreApiWithdraw::AMOUNT_RUB_MIN;

		return parent::beforeSave();
	}


	/**
	 * менеджер к которому привязан магазин
	 * @return User
	 */
	public function getUser()
	{
		return User::getUser($this->user_id);
	}


	/**
	 * @return StoreApi[]

	public static function getActiveStoreArr()
	{
		$result = array();

		$models = self::model()->findAll();

		foreach($models as $model)
		{
			if($model->isEnabled)
				$result[] = $model;
		}

		return $models;
	}
	*/

	/**
	 * @return StoreApi[]

	public static function getStoreArr()
	{
		return self::model()->findAll(array(
			'order'=>"`store_id` ASC"
		));
	}

	 */

	/**
	 * вывести $amount биткоина
	 * @param string $address
	 * @param float $amount
	 * @return bool если ответ не был получен от сервера то return true
	 * todo: сделать повтор неудачной покупки(ктото забирает ордер первее)

	public static function withdrawBtc($address, $amount)
	{
		$config = cfg('storeApi');
		$bot = WexBot::getInstance($config['key'], $config['secret']);

		$balance = $bot->getBalance();

		$balanceBtc = $balance['btc'];

		//уведомить если осталось мало баланса
		$balanceUsd = $balance['usd'];

		if($balanceUsd !== false and $balanceUsd < config('storeApiNoticeMinBalance'))
			self::notice('предупреждение: на балансе осталось '.$balanceUsd.' usd');

		//echo '<br>'.$balanceBtc;

		if($balanceBtc === false)
			return false;

		//если не хватает на вывод то купить btc_usd
		$isEnoughBtc = $bot->isEnoughMoney('withdrawBtc', $amount, $balanceBtc);

		if($isEnoughBtc === false)
		{
			//+0.0001 пусть покупает чуть больше
			$buyAmount = $amount - $balanceBtc + WexBot::COMMISSION_WITHDRAW_BTC + 0.0001;

			//если покупка меньше минимального объема
			if($buyAmount < WexBot::BTC_TRADE_MIN)
				$buyAmount = WexBot::BTC_TRADE_MIN;

			$balanceUsd = $bot->getBalance('usd');

			//если не хватает на покупку то выдать в лог и завершить
			if(!$bot->isEnoughMoney('buyBtc', $buyAmount, $balanceUsd))
			{
				toLogEcommApi('не хватает на покупку '.$buyAmount.' btc');
				//уведомление
				self::notice('ВНИМАНИЕ!!! не хватает на покупку '.$buyAmount.' битов (в наличии: '.$balanceUsd.' баков');
				return false;
			}

			if($buyResult = $bot->buyBtc($buyAmount))
			{
				$balanceBtc = $bot->getBalance('btc');

				//если не хватает на вывод после покупки
				if(!$bot->isEnoughMoney('withdrawBtc', $amount, $balanceBtc))
				{
					toLogEcommApi('не хватает на вывод '.$amount.' btc после  покупки '.$buyResult.' btc');
					return false;
				}
			}
			else
				return false;
		}
		elseif($isEnoughBtc === null)
			return false;


		if($bot->withdrawBtc($amount, $address))
			return true;
		else
			return false;
	}
	 */

	/*
	 * создает юзера и магазин
	 * @param int $storeId
	 * @return self|bool
	public static function createStore($storeId)
	{
		$config = cfg('storeApi');

		//максимальное кол-во магазинов
		if(self::model()->count() >= self::STORE_COUNT_MAX)
		{
			toLogEcommApi('ошибка создания магазина '.$storeId.': максимальное кол-во магазинов '
				.self::STORE_COUNT_MAX
				.' достигнуто'
			);

			return false;
		}

		$params = array(
			'login'=>'storeApiMan'.$storeId,
			'role'=>User::ROLE_MANAGER,
			'client_id'=>$config['clientId'],
		);

		if($userModel = User::register($params))
		{
			$storeModel = new self;
			$storeModel->scenario = self::SCENARIO_ADD;
			$storeModel->user_id = $userModel->id;
			$storeModel->store_id = $storeId;

			if($storeModel->save())
			{
				toLogEcommApi('создан магазин store_id='.$storeModel->store_id);
				return $storeModel;
			}
			else
			{
				$userModel->delete();
				toLogEcommApi('удален пользователь (ошибка создания магазина) id='.$userModel->id);
			}
		}
		else
			toLogEcommApi('ошибка регистрации пользователя '.Tools::arr2Str($params));

		return false;
	}
	 */


	public function getIsEnabled()
	{
		return $this->user->active;
	}

	/*
	public function getStatusStr()
	{
		return ($this->user->active) ? '<span class="success">активен</span>' : '<span class="success">отключен</span>';
	}

	public static function notice($text)
	{
		$cfg = cfg('notice');
		$interval = 3600;	//интервал надоедания

		if($wheelUser = User::getWheelUser() and $wheelUser->jabber)
			$to = $wheelUser->jabber;
		else
			$to = config('storeApiNoticeJabber');

		//$to = 'my@system.im';

		$noticeTimestamp = config('storeApiNoticeTimestamp') * 1;

		if(time() - $noticeTimestamp < $interval)
			return true;

		$bot = JabberBot::getInstance($cfg['botLogin'], $cfg['botPass']);

		if(!$bot->error)
		{
			//чтобы не забанили как спам-рассылку, делаем сообщения уникальными
			$dateStr = date('H:i'). ': ';

			$bot->sendMessage($to, $dateStr.$text);

			config('storeApiNoticeTimestamp', time());
		}
		else
		{
			toLog('JabberBot: '.$bot->error);
		}
	}

	public static function switchStatus($id)
	{
		if($model = self::getModel($id))
		{
			if($model->user->active)
			{
				if(User::disable($model->user->id))
				{
					toLogEcommApi('магазин store_id='.$model->store_id.' Отключен');
					return true;
				}
			}
			else
			{
				if(User::enable($model->user->id))
				{
					toLogEcommApi('магазин store_id='.$model->store_id.' Задействован');
					return true;
				}
			}
		}

		return false;
	}

	public function getRate($currency)
	{
		$allowCurrencyArr = array('btc');

		if(in_array($currency, $allowCurrencyArr)!==false)
		{
			$usdRate = $this->user->client->commissionRule->rateValue;
			$btcRate = config('btc_usd_rate_btce');

			if($usdRate and $btcRate)
				return ceilAmount($usdRate * $btcRate, 0);
			else
			{
				self::$lastErrorCode = self::ERROR_CURRENCY;
				return false;
			}
		}
		else
		{
			self::$lastErrorCode = self::ERROR_CURRENCY;
			return false;
		}
	}

	public function getWithdrawLimitVal()
	{
		if($this->withdraw_limit < StoreApiWithdraw::AMOUNT_RUB_MIN)
			return StoreApiWithdraw::AMOUNT_RUB_MIN;
		else
			return $this->withdraw_limit;
	}
*/
	/*
	 * устанавливает минимальную сумму на вывод для всех магазинов сразу
	 * @param array $params [1=>12344, 68=>10000, ...]
	 * @return int	количество измененных записей

	public static function setWithdrawLimit(array $params)
	{
		$count = 0;

		foreach($params as $id=>$withdrawLimit)
		{
			if($model = self::getModel($id))
			{
				//не меняется значение - пропускаем
				if($model->withdraw_limit == $withdrawLimit)
					continue;

				$model->withdraw_limit = $withdrawLimit;

				if($model->save())
					$count++;
				else
				{
					self::$lastError = 'магазин '.$model->store_id.': '.$model::$lastError;
					break;
				}
			}
			else
			{
				self::$lastError = 'не найден магазин id='.$id;
				break;
			}
		}

		return $count;
	}
*/

	/**
	 * попытка расшифровать
	 * проверка requestNumber и method
	 * @param string $encryptedString зашифрованая строка
	 * @param string $keyFile адрес файла с ключом
	 * @return array|false
	 */
	public static function decrypt($encryptedString, $keyFile)
	{
		$binData = base64_decode($encryptedString);

		$key = openssl_get_privatekey(file_get_contents($keyFile));

		$size = 512;
		$i = 0;
		$count = ceil(strlen($binData) / $size );

		$decodeString = '';

		do
		{
			$chunk = substr($binData, $size * $i, $size);

			openssl_private_decrypt($chunk, $decrypted, $key);

			$decodeString .= $decrypted;

			$i++;

		} while ( $i < $count );

		if($params = json_decode($decodeString, true) and is_array($params))
		{
			return $params;
		}
		else
		{
			self::$lastErrorCode = self::ERROR_DECRYPT;
			self::setErrorMsg();
			return false;
		}
	}


	/**
	 * шифрует текст в rsa (разбивает на блоки)
	 * @param string $text (base64)
	 * @param string $keyFile
	 * @return string (bse64)
	 */
	public static function encrypt($text, $keyFile)
	{
		$result = '';

		$publicKey = openssl_get_publickey(file_get_contents($keyFile));

		$size = 256;
		$i = 0;
		$count = ceil(strlen($text) / $size);

		do
		{
			$chunk = substr($text, $size * $i, $size);

			openssl_public_encrypt($chunk, $encrypted, $publicKey);

			$result .= $encrypted;

			$i++;

		} while ($i < $count);

		return base64_encode($result);
	}

	/**
	 * устанавливает self::$lastError
	 */
	private static function setErrorMsg(/*$errorCode*/)
	{
		$arr = array(
			self::ERROR_DECRYPT	=> 'ошибка расшифровки',
			self::ERROR_REQUEST_NUMBER	=> 'ошибка в requestNumber',
			self::ERROR_REQUEST_METHOD	=> 'ошибка в method',
			self::ERROR_STORE_ID => 'ошибка в storeId',

			self::ERROR_PAYMENTS => 'ошибка в payments',
			self::ERROR_BTC_ADDRESS => 'ошибка в btcAddress',
			self::ERROR_DEBUG => 'включен отладочный режим',	//todo: проверить работет ли
			self::ERROR_ACCESS => 'доступ заблокирован',
			self::ERROR_NO_WALLETS => 'недостаточно кошельков',
			self::ERROR_DATE => 'ошибка в дате',
			self::ERROR_CURRENCY => 'ошибка в валюте',
			self::ERROR_WALLET_LIMIT => 'выдача кошшельков ограничена',
			self::ERROR_WALLETS => 'ошибка в wallets',
		);

		if(isset($arr[self::$lastErrorCode]))
			self::$lastError = $arr[self::$lastErrorCode];
		elseif(self::$lastErrorCode > 0)
			self::$lastError = 'неизвестная ошибка';
		else
			self::$lastError = '';
	}

	/**
	 * базовая проверка данных каждого запроса
	 * @param array $params
	 * @return bool
	 */
	public static function checkRequest(array $params)
	{
		$allowMethods = self::getAllowedMethods();

		if(
			!is_int($params['storeId'])
			or $params['storeId'] <= 0
			or !$store = self::getModel($params['storeId'])
		)
		{
			//storeId
			self::$lastErrorCode = self::ERROR_STORE_ID;
		}
		elseif(
			!is_int($params['requestNumber'])
			or $params['requestNumber'] <=0
			or $params['requestNumber'] > 9999999999
		)
		{
			//requestNumber
			self::$lastErrorCode = self::ERROR_REQUEST_NUMBER;
		}
		elseif(!is_string($params['method']) or !in_array($params['method'], $allowMethods))
		{
			//method
			self::$lastErrorCode = self::ERROR_REQUEST_METHOD;
		}
		elseif(!$store->isEnabled)
		{
			//если магазин отключен
			self::$lastErrorCode = self::ERROR_ACCESS;
		}

		if(!self::$lastErrorCode)
			return true;
		else
		{
			self::setErrorMsg();
			return false;
		}
	}

	/**
	 * @param int $id
	 * @return self;
	 */
	public static function getModel($id)
	{
		return self::model()->findByPk($id);
	}

	/**
	 * найти закешированный запрос и отдать его
	 * @param array $params
	 * @return string|false
	 */
	public static function findRequest(array $params)
	{
		/**
		 * @var EcommApiRequest $model
		 */
		if($model = EcommApiRequest::model()->findByAttributes(array('store_id'=>$params['storeId'], 'request_number'=>$params['requestNumber'])))
			return $model->answer;
		else
			return false;
	}

	/**
	 * @param string $postRaw зашифрованные пост-данные
	 * @param array|false $paramsArr массив расшифрованных параметров(может быть false - тогда сохраняем только $postRaw)
	 * ['storeId'=>, 'requestNumber'=>, 'method'=>]
	 *
	 * @param string $answer ответ от апи
	 * @param int $errorCode - код ответа
	 * @return bool
	 */
	public static function saveRequest($postRaw, $paramsArr, $answer, $errorCode)
	{
		$model = new EcommApiRequest;
		$model->scenario = EcommApiRequest::SCENARIO_ADD;

		if($paramsArr)
		{
			$model->store_id = $paramsArr['storeId'];
			$model->request_number = $paramsArr['requestNumber'];
			$model->method = $paramsArr['method'];
			$model->params = Tools::arr2Str($paramsArr);
		}
		else
			$model->params = $postRaw;

		$model->answer = $answer;
		$model->error_code = $errorCode;

		if($model->save())
			return true;
		else
		{
			toLogEcommApi('ошибка сохранения запроса');
			return false;
		}
	}

	public static function getAllowedMethods()
	{
		return array(
			'getWallets',
			'getPayments',
			'checkPayments',
			'addWallets',
			'getWalletsInfo',
			'getWithdrawals',
			'disableWallets',
			'enableWallets',
		);
	}

	/**
	 * выдача коков
	 * необходимые параметры: $prams['amount']
	 * @param array $params ['amount'=>]
	 * @return array
	 */
	public static function getWallets(array $params)
	{
		$result = array();

		if(
			!is_int($params['amount'])
			or $params['amount'] < self::GET_WALLETS_MIN_AMOUNT
			or $params['amount'] > self::GET_WALLETS_MAX_AMOUNT
		)
		{
			self::$lastErrorCode = self::ERROR_AMOUNT;
			self::$lastError = 'ошибка в amount: должен быть от '.self::GET_WALLETS_MIN_AMOUNT.' до '.self::GET_WALLETS_MAX_AMOUNT;
			return $result;
		}

		$store = self::getModel($params['storeId']);

		if($accounts = $store->user->pickAccountsByAmount($params['amount']))
		{
			foreach($accounts as $arr)
				$result[$arr['account']->login] = $arr['amount'];

			toLogEcommApi('выдано '.count($accounts).' кошельков на сумму: '.$params['amount'].', user: '.$store->user->name);
		}
		else
		{
			$errorCode = User::$lastErrorCode;

			if($errorCode == User::ERROR_NO_WALLETS)
				self::$lastErrorCode = self::ERROR_NO_WALLETS;
			elseif($errorCode == User::ERROR_WALLET_LIMIT)
				self::$lastErrorCode = self::ERROR_WALLET_LIMIT;
			else
				self::$lastErrorCode = self::ERROR_NO_WALLETS;
		}

		return $result;
	}

	/**
	 * todo: возвращать статус платежа если найден и notFound если нет и кошель проверен, '' - если нет и кошель еще не проверен
	 * проверка пришедших платежей
	 * @param array $params ['payments'=>[['walletTo'=>'', 'walletFrom'=>'', 'amount'=>1000, 'comment'=>''], [..], ..], ..]
	 * @return array|false
	 */
	public static function checkPayments($params)
	{
		/*
		$result = array();

		$payments = $params['payments'];

		if(!is_array($payments) and count($payments) > 0)
		{
			self::$lastErrorCode = self::ERROR_PAYMENTS;
			self::setErrorMsg();
			return false;
		}

		$paymentsForSave = array();

		//проверим формат всех платежей, если есть ошибки то retirn false
		foreach($payments as $key=>$payment)
		{
			if(!preg_match(cfg('wallet_reg_exp'), $payment['walletTo']))
			{
				self::$lastErrorCode = StoreApi::ERROR_PAYMENTS;
				self::$lastError = 'неверно указан walletTo';
			}
			elseif(!preg_match(cfg('wallet_reg_exp'), $payment['walletFrom']))
			{
				self::$lastErrorCode = StoreApi::ERROR_PAYMENTS;
				self::$lastError = 'неверно указан walletFrom';
			}
			elseif(!preg_match('!\d+$!', $payment['amount']) or $payment['amount']*1 < 1)
			{
				self::$lastErrorCode = StoreApi::ERROR_PAYMENTS;
				self::$lastError = 'неверно указана сумма';
			}

			if(!preg_match('!\d+$!', $payment['transactionId']))
			{
				self::$lastErrorCode = StoreApi::ERROR_PAYMENTS;
				self::$lastError = 'неверно указан id';
			}

			if(!$payments)
			{
				self::$lastErrorCode = StoreApi::ERROR_PAYMENTS;
				self::$lastError = 'неверный формат payments';
			}

			if(!$accountModel = Account::model()->findByAttributes(array('user_id'=>$this->store->user->id, 'login'=>$payment['walletTo'])))
			{
				self::$lastErrorCode = StoreApi::ERROR_PAYMENTS;
				self::$lastError = 'не найден walletTo';
			}

			if(self::$lastErrorCode)
			{
				self::$lastError .=  ' в платеже '.($key+1);
				$this->resultOut();
			}

			$payment['walletTo'] = '+'.$payment['walletTo'];
			$payment['walletFrom'] = '+'.$payment['walletFrom'];

			$paymentsForSave[$key] = array(
				'account_id'=>$accountModel->id,
				'wallet_from'=>$payment['walletFrom'],
				'amount'=>$payment['amount'],
				'qiwi_id'=>$payment['transactionId'],
				'store_id'=>$this->store->store_id,
			);
		}

		//сохранить платежи
		if($models = StoreApiTransaction::addMany($paymentsForSave))
		{
			//вывести инфу о платежах
			if(!$result = StoreApiTransaction::updateInfo($models))
				toLogStoreApi('ошибка обновления платежей: '.StoreApiTransaction::$lastError);
		}
		else
			toLogStoreApi('ошибка добавления платежей: '.StoreApiTransaction::$lastError);

		if($btcAddress)
		{
			//обновить адрес магазина для вывода

			if(preg_match(cfg('btcAddressRegExp'), $btcAddress))
			{
				if($btcAddress !== $this->store->withdraw_wallet)
				{
					$this->store->withdraw_wallet = $btcAddress;

					if($this->store->save())
					{
						toLogStoreApi('сохранен withdraw_wallet для storeId='.$this->store->store_id.': '.$btcAddress);
					}
					else
						toLogStoreApi('ошибка изменения withdraw_wallet: '.$btcAddress);
				}
			}
			else
			{
				self::$lastErrorCode = StoreApi::ERROR_BTC_ADDRESS;
				self::$lastError = 'неверный btcAddress';
				$this->resultOut();
			}
		}

		$this->resultOut($result);
		*/
	}

	/**
	 * получение вхоящих платежей кошелька
	 * @param array $params
	 * ['wallets'=>['79848374653', ...], 'timestampStart'=>0, 'timestampEnd'=>0]
	 * 	timestampStart по умолчанию time() - 3600*24, timestampEnd по умолчанию time()
	 * @return array
	 * [
	 * 	'79763527364'=>[
	 * 		'payments'=>['id'=>'', 'from'=>'', 'to'=>'', 'amount'=>'', 'comment'=>'', 'status'=>'', 'error'=>'', 'timestamp'=>0],
	 *		'info'=>['timestampCheck'=>0, 'error'=>'']	//инфо о кошельке
	 * 	],
	 * 	....
	 * ]
	 */
	public static function getPayments($params)
	{

		$cfg = array(
			'timestampStart'=>time() - 3600*24,
			'timestampEnd'=>time(),
		);

		$result = array();

		$goodAccounts = self::getGoodAccounts($params['wallets'], $params['storeId']);

		if(!$goodAccounts)
			return $result;

		//ищем платежи
		foreach($goodAccounts as $login=>$account)
		{
			if(isset($params['timestampStart']))
				$timestampStart = $params['timestampStart'] * 1;
			else
				$timestampStart = $cfg['timestampStart'];

			if(isset($params['timestampEnd']))
				$timestampEnd = $params['timestampEnd'] * 1;
			else
				$timestampEnd = $cfg['timestampEnd'];


			$timestampCond = " AND `date_add` >= $timestampStart AND `date_add` < $timestampEnd";

			//входящие платежи на этот кошелек
			$transactions = Transaction::model()->findAll(array(
				'condition' => "
					`account_id`='{$account->id}' AND `type`='" . Transaction::TYPE_IN . "'
					$timestampCond
					",
				'order' => "`date_add` DESC",
			));

			/**
			 * @var Transaction[] $transactions
			 */

			foreach($transactions as $transaction)
			{
				$result[$login]['payments'][] = array(
					'id'=>$transaction->qiwi_id,
					'from'=>trim($transaction->wallet, '+'),
					'to'=>$login,
					'amount'=>$transaction->amount,
					'comment'=>$transaction->comment,
					'status'=>$transaction->status,
					'error'=>$transaction->error,
					'timestamp'=>$transaction->date_add,
				);
			}

			$result[$login]['info'] = array(
				'timestampCheck'=>$account->date_check,
				'error'=>$account->error,
			);
		}

		return $result;
	}

	/**
	 * добавляет кошельки менеджерам в список активных
	 * добавляет ошибку check_wait для полной проверки
	 *
	 * @param array $params ['wallets'=>['login'=>'', 'pass'=>''], ....]
	 * @return array
	 * 	['79763535454', '79763535454']	- успешно добавленные кошельки
	 * 	также возвращает последнюю ошибку при добавлении
	 * 	если кошелек уже был добавлен этому менеджеру, то вносит его в массив успешных
	 */
	public static function addWallets($params)
	{
		//todo: совместить с функцией Account::addMany()

		//массив успешно добавленных кошельков
		$result = array();

		$user = self::getModel($params['storeId'])->user;

		$wallets = $params['wallets'];

		if(!is_array($wallets))
		{
			self::$lastError = 'wallets должен быть массивом';
			self::$lastErrorCode = self::ERROR_WALLETS;
			return array();
		}
		elseif(count($wallets) == 0)
		{
			self::$lastError = 'массив wallets пуст';
			self::$lastErrorCode = self::ERROR_WALLETS;
			return array();
		}

		//правильно отформатированные кошельки
		$goodWallets = array();

		/**
		 * @var Account[] $goodWallets
		 */

		foreach($wallets as $wallet)
		{
			if(preg_match(cfg('wallet_reg_exp2'), $wallet['login'], $resLogin))
			{
				$login = $resLogin[1];

				if(preg_match(cfg('walletPassRegExp'), $wallet['pass'], $resPass))
				{
					$pass = $resPass[1];

					if($account = Account::model()->findByAttributes(array('login'=>'+'.$login)))
					{
						if($account->user_id == $user->id)
							$result[] = $login;
						else
						{
							self::$lastError = 'кошелек принадлежит другому пользователю: '.$login;
							self::$lastErrorCode = self::ERROR_WALLETS;
						}
					}
					else
						$goodWallets[] = array('login'=>$login, 'pass'=>$pass);
				}
				else
				{
					self::$lastError = 'неверный пароль к: '.$login.' ('.$wallet['pass'].')';
					self::$lastErrorCode = self::ERROR_WALLETS;
					return array();
				}
			}
			else
			{
				self::$lastError = 'неверный логин: '.$wallet['login'];
				self::$lastErrorCode = self::ERROR_WALLETS;
				return array();
			}
		}

		foreach($goodWallets as $wallet)
		{
			//todo: не крепить мыла к кошелькам юзеров
			//todo: выводить предупреждения(хз пока где)
			$account = new Account;
			$account->scenario = Account::SCENARIO_ADD;

			$account->login = '+'.$wallet['login'];
			$account->pass = $wallet['pass'];
			$account->type = Account::TYPE_IN;
			$account->client_id = $user->client_id;
			$account->group_id = Account::getGroupIdforAdd($user->client_id, $account->type);
			$account->user_id = $user->id;
			$account->date_pick = time();
			$account->check_priority = Account::PRIORITY_BIG;
			$account->error = Account::ERROR_CHECK;
			$account->is_ecomm = 1;
			$account->status = Account::STATUS_ANONIM;


			$result[] = $wallet['login'];
		}

		return $result;
	}

	/**
	 * получение инфы о своих кошельках (текущих кошельках менеджера (использованных или нет), обычных или e-comm)
	 * @param array $params ['wallets'=>['79536354545', ....]]
	 * @return array|false
	 * 	['79536354545'=>[
	 * 			'status'=>'check|anonim|half|full',
	 * 			'balance'=>0.00,
	 * 			'email'=>false,
	 * 			'limit'=>190000,
	 * 			'timestampCheck'=>0,
	 * 			'timestampAdd'=>0,
	 * 			'error'=>'ban',
	 * 			'warning'=>'не прикреплен email, возможна кража кошелька',
	 * 		], ...
	 * ]
	 */
	public static function getWalletsInfo($params)
	{
		$result = array();

		$goodAccounts = self::getGoodAccounts($params['wallets'], $params['storeId']);

		if(!$goodAccounts)
			return $result;

		foreach($goodAccounts as $login=>$account)
		{
			$result[$login] = self::walletInfo($account);
		}

		return $result;
	}

	/**
	 * отключает кошельки менеджера
	 *
	 * @param array $params ['wallets'=>['7873635463', '76353545744', ...]]
	 * @return array
	 *
	 * todo: если режим Свои кошельки, то запрещать отключать другие и наоборот
	 */
	public static function disableWallets($params)
	{
		$result = array();

		$goodAccounts = self::getGoodAccounts($params['wallets'], $params['storeId']);

		if(!$goodAccounts)
			return $result;

		foreach($goodAccounts as $login=>$account)
		{
			Account::model()->updateByPk($account->id, array('enabled'=>0));
			$result[] = $login;
		}

		return $result;
	}

	/**
	 * включает ecomm-кошельки менеджера
	 *
	 * @param array $params []
	 * @return bool
	 */
	public static function enableWallets($params)
	{
		$result = array();

		$goodAccounts = self::getGoodAccounts($params['wallets'], $params['storeId']);

		if(!$goodAccounts)
			return $result;

		foreach($goodAccounts as $login=>$account)
		{
			Account::model()->updateByPk($account->id, array('enabled'=>1));
			$result[] = $login;
		}

		return $result;
	}

	/**
	 * получение проверенных кошельков (верных по формату и принадлежащих юзеру) из переданного массива
	 * @param array $wallets
	 * @param int $storeId
	 * @return Account[]	['76535352323'=>Account, ...]
	 */
	private static function getGoodAccounts($wallets, $storeId)
	{
		$result = array();

		$user = self::getModel($storeId)->user;

		if(!is_array($wallets))
		{
			self::$lastError = 'wallets должен быть массивом';
			self::$lastErrorCode = self::ERROR_WALLETS;
			return array();
		}
		elseif(count($wallets) == 0)
		{
			self::$lastError = 'массив wallets пуст';
			self::$lastErrorCode = self::ERROR_WALLETS;
			return array();
		}


		//проверить на формат и принадлежность юзеру
		foreach($wallets as $wallet)
		{
			if(preg_match(cfg('wallet_reg_exp2'), $wallet, $res))
			{
				$login = $res[1];

				if($account = Account::model()->findByAttributes(array('login'=>'+'.$login, 'user_id'=>$user->id)))
					$result[$login] = $account;
				else
				{
					self::$lastError = 'кошелек не найден: '.$wallet;	//не найден либо принадлежит другому юзеру
					self::$lastErrorCode = self::ERROR_WALLETS;
					return array();
				}
			}
			else
			{
				self::$lastError = 'неверный формат кошелька: '.$wallet;
				self::$lastErrorCode = self::ERROR_WALLETS;
				return array();
			}
		}

		return $result;
	}

	public static function getActiveWallets($params)
	{
		$result = array();

		$user = self::getModel($params['storeId'])->user;

		$accounts = Account::model()->findAll(array(
			'condition'=>"
				`client_id`={$user->client_id} AND `user_id`={$user->id}
				 AND `date_used`=0 and `error`=''
				 AND `enabled`=1
			",
			'order'=>"`date_pick` DESC",
		));

		/**
		 * @var Account[] $accounts
		 */

		foreach($accounts as $account)
		{
			$login = trim($account->login, '+');
			$result[$login] = self::walletInfo($account);
		}

		return $result;
	}

	/**
	 * форматированная инфа о кошельке
	 * @param Account $account
	 * @return array
	 */
	private static function walletInfo($account)
	{
		$status = '';

		if($account->error === Account::ERROR_CHECK)
			$status = self::WALLET_STATUS_CHECK;	//кошелек еще проверяется
		elseif($account->status === Account::STATUS_ANONIM)
			$status = 'anonim';
		elseif($account->status === Account::STATUS_HALF)
			$status = 'half';
		elseif($account->status === Account::STATUS_FULL)
			$status = 'full';

		$warning = '';

		if(!$account->is_email and $status != self::WALLET_STATUS_CHECK)
			$warning = 'не прикреплен email, возможна кража кошелька';

		//todo: если кошелек уже использован или еще чтото то ставить лимит:0
		return array(
			'status'=>$status,
			'balance'=>$account->balance,
			'email'=>$account->is_email,
			'limit'=>$account->limit_in,
			'timestampCheck'=>$account->date_check,
			'timestampAdd'=>$account->date_add,
			'error'=>$account->error,
			'warning'=>$warning,
			'enabled'=>$account->enabled,
		);
	}

	/**
	 * выдача кода
	 * @param array $params ['amount'=>10.2]
	 * @return string
	 */
	public static function withdrawCode($params)
	{
		$result = '';

		$cfg = cfg('ecommApi');
		$cfg = $cfg['btce'];

		//todo:заменить
		$bot = WexBot::getInstance($cfg['key'], $cfg['secret']);

		$amount = $params['amount']*1;

		if($amount < self::WITHDRAW_CODE_AMOUNT_MIN)
		{
			self::$lastErrorCode = self::ERROR_WITHDRAW_AMOUNT;
			self::$lastError = 'сумма меньше минимальной ('.self::WITHDRAW_CODE_AMOUNT_MIN.')';
			return $result;
		}
		elseif($amount > self::WITHDRAW_CODE_AMOUNT_MAX)
		{
			self::$lastErrorCode = self::ERROR_WITHDRAW_AMOUNT;
			self::$lastError = 'сумма больше максимальной ('.self::WITHDRAW_CODE_AMOUNT_MAX.')';
			return $result;
		}

		if($result = $bot->createCode($amount))
			return $result;
		else
		{
			self::$lastErrorCode = self::ERROR_WITHDRAW_BALANCE;
			self::$lastError = 'недостаточно средств на счету для создания кода на сумму '.$amount.' USD';
			return '';
		}
	}


	/**
	 * постановка на вывод btc
	 * @param array $params ['address'=>'', 'amount'=>]
	 * @return int|0	ид вывода, поставленного на выполнение
	 */
	public static function withdrawBtc($params)
	{
		$result = 0;

		//сделать проверки, вернуть true если все ок
		$address = $params['address'];
		$amount = $params['amount'] * 1;

		if(!preg_match(cfg('btcAddressRegExp'), $address))
		{
			self::$lastErrorCode = self::ERROR_BTC_ADDRESS;
			self::$lastError = 'неверный btc адрес';
			return $result;
		}

		if($amount < self::WITHDRAW_BTC_AMOUNT_MIN)
		{
			self::$lastErrorCode = self::ERROR_AMOUNT;
			self::$lastError = 'сумма меньше минимальной ('.self::WITHDRAW_BTC_AMOUNT_MIN.')';
			return $result;
		}

		if($amount > self::WITHDRAW_BTC_AMOUNT_MAX)
		{
			self::$lastErrorCode = self::ERROR_AMOUNT;
			self::$lastError = 'сумма больше максимальной ('.self::WITHDRAW_BTC_AMOUNT_MAX.')';
			return $result;
		}

		//todo: закрыть муляж
		return time();

	}

	/**
	 * список выводов
	 * @param array $params ['timestampStart'=>0, 'timestampEnd'=>0] по умолчанию: последние 24 часа
	 * @return array	[['wallet'=>'', 'amount'=>1, 'currency'=>'btc', 'amountRub'=>98000, 'status'=>'wait|success|error', 'timestampAdd'=>0], ...]
	 */
	public static function getWithdrawals($params)
	{
		$result = array();

		return $result;
	}

	/**
	 * @param $params ['']
	 * @return int 0|1
	 */
	public static function settings($params)
	{
		$result = 1;



		return $result;
	}

}