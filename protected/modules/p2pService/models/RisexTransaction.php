<?php
/**
 *
 * @property int id
 * @property int transaction_id
 * @property int order_id
 * @property string requisites
 * @property string requisitesStr
 * @property string payment_system
 * @property string bank_type
 * @property string currency
 * @property string currencyStr
 * @property string crypto_currency
 * @property float crypto_amount
 * @property float price
 * @property float fiat_amount
 * @property float commissions_offer
 * @property float commissions_client
 * @property int client_id
 * @property int user_id
 * @property int date_add
 * @property string error
 * @property string comment
 * @property string status
 * @property string created_at
 * @property string dateAddStr
 * @property string createdAtStr
 * @property string dateCancelationStr
 * @property string statusStr
 * @property Client client
 * @property User user
 *
 */

class RisexTransaction extends Model
{
	const STATUS_VERIFIED = 'Verified'; //активная
	const STATUS_PAID = 'Paid'; //оплаченная (ставится клиентом, уведомление что он оплатил)
	const STATUS_FINISHED = 'Finished'; //завершена (мы получили деньги)
	const STATUS_AUTOCANCELED = 'Autocanceled'; //отменена по таймеру
	const STATUS_CANCELED = 'Canceled'; //отменена
	const STATUS_IN_DISPUTE = 'In dispute'; //в диспуте
	const STATUS_VERIFICATION = 'Verification'; // системный
	const STATUS_CANCELLLATION = 'Cancellation'; // системный
	const STATUS_FINISHING = 'Finishing'; // системный
	const STATUS_SELLER_REQUISITE = 'Seller requisite'; //продавец отправил реквизит, можно ставить “Paid” и отправлять ласт4
	const STATUS_ERROR = 'error';

	const SCENARIO_ADD = 'add';
	const AMOUNT_MIN = 3000;
	const AMOUNT_MAX = 100000;

