<?php

/**
 * для теста киви на мегакассе
 */
class TestApiController extends CController
{
	const API_KEY = 'ndsfsdhf94329432h324jhg324hg32j42g34';

	const ERROR_ACCESS = 'accessDenied';
	const ERROR_REQUEST = 'invalidRequest';
	const ERROR_METHOD = 'invalidMethod';
	const ERROR_OTHER = 'otherError';

	private $errorCode = '';
	private $errorMsg = '';

	private $rawRequest = '';
	private $request = [];

	protected function beforeAction($action)
	{
		//sleep(self::REQUEST_PAUSE);
		session_write_close();

		$this->rawRequest = file_get_contents('php://input');
		$this->request = @json_decode($this->rawRequest, true);


		if(!is_array($this->request))
		{
			//запрос не в json
			var_dump($this->request);
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
		if(!$this->request['key'] or !$this->request['method'])
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указаны необходимые параметры (key, method)';
			return false;
		}

		if(in_array($this->request['method'], self::getAllowedMethods()) === false)
		{
			$this->errorCode = self::ERROR_METHOD;
			$this->errorMsg = 'метод '.$this->request['method'].' не найден';
			return false;
		}

		if($this->request['key'] !== self::API_KEY)
		{
			$this->errorCode = self::ERROR_ACCESS;
			$this->errorMsg = 'неверный key';
			return false;
		}

		return true;
	}

	protected function getErrorMsg()
	{
		$arr = [
			self::ERROR_ACCESS => 'доступ запрещен',
			self::ERROR_REQUEST => 'неверный формат запроса',
			self::ERROR_METHOD => 'неверный параметр method',
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

	public static function getAllowedMethods()
	{
		return [
			'getPayUrl',
			'checkPayment',
		];
	}

	/**
	 * @return array ['url'=>'...', 'orderId'=>'YandexPay ID']
	 */
	private function getPayUrl()
	{
		$result = [];

		//orderId
		$orderId = $this->request['orderId']*1;

		if(!$orderId)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'не указан orderId';
			return $result;
		}

		if($model = TestQiwi::getModel(['order_id'=>$orderId]))
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'этот orderId уже зарегистрирован';
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

		//валюта
		if(in_array($this->request['currency'], self::getCurrencies()) === false)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'неверная валюта';
			return $result;
		}

		//телефон
		$phone = trim($this->request['phone'], '+ ');

		if(!$phone)
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'неверный телефон';
			return $result;
		}

		if($url = TestQiwi::generateMegakassaUrl(
			$amount, $orderId, $phone,
			$this->request['successUrl'], $this->request['failUrl'])
		)
		{
			$result = [
				'url' => $url,
			];
		}
		else
		{
			$this->errorCode = self::ERROR_OTHER;
			$this->errorMsg = NewYandexPay::$lastError;
		}

		return $result;
	}

	public static function getCurrencies()
	{
		return [
			'RUB',
		];
	}

	/**
	 * @return array [
	 * 		'orderId'=>213123,
	 * 		'status'=>'wait|success|error',
	 * ]
	 */
	private function checkPayment()
	{
		$result = [];

		$orderId = $this->request['orderId'];

		if(!$model = TestQiwi::getModel(['order_id'=>$orderId]))
		{
			$this->errorCode = self::ERROR_REQUEST;
			$this->errorMsg = 'заявка не найдена';
			return $result;
		}

		$result = [
			'amount' => $model->amount,
			'currency' => $model->currency,
			'status' => $model->status,
			'orderId' => $model->order_id,
			'timestampPay' => $model->date_pay,
		];

		return $result;
	}

}
