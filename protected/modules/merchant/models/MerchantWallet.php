<?php
/**
 *
 * @property int id
 * @property int client_id
 * @property string login
 * @property string type
 * @property int user_id
 * @property float amount_in
 * @property float limit_in
 * @property float limit_out
 * @property float balance
 * @property string balanceStr
 * @property string amountStr
 * @property int date_check
 * @property string error
 * @property User user
 * @property string label
 * @property MerchantTransaction[] transactionsManager
 * @property string status
 * @property int date_add
 * @property int date_used
 * @property int date_out_of_limit
 * @property string comment
 * @property int enabled
 * @property int hidden
 * @property string orderMsg
 * @property int day_limit
 * @property string merchant_user_id
 * @property string merchant_user_internal_id
 * @property string internal_wallet_id
 * @property string wallet_name
 * @property string card_number
 * @property MerchantTransaction lastTransaction
 *
 */

class MerchantWallet extends Model
{
	protected static $bot;
	public $cacheTransactionsManager = [];
	const DAY_LIMIT_MAX  = 100000;	//в день можно прогнать через кош
	const DAY_LIMIT_MAX_YANDEX  = 1000000;	//в день можно прогнать через кош яда
	const ERROR_OUT_OF_LIMIT = 'out_of_limit';
	const ERROR_LIMIT_OUT = 'limit_out'; //ошибка платежа превышен лимит исходящих транзакций
	const TYPE_YANDEX = 'yandex';
	const TYPE_QIWI_WALLET = 'qiwi_wallet';
	const TYPE_QIWI_CARD = 'qiwi_card'; //когда есть и номер киви и виртуальная карта

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
		);
	}

	public function rules()
	{
		return [
			['client_id, login, type, user_id, amount_in, limit_in, limit_out', 'safe'],
			['balance, date_check, error, label, status, date_add, date_out_of_limit', 'safe'],
			['comment, enabled, hidden, day_limit, merchant_user_id, internal_wallet_id, date_used, wallet_name, card_number', 'safe'],
		];

	}

	public function tableName()
	{
		return '{{merchant_wallet}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();

	}

	public static function getBot($test = false)
	{
		if($test)
			$config = cfg('qiwiMerchantTest');
		else
			$config = cfg('qiwiMerchant');

		if(!self::$bot)
			self::$bot = new MerchantApi($config['clienId'], $config['clienSecret'], $config['proxy'], $test);

		return self::$bot;
	}

	public static function getAll()
	{
		return self::model()->findAll();
	}

	/**
	 * @param $params
	 * @return mixed
	 *
	 * добавление и обновление информации о кошельках мерчанта
	 */
	public static function addInfo()
	{
		$bot = MerchantWallet::getBot();
		$walletData = $bot->walletList();

		if(!$bot::$lastError)
		{
			$freeCardsArr = $bot->fetchDirectCards();
			if($walletData)
			{
				// нужен будет для сверки существования кошельков
				$externalWalletArr = [];

				foreach($walletData as $wallet)
				{
					$externalWalletArr[] = $wallet['tel'];
					/**
					 * @var MerchantUser $merchantUser
					 */
					$merchantUser = MerchantUser::model()->findByAttributes(['internal_id'=>$wallet['merchant_user_id']]);

					if($model = MerchantWallet::model()->findByAttributes(['login'=>$wallet['tel']]))
					{
						/**
						 * @var MerchantWallet $model
						 */

						$lastTimeSync = strtotime($wallet['last_sync_date']);

						//перекрываем баг с некорректной датой обновления
						if($model->date_check < $lastTimeSync)
						{
							$model->date_check = $lastTimeSync;
						}

						$model->balance = $wallet['rub'];
						$model->merchant_user_internal_id = $wallet['merchant_user_id'];
						$model->internal_wallet_id = $wallet['_id'];

						if(preg_match('!(\d{15,16})!', $wallet['tel'], $res))
							$model->type = self::TYPE_YANDEX;
						elseif(preg_match('!(\d{11})!', $wallet['tel'], $res) and $model->card_number == '')
							$model->type = self::TYPE_QIWI_WALLET;
						elseif(preg_match('!(\d{11})!', $wallet['tel'], $res) and $model->card_number != '')
							$model->type = self::TYPE_QIWI_CARD;

						if($merchantUser)
						{
							$merchantUser->balance_qiwi = $wallet['rub'];
							$model->user_id = $merchantUser->uni_user_id;
							$model->client_id = $merchantUser->uni_client_id;
							$model->merchant_user_id = $merchantUser->id;
//							$model->enabled = 1;
//							$model->date_used = 0;
//							$model->hidden = 0;
						}
						else
						{
							$model->user_id = 0;
							$model->client_id = 0;
							$model->merchant_user_id = 0;
//							$model->enabled = 0;
							$model->hidden = 1;
						}

						if($wallet['qiwi_blocked'] == 1)
							$model->error = 'blocked';

						if($freeCardsArr)
						{
							foreach($freeCardsArr as $card)
							{
								if($card['wallet_name'] == $wallet['wallet_name'])
									$model->card_number = $card['card_number'];
							}
						}

						$model->update();
						continue;
					}

					$model = new MerchantWallet;
					$model->login = $wallet['tel'];
					$model->merchant_user_internal_id = $wallet['merchant_user_id'];
					$model->date_add = time();
					$model->date_check = strtotime($wallet['last_sync_date']);
					$model->balance = $wallet['rub'];
					$model->status = 'full';
					$model->limit_in = 2000000;
					$model->limit_out = 2000000;

					if($freeCardsArr)
					{
						foreach($freeCardsArr as $card)
						{
							if($card['wallet_name'] == $wallet['wallet_name'])
							{
								toLogRuntime('Добавлена информация о карте');
								$model->card_number = $card['card_number'];
							}
						}
					}

					$model->wallet_name =  $wallet['wallet_name'];
					if($merchantUser)
					{
						$model->user_id = $merchantUser->uni_user_id;
						$model->client_id = $merchantUser->uni_client_id;
						$model->merchant_user_id = $merchantUser->id;
						$model->enabled = 1;
						$model->date_used = 0;
						$merchantUser->balance_qiwi = $wallet['rub'];
						$merchantUser->save();
					}

					$model->save();
				}

//				$existBaseWallets = self::getAll();

				/**
				 * убираем отображение кошельков которых уже не существует
				 */
//				if(count($externalWalletArr) > 0)
//				{
//					/**
//					 * @var MerchantWallet[] $existBaseWallets
//					 */
//					foreach($existBaseWallets as $wallet)
//					{
//						toLogRuntime('$externalWalletArr: '.arr2str($externalWalletArr));
//						toLogRuntime('$existBaseWallets: '.arr2str($existBaseWallets));
//						if(!in_array($wallet->login, $externalWalletArr))
//						{
//							$wallet->hidden = 0;
//							$wallet->comment = '';
//							$wallet->save();
//						}
//					}
//				}
			}

			return true;
		}
		else
			return false;
	}

	/**
	 * @param $userId
	 *
	 * @return bool
	 *
	 * удаляем пользователя в базе и в сервисе
	 */
	public static function deleteUser($userId)
	{
		$bot = self::getBot();

		$model = self::model()->findByAttributes(['internal_id'=>$userId]);

		if(!$model)
		{
			$errorMessage = 'Не найден пользователь с internal_id = '.$userId;
			self::$lastError = $errorMessage;
			toLogError($errorMessage);
			return false;
		}

		$responce = $bot->deleteUser($userId);

		if(!$bot::$lastError)
		{
			if($responce['message'] == 'User has been deleted')
			{
				$model->delete();
				return true;
			}
			else
				return false;
		}
		else
			return false;
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

	/**
	 * @param $id
	 *
	 * @return bool
	 * юзеру будет назначен свободный валидный кош автоматом
	 */
//	public static function assignWallet($id)
//	{
//		/**
//		 * @var MerchantUser $model
//		 */
//		$model = self::model()->findByPk($id);
//
//		if(!$model)
//			return false;
//
//		$bot = self::getBot();
//
//		$responce = $bot->assignWallet($model->internal_id);
//
//		if(!$bot::$lastError and $responce)
//		{
//			if($responce['message'] == 'Successfully allocated wallet to Merchant user')
//				return true;
//			else
//				return false;
//		}
//		else
//			return false;
//
//	}

	/**
	 * @return MerchantTransaction[]
	 */
	public function getTransactionsManager()
	{
		if($this->cacheTransactionsManager)
			return $this->cacheTransactionsManager;

		$models = [];

		if ($user = $this->getUser())
		{
			//для менеджера
			$models = MerchantTransaction::model()->findAll(array(
				'condition' => "
						`wallet`='{$this->login}'
						AND `user_id`='{$user->id}'
						AND `status`='success'
						AND `type`='in'
						AND `client_id` <> 0
					",
				'order' => "`date_add` DESC",
			));
		}

		return $models;
	}

	public function getAmountStr()
	{
		return formatAmount($this->getInAmount(), 0);
	}

	/**
	 * amount по сумме входящих транзакций  с даты пика
	 * использовать только для отображения менеджерам!!!!
	 */
	public function getInAmount()
	{
		$result = 0;

		$transactions = MerchantTransaction::model()->findAll([
			'select'=>"`amount`",
			'condition'=>"
				`merchant_wallet_id`='{$this->id}'
				AND `status`='success'
				AND `type`='in'
				AND `client_id` <> 0
				AND `user_id` <> 0
			",
		]);

		foreach ($transactions as $model)
			$result += $model->amount;

		$this->amount_in = $result;
		$this->limit_in = $this->getMonthLimit();
		$this->save();

		return $result;
	}

	public function getBalanceStr()
	{
		$currencyStr = ' руб';

		return formatAmount($this->balance, 2)." $currencyStr";
	}

	/**
	 * последняя транзакция кошелька(входящая или исходящая)
	 * $type = 'in' or 'out'
	 * @param string|null $type
	 * @param int|null $timestampFrom
	 * @return Transaction
	 */
	public function getLastTransaction($type = null, $timestampFrom = null)
	{
		$typeCondition = '';

		if($type=='in')
			$typeCondition = " and `type`='in'";
		elseif($type=='out')
			$typeCondition = " and `type`='out'";

		$dateCondition = '';

		if($timestampFrom)
			$dateCondition = " and `date_add`>=$timestampFrom";

		return MerchantTransaction::model()->find(array(
			'condition' => "`merchant_wallet_id`='{$this->id}'".$typeCondition.$dateCondition,
			'order' => "`date_add` DESC",
		));
	}

	/*
	 * дата последнего прихода на кошель
	 */
	public function getDateLastTransactionInStr()
	{
		if($trans = $this->getLastTransaction(MerchantTransaction::TYPE_IN))
			return date('d.m.Y H:i', $trans->date_add);
		else
			return '';
	}

	/**
	 * помечает кошель для отправки в отстойник через 12ч
	 * отправляет в отстойник помеченные кошельки
	 * если лимит меньше 100к или error=='ban' или error=='out_of_limit' то помечает к отправке в отстойник через 12ч
	 * если кошель давно не использовался, но не нулячий, то тоже помечается в отстойник
	 *
	 * new: автозавершение заявок, проверка кошелька на наличие в 2х открытых заявках
	 * new: сброс wallets_count в полночь
	 *
	 */
	public static function startCheckUsed()
	{
		$threadName = 'checkUsed';

		if(!Tools::threader($threadName))
			die('поток уже запущен');

		//проверено кошелей из отстойника
		$done = 0;

		//отпрвлять в отстойник после 12ч превышения лимита
		$markUsedInterval = config('account_mark_used_interval');
		$markUsedLimit = config('account_mark_used_after');
		$transactionDateLimit = time() - cfg('account_last_used_interval');
		$priorityStdDate = time() - cfg('priorityStdlastTransInterval');

		$models = self::model()->findAll(array(
			'condition' => "
				`user_id`!=0
				AND `date_used`=0
				AND `enabled` = 1
			",
		));

		/**
		 * @var MerchantWallet[] $models
		 */

		foreach ($models as $model)
		{
			// у мерчантов один лимит account_in_safe_limit

			$maxLimit = cfg('account_in_safe_limit');

			$inAmount = $model->getInAmount();

			if(
				$model->date_out_of_limit
				and !$model->date_used
				and $model->limit_in > $markUsedLimit
				and !$model->error
				and $model->lastTransaction->date_add > $transactionDateLimit
				and $inAmount < $maxLimit
			)
			{
				self::model()->updateByPk($model->id, array(
					'date_out_of_limit' => 0,
				));
			}

			if($model->date_out_of_limit and time() - $model->date_out_of_limit > $markUsedInterval) {
				//отправить в отстойник
				self::model()->updateByPk($model->id, array(
					'date_used' => time(),
				));

				toLogRuntime('кошелек: ' . $model->login . ' отпрвлен в отстойник');

				$done++;
			}
		}

		echo "\n проверено: $done";
		$minBalance = cfg('min_balance');

		//проставить ошибки перелимита всем аккам где баланс больше исходящего лимита
		$monthStart = strtotime(date('01.m.Y'));

		/**
		 * @var MerchantWallet[] $modelsLimitOut
		 */
		$modelsLimitOut = MerchantWallet::model()->findAll([
			'select'=>'id',
			'condition'=>"
				`date_check`>$monthStart AND `limit_out`<2
				AND `balance`>=2 AND `error`=''
			",
			'limit'=>10,
		]);

		foreach($modelsLimitOut as $modelLimitOut)
			self::model()->updateByPk($modelLimitOut->id, ['error'=>self::ERROR_LIMIT_OUT]);

	}

	/**
	 * если кошелек давно не проверялся то часть номера будет скрыта
	 * @return bool
	 */
	public function getIsOldCheck()
	{
		$interval = 3600*2;

		if($this->date_check < time() - $interval)
			return true;
		else
			return false;
	}

	public function getHiddenLogin()
	{
		if(YII_DEBUG)
			$login = $this->login;
		else
			$login = '...'.substr($this->login, 5);

		return $login;
	}

	public function getLabelStr()
	{
		return shortText($this->label, 60);
	}

	/**
	 * выдает сообщение для менеджера о состоянии кошелька
	 */
	public function getOrderMsg()
	{
		//если не првоерялся больше 60 минут то пишем не заливать
		$dateCheckMin = time() - 3600;

		if ($this->error) {
			if ($this->error == 'ban')
				return '<font color="red">ОСТАНОВИТЕ ПЕРЕВОДЫ <br> НА ЭТОТ КОШЕЛЕК!!! <br> кошелек заблокирован</font>';
			else
				return '<font color="red">ОСТАНОВИТЕ ПЕРЕВОДЫ <br> НА ЭТОТ КОШЕЛЕК!!! <br> до разрешения проблемы</font>';
		}
		elseif ($this->limit_in < cfg('min_balance'))
			return '<font color="red">Исчерпан лимит переводов <br> на данный кошелек</font>';
		/*elseif ($this->balance >= config('in_max_balance'))
			return '<font color="red">Не превышайте максимальный <br> баланс на кошельке. <br>Дождитесь пока баланс <br> текущего кошелька <br> уменьшится до нуля.</font>';*/
		elseif ($this->limit_in < config('in_max_balance'))
			return '<font color="red">Лимит переводов <br> на этот кошелек <br> приближается к нулю</font>';
		elseif ($this->date_check < $dateCheckMin and $this->date_check > $dateCheckMin - 1800)
			return '<font color="orange">КОШЕЛЕК ОБНОВЛЯЕТСЯ <br> ОЖИДАЙТЕ <br> последнее обновление: '.date('d.m.Y H:i', $this->date_check).'</font>';
		elseif ($this->date_check < $dateCheckMin)
			return '<font color="red">ВНИМАНИЕ! КОШЕЛЕК <br> МОЖЕТ <br> НАХОДИТЬСЯ В БЛОКИРОВКЕ: '.date('d.m.Y H:i', $this->date_check).'</font>';
		else
			return '<font color="green">можно переводить</font>';
	}

	/**
	 * принято за месяц
	 * @return int
	 */
	public function getMonthAmountIn()
	{
		$dateStart = strtotime(date('01.m.Y'));

		$info = MerchantTransaction::model()->findAll(array(
			'select'=>"SUM(`amount`) as 'amnt'",
			'condition'=>"
				`merchant_wallet_id`='{$this->id}'
				AND `status`='".MerchantTransaction::STATUS_SUCCESS."'
				AND `type`='".MerchantTransaction::TYPE_IN."'
				AND `date_add`>'$dateStart'
				AND `user_id`<>0
				AND `client_id`<>0
			",
		));

		$amount = $info[0]->amnt*1;

		return $amount;
	}

	/**
	 * дневной лимит 100к (обход комсы)
	 * @return int
	 */
	public function getDayLimit()
	{
		$dateStart = strtotime(date('d.m.Y'));

		$info = MerchantTransaction::model()->findAll(array(
			'select'=>"SUM(`amount`) as 'amnt'",
			'condition'=>"
				`merchant_wallet_id`='{$this->id}'
				AND `status`='".MerchantTransaction::STATUS_SUCCESS."'
				AND `type`='".MerchantTransaction::TYPE_IN."'
				AND `date_add`>'$dateStart'
				AND `user_id`<>0
				AND `client_id`<>0
			",
		));

		$amount = $info[0]->amnt*1;

		$limit = self::DAY_LIMIT_MAX - $amount;

		if($limit > $this->limit_in)
			return $this->limit_in;
		else
			return $limit;
	}

	/**
	 * @return int
	 */
	public function getYadDayLimit()
	{
		$dateStart = strtotime(date('d.m.Y'));

		$walletType = ['yandex'];

		$typeCond = " ('".implode("', '", $walletType)."') ";

		$info = MerchantTransaction::model()->findAllBySql('
			select sum(mt.amount) as amnt from merchant_transaction as mt
			inner join merchant_wallet as mw
			on mt.merchant_wallet_id = '.$this->id.'
			and mt.date_add >='.$dateStart.'
			and mw.type in '.$typeCond.' and mt.type ="in" and mt.status ="success"
			and mt.client_id <>"0" and mt.user_id <>"0"'
		);

		$amount = $info[0]->amnt*1;

		$limit = self::DAY_LIMIT_MAX_YANDEX - $amount;

		if($limit > $this->limit_in)
			return $this->limit_in;
		else
			return $limit;
	}

	/**
	 * лимит на месяц
	 * @return int
	 */
	public function getMonthLimit()
	{
		$dateStart = strtotime(date('01.m.Y'));

		$info = MerchantTransaction::model()->findAll(array(
			'select'=>"SUM(`amount`) as 'amnt'",
			'condition'=>"
				`merchant_wallet_id`='{$this->id}'
				AND `status`='".MerchantTransaction::STATUS_SUCCESS."'
				AND `type`='".MerchantTransaction::TYPE_IN."'
				AND `date_add`>'$dateStart'
				AND `user_id`<>0
				AND `client_id`<>0
			",
		));

		$amount = $info[0]->amnt*1;

		$limit = cfg('account_in_merchant_limit') - $amount;

//		if($limit > $this->limit_in)
//			return $this->limit_in;
//		else
		return $limit;
	}

	/**
	 * @return string
	 */
	public function getDayLimitStr()
	{
		$limit = $this->getDayLimit();
		$managerLimit = $this->limit_in  - cfg('account_in_safe_limit');

		//для менеджера чтобы он видел не ту сумму
		if($limit > $managerLimit)
			$limit = $managerLimit;

		if($limit < 30000)
			return '<span class="error">'.formatAmount($limit, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limit, 0).'</span>';
	}

	/**
	 * @return string
	 */
	public function getMonthLimitStr()
	{
		$limit = $this->getDayLimit();
		$managerLimit = $this->limit_in  - cfg('account_in_safe_limit');

		//для менеджера чтобы он видел не ту сумму
		if($limit > $managerLimit)
			$limit = $managerLimit;

		if($limit < 30000)
			return '<span class="error">'.formatAmount($limit, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limit, 0).'</span>';
	}

	/**
	 * сколько времени осталось до удаления кошелька в отстойник
	 */
	public function getOutOfLimitStr()
	{
		if ($this->date_out_of_limit and !$this->date_used) {
			$last = time() - $this->date_out_of_limit;
			$interval = config('account_mark_used_interval');

			$val = ($interval - $last) / 3600;

			if($val < 0) $val = 0;

			return formatAmount($val, 0) . ' часов';
		}

	}

	/**
	 * @return string
	 */
	public function getYadDayLimitStr()
	{
		$limit = $this->getYadDayLimit()- cfg('account_in_safe_limit');

		if($limit < 30000)
			return '<span class="error">'.formatAmount($limit, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limit, 0).'</span>';
	}

	/**
	 * @return string
	 */
	public function getYadMonthLimitStr()
	{
		$limit = $this->getYadDayLimit();
		$managerLimit = $this->limit_in  - cfg('account_in_safe_limit');

		//для менеджера чтобы он видел не ту сумму
		if($limit > $managerLimit)
			$limit = $managerLimit;

		if($limit < 30000)
			return '<span class="error">'.formatAmount($limit, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limit, 0).'</span>';
	}

	/**
	 * сколько времени осталось до удаления кошелька в отстойник
	 */
	public function getYadOutOfLimitStr()
	{
		if ($this->date_out_of_limit and !$this->date_used) {
			$last = time() - $this->date_out_of_limit;
			$interval = config('account_mark_used_interval');

			$val = ($interval - $last) / 3600;

			if($val < 0) $val = 0;

			return formatAmount($val, 0) . ' часов';
		}

	}

	/**
	 * отображать манагеру лимит поменьше чем на самом деле
	 */
	public function getManagerLimitStr()
	{
		$limit = $this->getManagerLimit();

		$value = formatAmount($limit, 0);

		if ($limit < config('account_mark_used_after'))
			return '<font color="red" title="Лимит приближается к нулю, скоро этот кошелек будет удален">' . $value . '</font>';
		else
			return '<font color="green">' . $value . '</font>';
	}

	public function getManagerLimit()
	{
		$result = $this->limit_in - cfg('account_in_safe_limit');

		return $result;
	}

	public static function getWalletLimitCount()
	{
		return self::model()->findBySql('select * from `merchant_wallet` where `date_add` = (select max(`date_add`) from `merchant_wallet`)');
	}

	/**
	 * помечает кошелек датой использования, после этого он не отображается у клиента
	 * @param int $id
	 * @return bool
	 */
	public static function markOld($id)
	{
		/**
		 * @var MerchantWallet $wallet
		 */
		if(!$wallet = MerchantWallet::model()->findByPk($id))
		{
			self::$lastError = 'кошелек не существует';
			return false;
		}
		else
			return MerchantWallet::model()->updateByPk($wallet->id, array('date_used'=>time()));;
	}

	public static function getWalletByLogin($login)
	{
		return self::model()->findByAttributes(['login'=>$login]);
	}
}