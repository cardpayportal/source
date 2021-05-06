<?php

/**
 * апи для менеджеров
 * @property string errorCode
 * @property string $errorMsg
 * @property string $rawRequest
 * @property array $request
 * @property User $user
 * @property StoreApi $store
 */
class ApiStoreController extends CController
{
	const ERROR_DEBUG = 'debug';
	const ERROR_ACCESS = 'accessDenied';
	const ERROR_REQUEST = 'invalidRequest';
	const ERROR_REPEAT_REQUEST = 'repeatRequest';
	const ERROR_METHOD = 'invalidMethod';
	const ERROR_ORDER = 'errorOrder';
	const ERROR_DATE = 'errorDate';
	const ERROR_OTHER = 'errorOther';
	const ERROR_STORE = 'errorStore';

	const REQUEST_PAUSE = 1;

	private $errorCode = '';
	private $errorMsg = '';

	private $rawRequest = '';
	private $request = [];

	private $user;
	private $store;

	protected function beforeAction($action)
	{
		session_write_close();

		sleep(self::REQUEST_PAUSE);

		$this->rawRequest = file_get_contents('php://input');
		$this->request = @json_decode($this->rawRequest, true);

		if(!is_array($this->request))
		{
			//запрос не в json
			$this->errorCode = self::ERROR_REQUEST;
			$this->resultOut();
		}

		//если доступа нет то рендерим сразу
		if($this->checkAccess())
			return parent::beforeAction($action);
		else
			$this->resultOut();
	}

	private function checkAccess()
	{
		if(YII_DEBUG and !Tools::isAdminIp())
		{
			$this->errorCode = self::ERROR_DEBUG;
			return false;
		}

		if(!$this->request['key'] or !$this->request['method'] or !$this->request['hash'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указаны необходимые параметры (key, storeId, method, hash)';
			return false;
		}

		if(in_array($this->request['method'], self::getAllowedMethods()) === false)
		{
			$this->errorCode = self::ERROR_METHOD;
			return false;
		}

		$this->user = User::getModel(['api_key'=>$this->request['key']]);

		if(!$this->user or !$this->user->active)
		{
			$this->errorCode = self::ERROR_ACCESS;
			$this->errorMsg = 'неверный key';
			return false;
		}

		$hashParams = $this->request;
		unset($hashParams['hash']);
		$hash = self::hash($hashParams, $this->user->api_secret);

		if($hash !== $this->request['hash'])
		{
			$this->errorCode = self::ERROR_ACCESS;
			$this->errorMsg = 'неверный hash';
			return false;
		}

		$this->store = $this->getStore($this->request['storeId']);

		return true;
	}

	private function getErrorMsg()
	{
		$arr = [
			self::ERROR_DEBUG => 'тех работы',
			self::ERROR_ACCESS => 'доступ запрещен',
			self::ERROR_REQUEST => 'неверный формат запроса',
			self::ERROR_METHOD => 'неверный параметр method',
			self::ERROR_ORDER => 'ошибка в orderId',
			self::ERROR_OTHER => 'неизвестная ошибка',
			self::ERROR_STORE => 'неверный storeId',
		];

		if($this->errorMsg)
			return $this->errorMsg;

		if($this->errorCode)
			return $arr[$this->errorCode];
		else
			return '';
	}

	private function resultOut($result = [])
	{
		$result = [
			'result'=>$result,
			'errorCode'=>$this->errorCode,
			'errorMsg'=>$this->getErrorMsg(),
		];

		//кеширование запроса
		$apiRequest = new ManagerApiRequest;
		$apiRequest->scenario = $apiRequest::SCENARIO_ADD;

		if($this->user)
			$apiRequest->user_id = $this->user->id;

		if($this->request)
		{
			$apiRequest->method = $this->request['method'];
			$apiRequest->body = Tools::arr2Str($this->request);
		}
		else
			$apiRequest->body = $this->rawRequest;

		$apiRequest->response = Tools::arr2Str($result);
		$apiRequest->raw_response = json_encode($result);
		$apiRequest->error = $result['errorCode'];

		if(!$apiRequest->save())
			toLogError('ошибка сохранения ManagerApiRequest: '.$apiRequest::$lastError);

		$this->renderPartial('//system/json', [
			'result'=>$result,
		]);

		Yii::app()->end();
	}

	public function actionIndex()
	{
		$method = $this->request['method'];

		$result = $this->$method();

		$this->resultOut($result);
	}

	/**
	 * доступные методы
	 * @return array
	 */
	public static function getAllowedMethods()
	{
		return [
			'getStoreList',
			'updateStoreInfo',
			'getPayUrlExchange',
			'checkPayment',
			'getStats',
			'getWithdrawals',
		];
	}



	public static function getCurrencies()
	{
		return [
			'RUB',
		];
	}

	/**
	 * выдает ссылку на оплату киви вида
	 * https://qiwi.com/payment/form/99?amountFraction=0&currency=643&extra[%27account%27]=%2B79999999999&extra[%27comment%27]=commentTxt&amountInteger=11111
	 * amount, currency
	 * @return array|false
	 */
	private function getPayUrlQiwi()
	{
		session_write_close();

		$cfg = cfg('storeApi');

		$result = [];

		//валюта
		if(in_array($this->request['currency'], self::getCurrencies()) === false)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'неверная валюта';
			return $result;
		}

		//сумма
		$amount = $this->request['amount']*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		if(round($amount) != $amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'сумма должна быть целым числом';
			return $result;
		}

		$minAmount = 100;
		$maxAmount = 3000;

		if(!$amount or $amount < $minAmount or $amount > $maxAmount)
		{
			$this->errorCode = StoreApi::ERROR_AMOUNT;
			$this->errorMsg = 'ошибка в amount: должен быть от '.$minAmount.' до '.$maxAmount;
			$this->resultOut();
		}

		$result = NewYandexPay::getPayUrlQiwi($this->user->id, $amount, true, $this->request['orderId']*1);

		if(!$result)
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = NewYandexPay::$lastError;
		}

