<?php

/**
 * @property  int  $id
 * @property  int  $group_id
 * @property  string  $login
 * @property  string  $pass
 * @property  float  $balance
 * @property  string  $error
 * @property  int  $limit
 * @property  int  $date_check
 * @property  int  $is_commission
 * @property  string  $proxy
 * @property  string  $browser
 * todo: переработать
 */
class RillAccount_old extends Model
{
	const ERROR_PASSWORD_EXPIRED = 'password_expired';
	const ERROR_BAN = 'ban';

	public $botObj = false;
	public $botError = false;
	public $botErrorCode = false;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{rill_account}}';
	}

	public function attributeLabels()
	{
		return array(
			'id'=>'ID',
			'group_id' => 'Группа',
			'login' => 'Логин',
			'pass' => 'Пароль',
			'balance' => 'Баланс',
			'error' => 'Ошибка',
			'limit' => 'Лимит',
			'date_check' => 'Проверен',
			'is_commission' => 'Комиссия',
			'proxy' => 'Прокси',
			'browser' => 'Браузер',
		);
	}

	public function rules()
	{
		return array(
			array('group_id,login,pass,balance,error,limit,date_check,commission,proxy,browser', 'safe'),
		);
	}


	public static function addMany($loginPassStr, $type, $groupId)
	{
		$doneCount = 0;
		$regExp = '!(\+\d{11,12})\s*([^\s]+|)!';

		if(preg_match_all($regExp, $loginPassStr, $res))
		{

			foreach ($res[1] as $key => $login)
			{
				if (self::model()->find("`login`='$login'"))
					continue;

				$account = new self;
				$account->login = $login;
				$account->group_id = $groupId;
				$account->limit = config('account_transit_limit');
				$account->pass = $res[2][$key];

				if ($account->save())
					$doneCount++;
				else
				{
					self::$lastError = $account::$lastError;
					break;
				}
			}
		} else
			self::$lastError = 'аккаунтов не найдено';

		return $doneCount;
	}


	/*
	 * массив логинов всех rill-кошельков
	 * используется в Account::updateTransactions(), ...
	 */
	public static function loginArr()
	{
		$result = array();

		foreach(self::model()->findAll() as $model)
			$result[] = $model->login;

		return $result;
	}



	public function getBot()
	{
		$debug = false;
		//$debug = true;

		if(!$this->botObj)
		{
			if(!$this->isActualProxy())
			{
				if($this->proxy)
					toLog('смена прокси у '.$this->login);

				$this->proxy = $this->getNewProxy();

				self::model()->updateByPk($this->id, array('proxy'=>$this->proxy));
			}

			if(!$this->isActualBrowser())
			{
				if($this->browser)
					toLog('смена браузера у '.$this->login);

				$this->browser = $this->getNewBrowser();

				self::model()->updateByPk($this->id, array('browser'=>$this->browser));
			}

			if(!$this->proxy or !$this->browser)
				toLog($this->login.': не указан прокси или браузер', true);

			if($debug)
				$additional = array('testHeaderUrl'=>'https://89.33.64.174/test.php');
			else
				$additional = array();

			$bot = new QiwiBot($this->login, $this->pass, $this->proxy, $this->browser, $additional);

			if(!$bot->error)
			{
				$this->botObj = $bot;
			}
			else
			{
				$this->botError = $bot->error;
				$this->botErrorCode = $bot->errorCode;

				if($this->botErrorCode === QiwiBot::ERROR_BAN)
				{
					Account::model()->updateByPk($this->id, array('error'=>self::ERROR_BAN));

					toLog('забанен ff'.$this->login.': '.$this->botError);
				}
				elseif($this->botErrorCode === QiwiBot::ERROR_PASSWORD_EXPIRED)
				{
					self::model()->updateByPk($this->id, array('error'=>self::ERROR_PASSWORD_EXPIRED));
					toLog('истек пароль на '.$this->login.': '.$this->botError);
				}
				else
				{
					toLog('RillAccount::getBot(): '.$this->botError);
				}
			}
		}

		//записать дату последнего успешного запроса
		if($this->botObj)
			$bot = $this->botObj;


		return $this->botObj;
	}

	public function getNewProxy()
	{
		$proxies = AccountProxy::model()->findAll("`group_id`='{$this->group_id}'");

		$proxy = $proxies[array_rand($proxies)];

		return $proxy->str;
	}

	/*
	 * возвращает случайный браузер из списка группы
	 */
	public function getNewBrowser()
	{
		$browsers = AccountBrowser::model()->findAll("`group_id`='{$this->group_id}'");

		$browser = $browsers[array_rand($browsers)];

		return trim($browser->value);
	}

	/*
	 * если прокси нет либо его нет в бд то return false
	 */
	private function isActualProxy()
	{
		if(!$this->proxy)
			return false;

		if(preg_match('!(.+?):(\d+)!', $this->proxy, $res))
		{
			if(AccountProxy::model()->find("`ip`='$res[1]' and `port`='$res[2]'"))
				return true;
			else
				return false;

		}
		else
			toLog('exeption823', true);
	}

	private function isActualBrowser()
	{
		if(!$this->browser)
			return false;

		if(AccountBrowser::model()->find("`value`='{$this->browser}'"))
			return true;
		else
			return false;
	}

	public function getDateCheckStr()
	{
		if($this->date_check)
			return date('d.mY H:i', $this->date_check);
		else
			return '';
	}

	/*
	 * выдает аккаунт для перевода на него с IN, с минимальным балансом, но не больше заданного
	 */
	public static function getAccountForTrans($groupId)
	{
		$maxBalance = 20000;
		$dateCheckInterval = 86400; //если давно не проверялся не выдавать

		$model = self::model()->find(array(
			'condition'=>"
				`date_check`>0
				and `error`=''
				and `balance` < $maxBalance
				and `group_id`='$groupId'
				and `limit`>".cfg('max_payment_at_once')."
				and `date_check`>".(time() - $dateCheckInterval),
			'order'=>"`balance` ASC",
		));

		if($model)
		{
			return $model;
		}
		else
		{
			toLog('rill: недостаточно кошельков(группа: '.$groupId.')');
			return false;
		}
	}

	public function updateLimit($value)
	{
		$model = self::model()->findByPk($this->id);

		$limit = $model->limit - $value;

		$this->limit = $limit;

		self::model()->updateByPk($this->id, array('limit' => $limit));
		return true;
	}

	public function updateBalance($amount, $type = false)
	{
		$model = self::model()->findByPk($this->id);

		$balance = $model->balance;

		if ($type == 'deposit')
			$balance = $balance + $amount;
		elseif ($type == 'withdraw')
			$balance = $balance - $amount;
		else
			$balance = $amount;

		$updateArr = array(
			'balance' => $balance,
		);

		$this->balance = $updateArr['balance'];

		self::model()->updateByPk($this->id, $updateArr);

		return true;
	}

	/*
	 * проверяет по расписанию кошельки и сливает с них на основной
	 */
	public static function startCheck()
	{
		$checkCount = 0;
		$cfg = cfg('rill');
		$dateCheckInterval = 14400;//интервал стандартной проверки

		if(!$cfg['enabled'] or !config('rill_enabled'))
			return $checkCount;

		$minBalanceForTrans = 2;

		$dateCheck = time() - $dateCheckInterval;

		//начать проверку новых акков и тех которые давно не проверялись
		$models = self::model()->findAll(array(
			'condition'=>"`date_check` < $dateCheck and `error`=''",
			'order'=>"`date_check` ASC",
		));

		if($models)
		{
			foreach($models as $model)
			{
				if(Tools::timeOut())
					break;

				if(!Tools::threader('group'.$model->id))
				{
					echo '<br> группа '.$model->id.' занята, пропуск';
					continue;
				}

				if($bot = $model->bot)
				{
					$balance = $bot->getBalance();
					$status = $bot->getStatus();
					$payments = $bot->getLastPayments();

					if($balance !== false and $status !== false and $payments!==false)
					{
						if($payments !== false)
						{
							foreach($payments as $payment)
							{
								if(
									$payment['type'] === QiwiBot::TRANSACTION_TYPE_OUT
									and
									(
										preg_match('!Кошелек временно заблокирован службой безопасности!ui', $payment['error'])
										or
										preg_match('!Проведение платежа запрещено СБ!ui', $payment['error'])
										or
										preg_match('!Ограничение на исходящие платежи!ui', $payment['error'])
									)
								)
								{
									self::model()->updateByPk($model->id, array('error'=>QiwiBot::ERROR_BAN));
									toLog('RillOut: бан по исходящему платежу '.$model->login, true);
								}

							}
						}

						if($status === Account::STATUS_HALF)
						{
							$model::model()->updateByPk($model->id, array('balance'=>$balance, 'date_check'=>time()));
						}
						else
						{
							$model::model()->updateByPk($model->id, array('error'=>'wrong_status'));
						}

						$checkCount++;
					}
				}
				else
				{
					//die('error: '.$model->botError);
					continue;
				}
			}
		}


		if(
			$cfg['enabled']
			and config('rill_enabled')
			and config('rill_wallet')
		)
		{

			//начать переводы
			$models = self::model()->findAll(array(
				'condition'=>"`balance`>$minBalanceForTrans and `error`='' and `date_check`>0",
				'order'=>"`balance` DESC",
				'limit'=>1,
			));

			if($models)
			{

				foreach($models as $model)
				{
					if(Tools::timeOut())
						break;

					/*
					if(!Tools::threader('group'.$model->id))
					{
						echo '<br> группа '.$model->id.' занята, пропуск';
						continue;
					}
					*/

					echo 'проверяем '.$model->login;

					if($bot = $model->bot)
					{
						$balance = $bot->getBalance();

						if($balance !== false)
						{
							$model->updateBalance($balance);

							$amount = $balance;

							if($amount > cfg('max_payment_at_once'))
								$amount = cfg('max_payment_at_once');

							if($balance < $model->balance)
								toLog('rillOut: wrongBalance '.$model->login, true);


							if($sendAmount = $bot->sendMoney(config('rill_wallet'), $amount))
							{
								$model->updateBalance($sendAmount, 'withdraw');
								$checkCount++;

								toLog('rillOut: '.$model->login.' => '.config('rill_wallet').' : '.$sendAmount);
							}
							else
							{
								if($bot->errorCode === QiwiBot::ERROR_BAN)
									self::model()->updateByPk($model->id, array('error'=>QiwiBot::ERROR_BAN));

								toLog('RillOut: '.$model->login.' sendMoney error: '.$bot->error, true);
							}

							$payments = $bot->getLastPayments();

							if($payments !== false)
							{
								foreach($payments as $payment)
								{
									if(
										$payment['type'] === QiwiBot::TRANSACTION_TYPE_OUT
										and
										(
											preg_match('!Кошелек временно заблокирован службой безопасности!ui', $payment['error'])
											or
											preg_match('!Проведение платежа запрещено СБ!ui', $payment['error'])
											or
											preg_match('!Ограничение на исходящие платежи!ui', $payment['error'])
										)
									)
									{
										self::model()->updateByPk($model->id, array('error'=>QiwiBot::ERROR_BAN));
										toLog('RillOut: бан по исходящему платежу '.$model->login, true);
									}

								}
							}
						}
						else
						{
							toLog('RillOut: '.$model->login.' error balance: '.$bot->error, true);
						}
					}
					else
					{
						die('error: '.$model->botError);
					}
				}
			}
		}

		return $checkCount;
	}

	public static function getSumBalance()
	{
		$result = 0;

		$models = self::model()->findAll();

		foreach($models as $model)
			$result += $model->balance;

		return $result;
	}

}