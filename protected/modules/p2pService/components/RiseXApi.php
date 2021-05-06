<?php
/**
 * работа с апи RiseX
 */
class RiseXApi
{
	public static $lastError;
	public static $lastErrorCode;

	protected $_baseUrl = 'https://api.risex.net/api/v1';
	protected $_clienId; //данные из апи
	protected $_clientSecret; //данные из апи
	protected $_authToken; //токен авторизации
	protected $_config;
	public $proxy;


	public function __construct()
	{
		$this->_config = Yii::app()->getModule('p2pService')->config;
		$this->_authToken = $this->_config['token'];
		$this->proxy = $this->_config['proxy'];
	}

	protected function _sendQueryRequest($url, $postData, $extraCurlOpt=false)
	{
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"Content-Type: application/json",
			"Authorization: Bearer {$this->_authToken}",
			"Accept: application/json"
		];

		if($postData)
			$json = json_encode($postData);
		else
			$json = false;

		$content = $sender->send($url, $json, $this->proxy, false, $extraCurlOpt);
		$contentArr = json_decode($content, true);

		if(is_array($contentArr))
		{
			return $contentArr;
		}
		else
		{
			self::$lastError = arr2str($contentArr);
			toLogError('RiseX error, content: '.$content.' '.arr2str($contentArr).' '.self::$lastError);
			return false;
		}

	}

	protected function _sendRequest($method, $postData, $extraCurlOpt=false)
	{
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"Content-Type: application/json",
			"Authorization: Bearer {$this->_authToken}",
			"Accept: application/json"
		];

		if($postData)
			$json = json_encode($postData);
		else
			$json = false;

//		if(YII_DEBUG)
//			prrd($this->_baseUrl.$method);

		$content = $sender->send($this->_baseUrl.$method, $json, $this->proxy, false, $extraCurlOpt);
		$contentArr = json_decode($content, true);

		if(is_array($contentArr))
		{
			return $contentArr;
		}
		else
		{
			self::$lastError = arr2str($contentArr);
			toLogError('RiseX error, content: '.$content.' '.self::$lastError);
			return false;
		}

	}

	/**
	 * @return array|mixed
	 * получаем список созданных заявок (OLD)
	 */
	public function dealList()
	{
		$method = '/deal';
		$postData = false;

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * получаем список созданных заявок (NEW)
	 */
	public function dealListNew($page=1)
	{
		$perPage = 15;
		$isActive = 0;
		$canceled = 0;
		$cryptoCurrencyId = 1;

		$url = 'https://api.risex.net/api/v1/dashboard/deals?crypto_currency_id='.$cryptoCurrencyId
			.'&canceled='.$canceled.'&per_page='.$perPage.'&page='.$page;
//		$url = 'https://api.risex.net/api/v1/dashboard/deals?is_active='.$isActive.'&crypto_currency_id='.$cryptoCurrencyId
//			.'&canceled='.$canceled.'&per_page='.$perPage.'&page='.$page;

		return $this->_sendQueryRequest($url, false);
	}


	/**
	 * @return array|mixed
	 * отправить информацию об оплате заявки
	 */
	public function acceptPayment($id)
	{
		$method = "/deal/".$id."/paid";
		$postData = false;

		return $this->_sendRequest($method, $postData, 'put');
	}

	/**
	 * @return array|mixed
	 * отменяем заявку
	 */
	public function cancelPayment($id)
	{
		$method = "/deal/".$id."/cancel";
		$postData = false;

		return $this->_sendRequest($method, $postData, 'put');
	}

	/**
	 * @return array|mixed
	 * отменяем заявку
	 */
	public function balanceBtc($id)
	{
		$method = "/profile/balance/btc";
		$postData = false;

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * создаем сделку
	 * @param $amount - цена в рублях
	 */
	public function createDeal($amount)
	{
		$amount = $amount*1;
		$method = '/deal/trusted';
		$postData = [
			'is_sale' => '1',
			'fiat_amount' => $amount,
			'payment_system_id' => '3',
			'currency_id' => '1',
			'crypto_currency_id' => '1',
			'top' => '10',
			'deviation' => '4.1',
		];

		return $this->_sendRequest($method, $postData);
	}

}