		$result = [
			'url' => $result,
			'orderId' => $this->request['orderId'],
		];

		$this->resultOut($result);
	}


	/**
	 * тест киви для нового кл
	 * @return array
	 */
	private function getPayParamsQiwi()
	{
		session_write_close();

		$result = [];

		if(!$this->user->client->pick_accounts_next_qiwi)
		{
			$result = [];
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = 'выдача реквизитов отключена';
			return $result;
		}

		$amount = $this->request['amount']*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		if($result = NextQiwiPay::getPayParams($this->user->id, $amount, $this->request['requestId']))
		{
			//$result['orderId'] = NextQiwiPay::$someData['qiwiPayId'];

			$result = [
				'wallet' => $result['wallet'],
				'amount' => $result['amount'],
				'comment' => $result['comment'],
				'orderId' => $result['orderId'],
			];

			return $result;
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = NextQiwiPay::$lastError;
		}

		return $result;
	}

	/**
	 * @return array [
	 * 		'orderId'=>213123,
	 * 		'status'=>'wait|success|error',
	 * ]
	 */
	private function checkQiwiPayment()
	{
		$result = [];

		if(!$this->user->client->pick_accounts_next_qiwi)
		{
			$result = [];
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = 'выдача реквизитов отключена';
			return $result;
		}

		$orderId = $this->request['orderId']*1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		$order = NewYandexPay::model()->findByAttributes(['id'=>$orderId]);

		//повышаем приоритет проверки
		if($accountModel = Account::model()->findByAttributes(['login'=>$order->wallet]))
			$accountModel->setPriority(Account::PRIORITY_NOW);

		/**
		 * @var NextQiwiPay $order
		 */

		if(!$order or $order->user_id !== $this->user->id)
		{
			$this->errorCode = self::ERROR_ORDER;
			$this->errorMsg = 'orderId не найден';
			return $result;
		}

		$result = [
			'orderId' => $order->id,
			'status' => $order->status,
		];

		return $result;
	}

	/**
	 * выдает StoreApi по storeId из запроса
	 * @param int $storeId
	 * @return StoreApi
	 */
	private function getStore($storeId)
	{
		$realStoreId = $this->user->id.'_'.$storeId;
		return StoreApi::getModel(['id'=>$realStoreId]);
	}

	/**
	 * @param array $params
	 * @param string $privateKey
	 * @return string
	 */
	public static function hash(array $params, $privateKey)
	{
		$hashStr = '';

		foreach($params as $val)
			$hashStr .= $val;

		return md5($hashStr.$privateKey);
	}

	//если store не найден то создать
	/**
	 * @return array
	 */
	private function updateStoreInfo()
	{
		$model = $this->store;

		$params = [];

		if(!$model)
		{
			$model = new StoreApi;
			$model->scenario = StoreApi::SCENARIO_ADD;
			$params['id'] = $this->user->id.'_'.$this->request['storeId'];
			$params['user_id'] = $this->user->id.'_'.$this->request['storeId'];
			$params['client_id'] = $this->user->client_id;
		}

		if(isset($this->request['urlResult']))
			$params['url_result'] = $this->request['urlResult'];

		if(isset($this->request['withdrawWallet']))
			$params['withdraw_wallet'] = $this->request['withdrawWallet'];

		$model->attributes = $params;

		if($model->save())
		{
			return [
				'storeId' => $model->localId,
				'withdrawWallet' => $model->withdraw_wallet,
				'urlResult' => $model->url_result,
			];
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = $model::$lastError;
		}
	}

	private function getStoreList()
	{
		$user  = $this->user;
		$models = StoreApi::model()->findAll("`user_id`='$user->id'");
		/**
		 * @var StoreApi[] $models
		 */

		$result = [];

		foreach($models as $model)
		{
			$result[] = [
				'storeId' => $model->localId,
				'withdrawWallet' => $model->withdraw_wallet,
				'urlResult' => $model->url_result,
			];
		}

		return $result;
	}


	/**
	 * использует NewYandexPay::getPayUrlMultiple()
	 * @return array ['url'=>'...', 'orderId'=>'YandexPay ID']
	 */
	private function getPayUrlExchange()
	{
		$result = [];

		if(!$this->store)
		{
			$this->errorCode = self::ERROR_STORE;
			return $result;
		}

		//orderId
		$orderId = $this->request['orderId'] * 1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		if($model = StoreApiTransaction::getModel(['user_id'=>$this->user->id, 'order_id'=>$orderId]))
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = 'этот orderId уже занят';
			return $result;
		}

		//сумма
		$amount = $this->request['amount']*1;

		if(!is_int($amount))
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'неверно указан amount';
			return $result;
		}

		//валюта
		if(in_array($this->request['currency'], self::getCurrencies()) === false)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'неверная валюта';
			return $result;
		}

		if(!$this->user->client->pick_accounts)
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = 'прием платежей отключен';
			return $result;
		}

		if($url = NewYandexPay::getPayUrlMultiple($this->user->id, $amount, true, Tools::microtime()))
		{
			$model = new StoreApiTransaction;
			$model->scenario = StoreApiTransaction::SCENARIO_ADD;

			$params = [
				'amount' => $amount,
				'store_id' => $this->store->id,
				'currency' => $this->request['currency'],
				'client_id' => $this->user->client_id,
				'user_id' => $this->user->id,
				'order_id' => $orderId,
			];

			$model->model_id = NewYandexPay::$someData['model']->id;
			$model->model_type = StoreApiTransaction::MODEL_TYPE_NEW_YANDEX_PAY;

			$model->attributes = $params;

			if($model->save())
				$result = [
					'url'=>$url,
					'orderId'=>$model->order_id,
				];
			else
			{
				$this->errorCode = self::ERROR_OTHER;
				$this->errorMsg = $model::$lastError;
			}
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = NewYandexPay::$lastError;
		}

		return $result;
	}

	private function checkPayment()
	{
		$result = [];

		if(!$this->store)
		{
			$this->errorCode = self::ERROR_STORE;
			return $result;
		}

		$storeId = $this->store->id;
		$orderId = intval($this->request['orderId']);

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = 'неверный orderId';
			return $result;
		}

		if(!$model = StoreApiTransaction::getModel(['store_id'=>$storeId, 'order_id'=>$orderId]))
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = 'платеж не найден';
			return $result;
		}

		return [
			'orderId'=>$model->order_id,
			'amount'=>$model->amount,
			'currency'=>$model->currency,
			'status'=>$model->status,
			'timestampAdd'=>$model->date_add,
			'timestampPay'=>$model->date_pay,
		];
	}


	/**
	 * сумма по оплаченным заявкам за период
	 * (максимум 60 дней)
	 * @return array [
	 * 		'successAmount'=>10000,
	 * ]
	 */
	private function getStats()
	{
		$result = [
			'successAmount' => 0,
		];

		$storeId = ($this->store) ? $this->store->id : 0;

		$maxInterval = 3600*24*60;

		$timestampStart = intval($this->request['timestampStart']);
		$timestampEnd = intval($this->request['timestampEnd']);

		if(!$timestampStart or !$timestampEnd or $timestampEnd - $timestampStart > $maxInterval)
		{
			$this->errorCode = self::ERROR_DATE;
			$this->errorMsg = 'неверно указан timestampStart или timestampEnd';
			return $result;
		}

		$models = StoreApiTransaction::getModels($timestampStart, $timestampEnd, $this->user->id, $storeId, true);
		$stats = StoreApiTransaction::getStats($models);

		$result['successAmount'] = $stats['successAmount'];

		return $result;
	}

	private function getWithdrawals()
	{
		$result = [];

		$storeId = ($this->store) ? $this->store->id : 0;

		$maxInterval = 3600*24*60;

		$timestampStart = intval($this->request['timestampStart']);
		$timestampEnd = intval($this->request['timestampEnd']);

		if(!$timestampStart or !$timestampEnd or $timestampEnd - $timestampStart > $maxInterval)
		{
			$this->errorCode = self::ERROR_DATE;
			$this->errorMsg = 'неверно указан timestampStart или timestampEnd';
			return $result;
		}

		$models = StoreApiWithdraw::getModels($timestampStart, $timestampEnd, $this->user->id, $storeId);

		foreach ($models as $model)
		{
			$result[] = [
				'amount' => $model->amount_currency,
				'wallet' => $model->wallet,
				'timestampPay' => $model->date_pay,
				'amountRub' => $model->amount_rub,
				'lastPrice' => $model->btc_last_price,
				'usdRate' => $model->usd_rate,
				'networkFee' => $model->network_fee,
			];
		}

		return $result;
	}

}
