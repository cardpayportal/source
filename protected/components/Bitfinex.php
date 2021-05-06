<?php

/**
 * Class Bitfinex (Singleton)
 * todo: добавить функцию логирования
 *
 */
class Bitfinex
{
	const COMMISSION_TRADE = 0.002;	//0.2%
	const COMMISSION_WITHDRAW_BTC = 0.0004;	//0.001btc

	const BTC_TRADE_MIN = 0.01;		//минимальная сумма при покупке btc
	const BTC_WITHDRAW_MIN = 0.001;	//минимальная сумма на вывод btc (на самом деле 0.002btc, но проверка идет после прибавки коммиссии)
	const CODE_CREATE_MIN = 0.01;

	const REG_EXP_ADDRESS = '!^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$!';

	const ERROR_WITHDRAW_BALANCE = 1;	//недостаточно баланса для вывода

	const PAIR_BTC_USD = 'btcusd';

	const CURRENCY_BTC = 'btc';
	const CURRENCY_USD = 'usd';

	const API_URL = 'https://api.bitfinex.com/';


	private $key = '';

	private $secret = '';
	//пауза между запросами
	private $requestIntervalMin = 1;

	private $lastRequestDate = 0;

	private $ch = null;
	
	public $errorMsg = '';
	public $errorCode = 0;
	
	private static $_instancePublic = null;		//для публичных запросов

	private static $_instancePrivate = null;	//для приватных запросов

	/**
	 * если не указан ключ и секрет то можно выполнять публичные запросы
	 * @param string|bool $key
	 * @param string|bool $secret
	 */
	private function __construct($key = false, $secret = false)
	{
		$this->key = $key;
		$this->secret = $secret;
	}

	public function __destruct()
	{
		if(isset($this->ch))
			curl_close($this->ch);
	}

	protected function __clone(){}

	static public function getInstance($key = false, $secret = false)
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
	 * баланс валюты, либо массив балансов всех валют на аккаунте
	 * @param string $currency
	 * @return array|false|float
	 */
	public function getBalance($currency = null)
	{
		if($currency and in_array($currency, self::getCurrencies()) === false)
		{
			$this->errorMsg = 'неверная валюта: '.$currency;
			return false;
		}

		$method = 'v1/balances';

		$response = $this->privateRequest($method);

		if($response !== false)
		{
			$result = array();

			foreach($response as $wallet)
			{
				if($wallet['currency'] == $currency and $wallet['type'] == 'exchange')
					return $wallet['amount'] * 1;
				elseif($wallet['type'] == 'exchange')
					$result[$wallet['currency']] = $wallet['amount'] * 1;
			}

			return $result;
		}
		else
		{
			$this->log('ошибка получения баланса '.$currency);
			return false;
		}

	}

	/**
	 * возвращает массив ордера на покупку
	 *
	 * @param float $price
	 * @param float $amount
	 * @param string $pair
	 * @return array|bool
	 */
	public function buy($amount, $price, $pair = self::PAIR_BTC_USD)
	{
		$response = $this->privateRequest('v1/order/new', array(
			'symbol'=>'btcusd',
			'amount'=>str_replace(',', '.', $amount),
			'price'=>str_replace(',', '.', $price),
			'side'=>'buy',
			'type'=>'exchange limit',
		));

		if($response)
			return $this->orderInfoReplace($response);
		else
			return false;
	}

	/**
	 * получить курс, по которому можно купить $amont btc
	 * @param float $amount
	 * @return float|false
	 */
	public function getPriceByAmount($amount)
	{
		$asks = $this->getAsks();

		if($asks)
		{
			$amountSum = 0;	//ищем
			$price = $asks[0]['price'];

			foreach($asks as $key=>$ask)
			{
				$amountSum += $ask['amount'];

				if($amountSum >= $amount)
				{
					$price = $ask['price'];
					break;
				}
			}
		}
		else
			return false;

		return $price * 1;
	}

