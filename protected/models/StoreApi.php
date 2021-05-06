<?php

/**
 * Class StoreApi
 * todo: зашифровать ключ в бд
 * todo: продумать удаление кошелька в использованные (возможно если у него нет резервов и он перешел за черту)
 * todo: одноподочность через threader (1 поток на магазин)
 * todo: store_id убрать, сделать вместо него id(случайное число, длина 5 знаков)
 * todo: не логировать запросы на курс
 * todo: передавать какой то маркер что запрос кешированый
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
 * @property float balance сумма всех неоплаченых платежей
 * @property int date_wallet_change дата смены withdraw_wallet
 * @property string dateWalletChangeStr
 * @property string url_result
 * @property string url_return
 * @property string client_id
 * @property string localId
 *
 */

class StoreApi extends Model
{
	const SCENARIO_ADD = 'add';
	const CRYPT_METHOD = MCRYPT_RIJNDAEL_256;
	const CRYPT_MODE = MCRYPT_MODE_ECB;

	const ERROR_PARAMS = 1;
	const ERROR_REQUEST_NUMBER = 2;
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

	const KEY_NAME = 'key';

	const OLD_REQUEST_INTERVAL = 86400;	//сутки

	const STORE_COUNT_MAX = 300;	//на клиента


	public $requestParams = array();
	public $requestModel;

