<?php
/**
 * @property  Account account
 * @property  string type
 * @property  int account_id
 * @property  User user
 * @property  string error
 * @property  int date_add
 * @property  float amount
 * @property  string qiwi_id
 * @property  string status
 * @property  string comment
 * @property  string wallet
 * @property  string amountStr
 * @property  string statusStr
 * @property  string dateAddStr
 * @property  bool isBadComment
 * @property  int user_id
 * @property  float commission
 * @property  int convert_id 		если > 0 то это конвертация транзакции в таблице TransactionKzt
 * @property  TransactionKzt transactionKzt
 * @property  string transactionKztStr
 * @property  float amnt
 * @property  int date_add_db
 * @property  string dateAddDbStr
 * @property  bool is_rat 			если 1 то платеж - кража(получателя нет в бд и в выводах фина)
 * @property  string qiwiIdStr
 * @property  int client_id 		для ускорения статистики

 */
class Transaction extends Model
{

	const STATUS_SUCCESS = 'success';
	const STATUS_WAIT = 'wait';
	const STATUS_ERROR = 'error';
	
	const TYPE_IN = 'in';
	const TYPE_OUT = 'out';
	
	const SCENARIO_ADD = 'add';

	protected $_accountObj; //кэш аккаунта
	public $amnt ; //нужно при подсчете Account::$dayLimit
	private $_transactionKztObj; //кэш TransactionKzt

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
	
	public function attributeLabels()
	{
		return array(
			
		);
	}

	public function tableName()
	{
		return '{{transaction}}';
	}
	
	public function beforeValidate()
	{
		if(!$this->user_id)
			$this->user_id = '';
		
		$this->amount = str_replace(',', '.', $this->amount);
		$this->commission = str_replace(',', '.', $this->commission);

		if(!$this->convert_id)
			unset($this->convert_id);
		
		return parent::beforeValidate();
	}
		
	public function rules()
	{
		//если уник по qiwi_id, то у одного кошеля эта транзакция входящая а у другого исходящая
		return array(
			array('user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>true),
			array('account_id', 'exist', 'className'=>'Account', 'attributeName'=>'id', 'allowEmpty'=>false),
			array('type', 'in', 'range'=>array_keys(self::typeArr())),
			array('amount', 'numerical', 'allowEmpty'=>false),
			array('status', 'in', 'range'=>array_keys(self::statusArr())),
			array('convert_id', 'exist', 'className'=>'TransactionKzt', 'attributeName'=>'id', 'allowEmpty'=>true),
			array('comment, error, wallet, from_used,qiwi_id,stat_label,commission,date_add,date_add_db', 'safe'),
			array('is_rat', 'safe'),
			['client_id', 'safe'], //пока как можно быстрее чтобы работало
		);
	}
	
	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			if($this->error)
				$this->error = strip_tags($this->error);

			if($this->comment)
				$this->comment = strip_tags($this->comment);

			if($this->wallet)
				$this->wallet = strip_tags($this->wallet);

			$this->date_add_db = time();
		}
		