	/**
	 * немедленная покупка по рынку(через $this->getPriceByAmount())
	 * возвращает купленную сумму
	 *
	 * @param float $amount
	 * @param string $pair
	 * @return float|bool
	 */
	public function buyInstant($amount, $pair = self::PAIR_BTC_USD)
	{
		if($amount >= self::BTC_TRADE_MIN)
		{
			//отменить все текущие ордера
			if($this->cancelAllOrders())
			{
				//вычисляем курс
				$price = $this->getPriceByAmount($amount);

				//ставим на 1 проц выше на случай ухода рынка
				$price *= 1.01;

				if($price)
				{
					$balance = $this->getBalance(substr($pair, 3, 3));

					if($this->isEnoughMoney('trade', $amount, $balance, $price))
					{
						$order = $this->buy($amount, $price);

						if($order)
						{
							//ждем пока обновится инфа об ордере
							sleep(5);

							if($orderInfo = $this->getOrderInfo($order['id']))
							{
								if($orderInfo['executed_amount'] < $amount)
									$this->log(__CLASS__.' error: купленная сумма не сходится: amount='.$amount.', buyAmount='.$orderInfo['executed_amount']);

								return $orderInfo['executed_amount'];
							}
							else
							{
								$this->log(__CLASS__.' error: ордер не получен '.$order['id'].': '.$this->errorMsg);
								return false;
							}
						}
						else
						{
							$this->log(__CLASS__.' error: ошибка покупки btc: amount='.$amount.', price='.$price.': '.$this->errorMsg);
							return false;
						}
					}
					else
					{
						$this->log(__CLASS__.' error: недостаточно баланса для покупки btc: amount='.$amount.', price='.$price.', balance='.$balance);
						return false;
					}
				}
				else
				{
					$this->log(__CLASS__.' error: ошибка получения цены на сумму '.$amount.' btc');
					return false;
				}
			}
			else
				return false;
		}
		else
		{
			$this->log(__CLASS__.' error buyInstant(): amount is too small');
			return false;
		}
	}


	/**
	 * получить стакан на продажу
	 * @param string $pair
	 * @return array|bool
	 */
	private function getAsks($pair = self::PAIR_BTC_USD)
	{
		$array = $this->publicRequest('v1/book/'.$pair);

		if($array)
			return $array['asks'];
		else
			return false;
	}

	/**
	 * @param string $pair
	 * @return array|bool
	 */
	public function getActiveOrders($pair = '')
	{
		$result = array();

		$response = $this->privateRequest('v1/orders');

		if(is_array($response))
		{
			foreach($response as $order)
			{
				if($pair and $order['symbol'] !== $pair)
					continue;

				$result[$order['id']] = array(
					'type'=>$order['side'],
					'price'=>$order['price'],
					'amount'=>$order['original_amount'],
					'timestamp'=>round($order['timestamp']),
				);
			}
		}

		return $result;
	}

	/**
	 * @param $id
	 * @return bool
	 */
	public function cancelOrder($id)
	{
		$result = $this->privateRequest('v1/order/cancel', array('order_id'=>$id * 1));

		if($result and $result['id'])
		{
			$this->log(__CLASS__.': отмена ордера: '.$id);
			return true;
		}
		else
		{
			$this->log(__CLASS__.' error: error cancel order: '.$id);
			return false;
		}

	}

	/**
	 * отменяет по всем парам
	 * @return bool
	 */
	public function cancelAllOrders()
	{
		$response = $this->privateRequest('v1/order/cancel/all');

		if($response['result'] == 'None to cancel')
			return true;

		return !$this->getActiveOrders();
	}


