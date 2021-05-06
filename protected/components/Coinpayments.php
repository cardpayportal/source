<?php
/**
 * Class Coinpayments (Singleton)
 *
 */
class Coinpayments
{
	const REG_EXP_ADDRESS = '!^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$!';
	const API_URL = 'https://www.coinpayments.net/api.php';

	const ERROR_NO_MONEY = 'noMoney';				//недостаточно на счету для вывода
	const ERROR_WRONG_CURRENCY = 'wrongCurrency';	//неверная валюта(возможно не в списке разрешенных)
	const ERROR_WRONG_PARAMS = 'wrongQueryParams';	//неверные параметры запроса
	const ERROR_NOT_AVAILABLE = 'notAvailable';		//метод недоступен(в текущей системе)

	const WITHDRAW_COMMISSION = 0.001;
	const WITHDRAW_STATUS_DONE = 'done';
	const WITHDRAW_STATUS_WAIT = 'wait';
	const WITHDRAW_STATUS_ERROR = 'error';

	const CURRENCY_BTC = 'BTC';

	private $_key = '';
	private $_secret = '';
	//пауза между запросами
	public $requestInterval = 1;
	private $_lastRequestDate = 0;

	private $_ch = null;

	public $errorMsg = '';
	public $errorCode = '';

	#public $withdrawPriority = 'medium';	//влияет на комсу (low|medium|high)

	private static $_instance = null;	//для приватных запросов
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
	 * @param string $key
	 * @param string $secret
	 * @return self|false
	 */
	public static function getInstance($key, $secret)
	{
		if($key and $secret)
		{
			if(is_null(self::$_instance))
				self::$_instance = new self($key, $secret);

			return self::$_instance;
		}
		else
			return false;
	}

	/**
	 * @param string $method
	 * @param array $params
	 * @return array|bool
	 */
	private function _request($method, array $params = array())
	{
		$this->errorCode = '';
		$this->errorMsg = '';

		//$method пример: v1/order/new
		$url = self::API_URL;

		// Set the API command and required fields
		$params['version'] = 1;
		$params['cmd'] = $method;
		$params['key'] = $this->_key;
		$params['format'] = 'json'; //supported values are json and xml

		$postData = http_build_query($params, '', '&');

		$hash = hash_hmac('sha512', $postData, $this->_secret);


		if($this->_ch === null)
		{
			$this->_ch = curl_init();
			curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->_ch, CURLOPT_URL, $url);
			curl_setopt($this->_ch, CURLOPT_POST, true);
		}

