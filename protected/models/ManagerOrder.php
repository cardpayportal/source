<?php

/**
 * Class ManagerOrder
 * @property int id
 * @property int user_id
 * @property int amount_add сумма при добавлении
 * @property float amount_end сумма на момент окончания (сколько принято)
 * @property int date_add дата добавления
 * @property int date_end дата окончания
 * @property User user
 * @property ManagerOrderAccount[] orderAccounts
 * @property ManagerOrderAccount[] orderAccountsForSave
 * @property float $amountIn
 * @property int timeout
 * @property string timeoutStr
 * @property string dateAddStr
 * @property string dateEndStr
 * @property int date_end_request дата нажатия кнопки Завершить
 * @property int calc_id номер расчета
 * @property int date_pay дата, когда заявка попала в расчет либо была помечена олпаченной автоматом
 * @property ClientCalc calc
 * @property string datePayStr
 *
 *
 * todo: прописать события на удаление модели
 */

class ManagerOrder extends Model
{
	const SCENARIO_COMPLETE = 'complete';
	const SCENARIO_COMPLETE_PREPARE = 'completePrepare';	//не реализовано(добавлено повышение приоритета при завершении)

	private $orderAccountsForSave = [];

	private $_amountInCache = null; //кеш для amountIn

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{manager_order}}';
	}

	public function beforeValidate()
	{
		if($this->calc_id == 0)
			unset($this->calc_id);

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false],
			['amount_end', 'numerical', 'min'=>0, 'on'=>self::SCENARIO_COMPLETE, 'allowEmpty'=>false],

			['amount_add', 'numerical', 'allowEmpty'=>false],
			['amount_add', 'amountAddValidator', 'on'=>self::SCENARIO_ADD],
			['amount_add', 'limitValidator', 'on'=>self::SCENARIO_ADD],
			['calc_id', 'exist', 'className'=>'ClientCalc','attributeName'=>'id', 'on'=>self::SCENARIO_ADD, 'allowEmpty'=>true],
			['date_pay', 'numerical', 'allowEmpty'=>true],
			//array('user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>true),
		);
	}

	public function amountAddValidator()
	{
		$client = $this->getUser()->client;
		$config = $client->orderConfig;

		if(!$config)
			$this->addError('amount_add', 'не настроен конфиг для вашего пользователя');
		elseif($this->amount_add < $config['wallet_amount_min'] or $this->amount_add > $config['order_amount_max'])
			$this->addError('amount_add', 'неверная сумма заявки (мин '.$config['wallet_amount_min'].', макс '.$config['order_amount_max'].')');
	}

	/*
	 * проверка лимитов
	 */
	public function limitValidator()
	{
		$client = $this->getUser()->client;
		$config = $client->orderConfig;

		if(!$config)
			$this->addError('amount_add', 'не настроен конфиг для вашего пользователя');

		$clientUsers = '';

		foreach($client->users as $user)
			$clientUsers .= $user->id.',';

		$clientUsers = trim($clientUsers, ',');

		//проверить кол-во заявок на юзера и на кл
		if($this->scenario == self::SCENARIO_ADD)
		{
			if(self::model()->count("`user_id`={$this->user_id} AND `date_end`=0") >= $config['manager_order_count_max'])
				$this->addError('amount_add', 'завершите активные заявки перед получением новых');
			elseif(self::model()->count("`user_id` IN($clientUsers) AND `date_end`=0") >= $config['client_order_count_max'])
				$this->addError('amount_add', 'ограничение на максимальное количество заявок');
		}
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();
			$this->orderAccountsForSave = $this->getOrderAccountsByAmount();

			//недостаточно кошельков (ошибку забьет функция getAccountsByAmount)
			if(!$this->orderAccountsForSave)
				return false;
		}
		elseif($this->scenario == self::SCENARIO_COMPLETE)
		{
			$this->date_end = time();
			$this->amount_end = $this->getAmountIn();

			//обновить все кошельки этой заявки
			foreach($this->getOrderAccounts() as $orderAccount)
				Account::setPriorityNow($orderAccount->account_id);
		}

		return parent::beforeSave();
	}

	protected function afterSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			foreach($this->orderAccountsForSave as $orderAccount)
			{
				$orderAccount->order_id = $this->id;

				if(!$orderAccount->save())
				{
					self::$lastError = $orderAccount::$lastError;
					return false;
				}
			}
		}

		parent::afterSave();
	}


	/**
	 * @return User
	 */
	public function getUser()
	{
		return User::getUser($this->user_id);
	}

	/**
	 * @return ManagerOrderAccount[]
	 */
	public function getOrderAccounts()
	{
		return ManagerOrderAccount::modelsByOrderId($this->id);
	}

	/**
	 * создает заявку
	 *
	 * @param array $params ['amount'=>, 'user_id'=>]
	 * @return bool
	 */
	public static function add($params)
	{
		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->user_id = floor($params['user_id']*1);
		$model->amount_add = floor($params['amount']*1);

		return $model->save();
	}

	/**
	 * кошельки на нужную сумму
	 * @return ManagerOrderAccount[]
	 */
	private function getOrderAccountsByAmount()
	{
		$result = [];

		$user = $this->getUser();
		$client = $user->client;
		$config = $client->orderConfig;
		$amount = $this->amount_add;
		//сколько должно остаться лимита на кошельке(сумма для подстраховки)
		//если поставить больше нуля то у клиентов с заливом по 100к на кош не будут браться (dayLimit)
		$limitMin = 15000;	//используем кошель на 175к в общем

		$allowedAmounts = [
			20000, 40000, 80000, 100000, 200000
		];

		$amountMin = 2;

		$accountAmountMin = $config['wallet_amount_min'];

		if(!$config)
		{
			self::$lastError = 'не настроен конфиг для вашего пользователя';
			return $result;
		}

		if(
			!config('pickAccountEnabled')
			or !$client->pick_accounts
		)
		{
			self::$lastError = 'выдача новых кошельков временно прекращена';
			return $result;
		}

		$currentAccounts = Account::model()->findAll(array(
			'condition'=>"
				`type`='".Account::TYPE_IN."'
				AND `user_id`={$user->id}
				AND `date_used`=0 AND `date_out_of_limit`=0
				AND `error`=''
				AND `enabled`=1
			",
			'order'=>"`limit_in` ASC",	//побыстрее лимиты израсходовать
		));

		/**
		 * @var Account[] $currentAccounts
		 */

		//если критический мод
		foreach($currentAccounts as $key=>$currentAccount)
		{
			if(config('criticalMode'))
			{
				if(!$currentAccount->isCritical)
					unset($currentAccounts[$key]);
			}
			else
			{
				if($currentAccount->isCritical)
					unset($currentAccounts[$key]);
			}

		}

		$ostatok = $amount;	//на какую сумму еще осталось подобрать кошельков, если 0 то цикл завершить

		$resultAccounts = [];	//кошелек-сумма

		//todo: забивать кошельки с низким лимитом в остаток по возможности
		if($currentAccounts)
		{
			foreach($currentAccounts as $account)
			{
				//если задействован в активной заявке то пропускаем
				if(!self::isFreeAccount($account->id))
					continue;

				$possibleAmount = self::pickAmount($allowedAmounts, $accountAmountMin, $account->limit_in, $account->dayLimit, $limitMin);

				if(!$possibleAmount)
					continue;

				if($possibleAmount > $ostatok)
					$possibleAmount = $ostatok;

				if($possibleAmount >= $amountMin)
				{
					$resultAccounts[$account->id] = array('account'=>$account, 'amount'=>$possibleAmount);
					$ostatok -= $possibleAmount;
				}

				//если остаток меньше 2 рублей то добавить к последнему кошельку
				if($ostatok < $amountMin and $resultAccounts[$account->id])
				{
					$resultAccounts[$account->id]['amount'] += $ostatok;
					$ostatok = 0;
				}

				if($ostatok < 0)
				{
					toLog('ManagerOrder::getOrderAccountsByAmount() исключение001');
					return false;
				}

				if($ostatok == 0)
					break;
			}
		}

		$pickNewCount = 0;	//сколько новых выдано

		//если текущих не хватило, выдаем новые
		if($ostatok > 0)
		{
			//todo: убрать эти ограничения, ибо и так есть ограничения по заявкам
			//если текущих кошельков больше чем self::CURRENT_WALLET_LIMIT то не выдавать
			$currentAccounts = $user->getCurrentAccounts();


			$goodCurrentAccountsCount = 0;

			foreach($currentAccounts as $currentAccount)
			{
				if($currentAccount->error)
					continue;

				if(config('criticalMode') and $currentAccount->limit_in < cfg('criticalAccountMinLimit'))
					continue;

				$goodCurrentAccountsCount++;
			}

			$maxAccountCount = (config('criticalMode')) ? cfg('criticalAccountCountPerUser') : cfg('managerAccountLimit');

			if($goodCurrentAccountsCount  > $maxAccountCount)
			{
				self::$lastError = 'ограничение на выдачу кошельков (максимум '.$maxAccountCount.')';
				toLogError('ограничение на выдачу кошельков '.$client->name.': '.$user->name);
				return false;
			}

			$freeInAccounts = Account::getFreeInAccounts($client->id);

			foreach($freeInAccounts as $account)
			{
				//опять то же самое повторяем для новых кошельков

				//если задействован в активной заявке то пропускаем
				if(!self::isFreeAccount($account->id))
					continue;

				$possibleAmount = self::pickAmount($allowedAmounts, $accountAmountMin, $account->limit_in, $account->dayLimit, $limitMin);

				if(!$possibleAmount)
					continue;

				if($possibleAmount > $ostatok)
					$possibleAmount = $ostatok;

				if($possibleAmount >= $amountMin)
				{
					$resultAccounts[$account->id] = array('account'=>$account, 'amount'=>$possibleAmount);
					$ostatok -= $possibleAmount;
				}

				//если остаток меньше 2 рублей то добавить к последнему кошельку
				if($ostatok < $amountMin and $resultAccounts[$account->id])
				{
					$resultAccounts[$account->id]['amount'] += $ostatok;
					$ostatok = 0;
				}

				if($ostatok < 0)
				{
					toLog('ManagerOrder::getOrderAccountsByAmount() исключение001');
					return false;
				}

				if($ostatok == 0)
					break;
			}
		}


		if($ostatok > 0)
		{
			//не хватило кошельков
			self::$lastError = 'недостаточно кошельков';
			toLogError('недостаточно проверенных кошельков у кл'.$client->id.' , для суммы : '.formatAmount($amount).' '.$user->name, false, true);
			return $result;
		}

		$datePick = time();

		foreach($resultAccounts as $arr)
		{
			/**
			 * @var $model Account
			 */
			$model = $arr['account'];

			if(!$model->user_id)
			{
				$params = array(
					'check_priority'=>Account::PRIORITY_NOW,	//приоритет выше среднего
				);

				//если новый
				if(!$model->user_id)
				{
					$params['user_id'] = $user->id;
					$model->user_id = $user->id;

					//новый кош изменяет group_id под group_id взявшего
					if($user->group_id)
					{
						$params['group_id'] = $user->group_id;
						$model->group_id = $user->group_id;
					}
				}

				if(!$model->date_pick)
				{
					$params['date_pick'] = $datePick;
					$model->date_pick = $datePick;
				}

				Account::model()->updateByPk($model->id, $params);

				$model->check_priority = Account::PRIORITY_NOW;
			}

			//создаем ManagerOrderAccount
			$managerOrderAccount = new ManagerOrderAccount();
			$managerOrderAccount->scenario = ManagerOrderAccount::SCENARIO_ADD;
			$managerOrderAccount->account_id = $model->id;
			$managerOrderAccount->amount = $arr['amount'];

			$result[] = $managerOrderAccount;

		}

		if($pickNewCount)
			toLogRuntime('взято '.$pickNewCount.' новых аккаунтов (orders)');


		return $result;
	}

	/**
	 * подобрать сумму для кошелька, выберет одну из списка
	 * @param array $allowedAmounts
	 * @param float $amountMin
	 * @param float $accountFullLimit
	 * @param float $accountDayLimit
	 * @param float $limitMin
	 * @return float
	 */
	private static function pickAmount($allowedAmounts, $amountMin, $accountFullLimit, $accountDayLimit, $limitMin)
	{
		$result = 0;

		$fullLimit = $accountFullLimit - $limitMin;	//190-15

		$dayLimit = ($accountDayLimit > $fullLimit) ? $fullLimit : $accountDayLimit;

		$allowedAmounts = array_reverse($allowedAmounts);

		foreach($allowedAmounts as $allowedAmount)
		{
			if($allowedAmount >= $amountMin and $allowedAmount<=$dayLimit)
			{
				$result = $allowedAmount;
				break;
			}
		}


		return $result;
	}

	/**
	 * проверяет, не задействован ли аккаунт в какой то активной заявке
	 * @param int $accountId	Account->id
	 * @return bool
	 */
	private static function isFreeAccount($accountId)
	{
		$result = true;
		$orders = self::getActiveOrders();

		foreach($orders as $order)
		{
			$orderAccounts = $order->getOrderAccounts();

			foreach($orderAccounts as $orderAccount)
				if($orderAccount->account_id == $accountId)
					return false;
		}

		return $result;
	}

	/**
	 * активные заявки по убыванию даты добавления
	 * @param int|null $userId
	 * @return self[]
	 */
	public static function getActiveOrders($userId=null)
	{
		if($userId)
			$userCond = " AND `user_id`=".($userId*1);
		else
			$userCond = '';

		return self::model()->findAll([
			'condition'=>"`date_end`=0$userCond",
			'order'=>"`date_add` DESC",
		]);
	}

	/**
	 * активные заявки по убыванию даты добавления
	 * @param int|null $userId
	 * @return self[]
	 */
	public static function getUsedOrders($userId=null, $timestampStart = 0)
	{
		$userCond = ($userId) ? " AND `user_id`=".($userId*1) : '';
		$timestampStart *= 1;

		return self::model()->findAll([
			'condition'=>"`date_add`>$timestampStart AND `date_end`>0$userCond",
			'order'=>"`date_end` DESC",
		]);
	}

	/**
	 * @param int|null $userId
	 * @param int $timestampStart
	 * @return self[]
	 */
	public static function getAllOrders($userId=null, $timestampStart = 0)
	{
		$userCond = ($userId) ? " AND `user_id`=".($userId*1) : '';
		$timestampStart *= 1;

		return self::model()->findAll([
			'condition'=>"`date_add`>$timestampStart $userCond",
			'order'=>"`date_add` DESC",
		]);
	}

	/**
	 * сколько залито на текущую заявку (по сумме транзакций)
	 * @return float
	 */
	public function getAmountIn()
	{
		if($this->_amountInCache === null)
		{
			$result = 0;

			$timestampStart = $this->date_add;
			$timestampEnd = ($this->date_end) ? $this->date_end : time();

			$idCond = '';

			foreach($this->orderAccounts as $orderAccount)
				$idCond .= $orderAccount->account_id.',';

			$idCond = trim($idCond, ',');

			$transactions = Transaction::model()->findAll([
				'select'=>"`amount`",
				'condition'=>"
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				AND `status`='".Transaction::STATUS_SUCCESS."'
				AND `account_id` IN($idCond)
				AND `type`='".Transaction::TYPE_IN."'
			",
			]);

			/**
			 * @var Transaction[] $transactions
			 */

			foreach($transactions as $transaction)
				$result += $transaction->amount;


			$this->_amountInCache = $result;
		}

		return $this->_amountInCache;
	}

	/**
	 * завершение заявки
	 * @param int $orderId
	 * @param int $userId завершить может только хозяин
	 * @return bool
	 */
	public static function complete($orderId, $userId)
	{
		$orderId *= 1;
		$userId *= 1;

		if($model = self::model()->find("`id`=$orderId AND `user_id`=$userId AND `date_end`=0"))
		{
			/**
			 * @var self $model
			 */

			$model->scenario = self::SCENARIO_COMPLETE;
			return $model->save();
		}
		else
		{
			self::$lastError = 'заявка не найдена или уже завершена';
			return false;
		}
	}

	/**
	 * @param  int $orderId
	 * @param int $userId
	 * @return bool
	 */
	public static function completePrepare($orderId, $userId)
	{
		$orderId *= 1;
		$userId *= 1;

		if($model = self::model()->find("`id`=$orderId AND `user_id`=$userId AND `date_end`=0 AND `date_end_request`=0"))
		{
			/**
			 * @var self $model
			 */

			$model->scenario = self::SCENARIO_COMPLETE_PREPARE;
			return $model->save();
		}
		else
		{
			self::$lastError = 'заявка не найдена или уже завершена';
			return false;
		}
	}

	/**
	 * @param int $id
	 * @return self
	 */
	public static function getModelById($id)
	{
		return self::model()->findByPk($id);
	}

	/**
	 * ставит кошелькам заявки высокий приоритет
	 * @param int $orderId
	 * @param int $userId
	 * @return int кол-во поставленых на проверку
	 */
	public static function setPriorityNow($orderId, $userId)
	{
		$result = 0;

		$orderId *= 1;
		$userId *= 1;

		$order = self::getModelById($orderId);

		//если не хозяин либо заявка завершена то не делать
		if($order->user_id != $userId or $order->date_end)
		{
			self::$lastError = 'один из кошельков заявки не найден либо заявка уже завершена';
			return $result;
		}

		foreach ($order->orderAccounts as $orderAccount)
		{
			if(Account::setPriorityNow($orderAccount->account_id))
				$result++;
			else
			{
				self::$lastError = Account::$lastError;
				continue;
			}
		}

		return $result;
	}

	/**
	 * кол-во секунд до автоматического завершения заявки
	 * @return int
	 */
	public function getTimeout()
	{
		return config('managerOrderTimeout') - (time() - $this->date_add);
	}

	public function getTimeoutStr()
	{
		$seconds = $this->getTimeout();

		if($seconds < 3600)
			return  formatAmount($seconds/60, 0).' минут';
		else
			return  formatAmount($seconds/3600, 1).' часов';
	}

	public function getDateAddStr()
	{
		return ($this->date_add) ? date('d.m.Y H:i', $this->date_add) : '';
	}

	public function getDateEndStr()
	{
		return ($this->date_end) ? date('d.m.Y H:i', $this->date_end) : '';
	}

	/**
	 * последние заявки, сортировка по клиенту
	 *
	 * @param int $timestampStart
	 * @param int $clientId
	 * @return self[]
	 */
	public static function getLatestOrders($timestampStart, $clientId = 0)
	{
		$result = [];

		$orders = self::getAllOrders(null, $timestampStart);

		foreach(Client::getModels() as $client)
		{
			if($clientId and $client->id != $clientId)
				continue;

			$users = $client->users;

			foreach($orders as $order)
			{
				foreach($users as $user)
				{
					if($user->id == $order->user_id)
						$result[] = $order;
				}
			}

		}

		return $result;
	}

	/**
	 * @return ClientCalc|false
	 */
	public function getCalc()
	{
		if($model = ClientCalc::getModelById($this->calc_id))
			return $model;
		else
			return false;

	}

	public function getDatePayStr()
	{
		if($this->date_pay)
			return date('d.m.Y H:i', $this->date_pay);
		else
			return '';
	}

	/**
	 * есть ли один кошелек на двух активных заявках
	 * проверить уведомить админа
	 */
	public static function checkActiveOrdersForBug()
	{
		$activeOrders = self::getActiveOrders();

		//массив кол-ва совпадений [['account_id'=>1]]
		$countArr = [];

		foreach($activeOrders as $activeOrder)
		{
			$orderAccounts = $activeOrder->orderAccounts;

			foreach($orderAccounts as $orderAccount)
			{
				$countArr[$orderAccount->account_id]++;

			}
		}

		foreach($countArr as $accountId=>$count)
		{
			if($count > 1)
			{
				$account = Account::modelByPk($accountId);
				$msg = "ВНИМАНИЕ!!! кошелек {$account->login} используется более чем в $count одновременных заявках, необходимо закрыть одну из них";
				toLogError($msg);
				User::noticeAdmin($msg);
			}
		}
	}
}