<?php

/**
 * Qiwi  платежи
 *
 * Class QiwiPay
 * @property int id
 * @property int api_id
 * @property int user_id
 * @property int client_id
 * @property string wallet
 * @property float amount
 * @property string comment
 * @property string order_id
 * @property string number
 * @property string status
 * @property int date_add
 * @property int date_pay
 * @property string error
 * @property int account_id
 * @property int transaction_id
 * @property string mark
 * @property int request_api_id
 *
 * @property string datePayStr
 * @property string statusStr
 * @property string amountStr
 * @property string dateAddStr
 * @property User user
 * @property Account account
 */

class NextQiwiPay extends Model
{
	const STATUS_WAIT = 'wait';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';

	const MARK_CHECKED  = 'checked';
	const MARK_UNCHECKED = '';

	const ORDER_INTERVAL = 1200;
	const TIME_TO_DISABLE_PAY_URL = 1200; // после 20 мин от первой транзакции перестаем выдавать реквизиты

	private $_clientCache = null;
	private $_userCache = null;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{next_qiwi_pay}}';
	}

	public function rules()
	{
		return [
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false],

			['wallet', 'match', 'pattern'=>cfg('wallet_reg_exp'), 'allowEmpty'=>false],
			['amount', 'numerical', 'min'=>1, 'max'=>1000000000, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],
			['comment', 'unique', 'className'=>__CLASS__, 'attributeName'=>'comment', 'message'=>'comment уже был добавлен',
				'on'=>self::SCENARIO_ADD],
			['status', 'in', 'range' => array_keys(self::statusArr()), 'allowEmpty'=>false],
			['date_add, date_pay, request_api_id, transaction_id, order_id, number', 'safe'],
			['error', 'length', 'min'=>0, 'max'=>200],
			['account_id', 'exist', 'className'=>'Account', 'attributeName'=>'id', 'allowEmpty'=>false],
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
			'countSuccess'=>0,
			'amount'=>0,	//оплаченные
			'allAmount',	//все
		];

		foreach($models as $model)
		{
			$result['count']++;

			if($model->status == self::STATUS_SUCCESS)
			{
				$result['amount'] += $model->amount;
				$result['countSuccess']++;
			}

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
		];
	}

	/**
	 * перед созданием ссылки делаем запрос на создание
	 * создает и сохраняет модель, если не удалось получить ссылку то вернет false
	 *
	 * @param int $userId
	 * @param float $amount
	 * @param int $requestApiId
	 * @return array|bool
	 */
	public static function getPayParams($userId, $amount, $requestApiId = 0)
	{
		$amount = trim($amount)*1;
		$interval = 1;

		$user = User::getUser($userId);

		if(!$user)
		{
			self::$lastError = 'пользователь не найден';
			return false;
		}

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

		//TODO: сделать поиск коша с доступным лимитом
		$account = $user->pickAccountForPayment($amount);

		if(!$account or $account->comment == 'stopping')
		{
			self::$lastError = 'не удалось получить платежные параметры';
			return false;
		}

		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->user_id = $user->id;
		$model->client_id = $user->client_id;
		$model->amount = $amount;
		$model->wallet = $account->login;
		$model->status = self::STATUS_WAIT;
		$model->account_id = $account->id;
		$timestamp = time();
		$model->order_id = round(microtime(true) * 1000);
		$model->number = $timestamp + rand(3000000000, 3935503919);
		$model->comment = '#'.$model->order_id;

		if($requestApiId)
			$model->request_api_id = $requestApiId;

		$model->date_add = time();

		if($model->save())
		{
			return [
				'wallet' => $model->wallet,
				'amount' => $model->amount,
				'comment' => $model->comment,
				'orderId' => $model->id,
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

	public static function cancelPayment($id, $userId, $error = 'отменен пользователем')
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
		$model->error = $error;

		return $model->save();
	}

	/**
	 * @return Account
	 */
	public function getAccount()
	{
		return Account::model()->findByPk($this->account_id);
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

	public static function getTransactionStatus($id)
	{
		$model = self::model()->findByPk($id);

		/**
		 * @var self $model
		 */

		if($model)
		{
			$account = $model->account;

			try
			{
				$transactions = $account->getAllTransactions();

				if($transactions)
				{
					foreach($transactions as $transaction)
					{
						if(
							$model->amount == $transaction->amount
							and $transaction->status === Transaction::STATUS_SUCCESS
							and preg_match('!#'.$model->order_id.'!iu', $transaction->comment))
						{
							return self::STATUS_SUCCESS;
						}
					}
				}
			}
			catch(Exception $e)
			{
				toLogError('Ошибка получения статуса транзакции login = '.$account->login.' '.$e);
			}

			return $model->status;
		}



		//$account = Account::model()->findByPk();

		//$bot = $account->getBot();
		//$dayCount = 1;
		//$transactions = $bot->getLastPayments($dayCount);



		return false;
	}

	/**
	 * авто-завершение заявок по таймауту
	 */
	public static function startCheckOrders()
	{
		session_write_close();

		$liveTimePayUrl = 20 * 60; //время жизни заявки
		$timeToDisableWallet = 60 * 60; // после этого времени отключаем кош
		$time = time() - self::ORDER_INTERVAL;

		$waitOrders = self::model()->findAll("`status`='".self::STATUS_WAIT."'");

		/**
		 * @var self[] $waitOrders
		 */

		foreach ($waitOrders as $waitOrder)
		{
			$status = self::getTransactionStatus($waitOrder->id);

			$account = $waitOrder->account;

			/**
			 * @var Transaction $firstTransaction
			 */
			$firstTransaction = end($account->getAllTransactions());

			if($firstTransaction)
			{
				if((time() - $firstTransaction->date_add) > self::TIME_TO_DISABLE_PAY_URL)
				{
					$account->comment = 'stopping';
					$account->save();
				}
				if(
					(time() - $firstTransaction->date_add) > $timeToDisableWallet
					and $account->date_check > ($liveTimePayUrl + $waitOrder->date_add)
				)
				{
					$account->error = 'stop';
					$account->save();
				}
			}


			if($status === self::STATUS_SUCCESS)
			{
				$waitOrder->status = self::STATUS_SUCCESS;

				$waitOrder->account->reserveAmount(-$waitOrder->amount);

				//отпустить кош если нет в текущих заявках
				if(!self::model()->find("`account_id`='{$waitOrder->account->id}' AND `status`='wait'"))
				{
					if(!self::returnToFreeAccount($waitOrder->account->id))
					{
						toLogError('ошибка отмены заявки NextQiwi ' . $waitOrder->id);
						return false;
					}
				}


				if($waitOrder->save())
					toLogRuntime('помечен оплаченным NextQiwi orderId='.$waitOrder->id.' на сумму '.$waitOrder->amount);
			}
			else
			{
				if(
					$waitOrder->date_add < $time
					and $account->date_check > ($liveTimePayUrl + $waitOrder->date_add)
				)
				{
					if(self::cancelPayment($waitOrder->id, $waitOrder->user_id, 'отменено системой'))
					{
						//отпустить кош если нет в текущих заявках
						if(!self::model()->find("`account_id`='{$waitOrder->account->id}' AND `status`='wait'"))
						{
							if(!self::returnToFreeAccount($waitOrder->account->id))
							{
								toLogError('ошибка1 отмены заявки NextQiwi ' . $waitOrder->id);

								return false;
							}
						}
					}
					else
					{
						toLogError('ошибка отмены заявки NextQiwi ' . $waitOrder->id);

						return false;
					}
				}
			}

		}

		return true;
	}

	public static function returnToFreeAccount($accountId)
	{
		$account = Account::model()->findByPk($accountId);

		/**
		 * @var Account $account
		 */

		Account::model()->updateByPk($account->id, [
			'user_id'=>0,
			'check_priority'=>0,
			'label'=>'',
			'date_pick'=>0,
		]);

		//обнулить резервы
		$account->reserveAmount(-$account->getReserveAmount());

		return true;
	}


	/**
	 * запуск по расписанию
	 * @param int $thread
	 * @return int
	 */
	public static function checkWalletsBan($thread = 0)
	{
		$clientCond = "";
		$clientId = "";
		$withError = false;
		$order = false;
		$threadName = 'banChecker'.$thread;
		$limitAtOnce = 20;
		$pauseMin = 2;
		$pauseMax = 4;
		$maxTime = 55;
		$maxThreads = 10;

		$checkCount = 0;

		if($clientId)
			$clientCond = " AND `client_id`='$clientId'";

		$errorCond = "";

		if(!$withError)
			$errorCond = " AND `error`=''";

		//оптимизация
		//если кошельки неделю не чекались нас не интересуют
		$dateCheck = time() - 3600*24*7;

		$threadCond = " AND ".Tools::threadCondition($thread, $maxThreads);

		$models = Account::model()->findAll(array(
			'condition'=>"
				`date_check` > $dateCheck
				AND `type`='".Account::TYPE_IN."' and `date_used`=0
				AND `enabled` = 1
				$threadCond
				$clientCond
				$errorCond
				",
			'order'=>($order) ? $order : "`id`",
		));

		if($models)
		{
			foreach($models as $model)
			{
				if(Tools::timeIsOut($maxTime) or $checkCount > $limitAtOnce)
					break;

				$res = Account::checkBan($model->login);

				//TODO: после теста убрать вывод
				toLogRuntime('Проверка на бан, аккаунт = '.$model->login.' результат = '.(bool)$res);

				if($res === true or $res === false)
				{
					$model->date_check = time();
					$model->error = ($res === true) ? BanChecker::ERROR_BAN : '';

					//блокируем кошелек если он есть в панели
					if($res === true)
						BanChecker::disableAccount($model->login);
				}
				else
				{
					if(Account::$lastErrorCode)
					{
						$model->date_check = time();
						$model->error = Account::$lastErrorCode;

						if(Account::$lastErrorCode == BanChecker::ERROR_PASSWORD_EXPIRED)
						{
							//слетел пароль(скорее всего), отключаем кошелек
							BanChecker::disableAccount($model->login);
						}
					}
					else
					{
						//тут какие то другие вариенты которые появятся в будущем
					}

					//любые ошибки пишем в лог
					toLogError("BanChecker error check {$model->login}: ".Account::$lastError);
				}

				if(!$model->save())
				{
					toLogError("BanChecker error save ".$model->id);
					break;
				}

				//sleep(rand($pauseMin, $pauseMax));

				$checkCount++;
			}
		}
		else
			self::$lastError = 'nothing to check';

		return $checkCount;
	}





}