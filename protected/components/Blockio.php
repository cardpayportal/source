<?php

/**
 * Class Blockio (Singleton)
 * todo: список выводов, сделать в панели отображение списка и кол-ва подтверждений
 */
class Blockio
{
	const BTC_TRADE_MIN = 0.01;		//минимальная сумма при покупке btc
	const BTC_WITHDRAW_MIN = 0.002;	//минимальная сумма на вывод btc (на самом деле 0.002btc, но проверка идет после прибавки коммиссии)

	//todo: точнее рассчитывать комсу избавиться от константы
	const BTC_WITHDRAW_COMMISSION_MAX = 0.002;	//максимальная комиссия при выводе

	const REG_EXP_ADDRESS = '!^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$!';
	const API_URL = 'https://block.io/api/';

	const ERROR_NO_MONEY = 'noMoney';	//недостаточно на счету для вывода

	const CHANGE_ADDRESS_COUNT_MAX = 100;

	private $_key = '';
	private $_secret = '';
	//пауза между запросами
	public $requestIntervalMin = 1;
	private $_lastRequestDate = 0;

	private $_ch = null;

	public $errorMsg = '';
	public $errorCode = '';

	public $withdrawPriority = 'low';	//влияет на комсу (low|medium|high)

	private static $_instancePrivate = null;	//для приватных запросов
	private static $_instancePublic = null;	//для приватных запросов

	/**
	 * если не указан ключ и секрет то можно выполнять публичные запросы
	 * @param string|bool $key
	 * @param string|bool $secret
	 */
	private function __construct($key = false, $secret = false)
	{
		$this->_key = $key;
		$this->_secret = $secret;
	}

	public function __destruct()
	{
		if(isset($this->_ch))
			curl_close($this->_ch);
	}

	protected function __clone(){}

	/**
	 * @param bool|string $key
	 * @param bool|string $secret
	 * @return Blockio|false
	 */
	public static function getInstance($key = false, $secret = false)
	{
		if($key and $secret)
		{
			if(is_null(self::$_instancePrivate))
				self::$_instancePrivate = new self($key, $secret);

			return self::$_instancePrivate;
		}
		else
		{
			if(is_null(self::$_instancePublic))
				self::$_instancePublic = new self($key, $secret);

			return self::$_instancePublic;
		}
	}


	/**
	 * баланс текущего апи ключа (каждый ключ к своей валюте)
	 * @return array|false|float
	 */
	public function getBalance()
	{
		$method = 'v2/get_balance';

		$response = $this->_privateRequest($method);

		if($response !== false)
			return $response['available_balance'];
		else
		{
			$this->_log('ошибка получения баланса ');
			return false;
		}

	}

	/**
	 * отослать коин на адрес
	 * @param string $address
	 * @param float $amount
	 * @return string|false id транзакции в сети
	 */
	public function withdraw($address, $amount)
	{
		$method = 'v2/withdraw';

		$amount = $amount*1;

		//,AMOUNT2,...&to_addresses=ADDRESS1,ADDRESS2,...&pin=SECRET PIN

		$amountMin = self::BTC_WITHDRAW_MIN;

		if($amount < $amountMin)
		{
			$this->errorMsg = "минимальная сумма для вывода: $amountMin";
			$this->_log($this->errorMsg);
			return false;
		}

		if(!preg_match(self::REG_EXP_ADDRESS, $address))
		{
			$this->_log(__CLASS__.' error: неверный адрес '.$address);
			return false;
		}

		$params = array(
			'amounts'=>str_replace(',', '.', $amount),
			'to_addresses'=>$address,
			'pin'=>$this->_secret,
			'priority'=>$this->withdrawPriority,
		);

		//проверить хватит ли баланса
		$balance = $this->getBalance();

		if($balance === false)
		{
			$this->errorMsg = 'ошибка получения баланса';
			return false;
		}

		$commission = $this->getWithdrawCommission($address, $amount);

		if($commission === false)
		{
			$commission = self::BTC_WITHDRAW_COMMISSION_MAX;
			/*
			$this->errorMsg = 'ошибка получения комиссии';
			$this->_log(__CLASS__.': '.$this->errorMsg);
			return false;
			*/
		}

		$amountWithCommission = $amount + $commission;

		if($balance < $amountWithCommission)
		{
			$this->_log(__CLASS__.' error: недостаточно баланса для вывода '.$balance);
			$this->errorCode = self::ERROR_NO_MONEY;
			$this->errorMsg = 'не хватает '.($amountWithCommission - $balance).' BTC для вывода на '.$address;
			return false;
		}

		$response = $this->_privateRequest($method, $params);

		if($response and $response['amount_sent'])
		{
			$this->_log(__CLASS__.': вывод '.$response['amount_sent'].' BTC на '.$address.', коммиссия '.(formatAmount($response['amount_withdrawn'] - $response['amount_sent'], 6)).' BTC, rate: '.config('storeApiBtcRate'));
			return $response['txid'];
		}
		else
		{
			$this->errorMsg = __CLASS__.': ошибка вывода: '.Tools::arr2Str($response).': '.$this->errorMsg;
			$this->_log($this->errorMsg);
			return false;
		}
	}

