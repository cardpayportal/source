<?php

/**
 * Class WexBot (Singleton)
 * работает на паре btc_usd
 */
class WexBot
{
	const COMMISSION_TRADE = 0.002;	//0.2%
	const COMMISSION_WITHDRAW_BTC = 0.001;	//0.001btc

	const BTC_TRADE_MIN = 0.002;		//минимальная сумма при покупке btc
	const BTC_WITHDRAW_MIN = 0.001;	//минимальная сумма на вывод btc (на самом деле 0.002btc, но проверка идет после прибавки коммиссии)
	const CODE_CREATE_MIN = 0.01;

	const REG_EXP_ADDRESS = '!^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$!';

	const ERROR_WITHDRAW_BALANCE = 1;	//недостаточно баланса для вывода

	private $key = '';
	private $secret = '';

	//пауза между запросами
	private $requestIntervalMin = 1;
	private $lastRequestDate = 0;

	private $ch = null;

	//todo: реализовать ошибки и коды ошибок
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

	public function __destruct()
	{
		if(isset($this->ch))
			curl_close($this->ch);
	}

	private function privateRequest($method, array $params = array())
	{
		if(time() - $this->lastRequestDate < $this->requestIntervalMin)
			sleep($this->requestIntervalMin);

		$params['method'] = $method;
		$params['nonce'] = time();

		$postData = http_build_query($params, '', '&');

		$sign = hash_hmac('sha512', $postData, $this->secret);

		$headers = array(
			'Sign: '.$sign,
			'Key: '.$this->key,
		);

		if($this->ch === null)
		{
			$this->ch = curl_init();
			curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; BTCE PHP client; Unix; PHP/'.phpversion().')');
			curl_setopt($this->ch, CURLOPT_URL, 'https://btc-e.com/tapi/');
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		}

		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postData);

		$res = curl_exec($this->ch);
		$this->lastRequestDate =  time();

		if($res === false)
		{
			toLogStoreApi('Btce error: Could not get reply: '.curl_error($this->ch));
			return false;
		}


		$dec = json_decode($res, true);

		if(!$dec)
		{
			toLogStoreApi('Btce error: Invalid data received, please make sure connection is working and requested API exists');
			return false;
		}

		$notErrorArr = array(
			'no orders',
		);

		if($dec['success'] !== 1 and in_array($dec['error'], $notErrorArr)===false)
		{
			toLogStoreApi('Btce error: '.$dec['error'].' postData: '.$postData);
			return false;
		}

		return $dec;
	}

	private function publicRequest($url)
	{
		if (time() - $this->lastRequestDate < $this->requestIntervalMin)
			sleep($this->requestIntervalMin);

		$this->lastRequestDate = time();

		$content = file_get_contents($url);

		if($result = json_decode($content, true))
			return $result;
		else
		{
			toLogStoreApi('Btce error: json_decode '.$content);
			return false;
		}
	}

	/**
	 * баланс валюты
	 * @param string $currency
	 * @return array|false|float
	 */
	public function getBalance($currency = '')
	{
		$method = 'getInfo';

		$response = $this->privateRequest($method);

		if($response !== false)
		{
			$funds = $response['return']['funds'];

			if(!$currency)
				return $funds;

			if(isset($funds[$currency]))
				return $funds[$currency];
			else
			{
				toLogStoreApi('Btce error: currency not found');
				return false;
			}
		}
		else
		{
			toLogStoreApi('ошибка получения баланса btc');
			return false;
		}

	}

	/**
	 * покупка btc за usd (цена вычисляется автоматически)
	 * возвращает сумму купленого btc
	 * @param float $amount
	 * @return float|bool
	 * todo:  использовать isEnoughMoney вместо проверки баланса
	 */
	public function buyBtc($amount)
	{
		//отменить все текущие ордера
		if($this->cancelAllOrders())
		{
			//вычисляем курс
			$price = $this->getPriceByAmount($amount);

			if($price)
			{
				$balance = $this->getBalance('usd');

				if($this->isEnoughMoney('buyBtc', $amount, $balance, $price))
				{
					if($price and $amount >= self::BTC_TRADE_MIN)
					{
						$buyAmount = $this->buyBtcUsd($amount, $price);

						if($buyAmount !== false)
						{
							if($buyAmount !== ceilAmount($amount * 1.002, 8))	//ебучий бтце вычитает комсу из купленного битка и никому не говорит
								toLogStoreApi('Btce error: купленная сумма не сходится: amount='.$amount.', buyAmount='.$buyAmount);

							return $buyAmount;
						}
						else
						{
							toLogStoreApi('Btce error: ошибка покупки btc: amount='.$amount.', price='.$price);
							return false;
						}
					}
					else
					{
						toLogStoreApi('Btce error: check price ('.$price.') or amount('.$amount.')');
						return false;
					}
				}
				else
				{
					toLogStoreApi('Btce error: недостаточно баланса для покупки btc: amount='.$amount.', price='.$price.', balance='.$balance);
					return false;
				}


			}
			else
			{
				toLogStoreApi('Btce error: ошибка получения цены на сумму '.$amount.' btc');
				return false;
			}
		}
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
			$price = $asks[0][0];

			foreach($asks as $key=>$ask)
			{
				$amountSum += $ask[1];

				if($amountSum >= $amount)
				{
					$price = $ask[0];
					break;
				}
			}
		}
		else
			return false;

		return $price;
	}

	/**
	 *	покупка btc по определенной цене
	 * @param float $amount
	 * @param float $price
	 * @return float|bool
	 */
	private function buyBtcUsd($amount, $price)
	{
		//покупаем на 0.002  больше(комиссия вычитается из купленного битка)
		$response = $this->privateRequest('Trade', array(
			'pair'=>'btc_usd',
			'type'=>'buy',
			'rate'=>str_replace(',', '.', $price),
			'amount'=>str_replace(',', '.', ceilAmount($amount * 1.002, 8))
		));

		if($response !== false)
		{
			$result = $response['return']['received'];

			if($result > 0)
				toLogStoreApi('Btce: покупка '.$amount.' btc за '.$price.' USD, комиссия: '.ceilAmount($amount * 0.002, 8));

			if($response['return']['order_id'])
			{
				sleep(10);

				//удаление ордера
				$this->cancelOrder($response['return']['order_id']);
			}

			return $result;
		}
		else
			return false;
	}


	/**
	 * получить стакан на продажу
	 * @return array|bool
	 */
	private function getAsks()
	{

		$array = $this->publicRequest('https://btc-e.com/api/2/btc_usd/depth');

		if($array)
			return $array['asks'];
		else
			return false;
	}

	/**
	 * @return array|bool
	 */
	private function getActiveOrders()
	{
		$result = $this->privateRequest('ActiveOrders', array('pair'=>'btc_usd'));

		if($result['success'])
			return $result['return'];
		elseif($result['error'] === 'no orders')
			return array();
		else
			return false;
	}

	/**
	 * @param $id
	 * @return bool
	 */
	private function cancelOrder($id)
	{
		$result = $this->privateRequest('CancelOrder', array('order_id'=>$id));

		if($result['success'])
		{
			toLogStoreApi('Btce: отмена ордера: '.$id);
			return true;
		}
		else
		{
			toLogStoreApi('Btce error: error cancel order: '.$id);
			return false;
		}

	}

	/**
	 * @return bool
	 */
	private function cancelAllOrders()
	{
		$activeOrders = $this->getActiveOrders();

		if($activeOrders !== false)
		{
			foreach($activeOrders as $id=>$order)
			{
				if(!$this->cancelOrder($id))
					return false;
			}

			return true;
		}
		else
			return false;
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
		if($operationType == 'buyBtc')
		{
			if($balance !==false)
			{
				$amount = $amount*1;

				if($price === false)
					$price = $this->getPriceByAmount($amount);

				if($amount > 0 and $price > 0)
				{
					$cost = $amount * $price + $amount * $price * self::COMMISSION_TRADE;
				}
				else
				{
					toLogStoreApi('Btce error: неверный amount  или price isEnoughMoney 1: amount: '.$amount.', price: '.$price);
					return null;
				}
			}
			else
			{
				toLogStoreApi('Btce error: неверный $balance при расчете isEnoughMoney1');
				return null;
			}
		}
		elseif($operationType == 'withdrawBtc')
		{
			if($balance !== false)
			{
				$amount = $amount * 1;

				if($amount > 0)
				{
					$cost = $amount + self::COMMISSION_WITHDRAW_BTC;
				}
				else
				{
					toLogStoreApi('Btce error: неверный amount isEnoughMoney 2: '.$amount);
					return null;
				}
			}
			else
			{
				toLogStoreApi('Btce error: неверный $balance при расчете isEnoughMoney2');
				return null;
			}
		}
		else
		{
			toLogStoreApi('Btce error: неверный тип isEnoughMoney: '.$operationType);
			return null;
		}

		return $balance >= $cost;
	}

	/**
	 * отослать btc на адрес
	 * @param float $amount
	 * @param string $address
	 * @return bool
	 */
	public function withdrawBtc($amount, $address)
	{
		$method = 'WithdrawCoin';

		$amount = $amount*1;

		if($amount >= self::BTC_WITHDRAW_MIN)
		{
			if(preg_match(self::REG_EXP_ADDRESS, $address))
			{
				$amountWithCommission = $amount + self::COMMISSION_WITHDRAW_BTC;

				$response = $this->privateRequest($method, array(
					'coinName'=>'btc',
					'amount'=>str_replace(',', '.', $amountWithCommission),
					'address'=>$address,
				));

				if($response !== false)
				{

					if($response['return']['amountSent'] !== $amount)
						toLogStoreApi('Btce error: результат вывода('.$response['return']['amountSent'].') отличается от необходимого('.$amount.')');

					toLogStoreApi('Btce: вывод '.$amount.'btc на '.$address.', коммиссия '.self::COMMISSION_WITHDRAW_BTC.' btc, Last Price: '.config('btc_usd_rate_btce'));

					return true;
				}
				else
					return false;
			}
			else
			{
				toLogStoreApi('Btce error: неверный адрес '.$address);
				return false;
			}
		}
		else
		{
			toLogStoreApi('Btce error: минимальная сумма для вывода: '.self::BTC_WITHDRAW_MIN.' btc');
			return false;
		}
	}

	/**
	 * создание BTCE_USD кода
	 * @param $amount
	 * @return string|bool
	 */
	public function createCode($amount)
	{
		$method = 'CreateCoupon';

		$amount = $amount * 1;

		if($amount < self::CODE_CREATE_MIN)
		{
			$this->errorMsg = 'минимальная сумма для createCode: '.self::CODE_CREATE_MIN;
			return false;
		}

		$balance = $this->getBalance('usd');

		if($balance < $amount)
		{
			$this->errorCode =
			$this->errorMsg = 'недостаточно баланса для createCode';
			return false;
		}

		$response = $this->privateRequest($method, array(
			'currency'=>'usd',
			'amount'=>str_replace(',', '.', $amount),
		));

		if($response !== false)
			return $response['return']['coupon'];
		else
			return false;
	}

	/**
	 * активация BTCE_USD кода
	 * @param string $code
	 * @return bool
	 */
	public function redeemCode($code)
	{
		$method = 'RedeemCoupon';

		$response = $this->privateRequest($method, array(
			'coupon'=>$code,
		));

		if($response !== false)
			return true;
		else
			return false;
	}

	public function getLastPrice($pair = 'btc_usd')
	{
		$url = 'https://btc-e.com/api/2/'.$pair.'/ticker';

		$response = $this->publicRequest($url);

		if(is_array($response))
			return $response['ticker']['last'];
		else
			return false;
	}

}