	const CURRENCY_RUB = 'Рубль';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return [
		];
	}

	public function rules()
	{
		return [
			[
				'transaction_id, order_id, payment_system, bank_type, currency, crypto_currency, crypto_amount, price, fiat_amount,'.
				'commissions_offer, requisites, commissions_client, client_id, user_id, date_add, error, comment, status, created_at','safe'
			],
		];

	}

	public function tableName()
	{
		return '{{risex_transaction}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();

	}

	/**
	 * @return string
	 */
	public function getStatusStr()
	{
		return self::statusArr()[$this->status];
	}

	public static function statusArr()
	{
		return [
			self::STATUS_VERIFIED => 'активна',
			self::STATUS_PAID => 'оплата отмечена',
			self::STATUS_FINISHED => 'оплачена',
			self::STATUS_AUTOCANCELED => 'отменена',//'отменена по таймеру',
			self::STATUS_CANCELED => 'отменена',
			self::STATUS_IN_DISPUTE => 'проверка заявки',
			self::STATUS_VERIFICATION => 'подготовка',
			self::STATUS_CANCELLLATION => 'отменена',
			self::STATUS_FINISHING => 'оплачена', //завершение
			self::STATUS_ERROR => 'ошибка',
			self::STATUS_SELLER_REQUISITE => 'реквизиты готовы',
		];
	}

	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	public function getCurrencyStr()
	{
		if($this->currency == self::CURRENCY_RUB)
			return 'RUB';
		else
			return $this->currency;
	}


	public function getCreatedAtStr()
	{
		return date('d.m.Y H:i', $this->created_at);
	}

	public static function getAll()
	{
		return self::model()->findAll();
	}

	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $storeId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param bool $successOnly
	 * @return self[]
	 */
	public static function getModels($timestampStart, $timestampEnd, $clientId=0, $userId=0, $successOnly = true)
	{
		$userId = intval($userId);
		$clientId = intval($clientId);

		$timestampStart = intval($timestampStart);
		$timestampEnd = intval($timestampEnd);

		if($userId)
			$condition = " AND `user_id`='$userId'";

		if($clientId)
			$condition .= " AND `client_id`='$clientId'";

		if($successOnly)
			$condition .= " AND `status`='".self::STATUS_FINISHED."'";

		$models = self::model()->findAll([
			'condition'=>"
				`created_at`>=$timestampStart AND `created_at`<$timestampEnd
				 $condition
			",
			'order'=>"`date_add` DESC",
		]);

		return $models;
	}

	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $clientId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param bool $onlySuccess		только активированные
	 *
	 * @return array
	 */
	public static function getModelsForPagination($timestampStart=0, $timestampEnd=0, $clientId = 0, $userId = 0, $onlySuccess=false)
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
			$successCond = " AND `status`='".self::STATUS_FINISHED."'";

		$criteria = new CDbCriteria();
		$criteria->condition = "
				`created_at`>=$timestampStart AND `created_at`<$timestampEnd
				$userCond
				$successCond
			";
		$criteria->order = 'created_at DESC';

		$count = self::model()->count($criteria);

		$pagination = new CPagination($count);
		$pagination->pageSize = 40; // Количество элементов на страницу

		$pagination->applyLimit($criteria);
		$models = self::model()->findAll($criteria);

		return [
			'models' => $models,
			'pages' => $pagination
		];
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

			if($model->status == self::STATUS_FINISHED)
			{
				$result['amount'] += $model->fiat_amount;
				$result['countSuccess']++;
			}

			$result['allAmount'] += $model->fiat_amount;
		}

		return $result;
	}

	/**
	 * статистика по платежам за период
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param int $clientId
	 * @param int $userId
	 * @return array
	 */
	public static function getStats($timestampStart, $timestampEnd, $clientId = 0, $userId = 0)
	{
		$result = [
			'amountWait' => 0,	//поступления в ожидании
			'amountIn' => 0,	//успешных поступлений
		];

		$models = self::getModels($timestampStart, $timestampEnd, $clientId, $userId);

		foreach($models as $model)
		{
			if($model->status === self::STATUS_FINISHED)
			{
				$result['amountIn'] += $model->fiat_amount;
			}
			elseif($model->status === self::STATUS_SELLER_REQUISITE and $model->fiat_amount > 0)
				$result['amountWait'] += $model->fiat_amount;
		}

		//if(YII_DEBUG)
		//	prrd($result);

		return $result;
	}

	/**
	 * Создание сделки
	 *
	 * @param User $user
	 * @param $amount - сумма сделки в рублях
	 *
	 * @return bool
	 */
	public static function createDeal($user, $amount, $orderId=false)
	{
		$api = new RiseXApi;
		$data = $api->createDeal($amount);

//		var_dump($data);die;

		$deal = $data['data'];

		if(isset($data['message']))
		{
			self::$lastError = $data['message'];
			return false;
		}
		elseif(!is_array($deal))
		{
			self::$lastError = 'Rise error, пустые данные createDeal: '.arr2str($data);
			return false;
		}

		$transactionInfo['id'] = $deal['id'];
		$transactionInfo['requisites'] = $deal['requisites']['seller_info'];
		$transactionInfo['paymentSystem'] = $deal['payment_system']['title'];
		$transactionInfo['bankType'] = $deal['ad']['banks'][0]['title'];
		$transactionInfo['currency'] = $deal['currency']['title'];
		$transactionInfo['cryptoCurrency'] = $deal['crypto_currency']['title'];
		$transactionInfo['cryptoAmount'] = $deal['crypto_amount'];
		$transactionInfo['price'] = $deal['price'];
		$transactionInfo['status'] = $deal['status']['title'];
		$transactionInfo['bank'] = $deal['bank']['title'];
		$transactionInfo['fiatAmount'] = $deal['fiat_amount'];
		$transactionInfo['time'] = $deal['time'];
		$transactionInfo['createdAt'] = $deal['created_at'];
		$transactionInfo['commissionsOffer'] = $deal['commissions']['offer'];
		$transactionInfo['commissionsClient'] = $deal['commissions']['client'];

		if(!$model = RisexTransaction::model()->findByAttributes(['transaction_id'=>$transactionInfo['id']]))
		{
			$model = new RisexTransaction;
			$model->date_add = time();
			$model->scenario = RisexTransaction::SCENARIO_ADD;
			$model->client_id = $user->client_id;
			$model->user_id = $user->id;
		}

		$model->transaction_id = $transactionInfo['id'];
		if($orderId)
		{
			$model->order_id = $orderId;
		}
		$model->requisites = $transactionInfo['requisites'];
		$model->payment_system = $transactionInfo['paymentSystem'];
		$model->bank_type = $transactionInfo['bankType'];
		$model->currency = $transactionInfo['currency'];
		$model->crypto_currency = $transactionInfo['cryptoCurrency'];
		$model->crypto_amount = $transactionInfo['cryptoAmount'];
		$model->price = $transactionInfo['price'];
		$model->fiat_amount = $transactionInfo['fiatAmount'];
		$model->commissions_offer = $transactionInfo['commissionsOffer'];
		$model->commissions_client = $transactionInfo['commissionsClient'];
		$model->created_at = strtotime($transactionInfo['createdAt']);
		$model->status = $transactionInfo['status'];

		return $model->save();
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
	 * получить список сделок и сохранить(обновить) в базу
	 * @return bool|int
	 */
	public static function saveDealList()
	{
		$api = new RiseXApi;
		$dataList = $api->dealListNew();

		$dataListBefore = $dataList;

		if(isset($dataList['meta']['from']) and isset($dataList['meta']['last_page']))
		{
			$dataListTotal = [];
			$dataListTotal[] = $dataList;

			$begin = $dataList['meta']['from'];
			$end = $dataList['meta']['last_page'];

			if($end > 1)
			{
				for($page = 2; $page < $end; $page++)
				{
					$dataListTotal[] = $api->dealListNew($page);
				}
			}
		}
		else
		{
//			toLogError('RisexTransaction: ошибка получения списка заявок');
			return false;
		}

//		var_dump('dataListBefore');
//		var_dump($dataListBefore);
//		var_dump('$dataListTotal');
//		var_dump($dataListTotal);die;

		$addCount = 0;

		foreach($dataListTotal as $pageData)
		{
			if(!is_array($pageData))
			{
				self::$lastError = 'Rise error, пустые данные getDealList';
				return false;
			}

			foreach($pageData['data'] as $key=>$data)
			{
				unset($data['author']); //кучу левой инфы по апи выгружают
				unset($data['offer']); //кучу левой инфы по апи выгружают
				unset($data['statistics']); //кучу левой инфы по апи выгружают

				$transactionInfo['id'] = $data['id'];
				$transactionInfo['requisites'] = $data['requisites']['seller_info'];
				$transactionInfo['paymentSystem'] = $data['payment_system']['title'];
				$transactionInfo['bankType'] = $data['ad']['banks'][0]['title'];
				$transactionInfo['currency'] = $data['currency']['title'];
				$transactionInfo['cryptoCurrency'] = $data['crypto_currency']['title'];
				$transactionInfo['cryptoAmount'] = $data['crypto_amount'];
				$transactionInfo['price'] = $data['price'];
				$transactionInfo['status'] = $data['status']['title'];
				$transactionInfo['bank'] = $data['bank']['title'];
				$transactionInfo['fiatAmount'] = $data['fiat_amount'];
				$transactionInfo['time'] = $data['time'];
				$transactionInfo['createdAt'] = $data['created_at'];
				$transactionInfo['commissionsOffer'] = $data['commissions']['offer'];
				$transactionInfo['commissionsClient'] = $data['commissions']['client'];

				if(!$model = RisexTransaction::model()->findByAttributes(['transaction_id'=>$transactionInfo['id']]))
				{
					$model = new RisexTransaction;
					$model->date_add = time();
					$model->scenario = RisexTransaction::SCENARIO_ADD;
				}

				$model->transaction_id = $transactionInfo['id'];
				$model->requisites = $transactionInfo['requisites'];
				$model->payment_system = $transactionInfo['paymentSystem'];
				$model->bank_type = $transactionInfo['bankType'];
				$model->currency = $transactionInfo['currency'];
				$model->crypto_currency = $transactionInfo['cryptoCurrency'];
				$model->crypto_amount = $transactionInfo['cryptoAmount'];
				$model->price = $transactionInfo['price'];
				$model->fiat_amount = $transactionInfo['fiatAmount'];
				$model->commissions_offer = $transactionInfo['commissionsOffer'];
				$model->commissions_client = $transactionInfo['commissionsClient'];
				$model->created_at = strtotime($transactionInfo['createdAt']);
				$model->status = $transactionInfo['status'];

				if($model->save())
					$addCount++;
			}

		}

		return $addCount;
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

	public function getRequisitesStr()
	{
		if(!$this->requisites)
			return  '';

		return substr($this->requisites, 0, 4)
			.' '.substr($this->requisites, 4, 4)
			.' '.substr($this->requisites, 8, 4)
			.' '.substr($this->requisites, 12, 4);
	}

	public function getDateCancelationStr()
	{
		$liveTime = 3600;
		return 'действует до '.date('d.m.Y H:i', $this->created_at + $liveTime);
	}


	/**
	 * отправить информацию об оплате заявки
	 * @return bool|int
	 */
	public static function acceptPayment($id)
	{
		$api = new RiseXApi;
		$data = $api->acceptPayment($id);

//		if(YII_DEBUG)
//		{
//			var_dump($data);
//			die;
//		}

		if(!is_array($data))
		{
			self::$lastError = 'Rise error, пустые данные acceptPayment';
			return false;
		}

		$deal = $data['data'];

		if(isset($data['message']))
		{
			self::$lastError = $data['message'];
			return false;
		}
		elseif(!is_array($deal))
		{
			self::$lastError = 'Rise error, пустые данные acceptPayment: '.arr2str($data);
			return false;
		}

		if($model = RisexTransaction::model()->findByAttributes(['transaction_id'=>$id]))
		{
			$model->status = $deal['status']['title'];
			return $model->save();
		}
		else
			return false;

	}

	/**
	 * отменить заявку
	 * @return bool|int
	 */
	public static function cancelPayment($id)
	{
		$api = new RiseXApi;
		$data = $api->cancelPayment($id);

		if(!is_array($data))
		{
			self::$lastError = 'Rise error, пустые данные cancelPayment';
			return false;
		}

		$deal = $data['data'];

		if(isset($data['message']))
		{
			self::$lastError = $data['message'];
			return false;
		}
		elseif(!is_array($deal))
		{
			self::$lastError = 'Rise error, пустые данные cancelPayment: '.arr2str($data);
			return false;
		}

		if($model = RisexTransaction::model()->findByAttributes(['transaction_id'=>$id]))
		{
			$model->status = $deal['status']['title'];
			return $model->save();
		}
		else
			return false;

	}
}



