<?php

/**
 * @property int id
 * @property float amount
 * @property int client_id
 * @property int user_id
 * @property string currency
 * @property string status
 * @property string error
 * @property string comment
 * @property string email
 * @property string urlShort
 * @property int date_add   дата добавления
 * @property string amountStr
 * @property string statusStr
 * @property string order_id	номер заказа на стороне клиента(для возможной сверки отдельных платежей руками)
 * @property string invoice_id	номер заказа на стороне поставщика услуг
 * @property int date_pay	дата оплаты
 * @property string pay_params	json данные карты (возможно зашифрованные)
 * @property string pay_url 	ссылка на оплату
 * @property int client_order_id	передает клиент
 * @property string success_url переброс на сайт клиента после успешной оплаты
 * @property string fail_url	переброс на сайт клиента при неудаче
 * @property string proxy	прокси для статы
 * @property array payParams
 * @property string dateAddStr
 * @property string datePayStr
 * @property string pan - получаем после оплаты
 * @property string hash - получаем после оплаты
 * @property int is_notified - флаг доставки уведомления
 */
class WalletSTransaction extends Model
{
	const SCENARIO_ADD = 'add';

	const STATUS_SUCCESS = 'success';
	const STATUS_WAIT = 'wait';
	const STATUS_ERROR = 'error';
	const STATUS_PROCESSING = 'processing';

	//оплата через форму ввода карты либо напрямую редирект в банк
	const PAYMENT_METHOD = 'bank'; //form|bank

	const CURRENCY_RUB = 'RUB';
	const CURRENCY_USD = 'USD';
	const CURRENCY_EUR = 'EUR';

	const AMOUNT_MIN = 0.001;
	const AMOUNT_MAX = 3000;

	const ERROR_BAN = 'ban';
	const ERROR_WAIT = 'wait';

	const CANCEL_TIMEOUT = 1800;	//автоотмена через

