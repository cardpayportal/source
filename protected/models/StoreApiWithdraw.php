<?php

/**
 * Class StoreApiWithdraw

 * @property int id
 * @property int store_id
 * @property int amount_rub
 * @property string currency 'btc'|'возможноая валюта'
 * @property float amount_currency
 * @property string wallet
 * @property int date_pay
 * @property string datePayStr
 * @property float btc_last_price
 * @property float usd_rate
 * @property string btcLastPriceStr
 * @property string usdRateStr
 * @property float amountUsd
 * @property string withdraw_id id вывода внутри биржи
 * @property int confirmations ко-во подтверждений сети
 * @property int confirmationsStr
 * @property null|bool isConfirmed
 * @property float network_fee
 * @property int user_id
 */

class StoreApiWithdraw extends Model
{
	const SCENARIO_ADD = 'add';
	const CURRENCY_BTC = 'btc';
	const CURRENCY_RUB = 'rub';

	const AMOUNT_RUB_MIN = 1000;	//todo: заменить функцией
	const AMOUNT_RUB_MAX = 999999;
	const AMOUNT_CURRENCY_MIN = 0.001;
	const AMOUNT_CURRENCY_MAX = 99;

	const CONFIRMATIONS_MIN = 1;
	const CONFIRMATION_TIME = 3600;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'store_id' => 'ID Магазина',
			'amount_rub' => 'Сумма в рублях',
			'currency' => 'Валюта',
			'amount_currency' => 'Сумма в валюте',
			'btc_last_price' => 'Last Price на момент вывода',
			'usd_rate' => 'Курс USD',
			'wallet' => 'Кошлек',
			'date_pay' => 'Дата оплаты',
			'withdraw_id' => 'ID на бирже',
			'confirmations' => 'ко-во подтверждений сети',
			'network_fee' => 'комса системы на момент вывода',
		);
	}

	public function tableName()
	{
		return '{{store_api_withdraw}}';
	}

	public function beforeValidate()
	{
		$this->amount_rub = str_replace(',', '.', $this->amount_rub);
		$this->amount_currency = str_replace(',', '.', $this->amount_currency);
		$this->btc_last_price = str_replace(',', '.', $this->btc_last_price);
		$this->usd_rate = str_replace(',', '.', $this->usd_rate);

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('store_id', 'exist', 'className'=>'StoreApi', 'attributeName'=>'store_id', 'allowEmpty'=>false),
			array('amount_rub', 'numerical', 'min'=>self::AMOUNT_RUB_MIN, 'max'=>self::AMOUNT_RUB_MAX, 'allowEmpty'=>false),
			array('currency', 'in', 'range'=>array_keys(self::currencyArr()), 'allowEmpty'=>false),
			array('amount_currency', 'numerical', 'min'=>self::AMOUNT_CURRENCY_MIN, 'max'=>self::AMOUNT_CURRENCY_MAX, 'allowEmpty'=>true),	//true если вывод ручной
			array('btc_last_price', 'numerical', 'allowEmpty'=>true),
			array('usd_rate', 'numerical', 'allowEmpty'=>true),
			array('wallet', 'length', 'min'=>3, 'allowEmpty'=>true),	//если вывод ручной
			array('date_pay', 'numerical', 'allowEmpty'=>false),
			array('withdraw_id, confirmations, network_fee', 'safe'),
		);
	}

	public function beforeSave()
	{
		return parent::beforeSave();
	}

	public static function currencyArr()
	{
		return array(
			self::CURRENCY_BTC => 'BTC',
			self::CURRENCY_RUB => 'RUB',
		);
	}

	public function getDatePayStr()
	{
		if($this->date_pay)
			return date(cfg('dateFormat'), $this->date_pay);
		else
			return '';
	}

	/**
	 * список платежей для возврата по апи
	 * array(
	 * 	array('amountRub', 'amountBtc', 'wallet', 'timestamp'),
	 * ...
	 * )
	 * @param int $storeId
	 * @param int $timestampStart
	 * @param bool|false $timestampEnd
	 * @return array
	 */
	public static function getListArr($storeId, $timestampStart, $timestampEnd=false)
	{
		$result = array();

		$maxCount = 1000;

		if($timestampEnd === false)
			$timestampEnd = time();

		$storeId *= 1;
		$timestampStart *= 1;
		$timestampEnd *= 1;

		if(!$timestampStart or $timestampEnd <= $timestampStart)
		{
			self::$lastErrorCode = StoreApi::ERROR_DATE;
			return $result;
		}


		$models = self::model()->findAll(array(
			'condition'=>"`store_id`='$storeId' AND `date_pay` >= $timestampStart AND `date_pay` <= $timestampEnd",
			'order'=>"`date_pay`",
			'limit'=>$maxCount,
		));

		/**
		 * @var StoreApiWithdraw[] $models
		 */

		foreach($models as $model)
		{
			$result[] = array(
				'amountRub'=>$model->amount_rub,
				'amountBtc'=>$model->amount_currency,
				'wallet'=>$model->wallet,
				'timestamp'=>$model->date_pay,
			);
		}

		return $result;
	}

	/**
	 * список моделей для отображения
	 * записывает в self::$someDate['stats'] статистику
	 * @param array $filter ['storeId'=>,'dateStart'=>'01.08.2001','dateEnd'=>'01.08.20017']
	 * @return self[]
	 */
	public static function getListModels(array $filter = array())
	{
		//$limit = 1000;

		$storeId = ($filter['storeId']) ? $filter['storeId']*1 : 0;
		$timestampStart = strtotime($filter['dateStart'])*1;
		$timestampEnd = strtotime($filter['dateEnd'])*1;

		$conditionArr = array();

		if($storeId)
			$conditionArr[] = "`store_id` = '$storeId'";

		if($timestampStart)
			$conditionArr[] = "`date_pay` >= $timestampStart";

		if($timestampEnd)
			$conditionArr[] = "`date_pay` <= $timestampEnd";

		$conditionStr = implode(' AND ', $conditionArr);

		$models = self::model()->findAll(array(
			'condition'=>$conditionStr,
			'order'=>"`date_pay` DESC",
			//'limit'=>$limit,
		));

		/**
		 * @var self[] $models
		 */

		self::$someData['stats'] = array(
			'amountRub'=>0,
			'amountBtc'=>0,
			'amountUsd'=>0,
			'count'=>count($models),
		);

		self::$someData['successAmount'] = 0;

		foreach($models as $model)
		{
			self::$someData['stats']['amountRub'] += $model->amount_rub;
			self::$someData['stats']['amountBtc'] += $model->amount_currency;
			self::$someData['stats']['amountUsd'] += $model->amountUsd;
		}

		return $models;
	}

	public function getBtcLastPriceStr()
	{
		return formatAmount($this->btc_last_price, 3);
	}

	public function getUsdRateStr()
	{
		return ($this->usd_rate) ? formatAmount($this->usd_rate, 2) : '';
	}

	public function getAmountUsd()
	{
		return $this->btc_last_price * $this->amount_currency;
	}


	/**
	 * помечает выбранные платежи оплаченными(используется глобалфином при рассчете магазина)
	 * создает рублевый вывод
	 *
	 * @param int $storeId
	 * @param array $transactions
	 * @return bool
	 */
	public static function markPaid($storeId, $transactions)
	{
		if(!$storeApi = StoreApi::getModelByStoreId($storeId))
		{
			self::$lastError = 'магазин не найден';
			return false;
		}

		if(!$transactions or !is_array($transactions))
		{
			self::$lastError = 'платежи не выбраны';
			return false;
		}

		$withdrawAmount = 0;
		$transactionsCount = 0;
		$datePay = time();

		foreach($transactions as $id)
		{
			if(!$transaction = StoreApiTransaction::getModel($id))
			{
				self::$lastError = 'не найден storeApi платеж id='.$id;
				break;
			}

			if($transaction->date_pay)
			{
				self::$lastError = 'платеж '.$transaction->id.' уже был оплачен ранее';
				break;
			}

			if($transaction->store_id != $storeApi->store_id)
			{
				self::$lastError = 'платеж не принадлежит магазину id='.$transaction->id;
				break;
			}

			$transaction->date_pay = $datePay;

			if(!$transaction->save())
			{
				self::$lastError = 'сохранения storeApi платежа id='.$transaction->id;
				break;
			}

			echo "\n палтеж {$transaction->id} помечен оплаченым";

			$transactionsCount++;

			if($transaction->currency == StoreApiTransaction::CURRENCY_RUB)
				$withdrawAmount += $transaction->amount;
			else
			{
				//если тенге то находим конвертацию в рублях
				$transactionModel = $transaction->transactionModel->transactionModel;

				if(!$transactionModel)
					toLogError('не найден transactionModel'.__METHOD__, true);

				$withdrawAmount += $transactionModel->amount;
			}
		}

		if($withdrawAmount)
		{
			$withdrawModel = new self;
			$withdrawModel->scenario = self::SCENARIO_ADD;
			$withdrawModel->store_id = $storeApi->store_id;
			$withdrawModel->currency = self::CURRENCY_RUB;
			$withdrawModel->wallet = '';
			$withdrawModel->date_pay = $datePay;
			$withdrawModel->amount_rub = $withdrawAmount;

			if($withdrawModel->save())
			{
				self::$msg = "$transactionsCount платежей на сумму ".formatAmount($withdrawAmount, 0)." руб помечены оплачеными, вывод сохранен (store{$storeApi->store_id})";
				toLogRuntime(self::$msg);
				return true;
			}
			else
			{
				self::$lastError = "вывод на сумму $withdrawAmount не сохранен. обратитесь к админу";
				toLogError(self::$lastError);
				return false;
			}
		}

		return false;
	}

	public function getConfirmationsStr()
	{
		return formatAmount($this->confirmations, 0);
	}

	public static function updateWithdrawInfo()
	{
		$cfg = cfg('storeApi');
		$bot = Blockio::getInstance($cfg['key'], $cfg['secret']);

		$doneCount = 0;

		if($withdrawArr = $bot->getWithdrawList())
		{
			foreach($withdrawArr as $withdraw)
			{
				/**
				 * @var self $withdrawModel
				 */

				if(
					$withdrawModel = self::model()->findByAttributes(['withdraw_id'=>$withdraw['txid']])
					and $withdrawModel->confirmations != $withdraw['confirmations']*1
				)
				{
					$withdrawModel->confirmations = $withdraw['confirmations'];

					if($withdrawModel->save())
						$doneCount++;
				}
			}

		}
		else
			return false;

		return $doneCount;
	}

	/**
	 * если нет withdraw_id или не прошло еще достаточно времени то возвращает null
	 * если число подтверждений > self::CONFIRMATIONS_MIN true
	 * @return null|bool
	 */
	public function getIsConfirmed()
	{
		if($this->confirmations >= self::CONFIRMATIONS_MIN)
			return true;
		elseif($this->withdraw_id and time() - $this->date_pay > self::CONFIRMATION_TIME)
			return false;
		else
			return null;
	}

	/**
	 * получить выводы с withdraw_id и кол-вом подтверждений меньше self::CONFIRMATIONS_MIN
	 * @return self[]
	 */
	public static function getUnconfirmedWithdraws()
	{
		$date = time() - self::CONFIRMATION_TIME;

		return self::model()->findAll([
			'condition'=>"`withdraw_id`!='' AND `confirmations` < ".self::CONFIRMATIONS_MIN." AND `date_pay` < $date",
			'order'=>"`date_pay` DESC",
			'limit'=>50,
		]);
	}

	/**
	 * обновить баланс btc кошелька
	 */
	private static function updateBtcBalance()
	{
		$botCfg = cfg('storeApi');
		$bot = Blockio::getInstance($botCfg['key'], $botCfg['secret']);

		$balance = $bot->getBalance();

		if($balance!== false)
		{
			config('storeApiBalanceBtc', str_replace(',', '.', round($balance, 5)));
			config('storeApiBalanceBtcTimestamp', time());
		}
	}

	/**
	 * обновить комсу сети биткоин
	 */
	private static function updateBtcCommission()
	{
		$botCfg = cfg('storeApi');
		$bot = Blockio::getInstance($botCfg['key'], $botCfg['secret']);

		$commission = $bot->getWithdrawCommission();

		if($commission !== false)
			config('storeApiCommissionBtc', $commission);
	}

	public static function getWithdrawLimitRub()
	{
		$val = config('btc_usd_rate_btce') * self::AMOUNT_CURRENCY_MIN * config('usd_rate_parse_value')*1.2;

		return round($val, 5);
	}

	/**
	 * todo: заменить константу self::AMOUNT_RUB_MIN на вызов этой функции
	 * минимальная сумма для вывода в рублях для клиента
	 * @param $clientId
	 * @return float
	 */
	public static function getAmountRubMin($clientId)
	{
		$withdrawMinBtc = Blockio::BTC_WITHDRAW_MIN;
		$rateBtc = config('storeApiBtcRate');

		$client = Client::getModel($clientId);
		$rateUsd = $client->commissionRule->rateValue;

		return ceilAmount($rateBtc * $withdrawMinBtc * $rateUsd, 0);
	}

	/**
	 * @param int $storeId			стата либо по юзеру либо по клиенту
	 * @param int $userId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return self[]
	 */
	public static function getModels($timestampStart=0, $timestampEnd=0, $userId, $storeId = 0)
	{
		$timestampStart  = intval($timestampStart);
		$timestampEnd  = intval($timestampEnd);

		if($timestampEnd > time())
			$timestampEnd = time();

		//либо по юзеру либо по клиенту
		$condition = "`user_id`='$userId'";

		if($storeId)
			$condition .= " AND `store_id`='$storeId'";

		$models = self::model()->findAll([
			'condition'=>"
				`date_pay` >=$timestampStart AND `date_pay`<$timestampEnd
				AND $condition
			",
			'order'=>"`date_pay` DESC",
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
			'amount_rub',
			'amount_btc',
		];

		foreach($models as $model)
		{
			$result['count']++;

			$result['amount_rub'] += $model->amount_rub;
			$result['amount_btc'] += $model->amount_currency;
		}

		return $result;
	}

	/**
	 * по расписанию выполняет выплаты за найденные транзакции
	 */
	public static function startWithdraw()
	{
		$threadName = 'storeApiWithdraw';

		if(!Tools::threader($threadName))
			die('поток '.$threadName.' уже запущен');

		//сделать выплаты по каждому магазину
		$storeArr = StoreApi::getActiveStoreArr("`withdraw_limit` DESC");

		if(!$storeArr)
			echo '<br>не найдено активных магазинов';

		//обновить информацию о выводах
		self::updateWithdrawInfo();
		//toLogStoreApi('обновлено '.$withdrawUpdateCount.' выводов');

		self::updateBtcBalance();
		self::updateBtcCommission();

		if(cfg('shuffleStoreWithdraws'))
			shuffle($storeArr);

		foreach($storeArr as $storeApi)
		{
//			if($storeApi->store_id != 160)
//			{
//				//echo "\n  пропускаем нетестовые магазины";
//				continue;
//			}

			$datePay = time();	//чтобы у всех платежей магазина было одно время оплаты

			//выводы отключены
			if(!config('storeApiWithdrawEnabled'))
				continue;

			if(!$storeApi->withdraw_wallet)
				continue;

			//todo: проверить сходится ли сумма отправленных средств(в рублях) с суммой оплаченных транзакций магазина

			//успешные но не оплаченные транзакции
			if($transactions = NewYandexPay::getNotPaidTransactions($storeApi->store_id))
			{
				$withdrawModel = new self;
				$withdrawModel->scenario = self::SCENARIO_ADD;
				$withdrawModel->store_id = $storeApi->store_id;
				$withdrawModel->wallet = $storeApi->withdraw_wallet;
				$withdrawModel->date_pay = $datePay;

				$withdrawModel->amount_rub = 0;

				foreach($transactions as $transaction)
				{
					if(!$transaction->validate())
						toLogStoreApi('ошибка валидации транзакции '.$transaction->id.': '.$transaction::$lastError, true);

					$withdrawModel->amount_rub += $transaction->amount;
				}

				if($withdrawModel->amount_rub < $storeApi->withdrawLimitVal)
				{
					echo('<br>слишком маленькая сумма для вывода: '.$withdrawModel->amount_rub.', пропускаем (store_id='.$withdrawModel->store_id.')');
					continue;
				}

				$withdrawModel->currency = (preg_match(cfg('btcAddressRegExp'), $withdrawModel->wallet)) ?
					self::CURRENCY_BTC : '';

				if($withdrawModel->currency == self::CURRENCY_BTC)
				{
					//посчитать баксы по формуле клиента
					$usdRate = $storeApi->user->client->commissionRule->rateValue;
					$btcRate = config('storeApiBtcRate');

					$withdrawModel->btc_last_price = $btcRate;
					$withdrawModel->usd_rate = $usdRate;


					if(!$usdRate or !$btcRate)
						toLogStoreApi('ошибка в параметрах: $usdRate: '.$usdRate.', $btcRate: '.$btcRate, true);

					$usdAmount = floorAmount($withdrawModel->amount_rub/$usdRate, 2);

					$btcAmount = floorAmount($usdAmount/$btcRate, 8);

					//0.002 - комиссия при покупке биткоина
					//0.001 - комиссия при ввыводе
					$commission = StoreApi::getNetworkFee($withdrawModel->wallet, $btcAmount);

					if(!$commission)
						$commission = Blockio::BTC_WITHDRAW_COMMISSION_MAX;

					$withdrawModel->network_fee = $commission;

					$btcAmount -= $commission;

					$withdrawModel->amount_currency = $btcAmount;

				}
				else
				{
					toLogStoreApi('неизвестная валюта: '.$withdrawModel->currency);
					die;
				}

				if($withdrawModel->validate())
				{
					$withdrawAmount = $withdrawModel->amount_currency;
					$withdrawWallet = $withdrawModel->wallet;

					//$withdrawAmount = 0.003;
					//$withdrawWallet = '13wZLEfYarRrr3ZAaUoK8demCELe8pB4xD';
					//die($withdrawWallet.' '.$withdrawAmount);

					//$withdrawResult = StoreApi::withdrawBtc($withdrawWallet, $withdrawAmount);

					$withdrawResult = StoreApi::withdrawBtcBlockio($withdrawWallet, $withdrawAmount);


					if($withdrawResult)
					{
						echo '<br>успешная оплата для store_id='.$withdrawModel->store_id.' (withdrawId='.$withdrawResult.')';

						$withdrawModel->withdraw_id = $withdrawResult;

						/*
						$botCfg = cfg('storeApi');
						$bot = WexBot::getInstance($botCfg['key'], $botCfg['secret']);
						*/


						//обновить кэш баланса после успешного вывода
						sleep(10);
						self::updateBtcBalance();

						/*
						if($balance['usd'] !== false and $balance['btc'] !== false)
						{
							config('storeApiBalanceBtc', str_replace(',', '.', round($balance['btc'], 5)));
							config('storeApiBalanceUsd', str_replace(',', '.', $balance['usd']));
							config('storeApiBalanceTimestamp', time());
							toLogStoreApi('обновление баланса');
						}
						*/

						if($withdrawModel->save())
						{
							toLogStoreApi('вывод StoreApiWithdraw id='.$withdrawModel->id.' сохранен');

							foreach($transactions as $transaction)
							{
								$transaction->date_withdraw = $datePay;

								if(!$transaction->save())
								{
									toLogStoreApi('ВНИМАНИЕ!!!! возможна повторная оплата ошибка сохранения транзакции '.$transaction->id.': '.$transaction::$lastError);
									config('storeApiWithdrawEnabled', '');
									toLogStoreApi('ОТКЛЮЧАЮ ВЫВОДЫ');
								}
							}

							toLogStoreApi(count($transactions).' платежей на сумму '.$withdrawModel->amount_rub.' руб помечены оплаченными');
						}
						else
							toLogStoreApi('ВНИМАНИЕ!!!! возможна повторная оплата ошибка сохранения StoreApiWithdraw: '.$withdrawModel::$lastError, true);
					}
					else
						toLogStoreApi('ошибка вывода1 '.$withdrawAmount.' btc, store_id='.$withdrawModel->store_id.': '.Tools::arr2Str($withdrawModel->attributes));
				}
				else
					toLogStoreApi('ошибка валидации (вывод не будет выполнен): ('.self::$lastError.') '.Tools::arr2Str($withdrawModel->attributes));
			}
			else
				echo '<br>нет неоплаченых транзакций: store_id = '.$storeApi->store_id;
		}

		if(!config('storeApiWithdrawEnabled'))
			echo 'выводы отключены';
	}
}