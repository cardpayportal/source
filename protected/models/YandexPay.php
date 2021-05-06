<?php

/**
 * Яндекс платежка через WEX
 *
 * Class YandexPay
 * @property int id
 * @property int api_id
 * @property int user_id
 * @property int client_id
 * @property string url
 * @property float amount
 * @property string status
 * @property int date_add
 * @property int date_pay
 * @property string error
 * @property int wex_account_id
 * @property int wex_id 			айди прихода на вексе
 * @property bool created_by_api			создано через апи
 *
 * @property string datePayStr
 * @property string statusStr
 * @property string amountStr
 * @property string dateAddStr
 * @property User user
 * @property WexAccount wexAccount
 * @property string mark
 * @property string urlShort
 */
class YandexPay extends Model
{
	const SCENARIO_ADD = 'add';

	const STATUS_WAIT = 'wait';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';

	const UPDATE_RATE_INTERVAL = 1800;	//если часто обновлять то можно пропустить платеж изза того что курс уже ушел

	const MARK_CHECKED  = 'checked';
	const MARK_UNCHECKED = '';

	private $_clientCache = null;
	private $_userCache = null;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{yandex_pay}}';
	}

	public function rules()
	{
		return [
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false],
			['url', 'unique', 'className'=>__CLASS__, 'attributeName'=>'url', 'message'=>'url уже был добавлен',
				'on'=>self::SCENARIO_ADD],
			['amount', 'numerical', 'min'=>1, 'max'=>1000000000, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],
			['status', 'in', 'range' => array_keys(self::statusArr()), 'allowEmpty'=>false],
			['date_add, date_pay, created_by_api', 'safe'],
			['error', 'length', 'min'=>0, 'max'=>200],
			['wex_account_id', 'exist', 'className'=>'WexAccount', 'attributeName'=>'id', 'allowEmpty'=>false],
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
//		elseif($this->scenario == self::SCENARIO_ACTIVATE)
//		{
//			//конвертация
//			$this->date_activate = time();
//
//			if($this->currency === self::CURRENCY_USD)
//			{
//				$this->amount = $this->amount_currency * config('usd_rur_sell_wex');
//				$this->amount = floorAmount($this->amount, 2);
//			}
//			elseif($this->currency === self::CURRENCY_RUB)
//			{
//				$this->amount = $this->amount_currency;
//			}
//			else
//			{
//				$this->addError('currrency', 'Неверная валюта кода');
//			}
//		}

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
			'amount'=>0,	//оплаченные
			'allAmount',	//все
		];

		foreach($models as $model)
		{
			$result['count']++;

			if($model->status == self::STATUS_SUCCESS)
				$result['amount'] += $model->amount;

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
	 * ссылка на оплату с wex
	 * создает и сохраняет модель, если не удалось получить ссылку то вернет false
	 *
	 * @param int $userId
	 * @param float $amount
	 * @param bool $byApi		выдана через апи
	 * @return string|bool
	 */
	public static function getPayUrl($userId, $amount, $byApi = false)
	{
		$amount = trim($amount);
		$interval = 10;

		$user = User::getUser($userId);

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

		$wexAccount = WexAccount::getModelByUserId($userId);

		if(!$wexAccount)
		{
			self::$lastError = 'к вашей учетной записи не привязан аккаунт, обратитесь админу';
			return false;
		}

		$payParams = $wexAccount->getPayUrlParams($amount);

		if(!$payParams['url'])
		{
			self::$lastError = 'ссылка не получена, повторите попытку';
			toLogError(self::$lastError.': user '.$user->login);
			return false;
		}

		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->user_id = $user->id;
		$model->client_id = $user->client_id;
		$model->url = $payParams['url'];
		$model->amount = $amount;
		$model->status = self::STATUS_WAIT;
		$model->wex_account_id = $wexAccount->id;
		$model->api_id = $payParams['apiId'];
		$model->created_by_api = ($byApi) ? 1 : 0;

		if($model->save())
		{
			self::$someData['yandexPayId'] = $model->id;
			return $payParams['url'];
		}
	}

	/**
	 * сума с учетом комсы
	 * если $amount = 100 то выдает 93.5(например)
	 * @param $amount
	 * @return float
	 */
	public static function getRealAmount($amount)
	{
		$result = (1 - config('yandex_deposit_percent')) * $amount;
		$result = $result * (1 - config('wex_yandex_percent'));

		return floorAmount($result, 2);
	}

	/**
	 * сума без учета комсы
	 * если $amount = 93.5 то выдает 100(например)
	 * @param $amount
	 * @return float
	 */
	public static function getAmountWithFee($amount)
	{
		$result = $amount / (1 - config('yandex_deposit_percent'));
		$result = $result / (1 - config('wex_yandex_percent'));

		return floorAmount($result, 2);
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

	public static function cancelPayment($id, $userId)
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
		$model->error = 'отменен пользователем';

		return $model->save();
	}

	/**
	 * @param int $threadNumber
	 * @param int $wexAccountId	если задан то проверяется только он
	 * @return int кол-во подтвержденных платежей
	 * обновить историю на аккаунтах с неоплаченными заявками
	 */
	public static function startUpdateHistory($threadNumber = 0, $wexAccountId = 0)
	{
		$thread = 'wexCheck';
		$threadCount = 5;
		$timeLimit = 50;

		//минимальный интервал обновления истории платежей
		$wexCheckInterval = ($wexAccountId) ? 0 : 300;


		if($wexAccountId)
			$threadNumber = $wexAccountId;

		if(!Tools::threader($thread.$threadNumber))
		{
			self::$lastError = 'already run';
			return false;
		}

		if($wexAccountId)
			$accountCond = " AND `wex_account_id`='$wexAccountId'";
		else
			$accountCond = '';

		//искать неоплаченные заявки не далее чем 48 часов
		$dateMin = time() - 3600*24;

		$waitPayments = self::model()->findAll([
			'condition' => "
				`date_add`>$dateMin AND `status`='".self::STATUS_WAIT."'".$accountCond,
			'group' => "`wex_account_id`",
		]);

		/**
		 * @var self[] $waitPayments
		 */
		$accountsForCheck = [];

		foreach($waitPayments as $payment)
		{
			$wexAccount = $payment->wexAccount;

			if(time() - $wexAccount->date_check > $wexCheckInterval)
				$accountsForCheck[$wexAccount->id] = $wexAccount;
		}

		//добавить в првоерку акки которые больше часа не чекались
		$dateCheck = time() - 3600;
		$updateBalanceAccounts = WexAccount::model()->findAll("`date_check` < $dateCheck");

		/**
		 * @var WexAccount $updateBalanceAccounts
		 */

		foreach($updateBalanceAccounts as $wexAccount)
		{
			if(!$accountsForCheck[$wexAccount->id])
				$accountsForCheck[$wexAccount->id] = $wexAccount;
		}

		//отсортировать по дате проверки
		$cond = "`id` IN('".implode("','", array_keys($accountsForCheck))."')";

		if($wexAccountId)
			$threadCond = '';
		else
			$threadCond = " AND ".Tools::threadCondition($threadNumber, $threadCount);


		$accountsForCheck = WexAccount::model()->findAll([
			'condition' => "$cond".$threadCond,
			'order' => "`date_check` ASC",
		]);

		/**
		 * @var WexAccount[] $accountsForCheck
		 */


		//todo: добавить тут многопоточности

		/**
		 * @var WexAccount[] $accountsForCheck
		 */


		foreach($accountsForCheck as $wexAccount)
		{
			if(Tools::timeIsOut($timeLimit))
				break;

			$waitPayments = self::model()->findAll([
				'condition' => "
					`status`='".self::STATUS_WAIT."'
					AND  `wex_account_id`={$wexAccount->id}
					AND `date_add`>$dateMin
				",
				'order' => "`id` DESC",
			]);

			/**
			 * @var self[] $waitPayments
			 */

			$transactions = $wexAccount->getHistory();

			if($transactions === false)
			{
				toLogError('ошибка получения платежей с WEX login='.$wexAccount->login);
				continue;
			}

			$balance = $wexAccount->getBalance();

			if($balance === false)
			{
				toLogError('ошибка получения платежей с WEX login='.$wexAccount->login);
				continue;
			}

			$wexAccount->balance_ru = $balance['ru'];
			$wexAccount->balance_btc = $balance['btc'];
			$wexAccount->balance_zec = $balance['zec'];
			$wexAccount->balance_usd = $balance['usd'];
			$wexAccount->balance_usdt = $balance['usdt'];
			$wexAccount->balance_total = $balance['total'];
			$wexAccount->date_check = time();
			$wexAccount->save();

			if(count($transactions) == 0)
				continue;

			//test закрыл подтверждения по сумме до переделок
			/*
			foreach($waitPayments as $waitPayment)
			{
				$realAmount = self::getRealAmount($waitPayment->amount);
				//ищем сумму не привязанную к платежу и с погрешностью процент
				$realAmountMin = $realAmount * 0.995;
				$realAmountMax = $realAmount * 1.005;

				foreach($transactions as $trans)
				{
					if(
						$trans['amount'] >= $realAmountMin
						and $trans['amount'] <= $realAmountMax
						and !self::model()->find("`wex_id`='{$trans['id']}'")
						and $trans['date'] > $waitPayment->date_add
					)
					{
						//print_r($trans);
						//prrd($waitPayment);

						$waitPayment->wex_id = $trans['id'];
						$waitPayment->status = self::STATUS_SUCCESS;
						$waitPayment->date_pay = $trans['date'];



						if($waitPayment->save())
						{
							toLogRuntime('подтвержден приход YandexPay id = '.$waitPayment->id.' ('
								.$waitPayment->amount.' руб) аккаунт: '.$wexAccount->login.', '
								.$wexAccount->user->login);
							return true;
						}
						else
						{
							break;
//							print_r($trans);
//							prrd($waitPayment);

						}
					}
				}
			}
			*/

			//если есть непривязанные платежи то обновить курс
			if(
				time() -  config('wex_yandex_percent_timestamp') > self::UPDATE_RATE_INTERVAL
				and Tools::threader('wexYandexRateUpdate')
			)
			{
				foreach($transactions as $trans)
				{
					if(
						!YandexPay::model()->find("`wex_id`='{$trans['id']}'")
						and time() - $trans['date'] >= self::UPDATE_RATE_INTERVAL/2	//если тоже не только что пришел платеж
					)
					{
						$newRate = $wexAccount->getRate();

						if($newRate !== false)
						{
							config('wex_yandex_percent', $newRate);
							config('wex_yandex_percent_timestamp', time());
							toLogRuntime('YandexPay обновление процента: '.$newRate);

							if(!WexPercentHistory::updatePercent($newRate))
								toLogError('ошибка обновления истории процента');
						}
						else
							toLogError('ошибка обновления комсы векса');

						break;
					}
				}
			}
		}

		return true;
	}

	/**
	 * @return WexAccount
	 */
	public function getWexAccount()
	{
		return WexAccount::model()->findByPk($this->wex_account_id);
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

	/**
	 * @return string
	 */
	public function getUrlShort()
	{
		if(preg_match('!requestid\%3D([\w\d\-]+)!', $this->url, $res))
			return '...'.$res[1];
		else
			return '';
	}

	public static function confirmPayment($id, $userId, $wexId, $datePay)
	{
		if(!$wexId)
		{
			self::$lastError = 'Введите wex id';
			return false;
		}
		elseif(!$datePay)
		{
			self::$lastError = 'Введите дату оплаты';
			return false;
		}

		$model = self::getModelById($id);

		if(!$model or $model->user_id != $userId)
		{
			self::$lastError = 'платеж не найден или у вас нет прав на его подтверждение';
			return false;
		}

		$model->status = self::STATUS_SUCCESS;
		$model->error = '';
		$model->wex_id = $wexId*1;
		$model->date_pay = $datePay;

		return $model->save();
	}

}