	public $transAmount = 0;	//при подсчете суммы успешных платежей

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{wallet_s_transaction}}';
	}

	public function rules()
	{
		return [
			['amount', 'numerical', 'min'=>self::AMOUNT_MIN, 'max'=>self::AMOUNT_MAX, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],
			['client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id', 'allowEmpty'=>false],
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false],
			['currency', 'in', 'range'=>array_keys(self::getCurrencyArr())],
			['status', 'in', 'range'=>array_keys(self::getStatusArr())],
			['error', 'length', 'max'=>255],
			['comment', 'length', 'max'=>255],
			['email', 'email', 'message'=>'неверный email', 'on'=>self::SCENARIO_ADD],
			['date_add', 'numerical', 'max'=>time()+3600],
			['order_id', 'length', 'max'=>255],
			['pay_params', 'length', 'max'=>30000],
			['client_order_id', 'length', 'max'=>255, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],
			['success_url', 'url', 'message'=>'неверный successUrl','on'=>self::SCENARIO_ADD],
			['fail_url', 'url', 'message'=>'неверный failUrl', 'on'=>self::SCENARIO_ADD],
			['pay_url', 'url', 'message'=>'неверный failUrl', 'on'=>self::SCENARIO_ADD],
			['proxy', 'length', 'max'=>100],
			['client_order_id, pan, hash, invoice_id, is_notified', 'safe'],
		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			if(!$this->date_add)
				$this->date_add = time();
		}

		$this->error = strip_tags($this->error);

		return parent::beforeSave();
	}

	/**
	 * @return array
	 */
	public static function getCurrencyArr()
	{
		return [
			self::CURRENCY_RUB => 'рубль',
			self::CURRENCY_USD => 'доллар',
			self::CURRENCY_EUR => 'евро',
		];
	}

	/**
	 * @return array
	 */
	public static function getStatusArr()
	{
		return [
			self::STATUS_SUCCESS => 'завершен',
			self::STATUS_WAIT => 'ожидание',
			self::STATUS_ERROR => 'ошибка',
			self::STATUS_PROCESSING => 'в процессе',
		];
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
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param int $clientId
	 * @param int $userId
	 * @param string $type (successIn|in)
	 * @return  self[]
	 */
	public static function getModels($timestampStart, $timestampEnd, $clientId=0, $userId = 0, $type='')
	{
		$timestampStart *= 1;
		$timestampEnd *= 1;

		$cond =  [];

		$cond[] = "`date_add` >= $timestampStart AND `date_add` < $timestampEnd";

		if($type == 'successIn')
			$cond[] = "`amount` > 0 AND `status`='".self::STATUS_SUCCESS."'";

		if($clientId)
			$cond[] = "`client_id` = '$clientId'";

		if($userId)
			$cond[] = "`user_id` = '$userId'";

		return self::model()->findAll([
			'condition' => implode(" AND ", $cond),
			'order' => "`date_add` DESC",
		]);
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
		$intervalMax = 3600 * 24 * 500;

		$userId *=1;
		$clientId *=1;

		$timestampStart *=1;
		$timestampEnd *=1;

		if($timestampEnd > time())
			$timestampEnd = time();

		if($timestampEnd - $timestampStart > $intervalMax)
		{
			$timestampStart = $timestampEnd - $intervalMax;
		}

		//либо по юзеру либо по клиенту
		$userCond = ($userId) ? " AND `user_id`='$userId'" :
			(($clientId) ? " AND `client_id`='$clientId'" : '');

		$successCond = '';

		if($onlySuccess)
			$successCond = " AND `status`='".self::STATUS_SUCCESS."'";

		$criteria = new CDbCriteria();
		$criteria->condition = "
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				$userCond
				$successCond
			";
		$criteria->order = 'date_add DESC';

		$count = self::model()->count($criteria);

		$pagination = new CPagination($count);
		$pagination->pageSize = 20; // Количество элементов на страницу

		$pagination->applyLimit($criteria);
		$models = self::model()->findAll($criteria);

		return [
			'models' => $models,
			'pages' => $pagination,
			'header' => '',
		];
	}


	public function getAmountStr()
	{
		$result = formatAmount($this->amount, 2);

		return $result.' '.$this->currency;
	}

	public function getStatusStr()
	{
		return self::getStatusArr()[$this->status];
	}

	/**
	 * статистика по платежам за период
	 * @param int $timestampStart
	 * @param int $timestampEnd
	* @return array
	 */
	public static function getStatsIn($timestampStart, $timestampEnd, $clientId = 0, $userId = 0)
	{
		$result = [
			'amountWait' => 0,	//поступления в ожидании
			'amountIn' => 0,	//успешных поступлений
		];

		$models = self::getModels($timestampStart, $timestampEnd, $clientId, $userId, 'successIn');

		foreach($models as $model)
		{
			if($model->status === self::STATUS_SUCCESS)
			{
				$result['amountSuccess'] += $model->amount;

				if($model->amount > 0)
					$result['amountIn'] += $model->amount;
			}
			elseif($model->status === self::STATUS_WAIT and $model->amount > 0)
				$result['amountWait'] += $model->amount;
		}

		return $result;
	}

	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $clientId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param bool $onlySuccess		только активированные
	 * @return self[]
	 */
//	getModels

	/**
	 * @param self[] $models
	 * @return array
	 */
	public static function getStats($models)
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

			if($model->status == self::STATUS_SUCCESS)
			{
				$result['amount'] += $model->amount;
				$result['countSuccess']++;
			}

			$result['allAmount'] += $model->amount;
		}

		return $result;
	}

	/**
	 * генерация уникального order_id заданной длины
	 * @param int $length
	 * @return int|false
	 */
	public static function generateOrderId($length = 16)
	{
		$code = false;

		//ограниченное число попыток на генерацию
		for($i=1; $i<=1000; $i++)
		{
			$code = Tools::generateCode('123456789', $length);

			if(!self::getModel(['order_id'=>$code]))
				break;
		}

		return $code;
	}

	/**
	 * генерация уникального client_order_id заданной длины
	 * @param int $length
	 * @param int $clientId
	 * @return int|false
	 */
	public static function generateClientOrderId($length = 16, $clientId)
	{
		$code = false;

		//ограниченное число попыток на генерацию
		for($i=1; $i<=1000; $i++)
		{
			$code = Tools::generateCode('123456789', $length);

			if(!self::getModel(['client_order_id'=>$code, 'client_id'=>$clientId]))
				break;
		}

		return $code;
	}

	/**
	 * ссылка  на форму для оплаты
	 * @param int $amount
	 * @param int $clientOrderId
	 * @param string $successUrl
	 * @param string $failUrl
	 * @param string $paymentType
	 * @return bool|string
	 *
	 * $params = [
	 	'amount' => 100,
	  	'clientOrderId' => 2222222,
	  	'comment' => 'payment_2222222',
	  	'email' => 'FigueiraSoriyah92@mail.ru',
	  	'name' => 'IVAN',
	  	'surname' => 'IVANOV',
	  	'successUrl' => 'https://google.com',
	  	'failUrl' => 'https://yandex.ru',
	   ]
	 */
	public static function getPayUrl($params)
	{
		$params['clientOrderId'] *= 1;

		if(YII_DEBUG)
		{
			$config = Yii::app()->getModule('walletS')->config["testParams"];
		}
		else
		{
			$config = Yii::app()->getModule('walletS')->config["devParams"];
		}

		if(!$params['clientOrderId'])
			$params['clientOrderId'] = self::generateOrderId(16);

		$amount = floor(preg_replace('!\s!', '', $params['amount']));

		$orderId = self::generateOrderId(16);

		$model = new self;
		$model->scenario = self::SCENARIO_ADD;

		$model->amount = $amount;
		$model->client_id = $params['clientId'];
		$model->user_id = $params['userId'];
		$model->currency = self::CURRENCY_RUB;
		$model->status = self::STATUS_WAIT;
		$model->comment = $params['comment'];
		$model->order_id = $orderId;
		$model->email = $params['email'];
		$model->client_order_id = $params['clientOrderId'];
		$model->success_url = $params['successUrl'];
		$model->fail_url = $params['failUrl'];

		if(YII_DEBUG)
			$bot = new WalletSBot(true);
		else
			$bot = new WalletSBot();

		$createParams = [
			"order_nr" => $orderId.'',
			"amount" => formatAmount($params['amount']/$config["currentRateEur"], 2),
			"payer_name" => $params['name'],
			"payer_lname" => $params['surname'],
			"description" => $params['comment'],
			"success_url" => $params['successUrl'],
			"cancel_url" => $params['failUrl'],
			"payer_email" => $params['email'],
		];

//		var_dump($params);

		$responce = $bot->createInvoice($createParams);

		if(!$responce["invoice_url"])
		{
			if(YII_DEBUG)
			{
				var_dump($responce);
			}

			self::$lastError = 'ошибка создания ссылки '.is_array($responce)?arr2str($responce):$responce;
			toLogError(self::$lastError);
			return false;

		}

		//эти параметры передаются на страницу обменника
		$pageParams = [
			'invoiceId' => $responce["invoice_id"],
			'payUrl' => $responce["invoice_url"],
			'email' => $params['email'],
		];

		$model->pay_url = 'https://easyexchange.pw?param='.base64_encode(json_encode($pageParams));
		$model->invoice_id = $responce["invoice_id"];

		if($model->save())
		{
			self::$someData['orderId'] = $model->order_id;
			return $model->pay_url;
		}
		else
			return false;
	}

	/**
	 * @return string
	 */
	public function getUrlShort()
	{
		if(preg_match('!invoice/([\w\d\-]+)!', $this->pay_url, $res))
			return '...'.$res[1];
		elseif(preg_match('!param=([\w\d\-]{15})!', $this->pay_url, $res))
			return '...'.$res[1];
		else
			return '';
	}

	/**
	 * @param string $msgUser
	 * @param array $params
	 * @param Sender|bool $sender
	 * @param string $msgAdmin
	 * @return array
	 */
	private static function walletSError($msgUser, $params, $sender = false, $msgAdmin = '')
	{
		self::$lastError = $msgUser;

		if(!$msgAdmin)
			$msgAdmin = $msgUser;

		if($sender)
			$msgAdmin .= ', httpCode '.$sender->info['httpCode'][0];

		$msgAdmin .= Tools::arr2Str($params);

		self::log($msgAdmin);

		return [];
	}

	public static function log($msg)
	{
		Tools::log('walletS: '.$msg, null, null, 'test');
	}

	/**
	 * @param string $error
	 * @return bool
	 */
	public function cancelOrder($error = '')
	{
		if($this->status === self::STATUS_ERROR)
			return true;
		elseif($this->status !== self::STATUS_WAIT)
		{
			self::$lastError = 'заявка уже подтверждена или не актуальна';
			return false;
		}

		$this->status = self::STATUS_ERROR;
		//$this->error = 'отменен в '.date('d.m.Y H:i');

		if($error)
			$this->error = $error;

		if($this->save())
		{
			self::log('заявка отменена '.$this->order_id."(причина: $error)"
				.' params: '.Tools::arr2Str($this->payParams));
			return true;
		}
		else
			return false;
	}

	public function getPayParams()
	{
		if($result = @json_decode($this->pay_params, true))
			return $result;
		else
			return [];
	}

	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	public function getDatePayStr()
	{
		return ($this->date_pay) ? date('d.m.Y H:i', $this->date_pay) : '';
	}

	public static function startCancelOldTransactions()
	{
		$countAtOnce = 1000;
		$timestampMin = time() - self::CANCEL_TIMEOUT;
		$error = 'отменен по таймауту';

		if(!Tools::threader('walletSCancelOldTransactions'))
		{
			self::$lastError = 'already run';
			return false;
		}

		$models = self::model()->findAll([
			'condition' => "`status`='".self::STATUS_WAIT."' AND `date_add` < $timestampMin",
			'limit' => $countAtOnce,
			'order' => "`date_add` DESC",
		]);

		/**
		 * @var self[] $models
		 */

		$count = 0;

		//нужно именно циклом чтобы баланс на аккаунтах обновлялся
		foreach($models as $model)
		{
			if($model->cancelOrder($error))
				$count++;
		}

		if($count > 0)
			self::log('отменено '.$count.' заявок');

		config('walletSCancel', time());
	}

	public static function hash(array $params, $apiSecret)
	{
		unset($params['hash']);

		$result = '';

		foreach($params as $val)
			$result .= $val;

		$result .= $apiSecret;

		return md5($result);
	}

	public static function startApiNotification()
	{
		$cfg = cfg('storeApi');
		/**
		 * @var User[] $users
		 */
		$users = User::model()->findAll();

		foreach($users as $user)
		{
			if(!$user->url_result)
				continue;

			$successPayments = self::model()->findAll([
				'condition' => "`user_id`=$user->id AND `status`='".self::STATUS_SUCCESS."' AND `is_notified`=0",
				'order' => "`date_pay` DESC",
			]);
			/**
			 * @var self[] $successPayments
			 */

			$payments = [];

			foreach($successPayments as $successPayment)
			{
				$params = [
					'id' => $successPayment->id,
					'orderId' => $successPayment->order_id,
					'clientOrderId' => $successPayment->client_order_id,
					'url' => $successPayment->pay_url,
					'amount' => $successPayment->amount,
					'currency' => 'RUB',
					'status' => $successPayment->status,
					'timestampPay' => $successPayment->date_pay,
					'error' => $successPayment->error,
					'system' => 'WalletS',
				];

				$params['hash'] = self::hash($params, $user->api_secret);
				$payments[] = $params;
			}

			if($payments)
			{
				$sender = new Sender;
				$sender->followLocation = false;
				$sender->proxyType = $cfg['notificationProxyType'];
				$sender->useCookie = false;

				$result = [
					'payments' => $payments,
				];

				$content = $sender->send($user->url_result, json_encode($result), $cfg['notificationProxy']);

				toLogRuntime('API MANAGER content WalletS notification'.arr2str($content));

				if($content === 'OK')
				{
					foreach($successPayments as $successPayment)
					{
						$successPayment->is_notified = 1;

						if($successPayment->save())
						{
							toLogRuntime('API MANAGER WalletS платеж order_id='.$successPayment->order_id.' уведомлен');
						}
						else
						{
							toLogRuntime('API MANAGER ошибка уведомления платежа WalletS id='.$successPayment->id);
							return false;
						}
					}
				}
				else
				{
//				убрать вывод в логи после того как кл у себя настроит
					toLogRuntime('API MANAGER ошибка уведомления WalletS: url='.$user->url_result.' contentLength='.strlen($content).', httpCode='
						.$sender->info['httpCode'][0].', url = '.$user->url_result);
				}

			}
		}
		return true;
	}

}