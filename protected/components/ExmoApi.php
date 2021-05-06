<?php

/**
 * Class ExmoApi
 * @property string proxy
 * @property Sender _sender
 */
class ExmoApi extends CApplicationComponent
{
	const URL = 'https://api.exmo.com/v1/';

	public $proxy = false;
	public $lastResponse = '';	//последний ответ
	public $error = '';
	public $errorCode = '';

	protected $_sender;
	public $timeout = 30;


	public function init()
	{
		$this->_sender = new Sender;
		$this->_sender->followLocation = false;
		$this->_sender->useCookie = false;
		$this->_sender->browser = '';
		$this->_sender->timeout = $this->timeout;

	}

	/**
	 * @param $url
	 * @param null $postData
	 * @return array|false
	 */
	public function requestPublic($url, $postData=null)
	{

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

			return false;
		}
	}

	/**
	 * @param string $pair
	 * @return array|false
	 */
	public function getTicker($pair='')
	{
		$url = self::URL.'ticker/';

		$response = $this->requestPublic($url);

		if(!$response)
			return false;

		if($pair)
			return $response[$pair];
		else
			return $response;
	}

	/**
	 * @param $pair
	 * @param string $type
	 * @return float|false
	 */
	public function getRate($pair, $type = 'buy')
	{
		$response = $this->getTicker($pair);

		if(!$response)
			return false;

		if($type == 'buy')
			$result = $response['buy_price'];
		elseif($type == 'sell')
			$result = $response['sell_price'];
		else
		{
			$this->error = 'error type';
			return false;
		}

		return round($result, 2);
	}




}