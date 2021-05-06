<?php
/**
 * Wallet Api Yandex
 */

class YandexApi
{

	const PAYMENT_DIRECTION_IN = 'in';
	const PAYMENT_DIRECTION_OUT = 'out';

	const PAYMENT_STATUS_SUCCESS = 'success';
	const PAYMENT_STATUS_WAIT = 'wait';
	const PAYMENT_STATUS_ERROR = 'error';

	public $appIdentifier;	//id  приложения
	public $appSecret;		//секрет приложения
	public $accessToken;	//токен доступа к кошельку клиента
	public $proxy;
	public $proxyType = 'http';
	public $sender;

	public $authRedirectUrl;
	public $authRedirectSuccess;
	public $error;

	const URL = 'https://money.yandex.ru';
	const URL_AUTH = 'https://money.yandex.ru/oauth/authorize';

	public function __construct()
	{
		//$this->accessToken = $accessToken;
		//$this->appIdentifier = $appIdentifier;
		//$this->appSecret = $appSecret;
		//$this->proxy = $proxy;
	}


	/**
	 * @param string $url
	 * @param array $params
	 * @return array|mixed
	 */
	private function request($url, $params = [])
	{
		if(!$this->sender)
		{
			$this->sender = new Sender;
			$this->sender->followLocation = false;
			$this->sender->useCookie = false;
			$this->sender->browser = 'Yandex.Money.SDK/PHP';
			//test поменять на http
			$this->sender->proxyType = $this->proxyType;

			$this->sender->additionalHeaders = [
				"Authorization: Bearer {$this->accessToken}"
			];
		}

		$postData = ($params) ? http_build_query($params) : '';

		if(YII_DEBUG)
		{
			echo "\n";
			echo self::URL.$url."\n";
			echo $postData."\n";
		}

		$content = $this->sender->send(self::URL.$url, $postData, $this->proxy);

		if(YII_DEBUG)
		{
			print_r($this->sender->info);
		}

		if($arr = @json_decode($content, true))
		{
			if($arr['error'])
				$this->error = $arr['error'];

			return $arr;
		}
		else
		{
			$httpCode = $this->sender->info['httpCode'][0];

			if($httpCode == 401)
				$this->error = 'Указанный токен не существует, либо уже отозван.';
			else
				$this->error = 'error  json_decode, content: '.$content.', httpCode: ' . $httpCode;

			return false;
		}
	}

	public function authForm()
	{
		$action = self::URL_AUTH;

		$form = <<<EOD
		<form method="post" action="{$action}" target="_blank">
			<input type="hidden" name="client_id" value="{$this->appIdentifier}"/>
			<input type="hidden" name="response_type" value="code"/>
			<input type="hidden" name="redirect_uri" value="{$this->authRedirectUrl}"/>
			<input type="hidden" name="scope" value="account-info operation-history"/>

			<input type="submit" name="" value="Отправить"/>
		</form>
EOD;
		return $form;

	}

	/**
	 * @param string $code
	 * @return string access_token|bool
	 */
	public function auth($code)
	{
		$params = [
			'code' => $code,
			'client_id' => $this->appIdentifier,
			'grant_type' => 'authorization_code',
			'redirect_uri' => $this->authRedirectSuccess,
			'client_secret' => $this->appSecret,
		];

		$response = $this->request('/oauth/token', $params);

		if($response and $response['access_token'])
		{
			return  $response['access_token'];
		}
		else
		{
			return false;
		}
	}

	/**
	 * @return bool|float
	 */
	public function getBalance()
	{
		if($response = $this->request('/api/account-info'))
			return $response['balance'] * 1;
		else
			return false;
	}

	/**
	 * 100 лимит
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param string $direction in|out
	 * @return array|bool
	 */
	public function getHistory($timestampStart = 0, $timestampEnd = 0, $direction = '')
	{
		$result = [];

		$params = [
			'records' => 100,
			'start_record' => 0,
		];

		if($timestampStart)
			$params['from'] = date('c', $timestampStart);

		if($timestampEnd)
			$params['till'] = date('c', $timestampEnd);

		if($direction)
			$params['type'] = ($direction == self::PAYMENT_DIRECTION_IN) ? 'deposition' : 'payment';


		$lastPage = false; //получаем несколько страниц с платежами

		while(!$lastPage)
		{
			if($response = $this->request('/api/operation-history', $params))
			{
				if(count($response['operations']) < $params['records'])
					$lastPage = true;

				foreach($response['operations'] as $operation)
				{
					//статус платежа
					if($operation['status'] == 'success')
						$status = self::PAYMENT_STATUS_SUCCESS;
					elseif($operation['status'] == 'in_progress')
						$status = self::PAYMENT_STATUS_WAIT;
					elseif($operation['status'] == 'error' or $operation['status'] == 'refused')
						$status = self::PAYMENT_STATUS_ERROR;
					else
					{
						toLogError('YandexAccount error:  неизвестный статус платежа: '.Tools::arr2Str($operation));
						return false;
					}


					$arr = [
						'id' => $operation['operation_id'],
						'amount' => $operation['amount'],
						'direction' => ($operation['direction'] == 'in') ? self::PAYMENT_DIRECTION_IN : self::PAYMENT_DIRECTION_OUT,
						'timestamp' => strtotime($operation['datetime']),
						'date' => date('d.m.Y H:i', strtotime($operation['datetime'])),
						'label' => $operation['label'],
						'title' => $operation['title'],
						//todo: доделать статусы
						'status' => $status,
					];

					$result[] = $arr;
				}

			}
			else
				return false;

			$params['start_record'] += $params['records'];

			sleep(1);
		}

		return $result;
	}

}