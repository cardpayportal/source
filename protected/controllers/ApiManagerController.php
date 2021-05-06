<?php

/**
 * апи для менеджеров
 * @property string errorCode
 * @property string $errorMsg
 * @property string $rawRequest
 * @property array $request
 * @property User $user
 */
class ApiManagerController extends CController
{
	const ERROR_DEBUG_MODE = 'debugMode';
	const ERROR_ACCESS = 'accessDenied';
	const ERROR_REQUEST = 'invalidRequest';
	const ERROR_REPEAT_REQUEST = 'repeatRequest';
	const ERROR_METHOD = 'invalidMethod';
	const ERROR_OTHER = 'errorOther';
	const ERROR_ORDER = 'errorOrder';
	const ERROR_DATE = 'errorDate';
	const ERROR_NOT_AVAILABLE = 'notAvailable';//выключение выдачи ссылок для кл13
	const ERROR_CREATING_ORDER = 'errorCreatingOrder';
	const ERROR_TECHNIC_WORKS = 'technic_works';
	const ERROR_WRITE_IMAGE = 'errorWriteImage';
	const IMAGE_ALREADY_EXIST = 'imageAlreadyExist';
	const IMAGE_NOT_EXIST = 'imageNotExist';
	const DATA_NOT_ADDED = 'dataNotAdded';
	const SMS_NOT_FOUND = 'smsNotFound';

	const STATUS_WAIT = 'wait';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';

	const REQUEST_PAUSE = 1;

	private $errorCode = '';
	private $errorMsg = '';

	private $rawRequest = '';
	private $request = [];

	private $user;

	protected function beforeAction($action)
	{
		//sleep(self::REQUEST_PAUSE);
		session_write_close();

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

	public function checkAccess()
	{
		if(YII_DEBUG and !Tools::isAdminIp())
		{
			$this->errorCode = self::ERROR_DEBUG_MODE;
			return false;
		}

		if(
			!$this->request['key']
			or !$this->request['hash'] or !$this->request['method']
		)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указаны необходимые параметры (key, hash, method)';
			return false;
		}

		if(in_array($this->request['method'], self::getAllowedMethods()) === false)
		{
			$this->errorCode = self::ERROR_METHOD;
			return false;
		}

		$this->user = User::model()->findByAttributes(['api_key' => $this->request['key']]);

		if(!$this->user or !$this->user->active or !$this->user->api_key or !$this->user->api_secret)
		{
			$this->errorCode = self::ERROR_ACCESS;
			return false;
		}

		if($this->request['requestId'])
			$userHash = self::hash($this->request['requestId'].$this->request['key'].$this->user->api_secret);
		else
		{
			$hashParams = $this->request;
			unset($hashParams['hash']);

			//удалить массивы из хеш параметров
			foreach ($hashParams as $key=>$item)
			{
				if(is_array($item))
					unset($hashParams[$key]);
			}

			$userHash = self::hash(implode('', $hashParams).$this->user->api_secret);
		}

		if($userHash !== $this->request['hash'])
		{
			$this->errorCode = self::ERROR_ACCESS;
			$this->errorMsg = 'неверный hash';
			return false;
		}

		return true;
	}

	protected function getErrorMsg()
	{
		$arr = [
			self::ERROR_DEBUG_MODE => 'тех работы',
			self::ERROR_ACCESS => 'доступ запрещен',
			self::ERROR_REQUEST => 'неверный формат запроса',
			self::ERROR_METHOD => 'неверный параметр method',
			self::ERROR_OTHER => 'неизвестная ошибка',
			self::ERROR_ORDER => 'ошибка в orderId',
			self::ERROR_CREATING_ORDER => 'ошибка создания заявки',
		];

		if($this->errorMsg)
			return $this->errorMsg;

		if($this->errorCode)
			return $arr[$this->errorCode];
		else
			return '';
	}

	protected function resultOut($result = [])
	{
		$result = [
			'result'=>$result,
			'errorCode'=>$this->errorCode,
			'errorMsg'=>$this->getErrorMsg(),
		];

		if($this->request['requestId'])
		{
			//кеширование запроса
			$apiRequest = new ManagerApiRequest;
			$apiRequest->scenario = $apiRequest::SCENARIO_ADD;

			if($this->user)
				$apiRequest->user_id = $this->user->id;

			if($this->request)
			{
				$apiRequest->request_id = $this->request['requestId'];
				$apiRequest->method = $this->request['method'];
				$apiRequest->body = Tools::arr2Str($this->request);
			}
			else
			{
				$apiRequest->body = $this->rawRequest;
			}

			$apiRequest->response = Tools::arr2Str($result);
			$apiRequest->raw_response = json_encode($result);
			$apiRequest->error = $result['errorCode'];

			if(!$apiRequest->save())
				toLogError('ошибка сохранения ManagerApiRequest: '.$apiRequest::$lastError);
		}


		$this->renderPartial('//system/json', [
			'result'=>$result,
		]);


		Yii::app()->end();
	}

	public static function hash($string)
	{
		return md5($string);
	}

	public function actionIndex()
	{
		if($this->request['requestId'] and $cache = ManagerApiRequest::model()->findByAttributes([
			'user_id'=>$this->user->id,
			'request_id'=>$this->request['requestId'],
			//'method'=>$this->request['method'],	//убрал идент по методу
		]))
		{
			/**
			 * @var ManagerApiRequest $cache
			 */

			echo $cache->raw_response;
			Yii::app()->end();
		}

		$method = $this->request['method'];

		$result = $this->$method();

		$this->resultOut($result);
	}

	public static function getAllowedMethods()
	{
		return [
			'getYandexPayUrl',
			'checkYandexPayment',
			'getWaitYandexPayments',
			'getQiwiNewPayParams',
			'checkQiwiNewPayment',
			'getQiwiNewStats',
			'sendPaymentErrors',
			'saveImage',
			'getImagePosition',
			'addCardInfo',
			'addSmsInfo',
			'getImageList',
			'checkSmsInfo',
			'resendSms',
			//'confirmSimPayment',
			//'getSimWallet',
			'getExchangeYadBitPayUrl',
			'checkExchangeYadBitPayment',
			'getYandexPayUrlExtended',
			'getPayUrlExtended',
			'checkPayUrlExtended',
			'getCardPayUrl',
			'checkCardPayment',
			'getCardPayBankParams',
			'checkCardPayBankStatus',
			'checkYandexPaymentByOrder',
			'getBankPayUrl',
			'getPayUrlWalletS',
			'checkWalletSStatus',
			'getP2pServiceRequest',
			'p2pServiceCheckStatus',
			'p2pServiceCancel',
			'p2pServiceAccept',
		];
	}

	/**
	 * @return array ['url'=>'...', 'orderId'=>'YandexPay ID']
	 */
	private function getYandexPayUrl()
	{
		session_write_close();
		$result = [];

		$amount = preg_replace('!\s!', '', $this->request['amount'])*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		$orderId = $this->request['orderId'] ? $this->request['orderId']*1 : 0;

		if($url = NewYandexPay::getPayUrl($this->user->id, $amount, true, $orderId))
		{
			$result = [
				'url' => $url,
				'orderId' => NewYandexPay::$someData['yandexPayId'],
			];
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = NewYandexPay::$lastError;
		}

		return $result;
	}

