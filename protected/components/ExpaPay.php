<?php

/**
 * Class ExpaPay
 * @property array $error
 */
Class ExpaPay
{
	//['msg'=>'', 'code'=>'']
	public $error;

	protected $sender;

	private $key;
	private $secret;

	private $apiUrl = 'https://gw.expapay.com/v1/';

	/**
	 * ExpaPay constructor.
	 * @param array $params [
	 * 		'key' => '',
	 * 		'proxy' => '',
	 * ]
	 */
	public function __construct(array $params)
	{
		$this->key = $params['key'];
		$this->secret = $params['secret'];
		$this->sender = new Sender;
		$this->sender->followLocation = false;
		$this->sender->useCookie = false;

	}

	/**
	 * @param string $method
	 * @param array $params
	 * @return mixed
	 */
	protected function request($method, array $params)
	{
		$url = $this->apiUrl.$method;

		$params['account_id'] = $this->key;
		$params['signature'] = $this->hash($params);

		$this->sender->proxyType = 'http';
		$this->sender->additionalHeaders = [
			'Content-Type: application/json',
		];
		print_r($params);
		var_dump($url);
		$content = $this->sender->send($url, json_encode($params));
		print_r($this->sender->info);

		$json = @json_decode($content, true);

		echo "\n\n RESPONSE: \n\n";
		print_r($json);

		if(!$json)
		{
			$this->error['msg'] = 'error content: '.$content.', httpCode='.$this->sender->info['httpCode'][0];
			return false;
		}
	}

	private function hash(array $params)
	{
		return hash_hmac('sha256', $this->arr2str($params), $this->secret);
	}

	private function arr2str($array)
	{
		ksort($array);

		$str = '';

		foreach($array as $key=>$val)
		{
			if(is_array($val))
				$str .= $this->arr2str($val);
			elseif($key == 'amount') {
				$str .= number_format((float)$val, 2, ".", "");
			}
			elseif(is_bool($val))
				$str .= $val ? '1' : '0';
			else
				$str .= $val;
		}

		return $str;
	}

	public function card2card($params)
	{
		$method = 'transfer';

		var_dump($this->request($method, $params));
	}

	public function balance($params)
	{
		$method = 'balance';

		var_dump($this->request($method, $params));
	}

	public function test()
	{

	}

}