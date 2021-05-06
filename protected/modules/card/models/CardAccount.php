<?php

/**
 * todo: сделать пересчет лимитов и тд не на лету а по расписанию в 00:00
 * @property int id
 * @property int client_id
 * @property int user_id
 * @property string pan					номер карты
 * @property string expire				гг мм
 * @property string cvv					номер карты
 * @property string name					номер карты
 * @property float balance	 			сумма всех успешных платежей
 * @property string status				active|wait|ban|hidden
 * @property int date_add
 * @property Client client
 * @property User user
 * @property string statusStr
 * @property int transactionCount
 * @property int amount_in 				сумма входящих платежей за лимитный период
 * @property float limitIn 				динамический лимит пересчитыается на ходу
 * @property float amountIn				считается динамически
 * @property float limit_in				базовое значение дневного лимита из которого идет пересчет
 * 										текущего лимита/ может быть изменено
 *
 * @property float amountOut				сумма списаний за лимитный период
 * @property float balance_wait				сумма всех wait-платежей
 * @property float amount_in_wait			сумма ожидаемых платежей
 * @property int error_count				ошибки связанные с этим номером
 * @property int date_error				дата ошибки
 *
 */
class CardAccount extends Model
{
	const SCENARIO_ADD = 'add';

	const STATUS_ACTIVE = 'active';		//активен
	const STATUS_WAIT = 'wait';			//не активен
	const STATUS_BAN = 'ban';			//забанен
	const STATUS_HIDDEN = 'hidden';		//забанен
	const STATUS_DAY_LIMIT = 'dayLimit';		//дневной лимит

	const LIMIT_IN_VALUE = 60000;
	const MIN_BALANCE = 5400;			//минимальный баланс который можно вывести с сим
	const MAX_BALANCE = 30000;

	//минимальный интервал выдачи кошелька (чтобы один и тот же кошелек не выдавался слишком часто на оплату)
	const PICK_INTERVAL = 30; //было 300


