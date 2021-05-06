<?php
/**
 * @property Sender $_sender
 *
 * пример создания:
 *
 */
class WalletSBot
{

	const PAYMENT_DIRECTION_IN = 'in';
	const PAYMENT_DIRECTION_OUT = 'out';

	const PAYMENT_STATUS_SUCCESS = 'success';
	const PAYMENT_STATUS_WAIT = 'wait';
	const PAYMENT_STATUS_ERROR = 'error';

	private $_merchantKey = '';
	private $_merchantSign = '';
	private $_sender = null;	//Sender
	private $_requestInfo = array();	//Sender
	private $_lastContent = '';	//Sender
	private $_proxy;
	private $_config; //переменная с текущими конфигами
	private $_isTest; //флаг тестового запуска

	public $errorMsg = '';
	public $errorCode = '';
	public $baseUrl = '';

	public function __construct($isTest=false)
	{
		$this->_isTest = $isTest;

		if($isTest)
		{
			$this->_config = Yii::app()->getModule('walletS')->config["testParams"];
		}
		else
		{
			$this->_config = Yii::app()->getModule('walletS')->config["devParams"];
		}

		$this->_merchantKey = $this->_config['merchant_key'];
		$this->_merchantSign = $this->_config['merchant_sign'];

		$this->_sender = new Sender();
		$this->_sender->browser = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:66.0) Gecko/20100101 Firefox/66.0';
		$this->_sender->followLocation = false;
		$this->_sender->pause = 1;
		$this->_sender->timeout = 30;
		$this->_proxy = $this->_config['proxy'];
		$this->_sender->proxyType = $this->_config['proxyType'];
	}

	private function _request($url, $postData = false, $referer = '')
	{

		$this->_lastContent = $this->_sender->send($url, $postData, $this->_proxy, $referer);

		$this->_requestInfo = $this->_sender->info;

		return $this->_lastContent;
	}

	/**
	 * @return true|false|null (успех|неудача|ошибка запроса-повторить)
	 *
	 * Создание инвойса
	 * входные параметры: массив $params [
	  	merchant_key string	Ключ магазина
		order_nr string Номер заказа
		amount integer Сумма платежа без центов. Запрос для получения суммы платежа в зависимости от валюты описано в пункте 1.1
		currency string Валюта товара или услуги
		payer_email string E-mail плательщика
		payer_name string Имя плательщика
		payer_lname string Фамилия плательщика
		lang string Язык интерфейса
		description string Описание платежа
		success_url string Ссылка переадресации пользователя при успешном платеже
		success_method string Метод для переадресации пользователя при успешном платеже GET|POST
		cancel_url string Ссылка переадресации пользователя при ошибочном платеже
		cancel_method string Метод для переадресации пользователя при ошибочном платеже GET|POST
		callback_url string Ссылка для ответа о статусе платежа
		callback_method string Метод отстука GET|POST
		hash Подпись запроса md5(“​merchant_key|order_nr|amount|currency|​​merc hant_sign”)​
	 * ]
	 *
	 * пример
	 * $params = [
		"order_nr" => "123123",
		"amount" => "100",
		"currency" => "USD",
		"payer_email" => "",
		"payer_name" => "IVAN",
		"payer_lname" => "IVANOV",
		"lang"=> "RU",
		"description" => "payment_123123",
		"success_url" => "https://www.google.com/",
		"success_method" => "GET",
		"cancel_url" => "https://www.ya.ru/",
		"cancel_method" => "GET",
		"callback_url" => "https://www.google.com/",
		"callback_method" => "POST",
		];
	 *
	 * возвращает массив
	 *
	 * array(4)
	 * {
	 * 		["invoice_id"]=> string(16) "FQBg8FHFCzAmalQH"
	 *  	["invoice_url"]=> string(64) "https://sand.walletesvoe.com/processing/invoice/FQBg8FHFCzAmalQH"
	 *  	["error_code"]=> string(0) ""
	 *  	["error_msg"]=> string(0) ""
	 * }
	 */
	public function createInvoice($params=[])
	{
		$url = $this->_config['baseUrl'].'/invoice/create';

		$this->_sender->additionalHeaders = null;

		$this->_sender->additionalHeaders = [
			'Content-Type: application/json',
		];

		$params["currency"] = $this->_config['currency'];
		$params["lang"] = $this->_config['lang'];
		$params["success_method"] = $this->_config['success_method'];
		$params["cancel_method"] = $this->_config['cancel_method'];
		$params["callback_url"] = $this->_config['callback_url'];
		$params["callback_method"] = $this->_config['callback_method'];
		$params["amount"] = $params["amount"] * 100;
		$params['merchant_key'] = $this->_merchantKey;
		$params['hash'] = md5($this->_merchantKey.'|'.$params["order_nr"].'|'.$params["amount"].'|'.$params["currency"].'|'.$this->_merchantSign);

//		var_dump($params['hash']);die;

		$contentJson = $this->_request($url, json_encode($params));
		$content = json_decode($contentJson, 1);

		if($content['response'] == true)
		{
			return [
				'invoice_id' => $content['invoice_id'],
				'invoice_url' => $content['invoice_url'],
				'error_code' => $content['error_code'],
				'error_msg' => $content['error_msg'],
			];
		}
		elseif(preg_match('!IP not whitelisted!', $contentJson, $matches))
		{
			return [
				'error_code' => 1,
				'error_msg' => 'IP not whitelisted '.$this->_config['proxy'].' for '.$this->_config['baseUrl'],
			];
		}
		elseif($content['response'] == false)
		{
			return [
				'error_code' => $content['error_code'],
				'error_msg' => $content['error_msg'],
				'content' => $this->_isTest ? $contentJson : '',
			];
		}
		elseif($this->_requestInfo['httpCode'][0] == 302)
		{
			return false;
		}
		elseif($this->_requestInfo['httpCode'][0] == 200)
		{
			$this->errorMsg = 'walletS createInvoice contentLength='.strlen($content);
			return false;
		}
		else
		{
			$this->errorMsg = 'walletS createInvoice code='.$this->_requestInfo['httpCode'][0];
			return null;
		}
	}

	/**
	 * Запрос для получения суммы платежа
	 *
	 * насколько понял у них все в USD, если подгонять под рубли нужно предварительно расчитать этим запросом сумму
	 *
	 * $currency string
	 * $value string
	 */
	public function getSum($currency, $value)
	{
		$url = $this->_config['baseUrl'].'/round';

		$this->_sender->additionalHeaders = null;

		$this->_sender->additionalHeaders = [
			'Content-Type: application/json',
		];

		$contentJson = $this->_request($url, json_encode(['currency'=>$currency, 'value'=>$value]), $this->_proxy);

		$content = json_decode($contentJson, 1);

		if($content['response'] == true)
		{
			return [
				'value' => $content['value'],
			];
		}
		elseif($content['response'] == false)
		{
			return [
				'error_code' => $content['error_code'],
				'error_msg' => $content['message'],
				'content' => $this->_isTest ? $contentJson : '',
			];
		}
		elseif($this->_requestInfo['httpCode'][0] == 302)
		{
			return false;
		}
		elseif($this->_requestInfo['httpCode'][0] == 200)
		{
			$this->errorMsg = 'walletS getSum contentLength='.strlen($content);
			return false;
		}
		else
		{
			$this->errorMsg = 'walletS getSum code='.$this->_requestInfo['httpCode'][0];
			return null;
		}
	}

}