	public static $obj;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'user_id' => 'Пользователь',
			'withdraw_wallet' => 'BTC адрес',
			'date_wallet_change' => 'Дата смены кошелька',
		);
	}

	public function tableName()
	{
		return '{{store_api}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();
	}

	public function rules()
	{
		return [
			['user_id', 'required'],
			['user_id', 'exist', 'className' => 'User', 'attributeName' => 'id'],
			['user_id', 'unique', 'className' => __CLASS__, 'attributeName' => 'user_id', 'on' => self::SCENARIO_ADD],
			['withdraw_wallet', 'match', 'pattern'=>cfg('btcAddressRegExp'), 'allowEmpty'=>true],
			['withdraw_limit', 'numerical', 'min'=>StoreApiWithdraw::AMOUNT_RUB_MIN, 'max'=>999999,  'allowEmpty'=>true],
			['url_result', 'url', 'message'=>'неверный urlResult'],
			['url_result, url_return', 'length', 'max'=>100, 'message'=>'слишком длинный urlResult'],
			['id', 'idValidator'],
			['id', 'unique', 'className'=>__CLASS__, 'attributeName'=>'id', 'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD],
			['id', 'countValidator', 'on' => self::SCENARIO_ADD],
		];
	}

	public function beforeSave()
	{
		if(!$this->withdraw_limit)
			$this->withdraw_limit = StoreApiWithdraw::AMOUNT_RUB_MIN;

		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->client_id = $this->user->client_id;
		}

		//зафиксировать дату смены кошелька
		if($this->withdraw_wallet)
		{
			$oldModel = self::getModel(['id'=>$this->id]);

			if($oldModel->withdraw_wallet != $this->withdraw_wallet)
				$this->date_wallet_change = time();
		}

		return parent::beforeSave();
	}



	private static function checkParams($storeId, array $requestParams)
	{
		if($storeId <= 0 or $storeId > 99999999999)
		{
			self::$lastErrorCode = self::ERROR_STORE_ID;
			self::$lastError = 'StoreId должен быть от 1 до 99999999999';
			return false;
		}

		if($requestParams['requestNumber']*1 <= 0)
		{
			self::$lastErrorCode = self::ERROR_REQUEST_NUMBER;
			self::$lastError = 'номер запроса должен быть больше нуля';
			return false;
		}

		if(!self::$lastError)
		{
			return true;
		}
		else
			return false;

	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		return User::getUser($this->user_id);
	}


	/**
	 * сохранить запрос
	 *
	 * @param int $storeId
	 * @param string $answerStr
	 * @return bool
	 */
	public function logRequest($answer)
	{
		//только если запрос удалось расшифровать

		if(!StoreApiRequest::model()->findByAttributes(array('store_id'=>$this->store_id, 'id'=>$this->requestParams['requestNumber'])))
		{
			$apiRequest = new StoreApiRequest;
			$apiRequest->scenario = StoreApiRequest::SCENARIO_ADD;
			$apiRequest->id = $this->requestParams['requestNumber'];
			$apiRequest->params = Tools::arr2Str($this->requestParams);
			$apiRequest->answer = Tools::arr2Str($answer);
			return $apiRequest->save();
		}
		else
			return true;

	}

	/**
	 * @param string $type (public|private)
	 * @return string
	 */
	private static function getKey($type)
	{
		$filePath = dirname(__FILE__).'/StoreApi/'.self::KEY_NAME.'_'.$type;

		return file_get_contents($filePath);
	}

	/**
	 * @param string $order
	 * @return StoreApi[]
	 */
	public static function getActiveStoreArr($order = "")
	{
		$result = array();

		$models = self::model()->findAll([
			'order'=>$order,
		]);

		foreach($models as $model)
		{
			if($model->isEnabled)
				$result[] = $model;
		}

		return $models;
	}

	/**
	 * @return StoreApi[]
	 */
	public static function getStoreArr()
	{
		return self::model()->findAll(array(
			'order'=>"`id` ASC"
		));
	}

	/**
	 * вывести $amount биткоина
	 * @param string $address
	 * @param float $amount
	 * @return int|false	withdrawId
	 * todo: сделать повтор неудачной покупки(рынок быстро уходит)
	 */
	public static function withdrawBtcBitfinex($address, $amount)
	{
		if(!is_numeric($amount) or !preg_match(cfg('btcAddressRegExp'), $address))
		{
			toLogStoreApi('неверная сумма или адрес: amount='.$amount.', address='.$address);
			return false;
		}

		$config = cfg('storeApi');
		$bot = Bitfinex::getInstance($config['key'], $config['secret']);

		$balance = $bot->getBalance();

		if($balance === false)
		{
			toLogStoreApi('нет ответа от BTCE-API(проверка баланса), вывод не будет произведен');
			return false;
		}

		$balanceBtc = $balance['btc'];

		//уведомить если осталось мало баланса
		$balanceUsd = $balance['usd'];

		if($balanceUsd !== false and $balanceUsd < config('storeApiNoticeMinBalance'))
			self::notice('предупреждение: на балансе осталось '.$balanceUsd.' usd');

		//echo '<br>'.$balanceBtc;

		if($balanceBtc === false)
			return false;

		//если не хватает на вывод то купить btc_usd
		$isEnoughBtc = $bot->isEnoughMoney('withdraw', $amount, $balanceBtc);


		if($isEnoughBtc === false)
		{
			//+0.0001 пусть покупает чуть больше
			//$buyAmount = $amount - $balanceBtc + WexBot::COMMISSION_WITHDRAW_BTC + 0.0001;
			$buyAmount = $amount - $balanceBtc + Bitfinex::COMMISSION_WITHDRAW_BTC + 0.0001;

			//если покупка меньше минимального объема
			if($buyAmount < Bitfinex::BTC_TRADE_MIN)
				$buyAmount = Bitfinex::BTC_TRADE_MIN;

			$balanceUsd = $bot->getBalance('usd');

			//если не хватает на покупку то выдать в лог и завершить
			$isEnoughUsd = $bot->isEnoughMoney('trade', $buyAmount, $balanceUsd);

			if($isEnoughUsd === null)
			{
				//ошибка в функции
				toLogStoreApi('ошибка при получении $isEnoughUsd: '.__METHOD__);
				return false;
			}
			elseif(!$isEnoughUsd)
			{
				$msg = 'ВНИМАНИЕ!!! не хватает на покупку '.$buyAmount.' битов (в наличии: '.$balanceUsd.' баков';
				toLogStoreApi($msg);
				//уведомление
				self::notice($msg);
				return false;
			}


			if($buyResult = $bot->buyInstant($buyAmount))
			{
				$balanceBtc = $bot->getBalance('btc');

				if($balanceBtc === false)
					return false;

				//если не хватает на вывод после покупки
				if(!$bot->isEnoughMoney('withdraw', $amount, $balanceBtc))
				{
					toLogStoreApi('не хватает на вывод '.$amount.' btc после  покупки '.$buyResult.' btc');
					return false;
				}
			}
			else
				return false;
		}
		elseif($isEnoughBtc === null)
		{
			toLogStoreApi('ошибка получения '.$isEnoughBtc);
			return false;
		}

		if($withdrawId = $bot->withdraw($address, $amount))
			return $withdrawId;
		else
		{
			toLogStoreApi($bot->errorMsg);
			self::$lastError = $bot->errorMsg;
			return false;
		}

	}

	/**
	 * вывести $amount биткоина
	 * @param string $address
	 * @param float $amount
	 * @return int|false	withdrawId
	 * todo: сделать повтор неудачной покупки(рынок быстро уходит)
	 */
	public static function withdrawBtcBlockio($address, $amount)
	{
		if(!is_numeric($amount) or !preg_match(cfg('btcAddressRegExp'), $address))
		{
			toLogStoreApi('неверная сумма или адрес: amount='.$amount.', address='.$address);
			return false;
		}

		$config = cfg('storeApi');
		$bot = Blockio::getInstance($config['key'], $config['secret']);
		$bot->withdrawPriority = config('blockio_withdraw_priority');

		$balanceBtc = $bot->getBalance();

		if($balanceBtc === false)
		{
			toLogStoreApi('нет ответа от Blockio(проверка баланса), вывод не будет произведен');
			return false;
		}

		if($balanceBtc !== false and $balanceBtc < config('storeApiNoticeMinBalanceBtc'))
			self::notice('предупреждение: на балансе осталось '.$balanceBtc.' btc');

		//echo '<br>'.$balanceBtc;

		if($withdrawId = $bot->withdraw($address, $amount))
			return $withdrawId;
		else
		{
			$msg = $bot->errorMsg;

			if($bot->errorCode == Blockio::ERROR_NO_MONEY)
			{
				$msg = 'ВНИМАНИЕ!!! недостаточно BTC для вывода: '.$msg;
				self::notice($msg);
			}

			toLogStoreApi($msg);
			self::$lastError = $msg;
			return false;
		}

	}

	public function getIsEnabled()
	{
		return $this->user->active;
	}

	public function getStatusStr()
	{
		return ($this->user->active) ? '<span class="success">активен</span>' : '<span class="success">отключен</span>';
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
	 * @param int $storeId
	 * @return self
	 */
	public static function getModelByStoreId($id)
	{
		return self::model()->findByAttributes(array('id'=>$id));
	}

	public static function switchStatus($id)
	{
		if($model = self::getModel(['id'=>$id]))
		{
			if($model->user->active)
			{
				if(User::disable($model->user->id))
				{
					toLogStoreApi('магазин ID='.$model->id.' Отключен');
					return true;
				}
			}
			else
			{
				if(User::enable($model->user->id))
				{
					toLogStoreApi('магазин ID='.$model->id.' Задействован');
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
			$btcRate = config('storeApiBtcRate');

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

	/**
	 * устанавливает минимальную сумму на вывод для всех магазинов сразу
	 * @param array $params [1=>12344, 68=>10000, ...]
	 * @return int	количество измененных записей
	 */
	public static function setWithdrawLimit(array $params)
	{
		$count = 0;

		foreach($params as $id=>$withdrawLimit)
		{
			if($model = self::getModel(['id'=>$id]))
			{
				//не меняется значение - пропускаем
				if($model->withdraw_limit == $withdrawLimit)
					continue;

				$model->withdraw_limit = $withdrawLimit;

				if($model->save())
					$count++;
				else
				{
					self::$lastError = 'магазин '.$model->id.': '.$model::$lastError;
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

	public static function notice($text)
	{
		//test отключил пока хмпп уведомления
		return true;

		$cfg = cfg('notice_test');
		$interval = 3600;	//интервал надоедания

		if($wheelUser = User::getWheelUser() and $wheelUser->jabber)
			$to = $wheelUser->jabber;
		else
			$to = config('storeApiNoticeJabber');


		$noticeTimestamp = config('storeApiNoticeTimestamp') * 1;

		//$noticeTimestamp = 0;
		//$to = 'my@system.im';
		//$to = 'artem71@xmpp.jp';
		//$to = 'petrovich1215@xmpp.jp';
		//$to = 'jenddos@jabber.de';



		if(time() - $noticeTimestamp < $interval)
			return true;

		$conn = new XMPPHP($cfg['botServer'], 5222, $cfg['botLogin'], $cfg['botPass'], 'xmpphp', $cfg['botServer']);


		//todo: разобраться в новом боте, нужна нормлаьная обработка ошибок
		//чтобы не забанили как спам-рассылку, делаем сообщения уникальными
		$dateStr = date('H:i'). ': ';

		$conn->connect();
		$conn->processUntil('session_start');
		$conn->presence();
		$conn->message($to, $dateStr.$text);
		$conn->disconnect();

		toLogRuntime('jabber msg: '.Tools::shortText($dateStr.$text, 50).' => '.$to);

		config('storeApiNoticeTimestamp', time());
		return true;

	}

	/**
	 * сумма всех неоплаченных платежей минус ручные выводы
	 * @return float
	 */
	public function getBalance()
	{
		$result = 0;

		if($transactions = NewYandexPay::getNotWithdrawTransactions($this->user_id))
		{
			foreach($transactions as $transaction)
				$result += $transaction->amount;
		}

		return $result;
	}

	/**
	 * @param int $userId
	 * @return self
	 */
	public static function getModelByUserId($userId)
	{
		return self::model()->findByAttributes(['user_id'=>$userId]);
	}

	/**
	 * адрес для пополнения кошелька (последний из списка)
	 * @param $changeAddress
	 * @return string|false
	 */
	public static function getDepositAddress($changeAddress = false)
	{
		$cfg = cfg('storeApi');

		$bot = Blockio::getInstance($cfg['key'], $cfg['secret']);

		if($changeCount = $bot->getChangeAddressLimit() and $changeCount <= 0)
		{
			self::$lastError = 'достигнуто предельное кол-во адресов на аккаунте';
			return false;
		}

		if($changeAddress)
		{
			if($address = $bot->getNewAddress())
			{
				config('blockio_change_address_left', $bot->getChangeAddressLimit());
				return $address;
			}
			else
			{
				self::$lastError = $bot->errorMsg;
				return false;
			}
		}

		if($address = $bot->getDepositAddress())
			return $address;
		else
		{
			self::$lastError = $bot->errorMsg;
			return false;
		}
	}

	/**
	 * комиссия системы биткоин на данный момент
	 * @param string $address
	 * @param float $amount
	 * @return false|float
	 */
	public static function getNetworkFee($address, $amount)
	{
		$cfg = cfg('storeApi');
		$bot = Blockio::getInstance($cfg['key'], $cfg['secret']);
		$bot->withdrawPriority = config('blockio_withdraw_priority');
		return $bot->getWithdrawCommission($address, $amount);
	}

	public function getDateWalletChangeStr()
	{
		return ($this->date_wallet_change) ? date('d.m.Y H:i', $this->date_wallet_change) : '';
	}

	public static function hash(array $params)
	{
		$cfg = cfg('storeApi');

		unset($params['hash']);

		$result = '';

		foreach($params as $val)
			$result .= $val;

		$result .= $cfg['apiSecret'];

		return md5($result);
	}

	private static function createUniqueId()
	{
		do
		{
			$id = Tools::generateCode('123456789', 6);
		}
		while(self::model()->findByPk($id));

		return $id;
	}

	/*
	 * проверка на существование в других панелях
	 */
	public function idValidator()
	{
		list($userId, $storeId) = explode('_', $this->id);

		$userId = @intval($userId);
		$storeId = @intval($this->id);

		if(is_int($userId) and is_int($storeId) and $this->user_id == $userId)
			return true;
		else
			$this->addError('id', 'неверный storeId');
	}

	public function countValidator()
	{
		$clientId = $this->user->client_id;
		$count = self::model()->count("`client_id`='{$clientId}'");

		if($count + 1 > self::STORE_COUNT_MAX)
			$this->addError('id', 'превышено максимальное кол-во магазинов для вашего аккаунта');
	}

	/**
	 * пользовательский id магазина 123_этот
	 * @return string
	 */
	public function getLocalId()
	{
		$explode = explode('_', $this->id);
		return $explode[1];
	}

}