<?php
/**
 * Class SmsRegApi
 * для приема смс
 *
	1. Запросить номер с необходимыми параметрами (страна, для какого сервиса);
	2. Использовать номер и установить транзакцию в состояние "Готов";
	3. Запустить цикл, который будет проверять поступление ответа;
	4. Если ответ верный - завершить транзакцию;
	5. Если ответ не подошел - запросить уточнение;
	6. Ответ после уточнения верный - завершить транзакцию ;
	7. Ответ не верный - отправить уведомления о неверном коде.
 *
 * @property string $apiKey
 * @property Sender $sender
 * @property string $proxy
 * @property string $lastContent
 * @property int $timeout
 * @property string $error
 * @property string $errorCode
 */
class SmsRegApi
{
	private $apiKey;
	private $sender;
	private $proxy;
	private $timeout = 30;

	public $error = '';
	public $errorCode = '';


	//сколько ждать всего
	const REQUEST_WAIT_TIME = 120;
	//через сколько повторить
	const REQUEST_INTERVAL = 5;

	public function __construct($proxy)
	{
		$this->apiKey = cfg('smsRegApiKey');
		$this->proxy = $proxy;

		$this->sender = new Sender;
		$this->sender->useCookie = false;
		$this->sender->followLocation = false;
		$this->sender->timeout = $this->timeout;
	}

	/**
	 * @param string $url
	 * @param bool|string $postData
	 * @return string|bool
	 */
	private function request($url, $postData = false)
	{
		$content = $this->sender->send($url, $postData, $this->proxy);

		if($result = @json_decode($content, true))
		{
			return $result;
		}
		else
		{
			$this->error = 'error parse json: content='.$content;
			return false;
		}
	}

	/**
	 * заказываем новый номер
	 * @param string|bool $tzid	если не нужно получать номер повторно
	 * @return array|bool
	 */
	public function getNewNumber($tzid = false)
	{
		if(!$tzid)
		{
			$params = [
				'country' => 'all',
				'service' => 'other',
			];

			$paramsStr = http_build_query($params);

			$url = 'http://api.sms-reg.com/getNum.php?'.$paramsStr.'&apikey='.$this->apiKey;
			$response = $this->request($url);
			$tzid = $response['tzid'];
		}

		$result = [];

		if($tzid)
		{
			echo "\n  операция ".$tzid;

			$response = $this->getState($tzid);

			if($response and $response['number'])
			{
				$result['id'] = $tzid;
				$result['phone'] = $response['number'];
			}
			else
			{
				echo "\n error1";
				prrd($response);
			}
		}
		else
		{
			echo "\n error2";
			prrd($response);
			return false;
		}

		return $result;
	}

	public function getSms($tzid)
	{
		$response = $this->getState($tzid);

		if($response and $response['msg'])
		{
			return $response['msg'];
		}
		else
		{
			echo "\n error4";
			prrd($response);
		}
	}

	/**
	 * ожидение окончания операции
	 * @param string $tzid
	 * @return array
	 */
	private function getState($tzid)
	{
		$url = 'http://api.sms-reg.com/getState.php?tzid='.$tzid.'&apikey='.$this->apiKey;

		$startTimestamp = time();

		do
		{
			$response = $this->request($url);

			if(!preg_match('!.*WAIT.*!', $response['response']) and !preg_match('!TZ_INPOOL!', $response['response']))
			{
				return $response;
			}
			else
				//надо подождать
				sleep(self::REQUEST_INTERVAL);
		}
		while(time() - $startTimestamp < self::REQUEST_WAIT_TIME);
	}

	/**
	 * устанавливает операцию в готовность принять смс
	 * @param string $tzid
	 * @return bool
	 */
	public function setReady($tzid)
	{
		sleep(30);	//вернет to this TZID not applicable если с момента получения номера не прошло 30сек

		$url = 'http://api.sms-reg.com/setReady.php?tzid='.$tzid.'&apikey='.$this->apiKey;

		$response = $this->request($url);

		if($response and ($response['response'] == '1'
				or preg_match('!to this TZID not applicable!', $response['error_msg'])))
			return true;
		else
		{
			echo "\n error3";
			prrd($response);
		}
	}

	/**
	 * устанавливает операцию в завершение
	 * @param string $tzid
	 * @return bool
	 */
	public function setComplete($tzid)
	{
		$url = 'http://api.sms-reg.com/setOperationOk.php?tzid='.$tzid.'&apikey='.$this->apiKey;

		$response = $this->request($url);

		if($response and ($response['response'] == '1'
				or preg_match('!to this TZID not applicable!', $response['error_msg'])))
			return true;
		else
		{
			echo "\n error3";
			prrd($response);
		}
	}


	/**
	 * @param string $phone
	 * @return array|bool
	 */
	public function getSmsHistory($phone)
	{
		$result = [];
		$phone = str_replace('+', '', $phone);

		$url = 'http://api.sms-reg.com/vsimGetSMS.php?number='.$phone.'&apikey='.$this->apiKey;
		$response = $this->request($url);

		if($response and $response['response']=='1')
		{
			$items = $response['items'];

			foreach($items as $item)
			{
				$result[] = [
					'date' => $item['date'],
					'timestamp' =>	strtotime($item['date']),
					'from' => $item['sender'],
					'text' => $item['text'],
				];
			}
		}
		else
		{
			echo "\n error6";
			prrd($response);
		}

		return $result;
	}

	/**
	 * @param string $country 	-	страна	ru, ua, ...
	 * @param string $time 		-	на сколько брать 3hours, day, week
	 * @return array|bool
	 *
	 */
	public function getPersonalNumber($country, $period = 'day')
	{
		if(!in_array($country, ['ru', 'ua']))
		{
			$this->error = 'wrong country';
			return false;
		}

		if($period === '3hours')
			$expire = time() + 3600 * 3;
		elseif($period === 'day')
			$expire = time() + 3600 * 24;
		elseif($period === 'week')
			$expire = time() + 3600 * 24 * 7;
		else
		{
			$this->error = 'wrong time';
			return false;
		}

		$result = [
			'phone' => '',
			'expire' => $expire,
		];

		$url = 'http://api.sms-reg.com/vsimGet.php?country='
			.$country.'&period='.$period.'&apikey='.$this->apiKey;

		$response = $this->request($url);
		prrd($response);
		if($response and $response['response']=='1')
		{
			$result['phone'] = '+'.$response['number'];
			$result['expire'] = $expire;
		}
		elseif($response['response'] == 'ERROR')
		{
			$this->error = $response['error_msg'];
			toLogError($this->error);
			return false;
		}

		return $result;
	}

}