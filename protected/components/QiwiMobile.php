<?php

/**
 * Class QiwiApi
 */
class QiwiMobile extends QiwiApi
{
	const TOKEN_LIFETIME = 1800;
	const CLIENT_ID = 'android-qw';
	const CLIENT_SECRET = 'zAm4FKq9UnSe7id';
	const DOMAIN = 'mobile-api.qiwi.com';

	public $deviceId = '';
	public $devicePin = '';

	public $token = '';	//постоянный токен
	public $accessToken = '';	//временны токен
	public $accessTokenExpire = 0;	//истекает временный токен


	public function init()
	{
		$this->_sender = new Sender;
		$this->_sender->followLocation = false;
		$this->_sender->useCookie = false;
		$this->_sender->browser = '';
		$this->_sender->timeout = $this->timeout;

		//общие заголовки для всех запросов
		$this->_sender->additionalHeaders = [
			'accept' => 'Accept: application/json',
			'contentType' => 'Content-Type: application/json',
			'clientSoftware' => 'Client-Software: Android v3.14.0 MKT',
			'userAgent' => 'User-Agent: okhttp/3.8.0',
		];

		$this->estmatedTransactions = [];
		//$this->_sender->pause = 1;
	}

	/**
	 * @param $url
	 * @param null $postData
	 * @return array|false
	 */
	protected function request($url, $postData=null)
	{
		if(!$this->checkParams())
			return false;

		$this->_sender->additionalHeaders['authorization'] = "Authorization: Bearer {$this->accessToken}";
		$this->_sender->additionalHeaders['deviceId'] = "X-QIWI-UDID: {$this->deviceId}";

		$this->lastResponse = $this->_sender->send($url, $postData, $this->proxy);

		if($arr = json_decode($this->lastResponse, true))
		{
			return $arr;
		}
		else
		{
			$this->error = 'error json decode response: '.$this->lastResponse.', httpCode = '
				.$this->_sender->info['httpCode'][0]
				.', ';

			if($this->_sender->info['httpCode'][0] == 401)
				$this->errorCode = self::ERROR_BAN;

			return false;
		}
	}

	/**
	 * на получение токена
	 * @param $url
	 * @param null $postData
	 * @return array|false
	 */
	private function requestSimple($url, $postData=null)
	{
		$this->_sender->additionalHeaders['authorization'] = "Authorization: Bearer {$this->accessToken}";
		$this->_sender->additionalHeaders['deviceId'] = "X-QIWI-UDID: {$this->deviceId}";

		$url .= '?'.$postData;

		$this->lastResponse = $this->_sender->send($url, $postData, $this->proxy);


		if($arr = json_decode($this->lastResponse, true))
		{
			return $arr;
		}
		else
		{
			$this->error = 'error json decode response: '.$this->lastResponse.', httpCode = '
				.$this->_sender->info['httpCode'][0]
				.', ';

			if($this->_sender->info['httpCode'][0] == 401)
				$this->errorCode = self::ERROR_BAN;

			return false;
		}
	}

	public function refreshAccessToken()
	{
		if(time() < $this->accessTokenExpire - 300)
			return $this->accessToken;

		$url = 'https://'.self::DOMAIN.'/oauth/token';

		$params = [
			'client_id' => self::CLIENT_ID,
			'client_secret' => self::CLIENT_SECRET,
			'grant_type' => 'urn:qiwi:oauth:grant-type:app-token',
			'app_token' => $this->token,
			'mobile_pin' => $this->devicePin,
		];

		$paramsStr = http_build_query($params);
		//$paramsStr = json_encode($params);
		//$paramsStr = 'client_id=android-qw';
        //'response_type' => 'urn:qiwi:oauth:response-type:confirmation-id',
        //'username' => str_replace('+7', '7', $this->model->number),

		$response = $this->requestSimple($url, $paramsStr);


		if(!isset($response['access_token']))
		{
			//toLogError('error get accessToken '.$this->login.': '.Tools::arr2Str($response));
			$this->error = 'error get accessToken';
			return false;
		}


		$this->accessToken = $response['access_token'];
		$this->accessTokenExpire = time() + $response['expires_in'];

		AccountMobile::updateAccessToken($this->deviceId, $this->accessToken, $this->accessTokenExpire);

		return true;
	}



	/**
	 * todo: дописать проверки установленных параметров(всех ли хватает, не  истек ли токен, ...)
	 *
	 * @return bool
	 */
	private function checkParams()
	{
		if(!$this->refreshAccessToken())
			return false;

		return true;
	}




}