<?php

/**
 * Class Client
 * @property int id
 * @property string name
 * @property int date_add
 * @property string dateAddStr форматированная дата добавления
 * @property array calcList
 * @property ClientCalc lastCalc
 * @property ClientCalc firstCalc
 * @property FinansistOrder[] finOrdersInProcess
 * @property int newCalcAmount
 * @property ClientCommission commissionRule
 * @property bool global_fin
 * @property string globalFinStr
 * @property string description
 * @property bool is_active
 * @property bool isFinOrdersInProcess	сливается ли с клиента в данный момент
 * @property bool pick_accounts	можно ли юзерам этого кл брать кошельки
 * @property string pickAccountsStr
 * @property Account[] activeAccounts
 * @property ClientCalc lastControlCalc
 * @property array lastWallets	//кошельки с которых клиент кидал нам (из входящих транзакций за последние 30 дней)
 * @property string income_mode	режим приема у манов (кошельки или заявки)
 * @property array orderConfig	конфиг для заявочной системы
 * @property User[] users все пользователи текущего клиента
 * @property string calc_mode 'amount'|'order'
 * @property ManagerOrder[] notPaidOrders
 * @property ManagerOrder[] currentManagerOrders
 * @property Account[] enabledAccounts enabled=1
 * @property Account[] disabledAccounts enabled=0
 * @property bool calc_enabled включены ли расчеты(не отображать сумму к расчету у кл13 кл16)
 * @property string calcEnabledStr
 * @property string email мыло для подтверждения выводов фина
 * @property bool personal_proxy
 * @property bool allow_anonim	разрешает не идентить аноним кош
 * @property int wexCount
 * @property int qiwiNewCount
 * @property int pick_accounts_next_qiwi
 * @property int control_yandex_bit
 * @property string yandex_payment_type
 *
 */

class Client extends Model
{
	const SCENARIO_ADD = 'add';

	const INCOME_WALLET = 'wallet';
	const INCOME_ORDER = 'order';

	const CALC_MODE_AMOUNT = 'amount';
	const CALC_MODE_ORDER = 'order';