	private function _privateRequest($method, array $params = array())
	{
		//$method пример: v1/order/new
		$url = self::API_URL.$method;

		$url .= '/?api_key='.$this->_key;

		if($params)
			$url .= '&'.http_build_query($params);

		if(time() - $this->_lastRequestDate < $this->requestIntervalMin)
			sleep($this->requestIntervalMin);

		if($this->_ch === null)
		{
			$this->_ch = curl_init();
			curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		}

		curl_setopt($this->_ch, CURLOPT_URL, $url);
		//curl_setopt($this->_ch, CURLOPT_POST, true);
		//curl_setopt($this->_ch, CURLOPT_POSTFIELDS, "");

		$content = curl_exec($this->_ch);

		$this->_lastRequestDate =  time();
		//$httpCode = curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);

		$result = json_decode($content, true);

		if(!$result)
		{
			$this->errorMsg = 'bad json: '.$content;
			$this->_log(__CLASS__.' '.$this->errorMsg);
			return false;
		}

		if($result['status'] != 'success')
		{
			$this->errorMsg = $result['data']['error_message'];
			return false;
		}

		return $result['data'];
	}

	private function _log($msg)
	{
		toLogStoreApi($msg);
	}

	/**
	 * комиссия сети биткоин
	 * @param string $address - любой адрес сети биткоин
	 * @param float $amount
	 * @return float|false
	 */
	public function getWithdrawCommission($address = '15FXQ9uNqWStW2UNJeLxxL5nUKuZ41LuWY', $amount = self::BTC_WITHDRAW_MIN)
	{
		$method = 'v2/get_network_fee_estimate';

		$params = [
			'amounts'=>str_replace(',', '.', $amount),
			'to_addresses'=>$address,
			'priority'=>$this->withdrawPriority,
		];

		$response = $this->_privateRequest($method, $params);

		if($response)
			return ceilAmount($response['estimated_network_fee']*1, 4);
		elseif(preg_match('!Cannot withdraw funds without Network Fee of ([\d\.]+) BTC!', $this->errorMsg, $res))
		{
			//костыль: если баланс не позволяет вывести сумму, то комсу берем из ошибки
			if($res[1] <= self::BTC_WITHDRAW_COMMISSION_MAX)
			{
				$this->errorMsg = '';
				return $res[1]*1;
			}
			else
				return false;

		}
		else
			return false;
	}

	/**
	 * выдает адрес для пополнения кошелька
	 * @return string|false
	 */
	public function getDepositAddress()
	{
		if($addresses = $this->getAddresses())
			return end($addresses);
		else
			return false;
	}

	/**
	 * последние 25 выводов
	 *
	 * @return array|false
	 * [
	  		[txid] => 01383ae6b45b9161bb7eeac99defaa8df773a97b3ec0dec147243c3d6ab04adc
			[from_green_address] => 1
			[time] => 1502893691
			[confirmations] => 9
			[total_amount_sent] => 0.01694443
			[amounts_sent] => Array
			(
			[0] => Array
			(
			[recipient] => 1Cup5w19v9ge5eFjUVuq4RTXviNEUNGPct
			[amount] => 0.01601023
			)

			)

			[senders] => [
				[0] => 3AnY94ppsSJ39FFXGAaUt7QzZth9cye7Rt
			]

			[confidence] => 1
			[propagated_by_nodes] =>
	  ]
	 */
	public function getWithdrawList()
	{
		$method = 'v2/get_transactions';

		$params = [
			'type'=>'sent',
		];

		$response = $this->_privateRequest($method, $params);

		if($response)
			return $response['txs'];
		else
		{
			$this->_log(__CLASS__.'ошибка получения списка выводов: '.$this->errorMsg);
			return false;
		}
	}

	public function getAddresses()
	{
		$method = 'v2/get_my_addresses';

		$response = $this->_privateRequest($method);

		if($response and $response['addresses'])
		{
			$result = [];

			foreach($response['addresses'] as $arr)
				$result[] = $arr['address'];

			return $result;
		}
		else
			return false;
	}

	/**
	 * получение нового адреса
	 * при этом getDepositAddress() начнет выдавать именно последний полученный
	 * @return string|bool новый адрес
	 */
	public function getNewAddress()
	{
		$method = 'v2/get_new_address';

		$response = $this->_privateRequest($method);

		if($response !== false)
		{
			return $response['address'];
		}
		else
		{
			$this->_log('ошибка получения баланса');
			return false;
		}
	}

	/**
	 * оставшееся кол-во получения нового адреса
	 * @return int|bool
	 */
	public function getChangeAddressLimit()
	{
		if($addresses = $this->getAddresses())
			return self::CHANGE_ADDRESS_COUNT_MAX - count($addresses);
		else
			return false;
	}

	/**
	 * @return array
	 */
	public static function getWithdrawPriorityArr()
	{
		return [
			'low' => 'Низкий',
			'medium' => 'Средний',
			'high' => 'Высокий',
		];
	}

}