		return parent::beforeSave();
	}
	
	public static function statusArr($key=false)
	{
		$result = array(
			self::STATUS_SUCCESS => 'успех',
			self::STATUS_WAIT => 'ожидание',
			self::STATUS_ERROR => 'ошибка',
		);
		
		if($key)
			return $result[$key];
		else
			return $result;
	}
	
	public function getStatusStr()
	{
		if($this->status==self::STATUS_SUCCESS)
			return '<font color="green">'.self::statusArr(self::STATUS_SUCCESS).'</font>';
		elseif($this->status==self::STATUS_WAIT)
			return '<font color="orange">'.self::statusArr(self::STATUS_WAIT).'</font>';
		elseif($this->status==self::STATUS_ERROR)
			return '<font color="red">'.self::statusArr(self::STATUS_ERROR).': '.$this->error.'</font>';
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
	
	public function getTypeStr()
	{
		if($this->type==self::TYPE_IN)
			return self::typeArr(self::TYPE_IN);
		elseif($this->type==self::TYPE_OUT)
			return self::typeArr(self::TYPE_OUT);
	}
	
	public function getAmountStr()
	{
		$result = formatAmount($this->amount, 2).' руб';

		if($transKzt = $this->getTransactionKzt())
			$result .= '<br><span class="success smallText">'.formatAmount($transKzt->amount, 0).' тенге</span>';

		return $result;
	}
	
	public function getCommentStr()
	{
		return shortText($this->comment, 80);
	}
	
	public function getErrorStr()
	{
		return shortText($this->error, 80);
	}
	
	public function getDateAddStr()
	{
		return date('d.m H:i:s', $this->date_add);
	}
	
	public function getWalletStr()
	{
		if($this->type==self::TYPE_IN)
			return 'с &nbsp;&nbsp;'.$this->wallet;
		elseif($this->type==self::TYPE_OUT)
			return 'на '.$this->wallet;
	}
	
	public function getUser()
	{
		if($this->user_id)
			return User::model()->findByPk($this->user_id);
	}
	
	public function getUserStr()
	{
		if($this->user_id)
			return $this->getUser()->name;
	}
	
	/**
     * статистика за период по выбранному юзеру или по всем
     * 
     * 
     * $dateFrom = '10.12.2014'
     * $dateTo = '10.12.2014' - не может быть больше 00.00 текущего дня
     * 
     * $fromUsed - если true, то выводит только транзакции с отстойных кошельков,
     * 	иначе - все остальные
     * 
     * return array(
	 * 		'days'=>array(
	 * 			'23.12'=>array(
	 * 				'items'=>array(
	 * 					$model1,
	 * 					$model2,
	 *				),
	 * 				'amount'=>123, 
	 * 				...
	 * 			),
	 * 			.....
	 * 		),
	 * 		'amount'=>общая сумма,
	 * )
     */
    public static function stats($dateFrom, $dateTo, $userId=false, $fromUsed=false)
    {
    	$result = array();
    	
    	if($dateFrom and $dateTo)
		{ 
	    	$dateFrom = strtotime($dateFrom);
	    	$dateTo = strtotime($dateTo)+24*3600;	//до конца дня
	    	
	    	if($userId)
	    		$condUser = " and `user_id`='$userId'";
			else
				$condUser = '';
	    		
    		if($fromUsed)
    			$condUsed = "and `from_used`=1";
   			else
   				$condUsed = "and `from_used`=0";
	    	
	    	$models = self::model()->findAll(array(
				'condition'=>"
					`date_add`>=$dateFrom and `date_add`<$dateTo
					 AND `type`='".self::TYPE_IN."'
					 AND `status`='".self::STATUS_SUCCESS."'
					 $condUsed
					 $condUser
					 ",
				'order'=>"`id` DESC",
			));
			
			$amount = 0;
	    	
	    	if($models)
	    	{
	    		$dayAmount = 0;
	    		
	    		foreach($models as $model)
	    		{
	    			//статистика только по менеджерам
		    		if($user = $model->user and $user->role==User::ROLE_USER)
		    		{
	    				$result['days'][date('d.m', $model->date_add)]['items'][] = $model;
		    			$result['days'][date('d.m', $model->date_add)]['amount'] += $model->amount;
					}
	    		}
	    		
	    		if($result)
	    		{
	    			foreach($result['days'] as $day)
	    				$result['amount'] += $day['amount'];
    			}
	    	}
    	}
    	
    	return $result;
    }
    
    
 	/**
 	 * возвращает сумму пришедших на  IN кошельки платежей за выбранный период
 	 * $fromUsed - запоздалые платежи
 	 */
 	public static function outStats($dateFrom, $dateTo, $clientId, $fromUsed=false)
    {
    	$amount = 0;

		$limit = 2000;
    	
    	if($dateFrom and $dateTo)
		{
    		if($fromUsed)
    			$condUsed = "and `from_used`=1";
   			else
   				$condUsed = "and `from_used`=0";

			if($clientId)
			{
				$users = User::model()->findAll("`role`='".User::ROLE_MANAGER."' and `client_id`='$clientId'");

				$arr = array();

				foreach($users as $user)
					$arr[] = $user->id;

				$condClient = "and `user_id` in(".implode(',', $arr).")";
			}
			else
				$condClient = "";

			$condition = "
					`user_id`>0
					 AND `type`='".self::TYPE_IN."'
					 AND `status`='".self::STATUS_SUCCESS."'
					 AND `date_add` >= $dateFrom and `date_add` < $dateTo
					 $condUsed
					 $condClient
					 ";

			$count = self::model()->count($condition);

			for ($i = 0; $i < ($count / $limit); $i++)
			{
				$models = self::model()->findAll(array(
					'select'=>"`amount`",
					'condition' => $condition,
					'order'=>"`id` DESC",
					'limit' => $limit,
					'offset' => $i * $limit,
				));

				foreach($models as $model)
					$amount += $model->amount;
			}
    	}
    	
    	return $amount;
    }
    
    /**
     * Статистика по переводам финансиста
     * todo: $userId - на случай множества финов
     * 
     */
    public static function finansistStats($timestampFrom, $timestampTo, $clientId)
    {
    	$result = array(
			'models'=>array(),
			'allAmount'=>0,
		);
    	
    	if($timestampFrom and $timestampTo)
		{
	    		
	    	$outAccounts = Account::model()->findAll(array(
				'select'=>"`id`",
				'condition'=>"
					`type`='".Account::TYPE_OUT."'
					AND `client_id`='$clientId'
					AND `enabled`=1
				",
			));
			
			$idArr = array();
			
			foreach($outAccounts as $account)
				$idArr[] = $account->id;
			
			$idArrStr = implode(',', $idArr);

			$result['models'] = self::model()->findAll(array(
				'condition'=>"
					 `type`='".self::TYPE_OUT."'
					 AND `status`='".self::STATUS_SUCCESS."'
					 AND `date_add`>=$timestampFrom and `date_add`<$timestampTo
					 AND `account_id` IN($idArrStr)
					 ",
				'order'=>"`id` DESC",
			));

			foreach($result['models'] as $model)
				$result['allAmount'] += $model->amount;


    	}
    	
    	return $result;
    }
    
    /**
     * Статистика по суммам для менеджера по входящим платежам
	 * возвращает сумму прихода на входящие
     * $statLabel - статистика по выбранной метке
     * 
     */
    public static function managerStats($dateFrom, $dateTo, $userId, $statLabel=false)
    {
    	$result = 0;
    	

    	if($dateFrom and $dateTo and $user = User::getUser($userId))
		{ 
	    	if($statLabel)
	    		$labelCond = " AND `stat_label`='$statLabel'";
    		else
    			$labelCond = "";
   			
   			//если админ то всех юзеров за период 
   			if($user->role==User::ROLE_ADMIN)
    			$userCond = "`user_id`>'0' AND ";
   			else
   				$userCond = "`user_id`='{$user->id}' AND ";
   			
	    	$models = self::model()->findAll(array(
				'condition'=>"
					$userCond   
					 `type`='".self::TYPE_IN."'
					 AND `status`='".self::STATUS_SUCCESS."'
					 AND `date_add`>=$dateFrom and `date_add`<$dateTo 
					 $labelCond
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
	 * стата по манагерам для финансиста
	 */
	public static function myManagerStats($dateFrom, $dateTo, $userId, $statLabel=false)
	{
		$result = 0;

		if($dateFrom and $dateTo and $user = User::getUser($userId) and $user->role == User::ROLE_FINANSIST)
		{
			if($statLabel)
				$labelCond = " AND `stat_label`='$statLabel'";
			else
				$labelCond = "";

			$userCond = "AND `user_id` IN(SELECT `id` FROM `user` WHERE `client_id`='{$user->client_id}')";

			$models = self::model()->findAll(array(
				'condition'=>"
					 `date_add`>=$dateFrom and `date_add`<$dateTo
					 AND `type`='".self::TYPE_IN."'
					 AND `status`='".self::STATUS_SUCCESS."'
					 $labelCond
					 $userCond
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
	 * возвращает массив кошельков пользователя и сумм, принятых на них, за выбранный интервал
	 * возвращает активированные коды
	 * массив
	 * array(
	 * '+79344344324' => 22333,
	 * '+79432432444' => 11233.2,
	 * );
	 */
	public static function controlStatsIn($timestampFrom, $timestampTo, $userId=false, $justSum=false)
	{
		//стата не больше чем за .. дней
		$interval = (YII_DEBUG) ? 3600*24*30 : 3600*24*7;
		$minDate = $timestampTo - $interval;

		if($timestampFrom < $minDate)
			$timestampFrom = $minDate;

		if($justSum)
			$result = 0;
		else
			$result = array();

		$limit = 2000;

		if($userId  and $user = User::getUser($userId) and ($user->role==User::ROLE_MANAGER or $user->role==User::ROLE_FINANSIST))
		{
			$userCond = " AND `user_id`='{$user->id}'";
		}
		else
			$userCond = " AND `user_id`>0";


		if($timestampFrom and $timestampTo)
		{
			$condition = "
					`date_add`>=$timestampFrom and `date_add`<$timestampTo
					 AND `type`='".self::TYPE_IN."'
					 AND `status`='".self::STATUS_SUCCESS."'
					 $userCond
					 ";

			$count = self::model()->count($condition);

			//array('account_id'=>amount)
			$preRes = array();

			for ($i = 0; $i < ($count / $limit); $i++)
			{
				$models = self::model()->findAll(array(
					'select'=>"`amount`,`account_id`",
					'condition' => $condition,
					'order'=>"`id` DESC",
					'limit' => $limit,
					'offset' => $i * $limit,
				));


				if($justSum)
				{
					foreach ($models as $model)
						$result += $model->amount;
				}
				else
				{
					foreach ($models as $model)
						$preRes[$model->account_id] += $model->amount;
				}

			}

			if($preRes)
			{
				foreach($preRes as $accountId=>$amount)
					$result[Account::model()->findByPk($accountId)->login] = $amount;
			}

			//wex
			$coupons = Coupon::getModels($timestampFrom, $timestampTo, $userId, $user->client_id, true);

			foreach($coupons as $coupon)
			{
				if($justSum)
					$result += $coupon->amount;
				else
					$result[$coupon->code] = $coupon->amount;
			}

			//yandex
			$yandexPayments = TransactionWex::getModels($timestampFrom, $timestampTo, $userId, $user->client_id, true);


			foreach($yandexPayments as $payment)
			{
				if($justSum)
					$result += $payment->originalAmount;
				else
					$result['yandex#'.$payment->id] = $payment->originalAmount;
			}

			//new yandex
			$newYandexPayments = NewYandexPay::getModels($timestampFrom, $timestampTo, $userId, $user->client_id, true);


			foreach($newYandexPayments as $payment)
			{
				if($justSum)
					$result += $payment->amount;
				else
					$result['newYandex#'.$payment->id] = $payment->amount;
			}

			//payeer
			$qiwiPayments = QiwiPay::getModels($timestampFrom, $timestampTo, $userId, $user->client_id, true);

			foreach($qiwiPayments as $payment)
			{
				if($justSum)
					$result += $payment->amount;
				else
					$result['qiwiNew#'.$payment->id] = $payment->amount;
			}

			//qiwi 2 merchant
			$qiwiMerchantPayments = MerchantTransaction::getModels($timestampFrom, $timestampTo, $userId, $user->client_id, 0, ['qiwi_wallet', 'qiwi_card']);

			foreach($qiwiMerchantPayments as $payment)
			{
				if($justSum)
					$result += $payment->amount;
				else
					$result['qiwi2#'.$payment->id] = $payment->amount;
			}

			//yandex merchant
			$yadMerchantPayments = MerchantTransaction::getModels($timestampFrom, $timestampTo, $userId, $user->client_id, 0, ['yandex']);

			foreach($yadMerchantPayments as $payment)
			{
				if($justSum)
					$result += $payment->amount;
				else
					$result['merchantYandex#'.$payment->id] = $payment->amount;
			}

			//walletS
			$walletSPayments = WalletSTransaction::getModels($timestampFrom, $timestampTo, $user->client_id, $userId, 'successIn');

			foreach($walletSPayments as $payment)
			{
				if($justSum)
					$result += $payment->amount;
				else
					$result['walletS#'.$payment->id] = $payment->amount;
			}

			//Sim
			$simTransactions = SimTransaction::getModels($timestampFrom, $timestampTo, $user->client_id, $userId, 'successIn');
			foreach($simTransactions as $payment)
			{
				if($justSum)
					$result += $payment->amount;
				else
					$result['sim#'.$payment->id] = $payment->amount;
			}

			//P2pService
			$p2pServiceTransactions = RisexTransaction::getModels($timestampFrom, $timestampTo, $user->client_id, $userId);
			foreach($p2pServiceTransactions as $payment)
			{
				if($justSum)
					$result += $payment->fiat_amount;
				else
					$result['p2pService#'.$payment->id] = $payment->fiat_amount;
			}

			//YandexAccount
			$yandexTransactions = YandexTransaction::getModels($timestampFrom, $timestampTo, $user->client_id, $userId, 'successIn');
			foreach($yandexTransactions as $payment)
			{
				if($justSum)
					$result += $payment->amount;
				else
					$result['yandexWallet#'.$payment->id] = $payment->amount;
			}

		}

		return $result;
	}

	/*
	 * вернет все платежи `type`='out' where `wallet` NOT IN `account`
	 */
	public static function getRatTransactions($timestampFrom, $timestampTo, $clientId=0)
	{
		$clientCond = '';

		if($clientId)
			$clientCond = "AND `account_id` IN(SELECT `id` FROM `account` WHERE `type`='in' AND `client_id`=$clientId AND `date_check`>=$timestampFrom)";

		$transactions = self::model()->findAll(array(
			'condition'=>"
				`date_add`>=$timestampFrom AND `date_add`<$timestampTo
				AND `is_rat`=1
				$clientCond
			",
			'order'=>"`date_add` DESC",
		));

		return $transactions;
	}

	/**
	 * @return Account
	 */
	public function getAccount()
	{
		if(!$this->_accountObj)
			$this->_accountObj = Account::model()->findByPk($this->account_id);

		return $this->_accountObj;
	}

	/*
	 * проверяет является ли транзакция кражей
	 */
	public function isRat()
	{
		if($this->type == self::TYPE_OUT)
		{
			if($this->account->type == Account::TYPE_IN)
			{

			}
		}

		return false;


		/*
		if(
			$this->type==self::TYPE_IN
			and $transaction['type']=='out'
			//and $transaction['wallet'] != '702'//чтобы отмена платежа не была rat trans
			and !Account::model()->count("`login`='{$transaction['wallet']}'")
			and $transaction['wallet'] != cfg('withdraw_account')
			and !self::isTesterTransaction($transaction)
		)
		{
			return true;
		}
		*/
	}


	/*
	 * подробная стата по транзакциям за период по клиенту
	 * return array(
	 * 		in=>array(
	 *
	 *			'amount'=>array(
	 * 				'in'=>1111,
	 * 				'out'=>1111,	//+комиссия
	 * 				'commissionAmount'=>0,
	 * 			),
	 * 		),
	 * 		transit=>array(
	 *
	 *			'amount'=>array(
	 * 				'in'=>1111,
	 * 				'out'=>1111,	//+комиссия
	 * 				'commissionAmount'=>0,
	 * 			),
	 * 		),
	 *		out=>array(
	 *
	 *			'amount'=>array(
	 * 				'in'=>1111,
	 * 				'out'=>1111,	//+комиссия
	 * 				'commissionAmount'=>0,
	 * 			),
	 * 		),
	 *
	 *
	 * 		'transactions'=>array(	//все платежи за эту дату(без фильтров)
	 * 			model1, model2, model3...
	 * 		),
	 * )
	 */
	public static function transactionStats($timestampStart, $timestampEnd, $clientId)
	{
		$result = array(
			'amountIn'=>array(),
			'amountTransit'=>array(),
			'amountOut'=>array(),
		);

		//выбрать все аккаунты клиента
		$accounts = Account::model()->findAll(array(
			'select'=>'id',
			'condition'=>"
				`client_id`=$clientId
				AND `enabled`=1
			",
		));

		$accountStr = '';

		foreach($accounts as $account)
			$accountStr .= $account->id.',';
echo $timestampStart.' '.$timestampEnd;
		//выбрать все транзакции клиента
		$transactions = Transaction::model()->findAll(array(
			'select'=>"`account_id`,`status`,`amount`,`type`",
			'condition'=>"
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				AND `account_id` IN(".trim($accountStr, ',').")
			",
			'order'=>"`id`",
		));

		//общие суммы
		foreach($transactions as $trans)
		{
			if($trans->status == Transaction::STATUS_SUCCESS)
			{
				$result[$trans->account->type]['amount'][$trans->type] += $trans->amount;
				$result[$trans->account->type]['amount']['commissionAmount'] += $trans->commission;


			}

			$result['transactions'][] = $trans;
		}

		//по каждому кошельку
		foreach($transactions as $trans)
		{
			if ($trans->status == Transaction::STATUS_SUCCESS)
			{
				//$result[$trans->account->type]['amount'][$trans->type] += $trans->amount;
				//$result[$trans->account->type]['amount']['commissionAmount'] += $trans->commission;
			}
		}

		//костыль для транзитных, пока нет транзакций


		unset($transactions);
		return $result;
	}

	/**
	 *
	 * последние платежи входящие платежи на входящие кошельки
	 * @param bool $onlyWithComments
	 * @return self[]
	 */
	public static function getLastTransactionsIn($onlyWithComments = false)
	{
		$limit = 1000;	//чтобы небыло утечек памяти
		$timestamp = time() - 3600*24;	//за последние сутки

		$commentCond = ($onlyWithComments) ? " AND `comment`!=''" : '';

		$models = self::model()->findAll(array(
			'condition'=>"`date_add` > $timestamp AND `type`='".self::TYPE_IN."' AND `user_id`>0".$commentCond,
			'limit'=>$limit,
			'order'=>"`date_add` DESC, `account_id` DESC",
		));

		/**
		 * @var self[] $models
		 */

		self::$someData['stats']['badCommentCount'] = 0;

		foreach($models as $model)
			if($model->isBadComment)
				self::$someData['stats']['badCommentCount']++;

		return $models;
	}

	/**
	 * если комментарий содержит запрещенные слова
	 */
	public function getIsBadComment()
	{
		if(!$this->comment)
			return false;

		foreach(self::getBadWordsArr() as $regExp)
			if(preg_match($regExp, $this->comment))
				return true;

		return false;
	}

	/**
	 * сохраняет плохие слова в файл
	 * @param string $content
	 * @return bool
	 */
	public static function setBadWordsContent($content)
	{
		if(file_put_contents(cfg('badWordsFile'), $content)!==false)
			return true;
		else
		{
			self::$lastError = 'ошибка записи в файл';
			return false;
		}
	}

	/**
	 * возвращает плохие слова в файл
	 * @return string
	 */
	public static function getBadWordsContent()
	{
		if(file_exists(cfg('badWordsFile')))
			return file_get_contents(cfg('badWordsFile'));
		else
			return '';
	}

	/**
	 * возвращает плохие слова массивом
	 * @return array
	 */
	public static function getBadWordsArr()
	{
		$result = array();

		$explode = explode(PHP_EOL, self::getBadWordsContent());

		foreach($explode as $val)
			if(!empty($val))
				$result[] = trim($val);

		return $result;
 	}

	/**
	 * последние платежи с плохим комментом или с ограничением
	 * @return self[]
	 */
	public static function getBadTransactionsIn()
	{
		$result = array();

		$models = self::getLastTransactionsIn();

		foreach($models as $model)
		{
			if($model->isBadComment or $model->status == self::STATUS_ERROR)
				$result[] = $model;
		}

		self::$someData['stats']['count'] = count($result);

		return $result;
	}


	/**
	 * если convert_id > 0 то вернуть эту трансу
	 * @return TransactionKzt:false
	 */
	public function getTransactionKzt()
	{
		if($this->convert_id)
		{
			if($this->_transactionKztObj)
				return $this->_transactionKztObj;

			$this->_transactionKztObj = TransactionKzt::model()->findByPk($this->convert_id);
			return $this->_transactionKztObj;
		}
		else
			return false;
	}

	/**
	 * @return string
	 */
	public function getTransactionKztStr()
	{
		$trans = $this->getTransactionKzt();

		return ($trans) ? "конвертация {$trans->amount} KZT" : '';
	}

	/**
	 * используется в антибане
	 * поиск кошельков на которые были успешно сделаны платежи с $login
	 * возвращает только активные кошельки!
	 * todo: несколько уровней поиска и не только входящие
	 * @param string $login
	 * @return Account[]|null
	 */
	public static function linkSearch($login)
	{
		$timestampStart = time() - 3600*24*30*7;	//7 месяцев

		$result = [];

		$login = '+' . trim($login, '+ ');

		if (!preg_match(cfg('wallet_reg_exp'), $login)) {
			self::$lastError = 'wrong login';
			return null;
		}

		$transactions = self::model()->findAll([
			'condition' => "`type`='" . self::TYPE_IN . "' AND `wallet`='$login' AND `date_add`>$timestampStart",
			'group' => '`account_id`',
		]);

		/**
		 * @var self[] $transactions
		 */

		foreach($transactions as $transaction)
		{
			$account = $transaction->account;

			if(
				($account->limit_in > 2 or $account->balance >= 2)
				and !$account->error
				and !$account->date_used
				and !$account->is_old
				and ($account->limit_in < 190000 or $account->limit_out < 190000)
			)
			$result[] = $transaction->account;

		}

		return $result;
	}

	public function getDateAddDbStr()
	{
		return ($this->date_add_db) ? date('d.m.Y H:i', $this->date_add_db) : '';
	}

	/**
	 * проверка на бан кошелька клиента
	 * если в базе есть платеж с Огром с этого кошелька (wallet=$login) то вернет true
	 * @param string $login
	 * @param int $timestampStart
	 * @return bool
	 */
	public static function banCheck($login, $timestampStart = 0)
	{
		$model = self::model()->find(
			"
			`date_add`>$timestampStart
			AND `wallet`='$login'
			AND `error`='Ограничение на исходящие платежи'
			AND `user_id`>0
		");

		if($model)
			return true;
		else
			return false;
	}

	/**
	 * если это конвертация то вернуть qiwi_id оригинального платежа
	 * @return string
	 */
	public function getQiwiIdStr()
	{
		if($transKzt = $this->getTransactionKzt())
			return $transKzt->qiwi_id;
		else
			return $this->qiwi_id;
	}

	/**
	 * @param int $id
	 * @return self
	 */
	public static function modelByPk($id)
	{
		return self::model()->findByPk($id);
	}
}