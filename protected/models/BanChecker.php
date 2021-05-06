<?php

/**
 * Class BanChecker
 * @property int id
 * @property string login
 * @property string error
 * @property string errorStr
 * @property int date_check
 * @property int dateCheckStr
 * @property int date_add
 * @property int dateAddStr
 * @property string message
 * @property string messageStr
 */
//todo: сделать поле try_count чтобы не долбить впустую при неизвестной ошибке или смене верстки
class BanChecker extends Model
{
	const ADD_DUPLICATE_INTERVAL = 1800;	//чере сколько можно будет проверить тот же кошелек
	const OLD_INTERVAL = 3600;	//чере сколько можно будет проверить тот же кошелек
	const OLD_INTERVAL_BAN = 2592000;	//очищать забаны через месяц по кнопке

	const ERROR_BAN = 'ban';	//бан
	const ERROR_PASSWORD_EXPIRED = 'password_expired';	//бан
	const ERROR_UNKNOWN = 'unknown';	//бан

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{ban_checker}}';
	}

	public function beforeValidate()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->error = strip_tags($this->error);

			//чтобы можно было добавлять те же кошельки на првоерку еще раз
			/*
			if(
				$existModel = self::model()->findByAttributes(['login'=>$this->login])
				and $existModel->date_add < time() - self::ADD_DUPLICATE_INTERVAL
			)
				self::model()->deleteByPk($existModel->id);
			*/
		}

		return parent::beforeValidate();
	}



	public function rules()
	{
		return array(
			['login', 'match', 'pattern'=>'!^\+\d{11,12}$!', 'allowEmpty'=>false],
			['login', 'unique', 'className'=>__CLASS__, 'attributeName'=>'login', 'on'=>self::SCENARIO_ADD, 'message'=>'уже добавлен'],
			['error', 'length', 'max'=>100],
			['date_check, date_add, date_add,message', 'safe'],
		);
	}

	public function beforeSave()
	{
		return parent::beforeSave();
	}


	public function getDateCheckStr()
	{
		return ($this->date_check) ? date("d.m.Y H:i", $this->date_check) : '';
	}

	public function getDateAddStr()
	{
		return ($this->date_add) ? date("d.m.Y H:i:s", $this->date_add) : '';
	}

	public function getErrorStr()
	{
		if($this->error)
			return '<span class="error">'.strtoupper($this->error).'</span>';
		elseif($this->date_check)
			return '<span class="success">OK</span>';
		else
			return '';
	}

	/**
	 * запуск по расписанию
	 * @param int $thread
	 * @return int
	 */
	public static function startCheck($thread = 0)
	{
		$threadName = 'banChecker'.$thread;
		$limitAtOnce = 20;
		$pauseMin = 2;
		$pauseMax = 4;
		$maxTime = 55;
		$maxThreads = 10;

		$checkCount = 0;

		if(!Tools::threader($threadName))
		{
			self::$lastError = 'thread already run';
			return $checkCount;
		}

		$threadCond = Tools::threadCondition($thread, $maxThreads);

		$models = self::getModelsForCheck("AND $threadCond");

		//чтобы при загвоздке проверки одного не стопорилось все
		$models = array_slice($models, 0, $limitAtOnce);
		shuffle($models);

		if($models)
		{
			foreach($models as $model)
			{
				if(Tools::timeIsOut($maxTime) or $checkCount > $limitAtOnce)
					break;

				$res = Account::checkBan($model->login);

				if($res === true or $res === false)
				{
					$model->date_check = time();
					$model->error = ($res === true) ? self::ERROR_BAN : '';

					//блокируем кошелек если он есть в панели
					if($res === true)
						self::disableAccount($model->login);
				}
				else
				{
					if(Account::$lastErrorCode)
					{
						$model->date_check = time();
						$model->error = Account::$lastErrorCode;

						if(Account::$lastErrorCode == self::ERROR_PASSWORD_EXPIRED)
						{
							//слетел пароль(скорее всего), отключаем кошелек
							self::disableAccount($model->login);
						}
					}
					else
					{
						//тут какие то другие вариенты которые появятся в будущем
					}

					//любые ошибки пишем в лог
					toLog("BanChecker error check {$model->login}: ".Account::$lastError);
				}

				if(!$model->save())
				{
					toLog("BanChecker error save ".$model->id);
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

	/**
	 * отключает аккаунт если такой есть
	 * @param string $login
	 */
	public static function disableAccount($login)
	{
		if(
			$account = Account::model()->find("`login`='$login'")
			and $account->error != Account::ERROR_BAN
		)
		{
			Account::model()->updateByPk($account->id, [
				'error'=>Account::ERROR_BAN,
				'comment'=>'отключен админом',
			]);

			$account->noticeManager('Внимание! Забанен кошелек '.$account->login);

			toLogRuntime("AntiBan: отключен админом {$account->login} (".formatAmount($account->balance, 0).' руб)');
			toLogError('забанен '.$account->login, false, true);
		}
	}

	/**
	 * @param string $additionalCond
	 * @return self[]
	 */
	private static function getModelsForCheck($additionalCond = '')
	{
		return self::model()->findAll([
			'condition'=>"
				`date_check`=0
				$additionalCond
			",
			'order'=>"`id` DESC",
		]);
	}

	/**
	 * @return self[]
	 */
	public static function getModelsForView()
	{
		return self::model()->findAll([
			'order'=>"`id` DESC",
		]);
	}

	/**
	 * @param string $loginContent
	 * @param bool $noSearch
	 * @return int
	 */
	public static function addMany($loginContent, $noSearch=false)
	{
		$addCount = 0;
		$dateAdd = time();	//чтобы можно было разделить по группам добавленные кошельки

		if(
			preg_match_all('!(\+[73]\d{10,11})!is', $loginContent, $res)
			or preg_match_all('!([73]\d{10,11})!is', $loginContent, $res)
		)
		{
			foreach($res[1] as $login)
			{
				$login = '+'.trim($login, '+ ');

				if(self::model()->find("`login`='$login'"))
				{
					self::$lastError = 'уже добавлен';
					self::$msg .= 'не добавлен '.$login.': '.self::$lastError.'<br>';
					continue;
				}

				$model = new self;
				$model->scenario = self::SCENARIO_ADD;
				$model->login = $login;
				$model->date_add = $dateAdd;

				if($noSearch)
					$model->message = 'null';

				if($model->save())
					$addCount++;
				else
					self::$msg .= 'не добавлен '.$login.' :'.self::$lastError.'<br>';
			}
		}
		else
			self::$lastError = 'не найдено ни одного логина';

		return $addCount;
	}

	/**
	 * @return array
	 */
	public static function getStats()
	{
		$lastModel = self::model()->find([
			'condition'=>"",
			'order'=>"`date_add` DESC",
		]);

		/**
		 * @var self $lastModel
		 */

		$result = [
			'allCount'=>self::model()->count(),
			'banCount'=>self::model()->count("`error`='".self::ERROR_BAN."'"), 	//всего банов
			'errorCount'=>self::model()->count("`error`!=''"),					//всего ошибок
			'goodCount'=>self::model()->count("`error`='' and `date_check`>0"),
			'notCheckCount'=>self::model()->count("`date_check`=0"),
			'lastCount'=>self::model()->count("`date_add`={$lastModel->date_add}"),
			//кол-во банов при последней проверке
			'lastBanCount'=>self::model()->count("`error`='".self::ERROR_BAN."' AND `date_add`={$lastModel->date_add}"),
		];

		return $result;
	}

	/**
	 * удаляет проверенные более часа назад кошельки (не забаненые)
	 * удаляет старые забаны > 30 дней
	 * @return int
	 */
	public static function clearOld()
	{
		$deleteCount = 0;

		$timestamp1 = time() - self::OLD_INTERVAL;
		$timestamp2 = time() - self::OLD_INTERVAL_BAN;

		$models = self::model()->findAll("
			(`error`='' AND `date_check`>0 AND `date_check`<$timestamp1)
			OR
			(`error`='".self::ERROR_BAN."' AND `date_check`>0 AND `date_check`<$timestamp2)
		");

		foreach($models as $model)
		{
			if(self::model()->deleteByPk($model->id))
				$deleteCount++;
			else
			{
				self::$lastError = 'error delete '.$model->id;
				break;
			}
		}

		return $deleteCount;
	}

	/**
	 * удаляет проверенные и не забаненные кошельки
	 * @return int
	 */
	public static function clearAll()
	{
		$deleteCount = 0;

		$models = self::model()->findAll("`error`='' AND `date_check`>0");

		foreach($models as $model)
		{
			if(self::model()->deleteByPk($model->id))
				$deleteCount++;
			else
			{
				self::$lastError = 'error delete '.$model->id;
				break;
			}
		}

		return $deleteCount;
	}

	/**
	 * @return string
	 */
	public function getMessageStr()
	{
		if(!$this->message)
		{
			$trDateLimit = time() - 3600*24*60;

			if($order = FinansistOrder::model()->find("
				`to`='{$this->login}' AND `user_id` IN(select `id` from `user` where `role`='global_fin')
			"))
			{
				/**
				 * @var FinansistOrder $order
				 */

				$this->message =  "GlobalFin ".$order->user->name;
			}
			elseif($account = Account::model()->findByAttributes(['login'=>$this->login]))
			{
				/**
				 * @var Account $account
				 */

				$this->message =  $account->client->name.' ('.strtoupper($account->type).')';
			}
			elseif($transaction = Transaction::model()->find("
				`type`='in' AND `wallet`='{$this->login}' AND `user_id`>0 AND `date_add`>$trDateLimit
			"))
			{
				/**
				 * @var Transaction $transaction
				 */

				$this->message =  " От ".$transaction->account->client->name;
			}
			else
				$this->message = 'null';

			$this->save();
		}

		return $this->message;
	}

}