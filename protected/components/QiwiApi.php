<?php

/**
 * Class QiwiApi
 * @property string proxy
 * @property Sender _sender
 */
class QiwiApi extends CApplicationComponent
{
	const CURRENCY_RUB = 'RUB';
	const CURRENCY_KZT = 'KZT';

	const STATUS_ANONIM = 'anonim';
	const STATUS_HALF = 'half';
	const STATUS_FULL = 'full';

	const TRANS_TYPE_IN = 'in';
	const TRANS_TYPE_OUT = 'out';

	const TRANS_STATUS_WAIT = 'wait';
	const TRANS_STATUS_SUCCESS = 'success';
	const TRANS_STATUS_ERROR = 'error';

	const ERROR_BAN = 'ban';
	const ERROR_LIMIT_OUT = 'limit_out';	//превышен лимит исходящих транзакций
	const ERROR_NO_MONEY = 'not_enough_money';
	const ERROR_SEND_MONEY_TO_LIMIT = 'send_money_to_limit';
	const ERROR_PASSPORT_EXPIRED = 'passport_expired';
	const ERROR_PASSPORT_NOT_VERIFIED = 'not_verified';
	const ERROR_PASSPORT_MAX_COUNT = 'max_count';	//максимум заиденчено
	const ERROR_IDENT_CLOSED = 'ident_closed';	//закрыта идентификация в приложении

	const COMMISSION = 0.01; //минимальная комиссия
	const COMMISSION_FULL = 0.02; //максимальная комиссия

	const DOMAIN = 'edge.qiwi.com';




	public $token;
	public $login;	//79653243344


	public $proxy;
	public $lastResponse = '';	//последний ответ
	public $error = '';
	public $errorCode = '';

	protected $_sender;
	public $isCommission = false;
	public $timeout = 30;

	public $estmatedTransactions;	//заполняется на sendMoney() - последние полсанные но неизвестные платежи

	public function init()
	{
		$this->_sender = new Sender;
		$this->_sender->followLocation = false;
		$this->_sender->useCookie = false;
		$this->_sender->browser = '';
		$this->_sender->timeout = $this->timeout;
		$this->_sender->additionalHeaders = [
			'accept' => 'Accept: application/json',
			'contentType' => 'Content-Type: application/json',
		];

		$this->estmatedTransactions = [];
		//$this->_sender->pause = 1;
	}

	/**
	 * сопоставление валюты и цифрового кода из запросов
	 * @param string|bool $qiwiCurrency
	 * @return array|string
	 */
	public static function qiwiCurrencies($qiwiCurrency = false)
	{
		$arr = [
			self::CURRENCY_RUB => '643',
			self::CURRENCY_KZT => '398',
		];

		if($qiwiCurrency)
			return array_search($qiwiCurrency, $arr);
		else
			return $arr;
	}


	/**
	 * сопоставление валюты и строкового кода счета в кошельке
	 * @return array
	 */
	public function qiwiAccounts()
	{
		return [
			self::CURRENCY_RUB => 'qw_wallet_rub',
			self::CURRENCY_KZT => 'qw_wallet_kzt',
		];
	}

	public static function qiwiIdentStatuses()
	{
		return [
			'SIMPLE'=>self::STATUS_ANONIM,
			'VERIFIED'=>self::STATUS_HALF,
			'ANONYMOUS'=>self::STATUS_ANONIM,
			'FULL'=>self::STATUS_FULL,
		];
	}

