<?php

/**
 * Qiwi  платежи через payeer
 *
 * Class QiwiPay
 * @property int id
 * @property int api_id
 * @property int user_id
 * @property int client_id
 * @property float amount
 * @property string status
 * @property int date_add
 * @property int date_pay
 * @property string error
 * @property int request_api_id
 *
 * @property string datePayStr
 * @property string statusStr
 * @property string amountStr
 * @property string dateAddStr
 * @property User user
 * @property PayeerAccount payeerAccount
 * @property string mark
 * @property string urlShort
 *
 * @property float amount_prime
 * @property string comment
 * @property string wallet
 * @property string payeer_account_id
 * @property int m_shopid
 * @property int m_historyid
 * @property int m_historytm
 * @property int m_curorderid
 */

class QiwiPay extends Model
{
	const SCENARIO_ADD = 'add';

	const STATUS_WAIT = 'wait';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';
	const STATUS_RESERVED = 'reserved';

	const UPDATE_RATE_INTERVAL = 1800;	//если часто обновлять то можно пропустить платеж изза того что курс уже ушел

	const MARK_CHECKED  = 'checked';
	const MARK_UNCHECKED = '';

	const FEE_QIW_RUB = 0.08602152;	//пр. 100/1.08602152 = 92.93(округл в меньшую сторону до второго зн)
	// - сколкьо надо отправить на пеер чтобы получить на оплату 100р
	//0.07602152	-	 комса шлюза