		curl_setopt($this->_ch, CURLOPT_HTTPHEADER, array('HMAC: '.$hash));
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $postData);

		if(time() - $this->_lastRequestDate < $this->requestInterval)
			sleep($this->requestInterval);

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

		if($result['error'] != 'ok')
		{
			$this->errorMsg = $result['error'];
			return false;
		}

		return $result['result'];
	}

	private function _log($msg)
	{
		toLogStoreApi('TEST: '.$msg);
	}


	/**
	 * баланс текущего апи ключа (каждый ключ к своей валюте)
	 * @param string|null $currency если null то выдает массив
	 * @return array|false|float
	 */
	public function getBalance($currency = self::CURRENCY_BTC)
	{
		$method = 'balances';

		$response = $this->_request($method);

		if($response !== false)
		{
			if($currency)
			{
				if(!isset(self::getAllowedCurrencies()[$currency]))
				{
					$this->errorCode = self::ERROR_WRONG_CURRENCY;
					return false;
				}

				if(isset($response[$currency]['balancef']))
					$result = $response[$currency]['balancef'] * 1;
				else
				{
					$this->errorMsg = 'не найдена валюта в ответе: '.$currency;
					return false;
				}
			}
			else
			{
				$result = [];

				foreach($response as $currency=>$arr)
					$result[$currency] = $arr['balancef'] * 1;
			}

			return $result;
		}
		else
		{
			$this->_log('ошибка получения баланса: '.$this->errorMsg);
			return false;
		}

	}

	/**
	 * отослать коин на адрес
	 * @param string $address
	 * @param float $amount
	 * @param string $currency
	 * @return string|false id транзакции в сети
	 */
	public function withdraw($address, $amount, $currency)
	{
		$method = 'create_withdrawal';

		$amount = $amount*1;

		if(!preg_match(self::REG_EXP_ADDRESS, $address))
		{
			$this->errorCode = self::ERROR_WRONG_PARAMS;
			$this->errorMsg = 'неверный адрес';
			return false;
		}

		if(!isset(self::getAllowedCurrencies()[$currency]))
		{
			$this->errorCode = self::ERROR_WRONG_PARAMS;
			$this->errorMsg = 'неверныая валюта';
			return false;
		}

		$currencyInfo = self::getAllowedCurrencies()[$currency];

		if($amount < $currencyInfo['minWithdraw'])
		{
			$this->errorCode = self::ERROR_WRONG_PARAMS;
			$this->errorMsg = "минимальная сумма для вывода: {$currencyInfo['minWithdraw']}";
			return false;
		}

		$params = [
			'amount'=>$amount,
			'currency'=>$currency,
			'address'=>$address,
			'auto_confirm'=>'1',	//без подтверждения по мылу

		];

		$response = $this->_request($method, $params);
		/*
		 * [
		 * 		'id'=>id in Coinpayment,
		 * 		'status'=>0|1,	1 - вывод отправлен, 0 - требует подтверждения
		 * 		'amount'=>0.005,
		 * ]
		 */

		if($response !== false)
		{
			if($params['status'] == '1')
			{
				$this->_log(__CLASS__.': вывод '.$response['amount'].' '
					.$params['currency'].' на '.$params['address'].', коммиссия '
					.($amount - $response['amount']).' BTC, BTC rate: '.config('storeApiBtcRate'));

				return $params['id'];
			}
		}
		else
			return false;
	}

	/**
	 * комиссия за вывод
	 * @param string $currency
	 * @return float|false
	 */
	public function getWithdrawCommission($currency)
	{
		$currencyInfo = self::getAllowedCurrencies();

		if(!isset($currencyInfo[$currency]))
			return $currencyInfo[$currency]['withdrawCommission'];
		else
		{
			$this->errorCode = self::ERROR_WRONG_CURRENCY;
			return false;
		}
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
	 * последние выводы
	 * @param string|bool $currency
	 * @return array|false
	 * [
	 *		[
	 * 			'id'=>'внутренний id',
	 * 			'currency'=>'BTC|...',
	 * 			'txId'=>'id в сети',
	 * 			'address'=>'',
	 * 			'amount'=>'сколько дошло получателю',
	 * 			'status'=>'done|wait|error',
	 * 		]
	 *	]
	 */
	public function getWithdrawList($currency = false)
	{
		$method = 'get_withdrawal_history';

		if($currency !== false and !isset(self::getAllowedCurrencies()[$currency]))
		{
			$this->errorCode = self::ERROR_WRONG_CURRENCY;
			return false;
		}

		$params = [
			'limit'=>100,
		];

		$response = $this->_request($method, $params);

		if(is_array($response))
		{
			$result = [];

			foreach($response as $item)
			{
				if($currency !== false and $item['coin'] !== $currency)
					continue;

				$status = ($item['status'] === 2)
					? self::WITHDRAW_STATUS_DONE
					: (($item['status'] === 1) ? self::WITHDRAW_STATUS_WAIT : self::WITHDRAW_STATUS_ERROR);

				$result[] = [
					'currency'=>$item['coin'],
					'id'=>$item['id'],
					'txId'=>$item['send_txid'],
					'address'=>$item['send_address'],
					'amount'=>$item['amountf']*1,
					'status'=>$status,
				];
			}

			return $result;
		}
		else
		{
			$this->errorMsg = 'ошибка получения списка выводов: '.$this->errorMsg;
			return false;
		}
	}

	/**
	 * @param string $currency
	 * @return array|bool
	 */
	public function getAddresses($currency)
	{
		$method = 'get_deposit_address';

		if(!isset(self::getAllowedCurrencies()[$currency]))
		{
			$this->errorCode = self::ERROR_WRONG_CURRENCY;
			return false;
		}

		$params = [
			'currency' => $currency,
		];

		$response = $this->_request($method, $params);

		if($response)
		{
			$result = [];

			foreach($response as $arr)
				$result[] = $arr['address'];

			return $result;
		}
		else
			return false;
	}

	/**
	 * получение нового адреса
	 * при этом self::getAddresses() начнет выдавать именно последний полученный
	 * @return string|bool новый адрес
	 */
	public function getNewAddress()
	{
		$this->errorCode = self::ERROR_NOT_AVAILABLE;
		$this->errorMsg = 'метод недоступен';
		return false;
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

	/**
	 * информация по валютам
	 * @return array
	 */
	public static function getAllowedCurrencies()
	{
		return [
			self::CURRENCY_BTC => ['name'=>'BTC', 'minWithdraw'=>0.002, 'withdrawCommission'=>0.001],
		];
	}

}