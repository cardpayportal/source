<?php
/**
 * @property int id
 * @property string wallet
 * @property int client_id
 * @property int user_id
 * @property int date_add
 * @property string access_token
 * @property int date_check
 * @property string error
 * @property float balance
 *
 * @property float LimitIn
 * @property string limitInMonthStr
 * @property string limitInDayStr
 * @property float limitIn
 * @property YandexTransaction[] transactionsManager
 *
 * @property Client client
 * @property User user
 * @property YandexNotification lastNotification
 * @property string dateCheckStr
 * @property YandexApi api
 * @property int date_pick
 * @property int card_number
 * @property int cardNumberStr
 * @property bool hidden
 */
class YandexAccount extends Model
{
	const SCENARIO_ADD = 'add';

	private $api;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return [];
	}

	public function tableName()
	{
		return '{{yandex_account}}';
	}

	public function beforeValidate()
	{
		if(!$this->user_id)
			unset($this->user_id);

		if(!$this->client_id)
			unset($this->client_id);

		return parent::beforeSave();
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}

	public function rules()
	{
		return [
			['wallet', 'unique', 'className'=>__CLASS__, 'attributeName'=>'wallet', 'allowEmpty'=>false],
			['client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id', 'allowEmpty'=>false],
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>true],
			['card_number', 'match', 'pattern'=>'!^\d{16}$!', 'message'=>'неверный номер карты'],
			['id, date_add, custom_order_id, access_token, date_check, error, balance, date_pick, hidden', 'safe'],
		];
	}

	/**
	 * статистика по платежам для кошелька
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return array ['amountIn'=>0, 'amountOut'=>0]
	 */
	public function getTransactionStats($timestampStart = 0, $timestampEnd = 0)
	{
		$result = [
			'amountIn'=>0,
			'amountOut'=>0,
		];

		$timestampStart *= 1;
		$timestampEnd *= 1;

		$transactions = YandexTransaction::model()->findAll([
			'condition' => "
				`account_id` = '{$this->id}' AND `status` = '".YandexTransaction::STATUS_SUCCESS."'
				AND `date_add` >= $timestampStart and `date_add` < $timestampEnd
				AND `client_id`='{$this->client_id}' AND `user_id`='{$this->user_id}'
			",
		]);

		/**
		 * @var YandexTransaction[] $transactions
		 */

		foreach($transactions as $trans)
		{
			if($trans->direction == YandexTransaction::DIRECTION_IN)
				$result['amountIn'] += $trans->amount;
			elseif($trans->direction == YandexTransaction::DIRECTION_OUT)
				$result['amountOut'] += $trans->amount;
		}

		return $result;
	}

	/**
	 * кошельки юзера
	 * @param int $userId
	 * @return self[]
	 */
	public static function getUserModels($userId)
	{
		return self::model()->findAll([
			'condition' => "`user_id`='$userId'",
			'order' => "`date_pick` DESC",
		]);
	}

	/**
	 * проверяем суммы переводов за каждый месяц и формируем оставшийся лимит
	 * за прошлые месяцы
	 * @return int|mixed
	 */
	public function getLimitForAllPreviousTime()
	{
		$cfg = cfg('yandexAccount');

		$begin = new DateTime(date('01.m.Y', $this->date_add));
		$end = new DateTime(date('d.m.Y 23:59:59', time()));
		$end->modify('last day of this month');
		$interval = new DateInterval('P1M');

		$period = new DatePeriod($begin, $interval, $end);

		//получаем массив дат с интервалом 1 месяц, попадающих в период
		$periodArr = [];
		foreach($period as $date)
		{
			$periodArr[] = [
				'dateStr' => $date->format('d.m.Y H:i').'',
				'month' => $month = $date->format('m')*1,
				'year' => $year = $date->format('Y')*1,
			];
		}

		$resultLimit = $cfg['limitInMonth'];

		foreach ($periodArr as $key=>$date)
		{
			if($key == count($periodArr)-1)
				break;

			$statsMonth = $this->getTransactionStats(strtotime($date['dateStr']), strtotime($periodArr[$key + 1]['dateStr']));
			$lastLimit = $cfg['limitInMonth'] - $statsMonth['amountIn'];

			/**
			 * @var Model $model
			 */
			if(!$model = YandexAccountLimit::getModel(
				[
					'month'=>$date['month'],
					'year'=>$date['year'],
					'wallet_id'=>$this->id
				]))
			{
				$model = new YandexAccountLimit;
				$model->scenario = YandexAccountLimit::SCENARIO_ADD;
				$model->month = $date['month'];
				$model->year = $date['year'];
				$model->in_amount_per_month = $statsMonth['amountIn'];
				$model->limit = $lastLimit;
				$model->date_calc = time();
				$model->wallet_id = $this->id;
				$model->save();
			}

			if($lastLimit < 0)
				$resultLimit += $model->limit;
		}

		return $resultLimit;
	}

	public function getOutAmountMonth()
	{
		$cfg = cfg('yandexAccount');
		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());

		return $statsMonth['amountOut'];
	}

	public function getInAmountMonth()
	{
		$cfg = cfg('yandexAccount');
		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());

		return $statsMonth['amountIn'];
	}

	/**
	 * остаток лимита на кошельке
	 * если месячный лимит больше максимального дневного то чекать оставшийся дневной
	 * 	иначе отображать оставшийся месячный
	 */
	public function getLimitIn()
	{
		$cfg = cfg('yandexAccount');

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $cfg['limitInMonth'] - $statsMonth['amountIn'] - $this->balance;
		$limitOutMonth = $cfg['limitInMonth'] - $statsMonth['amountOut'] - $this->balance;

		return floor(min($limitInMonth, $limitOutMonth));
	}


	/**
	 * остаток лимита на кошельке дневной
	 * пользователи переливают часто, нужно было разделить
	 */
	public function getLimitInDayStr()
	{
		$cfg = cfg('yandexAccount');

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $cfg['limitInMonth'] - $statsMonth['amountIn'] - $this->balance;
		$limitOutMonth = $cfg['limitInMonth'] - $statsMonth['amountOut'] - $this->balance;

		$statsDay = $this->getTransactionStats(Tools::startOfDay(), time());
		$limitInDay = $cfg['limitInDay'] - $statsDay['amountOut'] - $this->balance;

		$limit = min($limitInMonth, $limitOutMonth, $limitInDay);

		if($limit < 30000)
			return '<span class="error">'.formatAmount($limit, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limit, 0).'</span>';
	}

	/**
	 * остаток лимита на кошельке месячный
	 * пользователи переливают часто, нужно было разделить
	 */
	public function getLimitInMonthStr()
	{
		$cfg = cfg('yandexAccount');

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $cfg['limitInMonth'] - $statsMonth['amountIn'] - $this->balance;
		$limitOutMonth = $cfg['limitInMonth'] - $statsMonth['amountOut'] - $this->balance;

		$limitMonth = min($limitInMonth, $limitOutMonth);

		if($limitMonth < 30000)
			return '<span class="error">'.formatAmount($limitMonth, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limitMonth, 0).'</span>';
	}


	/**
	 * @return YandexTransaction[]
	 */
	public function getTransactionsManager()
	{
		$transactions = YandexTransaction::model()->findAll([
			'condition' => "`account_id`='{$this->id}' AND `date_add`>{$this->date_pick} AND `direction`='".YandexTransaction::DIRECTION_IN."' and `status`='".YandexTransaction::STATUS_SUCCESS."' AND `client_id`='{$this->client_id}'  AND `user_id`='{$this->user_id}'",
			'order' => "`date_add` DESC",
		]);

		return $transactions;
	}

	/**
	 * все аккаунты по дате добавления
	 * @return self[]
	 */
	public static function getModels()
	{
		return self::model()->findAll([
			'condition' => "",
			'order' => "`date_add` DESC",
		]);
	}

	/**
	 * @return Client
	 */
	public function getClient()
	{
		return Client::model()->findByPk($this->client_id);
	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		return User::model()->findByPk($this->user_id);
	}

	public function getDateCheckStr()
	{
		if($this->date_check)
			return date('d.m.Y H:i', $this->date_check);
		else
			return '';
	}

	/**
	 * @param string $wallets - textarea(каждый с новой строке)
	 * @param int $clientId
	 * @return int кол-во добавленных
	 */
	public static function addMany($wallets, $clientId, $userId)
	{
		$result = 0;

		if(!Client::getModel($clientId))
		{
			self::$lastError = 'указанный клиент не найден';
			return $result;
		}

		if(preg_match_all('!((\d{10,20})\.\w+)(\|[\d\s]+|)!', $wallets, $res))
		{

			foreach($res[2] as $key=>$wallet)
			{
				if(self::getModel(['wallet'=>$wallet]))
					continue;

				$token = $res[1][$key];
				$card = $res[3][$key];

				$model = new self;
				$model->scenario = YandexAccount::SCENARIO_ADD;
				$model->wallet = $wallet;
				$model->access_token = $token;
				$model->client_id = $clientId*1;
				if($userId)
				{
					$model->user_id = $userId*1;
					$model->date_pick = time();
				}
				$model->card_number = preg_replace('![\s\|]!', '', $card);

				if($model->save())
					$result++;
				else
					return $result;
			}
		}
		else
			self::$lastError = 'кошельков не найдено';

		return $result;
	}

	/**
	 * выдает коешльки манагерам
	 * @param $userId
	 * @param $count
	 * @return bool
	 */
	public static function pickAccounts($userId, $count)
	{
		$countMax = 10;

		$count = (int)$count;

		if(!$user = User::getUser($userId) or $user->role !== User::ROLE_MANAGER)
		{
			self::$lastError = 'у вас нет прав на получение кошельков';
			return false;
		}
		elseif($count <= 0 or $count > $countMax)
		{
			self::$lastError = 'неверно указано количество';
			return false;
		}

		$clientId = $user->client->id;

		$models = self::model()->findAll([
			'condition' => "`client_id`='{$clientId}' AND `user_id`=0 AND `error`='' and `date_check`>0",
			'order' => "`id` ASC",
		]);

		/**
		 * @var self[] $models
		 */

		if(count($models) < $count)
		{
			self::$lastError = 'недостаточно проверенных кошельков';
			return false;
		}

		foreach($models as $model)
		{
			$model->user_id = $user->id;
			$model->date_pick = time();

			if(!$model->save())
			{
				self::$lastError = 'ошибка выдачи кошелька';
				return false;
			}
		}

		return true;
	}

	/**
	 * @param int $threadNumber (0 - 9)
	 * @return bool
	 */
	public static function startCheck($threadNumber)
	{
		//минимальный интервало проверки(сек)
		$checkInterval = 30;

		$threadMax = 1;
		$threadName = 'yandexAccountCheck'.$threadNumber;

		if(!Tools::threader($threadName))
		{
			echo "\n поток уже запущен";
			return false;
		}

		$threadCond = Tools::threadCondition($threadNumber, $threadMax);

		$dateCheck = time() - $checkInterval;

		$models = self::model()->findAll([
			'condition' => "`error`='' AND `date_check` < $dateCheck AND  $threadCond",
			'order' => "`date_check` ASC",
		]);

		/**
		 * @var self[] $models
		 */

		if(!$models)
		{
			echo "\n нечего проверять";
			return false;
		}

		$checkCount = 0;

		foreach($models as $model)
		{
			if(!$model->updateTransactions())
			{
				$msg = "YandexAccount ошибка проверки кошелька ".$model->wallet.': '.$model::$lastError;
				echo "\n $msg" ;
				toLogError($msg);

				continue;
			}

			$checkCount++;
		}

		echo "\n success $checkCount";

		return true;
	}

	/**
	 * @return YandexApi|bool
	 */
	private function getApi()
	{
		if(!$this->access_token)
		{
			self::$lastError = 'no access token';
			return false;
		}

		if(!$this->api)
		{
			$cfg = cfg('yandexAccount');

			$this->api = new YandexApi;
			$this->api->accessToken = $this->access_token;
			$this->api->proxy = $cfg['proxy'];
			$this->api->proxyType = $cfg['proxyType'];
		}

		return $this->api;
	}


	/**
	 * обновляет платежи на кошельке  и баланс
	 * использует апи
	 * @param int $timestampStart если 0 то автоматом
	 * @return bool
	 */
	public function updateTransactions($timestampStart = 0)
	{
		if(!$api = $this->getApi())
			return false;

		//1.баланс
		$balance = $api->getBalance();

		if($balance === false)
		{
			self::$lastError = $api->error;
			return false;
		}

		$this->balance = $balance;

		if(!$timestampStart)
			$timestampStart = $this->date_check - 3600;

		//2.платежи
		$transactions = $api->getHistory($timestampStart, time() + 3600);
		/*
		 	[id] => 568640776497037008
            [amount] => 2787.73
            [direction] => in
            [timestamp] => 1515325576
            [date] => 07.01.2018 14:46
            [label] => 15153202708252
            [status] => success
        )
		 */


		if($transactions === false)
		{
			self::$lastError = $api->error;
			return false;
		}


		$statuses = YandexTransaction::statusArr();

		foreach($transactions as $trans)
		{
			//пропуск добавленных
			if($model = YandexTransaction::model()->findByAttributes(['yandex_id'=>$trans['id']]))
			{
				if($model->status != $trans['status'] or $model->amount != $trans['amount'])
				{
					if(!isset($statuses[$trans['status']]))
					{
						self::$lastError = 'YandexAccount::updateTransactions() неизвестный статус '.$trans['status'].' ИСПРАВИТЬ';
						toLogError(self::$lastError);
						return false;
					}

					$oldStatus = $model->status;
					$model->status = $trans['status'];
					$model->amount = $trans['amount'];

					if($model->save())
					{
						toLog('YandexAccount: сменен статус платежа id='.$model->id.' : '.$oldStatus.' => '.$trans['status']);
					}
					else
					{
						toLogError('YandexAccount: '.self::$lastError.', '.$model->amount.' руб');
						return false;
					}
				}

				continue;
			}

			$model = new YandexTransaction;
			$model->scenario = YandexTransaction::SCENARIO_ADD;
			$model->account_id = $this->id;
			$model->direction = $trans['direction'];
			$model->amount = $trans['amount'];
			$model->title = $trans['title'];
			$model->comment = $trans['label'];
			$model->status = $trans['status'];
			$model->date_add = $trans['timestamp'];
			$model->yandex_id = $trans['id'];

			//только если платежи были после взятия то зачисляем их клиенту
			if($this->date_pick > 0 and $trans['timestamp'] > $this->date_pick)
			{
				$model->user_id = $this->user_id;
				$model->client_id = $this->client_id;
			}

			if(!$model->save())
			{
				self::$lastError = 'ошибка сохранения платежа';
				toLogError('YandexAccount: '.self::$lastError.', '.$model->amount.' руб');
				return false;
			}
		}

		$this->date_check = time();
		return $this->save();
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function modelByAttribute(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	public function getCardNumberStr()
	{
		if(!$this->card_number)
			return  '';

		return substr($this->card_number, 0, 4)
			.' '.substr($this->card_number, 4, 4)
			.' '.substr($this->card_number, 8, 4)
			.' '.substr($this->card_number, 12, 4);
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
	 * скрывает(или отображает) кошелек менеджера(на проверку не влияет)
	 * пишет в self::$msg сообщение об успехе
	 * @param int $id
	 * @param int $managerId
	 * @return bool
	 */
	public static function toggleHidden($id)
	{
		if(!$account = YandexAccount::model()->findByPk($id))
		{
			self::$lastError = 'кошелек не существует';
			return false;
		}

		if($account->hidden)
		{
			$value = 0;
			self::$msg = 'кошелек показан';
		}
		else
		{
			$value = 1;
			self::$msg = 'кошелек скрыт';
		}

		YandexAccount::model()->updateByPk($account->id, array('hidden'=>$value));

		return true;
	}

	//получаем запись о последнем уведомлении в этом месяце, если глобал уже уведомлен - не спамим
	public function getLastNotification()
	{
		return  YandexNotification::model()->findBySql("select * from {{yandex_notification}}
			where date_add=(SELECT MAX(date_add) from {{yandex_notification}} where account_id={$this->id})
			and date_add >".Tools::startOfMonth()." and account_id={$this->id}");
	}

	/**
	 * уведомляем о перелимите
	 */
	public static function notifyOutOfLimit()
	{
//		return false;
		session_write_close();
		$threadName = 'notifyOutOfLimit';

		if(!Tools::threader($threadName))
		{
			echo "\n поток уже запущен";
			return false;
		}

		$config = cfg('telegramNotification');
		/**
		 * @var YandexAccount[] $accounts
		 */
		$accounts = self::model()->findAll([
			'condition' => " `error` ='' AND `date_check` >".(time()-3600)
			]
		);

		$alertLimit = 100000;

		if(!$accounts)
			echo('\n There are no yandex accounts');

		$message = '';
		$receivedAccounts = [];

		foreach($accounts as $account)
		{

//			если лимит меньше порогового - отправляем ссообщение об опасности перелимита
			if($account->limitIn <= $alertLimit)
			{
				if(!$account->lastNotification->id)
				{
					$notification = new YandexNotification;
					$notification->limit_in = $account->limitIn;
					$notification->account_id = $account->id;
					$notification->scenario = YandexNotification::SCENARIO_ADD;
					$notification->save();
				}

				if($account->lastNotification->is_global_notified == 0)
				{
					$message = $message.$account->wallet.' ';
					$receivedAccounts[] = $account;
				}
			}
		}

		if(!$message)
		{
			echo('\n No massages');
			return false;
		}

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->proxyType = 'http';
		$proxy = $config['proxy'];

		foreach($config['receiverMessage'] as $receiver)
		{
			sleep(1);
			$method = 'sendMessage?chat_id='.$receiver.'&text=(ID'.uniqid().'): '.$message.' осталось меньше ' . $alertLimit;
			$url = str_replace([
				'{token}',
				'{method}'
			], [
				$config['token'],
				$method
			], $config['telegramApiUrl']);
			$content = $sender->send($url, false, $proxy);
		}

		if($content)
		{
			foreach($receivedAccounts as $account)
			{
				if($account->lastNotification->is_global_notified == 0)
					$account->lastNotification->notified = 1;
			}
		}
		else
			toLogError('Проверить прокси уведомлений Telegram');

		echo('\n exit');
		return true;
	}
}