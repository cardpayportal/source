<?php

/**
 * Class StoreApiTransactions

 * @property int id
 * @property int amount
 * @property string store_id
 * @property int date_add
 * @property int date_pay
 * @property string status 'success'|'wait'|'error'
 * @property string amountStr
 * @property string statusStr
 * @property string dateAddStr
 * @property string datePayStr
 * @property string currency
 * @property int user_id
 * @property int client_id
 * @property int model_id
 * @property string model_type
 * @property int order_id
 *
 */

class StoreApiTransaction extends Model
{
	const SCENARIO_ADD = 'add';

	const STATUS_NO = '';					//нет информации
	const STATUS_WAIT = 'wait';				//платеж в ожидании
	const STATUS_SUCCESS = 'success';		//успешный
	const STATUS_ERROR = 'error';			//ошибка
	const STATUS_NOT_FOUND = 'not_found';	//платеж не найден после проверки кошелька

	const LIMIT_ERROR_VIEW = 500;			//отображение последних 100 ошибок для глобалФина
	const INTERVAL_NOT_FOUND = 1200;			//отображать в ошибках если платеж не найден за .. сек
	const CURRENCY_RUB = 'RUB';
	const CURRENCY_KZT = 'KZT';

	const MODEL_TYPE_NEW_YANDEX_PAY = 'newYandexPay';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'account_id' => 'Кошелек',
			'amount' => 'Сумма',
			'store_id' => 'ID магазина',
			'date_add' => 'дата добавления',
			'date_pay' => 'дата оплаты',
			'currency' => 'Валюта',
		);
	}

	public function tableName()
	{
		return '{{store_api_transactions}}';
	}

	public function rules()
	{
		return array(
			array('amount', 'numerical', 'min'=>1, 'max'=>300000, 'allowEmpty'=>false),	//вдруг перевод в тенге
			array('status', 'in', 'range'=>array_keys(self::statusArr()), 'allowEmpty'=>false),
			array('store_id', 'exist', 'className'=>'StoreApi', 'attributeName'=>'id', 'allowEmpty'=>false),
			array('currency', 'in', 'range'=>array_keys(self::currencyArr()), 'allowEmpty'=>true),
			['user_id', 'exist', 'className' => 'User', 'attributeName' => 'id'],
			['client_id', 'exist', 'className' => 'Client', 'attributeName' => 'id'],
			['order_id', 'numerical'],
			['model_type', 'in', 'range'=>array_keys(self::modelTypeArr()), 'allowEmpty'=>false],
		);
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();
			$this->status = self::STATUS_WAIT;
		}

		return parent::beforeSave();
	}

	public static function statusArr()
	{
		return array(
			self::STATUS_NO => 'нет информации',
			self::STATUS_WAIT => 'ожидание',
			self::STATUS_SUCCESS => 'успех',
			self::STATUS_ERROR => 'ошибка',
			self::STATUS_NOT_FOUND => 'не найден',
		);
	}

	public static function modelTypeArr()
	{
		return [
			self::MODEL_TYPE_NEW_YANDEX_PAY => 'NewYandexPay',
		];
	}

	public static function currencyArr()
	{
		return array(
			self::CURRENCY_RUB => 'руб',
			self::CURRENCY_KZT => 'kzt',
		);
	}


	public function getAccount()
	{
		return Account::model()->findByPk($this->account_id);
	}

	public function getAmountStr()
	{
		$formatAmount = formatAmount($this->amount, 2);

		return $formatAmount;
	}

	public function getStatusStr()
	{
		if($this->status == self::STATUS_NO)
			return '<span>нет информации</span>';
		elseif($this->status == self::STATUS_WAIT)
			return '<span class="orange">ожидание</span>';
		elseif($this->status == self::STATUS_SUCCESS)
			return '<span class="green">успех</span>';
		elseif($this->status == self::STATUS_ERROR)
			return '<span class="red">ошибка</span>';
		elseif($this->status == self::STATUS_NOT_FOUND)
			return '<span class="red">не найден</span>';
	}

	public function getStatusApiStr()
	{
		if($this->status == self::STATUS_SUCCESS)
			return '<span class="green">успех</span>';
	}

	/**
	 * модель из Transaction
	 * @return Transaction|TransactionKzt
	 */
	public function getTransactionModel()
	{
		if($this->status != self::STATUS_NO and $this->status != self::STATUS_NOT_FOUND)
		{
			if($this->isKzt)
				return TransactionKzt::model()->findByAttributes(array('account_id'=>$this->account_id, 'qiwi_id'=>$this->qiwi_id));
			else
				return Transaction::model()->findByAttributes(array('account_id'=>$this->account_id, 'qiwi_id'=>$this->qiwi_id));
		}
		else
			return false;
	}

	/**
	 * добавляет сразу несколько платежей возвращает массив моделей
	 * @param array $payments
	 * @return StoreApiTransaction[]|false
	 */
	public static function addMany(array $payments)
	{
		$result = array();

		foreach($payments as $payment)
		{
			if($model = self::model()->findByAttributes(array('account_id'=>$payment['account_id'], 'qiwi_id'=>$payment['qiwi_id'])))
			{
				$result[] = $model;
				continue;
			}

			$model = new self;
			$model->scenario = self::SCENARIO_ADD;
			$model->attributes = $payment;

			if($model->save())
				$result[] = $model;
			else
				return false;
		}

		return $result;
	}

	/**
	 * обновить информацию о транзакциях
	 * @param StoreApiTransaction[] $models
	 * @return array|false [array('walletTo'=>, 'walletFrom'=>, 'amount'=>, 'transactionId'=>,'status'=>''), array(), ...]
	 */
	public static function updateInfo(array $models)
	{
		$result = array();

		foreach($models as $paymentModel)
		{
			/**
			 * @var StoreApiTransaction $paymentModel
			 */

			//пропускаем если статус уже обновлен
			if(!$paymentModel->status or $paymentModel->status == self::STATUS_NOT_FOUND or $paymentModel->status == self::STATUS_WAIT)
			{
				if(
					$paymentModel->currency == self::CURRENCY_RUB and (
						$transactionModel = Transaction::model()->findByAttributes(array('account_id'=>$paymentModel->account_id, 'qiwi_id'=>$paymentModel->qiwi_id))
						and $transactionModel->type == Transaction::TYPE_IN
						and $transactionModel->amount == $paymentModel->amount
						and $transactionModel->wallet == $paymentModel->wallet_from
					)
					or	//казашкин костыль
					$paymentModel->currency == self::CURRENCY_KZT and (
						$transactionModel = TransactionKzt::model()->findByAttributes(array('account_id'=>$paymentModel->account_id, 'qiwi_id'=>$paymentModel->qiwi_id))
						and $transactionModel->type == TransactionKzt::TYPE_IN
						and $transactionModel->amount == $paymentModel->amount
						and $transactionModel->wallet == $paymentModel->wallet_from
					)
				)
				{
					/**
					 * @var Transaction $transactionModel
					 */

					$paymentModel->status = $transactionModel->status;

					if(!$paymentModel->save())
					{
						toLogStoreApi('error save StoreApiTransaction id= '.$paymentModel->id.': '.self::$lastError.' 1');
						return false;
					}
				}
				else
				{
					//если дата проверки кошелька больше даты добавления $paymentModel то ставим статус STATUS_NOT_FOUND

					if($paymentModel->account->date_check > $paymentModel->date_add)
					{
						$paymentModel->status = self::STATUS_NOT_FOUND;

						if(!$paymentModel->save())
						{
							toLogStoreApi(self::$lastError.' 2');
							return false;
						}
					}
				}
			}

			$result[] = array(
				'walletTo'=>$paymentModel->account->login,
				'walletFrom'=>$paymentModel->wallet_from,
				'amount'=>$paymentModel->amount,
				'transactionId'=>$paymentModel->qiwi_id,
				'status'=>$paymentModel->status_api,
				'currency'=>$paymentModel->currency,
			);
		}

		return $result;
	}

	/**
	 * возвращает массив найденных но не оплаченых транзакций по store_id
	 * @param int $storeId
	 * @return StoreApiTransaction[]
	 */
	public static function getNotPaidTransactions($storeId)
	{
		$result =  self::model()->findAll(array(
			'condition'=>"`id`='$storeId' AND `date_pay`=0 AND `status`='".self::STATUS_SUCCESS."'",
		));

		/**
		 * @var self[] $result
		 */

		//фильтруем казашек без конвертации
		foreach($result as $key=>$model)
		{
			if($model->currency == self::CURRENCY_KZT)
			{
				//тут все верно! $transactionModel - успешный  конвертационный платеж в рублях из тенге таблицы Transaction
				if(!$transaction = $model->transactionModel->transactionModel)
					unset($result[$key]);
			}
		}

		return $result;
	}

	/**
	 * возвращает массив непроверенных транзакций
	 * @param int $storeId
	 * @return StoreApiTransaction[]
	 */
	public static function getNotCheckTransactions($storeId)
	{
		return self::model()->findAll(array(
			'condition'=>"
				`store_id`='$storeId' AND `date_pay`=0
				AND (`status`='".self::STATUS_NO."' OR `status`='".self::STATUS_WAIT."')
			",
		));
	}

	/**
	 * платежи, не найденные за $notFoundInterval
	 * @param bool|false $all
	 * @return self[]
	 */
	public static function getErrorTransactions($all = false)
	{
		$timestamp = time() - self::INTERVAL_NOT_FOUND;

		$models = self::model()->findAll(array(
			'condition'=>"`status`!='".self::STATUS_SUCCESS."' AND `date_add` < $timestamp",
			'order'=>"date_add desc",
			'limit'=>($all) ? null : self::LIMIT_ERROR_VIEW,
		));

		return $models;
	}

	public function getDateAddStr()
	{
		return ($this->date_add) ? date(cfg('dateFormat'), $this->date_add) : '';
	}

	public function getDatePayStr()
	{
		return ($this->date_pay) ? date(cfg('dateFormat'), $this->date_pay) : '';
	}

	/**
	 * @param int|array $id
	 * @return self
	 */
	public static function getModel($id)
	{
		//костыль
		if(is_array($id))
			return self::model()->findByAttributes($id);
		else
			return self::model()->findByPk($id);
	}

	/*
	 * помечает найденным
	 */
	public function confirm()
	{
		//если платеж являетс ошибочным
		if($this->status != self::STATUS_SUCCESS)
		{
			$this->status = self::STATUS_SUCCESS;
			$this->date_pay = time();

			if($this->save())
			{
				toLogStoreApi('платеж '.$this->id.'  помечен успешным');
				return true;
			}
			else
				self::$lastError = 'ошибка сохранения платежа '.$this->id;
		}
		else
			self::$lastError = 'платеж не является ошибочным';

		return false;
	}

	/*
	 * удаляет
	 */
	public function deleteErrorModel()
	{
		//если платеж являетс ошибочным
		if($this->status != self::STATUS_SUCCESS and time() - self::LIMIT_ERROR_VIEW > $this->date_add)
		{
			if($this->delete())
			{
				toLogStoreApi('платеж удален: '.Tools::arr2Str($this->getAttributes()));
				return true;
			}
			else
				self::$lastError = 'ошибка удаления платежа '.$this->id;
		}

		return false;
	}

	/**
	 * список моделей для отображения
	 * записывает в self::$someDate['stats'] статистику
	 * @param array $filter ['storeId'=>,'dateStart'=>'01.08.2001','dateEnd'=>'01.08.20017']
	 * @return self[]
	 */
	public static function getListModels(array $filter = array())
	{
		//$limit = 1000;

		$storeId = ($filter['storeId']) ? $filter['storeId']*1 : 0;
		$timestampStart = strtotime($filter['dateStart'])*1;
		$timestampEnd = strtotime($filter['dateEnd'])*1;

		$conditionArr = array();

		if($storeId)
			$conditionArr[] = "`store_id` = '$storeId'";

		if($timestampStart)
			$conditionArr[] = "`date_add` >= $timestampStart";

		if($timestampEnd)
			$conditionArr[] = "`date_add` <= $timestampEnd";

		$conditionStr = implode(' AND ', $conditionArr);

		$models = self::model()->findAll(array(
			'condition'=>$conditionStr,
			'order'=>"`date_add` DESC",
			//'limit'=>$limit,
		));

		/**
		 * @var self[] $models
		 */

		self::$someData['stats'] = array(
			'successAmount'=>0,
			'successAmountKzt'=>0,
			'allAmount'=>0,
			'allAmountKzt'=>0,
			'count'=>count($models),
		);

		self::$someData['successAmount'] = 0;

		foreach($models as $model)
		{
			if($model->status == self::STATUS_SUCCESS)
			{
				if($model->isKzt)
				{
					self::$someData['stats']['successAmountKzt'] += $model->amount;

					if($transactionKzt = $model->getTransactionModel())
					{
						//либо есть конвертация либо нет
						if($transaction = $transactionKzt->transactionModel)
							self::$someData['stats']['successAmount'] += $transaction->amount;
					}
				}
				else
					self::$someData['stats']['successAmount'] += $model->amount;
			}

			if($model->isKzt)
				self::$someData['stats']['allAmountKzt'] += $model->amount;
			else
				self::$someData['stats']['allAmount'] += $model->amount;
		}

		return $models;
	}

	/**
	 * todo: как то находить неизвестные kzt-переводы
	 * лишние платежи которые не зафиксированы в StoreApiTransaction
	 * @param bool|false $all
	 * @return Transaction[]
	 */
	public static function getUnknownTransactions($all = false)
	{
		$result = array();

		$clientId = 16;

		$timestampFrom = time() - 3600*24*15;	//смотрим за последние .. часа
		$timestampTo = time() - self::INTERVAL_NOT_FOUND;	//не позже ..

		$users = User::model()->findAll("`role`='".USER::ROLE_MANAGER."' and `client_id`=$clientId");

		/**
		 * @var User[] $users
		 */

		$userStr = '';

		foreach($users as $user)
			$userStr .=  ',' . $user->id;

		$userStr = ltrim($userStr, ',');

		//входящие платежи менеджеров
		$models = Transaction::model()->findAll(array(
			'condition'=>"
				`date_add` >= $timestampFrom AND `type`='".Transaction::TYPE_IN."' AND `status`='".Transaction::STATUS_SUCCESS."'
				AND `date_add` <= $timestampTo
				AND `user_id` IN($userStr)
			",
			'order'=>"date_add DESC",
			'limit'=>1000,
		));

		/**
		 * @var Transaction[] $models
		 */

		foreach($models as $model)
		{
			if($model->convert_id)
			{
				//если конвертация есть а платежа по апи не пришло
				$kztTransaction = $model->transactionKzt;

				if(!self::model()->find("`account_id`='{$kztTransaction->account_id}' AND `qiwi_id`='{$kztTransaction->qiwi_id}'"))
					$result[] = $model;
			}
			elseif(!self::model()->find("`account_id`='{$model->account_id}' AND `qiwi_id`='{$model->qiwi_id}'"))
			{
				$result[] = $model;
			}
		}

		return $result;
	}

	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $storeId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param bool $successOnly
	 * @return self[]
	 */
	public static function getModels($timestampStart, $timestampEnd, $userId, $storeId = 0, $successOnly = true)
	{
		$userId = intval($userId);
		$storeId = intval($storeId);

		$timestampStart = intval($timestampStart);
		$timestampEnd = intval($timestampEnd);

		$condition = "`user_id`='$userId'";

		if($storeId)
			$condition .= " AND `store_id`='$storeId'";

		if($successOnly)
			$condition .= " AND `status`='".self::STATUS_SUCCESS."'";

		$models = self::model()->findAll([
			'condition'=>"
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				AND $condition
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
			'successAmount'=>0,
		];

		foreach($models as $model)
		{
			if($model->status == self::STATUS_SUCCESS)
				$result['successAmount'] += $model->amount;
		}

		return $result;
	}

	/**
	 * @param int $modelId
	 * @return bool
	 */
	public static function confirmByModelId($modelId)
	{
		if($model = self::getModel(['model_id'=>$modelId]))
		{
			return $model->confirm();
		}
		else
		{
			//self::$lastError = 'запись не найдена';
			//toLogStoreApi('confirmByModelId: model_id = '.$modelId.' не найден');
			return false;
		}
	}


}