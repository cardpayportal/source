<?php

/**
 * Class Kraken (Singleton)
 * todo: добавить функцию логирования
 *
 */
class Kraken
{
	const COMMISSION_TRADE = 0.002;	//0.2%
	const COMMISSION_WITHDRAW_BTC = 0.001;	//0.001btc

	const BTC_TRADE_MIN = 0.01;		//минимальная сумма при покупке btc
	const BTC_WITHDRAW_MIN = 0.005;	//минимальная сумма на вывод btc (на самом деле 0.002btc, но проверка идет после прибавки коммиссии)

	const REG_EXP_ADDRESS = '!^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$!';

	const ERROR_WITHDRAW_BALANCE = 1;	//недостаточно баланса для вывода

	const PAIR_BTC_USD = 'XXBTZUSD';

	const CURRENCY_BTC = 'XXBT';
	const CURRENCY_USD = 'ZUSD';

	const API_URL = 'https://api.kraken.com';


	private $key = '';
	private $secret = '';
	private $version = '0';

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

		if($this->ch === null)
		{
			$this->ch = curl_init();

			curl_setopt_array($this->ch, [
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_USERAGENT => 'Kraken PHP API Agent',
				CURLOPT_POST => true,
				CURLOPT_RETURNTRANSFER => true,
			]);
		}
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
	 * @return array|null|float
	 */
	public function getBalance($currency = null)
	{
		if($currency and in_array($currency, self::getCurrencies()) === false)
		{
			$this->errorMsg = 'неверная валюта: '.$currency;
			return null;
		}

		$method = 'Balance';

		$response = $this->privateRequest($method);

		if($response !== null)
		{
			$result = [];

			foreach($response as $currencyName=>$amount)
			{
				if($currencyName == $currency)
					return $amount * 1;
				else
					$result[$currencyName] = $amount * 1;
			}

			return $result;
		}
		else
		{
			$this->errorMsg = 'ошибка получения баланса '.$currency;
			$this->log($this->errorMsg);
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
		$method = 'Withdraw';

		$amount = $amount*1;

		$params = [
			'asset'=>$currency,
		];

		if($currency === self::CURRENCY_BTC)
			$amountMin = self::BTC_WITHDRAW_MIN;
		else
		{
			$this->errorMsg = 'неверная валюта для вывода: '.$currency;
			return false;
		}

		if($amount < $amountMin)
		{
			$this->errorMsg = "минимальная сумма для вывода: $amountMin $currency";
			$this->log($this->errorMsg);
			return null;
		}

		if(!preg_match(self::REG_EXP_ADDRESS, $address))
		{
			$this->log(__CLASS__.' error: неверный адрес '.$address);
			return false;
		}



		$params[''] = $address;

		//проверить хватит ли баланса
		$balance = $this->getBalance(self::CURRENCY_BTC);

		$amountWithCommission = $amount + self::COMMISSION_WITHDRAW_BTC;

		if($balance < $amountWithCommission)
		{
			$this->log(__CLASS__.' error: недостаточно баланса для вывода '.$balance.' '.$currency);
			return false;
		}

		$params['amount'] = str_replace(',', '.', $amount);

		$response = $this->privateRequest($method, $params);

		if($response and $response[0]['status'] == 'success')
		{
			//if($response['return']['amountSent'] !== $amount)
			//	$this->log(__CLASS__.' error: результат вывода('.Tools::arr2Str($response).') отличается от необходимого('.$amount.')');

			$this->log(__CLASS__.': вывод '.$amount.'btc на '.$address.', коммиссия '.$response[0]['fees'].' btc');

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
		$method = 'Ticker';

		$params = [
			'pair'=>$pair,
		] ;

		$response = $this->publicRequest($method, $params);

		if(is_array($response))
			return ($response[$pair]['c'][0])*1;
		else
			return null;
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

	private function privateRequest($method, array $params = array())
	{
		if (time() - $this->lastRequestDate < $this->requestIntervalMin)
			sleep($this->requestIntervalMin);

		if(!isset($params['nonce'])) {
			// generate a 64 bit nonce using a timestamp at microsecond resolution
			// string functions are used to avoid problems on 32 bit systems
			$nonce = explode(' ', microtime());
			$params['nonce'] = $nonce[1] . str_pad(substr($nonce[0], 2, 6), 6, '0');
		}

		// build the POST data string
		$postData = http_build_query($params, '', '&');

		// set API key and sign the message
		$path = '/' . $this->version . '/private/' . $method;
		$sign = hash_hmac('sha512', $path . hash('sha256', $params['nonce'] . $postData, true), base64_decode($this->secret), true);

		$headers = array(
			'API-Key: ' . $this->key,
			'API-Sign: ' . base64_encode($sign)
		);

		// make request
		curl_setopt($this->ch, CURLOPT_URL, self::API_URL. $path);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($this->ch);

		$this->lastRequestDate = time();

		if($result===false)
			throw new KrakenException('CURL error: ' . curl_error($this->ch));

		// decode results
		$result = json_decode($result, true);

		if(!is_array($result))
			throw new KrakenException('JSON decode error');

		if($result['error'])
		{
			$this->errorMsg = $result['error'][0];
			return null;
		}

		return $result['result'];
	}

	private function publicRequest($method, array $params = [])
	{
		if (time() - $this->lastRequestDate < $this->requestIntervalMin)
			sleep($this->requestIntervalMin);

        $postData = http_build_query($params, '', '&');

        // make request
        curl_setopt($this->ch, CURLOPT_URL, self::API_URL . '/' . $this->version . '/public/' . $method);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array());

		$result = curl_exec($this->ch);

		$this->lastRequestDate = time();

        if($result===false)
			throw new KrakenException('CURL error: ' . curl_error($this->ch));

        $result = json_decode($result, true);

        if(!is_array($result))
			throw new KrakenException('JSON decode error');



        return $result['result'];
	}

	public function getWithdrawInfo()
	{
		$method  = 'WithdrawInfo';

		$params = [
			'asset'=>self::CURRENCY_BTC,
			'key'=>'testtest',
			'amount'=>0.001,
		];

		$res = $this->privateRequest($method, $params);

		print_r($res);
		die($this->errorMsg);
	}
}

class KrakenException extends ErrorException {};