	/**
	 * метод для получения пост данных формы оплаты без страницы накладки, только апи
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getBankPayUrl()
	{
		session_write_close();
		set_time_limit(120);
		$this->log('getBankPayUrl_request', $this->request);

		$result = [];

		$amount = preg_replace('!\s!', '', $this->request['amount'])*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		$orderId = $this->request['orderId'];

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		if(!$this->request['headers'] or !is_array($this->request['headers']))
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан headers';
			return $result;
		}

		if(!$this->request['browser'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан browser';
			return $result;
		}

		if(!$this->request['cardNumber'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан cardNumber';
			return $result;
		}

		if(!$this->request['cardM'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан cardM';
			return $result;
		}

		if(!$this->request['cardY'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан cardY';
			return $result;
		}

		if(!$this->request['cardCvv'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан cardCvv';
			return $result;
		}

		$params = $this->request;

		$payment = new PaySol('5e0b252140aa7906d02b2628', '_brt-0zH65Gc_oqBYepgAO-XsNKf-LN1', 'BrfVkPvK42XEltyIzPMyDoK7CEeCVVc_');
		$orderResult = $payment->createOrder(
			$amount,
			$orderId,
			'processing',
			'https://apiapi.pw/index.php?r=api/MegafonCollback&key=api_key128312683',
			$params['successUrl'],
			$params['failUrl'],
			$params['cardNumber'],
			$params['cardM'].'/'.$params['cardY'],
			'ANONYMOUS CARD',
			$params['cardCvv'],
			$params['receiveMegaphone']
		);

		var_dump($orderResult);die;

		$tryCount = 12;

		$data = [];


//		if(YII_DEBUG)
//			prrd($responce);

		$params['url'] = $data['redirectUrl'];
		$params['postArr'] = [
			'MD' => $data['MD'],
			'MdOrder' => $data['MD'],
			'PaReq' => $data['PaReq'],
			'TermUrl' => $data['originalTermUrl'],
		];

		$model = NewYandexPay::getModel(['order_id'=>$orderId, 'user_id'=>$this->user->id]);

		if(!$model)
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = NewYandexPay::$lastError;
			SimTransaction::log('не найден платеж order_id='.$model->order_id);
		}

		$params['orderId'] = $model->order_id;

		if(!$data)
		{
			if($model)
				$model->delete();

			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = NewYandexPay::$lastError;
			SimTransaction::log('удален платеж order_id='.$model->order_id.', причина:  не получен BankUrl');
		}

		if($params)
			$params['orderId'] = $model->order_id;

		$this->log('getBankPayUrl_Response', $params);

		return $params;
	}

	/*
	 * по подобию оплат с телефона создается ссылка на страницу накладки
	 *
	 */
	private function getPayUrlExtended()
	{
		session_write_close();
		$result = [];

		$user = $this->user;

		$amount = $this->request['amount']*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		if(!$this->request['orderId'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		$orderId = $this->request['orderId']*1;


		if($urlYandex = NewYandexPay::getPayUrl($this->user->id, $amount, true, $orderId,
			$this->request['successUrl'],
			$this->request['failUrl']
		))
		{
			$urlApi = 'https://quicktransfer.pw/test.php?r=payment/createOrderByApi';
			$sender = new Sender;
			$sender->followLocation = true;
			$sender->useCookie = false;
			$sender->additionalHeaders = [
				'accept' => 'Accept: application/json',
				'contentType' => 'Content-Type: application/json',
			];

			$key = 'ad43faTNf545evipDC3Wh4NQh4WZXU4h5h3eATxvfH';

			$params = [
				'orderId' => $orderId,//NewYandexPay::$someData['yandexPayId'],
				'amount' => $amount,
				'urlYandex' => $urlYandex,
				'successUrl' => $this->request['successUrl'],
				'failUrl' => $this->request['failUrl'],
			];

			$responce = json_decode($sender->send($urlApi, json_encode($params)), 1);

			/**
			 * @var NewYandexPay $model
			 */
			$model = NewYandexPay::model()->findByPk(NewYandexPay::$someData['yandexPayId']);

			if(!$model)
			{
				$this->errorCode = self::ERROR_OTHER;
				$this->errorMsg = NewYandexPay::$lastError;
				toLogError('getPayUrlExtended: '.$this->errorMsg);
				return $result;
			}

			$newUrl = 'https://quicktransfer.pw/index.php?r=payment/processing&h='.$responce['hash'];

			toLogRuntime('getPayUrlExtended: '.$model->id);

			$model->url = $newUrl;
			$model->url_yandex = $urlYandex;
			$model->save();

			$result = [
				'url' => $newUrl,
				'orderId' => $orderId,
				'successUrl' => $this->request['successUrl'],
				'failUrl' => $this->request['failUrl'],
			];

		}
		else
		{
			$this->errorCode = self::ERROR_CREATING_ORDER;
			$this->errorMsg = Model::$lastError;
		}

		return $result;
	}


	/**
	 * проверяем статус заявки
	 * @return array
	 */
	private function checkPayUrlExtended()
	{
		session_write_close();
		$result = [];
		$user = $this->user;
//		$amount = $this->request['amount']*1;

		if(!$this->request['orderId'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		$orderId = $this->request['orderId']*1;

		if($order = NewYandexPay::getModel(['order_id'=>$orderId, 'user_id'=>$user->id]))
		{
			/**
			 * @var NewYandexPay $order
			 */
			$progress = 0;
			if($order->remote_order_id)
			{
				$payment = new PaySol('5da49bfa90a4ca72fc454d95', '6BrD5iMPG1hSZopK6HTjaYXsKtjyIsjw', 'GnTQv82Z0tq9fZhRKEVCkkpE2YTQTa0P');
				$orderResult = $payment->getOrder($order->remote_order_id);

				$status = isset($orderResult->status) ? $orderResult->status : $order->status;

				if($status == 'succeed' or $order->status == 'success')
					$status = 'success';
				elseif($status == 'progress')
					$status = 'wait';
				elseif($status == 'failed')
					$status = 'error';

				$order->status = $status;

				$progress = ($orderResult->status == 'succeed') ? 100 : $orderResult->progress;

				$order->progress = $progress;
				$order->save();

				$urlApi = 'https://quicktransfer.pw/test.php?r=payment/remoteChangeStatus';
				$sender = new Sender;
				$sender->followLocation = true;
				$sender->useCookie = false;
				$sender->additionalHeaders = [
					'accept' => 'Accept: application/json',
					'contentType' => 'Content-Type: application/json',
				];

				$key = 'ad43faTNf545evipDC3Wh4NQh4WZXU4h5h3eATxvfH';
				$sendData = [
					'orderId' => $orderId,
					'status' => $status,
					'hash' => md5($key.$orderId)
				];

				$result = json_decode($sender->send($urlApi, json_encode($sendData)), 1);

			}

			$result = [
				'id' => $order->order_id,
				'amount' => $order->amount,
				'status' => $status,
				'progress' => $progress,
			];

		}
		else
		{
			$this->errorCode = self::ERROR_ORDER;
			$this->errorMsg = 'orderId не найден';
			toLogError('checkPayUrlExtended: '.$this->errorMsg);
			return $result;
		}

		return $result;
	}


	/**
	 * @return array ['url'=>'...', 'orderId'=>'YandexPay ID']
	 *
	 *
	 */
	private function getYandexPayUrlExtended()
	{
		session_write_close();
		$result = [];

		$user = $this->user;

		$amount = $this->request['amount']*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		$orderId = $this->request['orderId'] ? $this->request['orderId']*1 : 0;

		if($url = NewYandexPay::getPayUrl($this->user->id, $amount, false, $orderId,
			$this->request['successUrl'],
			$this->request['failUrl']
		))
		{
			if($user->client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_SIM_ACCOUNT)
			{
				$model = SimTransaction::getModel(['client_order_id'=>NewYandexPay::$someData['yandexPayId'],
					'user_id'=>$user->id]);

				$result = [
					'url' => $model->pay_url,
					'orderId' => $model->client_order_id,
					'successUrl' => $model->success_url,
					'failUrl' => $model->fail_url,
				];
			}
			else
			{
				$urlApi = 'https://as-otpay.com/pay/v2/getUrl';
				$cfg = cfg('storeApi');
				$sender = new Sender;
				$sender->followLocation = true;
				$sender->proxyType = $cfg['notificationProxyType'];
				$sender->useCookie = false;

				$key = '2yuie8afz4T49SDG';
				$secret = '4u8TIkUu4sOLqOoG4lSheyTBvXlYZfo5';
				
				$params = [
					'pay_url' => $url,
					'key' => $key,
					'order_id' => NewYandexPay::$someData['yandexPayId'],
					'amount' => $amount,
					'success_url' => $this->request['successUrl'],
					'fail_url' => $this->request['failUrl'],
				];

//				if(YII_DEBUG)
//				{
//					print_r('start');
//					var_dump($params);die;
//				}

				$postData = 'pay_url='.urlencode($url).'&key='.$key.'&order_id='.NewYandexPay::$someData['yandexPayId'].
					'&amount='.$amount.'&success_url='.urlencode($this->request['successUrl']).'&fail_url='.urlencode($this->request['failUrl']);

				$postData .= '&hash='.md5($url.';'.$key.';'.NewYandexPay::$someData['yandexPayId'].';'.$amount.
						';'.$this->request['successUrl'].';'.$this->request['failUrl'].';'.$secret);
				$contentJson = $sender->send($urlApi, $postData);//, $cfg['notificationProxy']);

				$content = json_decode($contentJson,1);

				if($content['status'] !== "success" or !$content['status'])
				{
					toLogError('getYandexPayUrlExtended: '.arr2str($content));
					$this->errorCode = self::ERROR_OTHER;
					$this->errorMsg = NewYandexPay::$lastError;
					return $result;
				}

//			{"status":"success","data":{"transaction_id":3,"url":"https:\/\/as-billing.info\/pay\/v2\/df95181afe880e5d255f918bff5faa09"}}

				/**
				 * @var NewYandexPay $model
				 */
				$model = NewYandexPay::model()->findByPk(NewYandexPay::$someData['yandexPayId']);

				if(!$model)
				{
					$this->errorCode = self::ERROR_OTHER;
					$this->errorMsg = NewYandexPay::$lastError;
					toLogError('getYandexPayUrlExtended: '.$this->errorMsg);
					return $result;
				}

				toLogRuntime('getYandexPayUrlExtended: '.arr2str($content));

				$model->url = $content['data']['url'];
				$model->save();

				$result = [
					'url' => $model->url,
					'orderId' => NewYandexPay::$someData['yandexPayId'],
					'successUrl' => $this->request['successUrl'],
					'failUrl' => $this->request['failUrl'],
				];
			}
		}
		else
		{
			$this->errorCode = self::ERROR_CREATING_ORDER;
			$this->errorMsg = Model::$lastError;
		}

		return $result;
	}




//	private function getYandexPayUrl()
//	{
//		$result = [];
//
//		$amount = $this->request['amount']*1;
//
//		if(!$amount)
//		{
//			$this->errorCode = self::ERROR_REQUEST;
//			$this->errorMsg = 'не указан amount';
//			return $result;
//		}
//
//		if($url = YandexPay::getPayUrl($this->user->id, $amount, true))
//		{
//			$result = [
//				'url' => $url,
//				'orderId' => YandexPay::$someData['yandexPayId'],
//			];
//		}
//		else
//		{
//			$this->errorCode = self::ERROR_OTHER;
//			$this->errorMsg = YandexPay::$lastError;
//		}
//
//		return $result;
//	}


	private function checkYandexPaymentByOrder()
	{
		$result = [];

		$orderId = $this->request['orderId']*1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		$user = $this->user;

		$order = NewYandexPay::model()->findByAttributes(['order_id'=>$orderId, 'user_id'=>$user->id]);

		/**
		 * @var NewYandexPay $order
		 */

		if(!$order)
		{
			$this->errorCode = self::ERROR_ORDER;
			$this->errorMsg = 'orderId не найден';
			return $result;
		}

		$result = [
			'id' => $order->order_id,
			'amount' => $order->amount,
			'status' => $order->status,
			'timestampAdd' => $order->date_add,
			'timestampPay' => $order->date_pay,
		];

		return $result;
	}

	/**
	 * @return array [
	 * 		'id'=>213123,
	 * 		'amount'=>123.12,
	 * 		'status'=>'wait|success|error',
	 * 		'timestampAdd'=>2163812638123,
	 * 		'timestampPay'=>2163812638123
	 * ]
	 */
	private function checkYandexPayment()
	{
		$result = [];

		$orderId = $this->request['orderId']*1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		$user = $this->user;

		//если сим то получаем данные из другой модели
		if($user->client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_SIM_ACCOUNT)
		{
			$order = SimTransaction::getModel(['client_order_id'=>$orderId, 'user_id'=>$user->id]);

			/**
			 * @var SimTransaction $order
			 */

			if(!$order)
			{
				$this->errorCode = self::ERROR_ORDER;
				$this->errorMsg = 'orderId не найден';
				return $result;
			}

			$result = [
				'id' => $order->client_order_id,
				'amount' => $order->amount,
				'status' => $order->status,
				'timestampAdd' => $order->date_add,
				'timestampPay' => $order->date_pay,
			];
		}
		else
		{
			$order = NewYandexPay::model()->findByAttributes(['id'=>$orderId, 'user_id'=>$user->id]);

			/**
			 * @var NewYandexPay $order
			 */

			if(!$order)
			{
				$this->errorCode = self::ERROR_ORDER;
				$this->errorMsg = 'orderId не найден';
				return $result;
			}

			$result = [
				'id' => $order->id,
				'amount' => $order->amount,
				'status' => $order->status,
				'timestampAdd' => $order->date_add,
				'timestampPay' => $order->date_pay,
			];
		}

		return $result;
	}

	/**
	 * сумма по оплаченным заявкам за период
	 * (максимум 60 дней)
	 * @return array [
	 * 		'successAmount'=>10000,
	 * ]
	 */
	private function getYandexStats()
	{
		$result = [
			'successAmount' => 0,
		];

		$timestampMin = time() - 3600*24*60;

		$timestampStart = $this->request['timestampStart'] * 1;
		$timestampEnd = $this->request['timestampEnd'] * 1;

		if(!$timestampStart or !$timestampEnd or $timestampEnd <= $timestampStart)
		{
			$this->errorCode = self::ERROR_DATE;
			$this->errorMsg = 'неверно указан timestampStart или timestampEnd';
			return $result;
		}

		if($timestampStart < $timestampMin)
		{
			$this->errorCode = self::ERROR_DATE;
			$this->errorMsg = 'статистика доступна только за последние 60 дней';
			return $result;
		}


		$models = YandexPay::getModels($timestampStart, $timestampEnd, $this->user->id, 0, true);
		$stats = YandexPay::getStats($models);
		$result['successAmount'] = $stats['amount'];

		return $result;
	}

//	/**
//	 * @return array ['url'=>'...', 'orderId'=>'YandexPay ID']
//	 */
//	private function getQiwiNewPayParams()
//	{
//		if(!YII_DEBUG)
//		{
//			$result = [];
//			$this->errorCode = self::ERROR_TECHNIC_WORKS;
//			$this->errorMsg = 'временные технические работы';
//			return $result;
//		}
//
//		$result = [];
//
//		$amount = $this->request['amount']*1;
//
//		if(!$amount)
//		{
//			$this->errorCode = self::ERROR_REQUEST;
//			$this->errorMsg = 'не указан amount';
//			return $result;
//		}
//
//
//		/*тут не будет работать блок изза кеша апи запросов
//		 * if($order = QiwiPay::model()->findByAttributes([
//				'request_api_id'=>$this->request['requestId'],
//				'user_id'=>$this->user->id
//			])
//		)
//		{
//			if($order->status == self::STATUS_WAIT)
//			{
//				$result['orderId'] = $order->id;
//
//				return [
//					'wallet' => $order->wallet,
//					'amount' => $order->amount,
//					'comment' => $order->comment,
//				];
//			}
//			elseif($order->status == QiwiPay::STATUS_RESERVED)
//			{
//				$result = [];
//				$this->errorCode = self::ERROR_REPEAT_REQUEST;
//				$this->errorMsg = 'repeat request, повторите запрос';
//				return $result;
//			}
//		}
//		else*/if($result = QiwiPay::getPayUrlRequest($this->user->id, $amount, $this->request['requestId']))
//		{
//			//$result['orderId'] = QiwiPay::$someData['qiwiPayId'];
//
//			$result = [];
//			$this->errorCode = self::ERROR_REPEAT_REQUEST;
//			$this->errorMsg = 'repeat request, повторите запрос';
//			return $result;
//		}
//		else
//		{
//			$this->errorCode = self::ERROR_OTHER;
//			$this->errorMsg = QiwiPay::$lastError;
//		}
//
//		return $result;
//	}
//
//	/**
//	 * @return array [
//	 * 		'id'=>213123,
//	 * 		'amount'=>123.12,
//	 * 		'status'=>'wait|success|error|ordered',
//	 * 		'timestampAdd'=>2163812638123,
//	 * 		'timestampPay'=>2163812638123
//	 * ]
//	 */
//	private function checkQiwiNewPayment()
//	{
//		if(!YII_DEBUG)
//		{
//			$result = [];
//			$this->errorCode = self::ERROR_TECHNIC_WORKS;
//			$this->errorMsg = 'временные технические работы';
//			return $result;
//		}
//
//		$result = [];
//
//		$orderId = $this->request['orderId']*1;
//
//		if(!$orderId)
//		{
//			$this->errorCode = self::ERROR_REQUEST;
//			$this->errorMsg = 'не указан orderId';
//			return $result;
//		}
//
//		$order = QiwiPay::model()->findByAttributes(['id'=>$orderId]);
//
//		/**
//		 * @var QiwiPay $order
//		 */
//
//		if(!$order or $order->user_id !== $this->user->id)
//		{
//			$this->errorCode = self::ERROR_ORDER;
//			$this->errorMsg = 'orderId не найден';
//			return $result;
//		}
//		else
//		{
//			if($order->status == self::STATUS_WAIT)
//			{
//				$order->status = (QiwiPay::getTransactionStatus($orderId)) ? self::STATUS_SUCCESS : self::STATUS_WAIT;
//			}
//			elseif($order->status == QiwiPay::STATUS_RESERVED)
//			{
//				$this->errorCode = self::ERROR_REPEAT_REQUEST;
//				$this->errorMsg = 'repeat request, повторите запрос';
//				return $result;
//			}
//		}
//
//		$result = [
//			'id' => $order->id,
//			'amount' => $order->amount,
//			'status' => $order->status,
//			'wallet' => $order->wallet,
//			'comment' => $order->comment,
//			'timestampAdd' => $order->date_add,
//			'timestampPay' => $order->date_pay,
//		];
//
//		return $result;
//	}
//
//	/**
//	 * сумма по оплаченным заявкам за период
//	 * (максимум 60 дней)
//	 * @return array [
//	 * 		'successAmount'=>10000,
//	 * ]
//	 */
//	private function getQiwiNewStats()
//	{
//		$result = [
//			'successAmount' => 0,
//		];
//
//		$timestampMin = time() - 3600*24*60;
//
//		$timestampStart = $this->request['timestampStart'] * 1;
//		$timestampEnd = $this->request['timestampEnd'] * 1;
//
//		if(!$timestampStart or !$timestampEnd or $timestampEnd <= $timestampStart)
//		{
//			$this->errorCode = self::ERROR_DATE;
//			$this->errorMsg = 'неверно указан timestampStart или timestampEnd';
//			return $result;
//		}
//
//		if($timestampStart < $timestampMin)
//		{
//			$this->errorCode = self::ERROR_DATE;
//			$this->errorMsg = 'статистика доступна только за последние 60 дней';
//			return $result;
//		}
//
//
//		$models = QiwiPay::getModels($timestampStart, $timestampEnd, $this->user->id, 0, true);
//		$stats = QiwiPay::getStats($models);
//		$result['successAmount'] = $stats['amount'];
//
//		return $result;
//	}


	/**
	 * @return array
	 */
	private function getQiwiNewPayParams()
	{

		$result = [];

		//для тестов, только 1 манагера пропускаем запросы
		if(in_array($this->user->id, ['806'])===false)
		{
			$result = [];
			$this->errorCode = self::ERROR_TECHNIC_WORKS;
			$this->errorMsg = 'временные технические работы';
			return $result;
		}

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
			//$result['orderId'] = QiwiPay::$someData['qiwiPayId'];


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
			$this->errorMsg = QiwiPay::$lastError;
		}

		return $result;
	}

	/**
	 * @return array [
	 * 		'id'=>213123,
	 * 		'amount'=>123.12,
	 * 		'status'=>'wait|success|error|ordered',
	 * 		'timestampAdd'=>2163812638123,
	 * 		'timestampPay'=>2163812638123
	 * ]
	 */
	private function checkQiwiNewPayment()
	{

		//для тестов, только 1 манагера пропускаем запросы
		if(in_array($this->user->id, ['806'])===false)
		{
			$result = [];
			$this->errorCode = self::ERROR_TECHNIC_WORKS;
			$this->errorMsg = 'временные технические работы';
			return $result;
		}

		$result = [];

		$orderId = $this->request['orderId']*1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		$order = NextQiwiPay::model()->findByAttributes(['id'=>$orderId]);

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
			'amount' => $order->amount,
			'status' => $order->status,
			'wallet' => $order->wallet,
			'comment' => $order->comment,
			'timestampAdd' => $order->date_add,
			'timestampPay' => $order->date_pay,
		];

		return $result;
	}

	/**
	 * сумма по оплаченным заявкам за период
	 * (максимум 60 дней)
	 * @return array [
	 * 		'successAmount'=>10000,
	 * ]
	 */
	private function getQiwiNewStats()
	{
		$result = [
			'successAmount' => 0,
		];

		$timestampMin = time() - 3600*24*60;

		$timestampStart = $this->request['timestampStart'] * 1;
		$timestampEnd = $this->request['timestampEnd'] * 1;

		if(!$timestampStart or !$timestampEnd or $timestampEnd <= $timestampStart)
		{
			$this->errorCode = self::ERROR_DATE;
			$this->errorMsg = 'неверно указан timestampStart или timestampEnd';
			return $result;
		}

		if($timestampStart < $timestampMin)
		{
			$this->errorCode = self::ERROR_DATE;
			$this->errorMsg = 'статистика доступна только за последние 60 дней';
			return $result;
		}


		$models = NextQiwiPay::getModels($timestampStart, $timestampEnd, $this->user->id, 0, true);
		$stats = NextQiwiPay::getStats($models);
		$result['successAmount'] = $stats['amount'];

		return $result;
	}

	/**
	 * выдача заявки в статусе ожидания для зенопостера
	 */
	private function getWaitYandexPayments()
	{
		session_write_close();

		$result = [
			'TransExist' => 'No',
		];

		$models = NewYandexPay::getWaitPayments($this->user->id, 0);

		foreach($models as $model)
		{
			if($model->url and $model->url_yandex)
			{
				//делаем проверку чтобы выдавать заявки с заполенными данными карты
				if($model->card_no and $model->card_month and $model->card_year and $model->cvv)
				{
					$result['TransExist'] = 'Yes';
					$result['transactions'] = [
						"transaction_id" => $model->id,
						"status" => $model->status,
						"card_no" => $model->card_no,
						"card_month" => $model->card_month,
						"card_year" => $model->card_year,
						"cvv" => $model->cvv,
						"URL" => $model->url_yandex,
						"sms_code" => $model->sms_code,
					];
					$model->status = 'working';
					$model->save();
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * выдача заявки в статусе ожидания для зенопостера
	 */
	private function getImageList()
	{
		session_write_close();

		$result = [];

		$models = ImagePosition::getModels();

		foreach($models as $key=>$model)
		{
			$result[$key]['bankName'] = $model->bank_name;
			$result[$key]['status'] = $model->status;
			$result[$key]['type'] = $model->type;
		}

		return $result;
	}

	/**
	 * выдаем информацию по координатам кнопок
	 */
	private function getImagePosition()
	{
		session_write_close();

		$result = [];


		$bankName = $this->request['bankName'];

		$model = ImagePosition::model()->findByAttributes(['bank_name'=>$bankName]);

		if($model)
		{
			$result = [
				'picId' =>$model->id,
				'status' =>$model->status,
				'bankName' => $model->bank_name,
				'smsInputPos' => $model->sms_input_pos,
				'buttonPos' => $model->button_pos,
				'type' => $model->type,
			];
			return $result;
		}
		else
		{
			$this->errorCode = self::IMAGE_NOT_EXIST;
			$this->errorMsg = 'изображение не найдено';
			return $result;
		}
	}

	/**
	 *входные параметры
	 * {
	 *  "requestId":12312223424,
	 * 	"key":"RFUAHHDONOXKRSGP",
	 * 	"hash":"dcd11132f2a141cd32d5ab293a4c2bae",
	 * 	"method":"saveImage",
	 * 	"bankName":"test2",
	 * 	"picture":"69291"
	 * }
	 *
	 * сохраняем у себя скрин банка в формате base64
	 *
	 * возвращает
	 * успех
	 * Array([result] => Array([picId] => 12[status] => wait[bankName] => sber)[errorCode] => [errorMsg] => )
	 * ошибка (если изображение уже отправлено)
	 * Array([result] => Array()[errorCode] => imageAlreadyExist[errorMsg] => изображение уже добавлено)
	 *
	 */
	private function saveImage()
	{
		session_write_close();

		$result = [];

		$bankName = $this->request['bankName'];

		$model = ImagePosition::model()->findByAttributes(['bank_name'=>$bankName]);

		if(!$model)
		{
			$params = [
				'status'=>'wait',
				'bank_name' => $this->request['bankName'],
			];

			ImagePosition::add($params);

			$picId = ImagePosition::$someData['picId'];

			//в формате base64
			$image = $this->request['picture'];

			if(!$image)
			{
				$this->errorCode = self::IMAGE_NOT_EXIST;
				$this->errorMsg = 'нет изображения';
				return $result;
			}

			if(file_put_contents(
				DIR_ROOT.'img/'.$this->request['bankName'],
				$image
			))
			{
				$result['picId'] =$picId;
				$result['status']= 'wait';
				$result['bankName'] = $this->request['bankName'];
				return $result;
			}
			else
			{
				toLog('error write image'.$this->request['bankName']);
				$this->errorCode = self::ERROR_WRITE_IMAGE;
				$this->errorMsg = 'ошибка записи изображения';
				return $result;
			}

		}
		else
		{
			$this->errorCode = self::IMAGE_ALREADY_EXIST;
			$this->errorMsg = 'изображение уже добавлено';
			return $result;
		}
	}

	/**
	 * получаем ошибки платежей
	 */
	private function sendPaymentErrors()
	{
		$result = [
			'receivedError' => 'No',
		];

		$transactionId = $this->request['transactionId'] * 1;

		$model = NewYandexPay::model()->findByPk($transactionId);

		if($model)
		{
			$failMessage = $this->request['failMessage'];

			//выдаем общую ошибку при проблеме с яндексом
			if(preg_match('!yandex!iu', $failMessage, $matches))
				$failMessage = 'Transaction ERROR';

			$failMessage = str_replace($model->card_no, '...'.substr($model->card_no, 12, 15), $failMessage);
			$failMessage = str_replace($model->cvv, '...', $failMessage);
			//показываем клиенту ошибку
			$model->error = $failMessage;
			$model->status = NewYandexPay::STATUS_ERROR;
			$model->save();

			$result['receivedError'] = 'Yes';
		}

		return $result;

	}

	/**
	 * добавляем информацию по платежной карте к заявке яндекса
	 */
	private function addCardInfo()
	{
		session_write_close();

		$result = [];

		$orderId = $this->request['orderId'];

		$model = NewYandexPay::model()->findByAttributes(['order_id'=>$orderId]);


		if($model)
		{
			$model->card_no = $this->request['cardNo'];
			$model->card_year = $this->request['cardYear'];
			$model->card_month = $this->request['cardMonth'];
			$model->cvv = $this->request['cvv'];
			$model->card_name = $this->request['cardName'];

			if($model->save())
			{
				$result['info'] = 'Данные карты добавлены в заявку';
				return $result;
			}
			else
			{
				$this->errorCode = self::DATA_NOT_ADDED;
				$this->errorMsg = 'Ошибка добавления данных карты добавлены в заявку';
				return $result;
			}

		}

		return $result;

	}

	/**
	 * добавляем смс код к заявке яндекса
	 */
	private function addSmsInfo()
	{
		session_write_close();

		$result = [];

		$orderId = $this->request['orderId'];

		$model = NewYandexPay::model()->findByAttributes(['order_id'=>$orderId]);

		if($model)
		{
			$model->sms_code = $this->request['smsCode'];

			if($model->save())
			{
				$result['info'] = 'Смс код добавлен в заявку';
				return $result;
			}
			else
			{
				$this->errorCode = self::DATA_NOT_ADDED;
				$this->errorMsg = 'Ошибка добавления смс';
				return $result;
			}
		}

		return $result;

	}

	/**
	 * проверяем смс код в заявке
	 */
	private function checkSmsInfo()
	{
		session_write_close();

		$result = [];

		$orderId = $this->request['orderId'];

		$model = NewYandexPay::model()->findByAttributes(['id'=>$orderId]);

		if($model)
		{
			$result['smsCode'] = $model->sms_code;
			$result['smsCounter'] = ($model->status == NewYandexPay::STATUS_WAIT_SMS)?'2':'1';

			return $result;
		}
		else
		{
			$this->errorCode = self::SMS_NOT_FOUND;
			$this->errorMsg = 'Sms not found';
			return $result;
		}

	}


	/**
	 * запрос на повторный прием смс
	 */
	private function resendSms()
	{
		session_write_close();

		$result = [];

		$orderId = $this->request['orderId'];

		/**
		 * @var NewYandexPay $model
		 */

		$model = NewYandexPay::model()->findByAttributes(['id'=>$orderId]);

		if($model)
		{
			$model->sms_code = '';
			$model->status = NewYandexPay::STATUS_WAIT_SMS;
			$model->save();

			$result['info'] = 'запрошен повторный ввод смс, заявка №'.$orderId;
			return $result;
		}
		else
		{
			$this->errorCode = self::ERROR_ORDER;
			$this->errorMsg = 'Заявка '.$orderId.' не найдена';
			return $result;
		}

	}

	/*
	private function getSimWallet()
	{
		$result = [];

		$amount = $this->request['amount'] * 1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		$orderId = $this->request['orderId'];

		$response = SimAccount::getWallet($amount, $this->user->id, $orderId);

		if($response)
		{
			$result = [
				'wallet' => (string)$response['wallet'],
				'amount' => (string)$response['amount'],
				'transactionId' => (string)$response['transactionId'],
			];

			return $result;
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = SimAccount::$msg;
		}

		return $result;
	}
	*/

	/*
	private function confirmSimPayment()
	{
		$result = 'error';

		$amount = $this->request['amount'] * 1;
		$transactionId = $this->request['transactionId'] * 1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		if(!$transactionId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан transactionId';
			return $result;
		}

		$response = SimAccount::confirmPayment($transactionId, $this->user->id, $amount);

		if($response)
		{
			$result = 'OK';
			return $result;
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = SimAccount::$msg;
		}

		return $result;
	}
	*/


	/**
	 * @return array ['url'=>'...', 'orderId'=>'YandexPay ID']
	 */
	private function getExchangeYadBitPayUrl()
	{
		$result = [];

		$amount = $this->request['amount']*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		if($url = ExchangeYadBit::getPayUrlExchangeTest($this->user->id, $amount, true, 0))
		{
			$result = [
				'url' => $url,
				'orderId' => ExchangeYadBit::$someData['yandexPayId'],
			];
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = ExchangeYadBit::$lastError;
		}

		return $result;
	}


	/**
	 * @return array [
	 * 		'id'=>213123,
	 * 		'amount'=>123.12,
	 * 		'status'=>'wait|success|error',
	 * 		'timestampAdd'=>2163812638123,
	 * 		'timestampPay'=>2163812638123
	 * ]
	 */
	private function checkExchangeYadBitPayment()
	{

		$result = [];

		$orderId = $this->request['orderId']*1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		$order = ExchangeYadBit::model()->findByAttributes(['id'=>$orderId]);

		/**
		 * @var ExchangeYadBit $order
		 */

		if(!$order or $order->user_id !== $this->user->id)
		{
			$this->errorCode = self::ERROR_ORDER;
			$this->errorMsg = 'orderId не найден';
			return $result;
		}

		$result = [
			'id' => $order->id,
			'amount' => $order->amount,
			'status' => $order->status,
			'timestampAdd' => $order->date_add,
			'timestampPay' => $order->date_pay,
		];

		return $result;

	}

	/**
	 * работает чере SimTransaction
	 * @return array ['url'=>'...', 'orderId'=>'SimTransaction Order id']
	 */
	private function getCardPayUrl()
	{
		$this->log('getCardPayUrl_request', $this->request);

		$result = [];

		//test
		$paymentType = SimTransaction::PAYMENT_TYPE_YANDEX;

		$amount = preg_replace('!\s!', '', $this->request['amount'])*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		if($amount < SimTransaction::AMOUNT_MIN or $amount > SimTransaction::AMOUNT_MAX)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'amount должен быть от '.SimTransaction::AMOUNT_MIN.' до '.SimTransaction::AMOUNT_MAX;
			return $result;
		}

		$orderId = $this->request['orderId'];

		if(!$orderId)
			$orderId = Tools::generateCode('123456789', 9);

		if($url = SimTransaction::getPayUrl($this->user->id, $amount, $orderId,
			$this->request['successUrl'],
			$this->request['failUrl'], $paymentType))
		{
			$model = SimTransaction::getModel(['client_order_id'=>$orderId, 'user_id'=>$this->user->id]);

			$result = [
				'url' => $model->pay_url,
				'orderId' => $model->client_order_id,
				'successUrl' => $model->success_url,
				'failUrl' => $model->fail_url,
			];
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = NewYandexPay::$lastError;
		}

		$this->log('getCardPayUrl_Response', $result);

		return $result;
	}


	/**
	 * работает чере SimTransaction
	 * @return array ['url'=>'...', 'orderId'=>'SimTransaction Order id']
	 */
	private function checkCardPayment()
	{
		$this->log('getCardPayUrl_request', $this->request);

		$result = [];

		$orderId = $this->request['orderId']*1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		$user = $this->user;

		$order = SimTransaction::getModel(['client_order_id'=>$orderId, 'user_id'=>$user->id]);

		/**
		 * @var SimTransaction $order
		 */

		if(!$order)
		{
			$this->errorCode = self::ERROR_ORDER;
			$this->errorMsg = 'orderId не найден';
			return $result;
		}

		$result = [
			'id' => $order->client_order_id,
			'amount' => $order->amount,
			'status' => $order->status,
			'timestampAdd' => $order->date_add,
			'timestampPay' => $order->date_pay,
		];

		$this->log('getCardPayUrl_Response', $result);

		return $result;
	}

	//получить данные для пост-редиректа в банк
	private function getCardPayBankParams()
	{
		$this->log('getCardPayUrl_request', $this->request);

		$result = [];

		if(!$this->user->client->checkRule('sim'))
		{
			$this->errorCode = self::ERROR_NOT_AVAILABLE;
			$this->errorMsg = 'not available';
			return $result;
		}

		//test
//		$paymentType = SimTransaction::PAYMENT_TYPE_MTS;
//		$paymentType = SimTransaction::PAYMENT_TYPE_YANDEX;
//		$paymentType = SimTransaction::PAYMENT_TYPE_TELE2;
		$paymentType = SimTransaction::PAYMENT_TYPE_A3;

		//$paymentType = SimTransaction::PAYMENT_TYPE_YANDEX;

		$amount = preg_replace('!\s!', '', $this->request['amount'])*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		if($amount < SimTransaction::AMOUNT_MIN or $amount > SimTransaction::AMOUNT_MAX)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'amount должен быть от '.SimTransaction::AMOUNT_MIN.' до '.SimTransaction::AMOUNT_MAX;
			return $result;
		}

		$orderId = $this->request['orderId'];

		if(!$orderId)
			$orderId = Tools::generateCode('123456789', 9);

		if(!$this->request['headers'] or !is_array($this->request['headers']))
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан headers';
			return $result;
		}

		if(!$this->request['browser'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан browser';
			return $result;
		}

		if(!$this->request['cardNumber'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан cardNumber';
			return $result;
		}

		if(!$this->request['cardM'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан cardM';
			return $result;
		}

		if(!$this->request['cardY'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан cardY';
			return $result;
		}

		if(!$this->request['cardCvv'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан cardCvv';
			return $result;
		}

		if($url = SimTransaction::getPayUrl($this->user->id, $amount, $orderId,
			$this->request['successUrl'],
			$this->request['failUrl'], $paymentType))
		{
			$model = SimTransaction::getModel(['client_order_id'=>$orderId, 'user_id'=>$this->user->id]);

			$params = $this->request;
			$params['orderId'] = $model->order_id;

			if(!$result = SimTransaction::getBankUrl($params))
			{
				$model->delete();

				$this->errorCode = self::ERROR_OTHER;
				$this->errorMsg = SimTransaction::$lastError;
				SimTransaction::log('удален платеж order_id='.$model->order_id.', причина:  не получен BankUrl');
			}

			if($result)
				$result['orderId'] = $model->client_order_id;
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = NewYandexPay::$lastError;
		}

		$this->log('getCardPayUrl_Response', $result);

		return $result;
	}

	/**
	 * работает чере SimTransaction
	 * @return array ['url'=>'...', 'orderId'=>'SimTransaction Order id']
	 */
	private function checkCardPayBankStatus()
	{
		$this->log('getCardPayUrl_request', $this->request);

		$result = [];

		$orderId = $this->request['orderId'] * 1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		if(!$this->request['MD'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан MD';
			return $result;
		}

		if(!$this->request['PaRes'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан PaRes';
			return $result;
		}

		$user = $this->user;
		$model = SimTransaction::getModel(['client_order_id'=>$orderId, 'user_id'=>$user->id]);

		/**
		 * @var SimTransaction $order
		 */

		if(!$model)
		{
			$this->errorCode = self::ERROR_ORDER;
			$this->errorMsg = 'orderId не найден';
			return $result;
		}

		$params = $this->request;
		$params['orderId'] = $model->order_id;

		//test
		//$this->errorCode = self::ERROR_OTHER;
		//$this->errorMsg = 'debug';
		//return $result;

		$checkRes = $model->checkOrder($params);

		$result = [
			'orderId' => "{$model->client_order_id}",
			'status' => $checkRes['status'],
			'msg' => $model->error,//$checkRes['msg'],
		];

		$this->log('getCardPayUrl_Response', $result);

		return $result;
	}

	public function log($label, $params)
	{
		Tools::log($label.'('.$this->request['key'].') : '.arr2str($params), null, null, 'apiManager');
	}

	/**
	 * раз в день получаем актуальный курс евро к рублю, потому что у них пашет только евро пополнение
	 */
	public static function getRate()
	{

	}

	/**
	 * метод для получения пост данных формы оплаты без страницы накладки, только апи
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getPayUrlWalletS()
	{
		session_write_close();
		set_time_limit(120);

		$user = $this->user;
		$result = [];

		if(!$this->user->client->checkRule('walletS'))
		{
			$this->errorCode = self::ERROR_NOT_AVAILABLE;
			$this->errorMsg = 'not available';
			return $result;
		}

		$this->log('getPayUrlWalletS_request', $this->request);

		$result = [];

		$amount = preg_replace('!\s!', '', $this->request['amount'])*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		$orderId = $this->request['orderId'];

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		if(!$comment = $this->request['comment'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан comment';
			return $result;
		}

		if(!$name = $this->request['payeerName'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан payeerName';
			return $result;
		}

		if(!$surname=$this->request['payeerSurname'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан payeerSurname';
			return $result;
		}

		if(!$successUrl = $this->request['successUrl'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан successUrl';
			return $result;
		}

		if(!$failUrl = $this->request['failUrl'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан failUrl';
			return $result;
		}

		if(!$email = $this->request['email'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан email';
			return $result;
		}

		$params = $this->request;

		/**
		 * @var WalletSEmail $model
		 */
//		$model = WalletSEmail::getFreeEmail();

//		if(!$model->email)
//		{
//			$this->errorCode = self::ERROR_CREATING_ORDER;
//			$this->errorMsg = 'отсутствует email';
//			return $result;
//		}

		$transactionParams = [
			'amount' => $amount,
			'clientOrderId' => $orderId,
			'comment' => $comment,
			'email' => $email,
			'name' => $name,
			'surname' => $surname,
			'successUrl' => $successUrl,
			'failUrl' => $failUrl,
			'clientId' => $user->client_id,
			'userId' => $user->id,
		];

		$returnParams['payUrl'] = WalletSTransaction::getPayUrl($transactionParams);

		if(!$returnParams['payUrl'])
		{
			$this->errorCode = self::ERROR_CREATING_ORDER;
			$this->errorMsg = 'url не получен';
			return $result;
		}

		//помечаем email использованным
//		WalletSEmail::markUsed($model->id);

		$walletModel = WalletSTransaction::getModel(['order_id'=>WalletSTransaction::$someData['orderId']]);

		if(!$walletModel)
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = WalletSTransaction::$lastError;
			WalletSTransaction::log('не найден платеж pay_url='.$walletModel->pay_url);
		}

//		$returnParams['payUrl'] = $returnParams['payUrl'];
		$returnParams['orderId'] = $walletModel->order_id;
		$this->errorCode = '';
		$this->errorMsg = '';

		return $returnParams;
	}

	/**
	 * проверка статуса заявки WalletS
	 * @return array
	 $result = [
		'orderId' => "{$model->client_order_id}",
		'status' => $model->status,
		'msg' => $model->error,
	 ];
	 */
	private function checkWalletSStatus()
	{
		session_write_close();
		set_time_limit(120);

		$result = [];

		if(!$this->user->client->checkRule('walletS'))
		{
			$this->errorCode = self::ERROR_NOT_AVAILABLE;
			$this->errorMsg = 'not available';
			return $result;
		}

		$this->log('checkWalletSStatus_request', $this->request);

		$result = [];

		$orderId = $this->request['orderId'] * 1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		$model = WalletSTransaction::getModel(['order_id'=>$orderId]);

		/**
		 * @var WalletSTransaction $model
		 */
		if(!$model)
		{
			$this->errorCode = self::ERROR_ORDER;
			$this->errorMsg = 'orderId не найден';
			return $result;
		}

		$params = $this->request;
		$params['orderId'] = $model->order_id;

		$result = [
			'orderId' => "{$model->order_id}",
			'status' => $model->status,
			'msg' => $model->error,
		];

		$this->log('checkWalletSStatus_Response', $result);

		return $result;
	}

	/**
	 * создание заявки p2pService
	 * @return array []
	 */
	private function getP2pServiceRequest()
	{
		$this->log('getP2pServiceRequest: ', $this->request);

		$result = [];

		if(!$this->user->client->checkRule('p2pService'))
		{
			$this->errorCode = self::ERROR_NOT_AVAILABLE;
			$this->errorMsg = 'not available';
			return $result;
		}

		$amount = preg_replace('!\s!', '', $this->request['amount'])*1;

		if(!$amount)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан amount';
			return $result;
		}

		if($amount < RisexTransaction::AMOUNT_MIN or $amount > RisexTransaction::AMOUNT_MAX)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'amount должен быть от '.RisexTransaction::AMOUNT_MIN.' до '.RisexTransaction::AMOUNT_MAX;
			return $result;
		}

		$orderId = $this->request['orderId'];

		if(!$orderId)
			$orderId = Tools::generateCode('123456789', 9);

		if(RisexTransaction::getModel(['order_id'=>$orderId, 'user_id'=>$this->user->id]))
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'заявка с таким orderId уже существует';
			return $result;
		}

		if(RisexTransaction::createDeal($this->user, $amount, $orderId))
		{
			$model = RisexTransaction::getModel(['order_id'=>$orderId, 'user_id'=>$this->user->id]);

			$result = [
				'orderId' => $orderId,
				'status' => $model->statusStr,
			];
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			toLogError('RisexTransaction: '.RisexTransaction::$lastError);
			$this->errorMsg = 'Ошибка создания заявки';
		}

		$this->log('getP2pServiceRequest_Response', $result);

		return $result;
	}

	/**
	 * проверка статуса заявки p2pService
	 * @return array []
	 */
	private function p2pServiceCheckStatus()
	{
		$this->log('p2pServiceCheckStatus: ', $this->request);

		$result = [];

		if(!$this->user->client->checkRule('p2pService'))
		{
			$this->errorCode = self::ERROR_NOT_AVAILABLE;
			$this->errorMsg = 'not available';
			return $result;
		}

		$orderId = $this->request['orderId'];

		if($model = RisexTransaction::getModel(['order_id'=>$orderId, 'user_id'=>$this->user->id]))
		{
			$result = [
				'orderId' => $orderId,
				'status' => $model->statusStr,
				'requisites' => $model->requisites,
				'dateCancelation' => $model->dateCancelationStr,
			];
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			toLogError('p2pServiceCheckStatus: '.RisexTransaction::$lastError);
			$this->errorMsg = 'Ошибка получения статуса заявки';
		}

		$this->log('p2pServiceCheckStatus_Response: ', $result);

		return $result;
	}

	/**
	 * отмена заявки p2pService
	 * @return array []
	 */
	private function p2pServiceCancel()
	{
		$this->log('p2pServiceCancel: ', $this->request);
		$result = [];

		if(!$this->user->client->checkRule('p2pService'))
		{
			$this->errorCode = self::ERROR_NOT_AVAILABLE;
			$this->errorMsg = 'not available';
			return $result;
		}

		$orderId = $this->request['orderId'];

		if($model = RisexTransaction::getModel(['order_id'=>$orderId, 'user_id'=>$this->user->id]))
		{
			if(RisexTransaction::cancelPayment($model->transaction_id))
			{
				$updatedModel = $model = RisexTransaction::getModel(['order_id'=>$orderId, 'user_id'=>$this->user->id]);
				$result = [
					'orderId' => $orderId,
					'status' => $updatedModel->statusStr,
				];
			}
			else
			{
				$this->errorCode = self::ERROR_OTHER;
				toLogError('p2pServiceCancel: '.RisexTransaction::$lastError);
				$this->errorMsg = 'Ошибка отмены заявки';
			}

		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			toLogError('p2pServiceCancel: '.RisexTransaction::$lastError);
			$this->errorMsg = 'Ошибка отмены заявки';
		}

		$this->log('p2pServiceCancel_Response: ', $result);

		return $result;
	}

	/**
	 * подтверждение оплаты заявки p2pService (запрос "Я оплатил")
	 * @return array []
	 */
	private function p2pServiceAccept()
	{
		$this->log('p2pServiceAccept: ', $this->request);
		$result = [];

		if(!$this->user->client->checkRule('p2pService'))
		{
			$this->errorCode = self::ERROR_NOT_AVAILABLE;
			$this->errorMsg = 'not available';
			return $result;
		}

		$orderId = $this->request['orderId'];

		if($model = RisexTransaction::getModel(['order_id'=>$orderId, 'user_id'=>$this->user->id]))
		{
			if(RisexTransaction::acceptPayment($model->transaction_id))
			{
				$updatedModel = $model = RisexTransaction::getModel(['order_id'=>$orderId, 'user_id'=>$this->user->id]);
				$result = [
					'orderId' => $orderId,
					'status' => $updatedModel->statusStr,
				];
			}
			else
			{
				$this->errorCode = self::ERROR_OTHER;
				toLogError('p2pServiceAccept: '.RisexTransaction::$lastError);
				$this->errorMsg = 'Ошибка подтверждения оплаты заявки';
			}

		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			toLogError('p2pServiceAccept: '.RisexTransaction::$lastError);
			$this->errorMsg = 'Ошибка подтверждения оплаты заявки';
		}

		$this->log('p2pServiceAccept_Response: ', $result);

		return $result;
	}
}
