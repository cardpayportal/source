<?php
/**
 *
 * @property int id
 * @property string transaction_id
 * @property int merchant_wallet_id
 * @property int client_id
 * @property int user_id
 * @property string merchant_user_id
 * @property string wallet
 * @property MerchantWallet merchantWallet
 * @property string sender
 * @property float amount
 * @property string status
 * @property string statusStr
 * @property string typeStr
 * @property string amountStr
 * @property string walletStr
 * @property string commentStr
 * @property string src_user
 * @property string dest_user
 * @property string type
 * @property string comment
 * @property string error
 * @property int date_add
 * @property int dateAddStr
 * @property float commision
 * @property int date_add_db
 * @property  float amnt
 */

class MerchantTestTransaction extends Model
{
	const STATUS_APPROVED = 'APPROVED';
	const STATUS_SUCCESS = 'success';
	const STATUS_WAIT = 'wait';
	const STATUS_ERROR = 'error';

	const TYPE_IN = 'in';
	const TYPE_OUT = 'out';

	const SCENARIO_ADD = 'add';
	protected static $bot;
	public $amnt ; //нужно при подсчете Account::$dayLimit

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'login'=>'Логин',
		);
	}

	public function rules()
	{
		return [
			['transaction_id', 'unique', 'className'=>__CLASS__, 'attributeName'=>'transaction_id', 'on'=>self::SCENARIO_ADD, 'message'=>'транзакция уже существует'],
			['client_id, user_id, merchant_user_id, wallet, amount, comment, sender, type, src_user, dest_user', 'safe'],
			['status, error, date_add, commision, date_add_db, merchant_wallet_id, transaction_id', 'safe'],
		];

	}

	public function tableName()
	{
		return '{{merchant_transaction}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();

	}

	/**
	 * @return MerchantWallet[]
	 */
	public function getMerchantWallet()
	{
		return MerchantWallet::model()->find("`id`='{$this->merchant_wallet_id}'");
	}

	protected static function getBot($test = false)
	{
		if($test)
			$config = cfg('qiwiMerchantTest');
		else
			$config = cfg('qiwiMerchant');

		if(!self::$bot)
			self::$bot = new QiwiMerchantApi($config['clienId'], $config['clienSecret'], $config['proxy'], $test);

		return self::$bot;
	}

	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	public static function getAll()
	{
		return self::model()->findAll();
	}

	/**
	 * @param $userId
	 *
	 * @return int
	 *
	 * в отличие от updateUserTransactions получаем инфу по всем транзам мерчанта
	 */
	public static function updateMerchantTransactions()
	{
		$bot = self::getBot();

		$responce = $bot->getMerchantTransactions();

//		toLogRuntime('responce :'.arr2str($responce));

		//кол-во обновленных транз
		$count = 0;
		//добавочные транзакции
		$pushedCount = 0;

		if($responce)
		{
			$total = $responce['total'];
			$transactions = $responce['transactions'];
			$countTransactionsInBase = count(self::getAll());

			//если у нас есть еще не обновленные транзакции
			if($total > $countTransactionsInBase)
			{
				$countToUpdate = $total - $countTransactionsInBase;
				//проверяем сколько итераций запросов нужно выполнить для обновления всех транзакций
				$countIterations = ceil($countToUpdate/100);

				$startPos = 0;

				for($iteration = 0; $iteration < $countIterations; $iteration++)
				{
					sleep(10);
					$responce = $bot->getMerchantTransactions($startPos, $startPos+100);
					$startPos += ($startPos + 100);
					if(!$bot::$lastError and $responce)
					{
						foreach($responce['transactions'] as $transaction)
						{
							if(@array_push($transactions, $transaction))
								$pushedCount++;
						}

					}
				}
			}
			//prrd($pushedCount);
			$paymentsArr = [];

			foreach($transactions as $key=>$transaction)
			{
				if($transaction['src_user'] !== 'SYSTEM' and ($transaction['protocol_type'] == 'TRANSFER'  or  $transaction['protocol_type'] == 'CARD'))
				{
					$paymentsArr[$key]['src_user'] = $transaction['src_user'];
					$paymentsArr[$key]['dest_user'] = $transaction['dest_user'];
					$paymentsArr[$key]['status'] = $transaction['tx_status'];
					$paymentsArr[$key]['amount'] = $transaction['amount'];
					$paymentsArr[$key]['time'] = strtotime($transaction['ctime']);
					$paymentsArr[$key]['from'] = $transaction['source_address'];
					$paymentsArr[$key]['to'] = $transaction['dest_address'];
					$paymentsArr[$key]['transaction_id'] = $transaction['_id']?$transaction['_id']:'';
					$paymentsArr[$key]['type'] = $transaction['protocol_type'];
					$paymentsArr[$key]['comment'] = '';

					if($transaction['note'] !== 'AUTOCREDIT:undefined')
					{
						$paymentsArr[$key]['comment'] = str_replace('AUTOCREDIT:','', $transaction['note']);
					}
				}
			}

//			toLogRuntime(arr2str($paymentsArr));
//			prrd($paymentsArr);

			if($paymentsArr)
			{
				foreach($paymentsArr as $payment)
				{
					//todo: тут бы предусмотреть обновляемые поля

					if(self::model()->findByAttributes(['transaction_id'=>$payment['transaction_id']]))
						continue;

					/**
					 * @var self $model
					 */
					$model = new MerchantTransaction();
					$model->scenario = self::SCENARIO_ADD;

					$model->amount = $payment['amount'];
					$model->wallet = $payment['to'];
					$model->sender = $payment['from'];
					$model->date_add = $payment['time'];
					$model->date_add_db = time();

					/**
					 * @var MerchantUser $merchantUser
					 */
					if($merchantUser = MerchantUser::model()->findByAttributes(['internal_id'=>$payment['src_user']]))
					{

						$model->user_id = $merchantUser->uni_user_id;
						$model->client_id = $merchantUser->uni_client_id;
						$model->merchant_user_id = $merchantUser->id;
						$model->type = self::TYPE_OUT;
					}

					//помечаем входящий платеж
					if($merchantUser = MerchantUser::model()->findByAttributes(['internal_id'=>$payment['dest_user']]))
					{
						$model->user_id = $merchantUser->uni_user_id;
						$model->client_id = $merchantUser->uni_client_id;
						$model->merchant_user_id = $merchantUser->id;
						$model->type = self::TYPE_IN;
					}

					if($payment['dest_user'])
						$model->src_user = $payment['dest_user'];

					if($payment['src_user'])
						$model->src_user = $payment['src_user'];

					if($payment['transaction_id'])
						$model->transaction_id = $payment['transaction_id'];

					if($merchantWallet = MerchantWallet::model()->findByAttributes(['login'=>$payment['to']]))
						$model->merchant_wallet_id = $merchantWallet->id;

					$model->comment = $payment['comment'];
					if($model->save())
						$count++;

				}
			}
		}
		else
		{
			toLogError('ошибка обновления транзакций '.$bot::$lastError.' '.$responce);
		}

		return $count;

	}

	/**
	 * @param $userId
	 *
	 * @return bool
	 */
	public static function updateUserTransactions($userId)
	{
		$bot = self::getBot();
		$count = 0;
		$responce = $bot->getUserTransactions($userId);

		if(!$bot::$lastError and $responce)
		{
			/**
			 * @var MerchantUser $merchantUser
			 */
			$merchantUser = MerchantUser::model()->findByAttributes(['internal_id'=>$userId]);
			if(!$merchantUser)
				return false;

			$total = $responce['total'];
			$transactions = $responce['transactions'];

			$paymentsArr = [];

			foreach($transactions as $key=>$transaction)
			{
				if($transaction['src_user'] !== 'SYSTEM' and ($transaction['protocol_type'] == 'TRANSFER'  or  $transaction['protocol_type'] == 'CARD'))
				{
					$paymentsArr[$key]['src_user'] = $transaction['src_user'];
					$paymentsArr[$key]['status'] = $transaction['tx_status'];
					$paymentsArr[$key]['amount'] = $transaction['amount'];
					$paymentsArr[$key]['time'] = strtotime($transaction['ctime']);
					$paymentsArr[$key]['from'] = $transaction['source_address'];
					$paymentsArr[$key]['to'] = $transaction['dest_address'];
					$paymentsArr[$key]['transaction_id'] = $transaction['_id'];
					$paymentsArr[$key]['type'] = $transaction['protocol_type'];
					$paymentsArr[$key]['comment'] = '';

					if($transaction['note'] !== 'AUTOCREDIT:undefined')
					{
						$paymentsArr[$key]['comment'] = str_replace('AUTOCREDIT:','', $transaction['note']);
					}
				}
			}

			if($paymentsArr)
			{
				foreach($paymentsArr as $payment)
				{
					//todo: тут бы предусмотреть обновляемые поля

					if(self::model()->findByAttributes(['transaction_id'=>$payment['transaction_id']]))
						continue;

					/**
					 * @var self $model
					 */
					$model = new MerchantTransaction();
					$model->scenario = self::SCENARIO_ADD;
					$model->amount = $payment['amount'];
					$model->wallet = $payment['to'];
					$model->sender = $payment['from'];
					$model->date_add = $payment['time'];
					$model->date_add_db = time();
					$model->user_id = $merchantUser->uni_user_id;
					$model->client_id = $merchantUser->uni_client_id;
					$model->transaction_id = $payment['transaction_id'];

					if($merchantWallet = MerchantWallet::model()->findByAttributes(['login'=>$payment['to']]))
						$model->merchant_wallet_id = $merchantWallet->id;

					$model->merchant_user_id = $merchantUser->id;
					$model->comment = $payment['comment'];
					if($model->save())
						$count++;
				}
			}
		}
		else
		{
			toLogError('ошибка обновления транзакций '.$bot::$lastError.' '.$responce);
		}
		return $count;

	}

	public static function getTransactions()
	{
		return self::model()->findAll();
	}

	/**
	 * @param $timestampStart
	 * @param $timestampEnd
	 * @param int $clientId
	 * @param int $userId
	 * @return array
	 */
	public static function getStats($timestampStart, $timestampEnd, $clientId = 0, $userId = 0, $walletType = [])
	{
		//TODO: убрать массив при финальном запуске
		if(count($walletType) <= 0)
		{
			$walletType = array(
				'qiwi_wallet',
				'qiwi_card',
				'yandex'
			);
		}

		$result = [
			'amountIn'=>0,
			'amountOut'=>0,
		];

		$timestampStart *= 1;
		$timestampEnd *= 1;

		//либо по юзеру либо по клиенту
		$userCond = ($userId) ? " and mt.user_id ='$userId'" :
			(($clientId) ? " and mt.client_id ='$clientId'" : '');

		//необходимо для разделения статистики по киви и яду
		$typeCond = " ('".implode("', '", $walletType)."') ";

		$transactions = self::model()->findAllBySql('
				select mt.* from merchant_transaction as mt
				inner join merchant_wallet as mw
				on mt.merchant_wallet_id = mw.id '.$userCond.'
				and mt.date_add >='.$timestampStart.' and mt.date_add <'.$timestampEnd.'
				and mw.type in '.$typeCond.' and mt.type ="in" and mt.status ="success"
				and mt.client_id <>"0" and mt.user_id <>"0"'
		);

		//prrd($transactions);

		/**
		 * @var MerchantTransaction[] $transactions
		 */

		foreach($transactions as $trans)
		{
			if($trans->status === self::STATUS_SUCCESS)
			{
				$result['amountIn'] += $trans->amount;
			}
		}

		return $result;
	}

	/**
	 * @param self[] $models
	 * @return array
	 *
	 * важно передавать модели по киви и яду отдельными запросами
	 */
	public static function getStatsUser($models)
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

			if($model->status == self::STATUS_SUCCESS and $model->type == 'in')
			{
				$result['amount'] += $model->amount;
				$result['countSuccess']++;
			}

			$result['allAmount'] += $model->amount;
		}

		return $result;
	}

	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $clientId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return self[]
	 */
	public static function getModels($timestampStart=0, $timestampEnd=0, $userId = 0, $clientId = 0, $walletId = 0, $walletType=[])
	{
		//TODO: убрать массив при финальном запуске
		if(count($walletType) <= 0)
		{
			$walletType = array(
				'qiwi_wallet',
				'qiwi_card',
				'yandex'
			);
		}

		$intervalMax = 3600 * 24 * 365;

		$userId *= 1;
		$clientId *= 1;

		$timestampStart *= 1;
		$timestampEnd *= 1;

		if($timestampEnd > time())
			$timestampEnd = time();

		if($timestampEnd - $timestampStart > $intervalMax)
		{
			$timestampStart = $timestampEnd - $intervalMax;
			//self::$lastError = 'максимальный интервал статистики: 30 дней';
			//return [];
		}

		$walletCond = '';

		if($walletId)
			$walletCond = " and mt.merchant_wallet_id=$walletId ";

		//либо по юзеру либо по клиенту
		$userCond = ($userId) ? " and mt.user_id ='$userId'" :
			(($clientId) ? " and mt.client_id ='$clientId'" : '');

		//необходимо для разделения статистики по киви и яду
		$typeCond = " ('".implode("', '", $walletType)."') ";

		$models=MerchantTransaction::model()->findAllBySql('
				select mt.* from merchant_transaction as mt
				inner join merchant_wallet as mw
				on mt.merchant_wallet_id = mw.id '.$userCond.$walletCond.'
				and mt.date_add >='.$timestampStart.' and mt.date_add <'.$timestampEnd.'
				and mw.type in '.$typeCond.' and mt.type ="in" and mt.status ="success"
				and mt.client_id <>"0" and mt.user_id <>"0" order by mt.date_add desc'
		);

		return $models;
	}

	public function getTypeStr()
	{
		if($this->type==self::TYPE_IN)
			return self::typeArr(self::TYPE_IN);
		elseif($this->type==self::TYPE_OUT)
			return self::typeArr(self::TYPE_OUT);
	}

	public static function typeArr($key=false)
	{
		$result = array(
			self::TYPE_IN => 'приход',
			self::TYPE_OUT => 'расход',
		);

		if($key)
			return $result[$key];
		else
			return $result;
	}

	public function getAmountStr()
	{
		return formatAmount($this->amount, 2).' руб';
	}

	public function getWalletStr()
	{
		if($this->type==self::TYPE_IN)
			return 'с &nbsp;&nbsp;'.$this->sender;
		elseif($this->type==self::TYPE_OUT)
			return 'на '.$this->sender;
	}

	public function getCommentStr()
	{
		return shortText($this->comment, 80);
	}

	/**
	 * Статистика по суммам для менеджера по входящим платежам
	 * возвращает сумму прихода на входящие
	 *
	 * получаем статистику по принятому массиву типов
	 * $walletType = array(
			'qiwi_wallet',
	  		'qiwi_card',
	  		'yandex'
	  )
	 */
	public static function managerStats($dateFrom, $dateTo, $userId, $walletType=[])
	{
		//TODO: убрать массив при финальном запуске
		if(count($walletType) <= 0)
		{
			$walletType = array(
				'qiwi_wallet',
				'qiwi_card',
				'yandex'
			);
		}

		if(!is_array($walletType))
			return false;

		$result = 0;

		if($dateFrom and $dateTo and $user = User::getUser($userId))
		{
			//необходимо для разделения статистики по киви и яду
			$typeCond = " ('".implode("', '", $walletType)."') ";

			//если админ то всех юзеров за период
			if($user->role==User::ROLE_ADMIN)
				$userCond = " and mt.user_id <>'0' ";
			else
				$userCond = " and mt.user_id = '{$user->id}' ";

			$models=MerchantTransaction::model()->findAllBySql('
				select `amount` from merchant_transaction as mt
				inner join merchant_wallet as mw
				on mt.merchant_wallet_id = mw.id '.$userCond.'
				and mt.date_add>='.$dateFrom.' and mt.date_add<'.$dateTo.'
				and mw.type in '.$typeCond
			);

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


	public function getUser()
	{
		if($this->user_id)
			return User::model()->findByPk($this->user_id);
	}

	public function getUserStr()
	{
		if($user = $this->getUser())
			return $user->name;
	}

}