	private $_clientCache = null;
	private $_userCache = null;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{qiwi_pay}}';
	}

	public function rules()
	{
		return [
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false],

			['wallet', 'safe'],
			['amount', 'numerical', 'min'=>1, 'max'=>1000000000, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],
			['comment', 'unique', 'className'=>__CLASS__, 'attributeName'=>'comment', 'message'=>'comment уже был добавлен',
				'on'=>self::SCENARIO_ADD],
			['amount_prime', 'numerical', 'min'=>1, 'max'=>1000000000, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],

			['status', 'in', 'range' => array_keys(self::statusArr()), 'allowEmpty'=>false],
			['date_add, date_pay, request_api_id, m_shopid, m_historyid, m_historytm, m_curorderid', 'safe'],
			['error', 'length', 'min'=>0, 'max'=>200],
			['payeer_account_id', 'exist', 'className'=>'PayeerAccount', 'attributeName'=>'id', 'allowEmpty'=>false],
			['mark', 'length', 'max'=>100],
		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();
			$this->client_id = $this->getUser()->client_id;
		}

		$this->mark = strip_tags($this->mark);

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
	public function getDatePayStr()
	{
		return ($this->date_pay) ? date('d.m.Y H:i', $this->date_pay) : '';
	}

	/**
	 * @return string
	 */
	public function getAmountStr()
	{
		$result = ($this->amount > 0) ? formatAmount($this->amount, 0).' RUB' : '';

		return $result;
	}

	/**
	 * @return string
	 */
	public function getStatusStr()
	{
		return self::statusArr()[$this->status];
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
			$timestampStart = $timestampEnd - $intervalMax;
			//self::$lastError = 'максимальный интервал статистики: 30 дней';
			//return [];
		}

		//либо по юзеру либо по клиенту
		$userCond = ($userId) ? " AND `user_id`='$userId'" :
			(($clientId) ? " AND `client_id`='$clientId'" : '');

		$successCond = '';

		if($onlySuccess)
			$successCond = " AND `status`='".self::STATUS_SUCCESS."'";


		$models = self::model()->findAll([
			'condition'=>"
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				$userCond
				$successCond
			",
			'order'=>"`date_add` DESC",
		]);

		return $models;
	}

	/**
	 * @param self[] $models
	 * @return array
	 */
	public static function getStats($models)
	{
		$result = [
			'count'=>0,
			'amount'=>0,	//оплаченные
			'allAmount',	//все
		];

		foreach($models as $model)
		{
			$result['count']++;

			if($model->status == self::STATUS_SUCCESS)
				$result['amount'] += $model->amount;

			$result['allAmount'] += $model->amount;
		}

		return $result;
	}


	public static function statusArr()
	{
		return [
			self::STATUS_WAIT => 'в ожидании',
			self::STATUS_SUCCESS => 'оплачено',
			self::STATUS_ERROR => 'ошибка',
			self::STATUS_RESERVED => 'создается',
		];
	}

	/**
	 * перед созданием ссылки делаем запрос на создание
	 * создает и сохраняет модель, если не удалось получить ссылку то вернет false
	 *
	 * @param int $userId
	 * @param float $amount
	 * @param int $requestApiId
	 * @return string|bool
	 */
	public static function getPayUrlRequest($userId, $amount, $requestApiId = 0)
	{
		//TODO: убрать после тестов, создает реквизиты только для man11
		if($userId !== '309')
		{
			toLogRuntime('пока запускаем ман11, остальные не работают $userId = '.$userId);
			return false;
		}

		$amount = trim($amount);
		$interval = 10;

		$user = User::getUser($userId);

		if(preg_match('!^([\d\.]+)$!', $amount, $res))
		{
			$amount = $res[1];
		}
		else
		{
			self::$lastError = 'сумма должна быть числом';
			return false;
		}

		//интервал между созданием урлов
		$lastModel = self::model()->find([
			'condition' => "`user_id`='$userId'",
			'order' => "`id` DESC",
		]);

		if($lastModel and time() - $lastModel->date_add < $interval)
		{
			self::$lastError = 'подождите '.ceil($interval - time() + $lastModel->date_add).' секунд';
			return false;
		}

		$payeerAccount = PayeerAccount::getModelByUserId($userId);

		if(!$payeerAccount)
		{
			self::$lastError = 'к вашей учетной записи не привязан аккаунт, обратитесь админу';
			return false;
		}

		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->user_id = $user->id;
		$model->client_id = $user->client_id;
		$model->amount = $amount;

		$model->amount_prime = PayeerAccount::getAmountForPayeer($amount);
		$model->status = self::STATUS_RESERVED;
		$model->payeer_account_id = $payeerAccount->id;

		//TODO: найти чем заполняется, для чего это?
		//$model->api_id = $payParams['apiId'];

		if($requestApiId)
			$model->request_api_id = $requestApiId;

		$model->date_add = time();

		if($model->save())
		{
			self::$someData['qiwiPayId'] = $model->id;
			return [
				'id' => $model->id,
				'date_add' => $model->date_add,
				'status' => 'ordered',
				'request_api_id' => '$requestApiId',
			];
		}
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
	 * @return self
	 */
	public static function getModelById($id)
	{
		return self::model()->findByPk($id);
	}

	public static function cancelPayment($id, $userId)
	{
		$model = self::getModelById($id);

		if(!$model or $model->user_id != $userId)
		{
			self::$lastError = 'платеж не найден или у вас нет прав на его отмену';
			return false;
		}

		if($model->status == self::STATUS_ERROR)
		{
			self::$lastError = 'платеж уже отменен';
			return false;
		}

		$model->status = self::STATUS_ERROR;
		$model->error = 'отменен пользователем';

		return $model->save();
	}

	/**
	 * @param int $threadNumber
	 * @param int $accountId	если задан то проверяется только он
	 * @return int кол-во подтвержденных платежей
	 * обновить историю на аккаунтах с неоплаченными заявками
	 */
	public static function startUpdateHistory($threadNumber = 0, $accountId = 0)
	{
		$thread = 'payeerCheck';
		$threadCount = 10;
		$timeLimit = 50;

		//минимальный интервал обновления истории платежей
		$qiwiCheckInterval = ($accountId) ? 0 : 300;


		if($accountId)
			$threadNumber = $accountId;

		if(!Tools::threader($thread.$threadNumber))
		{
			self::$lastError = 'already run';
			return false;
		}

		if($accountId)
			$accountCond = " AND `payeer_account_id`='$accountId'";
		else
			$accountCond = '';

		//искать неоплаченные заявки не далее чем 5 часов
		$dateMin = time() - 3600*5;

		$waitPayments = self::model()->findAll([
			'condition' => "
				`date_add`>$dateMin AND `status`='".self::STATUS_WAIT."'".$accountCond,
			'group' => "`payeer_account_id`",
		]);

		/**
		 * @var self[] $waitPayments
		 */
		$accountsForCheck = [];

		foreach($waitPayments as $payment)
		{
			$payeerAccount = $payment->payeerAccount;

			if(time() - $payeerAccount->date_check > $qiwiCheckInterval)
				$accountsForCheck[$payeerAccount->id] = $payeerAccount;
		}

		//добавить в првоерку акки которые больше часа не чекались
		$dateCheck = time() - 3600;
		$updateBalanceAccounts = PayeerAccount::model()->findAll("`date_check` < $dateCheck");

		/**
		 * @var PayeerAccount $updateBalanceAccounts
		 */

		foreach($updateBalanceAccounts as $payeerAccount)
		{
			if(!$accountsForCheck[$payeerAccount->id])
				$accountsForCheck[$payeerAccount->id] = $payeerAccount;
		}

		//отсортировать по дате проверки
		$cond = "`id` IN('".implode("','", array_keys($accountsForCheck))."')";

		if($accountId)
			$threadCond = '';
		else
			$threadCond = " AND ".Tools::threadCondition($threadNumber, $threadCount);


		$accountsForCheck = PayeerAccount::model()->findAll([
			'condition' => "$cond".$threadCond,
			'order' => "`date_check` ASC",
		]);

		/**
		 * @var PayeerAccount[] $accountsForCheck
		 */

		foreach($accountsForCheck as $payeerAccount)
		{
			if(Tools::timeIsOut($timeLimit))
				break;

			$waitPayments = self::model()->findAll([
				'condition' => "
					`status`='".self::STATUS_WAIT."'
					AND  `payeer_account_id`={$payeerAccount->id}
					AND `date_add`>$dateMin
				",
				'order' => "`id` DESC",
			]);

			$previousTransaction = '';

			foreach($waitPayments as $payment)
			{
				if($payment->id !== $previousTransaction)
					self::getTransactionStatus($payment->id);
				$previousTransaction = $payment->id;
			}

			$balance = $payeerAccount->getBalance();

			if(!$balance)
				$balance = $payeerAccount->getApiBalance();

			if($balance === false)
			{
				//toLogError('ошибка получения баланса login='.$payeerAccount->login);
				continue;
			}

			$payeerAccount->balance_ru = $balance;
			$payeerAccount->date_check = time();
			$payeerAccount->save();
		}

		return true;
	}

	/**
	 * @return PayeerAccount
	 */
	public function getPayeerAccount()
	{
		return PayeerAccount::model()->findByPk($this->payeer_account_id);
	}


	public static function mark($id, $userId, $label)
	{
		$id *= 1;
		$userId *= 1;

		if(!$model = self::model()->find("`id`='$id' and `user_id`=$userId"))
		{
			self::$lastError = 'у вас нет прав на выполнение этого действия';
			return false;
		}

		$model->mark = $label;

		return $model->save();
	}


	/**
	 * @param int $id
	 *
	 * @return bool
	 * получаем статус заявки на оплату
	 */
	public static function getTransactionStatus($id = 0)
	{

		$model = self::getModelById($id);

		if($model->status == self::STATUS_SUCCESS)
		{
			toLogError(' Заявка уже подтверждена id = '. $id);
			return false;
		}
		elseif($model->status == self::STATUS_RESERVED)
		{
			toLogRuntime(' Заявка готовится id = '. $id);
			return false;
		}

		$account = PayeerAccount::getModelById($model->payeer_account_id);


		$params = [
			'mHistoryId' => $model->m_historyid,
			'mHistoryTm' => $model->m_historytm,
			'mCurOrderId' => $model->m_curorderid,
			'mShopId' => $model->m_shopid,
		];

		$result = $account->getTransactionStatus($params);

		if($result)
		{
			if($result['status'] == self::STATUS_SUCCESS)
			{
				$model->status = self::STATUS_SUCCESS;
				$model->save();
				return [

				];
			}
			else
				return false;
		}
		else
			return false;
	}


	/**
	 *
	 * @return string|bool
	 */
	public static function getPayUrl()
	{
		$thread = 'getPayUrl';
		$timeLimit = 50;

		if(!Tools::threader($thread))
		{
			self::$lastError = 'already run';
			return false;
		}

		$reservedPayments = self::model()->findAll([
			'condition' => "
				`status`='".self::STATUS_RESERVED."'",
			'group' => "`user_id`",
		]);

		/**
		 * @var self[] $reservedPayments
		 */
		$accountsForCheck = [];

		foreach($reservedPayments as $payment)
		{
			if(Tools::timeIsOut($timeLimit))
				break;

			$payeerAccount = $payment->payeerAccount;

			if(!$payeerAccount)
			{
				self::$lastError = 'к вашей учетной записи не привязан аккаунт, обратитесь админу';
				return false;
			}

			$payParams = $payeerAccount->getPayUrlParams($payment->amount);

			if(!$payParams['amount'] or !$payParams['number'] or !$payParams['comment'])
			{
				self::$lastError = 'реквизиты не получены '.$payeerAccount::$lastError;
				toLogError(self::$lastError.' : Account '.$payeerAccount->login);
				return false;
			}

			$payment->wallet = '+'.$payParams['number'];
			$payment->comment = $payParams['comment'];

			//добавочные параметры, по ним будем определать статус оплаты
			$payment->m_shopid = $payParams['mShopId'];
			$payment->m_historyid = $payParams['mHistoryId'];
			$payment->m_historytm = $payParams['mHistoryTm'];
			$payment->m_curorderid = $payParams['mCurOrderId'];
			$payment->status = self::STATUS_WAIT;

			//костыль на смену кеша ManagerApi, если запрос
			if($payment->request_api_id and $request = ManagerApiRequest::model()->findByAttributes([
					'request_id' => $payment->request_api_id,
					'user_id' => $payment->user_id,
				]))
			{
				/**
				 * @var ManagerApiRequest $request
				 */
				$result = [
					'result' => [
						'wallet' => $payment->wallet,
						'comment' => $payment->comment,
						'orderId' => $payment->id,
					],
					'errorCode' => '',
					'errorMsg' => '',
				];

				$request->raw_response = json_encode($result);
				$request->response = Tools::arr2Str($result);
				$request->error = '';

				if(!$request->save())
					toLogError('error save '.__METHOD__.', line: '.__LINE__);
			}

			if($payment->save())
			{
				self::$someData['qiwiPayId'] = $payment->id;
				return [
					'wallet' => $payment->wallet,
					'amount' => $payment->amount,
					'comment' => $payment->comment,
				];
			}

		}
	}

}