	private $_amountInCache = null;
	private $_amountOutCache = null;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{card_account}}';
	}

	public function rules()
	{
		return [
//			['pan', 'match', 'pattern'=>'!^(\d{16})$!', 'allowEmpty'=>false],
//			['pan', 'unique', 'className'=>__CLASS__, 'attributeName'=>'pan', 'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD],
//			['expire', 'match', 'pattern'=>'!^\d\d \d\d$!', 'allowEmpty'=>true],
//			['expire', 'expireValidator', 'on'=>self::SCENARIO_ADD, 'allowEmpty'=>true],
//			['cvv', 'match', 'pattern'=>'!^\d\d\d$!', 'allowEmpty'=>true,],
			['client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id', 'allowEmpty'=>false],
			//['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id'],
			//баланс будет пересчитываться внутри
			//['balance', 'numerical', 'min'=>0, 'max'=>999999],
			//['balance', 'default', 'value'=>0, 'setOnEmpty'=>true, 'on'=>self::SCENARIO_ADD],
			['status', 'in', 'range'=>array_keys(self::getStatusArr())],
			['error_count', 'numerical'],
			['date_error', 'numerical'],

			['pan, expire, cvv', 'safe'],
		];
	}

	public function expireValidator()
	{
		preg_match('!^(\d\d) (\d\d)$!', $this->expire, $match);

		$year = $match[1];
		$month = $match[2];

		$timestamp = strtotime('01.'.$month.'.20'.$year);

		if($timestamp < time())
			$this->addError('expire', 'срок действия карты истек: '.self::$lastError);

		return false;
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();

			if(!isset($this->status))
				$this->status = self::STATUS_ACTIVE;

			if(!isset($this->limit_in))
				$this->limit_in = self::LIMIT_IN_VALUE;

			$this->pan = strip_tags($this->pan);
			$this->expire = strip_tags($this->expire);
			$this->cvv = strip_tags($this->cvv);
		}



		return parent::beforeSave();
	}

	/**
	 * @return Client
	 */
	public function getClient()
	{
		return Client::getModel($this->client_id);
	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		return User::getUser($this->user_id);
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel($params)
	{
		return self::model()->findByAttributes($params);
	}

	/**
	 * @param int $clientId
	 * @param int $userId
	 * @param array $filter
	 * @return self[]
	 */
	public static function getModels($clientId=0, $userId=0, $filter = [])
	{
		$clientId *= 1;
		$userId *= 1;

		$cond =  [];

		if($clientId)
			$cond[] = "`client_id` = '$clientId'";

		if($userId)
			$cond[] = "`user_id` = '$userId'";

		if($filter['phone'] and preg_match('!(\d+)!', $filter['phone'], $res))
			$cond[] = "`pan` LIKE '%{$res[1]}%'";
		else
		{
			if($filter['status'])
			{
				//проверим статусы на валидность
				$error = '';
				foreach($filter['status'] as $val)
				{
					if(!self::getStatusArr()[$val])
						return [];
				}

				if(!$error)
					$cond[] .= "`status` IN ('".implode("','", $filter['status'])."')";
			}

			if($filter['client_id'])
				$cond[] .= "`client_id`='".intval($filter['client_id'])."'";

		}

		$condStr = ($cond) ? implode(" AND ", $cond) : '';

		$models = self::model()->findAll([
			'condition' => $condStr,
			'order' => "`date_add` DESC",
		]);

		return $models;
	}

	/**
	 * @param string $cardStr
	 * @param int $clientId
	 * @return int
	 */
	public static function addMany($cardStr, $clientId)
	{
		$addCount = 0;

		if(!$clientId or !$client = Client::getModel($clientId) or !$client->is_active )
		{
			self::$lastError = 'неверный клиент';
			return $addCount;
		}

		if(!preg_match_all('!(\d{16})!', $cardStr, $match))
		{
			self::$lastError = 'карт для добавления не найдено';
			return $addCount;
		}

		foreach($match[1] as $key=>$pan)
		{
			if(self::getModel(['pan'=>$pan]))
			{
				self::$lastError = 'карта уже добавлена '.$match[0][$key];
				continue;
			}

			$model = new self;
			$model->scenario = self::SCENARIO_ADD;
			$model->pan = $pan;
			$model->expire = $match[3][$key].' '.$match[2][$key]; //mm yy
			$model->cvv = $match[4][$key];
			$model->client_id  = $clientId;

			if($model->save())
				$addCount++;
			else
			{
				self::$lastError = 'карта не добавлена '.$match[0][$key];
				return $addCount;
			}
		}

		return $addCount;
	}

	/**
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param string $type (successIn|out|)
	 * @return  CardTransaction[]
	 */
	public function getTransactions($timestampStart, $timestampEnd, $type = '')
	{
		$timestampStart *= 1;
		$timestampEnd *= 1;

		if($type == 'successIn')
			$cond = " AND `amount`>0 AND `status`='".CardTransaction::STATUS_SUCCESS."'";
		elseif($type == 'out')
			$cond = " AND `amount`<0";
		else
			$cond = '';

		return CardTransaction::model()->findAll([
			'condition' => "`account_id`='{$this->id}' AND `date_add` >= $timestampStart AND `date_add` < $timestampEnd $cond",
			'order' => "`date_add` DESC",
		]);
	}

	/**
	 * только входящие
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return  array|CActiveRecord[]|mixed|null
	 */
	public function getTransactionsIn($timestampStart, $timestampEnd)
	{
		$timestampStart *= 1;
		$timestampEnd *= 1;

		return CardTransaction::model()->findAll([
			'condition' => "`account_id`='{$this->id}' AND `date_add` >= $timestampStart AND `date_add` < $timestampEnd and `amount`>0",
			'order' => "`date_add` DESC",
		]);
	}

	/**
	 * @param array $params ['amount'=>0, 'date_add'=>0, 'status'=>'success', 'currency'=>'RUB']
	 * @return CardTransaction|bool
	 */
	public function addTransaction(array $params)
	{
		$model = new CardTransaction;

		$params['amount'] = (float)$params['amount'];

		if(!$params['amount'])
		{
			self::$msg = 'не указана сумма';
			return false;
		}

		$model->scenario = ($params['amount'] > 0)
			? CardTransaction::SCENARIO_ADD
			: CardTransaction::SCENARIO_WITHDRAW;

		$model->attributes = $params;
		$model->account_id = $this->id;
		$model->client_id = $this->client_id;
		$model->user_id = $this->user_id;

		if($model->save())
		{
			$this->updateInfo();
			return $model;
		}
		else
		{
			self::$msg = 'платеж не сохранен';
			return false;
		}
	}

	public function deleteTransaction($id)
	{
		if(
			$model = CardTransaction::getModel(['account_id'=>$this->id, 'id'=>$id])
			and $model->amount < 0	//удалять только списания(только они делаются вручную)
		)
			return $model->delete();
		else
		{
			self::$lastError = 'транзакция не найдена';
			return false;
		}
	}

	/**
	 * @return array
	 */
	public static function getStatusArr()
	{
		return [
			self::STATUS_ACTIVE => 'активен',
			self::STATUS_WAIT => 'НЕ активен',
			self::STATUS_BAN => 'забанен',
			self::STATUS_HIDDEN => 'скрыт',
			self::STATUS_DAY_LIMIT => 'дневной лимит',
		];
	}

	public function getStatusStr()
	{
		if($this->status === self::STATUS_ACTIVE)
			$class = 'success';
		elseif($this->status === self::STATUS_WAIT)
			$class = 'wait';
		else
			$class = 'error';

		return '<span class="'.$class.'">'.self::getStatusArr()[$this->status].'</span>';
	}

	/**
	 * обновляет баланс, сумму поступивших платежей ... на кошельке
	 */
	public function updateInfo()
	{
		//баланс по успешным платежам
		$model = CardTransaction::model()->find([
			'select' => "SUM(`amount`) AS 'transAmount'",
			'condition' => "`account_id`='{$this->id}' AND `status`='".CardTransaction::STATUS_SUCCESS."'",
		]);

		$this->balance = $model->transAmount;

		//баланс ожидаемый
		$model = CardTransaction::model()->find([
			'select' => "SUM(`amount`) AS 'transAmount'",
			'condition' => "`account_id`='{$this->id}'
				AND `status`='".CardTransaction::STATUS_WAIT."'",
		]);

		$this->balance_wait = $model->transAmount;


		//сумма послутпивших платежей
		$dateStart = Tools::startOfDay();
		$model = CardTransaction::model()->find([
			'select' => "SUM(`amount`) AS 'transAmount'",
			'condition' => "`account_id`='{$this->id}'
				AND `status`='".CardTransaction::STATUS_SUCCESS."'
				AND `amount` > 0 AND `date_add`>=$dateStart",
		]);

		$this->amount_in = $model->transAmount;

		//сумма ожидаемых платежей
		$dateStart = Tools::startOfDay();
		$model = CardTransaction::model()->find([
			'select' => "SUM(`amount`) AS 'transAmount'",
			'condition' => "`account_id`='{$this->id}'
				AND `status`='".CardTransaction::STATUS_WAIT."'
				AND `amount` > 0 AND `date_add`>=$dateStart",
		]);

		$this->amount_in_wait = $model->transAmount;

		if($this->save())
		{
			return true;
		}
		else
			return false;
	}

	/**
	 * @param string $type (in|out|successIn|)
	 * @return int
	 */
	public function getTransactionCount($type='')
	{
		$timestampStart = Tools::startOfDay();

		if($type == 'in')
			$typeCond = " AND `amount` > 0";
		elseif($type == 'out')
			$typeCond = " AND `amount` < 0";
		elseif($type == 'successIn')
			$typeCond = " AND `amount` > 0 AND `status`='".CardTransaction::STATUS_SUCCESS."'";
		else
			$typeCond = '';

		return CardTransaction::model()->count("`account_id`='{$this->id}' AND `date_add`>=$timestampStart $typeCond");
	}


	public function setStatus($status)
	{
		$this->status = $status;
		return $this->save();
	}

	/**
	 * выдает кошелек для оплаты учитывая лимиты, максимальный и минимальный(это для вывода) баланс
	 * @param float $amount
	 * @param int $userId
	 * @return self|false
	 */
	public static function getWallet($amount, $userId)
	{
		$minAmount = CardTransaction::AMOUNT_MIN;
		$maxAmount = CardTransaction::AMOUNT_MAX;
		$maxBalance = self::MAX_BALANCE - $amount;

		$amount *= 1;

		if($amount < $minAmount or $amount > $maxAmount)
		{
			self::$lastError = "сумма должна быть от $minAmount до $maxAmount";
			return false;
		}

		if(!$userId or !$user = User::getUser($userId) or !$user->client_id)
		{
			self::$lastError = "неверно указан пользователь";
			return false;
		}

		$datePickMin = time() - self::PICK_INTERVAL;

		/**
		 * @var self[] $accounts
		 */

		//если есть с ненулевым балансом но меньше self::MIN_BALANCE то добиваем до минималки
//		$accounts = self::model()->findAll([
//			'condition' => "
//				`client_id`='{$user->client_id}'
//				AND `status`='".self::STATUS_ACTIVE."'
//				AND `limit_in` - `amount_in` > $maxAmount
//				AND `balance` + `balance_wait` <= $maxBalance
//				AND `balance` > 0
//				AND `balance` < ".self::MIN_BALANCE."
//			",
//			'order' => "`balance` DESC",
//		]);
//
//		if($accounts)
//			return $accounts[array_rand($accounts)];
//		else
//		{
			//иначе берем все остальные где лимит и баланс не превышают нормы
			$accounts = self::model()->findAll([
				'condition' => "
				`client_id`='{$user->client_id}'
				AND `status`='".self::STATUS_ACTIVE."'
				AND `limit_in` - `amount_in` > $maxAmount
				AND `balance` + `balance_wait` <= $maxBalance
				AND `date_pick` < $datePickMin
			",
				'order' => "`balance_wait` ASC",
			]);
//		}

		if(!$accounts)
		{
			self::$lastError = "недостаточно кошельков";
			self::log('НЕДОСТАТОЧНО КОШЕЛЬКОВ для суммы '.$amount.' ('.$user->name.')');
			return false;
		}

		$slice = array_slice($accounts, 0, 19, true);
		$account = $slice[array_rand($slice)];
		$account->date_pick = time();
		$account->save();

		return $account;
	}

	/**
	 * @param int $id
	 * @param int $userId
	 * @param float $amount
	 * @return bool
	 */
	public static function confirmPayment($id, $userId, $amount)
	{
		$transaction = CardTransaction::getModel(['id'=>$id, 'user_id'=>$userId]);

		if(!$transaction)
		{
			self::$msg = 'платеж не найден';
			return false;
		}

		if($transaction->amount != $amount)
		{
			self::$msg = 'неверная сумма платежа';
			return false;
		}

		if($transaction->status == CardTransaction::STATUS_SUCCESS)
			return true;

		if($transaction->status != CardTransaction::STATUS_WAIT)
		{
			self::$msg = 'неверный статус платежа';
			return false;
		}

		$transaction->status = CardTransaction::STATUS_SUCCESS;

		if($transaction->save())
		{
			toLogRuntime('Card: платеж подтвержден id='.$transaction->id.' '.$amount.' руб');
			return true;
		}
		else
			return false;
	}

	public function getLimitIn()
	{
		return $this->limit_in - $this->amountIn;
	}

	/**
	 * информация по кошелькам
	 * @param int $clientId
	 * @param int $userId
	 * @return array
	 */
	public static function getStats($clientId = 0, $userId = 0)
	{
		$result = [
			'countActive' => 0,
			'countBan' => 0,
			'balanceActive' => 0,
			'balanceBan' => 0,
		];

		$models = self::getModels($clientId, $userId);

		foreach($models as $model)
		{
			if($model->status === self::STATUS_ACTIVE)
			{
				$result['countActive']++;
				$result['balanceActive'] += $model->balance;
			}
			elseif($model->status === self::STATUS_BAN)
			{
				$result['countBan']++;
				$result['balanceBan'] += $model->balance;
			}
		}

		return $result;
	}

	/**
	 * сумма успешных платежей на сим
	 * @return float|null
	 */
	public function getAmountIn()
	{
		if($this->_amountInCache === null)
		{
			//сумма послутпивших платежей
			$dateStart = Tools::startOfDay();
			$model = CardTransaction::model()->find([
				'select' => "SUM(`amount`) AS 'transAmount'",
				'condition' => "`account_id`='{$this->id}' AND `status`='".CardTransaction::STATUS_SUCCESS."' AND `amount` > 0 AND `date_add`>=$dateStart",
			]);

			$this->_amountInCache = $model->transAmount;
		}

		return $this->_amountInCache;
	}

	public function getAmountOut()
	{
		if($this->_amountOutCache === null)
		{
			//сумма послутпивших платежей
			$dateStart = Tools::startOfDay();
			$model = CardTransaction::model()->find([
				'select' => "SUM(`amount`) AS 'transAmount'",
				'condition' => "`account_id`='{$this->id}' AND `status`='".CardTransaction::STATUS_SUCCESS."' AND `amount` < 0 AND `date_add`>=$dateStart",
			]);

			$this->_amountOutCache = -$model->transAmount;
		}

		return $this->_amountOutCache;
	}

	/**
	 * находим разницу между старым и новым лимитом и прибавляем к текущему начальному лимиту
	 * @param $amount
	 * @return bool
	 */
	public function setLimitIn($amount)
	{
		$amount = preg_replace('!\s!', '', $amount);

		if(!preg_match('![\d\.]+!', $amount, $res))
		{
			self::$msg = 'число указано неверно';
			return false;
		}

		$amount = abs($amount*1);

//		var_dump($amount);
//		var_dump($this->limitIn);
//		var_dump($this->limit_in);
//		die();

		$this->limit_in =  ($amount - $this->limitIn) + $this->limit_in;


		if($this->limit_in <= 0)
		{
			self::$msg = 'лимит указан неверно';
			return false;
		}

		return $this->save();
	}

	private static function log($msg)
	{
		Tools::log('CardPayTest: '.$msg, null, null, 'test');
	}

	/**
	 * @param int $clientId
	 * @return int
	 */
	public static function getCountByClientId($clientId)
	{
		return self::model()->count("`client_id`='".intval($clientId)."'");
	}

}