	/**
	 * хватит ли баланса на проведение операции
	 * при покупке бтц: умножить на COMMISSION_TRADE
	 * при переводе бтц: прибавить COMMISSION_WITHDRAW_BTC
	 * @param string $operationType 'buyBtc'|'withdrawBtc'
	 * @param float $amount
	 * @param float $balance
	 * @param float|bool $price	курс
	 * @return bool|null
	 */
	public function isEnoughMoney($operationType, $amount, $balance, $price = false)
	{
		$amount *= 1;

		if($price === false)
			$price = $this->getPriceByAmount($amount);

		if($operationType == 'trade')
		{
			if($balance !==false)
			{
				if($amount > 0 and $price > 0)
					$cost = $amount * $price  * (1 + self::COMMISSION_TRADE);
				else
				{
					$this->log(__CLASS__.' error: неверный amount  или price isEnoughMoney 1: amount: '.$amount.', price: '.$price);
					return null;
				}
			}
			else
			{
				$this->log(__CLASS__.' error: неверный $balance при расчете isEnoughMoney1');
				return null;
			}
		}
		elseif($operationType == 'withdraw')
		{
			if($balance !== false)
			{
				if($amount > 0)
					$cost = $amount + self::COMMISSION_WITHDRAW_BTC;
				else
				{
					$this->log(__CLASS__.' error: неверный amount isEnoughMoney 2: '.$amount);
					return null;
				}
			}
			else
			{
				$this->log(__CLASS__.' error: неверный $balance при расчете isEnoughMoney2');
				return null;
			}
		}
		else
		{
			$this->log(__CLASS__.' error: неверный тип isEnoughMoney: '.$operationType);
			return null;
		}

		return $balance >= $cost;
	}

	/**
	 * отослать btc на адрес
	 * @param float $amount
	 * @param string $address
	 * @param string $currency
	 * @return int|false id вывода
	 */
	public function withdraw($address, $amount, $currency = self::CURRENCY_BTC)
	{
		$amount = $amount*1;

		$params = array(
			'walletselected'=>'exchange',
		);

		if($currency === self::CURRENCY_BTC)
		{
			$amountMin = self::BTC_WITHDRAW_MIN;
			$params['withdraw_type'] = 'bitcoin';
		}
		else
		{
			$this->errorMsg = 'неверная валюта для вывода: '.$currency;
			return false;
		}

		if($amount < $amountMin)
		{
			$this->errorMsg = "минимальная сумма для вывода: $amountMin $currency";
			$this->log($this->errorMsg);
			return false;
		}

		if(!preg_match(self::REG_EXP_ADDRESS, $address))
		{
			$this->log(__CLASS__.' error: неверный адрес '.$address);
			return false;
		}

		$params['address'] = $address;

		//проверить хватит ли баланса
		$balance = $this->getBalance(self::CURRENCY_BTC);

		$amountWithCommission = $amount + self::COMMISSION_WITHDRAW_BTC;

		if($balance < $amountWithCommission)
		{
			$this->log(__CLASS__.' error: недостаточно баланса для вывода '.$balance.' '.$currency);
			return false;
		}

		$params['amount'] = str_replace(',', '.', $amount);

		$response = $this->privateRequest('v1/withdraw', $params);

		if($response and $response[0]['status'] == 'success')
		{
			//if($response['return']['amountSent'] !== $amount)
			//	$this->log(__CLASS__.' error: результат вывода('.Tools::arr2Str($response).') отличается от необходимого('.$amount.')');

			$this->log(__CLASS__.': вывод '.$amount.'btc на '.$address.', коммиссия '.$response[0]['fees'].' btc, Last Price: '.config('btc_usd_rate_bitfinex'));

			return $response[0]['withdrawal_id'];
		}
		else
		{
			$this->errorMsg = 'ошибка вывода: '.Tools::arr2Str($response);
			$this->log($this->errorMsg);
			return false;
		}
	}

	public function getLastPrice($pair = self::PAIR_BTC_USD)
	{
		if($pair == 'btc_usd')
			$pair = 'btcusd';

		$url = 'v1/pubticker/'.$pair;

		$response = $this->publicRequest($url);

		if(is_array($response))
			return ($response['last_price'])*1;
		else
			return false;
	}