	/**
	 * @param $url
	 * @param null $postData
	 * @return array|false
	 */
	protected function request($url, $postData=null)
	{
		if(!$this->token)
		{
			$this->error = 'token is empty';
			return false;
		}

		$this->_sender->additionalHeaders['authorization'] = "Authorization: Bearer {$this->token}";

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

	protected function requestBot($url, $postData=null)
	{
		//$this->_sender->additionalHeaders['authorization'] = "Authorization: Bearer {$this->token}";

		$this->lastResponse = $this->_sender->send($url, $postData, $this->proxy);

		if($arr = json_decode($this->lastResponse, true))
		{
			return $arr;
		}
		else
		{
			$this->error = 'error json decode response: '.$this->lastResponse.', httpCode = '
				.$this->_sender->info['httpCode'][0].$this->lastResponse;

			if($this->_sender->info['httpCode'][0] == 401)
				$this->errorCode = self::ERROR_BAN;

			return false;
		}
	}

	/**
	 * @param string $currency
	 * @return float|false
	 */
	public function getBalance($currency = self::CURRENCY_RUB)
	{
		$url = 'https://'.self::DOMAIN.'/funding-sources/v1/accounts/current';

		$result = $this->request($url);

		if($result === false)
			return false;

		if(!isset($result['accounts']))
		{
			$this->error = 'accounts not found in response: '.Tools::arr2Str($result);
			return false;
		}

		foreach($result['accounts'] as $account)
		{
			if($account['alias'] == self::qiwiAccounts()[$currency]
				and $account['currency'] == self::qiwiCurrencies()[$currency]
			)
				return $account['balance']['amount'];
		}


		//если нет счетв в указанной валюте то баланс 0
		return 0;

		//$this->error = 'unknown error: currency='.$currency.', response: '.Tools::arr2Str($result['accounts']);
		//return false;
	}


	/**
	 * todo: мультивалютность
	 * send money to Qiwi wallet RUB => RUB
	 * @param string $wallet
	 * @param float $amount
	 * @param string $comment
	 * @param string $currency
	 * @return float|false (amount sent)
	 */
	public function sendMoney($wallet, $amount, $comment = '', $currency = self::CURRENCY_RUB)
	{
		sleep(1); //пауза на случай слишком частых запросов

		$this->estmatedTransactions = [];

		$this->_sender->timeOut = 60;

		$amount = $this->getAmountForTransaction($amount);

		$transactionId = substr(Tools::microtime(), 0, 13);

		$fields = [
			'account' => '+'.trim($wallet, '+'),
		];

		if(preg_match(cfg('regExpYandexWallet'), $wallet))
		{
			$fields = [
				'requestProtocol'=>'qw::26476',
				'account'=>$wallet,
				'sinap-form-version'=>'qw::26476, 8',
			];
		}

		$url = 'https://'.self::DOMAIN.'/sinap/api/v2/terms/99/payments';
		$postArr = [
			'id'  => $transactionId,	//должен увеличиваться с каждым запросом и  быть строкой
        	'sum' => [
				'amount' => $amount,
				'currency' => self::qiwiCurrencies()[$currency],
			],
			'paymentMethod' => [
				'type' => 'Account',
				'accountId' => self::qiwiCurrencies()[$currency],
			],
			'comment' => $comment,
			'fields' => $fields,
		];


		//на время увеличим таймаут чтобы точно дождаться ответа
		$this->_sender->timeOut = 60;

		$result = $this->request($url, json_encode($postArr));

		$this->_sender->timeOut = $this->timeout;

		if(!isset($result['id']))
		{
			//потом почему то с них все таки переводится
			//if(preg_match('!Неверный пароль!isu', $result['message']))
			//	$this->errorCode = self::ERROR_BAN;


			$this->error = 'sendMoney: id not found in payment response: '.Tools::arr2Str($result).',  httpCode = '
				.$this->_sender->info['httpCode'][0];

			if(
				preg_match('!Кошелек временно заблокирован службой безопасности!ui', $result['message'])
				or
				preg_match('!Проведение платежа запрещено СБ!ui', $result['message'])
				or
				preg_match('!Ограничение на исходящие платежи!ui', $result['message'])
				or
				preg_match('!Персона заблокирована!ui', $result['message'])
			)
			{
				$this->errorCode = self::ERROR_BAN;
				$this->error = 'Ограничение на исходящие платежи';

			}
			elseif(preg_match('!Ежемесячный лимит платежей и переводов для статуса!ui', $result['message']))
			{
				$this->errorCode = self::ERROR_LIMIT_OUT;
				$this->error = 'Превышен лимит';
			}
			elseif(
				preg_match('!Недостаточно средств!ui', $result['message'])
				or
				preg_match('!Сумма платежа больше максимальной!ui', $result['message'])
			)
			{
				$this->errorCode = self::ERROR_NO_MONEY;
				$this->error = 'недостаточно средств(возможно комса)';
			}
			elseif(preg_match('!Платеж не проведен из-за ограничений у получателя!uis', $result['message']))
			{
				$this->errorCode = self::ERROR_SEND_MONEY_TO_LIMIT;
			}
			elseif($this->_sender->info['httpCode'][0] == 0)
			{
				$this->estmatedTransactions[] = [
					'id'=>$transactionId,
					'amount'=>$amount,
				];
			}
			elseif(preg_match('!Пул номеров страны не активен!uis', $result['message']))
			{
				toLog(Tools::arr2Str($postArr));
				toLog(Tools::arr2Str($result));
			}

			return false;
		}

		if($result['transaction']['state']['code'] == 'Accepted')
		{
			//toLog('Api sendMoney: +'.$this->login.' => '.$wallet.' ('.$amount.' '.$currency.'), trId:'.$result['id'].', myId: '.$transactionId);

			return $result['sum']['amount'];
		}
		else
		{
			$this->error = 'error payment response: '.Tools::arr2Str($result);
			return false;
		}

		//return $result;
	}

	/**
	 * @return array|false
	 */
	public function getProfile()
	{
		$url = 'https://'.self::DOMAIN.'/person-profile/v1/profile/current?authInfoEnabled=true&contractInfoEnabled=true&userInfoEnabled=true';
		$result = $this->request($url);

		if($result !== false)
		{
			if(isset($result['contractInfo']))
			{
				return $result;
			}
			else
			{
				$this->error = 'contractInfo not found in response: '.Tools::arr2Str($result);
				return false;
			}
		}

		return false;
	}

	/**
	 * статус идентификации
	 *
	 * @return string|false anonim|half|full
	 */
	public function getIdentStatus()
	{
		$profile = $this->getProfile();

		if($profile !== false)
		{
			$qiwiStatus = $profile['contractInfo']['identificationInfo'][0]['identificationLevel'];


			$statusArr = self::qiwiIdentStatuses();
			$status = $statusArr[$qiwiStatus];


			if(!isset($statusArr[$qiwiStatus]))
			{
				$this->error = 'unknown ident status: '.$status;
				return false;
				//todo: прописать все статусы
			}

			return $status;
		}
		else
			return false;
	}


	/**
	 * @param int $timestampStart если не указана то с сегодня 00:00
	 * @param int $timestampEnd если не указана то time() + 3600
	 * @return array|false	[
	 * 		'id'=>'',//ID платежа в киви
	 * 	'type'=>'',	//тип (in, out)
	 * 	'wallet'=>'', //кошелек
	 * 	'amount'=>'', //сумма включая комиссию
	 *  'commission'=>0,	//комиссия
	 *  'currency'=>'RUB|KZT', //этот элемент доступен только если включен $allCurrencies
	 * 	'comment'=>'', //коммент к платежу (cash - снятия с банкомата)
	 *
	 * 	'status'=>success,wait,error
	 * 	'timestamp'=>2137213612,//метка времени
	 * 	'date'=>22.12.2016 12:00,//форматированная дата
	 * 	'error'=>'',
	 * 	'errorCode'=>'',
	 * operationType
	 * amountFrom
	 * currencyFrom
	 * ]
	 * todo: 50 строк ограничение, начдо чтото придумать
	 */
	public function getHistory($timestampStart = null, $timestampEnd = null)
	{
		if($timestampStart === null)
			$timestampStart = strtotime(date('d.m.Y'));

		if($timestampEnd === null)
			$timestampEnd = time() + 3600;	//время на серверах может не сходиться лучше поставить на час в будущее

		$url = "https://".self::DOMAIN."/payment-history/v1/persons/"
			."{$this->login}/payments?rows=50&startDate=".urlencode(date('c', $timestampStart))
			."&endDate=".urlencode(date('c', $timestampEnd));

		$response = $this->request($url);

		if($response === false)
			return false;

		if(!isset($response['data']))
		{
			$this->error = 'data not found in response: '.Tools::arr2Str($response);
			return false;
		}

		$result = [];

		$transStatuses = [
			'WAITING' => self::TRANS_STATUS_WAIT,
			'HOLD' => self::TRANS_STATUS_WAIT,
			'SUCCESS' => self::TRANS_STATUS_SUCCESS,
			'ERROR' => self::TRANS_STATUS_ERROR,
		];

		foreach($response['data'] as $transQiwi)
		{
			$timestamp = strtotime($transQiwi['date']);

			$trans = [
				'id' => $transQiwi['trmTxnId'],
				'type' => ($transQiwi['type'] == 'IN') ? self::TRANS_TYPE_IN : self::TRANS_TYPE_OUT,
				'wallet' => ($transQiwi['account']) ? $transQiwi['account'] : $transQiwi['provider']['shortName'],
				'amount' => $transQiwi['sum']['amount'],
				'currency' => self::qiwiCurrencies($transQiwi['sum']['currency']),
				'commission' => $transQiwi['commission']['amount'],
				'comment' => $transQiwi['comment'],
				'status' => $transStatuses[$transQiwi['status']],
				'error' => $transQiwi['error'],
				'errorCode' => $transQiwi['errorCode'],
				'timestamp' => $timestamp,
				'date' => date('d.m.Y H:i', $timestamp),
			];

			if(!$trans['status'])
			{
				$this->error = 'error status in transaction: '.Tools::arr2Str($transQiwi);
				return false;
			}

			if($transQiwi['currencyRate'] != 1)
			{
				$trans['operationType'] = 'convert';
				$trans['amountFrom'] = floorAmount($transQiwi['currencyRate'] * $trans['amount'], 0);

				//если курс > 1 (к рублю) то это KZT
				$trans['currencyFrom'] = ($transQiwi['currencyRate'] > 1) ? self::CURRENCY_KZT : self::CURRENCY_RUB;
			}

			$result[] = $trans;
		}

		return $result;
	}

	/**
	 * сохранение персональных данных
	 *
	 * [
	 * 	'birthDate' => '',	Дата рождения пользователя (в формате “ГГГГ-ММ-ДД”)
	 * 	'firstName' => '',
	 * 	'middleName' => '', отчество
	 * 	'lastName' => '',	фамилия
	 * 	'passport' => '',	Серия и номер паспорта пользователя (только цифры)
	 * 	'inn' => '',
	 * 	'snils' => '',
	 * 	'oms' => '',
	 * 	(один из трех)
	 *
	 *]
	 * @param array $person = [
	 * ['passport']['series']
	 * ['passport']['number']
	 * ['issue']
	 * ['birth']
	 * ['snils']
	 * ]
	 *
	 * @return bool
	 */
	public function identify(array $person)
	{
		$url = 'https://'.self::DOMAIN.'/identification/v1/persons/'.$this->login.'/identification';

//		$personArr['passport']['series'] = $personArr['doc_series'];
//		$personArr['passport']['number'] = $personArr['doc_number'];
//		$personArr['issue'] = $personArr['date_issue'];
//		$personArr['birth'] = $personArr['date_birth'];

		if(isset($person['snils']))
		{
			$snils = substr($person['snils'], 0, 3).'-'
			.substr($person['snils'], 3, 3)
			.'-'.substr($person['snils'], 6, 3)
			.' '.substr($person['snils'], 9, 2);
		}
		else
			$snils = '';

		$params = [
			'birthDate'=>date('Y-m-d', strtotime($person['birth'])),
			'firstName'=>mb_ucfirst($person['first_name'], 'utf-8'),
			'middleName'=>mb_ucfirst($person['third_name'], 'utf-8'),
			'lastName'=>mb_ucfirst($person['second_name'], 'utf-8'),
			'passport'=>$person['passport']['series'].$person['passport']['number'],
			'inn'=>'',
			'snils'=>$snils,
			'oms'=>'',
		];

		$postData = json_encode($params);
		$response = $this->request($url, $postData);

		if($response['type'] == 'VERIFIED')
		{
			return true;
		}
		else
		{
			if($response['type'] == 'SIMPLE')
				$this->errorCode = self::ERROR_PASSPORT_NOT_VERIFIED;
			elseif(isset($response['errorCode']) and $response['errorCode'] == 'bad.client.software')
				$this->errorCode = self::ERROR_IDENT_CLOSED;
			else
			{
//			if(preg_match('!Паспорт с этим номером недействителен!ui', $content))
//				$this->errorCode = self::ERROR_PASSPORT_EXPIRED;
//
//			if(preg_match('!По этим данным можно идентифицировать не больше 5 кошельков!ui', $content))
//				$this->errorCode = self::ERROR_PASSPORT_MAX_COUNT;
			}

			$this->error = 'ident error : '.Tools::arr2Str($response).' (code: '.$this->_sender->info['httpHeader'][0].')';

			return false;
		}
	}

	/**
	 * @param float $amountFrom
	 * @param string $currencyFrom
	 * @param string $currencyTo
	 * @param float $rate курс KZT_RUB (подается извне)
	 * @param string $comment
	 * @return bool
	 */
	public function convert($currencyFrom, $currencyTo, $amountFrom, $rate, $comment = '')
	{
		sleep(1); //пауза на случай слишком частых запросов

		$amountTo = $this->getConvertAmount($amountFrom, $rate);

		if($amountTo === false)
			return false;

		$url = 'https://'.self::DOMAIN.'/sinap/api/v2/terms/99/payments';

		$postArr = [
			'id'  => (time() * 1000).'',	//должен увеличиваться с каждым запросом и  быть строкой
			'sum' => [
				'amount' => $amountTo,
				'currency' => self::qiwiCurrencies()[$currencyTo],
			],
			'paymentMethod' => [
				'type' => 'Account',
				'accountId' => self::qiwiCurrencies()[$currencyFrom],
			],
			'comment' => $comment,
			'fields' => [
				'account' => $this->login,
			],
		];

		$result = $this->request($url, json_encode($postArr));

		if(!isset($result['id']))
		{
			//потом почему то с них все таки переводится
			//if(preg_match('!Неверный пароль!isu', $result['message']))
			//	$this->errorCode = self::ERROR_BAN;

			$this->error = 'convert: id not found in payment response: '.Tools::arr2Str($result).', httpCode = '
		.$this->_sender->info['httpCode'][0];
			return false;
		}

		if($result['transaction']['state']['code'] == 'Accepted')
		{
			return $result['sum']['amount'];
		}
		else
		{
			$this->error = 'error payment response: '.Tools::arr2Str($result);
			return false;
		}
	}

	/**
	 * получить сумму рублей на конвертацию $amountKzt
	 * @param float $amountKzt
	 * @param float $rate
	 * @return float|false
	 */
	public function getConvertAmount($amountKzt, $rate)
	{
		if(!$rate or !$amountKzt)
			return false;

		$result =  floorAmount($rate * $amountKzt, 2);

		return $result;
	}

	public function getLastPayments($dayCount = 0, $allCurrencies=false)
	{
		$dateFrom = time() - 3600 * 24 * $dayCount;
		return $this->getHistory(Tools::startOfDay($dateFrom));
	}


	/**
	 * статус валидации, работает по новой ссылке
	 * @param bool $withCache
	 * @return string|bool
	 */
	public function getStatus($withCache = false)
	{
		return $this->getIdentStatus();
	}

	public function getAmountForTransaction($amount)
	{
		if(!$this->isCommission)
			return $amount;

		if($this->isCommission == '1')
			$commission = self::COMMISSION;
		elseif($this->isCommission == '2')
			$commission = self::COMMISSION_FULL;
		else
			$commission = 0;

		//toLog('до комиссии: '.$amount);

		$amount = round($amount / (1 + $commission), 2);

		//toLog('после комиссии: '.$amount);

		return $amount;
	}

	/*
	 * прибавляет комиссию к $amount
	 */
	public function getAmountWithCommission($amount)
	{
		if(!$this->isCommission)
			return $amount;

		if($this->isCommission == '1')
			$commission = self::COMMISSION;
		elseif($this->isCommission == '2')
			$commission = self::COMMISSION_FULL;
		else
			$commission = 0;

		return round($amount * (1 + $commission), 2);
	}

}