	const YANDEX_PAYMENT_TYPE_YM = 'ym';
	const YANDEX_PAYMENT_TYPE_CARD = 'card';
	const YANDEX_PAYMENT_TYPE_EXCHANGE = 'exchange';
	const YANDEX_PAYMENT_TYPE_MULTIPLE_EXCHANGE = 'exchange_multiple';
	const YANDEX_PAYMENT_TYPE_CARD_UNIVER = 'card_univer';
	const YANDEX_PAYMENT_TYPE_MEGAKASSA_FAKER = 'megakassa_faker';
	const YANDEX_PAYMENT_TYPE_MEGAKASSA_YANDEX = 'megakassa_yandex';
	const YANDEX_PAYMENT_TYPE_BITEXCOIN_YAD = 'bitexcoin_yad';
	const YANDEX_PAYMENT_TYPE_SIM_ACCOUNT = 'sim_account';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Имя',
			'description' => 'Описание',
			'date_add' => 'Дата добавления',
			'is_active' => 'Дата добавления',
			'calc_mode' => 'Тип расчета',
		);
	}

	public function tableName()
	{
		return '{{client}}';
	}

	public function beforeValidate()
	{
		$this->name = strip_tags($this->name);
		$this->description = strip_tags($this->description);

		return parent::beforeValidate();
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		$this->email = strip_tags($this->email);

		//способ оплаты по умолчанию
		if(!$this->yandex_payment_type)
			$this->yandex_payment_type = self::YANDEX_PAYMENT_TYPE_CARD;

		return parent::beforeSave();
	}

	public function rules()
	{
		return array(
			array('id', 'unique', 'className' => __CLASS__, 'attributeName' => 'id', 'on' => self::SCENARIO_ADD),
			array('name', 'length', 'max' => 20, 'allowEmpty' => false),
			array('name', 'unique', 'className' => __CLASS__, 'attributeName' => 'name', 'on' => self::SCENARIO_ADD),
			array('is_active, pick_accounts', 'safe'),
			array('global_fin', 'safe'),
			array('description', 'length', 'max' => 255),
			array('income_mode', 'in', 'range' => array_keys(self::incomeModeArr()), 'allowEmpty'=>true),
			array('calc_mode', 'in', 'range' => array_keys(self::calcModeArr()), 'allowEmpty'=>true),
			array('calc_mode', 'calcModeValidator'),
			array('calc_enabled', 'in', 'range' =>[0, 1], 'allowEmpty'=>true),
			['email', 'length', 'min'=>5, 'max'=>100, 'allowEmpty'=>true],
			['email', 'match', 'pattern'=>cfg('emailRegExp'), 'allowEmpty'=>true], // регулярка
			['email', 'unique', 'className'=>__CLASS__, 'attributeName'=>'email', 'allowEmpty'=>true],
			['personal_proxy, pick_accounts_next_qiwi, yandex_payment_type, control_yandex_bit, fake_btc', 'safe'],
		);
	}

	/**
	 * правила переключения между режимами
	 */
	public function calcModeValidator()
	{
		if($this->scenario != self::SCENARIO_ADD)
		{
			$prevMode = self::model()->findByPk($this->id)->calc_mode;

			if($prevMode != $this->calc_mode)
			{
				//переключение режима расчетов
				//- сумму к расчету
				//- активные заявки
				//- завершенные заявки


				$calcAmount = $this->getNewCalcAmount();

				if($calcAmount > 0 or $calcAmount < 0)
				{
					//проверять долги только при переключении с кошельков на заявки
					if($prevMode == self::CALC_MODE_AMOUNT)
					{
						$this->addError('calc_mode', 'для смены режима расчета, у '.$this->name.' не должно быть долгов');
						return false;
					}

				}

				$currentOrders = $this->getCurrentManagerOrders();


				if($currentOrders)
				{
					$this->addError('calc_mode', 'для смены режима расчета, у '.$this->name.' не должно быть текущих заявок у манагеров');
					return false;
				}

				$notPaidOrders = $this->getNotPaidOrders();

				foreach($notPaidOrders as $order)
				{
					if($order->amountIn > 0)
					{
						$this->addError('calc_mode', 'для смены режима расчета, у '.$this->name.' не должно быть неоплаченных заявок(например #'.$order->id.')');
						return false;
						break;
					}
				}
			}
		}
	}

	public function getDateAddStr()
	{
		return ($this->date_add) ? date('d.m.Y', $this->date_add) : '';
	}


	/**
	 * @return self[]
	 */
	public static function clientList()
	{
		return self::model()->findAll(array(
			'condition'=>"",
			'order'=>"`id` ASC",
		));
	}

	/**
	 *
	 * добавление нового клиента,
	 * привязки проксей,
	 * создание 3х манов, 1 фина, 1 контрола
	 * включение глобал фина
	 *
	 * @param array $params = array(
	 *  'id'=>25,
	 * 	'name'=>'sdfdsf',
	 * 	'description'=>'dsfdsfd',
	 * 	'managerCount'=>3,
	 * )
	 *
	 * @return bool;
	 */
	public static function add($params)
	{
		$managerCountMin = 1;
		$managerCountMax = 10;
		$managerCountDefault = 3;

		$client = new self;
		$client->scenario = self::SCENARIO_ADD;
		$client->id = $params['id'];
		$client->name = $params['name'];
		$client->description = $params['description'];
		$client->is_active = 1;
		$client->global_fin = 1;
		$client->pick_accounts = 1;

		$managerCount = ($params['managerCount']) ? $params['managerCount'] : $managerCountDefault;

		if($managerCount < $managerCountMin or $managerCount > $managerCountMax)
		{
			self::$lastError = 'неверное кол-во манагеров (от '.$managerCountMin.' до '.$managerCountMax.')';
			return false;
		}

		if($client->save())
		{
			//создать привязки прокси
			if(!AccountProxy::model()->find("`client_id`={$client->id}"))
			{
				$groups = Account::getGroupArr();

				$proxies = array_slice(Proxy::getProxies("`category`=''"), 0, 7);

				foreach($groups as $groupId=>$arr)
				{
					$accountProxy = new AccountProxy();
					$accountProxy->scenario = AccountProxy::SCENARIO_ADD;

					$proxy = $proxies[array_rand($proxies)];

					$accountProxy->client_id = $client->id;
					$accountProxy->group_id = $groupId;
					$accountProxy->proxy_id = $proxy->id;

					if(!$accountProxy->save())
					{
						self::$lastError = 'ошибка привязки прокси к клиенту groupId='.$groupId.', clientId='.$client->id;
						break;
					}
				}
			}

			if(!self::$lastError)
			{
				//сколько пользователей и какого типа(порядок регистрации важен)
				$registerArr = array(
					User::ROLE_CONTROL=>1,
					User::ROLE_FINANSIST=>1,
					User::ROLE_MANAGER=>$managerCount,
				);

				//создание пользователей(если юзеров нет)
				if(!User::model()->find("`client_id`={$client->id}"))
				{
					foreach($registerArr as $role=>$userCount)
					{
						for($i=1; $i<=$userCount; $i++)
						{
							$login = User::generateLogin($client->id, $role);

							if(!$login)
							{
								self::$lastError = 'ошибка генерации логина для '.$role;
								toLog(self::$lastError);
								return false;
							}

							$params = array(
								'login'=>$login,
								'role'=>$role,
								'client_id'=>$client->id,
							);

							if(!User::register($params, false))
							{
								self::$lastError = 'ошибка регистрации юзера '.Tools::arr2Str($params);
								toLog(self::$lastError);
								return false;
							}

							//логины-пароли юзеров, для вывода админу
							self::$msg .= $login.' '.User::$passGenerated.'<br>';
						}
					}
				}
			}

			return true;
		}
		else
			return false;

	}

	/**
	 * деактивирует клиента
	 * деактивирует его пользователей
	 * помечает enabled=0 все активные кошельки
	 * удаляет из бд нетронутые и выводит списком с паролем
	 * пишет результат в self::$msg
	 * @return bool
	 */
	public function disable()
	{
		if($this->is_active == 0)
		{
			self::$lastError = 'клиент уже был отключен';
			return false;
		}

		foreach($this->getActiveUsers() as $user)
		{
			if(User::disable($user->id))
				self::$msg .= '<br>пользователь отключен: '.$user->name;
			else
			{
				self::$lastError = 'ошибка отключения юзера '.$user->name;
				return false;
			}
		}

		$countDisableAccounts = 0;

		foreach($this->getEnabledAccounts() as $account)
		{
			Account::model()->updateByPk($account->id, ['enabled'=>0]);
			$countDisableAccounts ++;
		}

		self::$msg .= "<br>отключено $countDisableAccounts кошельков";

		$this->is_active = 0;

		return $this->save();
	}

	public function enable()
	{
		if($this->is_active == 1)
		{
			self::$lastError = 'клиент уже был включен';
			return false;
		}

		foreach($this->getDisabledUsers() as $user)
		{
			if(User::enable($user->id))
				self::$msg .= '<br>пользователь включен: '.$user->name;
			else
			{
				self::$lastError = 'ошибка включения юзера '.$user->name;
				return false;
			}
		}

		$countEnableAccounts = 0;

		foreach($this->getDisabledAccounts() as $account)
		{
			Account::model()->updateByPk($account->id, ['enabled'=>1]);
			$countEnableAccounts ++;
		}

		self::$msg .= "<br>включено $countEnableAccounts кошельков";

		$this->is_active = 1;

		self::$msg .= '<br>Рекомендуется сменить пароли у включенных юзеров';

		return $this->save();
	}

	/**
	 * для селекта
	 *
	 */
	public static function getArr()
	{
		$result = array();

		foreach(self::getActiveClients() as $model)
		{
			$result[$model->id] = $model->name;
		}

		return $result;
	}

	/**
	 * @return self[]
	 */
	public static function getModels()
	{
		return self::model()->findAll([
			'order'=>"`id` ASC",
		]);
	}


	/*
	 * клиенты у которых включен глобал_фин
	 * для селекта в добавлении заявки фина
	 *
	 */
	public static function getArrWithGlobalFin()
	{
		$result = array();

		foreach(self::getActiveClients() as $model)
		{
			if($model->global_fin)
				$result[$model->id] = $model->name;
		}

		return $result;
	}

	public function getIsActiveStr()
	{
		if($this->is_active)
			return '<font color="green">да</font>';
		else
			return '<font color="red">нет</font>';
	}

	/**
	 * @return self[]
	 */
	public static function getActiveClients()
	{
		return self::model()->findAll(array(
			'condition'=>"`is_active`=1",
			'order'=>"`id`",
		));
	}

	/**
	 * сбор статистики по клиентам для админа
	 * @return array
	 */
	public static function getStats()
	{
		$result = array();

		$clients = self::clientList();

		$groupArr = Account::getGroupArr();

		foreach($clients as $client)
		{
			//данные клиента
			$stats = array(
				'model'=>$client,
			);

			//количество свободных всего
			$freeInAccounts = Account::getFreeInAccounts($client->id, '', true);
			$stats['countFreeInAccounts'] = count($freeInAccounts);

			if(count($freeInAccounts) < cfg('in_warn_count'))
				$stats['countFreeInAccountsWarn'] = true;

			//количество свободных зеленых
			$accounts = Account::getFreeInAccounts($client->id, 'full', true);
			$stats['countFreeInAccountsFull'] = count($accounts);

			//свободных с апи токеном
			$accounts = Account::getFreeInAccounts($client->id, 'apiHalf', true);
			$stats['countFreeInAccountsApi'] = count($accounts);
			$accounts = Account::getFreeInAccounts($client->id, 'apiFull', true);
			$stats['countFreeInAccountsApi'] += count($accounts);

			//лимиты входящих
			$accounts =  array_merge(
				Account::getCurrentInAccounts($client->id, false),
				$freeInAccounts
			);

			$stats['limitIn'] = $client->getLimitAccounts($accounts);

			if($stats['limitIn'] < cfg('in_warn_limit'))
				$stats['limitInWarn'] = true;

			foreach($groupArr as $groupId=>$group)
			{
				//количество транзитных
				$accounts = Account::getTransitAccounts($client->id, $groupId, true);
				$stats['countTransitAccounts'][$groupId] = count($accounts);

				if(count($accounts) < cfg('transit_warn_count'))
					$stats['countTransitAccountsWarn'][$groupId] = true;

				//количество исходящих
				$accounts = Account::getOutAccounts($client->id, $groupId, true);

				$stats['countOutAccounts'][$groupId] = count($accounts);

				if(count($accounts) < cfg('out_warn_count'))
					$stats['countOutAccountsWarn'][$groupId] = true;

				//лимиты транзитных
				$stats['limitTransit'][$groupId] = $client->getLimitTransit($groupId, true);

				if($stats['limitTransit'][$groupId] < cfg('transit_warn_limit'))
					$stats['limitTransitWarn'][$groupId] = true;

				//лимиты исходящих
				$stats['limitOut'][$groupId] = $client->getLimitOut($groupId, true);

				if($stats['limitOut'][$groupId] < cfg('out_warn_limit'))
					$stats['limitOutWarn'][$groupId] = true;
			}

			$result[$client->id] = $stats;
		}


		return $result;
	}

	/**
	 * сбор статистики по клиентам для админа
	 * @return array
	 */
	public static function getStatsTest()
	{
		$result = array();

		$clients = self::clientList();

		$groupArr = Account::getGroupArr();
		$minBalance = cfg('min_balance');
		$inLimitMin = config('in_max_balance');

		foreach($clients as $client)
		{
			//данные клиента
			$stats = array(
				'model'=>$client,
				'fullGroups'=>[],	//группы в которых есть хотябы 1 зеленый
			);

			//работоспособные кошельки клиента
			$accounts = $client->getWorkAccounts();

			foreach($accounts as $account)
			{
				if($account->type == Account::TYPE_IN)
				{
					//свободные
					if(
						$account->user_id == 0
						and $account->balance < $minBalance
						and $account->limit_in >= $inLimitMin
					)
					{
						$stats['countFreeInAccounts'] ++;

						if($account->status == Account::STATUS_FULL)
							$stats['countFreeInAccountsFull'] ++;

						if($account->api_token)
							$stats['countFreeInAccountsApi'] ++;
					}

					$stats['limitIn'] += $account->limit_in;
				}
				elseif($account->type == Account::TYPE_TRANSIT)
				{
					$stats['countTransitAccounts'][$account->group_id] ++;
					$stats['limitTransit'][$account->group_id] += $account->limit_in;
				}
				elseif($account->type == Account::TYPE_OUT)
				{
					$stats['countOutAccounts'][$account->group_id] ++;
					$stats['limitOut'][$account->group_id] += $account->limit_in;;
				}

				if($account->status == Account::STATUS_FULL)
					$stats['fullGroups'][$account->group_id] = true;

			}

			//пишем нули
			if(!isset($stats['countFreeInAccounts']))
				$stats['countFreeInAccounts'] = 0;

			if(!isset($stats['countFreeInAccountsFull']))
				$stats['countFreeInAccountsFull'] = 0;

			if(!isset($stats['countFreeInAccountsApi']))
				$stats['countFreeInAccountsApi'] = 0;


			if(!isset($stats['limitIn']))
				$stats['limitIn'] = 0;

			if($stats['countFreeInAccounts'] < cfg('in_warn_count'))
				$stats['countFreeInAccountsWarn'] = true;

			if($stats['limitIn'] < cfg('in_warn_limit'))
				$stats['limitInWarn'] = true;



			foreach($groupArr as $groupId=>$arr)
			{
				//пишем нули
				if(!isset($stats['countTransitAccounts'][$groupId]))
					$stats['countTransitAccounts'][$groupId] = 0;

				if(!isset($stats['limitTransit'][$groupId]))
					$stats['limitTransit'][$groupId] = 0;

				if(!isset($stats['countOutAccounts'][$groupId]))
					$stats['countOutAccounts'][$groupId] = 0;

				if(!isset($stats['limitOut'][$groupId]))
					$stats['limitOut'][$groupId] = 0;

				//предупреждения
				if($stats['countTransitAccounts'][$groupId] < cfg('transit_warn_count'))
					$stats['countTransitAccountsWarn'][$groupId] = true;

				if($stats['limitTransit'][$groupId] < cfg('transit_warn_limit'))
					$stats['limitTransitWarn'][$groupId] = true;

				if($stats['countOutAccounts'][$groupId] < cfg('out_warn_count'))
					$stats['countOutAccountsWarn'][$groupId] = true;

				if($stats['limitOut'][$groupId] < cfg('out_warn_limit'))
					$stats['limitOutWarn'][$groupId] = true;
			}

			$result[$client->id] = $stats;
		}

		return $result;
	}

	/*
	 * входящий лимит IN-кошельков
	 * складывается из остатка лимита текущих и свободных
	 */
	public function getLimitAccounts($accounts)
	{
		$result = 0;

		foreach($accounts as $account)
		{
			//у некоторых минусовые лимиты
			if($account->limit_in > 0)
				$result += $account->limit_in;
		}

		return $result;
	}

	/*
	 * общий оставшийся лимит транзитных кошельков
	 */
	public function getLimitTransit($groupId, $withNotChecked = false)
	{
		$result = 0;

		$accounts = Account::getTransitAccounts($this->id, $groupId, $withNotChecked);

		foreach($accounts as $account)
		{
			//у некоторых минусовые лимиты
			if($account->limit_in > 0)
				$result += $account->limit_in;
		}

		return $result;
	}

	/*
	 * общий оставшийся лимит Out - кошельков
	 */
	public function getLimitOut($groupId, $withNotChecked = false)
	{
		$result = 0;

		$accounts = Account::getOutAccounts($this->id, $groupId, $withNotChecked);

		foreach($accounts as $account)
		{
			//у некоторых минусовые лимиты
			if($account->limit_in > 0)
				$result += $account->limit_in;
		}

		return $result;
	}

	/*
	 * суммарный баланс всех кошельков клиента
	 */
	public static function getSumOutBalance($clientId=false, $withCache = false)
	{
		$cacheName = "SumOutBalance_{$clientId}";
		$cacheTime = 300;

		if($withCache)
		{
			if($balance = Yii::app()->cache->get($cacheName))
				return $balance;
		}

		$balance = 0;

		$clientCond = '';

		if($clientId)
			$clientCond = "AND `client_id`='{$clientId}'";

		$minBalance = cfg('min_balance');

		//тока кошельки которые проверялись последние 2 дня
		$dateCheck = time() - 3600*24*2;

		$models = Account::model()->findAll(array(
			'select'=>"`balance`",
			'condition'=>"
				`error`=''
				AND `date_used`=0
				AND `date_check`>$dateCheck
				AND `enabled`=1
				AND `balance`>$minBalance $clientCond
			",
		));

		foreach($models as $model)
			$balance += $model->balance;

		Yii::app()->cache->set($cacheName, $balance, $cacheTime);

		return $balance;
	}

	/**
	 *
	 * сумма накоплений на каждой цепочке
	 * @param int|null $clientId
	 * @param bool $withCache
	 * @return array
	 * если $clientId ['group id1'=>300, 'group id2'=>4000, ...]
	 * если !$clientId ['client Id'=>['group id1'=>300, 'group id2'=>4000, ...], ...]
	 */
	public static function getSumOutBalanceWithGroups($clientId = null, $withCache = false)
	{
		$cacheName = "sumOutBalanceWithGroups_{$clientId}";
		$cacheTime = 300;

		if($withCache)
		{
			if($result = Yii::app()->cache->get($cacheName))
				return $result;
		}

		$result = array();

		$clientCond = '';

		if($clientId)
			$clientCond = " AND `client_id`='{$clientId}'";

		$minBalance = cfg('min_balance');

		$models = Account::model()->findAll(array(
			'select'=>"`balance`, `group_id`, `client_id`",
			'condition'=>"
				`balance`>$minBalance
				AND `enabled`=1
				AND `error`='' $clientCond
			",
			'order'=>"`client_id`, `group_id`",
		));

		/**
		 * @var Account[] $models
		 */

		foreach(Account::getGroupArr() as $groupId=>$arr)
		{
			foreach($models as $model)
			{
				if(!isset($result[$groupId]) and $clientId)
					$result[$groupId] = 0;
				elseif(!isset($result[$model->client_id][$groupId]) and !$clientId)
					$result[$model->client_id][$groupId] = 0;

				if(
					$model->client->is_active
					and $model->client->global_fin
					and $model->group_id == $groupId
				)
				{

					if($clientId)
						$result[$model->group_id] += $model->balance;
					else
						$result[$model->client_id][$model->group_id] += $model->balance;
				}
			}
		}

		Yii::app()->cache->set($cacheName, $result, $cacheTime);

		return $result;
	}

	/**
	 * выводы клФина с $timestampStart
	 * @return FinansistOrder[]
	 */
	public function getClFinOrders($timestampStart)
	{
		$orders = FinansistOrder::model()->findAll("`client_id`={$this->id} AND `date_add`>=$timestampStart");

		foreach($orders as $key=>$order)
		{
			if($order->user->role == User::ROLE_GLOBAL_FIN)
				unset($orders[$key]);
		}

		return $orders;
	}

	/**
	 * выводы глобалФина с $timestampStart
	 * @return FinansistOrder[]
	 */
	public function getGlobalFinOrders($timestampStart)
	{
		$orders = FinansistOrder::model()->findAll("`client_id`={$this->id} AND `date_add`>=$timestampStart");

		foreach($orders as $key=>$order)
		{
			if($order->user->role != User::ROLE_GLOBAL_FIN)
				unset($orders[$key]);
		}

		return $orders;
	}

	/*
	 * есть ли текущие платежи фина или глобалфина в ожидании
	 */
	public function getIsFinOrdersInProcess()
	{
		if(FinansistOrder::model()->count("`client_id`={$this->id} AND `status`='".FinansistOrder::STATUS_WAIT."' AND `for_cancel`=0"))
			return true;
		else
			return false;
	}

	/**
	 * возвращает все FinansistOrder клиента в процессе
	 * @return FinansistOrder[]
	 *
	 * используется в ClientCalc - чтобы узнать сливает ли клФин сейчас
	 */
	public function getFinOrdersInProcess()
	{
		return FinansistOrder::model()->findAll("`client_id`={$this->id} AND `status`='".FinansistOrder::STATUS_WAIT."'");
	}

	/**
	 * возвращает все FinansistOrder клиента
	 * @return FinansistOrder[]
	 */
	public function getFinOrdersDone()
	{
		return FinansistOrder::model()->findAll("`client_id`={$this->id} AND `status`='".FinansistOrder::STATUS_WAIT."'");
	}

	/**
	 * возвращает сумму прихода на входящие
	 *
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return float|int
	 */
	public function statsIn($timestampStart, $timestampEnd)
	{
		$result = 0;

		if($timestampStart and $timestampEnd)
		{
			$tblAccount = Account::model()->tableSchema->name;
			$accountCond = "`account_id` IN(SELECT `id` FROM `".$tblAccount."` WHERE `type`='".Account::TYPE_IN."' AND `client_id`={$this->id} AND `date_check`>=$timestampStart)";

			$models = Transaction::model()->findAll(array(
				'select'=>'amount',
				'condition'=>"
					`type`='".Transaction::TYPE_IN."'
					AND `status`='".Transaction::STATUS_SUCCESS."'
					AND `date_add`>=$timestampStart and `date_add`<$timestampEnd
					AND $accountCond
					 ",
				'order'=>"`id` DESC",
			));

			/**
			 * @var Transaction[] $models
			 */

			foreach($models as $model)
				$result += $model->amount;

		}

		return $result;
	}

	/**
	 * возвращает сумму выхода с исходящих
	 *
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return float
	 */
	public function statsOut($timestampStart, $timestampEnd)
	{
		$result = 0;

		if($timestampStart and $timestampEnd)
		{
			$tblAccount = Account::model()->tableSchema->name;
			$accountCond = "`account_id` IN(SELECT `id` FROM `".$tblAccount."` WHERE `type`='".Account::TYPE_OUT."' AND `client_id`={$this->id} AND `date_check`>=$timestampStart)";

			$models = Transaction::model()->findAll(array(
				'condition'=>"
					`type`='".Transaction::TYPE_OUT."'
					AND `status`='".Transaction::STATUS_SUCCESS."'
					AND `date_add`>=$timestampStart and `date_add`<$timestampEnd
					AND $accountCond
					 ",
				'order'=>"`id` DESC",
			));

			if($models)
			{
				foreach($models as $model)
				{
					$result += $model->amount;
				}
			}
		}

		return $result;
	}

	/*
	 * сколько ушло на комиссию на всех кошельках за период
	 */
	public function commissionAmount($timestampStart, $timestampEnd)
	{
		$result = 0;

		if($timestampStart and $timestampEnd)
		{

			$tblAccount = Account::model()->tableSchema->name;

			$accountCond = "`account_id` IN(SELECT `id` FROM `".$tblAccount."` WHERE `client_id`='{$this->id}' AND `date_check`>$timestampStart)";

			$models = Transaction::model()->findAll(array(
				'condition'=>"
					`type`='".Transaction::TYPE_OUT."'
					AND `status`='".Transaction::STATUS_SUCCESS."'
					AND `date_add`>=$timestampStart and `date_add`<$timestampEnd
					AND `commission`>0
					AND $accountCond
					 ",
				'order'=>"`id` DESC",
			));

			if($models)
			{
				foreach($models as $model)
				{
					$result += $model->commission;
				}
			}
		}

		return $result;
	}

	/*
	 * суммарный баланс незаблокированных входящих и транзитных (деньги которые еще не перевелись)
	 */
	public function processAmount()
	{
		$result = 0;
		$minBalance = cfg('min_balance');

		$models = Account::model()->findAll("
			`client_id`='{$this->id}'
			AND `type` IN('".Account::TYPE_IN."','".Account::TYPE_TRANSIT."')
			AND `balance`>$minBalance
			AND `enabled`=1
			AND `error`=''
		");

		foreach($models as $model)
			$result += $model->balance;

		return $result;
	}

	/*
	 * суммарный баланс заблокированных по `date_check`
	 *
	 */
	public function banAmount($timestampStart, $timestampEnd)
	{
		$result = 0;
		$minBalance = cfg('min_balance');

		$timestampStart = $timestampStart*1;
		$timestampEnd = $timestampEnd*1;

		$models = Account::model()->findAll([
			'select' => "`balance`",
			'condition' => "
				`client_id`='{$this->id}'
				AND `balance`>$minBalance
				AND `error`!=''
				AND `enabled`=1
				AND `date_check` >= $timestampStart AND `date_check` < $timestampEnd
			",
		]);

		foreach($models as $model)
			$result += $model->balance;

		return $result;
	}

	/**
	 * стата для   globalFin
	 *  return array(
	 * 		'clients'=>array(
	 * 		'client_id'=>array(),
	 * 		'client_id'=>array(),
	 * 		),
	 * 	'allAmount'=>array(
	 * 		''=>..
	 * 		),
	 * 'errorAccounts'=>array(
	 * 		...
	 *		),
	 * )
	 * если не указан $clientId, то получать всех активных
	 *
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param int $clientId
	 * @return self[]
	 */
	public static function globalStats($timestampStart, $timestampEnd, $clientId = 0)
	{
		$result = array();

		$clients = Client::getActiveClients();

		foreach($clients as $client)
		{
			if($clientId and $client->id != $clientId)
				continue;

			$bonusArr = ClientCommission::getBonus($client->id);

			$commissionAmount = $client->commissionAmount($timestampStart, $timestampEnd);
			$statsIn = $client->statsIn($timestampStart, $timestampEnd);

			$coupons = Coupon::getModels($timestampStart, $timestampEnd, 0, $client->id);
			$couponStats = Coupon::getStats($coupons);

			$yandexPayments = TransactionWex::getModels($timestampStart, $timestampEnd, 0, $client->id);
			$yandexStats = TransactionWex::getStats($yandexPayments);

			$newYandexPayments = NewYandexPay::getModels($timestampStart, $timestampEnd, 0, $client->id, true);
			$newYandexStats = NewYandexPay::getStats($newYandexPayments);

			$qiwiNewPayments = QiwiPay::getModels($timestampStart, $timestampEnd, 0, $client->id);
			$qiwiNewStats = QiwiPay::getStats($qiwiNewPayments);

			$yandexAccountStats = YandexTransaction::getStats($timestampStart, $timestampEnd, $client->id);

			//qiwi merchant adgroup
			$qiwiMerchStats = MerchantTransaction::getStats($timestampStart, $timestampEnd, $client->id, 0, ['qiwi_wallet', 'qiwi_card']);

			//yad merchant adgroup
			$yadMerchStats = MerchantTransaction::getStats($timestampStart, $timestampEnd, $client->id, 0, ['yandex']);

			//walletSvoe
			$walletSPayments = WalletSTransaction::getModels($timestampStart, $timestampEnd, $client->id, 0, 'successIn');
			$walletSStats = WalletSTransaction::getStats($walletSPayments);

			//Sim
			$simStats = SimTransaction::getStats($timestampStart, $timestampEnd, $client->id);

			//RiseX
			$riseXStats = RisexTransaction::getStats($timestampStart, $timestampEnd, $client->id);

			//exchangeYadBitPayments
			$exchangeYadBitPayments = ExchangeYadBit::getModels($timestampStart, $timestampEnd, 0, $client->id);
			$exchangeYadBitStats = ExchangeYadBit::getStats($exchangeYadBitPayments);

			$stats = [
				'model'=>$client,
				'balance'=>self::getSumOutBalance($client->id),	// текущий баланс всех незалоченых кошельков
				'finOrderInProcess'=>$client->isFinOrdersInProcess, //идет ли слив с out-кошельков
				'inAmount'=>$statsIn,	//сколько пришло на входящие
				'outAmount'=>$client->statsOut($timestampStart, $timestampEnd),	//сколько ушло с исходящих
				//'processAmount'=>$client->processAmount(),
				'commissionAmount'=>$commissionAmount,
				'commissionPercent'=> ($statsIn > 0) ? floorAmount($commissionAmount / $statsIn * 100 , 2) : 0,
				'banAmount'=>$client->banAmount($timestampStart, $timestampEnd),	//сумма балансов всех забаненых кошельков
				'calcAmount'=>0,
				'couponAmount'=>$couponStats['amount'],	//сколько пришло кодами
				'yandexAmount'=>$yandexStats['amount'],	//сколько пришло яндексом
				'newYandexAmount'=>$newYandexStats['amount'],	//сколько пришло яндексом
				'qiwiNewAmount'=>$qiwiNewStats['amount'],	//сколько пришло по киви
				'yandexAccountAmount'=>$yandexAccountStats['amountIn'],	//сколько пришло по киви
				'qiwiMerchAmount'=>$qiwiMerchStats['amountIn'],	//сколько пришло по киви мерчанту
				'yadMerchAmount'=>$yadMerchStats['amountIn'],	//сколько пришло по киви мерчанту
				'walletSAmount'=>$walletSStats['amount'],	//сколько пришло по киви мерчанту
				'simAmount'=>$simStats['amountIn'],
				'riseXAmount'=>$riseXStats['amountIn'],
				'exchangeYadBitAmount'=>$exchangeYadBitStats['amount'],
				'bonus'=>$bonusArr,
			];


			if($client->lastCalc)
				$stats['calcAmount'] = $client->getNewCalcAmount(true);


			$result['clients'][$client->id] = $stats;

			$result['allAmount']['balance'] += $stats['balance'];
			$result['allAmount']['finOrderInProcess'] += $stats['finOrderInProcess'];
			$result['allAmount']['inAmount'] += $stats['inAmount'];
			$result['allAmount']['outAmount'] += $stats['outAmount'];
			//$result['allAmount']['processAmount'] += $stats['processAmount'];
			$result['allAmount']['commissionAmount'] += $stats['commissionAmount'];
			$result['allAmount']['banAmount'] += $stats['banAmount'];
			$result['allAmount']['calcAmount'] += $stats['calcAmount'];
			$result['allAmount']['couponAmount'] += $stats['couponAmount'];
			$result['allAmount']['yandexAmount'] += $stats['yandexAmount'];
			$result['allAmount']['newYandexAmount'] += $stats['newYandexAmount'];
			$result['allAmount']['qiwiNewAmount'] += $stats['qiwiNewAmount'];
			$result['allAmount']['yandexAccountAmount'] += $stats['yandexAccountAmount'];
			$result['allAmount']['qiwiMerchAmount'] += $stats['qiwiMerchAmount'];
			$result['allAmount']['yadMerchAmount'] += $stats['yadMerchAmount'];
			$result['allAmount']['walletSAmount'] += $stats['walletSAmount'];
			$result['allAmount']['exchangeYadBitAmount'] += $stats['exchangeYadBitAmount'];
			$result['allAmount']['simAmount'] += $stats['simAmount'];
			$result['allAmount']['riseXAmount'] += $stats['riseXAmount'];
		}

		if($result['allAmount']['inAmount'])
			$result['allAmount']['commissionPercent'] = floorAmount($result['allAmount']['commissionAmount'] / $result['allAmount']['inAmount'] * 100, 2);
		else
			$result['allAmount']['commissionPercent'] = 0;

		//проблемные коешльки
		$result['errorAccounts'] = Account::getErrorAccounts($timestampStart, $timestampEnd);


		return $result;
	}

	//считает статистику по транзакциям
	public static function globalStatsNew($timestampStart, $timestampEnd, $clientId = 0)
	{
		$newAlgTimestamp = strtotime(cfg('newAlgStatsDate'));

		if($timestampStart < $newAlgTimestamp)
			return false;


	}

	/*
	 * текущие кошельки менеджеров
	 * return array(
	 * 	'clients'=>array(
	 * 		'id'=>array(
	 * 			'model'=>obj,
	 	* 		'accounts'=>array(),
	 * 		),
	 * 		...
	 *
	 * 	),
	 * 	'slowCheckCount'=>10,	//давно не проверялись
	 *
	 * )
	 */
	public static function getCurrentInAccounts($clientId = 0, $withError=true)
	{
		$result = array();

		$clients = Client::getActiveClients();

		foreach($clients as $client)
		{
			if($clientId and $client->id != $clientId)
				continue;

			$result['clients'][$client->id]['model'] = $client;
			$result['clients'][$client->id]['accounts'] = Account::getCurrentInAccounts($client->id, true);

			foreach($result['clients'][$client->id]['accounts'] as $account)
			{
				if($account->isSlowCheck)
					$result['clowCheckCount']++;
			}
		}

		return $result;
	}

	public static function enableGlobalFin($clientId, $userId)
	{
		$user = User::getUser($userId);

		if($client = self::model()->findByPk($clientId) and $client->is_active and !$client->global_fin)
		{
			$client->global_fin = 1;

			if($client->save())
			{
				GlobalFinLog::add('включен GF на cl'.$client->name, $user->id);
				return true;
			}
			else
				return false;
		}
		else
			self::$lastError = 'клиент не найден либо GF уже включен';
	}

	public static function disableGlobalFin($clientId, $userId)
	{
		$user = User::getUser($userId);

		if($client = self::model()->findByPk($clientId) and $client->is_active and $client->global_fin)
		{
			$client->global_fin = 0;

			if($client->save())
			{
				GlobalFinLog::add('отключен GF на cl'.$client->name, $user->id);
				return true;
			}
			else
				return false;
		}
		else
			self::$lastError = 'клиент не найден либо GF уже отключен';
	}

	/**
	 * @param int $id
	 * @return self
	 */
	public static function modelByPk($id)
	{
		return self::model()->findByPk($id);
	}

	/*
	 * список расчетов клиента
	 * сортировка по дате(по убыванию)
	 */
	public function getCalcList()
	{
		return ClientCalc::model()->findAll(array(
			'condition'=>"`client_id`={$this->id}",
			'order'=>"`date_add` DESC",
		));
	}

	/**
	 * список расчетов по порядку добавления
	 * @return ClientCalc[]
	 *
	 */
	public function getCalcArr()
	{
		return ClientCalc::model()->findAll(array(
			'condition'=>"`client_id`={$this->id}",
			'order'=>"`id` ASC",
		));
	}

	/**
	 * получить последний расчет клиента
	 * @return ClientCalc
	 */
	public function getLastCalc()
	{
		return ClientCalc::model()->find(array(
			'condition'=>"`client_id`={$this->id} AND `status`!='".ClientCalc::STATUS_CANCEL."'",
			'order'=>"`id` DESC",
		));
	}

	public function getFirstCalc()
	{
		return ClientCalc::model()->find(array(
			'condition'=>"`client_id`={$this->id} AND `status`!='".ClientCalc::STATUS_CANCEL."'",
			'order'=>"`id` ASC",
		));
	}

	public function cancelLastCalc()
	{
		$interval = 120;	//минимальный интервал удаления последнего рассчета -  для защиты от повторного нажатия

		if($lastCalc = $this->getLastCalc())
		{
			//пауза в 5 минут для предотвращения удалений 2х подряд
			$last = time() - config('clientLastCalcDeleteTimestamp');

			if($last < $interval)
			{
				self::$lastError = 'слишком частая отмена, подождите: '.($interval - $last).' сек';
				return false;
			}

			if($lastCalc->cancel())
			{
				//админ может удалять без ограничений
				if($lastCalc->user->role != User::ROLE_ADMIN)
					config('clientLastCalcDeleteTimestamp', time());

				return true;
			}
			else
				self::$lastError = ClientCalc::$lastError;
		}
		else
			self::$lastError = 'расчет не найден';

		return false;
	}

	public function deleteLastCalc()
	{
		$interval = 120;	//минимальный интервал удаления последнего рассчета -  для защиты от повторного нажатия

		if($lastCalc = ClientCalc::model()->find(array(
			'condition'=>"`client_id`={$this->id}",
			'order'=>"`id` DESC",
		)))
		{
			/**
			 * @var ClientCalc $lastCalc
			 */
			//пауза в 5 минут для предотвращения удалений 2х подряд
			$last = time() - config('clientLastCalcDeleteTimestamp');

			if($last < $interval)
			{
				self::$lastError = 'слишком частое удаление, подождите: '.($interval - $last).' сек';
				return false;
			}

			if($lastCalc->deleteCalc())
			{
				//админ может удалять без ограничений
				if($lastCalc->user->role != User::ROLE_ADMIN)
					config('clientLastCalcDeleteTimestamp', time());

				toLogRuntime("удален расчет: ".Tools::arr2Str($lastCalc->attributes));

				return true;
			}
			else
				self::$lastError = ClientCalc::$lastError;
		}
		else
			self::$lastError = 'расчет не найден';

		return false;
	}


	/**
	 * вся накопленная и еще не отданная сумма
	 * @param bool $fromCache
	 * @return int
	 */
	public function getNewCalcAmount($fromCache = false)
	{
		$recalc = $this->recalc(null, $fromCache);

		$amount = $recalc['amountRub'];

		return round($amount);
	}

	public function getDescriptionStr()
	{
		return Tools::shortText($this->description, 20);
	}

	/**
	 * модель комиссии
	 */
	public function getCommissionRule()
	{
		if(
			$model = ClientCommission::model()->findByAttributes(array('client_id'=>$this->id))
			and $model->is_active
		)
			return $model;
		else
			return ClientCommission::model()->findByAttributes(array('client_id'=>0));	//правило по-умолчанию
	}

	public function getGlobalFinStr()
	{
		if($this->global_fin)
			return '<font color="green">включен</font>';
		else
			return '<font color="red">отключен</font>';
	}

	/**
	 * @param int $id
	 * @return self
	 */
	public static function getModel($id)
	{
		return self::model()->findByPk($id);
	}

	/**
	 * отменяет все активные выводы с данного клиента(если клиент активен и на нем включен гф)
	 * логирует в таблицу глобавлфина
	 * выдает список отмененных платежей
	 * @param int $clientId
	 * @param int $userId
	 * @return bool
	 */
	public static function cancelFinOrders($clientId, $userId)
	{
		$cancelArr = array();

		self::$someData['msg'] = '';

		if(
			$client = self::modelByPk($clientId)
			and $client->global_fin	//только если включен глобалфин на клиенте
			and $user = User::getUser($userId)
			and $user->role === User::ROLE_GLOBAL_FIN
			and $user->is_wheel	//отменять может только рулевой
		)
		{
			if($orders = $client->finOrdersInProcess)
			{
				//print_r($orders);
				//die('test passed, count for cancel: '.count($orders));

				foreach($orders as $order)
				{
					if(FinansistOrder::forCancel($order->id, $user->id))
						$cancelArr[] = $order->id;
					else
						self::$lastError .= FinansistOrder::$lastError.', ';
				}

				if($cancelArr)
				{
					self::$someData['msg'] = 'отмена заявок Cl'.$clientId.': '.implode(', ', $cancelArr);
					GlobalFinLog::add(self::$someData['msg'], $user->id);
					return true;
				}
				else
					return false;
			}
			else
				self::$lastError = 'нет текущих сливов у Client'.$clientId;
		}
		else
			self::$lastError = 'отменить все сливы может только рулевой';


		return false;
	}

	/**
	 * обнуляет все данные клиента, меняет пароли пользователей
	 * //todo добавить проверку на последний входящий платеж(если был менее 2х суток назад то запрещать удаление)
	 * @param int $clientId
	 * @param string $confirmPass
	 * @return bool
	 */
	public static function reset($clientId, $confirmPass)
	{
		$clientId *= 1;

		if(!$client = self::modelByPk($clientId))
		{
			self::$lastError = 'client not found';
			return false;
		}

		if(md5($confirmPass) !== cfg('adminConfirmPassHash'))
		{
			self::$lastError = 'wrong pass';
			return false;
		}

		if(md5($confirmPass) !== cfg('adminConfirmPassHash'))
		{
			self::$lastError = 'wrong pass';
			return false;
		}

		$accounts = Account::model()->findAll(array(
			'condition'=>"`client_id`={$client->id}",
			'select'=>"`id`, `login`",
		));
		/**
		 * @var Account[] $accounts
		 */

		$msg = '';

		if($accounts)
		{
			foreach($accounts as $account)
			{
				if(
					!$account->error
					and !$account->user_id
					and $account->limit_in >= 190000
					and $account->balance <= 2
				)
					continue;

				//удалить платежи
				$transactions = Transaction::model()->findAll(array(
					'condition'=>"`account_id`={$account->id}",
					'select'=>"`id`"
				));

				if($transactions)
				{
					foreach($transactions as $transaction)
					{
						if(!$transaction->delete())
						{
							self::$lastError = 'error delete trans: ID='.$transaction->id;
							self::$msg = $msg;
							return false;
						}
					}
				}

				if(!$account->delete())
				{
					self::$lastError = 'error delete account: ID='.$account->id;
					self::$msg = $msg;
					return false;
				}

				$msg .= "<br>delete account: ".$account->login;

			}
		}
		else
			self::$msg .= "<br>no wallets";


		$clientCalcArr = ClientCalc::model()->findAll("`client_id`={$client->id}");

		/**
		 * @var ClientCalc[] $clientCalcArr
		 */

		foreach($clientCalcArr as $clientCalc)
		{
			if($clientCalc->delete())
				$msg .= "<br>delete clientCalc: ID=".$clientCalc->id;
		}

		$finansistOrderArr = FinansistOrder::model()->findAll("`client_id`={$client->id}");

		/**
		 * @var FinansistOrder[] $finansistOrderArr
		 */

		foreach($finansistOrderArr as $finansistOrder)
		{
			if($finansistOrder->delete())
				$msg .= "<br>delete finOrder: ID=".$finansistOrder->id;
		}

		$users = User::model()->findAll("`client_id`={$client->id}");

		/**
		 * @var User[] $users
		 */

		foreach($users as $user)
		{
			if(User::changePass($user->id))
			{
				$msg .= '<br>'.User::$msg;
			}
			else
			{
				self::$lastError = 'error change pass for user ID='.$user->id;
				self::$msg = $msg;
				return false;
			}
		}

		self::$msg = $msg;

		return true;
	}

	/**
	 * вкл-откл выдачу кошельков у манагеров на данном клиенте
	 * @param int $clientId
	 * @param bool $value
	 * @return bool
	 */
	public static function switchPickAccounts($clientId, $value)
	{
		$clientId *= 1;
		$value *= 1;

		if($client = self::model()->findByPk($clientId))
		{
			/**
			 * @var self $client
			 */

			$client->pick_accounts = $value;

			if($client->save())
			{
				if($value == 1)
					self::$msg = 'выдача кошельков для клиента Client'.$clientId.' включена';
				else
					self::$msg = 'выдача кошельков для клиента Client'.$clientId.' Отключена';

				return true;
			}
		}
		else
		{
			self::$lastError = 'клиент не найден';
			return false;
		}
	}

	/**
	 * вкл-откл выдачу кошельков у манагеров на данном клиенте
	 * @param int $clientId
	 * @param bool $value
	 * @return bool
	 */
	public static function switchPickAccountsNextQiwi($clientId, $value)
	{
		$clientId *= 1;
		$value *= 1;

		if($client = self::model()->findByPk($clientId))
		{
			/**
			 * @var self $client
			 */

			$client->pick_accounts_next_qiwi = $value;

			if($client->save())
			{
				if($value == 1)
					self::$msg = 'выдача кошельков по API для клиента Client'.$clientId.' включена';
				else
					self::$msg = 'выдача кошельков по API  для клиента Client'.$clientId.' Отключена';

				return true;
			}
		}
		else
		{
			self::$lastError = 'клиент не найден';
			return false;
		}
	}

	/**
	 * вкл-откл работу с обменником Яндекс деньги -> bit у манагеров на данном клиенте
	 * @param int $clientId
	 * @param bool $value
	 * @return bool
	 */
	public static function controlYandexBit($clientId, $value)
	{
		$clientId *= 1;
		$value *= 1;

		if($client = self::model()->findByPk($clientId))
		{
			/**
			 * @var self $client
			 */

			$client->control_yandex_bit = $value;

			if($client->save())
			{
				if($value == 1)
					self::$msg = 'работа с обменником Яндекс деньги -> bit для клиента Client'.$clientId.' включена';
				else
					self::$msg = 'работа с обменником Яндекс деньги -> bit для клиента Client'.$clientId.' Отключена';

				return true;
			}
		}
		else
		{
			self::$lastError = 'клиент не найден';
			return false;
		}
	}

	public function getPickAccountsStr()
	{
		return ($this->pick_accounts) ? '<span class="success">включен</span>' : '<span class="error">отключен</span>';
	}

	/**
	 * пересчитывает платежи с даты последнего контрольного(если есть), если нет то по старинке
	 * если сумма отданного и пришедшего от первого до последнего расчета сходятся то запоздалые платежи не выдает
	 * @param int|null timestampEnd в форме расчета теперь есть скрытый date_add, нужно для добавления
	 * @param bool $fromCache в некоторых местах можно брать значение из кэша (обновляется по крону)
	 * @return array
	 *
	 */
	public function recalcOld($timestampEnd = null, $fromCache = false)
	{
		if(!$this->calc_enabled)
		{
			$result['amountRub'] = 0;
			return $result;
		}


		//берем из кэша если есть
		if($fromCache and $result = Yii::app()->cache->get('recalcClient'.$this->id))
			return $result;

		//если взять 3й расчет с конца (считаем всего 2 интервала)

		if($timestampEnd === null)
			$timestampEnd = time();

		$result = [
			'amountRub'=>0,	//сколько всего должны клиенту по $timestampEnd
			'lateTransactions'=>[],	//платежи, не попавшие в нужный расчет
			'diffAmount'=>0,	//на сколько не сходится приход и отданная сумма
		];

		$controlCalc = $this->getLastControlCalc();


		if(!$controlCalc)
		{
			//нет контрольного, считаем по-старинке
			$result['amountRub'] = ClientCalc::calcAmountRub($this->id);
			return $result;
		}


		$calcModels = ClientCalc::model()->findAll(array(
			'condition'=>"`client_id`={$this->id} AND `id`>={$controlCalc->id} AND `status`!='".ClientCalc::STATUS_CANCEL."'",
			'order'=>"`id` DESC",
		));

		/**
		 * @var ClientCalc[] $calcModels
		 */

		if(count($calcModels) < 2)
		{
			//если есть только первый расчет(контрольный) то выдаем сумму прихода
			$timestampStart = $controlCalc->date_add;

			$result['amountRub'] = $this->statsIn($timestampStart, $timestampEnd);

			//wex
			$coupons = Coupon::getModels($timestampStart, $timestampEnd, 0, $this->id);
			$couponStats = Coupon::getStats($coupons);
			$result['amountRub'] += $couponStats['amount'] * (1 - cfg('wexPercent'));

			$yandexPayments = TransactionWex::getModels($timestampStart, $timestampEnd, 0, $this->id);
			$yandexStats = TransactionWex::getStats($yandexPayments);
			$result['amountRub'] += $yandexStats['amount'];

			//Payeer
			$payeerModels = QiwiPay::getModels($timestampStart, $timestampEnd, 0, $this->id);
			$payeerStats = QiwiPay::getStats($payeerModels);
			$result['amountRub'] += $payeerStats['amount'];

			//new Yandex
			$newYandexPayments = NewYandexPay::getModels($timestampStart, $timestampEnd, 0, $this->id, true);
			$newYandexStats = NewYandexPay::getStats($newYandexPayments);

			$result['amountRub'] += $newYandexStats['amount'];

			//Yandex Account
			$yandexAccountStats = YandexTransaction::getStats($timestampStart, $timestampEnd, $this->id);
			$result['amountRub'] += $yandexAccountStats['amountIn'];

			//Qiwi Merchant Adgroup
			$qiwiMerchAmountStats = MerchantTransaction::getStats($timestampStart, $timestampEnd, $this->id, ['qiwi_wallet', 'qiwi_card']);
			$result['amountRub'] += $qiwiMerchAmountStats['amountIn'];

			//Yandex Merchant Adgroup
			$yadMerchAmountStats = MerchantTransaction::getStats($timestampStart, $timestampEnd, $this->id, ['yandex']);
			$result['amountRub'] += $yadMerchAmountStats['amountIn'];

			//Sim
			$simStats = SimTransaction::getStats($timestampStart, $timestampEnd, $this->id);
			$result['amountRub'] += $simStats['amountIn'];

			//RiseX
			$riseXStats = RisexTransaction::getStats($timestampStart, $timestampEnd, $this->id);
			$result['amountRub'] += $riseXStats['amountIn'];

			//exchange yandex money -> btc
			$exchangeYadBitPayments = ExchangeYadBit::getModels($timestampStart, $timestampEnd, 0, $this->id);
			$exchangeYadBitStats = ExchangeYadBit::getStats($exchangeYadBitPayments);
			$result['amountRub'] += $exchangeYadBitStats['amount'];

			return $result;
		}

		/**
		 * @var ClientCalc[] $calcModels
		 */

		//получаем последние  расчеты в порядке возрастания даты
		$calcModels = array_reverse($calcModels);

		$firstCalc = $calcModels[0];
		$lastCalc = $calcModels[count($calcModels) - 1];

		$recalcAmountIn = $this->statsIn($firstCalc->date_add, $lastCalc->date_add);;	//общий приход от первого до последнего расчета

		//wex
		$coupons = Coupon::getModels($firstCalc->date_add, $lastCalc->date_add, 0, $this->id);
		$couponStats = Coupon::getStats($coupons);
		$recalcAmountIn += $couponStats['amount'] * (1 - cfg('wexPercent'));


		$yandexPayments = TransactionWex::getModels($firstCalc->date_add, $lastCalc->date_add, 0, $this->id);
		$yandexStats = TransactionWex::getStats($yandexPayments);
		$recalcAmountIn += $yandexStats['amount'];

		//payeer
		$payeerPayments = QiwiPay::getModels($firstCalc->date_add, $lastCalc->date_add, 0, $this->id);
		$yandexStats = QiwiPay::getStats($payeerPayments);
		$recalcAmountIn += $yandexStats['amount'];

		//new Yandex
		$newYandexPayments = NewYandexPay::getModels($firstCalc->date_add, $lastCalc->date_add, 0, $this->id, true);
		$newYandexStats = NewYandexPay::getStats($newYandexPayments);

		$recalcAmountIn += $newYandexStats['amount'];

		//Yandex Account
		$yandexAccountStats = YandexTransaction::getStats($firstCalc->date_add, $lastCalc->date_add, $this->id);
		$recalcAmountIn += $yandexAccountStats['amountIn'];

		//Qiwi Merchant Adgroup
		$qiwiMerchAmountStats = MerchantTransaction::getStats($firstCalc->date_add, $lastCalc->date_add, $this->id, 0, ['qiwi_card', 'qiwi_wallet']);
		$recalcAmountIn += $qiwiMerchAmountStats['amountIn'];

		//Yandex Merchant Adgroup
		$yadMerchAmountStats = MerchantTransaction::getStats($firstCalc->date_add, $lastCalc->date_add, $this->id, 0, ['yandex']);
		$recalcAmountIn += $yadMerchAmountStats['amountIn'];

		//WalletS
		$walletSAmountStats = WalletSTransaction::getStatsIn($firstCalc->date_add, $lastCalc->date_add, $this->id, 0);
		$recalcAmountIn += $walletSAmountStats['amountIn'];

		//Sim
		$simStats = SimTransaction::getStats($firstCalc->date_add, $lastCalc->date_add, $this->id);
		$recalcAmountIn += $simStats['amountIn'];

		//RiseX
		$riseXStats = RisexTransaction::getStats($firstCalc->date_add, $lastCalc->date_add, $this->id);
		$recalcAmountIn += $riseXStats['amountIn'];

		//exchange yandex money -> btc
		$exchangeYadBitPayments = ExchangeYadBit::getModels($firstCalc->date_add, $lastCalc->date_add, 0, $this->id);
		$exchangeYadBitStats = ExchangeYadBit::getStats($exchangeYadBitPayments);
		$recalcAmountIn += $exchangeYadBitStats['amount'];

		//echo $recalcAmountIn."\n";
		$recalcAmountRub = 0;	//общее кол-во отданных денег

		foreach($calcModels as $key=>$calcModel)
		{
			if($key == 0)
				continue;

			$recalcAmountRub += $calcModel->amount_rub;

			$lateTransactions = $calcModel->latePayments;

			if($lateTransactions)
				$result['lateTransactions'][$calcModel->id.''] = $lateTransactions;
		}

		$debt = (isset($calcModel)) ? $calcModel->debt_rub : 0;

		//найти несходняк
		$result['diffAmount'] = $recalcAmountIn - ($recalcAmountRub + $debt);

		//не надо выводить запоздалые платежи если все сходится
		if($result['diffAmount'] == 0)
			$result['lateTransactions'] = [];
		else
			$result['lateTransactions'] = array_reverse($result['lateTransactions'], true);	//чтобы по порядку шли

		//выдаем текущий долг по настоящий момент
		$allStatsIn = $this->statsIn($firstCalc->date_add, $timestampEnd);

		//wex
		$coupons = Coupon::getModels($firstCalc->date_add, $timestampEnd, 0, $this->id);
		$couponStats = Coupon::getStats($coupons);
		$allStatsIn += $couponStats['amount'] * (1 - cfg('wexPercent'));

		//yandex
		$yandexPayments = TransactionWex::getModels($firstCalc->date_add, $timestampEnd, 0, $this->id);
		$yandexStats = TransactionWex::getStats($yandexPayments);
		$allStatsIn += $yandexStats['amount'];

		//new yandex
		$newYandexPayments = NewYandexPay::getModels($firstCalc->date_add, $timestampEnd, 0, $this->id, true);
		$newYandexStats = NewYandexPay::getStats($newYandexPayments);
		$allStatsIn += $newYandexStats['amount'];

		//payeer
		$payeerPayments = QiwiPay::getModels($firstCalc->date_add, $timestampEnd, 0, $this->id);
		$payeerStats = QiwiPay::getStats($payeerPayments);
		$allStatsIn += $payeerStats['amount'];

		//Yandex Account
		$yandexAccountStats = YandexTransaction::getStats($firstCalc->date_add, $timestampEnd, $this->id);
		$allStatsIn += $yandexAccountStats['amountIn'];

		//Qiwi Merchant Adgroup
		$qiwiMerchAmountStats = MerchantTransaction::getStats($firstCalc->date_add, $timestampEnd, $this->id, 0, ['qiwi_card', 'qiwi_wallet']);
		$allStatsIn += $qiwiMerchAmountStats['amountIn'];

		//Yandex Merchant Adgroup
		$yadMerchAmountStats = MerchantTransaction::getStats($firstCalc->date_add, $timestampEnd, $this->id, 0, ['yandex']);
		$allStatsIn += $yadMerchAmountStats['amountIn'];

		//WalletS
		$walletSAmountStats = WalletSTransaction::getStatsIn($firstCalc->date_add, $timestampEnd, $this->id, 0);
		$allStatsIn += $walletSAmountStats['amountIn'];

		//Sim
		$simStats = SimTransaction::getStats($firstCalc->date_add, $timestampEnd, $this->id);
		$allStatsIn += $simStats['amountIn'];

		//RiseX
		$riseXStats = RisexTransaction::getStats($firstCalc->date_add, $timestampEnd, $this->id);
		$allStatsIn += $riseXStats['amountIn'];

		//exchange yandex money -> btc
		$exchangeYadBitPayments = ExchangeYadBit::getModels($firstCalc->date_add, $timestampEnd, 0, $this->id);
		$exchangeYadBitStats = ExchangeYadBit::getStats($exchangeYadBitPayments);
		$allStatsIn += $exchangeYadBitStats['amount'];

		$result['amountRub'] = $allStatsIn - $recalcAmountRub;

		$result['amountRub'] = round($result['amountRub']);

		Yii::app()->cache->set('recalcClient'.$this->id, $result, 600);

		return $result;
	}

	/**
	 * Для бан-чекера, для отключения клиента
	 * все активные кошельки (IN, TRANSIT, OUT) БЕЗ ошибки которые используются или могут быть исползованы
	 * и на которые были переводы, и лимит там не 0
	 * fix 30.03.18: неюзаные тоже
	 * @return Account[]
	 */
	public function getActiveAccounts()
	{
		$minBalance = cfg('min_balance');

		return Account::model()->findAll([
			'condition'=>"`client_id`={$this->id}
				AND (`error` = '' OR `error`='".Account::ERROR_IDENTIFY."')
				AND (`limit_in` > $minBalance or `balance`>=$minBalance)
				AND `date_used` = 0 AND `is_old` = 0

				AND `enabled`=1
			",//AND (`limit_in`<190000 OR `limit_out`<190000)
			'order'=>"`type` ASC",
		]);
	}


	/**
	 * последний по дате контрольный расчет
	 * @return ClientCalc|null
	 */
	public function getLastControlCalc()
	{
		return ClientCalc::model()->find([
			'condition'=>"`client_id`={$this->id} AND `is_control`=1",
			'order'=>"`id` DESC",
		]);
	}

	/**
	 * последние кошельки с которых переводил клиент (для бан чекера)
	 * не возвращает кошельки с которых уже были огры раньше
	 * @return array
	 */
	public function getLastWallets()
	{
		$result = [];

		$timestampStart = time() - 3600*24*2;

		$tblAccount = Account::model()->tableSchema->name;
		$accountCond = "`account_id` IN(SELECT `id` FROM `".$tblAccount."` WHERE `type`='".Account::TYPE_IN."' AND `client_id`={$this->id} AND `date_check`>$timestampStart)";

		$models = Transaction::model()->findAll(array(
			'select'=>"`wallet`",
			'condition'=>"
				`type`='".Transaction::TYPE_IN."'
				AND `status`='".Transaction::STATUS_SUCCESS."'
				AND `date_add`>=$timestampStart
				AND $accountCond
				AND (`wallet` LIKE '+7%' OR `wallet` LIKE '+3%')
			",
			'order'=>"`id` DESC",
			'group'=>"`wallet`",
		));

		/**
		 * @var Transaction[] $models
		 */

		foreach($models as $model)
		{
			if(!Transaction::banCheck($model->wallet, $timestampStart))
				$result[] = $model->wallet;
		}

		return $result;
	}

	public static function incomeModeArr()
	{
		return array(
			self::INCOME_WALLET => 'кошельки',
			self::INCOME_ORDER => 'заявки',
		);
	}

	/**
	 * конфиг клиента для заявочной системы
	 * @return array
	 */
	public function getOrderConfig()
	{
		$result = [];

		if($model = ManagerOrderConfig::getModelByClientId($this->id))
			$result =  $model->attributes;

		return $result;
	}

	/**
	 * все пользователи клиента
	 * @return User[]
	 */
	public function getUsers()
	{
		return User::model()->findAll("`client_id`={$this->id}");
	}

	/**
	 * @return User[]
	 */
	public function getActiveUsers()
	{
		return User::model()->findAll("`client_id`={$this->id} AND `active`=1");
	}

	/**
	 * @return User[]
	 */
	public function getDisabledUsers()
	{
		return User::model()->findAll("`client_id`={$this->id} AND `active`=0");
	}

	/**
	 * статистика по использованию кошельков клиентом:
	 * -взятые кошельки
	 * -помечены  к отправке в отстойник
	 * -текущие кошельки
	 * -объем по приходу
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 */
	public function accountStats($timestampStart, $timestampEnd)
	{
		$timestampStart *= 1;
		$timestampEnd *= 1;


	}

	/**
	 * работоспособные кошельки клиента (в т.ч. и непроверенные)
	 * лимит > min_balance
	 * функция только для страницы лимитов и $this->disble()
	 * @return Account[]
	 */
	public function getWorkAccounts()
	{
		$dateCheck = time() - config('order_account_check_interval')*2;

		$minBalance = cfg('min_balance');

		$models = Account::model()->findAll("
			`client_id`={$this->id}
			AND `error` IN ('', '".Account::ERROR_IDENTIFY."', '".Account::ERROR_CHECK."')
			AND `limit_in` > $minBalance
			AND `date_used` = 0 AND `is_old` = 0
			AND (`date_check` > $dateCheck OR `date_check` = 0)
			AND `enabled`=1
		");

		return $models;
	}

	/**
	 * @return array
	 */
	public static function calcModeArr()
	{
		return [
			self::CALC_MODE_AMOUNT => 'по сумме',
			self::CALC_MODE_ORDER => 'по заявкам',
		];
	}


	/**
	 * неоплаченные завершенные заявки
	 * @return ManagerOrder[]
	 */
	public function getNotPaidOrders()
	{
		$users = $this->getUsers();

		$userCond = "`user_id` IN(";

		foreach($users as $user)
			$userCond .= "'{$user->id}',";

		$userCond = trim($userCond, ',').')';

		$orders = ManagerOrder::model()->findAll([
			'condition' => "
				`date_end` > 0
				AND `date_pay` = 0
				AND $userCond
			",
			'order' => "`date_end` DESC"
		]);

		/**
		 * @var managerOrder[] $orders
		 */

		foreach($orders as $key=>$order)
		{
			if($order->amountIn <= 0)
				unset($orders[$key]);
		}


		return $orders;
	}

	public function getCurrentManagerOrders()
	{
		$users = $this->getUsers();

		$userCond = "`user_id` IN(";

		foreach($users as $user)
			$userCond .= "'{$user->id}',";

		$userCond = trim($userCond, ',').')';

		return ManagerOrder::model()->findAll([
			'condition' => "
				`date_end` = 0
				AND $userCond
			",
			'order' => "`date_add` DESC"
		]);
	}

	/**
	 * @return Account[]
	 */
	public function getEnabledAccounts()
	{
		return Account::model()->findAll("`client_id`='{$this->id}' AND  `enabled`=1");
	}

	/**
	 * @return Account[]
	 */
	public function getDisabledAccounts()
	{
		return Account::model()->findAll("`client_id`='{$this->id}' AND  `enabled`=0");
	}

	/**
	 * обновить кеш пересчета у активных клиентов
	 */
	public static function cacheUpdateRecalc()
	{

		$config = [
			'startInterval' => 300,
			'cacheTime' => 600,
			'threadName' => 'cacheUpdateRecalc',
		];

		if(!Tools::threader($config['threadName']))
		{
			echo "\n thread already run";
			return true;
		}

		if(time() - config('cacheUpdateRecalcTimestamp') < $config['startInterval'])
		{
			echo "\n not now";
			return true;
		}

		$globalTimeStart = time();

		foreach(self::getActiveClients() as $client)
		{
			//если уже обновлен был кеш кем то то пропускаем для экономии ресурсов
			//if(Yii::app()->cache->get('recalcClient'.$client->id))
			//	continue;

			$timeStart = time();

			$recalc = $client->recalc(time(), false);


			Yii::app()->cache->set('recalcClient'.$client->id, $recalc, $config['cacheTime']);

			$updateTime = time() - $timeStart;

			if($updateTime > 4)
				toLogRuntime('update recalc cache '.$client->name.': '.$updateTime.' sec');
		}

		toLogRuntime('update recalc cache global: '.(time() - $globalTimeStart).' sec');

		config('cacheUpdateRecalcTimestamp', time());

		return true;
	}

	public function getCalcEnabledStr()
	{
		return ($this->calc_enabled) ? '<font color="green">включен</font>' : '<font color="green">отключен</font>';
	}

	public function calcEnable()
	{
		$this->calc_enabled = 1;
		return $this->save();
	}

	public function calcDisable()
	{
		$this->calc_enabled = 0;
		return $this->save();
	}

	/**
	 * редактирует данные клиента
	 * @param array $params
	 * @return bool
	 */
	public function edit(array $params)
	{
		$allowedParams = ['email'];

		foreach($params as $key=>$val)
		{
			if(!in_array($key, $allowedParams))
				continue;

			$this->$key = $val;
		}

		return $this->save();
	}

	/**
	 * отменяет все активные выводы со всех клиентов (если включен гф)
	 * логирует в таблицу глобавлфина
	 * выдает список отмененных платежей
	 * @param int $clientId
	 * @param int $userId
	 * @return bool
	 */
	public static function cancelFinOrdersAll($userId)
	{
		$clients = self::getActiveClients();

		self::$msg = '';

		foreach($clients as $client)
		{
			if(!$client->global_fin)
				continue;

			if(!$client->finOrdersInProcess)
				continue;

			$result = self::cancelFinOrders($client->id, $userId);

			self::$msg .= self::$someData['msg'];

			if(!$result)
				return false;
		}

		return true;
	}

	/**
	 * статистика по комиссии за выбранный период (текущее кол-во кошельков на каждой группе и суммарная комса)
	 * @param int $timestampStart
	 * @return array
		[
			'groups' => [
	  			'1' => [
	  				'in' => [
			  			'wallets' => [
			  				'+79...' => ['inAmount'=>0, 'outAmount'=>0, 'commissionAmount'=>0, 'uniqueWallets'=>22, 'model'=>],
			  			],
			  			'stats' => [
			  				'inAmount' => '',
			  				'outAmount' => '',
			  				'commissionPercent' => '', //floorAmount(commissionAmount / inAmount * 100, 2)
			  				'countWallets' => '',	//кол-во кошельков
			  			],
			  		],
			  		'transit' => [...],
			  		'out' => [...],

			  		'stats' => [

			  		],
	 			],
	  		]

	  		'stats' => [
	 			...
			],
	 	]
	 */
	public function commissionStats($timestampStart)
	{
		$result = [
			'groups' => [],
			'stats' => [
				'inAmount' => 0,
				'outAmount' => 0,
				'commissionAmount' => 0,
				'countWallets' => 0,
			]
		];

		//чтобы группы в массиве шли по порядку
		foreach(Account::getGroupArr() as $groupId=>$arr)
		{
			$result['groups'][$groupId] = [
				'types'=>[
					'in' => [],
					'transit' => [],
					'out' => [],
				],
				'stats'=>[],
			];
		}

		$timestampAccStart = $timestampStart - 3*3600; //на всякий

		$accounts = Account::model()->findAll([
			'select'=>"`id`",
			'condition'=>"`client_id`={$this->id} AND `date_check`>$timestampAccStart",
		]);


		$accountStr = '';

		foreach($accounts as $account)
			$accountStr .= ', '.$account->id;

		$transactions = Transaction::model()->findAll([
			'select' => "`account_id`, `amount`, `commission`, `type`, `id`, `date_add`",
			'condition' => "
				`date_add` >= $timestampStart
				AND `status` = '".Transaction::STATUS_SUCCESS."'
				AND `account_id` IN(".trim($accountStr, ',').")
			",
			'order'=>"`date_add` ASC",
			//'limit'=>1000,
		]);

		/**
		 * @var Transaction[] $transactions
		 */

		//чтобы лишний раз не делать запросы к Account

		$uniqueAccounts =  [];	//['accountId'=>$model,...]

		foreach($transactions as $transaction)
		{
			if(!$uniqueAccounts[$transaction->account_id])
				$uniqueAccounts[$transaction->account_id] = $transaction->account;
		}

		$inAmount = 0;	//приход на IN кошельки

		foreach($transactions as $transaction)
		{
			$account = $uniqueAccounts[$transaction->account_id];
			/**
			 * @var Account $account
			 */

			//стыты кошелька
			$walletArr = &$result['groups'][$account->group_id]['types'][$account->type]['wallets'][$account->login];
			//статы типа кошельков (in, transit, out)
			$typeArr = &$result['groups'][$account->group_id]['types'][$account->type]['stats'];
			//статы группы (1,2,3,4,5)
			$groupArr = &$result['groups'][$account->group_id]['stats'];
			//общие статы
			$allArr = &$result['stats'];


			if($transaction->type == Transaction::TYPE_IN)
			{
				$walletArr['inAmount'] += $transaction->amount;
				$typeArr['inAmount'] += $transaction->amount;
				$groupArr['inAmount'] += $transaction->amount;
				$allArr['inAmount'] += $transaction->amount;

				if($account->type == Account::TYPE_IN)
					$inAmount += $transaction->amount;
			}
			elseif($transaction->type == Transaction::TYPE_OUT)
			{
				$walletArr['outAmount'] += $transaction->amount;
				$typeArr['outAmount'] += $transaction->amount;
				$groupArr['outAmount'] += $transaction->amount;
				$allArr['outAmount'] += $transaction->amount;
			}

			$walletArr['commissionAmount'] += $transaction->commission;
			$typeArr['commissionAmount'] += $transaction->commission;
			$groupArr['commissionAmount'] += $transaction->commission;
			$allArr['commissionAmount'] += $transaction->commission;

			$walletArr['uniqueWallets'] = $account->wallets_count;
			$typeArr['uniqueWallets'] += $account->wallets_count;
			$groupArr['uniqueWallets'] += $account->wallets_count;
			$allArr['uniqueWallets'] += $account->wallets_count;

			$arr['model'] = $account;
		}

		//посчитать проценты
		foreach($result['groups'] as &$group)
		{
			foreach($group['types'] as &$type)
			{
				if(!isset($type['wallets']))
					continue;

				foreach($type['wallets'] as &$wallet)
					$wallet['commissionPercent'] = self::calcCommission($wallet['inAmount'], $wallet['commissionAmount']);

				$type['stats']['commissionPercent'] =
					self::calcCommission($type['stats']['inAmount'], $type['stats']['commissionAmount']);
			}

			$group['stats']['commissionPercent'] =
				self::calcCommission($group['stats']['inAmount'], $group['stats']['commissionAmount']);
		}

		$result['stats']['commissionPercent'] =
			self::calcCommission($result['stats']['inAmount'], $result['stats']['commissionAmount']);

		$result['stats']['inAmountIn'] = $inAmount;

		$result['stats']['commissionPercentRelative'] =
			self::calcCommission($inAmount, $result['stats']['commissionAmount']);

		return $result;
	}

	/**
	 * для self::commissionStats()
	 * @param int $inAmount
	 * @param int $commissionAmount
	 * @return float
	 */
	private static function calcCommission($inAmount, $commissionAmount)
	{
		if($inAmount > 0)
			return floorAmount($commissionAmount / $inAmount * 100, 2);
		else
			return 0;
	}

	public function getWexCount()
	{
		$users = $this->getUsers();

		$wexAccounts = [];

		foreach($users as $user)
		{
			if($wex = WexAccount::model()->find("`user_id`='{$user->id}'"))
				$wexAccounts[] = $wex;
		}

		return count($wexAccounts);
	}
	public function getQiwiNewCount()
	{
		$users = $this->getUsers();

		$qiwiNewAccounts = [];

		foreach($users as $user)
		{
			if($qiwiNew = PayeerAccount::model()->find("`user_id`='{$user->id}'"))
				$qiwiNewAccounts[] = $qiwiNew;
		}

		return count($qiwiNewAccounts);
	}

	/**
	 * вернет false - если есть правило off для текущего кл, либо если нет правила для текущего но есть у других кл on
	 * вернет true  - если есть правило on для текущего кл, или нет ни у кого правила on
	 *
	 * @param string $moduleId
	 * @return bool
	 */
	public function checkRule($moduleId)
	{
		$moduleId = strtolower(trim($moduleId));

		if($ruleModel = ClientModuleRule::getModel(['module_id'=>$moduleId, 'client_id'=>$this->id]))
		{
			if($ruleModel->rule == ClientModuleRule::RULE_ON)
				return true;
			else
				return false;
		}
		else
		{
			//если есть разрешающее правило у других кл то по умолчанию запрещено
			if($ruleModel = ClientModuleRule::getModel(['module_id'=>$moduleId, 'rule'=>ClientModuleRule::RULE_ON]))
				return false;
			else
				return true;
		}

	}

	/**
	 * @param $params
	 *
	 * @return bool
	 *
	 * управляем модулями клиента через веб-интерфейс
	 */
	public static function saveModuleRule($params)
	{
		session_write_close();

		$threadName = 'saveClientModuleRule';

		if(!Tools::threader($threadName))
		{
			die('поток saveClientModuleRule уже запущен');
		}

		foreach($params as $clientId => $param)
		{
			foreach($param as $moduleId => $state)
			{

				if($model = ClientModuleRule::getModel(['client_id'=>$clientId, 'module_id'=>$moduleId]))
				{
					$model->rule = $state ? 'on' : 'off';
					$model->update();
				}
				else
				{
					$model = new ClientModuleRule;
					$model->client_id = $clientId;
					$model->module_id = $moduleId;
					$model->rule = $state ? 'on' : 'off';
					$model->save();
				}
			}
		}
		return true;
	}

	/**
	 * @return MerchantUser[]
	 */
	public function getQiwiMerchantUsers()
	{
		return MerchantUser::model()->findAll([
			'condition'=>"`uni_client_id`=$this->id",
			'order' => "`uni_client_id` ASC",
		]);
	}

	/**
	 * пересчитывает платежи с даты последнего контрольного(если есть), если нет то по старинке
	 * если сумма отданного и пришедшего от первого до последнего расчета сходятся то запоздалые платежи не выдает
	 * @param int|null timestampEnd в форме расчета теперь есть скрытый date_add, нужно для добавления
	 * @param bool $fromCache в некоторых местах можно брать значение из кэша (обновляется по крону)
	 * @return array
	 *
	 */
	public function recalc($timestampEnd = null, $fromCache = false)
	{

		if(!$this->calc_enabled)
		{
			$result['amountRub'] = 0;
			return $result;
		}

		//берем из кэша если есть
		if($fromCache and $result = Yii::app()->cache->get('recalcClient'.$this->id))
			return $result;

		if($timestampEnd === null)
			$timestampEnd = time();


		$result = [
			'amountRub'=>0,	//сколько всего должны клиенту по $timestampEnd
			'lateTransactions'=>[],	//платежи, не попавшие в нужный расчет
			'diffAmount'=>0,	//на сколько не сходится приход и отданная сумма
		];

		$controlCalc = $this->getLastControlCalc();

		if(!$controlCalc)
		{
			//нет контрольного, считаем по-старинке
			$result['amountRub'] = ClientCalc::calcAmountRub($this->id);
			return $result;
		}

		$calcModels = ClientCalc::model()->findAll(array(
			'condition'=>"`client_id`={$this->id} AND `id`>={$controlCalc->id} AND `status`!='".ClientCalc::STATUS_CANCEL."'",
			'order'=>"`id` ASC",
		));

		/**
		 * @var ClientCalc[] $calcModels
		 */


		$firstCalc = $calcModels[0];
		$lastCalc = $calcModels[count($calcModels) - 1];

		if(count($calcModels) < 2)
		{
			//если есть только первый расчет(контрольный) то выдаем сумму прихода
			$result['amountRub'] = $this->subRecalc($lastCalc->date_add, time(), true);
		}
		else
		{
			$recalcAmountIn = $this->subRecalc($firstCalc->date_add, $lastCalc->date_add, true);

			$recalcAmountRub = 0;	//общее кол-во отданных денег

			foreach($calcModels as $key=>$calcModel)
			{
				if($key == 0)
					continue;

				$recalcAmountRub += $calcModel->amount_rub;

				$lateTransactions = $calcModel->latePayments;

				if($lateTransactions)
					$result['lateTransactions'][$calcModel->id.''] = $lateTransactions;
			}

			$debt = (isset($calcModel)) ? $calcModel->debt_rub : 0;

			//найти несходняк
			$result['diffAmount'] = $recalcAmountIn - ($recalcAmountRub + $debt);

			//не надо выводить запоздалые платежи если все сходится
			if($result['diffAmount'] == 0)
				$result['lateTransactions'] = [];
			else
				$result['lateTransactions'] = array_reverse($result['lateTransactions'], true);	//чтобы по порядку шли

			//выдаем текущий долг по настоящий момент
			$allStatsIn = $this->subRecalc($firstCalc->date_add, $timestampEnd, true);

			$result['amountRub'] = $allStatsIn - $recalcAmountRub;
		}

		$result['amountRub'] = round($result['amountRub']);

		Yii::app()->cache->set('recalcClient'.$this->id, $result, 30);

//		if(YII_DEBUG)
//		{
//			echo 'recalcClient'.$this->id;
//			var_dump(Yii::app()->cache->get('recalcClient'.$this->id));
//		}

		return $result;
	}

	private function subRecalc($timestampStart, $timestampEnd, $withBonus = false)
	{

		$recalcAmountIn = $this->statsIn($timestampStart, $timestampEnd);	//общий приход от первого до последнего расчета

		//wex
		$coupons = Coupon::getModels($timestampStart, $timestampEnd, 0, $this->id);
		$couponStats = Coupon::getStats($coupons);
		$recalcAmountIn += $couponStats['amount'] * (1 - cfg('wexPercent'));

		$yandexPayments = TransactionWex::getModels($timestampStart, $timestampEnd, 0, $this->id);
		$yandexStats = TransactionWex::getStats($yandexPayments);
		$recalcAmountIn += $yandexStats['amount'];

		//payeer
		$payeerPayments = QiwiPay::getModels($timestampStart, $timestampEnd, 0, $this->id);
		$yandexStats = QiwiPay::getStats($payeerPayments);
		$recalcAmountIn += $yandexStats['amount'];

		//new Yandex
		$newYandexPayments = NewYandexPay::getModels($timestampStart, $timestampEnd, 0, $this->id, true);
		$newYandexStats = NewYandexPay::getStats($newYandexPayments);


		//когда нужно посчитать стату за вычетом бонуса
		if($withBonus and $bonuses = ClientCommission::getBonus($this->id) and $bonuses['ym_card_bonus'] != 0)
		{
			$newYandexStats['amount'] += $newYandexStats['amount'] * (0 + $bonuses['ym_card_bonus']/100);
		}

		$recalcAmountIn += $newYandexStats['amount'];

		//Yandex Account
		$yandexAccountStats = YandexTransaction::getStats($timestampStart, $timestampEnd, $this->id);
		$recalcAmountIn += $yandexAccountStats['amountIn'];

		//Qiwi Merchant Adgroup
		$qiwiMerchAmountStats = MerchantTransaction::getStats($timestampStart, $timestampEnd, $this->id, 0, ['qiwi_card', 'qiwi_wallet']);
		$recalcAmountIn += $qiwiMerchAmountStats['amountIn'];

		//Yandex Merchant Adgroup
		$merchantYadAmountStats = MerchantTransaction::getStats($timestampStart, $timestampEnd, $this->id, 0, ['yandex']);
		$recalcAmountIn += $merchantYadAmountStats['amountIn'];

		//WalletS
		$walletSStats = WalletSTransaction::getStatsIn($timestampStart, $timestampEnd, $this->id, 0);
		$recalcAmountIn += $walletSStats['amountIn'];

		//Sim
		$simStats = SimTransaction::getStats($timestampStart, $timestampEnd, $this->id);
		$recalcAmountIn += $simStats['amountIn'];

		//RiseX
		$riseXStats = RisexTransaction::getStats($timestampStart, $timestampEnd, $this->id);

		//когда нужно посчитать стату за вычетом бонуса
		if($withBonus and $bonuses = ClientCommission::getBonus($this->id) and $bonuses['rise_x_bonus'] != 0)
		{
			$riseXStats['amountIn'] += $riseXStats['amountIn'] * (0 + $bonuses['rise_x_bonus']/100);
		}

		$recalcAmountIn += $riseXStats['amountIn'];

		//exchange yandex money -> btc
		$exchangeYadBitPayments = ExchangeYadBit::getModels($timestampStart, $timestampEnd, 0, $this->id);
		$exchangeYadBitStats = ExchangeYadBit::getStats($exchangeYadBitPayments);
		$recalcAmountIn += $exchangeYadBitStats['amount'];



		return $recalcAmountIn;
	}

}