	private function privateRequest($method, array $params = array())
	{
		//$method пример: v1/order/new
		$url = self::API_URL.$method;

		$data = $params;

		$data['request'] = '/'.$method;
		$data['nonce'] = strval(round(microtime(true) * 10,0));

		//print_r($data);die($url);
		$payload = base64_encode(json_encode($data));
		$signature = hash_hmac('sha384', $payload, $this->secret);

		$headers = array(
			'X-BFX-APIKEY: ' . $this->key,
			'X-BFX-PAYLOAD: ' . $payload,
			'X-BFX-SIGNATURE: ' . $signature
		);

		if(time() - $this->lastRequestDate < $this->requestIntervalMin)
			sleep($this->requestIntervalMin);


		if($this->ch === null)
		{
			$this->ch = curl_init();
			curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		}

		curl_setopt($this->ch, CURLOPT_URL, $url);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, "");

		$content = curl_exec($this->ch);

		$this->lastRequestDate =  time();
		$httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

		$result = json_decode($content, true);

		if($result and isset($result[0]) and $result[0]['status'] == 'error')
		{
			//ошибка при withdraw()
			$this->errorMsg = $result[0]['message'];
			$this->log(__CLASS__.' error request: '.$result['message'].' ('.Tools::arr2Str($data).')');
			return false;
		}
		elseif($content and  $httpCode == 400)
		{
			$this->errorMsg = $result['message'];
			$this->log(__CLASS__.' error request: '.$result['message'].' ('.Tools::arr2Str($data).')');
			return false;
		}
		elseif($content === false or  $httpCode != 200)
		{
			$this->log(__CLASS__.' error content: '.curl_error($this->ch).' httpCode: '.$httpCode);
			return false;
		}

		if(!$result)
		{
			$this->log(__CLASS__.' error json: '.$content);
			return false;
		}

		/*
		$notErrorArr = array(
			'no orders',
		);


		if($dec['success'] !== 1 and in_array($dec['error'], $notErrorArr)===false)
		{
			$this->log(__CLASS__.' error: '.$dec['error']);
			return false;
		}
		*/

		return $result;
	}

	private function publicRequest($method)
	{
		if (time() - $this->lastRequestDate < $this->requestIntervalMin)
			sleep($this->requestIntervalMin);

		$this->lastRequestDate = time();

		$url = self::API_URL.$method;

		$content = file_get_contents($url);

		if($result = json_decode($content, true))
			return $result;
		else
		{
			$this->log(__CLASS__.' error: json_decode, content='.$content);
			return false;
		}
	}

	/**
	 * получить минимальную сумму ордера по паре
	 * @param string $pair
	 * @return float
	 */
	public static function getOrderAmountMin($pair = self::PAIR_BTC_USD)
	{
		$data = array(
			self::PAIR_BTC_USD => 0.01,
		);

		return $data[$pair];
	}

	public function getOrderInfo($id)
	{
		$id *= 1;
		$response = $this->privateRequest('v1/order/status', array('order_id'=>$id));

		if($response)
			return $this->orderInfoReplace($response);
		else
			return false;
	}

	/**
	 * замена полей ордера на более понятные
	 *
	 * @param $response
	 * @return array|false
	 */
	private function orderInfoReplace($response)
	{
		if($response)
			return array(
				'id'=>$response['id'] * 1,
				'amount'=>$response['original_amount']*1,
				'price'=>$response['price']*1,
				'executed_amount'=>$response['executed_amount']*1,	//не работает у только что созданного ордера(првоерить)
				'timestamp'=>round($response['timestamp']),
			);
		else
			return false;
	}
	
	private function log($msg)
	{
		toLogStoreApi($msg);
	}

	public static function getCurrencies()
	{
		return array(
			self::CURRENCY_USD,
			self::CURRENCY_BTC,
		);
	}
}