<?php

/**
 * Рублевые векс-коды
 *
 * Class Coupon
 * @property int id
 * @property int user_id
 * @property int client_id
 * @property string code
 * @property float amount
 * @property int date_add
 * @property int date_activate
 * @property string error
 *
 * @property Client client
 * @property User user
 * @property string dateAddStr
 * @property string dateActivateStr
 * @property string amountStr
 * @property string statusStr
 * @property string currency
 * @property float amount_currency
 */
class Coupon extends Model
{
	const SCENARIO_ADD = 'add';
	const SCENARIO_ACTIVATE = 'activate';
	const VIEW_LIMIT = 1000;				//лимит на отображение(последние .. кодов)

	const THREAD_NAME = 'wexBot';
	const ACTIVATE_TIME = 40;

	const CURRENCY_RUB = 'RUB';
	const CURRENCY_USD = 'USD';

	private $_clientCache = null;
	private $_userCache = null;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{coupon}}';
	}

	public function rules()
	{
		return [
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false],
			['code', 'match', 'pattern'=>cfg('wexRegExp'), 'allowEmpty'=>false, 'message'=>'неверный формат'],
			['code', 'unique', 'className'=>__CLASS__, 'attributeName'=>'code', 'message'=>'код уже был добавлен',
				'on'=>self::SCENARIO_ADD],
			//['amount', 'numerical', 'min'=>1, 'max'=>1000000000, 'allowEmpty'=>true,
			//	'on'=>self::SCENARIO_ACTIVATE],
			['error', 'length', 'min'=>0, 'max'=>200],
			['currency', 'in', 'range' => array_keys(self::currencyArr()), 'allowEmpty'=>false],
			['amount_currency', 'numerical', 'min'=>0, 'max'=>1000000000, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ACTIVATE],
		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();
			$this->client_id = $this->getUser()->client_id;
		}
		elseif($this->scenario == self::SCENARIO_ACTIVATE)
		{
			//конвертация
			$this->date_activate = time();

			if($this->currency === self::CURRENCY_USD)
			{
				$this->amount = $this->amount_currency * config('usd_rur_sell_wex');
				$this->amount = floorAmount($this->amount, 2);
			}
			elseif($this->currency === self::CURRENCY_RUB)
			{
				$this->amount = $this->amount_currency;
			}
			else
			{
				$this->addError('currrency', 'Неверная валюта кода');
			}
		}

		return parent::beforeSave();
	}

	protected function afterSave()
	{

		parent::afterSave();
	}


	/**
	 * @return Client|null
	 */
	public function getClient()
	{
		if(!$this->_clientCache)
			$this->_clientCache = Client::modelByPk($this->client_id);

		return $this->_clientCache;
	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		if(!$this->_userCache)
			$this->_userCache = User::getUser($this->user_id);

		return $this->_userCache;
	}

	/**
	 * @return string
	 */
	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	/**
	 * @return string
	 */
	public function getDateActivateStr()
	{
		return ($this->date_activate) ? date('d.m.Y H:i', $this->date_activate) : '';
	}

	/**
	 * @return string
	 */
	public function getAmountStr()
	{
		$result = ($this->amount > 0) ? formatAmount($this->amount, 0).' RUB' : '';

		if($this->currency !== self::CURRENCY_RUB)
			$result .= "<br><small>({$this->amount_currency} {$this->currency})</small>";

		return $result;
	}

	/**
	 * @return string
	 */
	public function getStatusStr()
	{
		if($this->date_activate)
			return "<span class='success'>активирован {$this->dateActivateStr}</span>";
		elseif($this->error)
			return "<span class='error'>ошибка: {$this->error}</span>";
		else
			return "не активирован";

	}


	/**
	 * @param int $userId
	 * @param string $coupons 	контент с купонами
	 * @return int 				количество добавленных
	 */
	public static function addMany($coupons, $userId)
	{
		$maxAtOnce = 10;

		$user = User::getUser($userId);

		$addCount = 0;

		if(!preg_match_all(cfg('wexRegExpMany'), $coupons, $res))
		{
			self::$lastError = 'WEX-кодов не обнаружено';
			return $addCount;
		}

		if(count($res[1]) > $maxAtOnce)
		{
			self::$lastError = 'не больше '.$maxAtOnce.' за раз';
			return $addCount;
		}

		self::$msg = '';
		$errorMsg = '';

		foreach($res[1] as $coupon)
		{
			$model = new self;
			$model->scenario = self::SCENARIO_ADD;
			$model->user_id = $user->id;
			$model->code = $coupon;
			$model->currency = self::getCurrency($model->code);

			if($model->save() and $model->activate())
			{
				$addCount++;
				self::$msg .= "<br> {$model->code} активирован, сумма: {$model->amountStr}";

				toLogRuntime("WEX-CODE {$model->code} активирован, сумма: {$model->amountStr}");
			}
			else
				$errorMsg .= "<br> {$model->code} ошибка: ".self::$lastError;
		}

		self::$lastError = $errorMsg;

		return $addCount;
	}

	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $clientId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param bool $onlySuccess		только активированные
	 * @return self[]
	 */
	public static function getModels($timestampStart=0, $timestampEnd=0, $userId = 0, $clientId = 0, $onlySuccess=false)
	{
		$intervalMax = 3600 * 24 * 365;

		$userId *=1;
		$clientId *=1;

		$timestampStart *=1;
		$timestampEnd *=1;

		if($timestampEnd > time())
			$timestampEnd = time();

		if($timestampEnd - $timestampStart > $intervalMax)
		{
			self::$lastError = 'максимальный интервал статистики: 30 дней';
			return [];
		}

		//либо по юзеру либо по клиенту
		$userCond = ($userId) ? " AND `user_id`='$userId'" :
			(($clientId) ? " AND `client_id`='$clientId'" : '');

		$successCond = '';

		if($onlySuccess)
			$successCond = " AND `date_activate`>0";

		return self::model()->findAll([
			'condition'=>"
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				$userCond
				$successCond
			",
			'order'=>"`id` DESC",
		]);
	}

	/**
	 * @param self[] $models
	 * @return array
	 */
	public static function getStats($models)
	{
		$result = [
			'count'=>0,
			'amount'=>0,
		];

		foreach($models as $model)
		{
			$result['count']++;

			if(!$model->error and $model->date_activate > 0 and $model->amount > 0)
				$result['amount'] += $model->amount;
		}

		return $result;
	}

	public function activate()
	{
		if($this->date_activate)
			return true;

		//дождаться пока бот закончит
		$maxTime = self::ACTIVATE_TIME;
		set_time_limit($maxTime + 10);
		$start = time();

		$threadName = self::THREAD_NAME;

		do
		{
			$threaderResult = Tools::threader($threadName);

			if(!$threaderResult)
				sleep(1);
		}
		while(!$threaderResult and time() - $start < $maxTime);


		if($threaderResult)
		{
			$config = cfg('wexApi');
			$bot = WexBot::getInstance($config['key'], $config['secret']);

			//$amount =  100;
			$amount =  $bot->redeemCode($this->code);

			if($amount)
			{
				toLogRuntime('активирован код '.$this->code.' на сумму '.$amount);

				$this->amount_currency = $amount;
				$this->currency = self::getCurrency($this->code);
				$this->scenario = self::SCENARIO_ACTIVATE;

				Tools::threaderClear($threadName);
				return $this->save();
			}
			else
			{
				if($bot->errorMsg == $bot::ERROR_INVALID_COUPON)
				{
					$error = 'неверный код, возможно уже был активирован';
					$this->error = 'неверный код';
					$this->save();
				}
				else
					$error = 'не активирован';

				self::$lastError = $error;
				toLog('WEX: '.self::$lastError.': '.$bot->errorMsg);
				Tools::threaderClear($threadName);
				return false;
			}
		}
		else
		{
			self::$lastError = 'превышено время активации кода';
			return false;
		}
	}

	/**
	 * фоновая активация кодов которые не погасились сразу
	 *
	 */
	public static function startActivate()
	{
		//активировать те который былидобавлены более 60 сек назад
		$minDate = time() - 60;

		$models = self::model()->findAll([
			'condition'=>"`date_activate`=0 AND `error`='' AND `date_add` < $minDate",
		]);

		/**
		 * @var self[] $models
		 */

		if(!$models)
		{
			echo "\n нечего активировать";
			return false;
		}


		$maxTime = self::ACTIVATE_TIME;
		set_time_limit($maxTime + 10);
		$start = time();

		$threadName = self::THREAD_NAME;

		do
		{
			$threaderResult = Tools::threader($threadName);

			if(!$threaderResult)
				sleep(1);
		}
		while(!$threaderResult and time() - $start < $maxTime);

		if($threaderResult)
		{
			$config = cfg('wexApi');
			$bot = WexBot::getInstance($config['key'], $config['secret']);

			foreach($models as $model)
			{
				$amount =  $bot->redeemCode($model->code);

				if($amount)
				{
					toLogRuntime('активирован код '.$model->code.' на сумму '.$amount);

					$model->amount = $amount;
					$model->scenario = self::SCENARIO_ACTIVATE;

					if(!$model->save())
						toLogError('ошибка сохранения кода '.$model->code);

				}
				else
				{
					if($bot->errorMsg == $bot::ERROR_INVALID_COUPON)
					{
						$model->error = 'неверный код';
						$model->save();
					}
					else
						toLogError('ошибка активации кода '.$model->code.' '.$bot->errorMsg);
				}
			}

			Tools::threaderClear($threadName);

		}
		else
		{
			self::$lastError = 'превышено время активации кода';
			return false;
		}


	}

	public static function currencyArr()
	{
		return array(
			self::CURRENCY_RUB => 'RUB',
			self::CURRENCY_USD => 'USD',
		);
	}

	public static function getCurrency($code)
	{
		if(preg_match(cfg('wexRegExp'), $code, $res))
		{
			$currency = $res[1];

			//зачем руб называть рур непонятно
			$currency = str_replace('RUR', 'RUB', $currency);

			if(in_array($currency, array_keys(self::currencyArr()))!==false)
				return $currency;
			else
			{
				self::$lastError = 'неверная валюта';
				return false;
			}
		}
		else
		{
			self::$lastError = 'неверный код';
			return false;
		}
	}


}