<?php

//namespace App;

//use GuzzleHttp\Client;
//use GuzzleHttp\Exception\ServerException;

class Mts
{
	const PAY_FORM_URL = 'https://payment.mts.ru/';
	const PAY_URL = 'https://payment.mts.ru/payment/pay';
	const COMMISSION_URL = 'https://payment.mts.ru/payment/payform/GetCommissions';

	protected $headers = [
		'Content-Type' => 'application/x-www-form-urlencoded; charset=utf8',
		'Referer' => 'https://moskva.mts.ru',
	];

	protected $http;
	protected $phone;
	/**
	 * Пополнение телефона мтс или теле2
	 * @var string
	 */
	protected $type = 'tele2'; // mts, tele2

	/**
	 * Mts constructor.
	 * @param string $phone номер телефона без + (79001234567)
	 * @param string $proxy строка проскси http://docs.guzzlephp.org/en/stable/request-options.html#proxy
	 */
	public function __construct($phone, $proxy = '')
	{
		$config = [
			'cookies' => true
		];
		if ($proxy) {
			$config['proxy'] = $proxy;
		}
		$this->http = new Client($config);
		$this->setPhone($phone);
	}

	/**
	 * @return string
	 */
	public function getPhone()
	{
		return $this->phone;
	}

	/**
	 * @param string $phone
	 */
	public function setPhone($phone)
	{
		$this->phone = $phone;
	}

	public function pay($amount, array $data, $term_url = '')
	{
		$pay_form_url = $this->getPayFormUrl($amount);
		$signature = $this->getSignature($pay_form_url);
		sleep(2);
		$parameters_name = $this->isMts() ? 'NUMBER' : 'id1';
		$pay_name = $this->getPayName();
		$data_commission = [
			'ProviderId' => $this->getProviderId(),
			'PaymentSumMin' => 10,
			'PaymentToken' => '',
			'PaymentSumMax' => 15000,
			'IsZeroCommision' => 'False',
			'Parameters[0].Type' => 'PhoneField',
			'Parameters[0].Name' => $parameters_name,
			'Parameters[0].Val1' => $this->getPhone(),
			'SelectedInstrumentId' => 'ANONYMOUS_CARD',
			'Pan' => wordwrap($data['card'], 4, ' ', true),
			'ExpiryMonth' => '',
			'ExpiryYear' => '',
			'CardholderName' => '',
			'Cvc' => '',
			'__RequestVerificationToken' => $signature,
			'Sum' => number_format($amount, 2, ',', ''),
			'Name' => $pay_name
		];

		$headers = ['X-Requested-With' => 'XMLHttpRequest'] + $this->headers;

		$response = $this->http->request('post', self::COMMISSION_URL, [
			'form_params' => $data_commission,
			'timeout' => 20,
			'headers' => $headers,
		]);
		$commission = json_decode($response->getBody()->getContents(), true);

		sleep(2);

		// я так понимаю проверка на карту мтс, не обязательно, что будет если карта мтс - не знаю, возможно метод оплаты не сработает, т.к. что-то меняется
		/*$response = $this->http->request('post', 'https://payment.mts.ru/bankrequest/IsMtsBin', [
			'form_params' => [
				'bin' => mb_substr($data['card'], 0, 8),
				'__RequestVerificationToken' => $signature
			],
			'timeout' => 10,
			'headers' => $headers,
		]);
		$bin_response = json_decode($response->getBody()->getContents(), true);*/

		$post_data = [
			'ProviderId' => $this->getProviderId(),
			'PaymentSumMin' => 10,
			'PaymentToken' => '',
			'PaymentSumMax' => 15000,
			'IsZeroCommision' => 'False',
			'Parameters[0].Type' => 'PhoneField',
			'Parameters[0].Name' => $parameters_name,
			'Parameters[0].Val1' => $this->getPhone(),
			'SelectedInstrumentId' => 'ANONYMOUS_CARD',
			'Pan' => wordwrap($data['card'], 4, ' ', true),
			'ExpiryMonth' => $data['month'],
			'ExpiryYear' => $data['year'],
			'CardholderName' => mb_strtolower($data['username']),
			'Cvc' => $data['cvv'],
			'__RequestVerificationToken' => $signature,
			'Sum' => $commission['sumWithVat'],
			'Location' => $pay_form_url . (mb_strpos($pay_form_url, '?') !== false ? '#_3dsecure_info' : ''),
			'Name' => $pay_name
		];

		$content = $this->makePay($post_data, $headers);

		if (!empty($content['error']) || !isset($content['type']) || $content['type'] !== 'FINISH_3DS') {
			// тут может быть лог
			$message = 'Ошибка платежа';
			if (in_array($content['error'], ['20998', '37'])) {
				$message = 'Извините, платеж не был совершен';
			}
			throw new Exception($message);
		}
		$params = $content['model'];
		return [
			'form' => $this->getACSForm($params, $term_url),
			'target' => '_self',
			'params' => $params,
			'term_url' => $this->getTermUrl($params)
		];
	}

	public function setHeaders(array $headers)
	{
		$this->headers = $headers + $this->headers;
	}

	public function isMts()
	{
		return $this->isMts();
	}

	protected function makePay(array $post_data, array $headers, $count = 0)
	{
		$response = $this->http->request('post', self::PAY_URL, [
			'form_params' => $post_data,
			'timeout' => 40,
			'headers' => $headers,
		]);
		// от них бывает 500 ответ, толи ограничения по запросам, толи еще что. Можно пробовать делать рекурсию с уменьшенным таймаутом, но помоему не было результата
		/*try {
		} catch (ServerException $e) {
			sleep(rand(4, 10));
			if ($count > 2) {
				throw new ServerException($e->getMessage(), $e->getRequest(), $e->getResponse(), $e->getPrevious(), $e->getHandlerContext());
			}
			return $this->makePay($post_data, $headers, ++$count);
		}*/
		return json_decode($response->getBody()->getContents(), true);
	}

	protected function getACSForm(array $params, $term_url = '')
	{
		$term_url = $this->getTermUrl($params, $term_url);
		$pareq = $this->getUpdatedPareq($params['paReq']);
		return <<<EOT
                    <form id="_3d-secure-form" method="post" action="{$params['acsUrl']}" >
                        <input type="hidden" name="PaReq" value="{$pareq}" />
                        <input type="hidden" name="MD" value="{$params['md']}" />
                        <input type="hidden" name="MdOrder" value="{$params['mdOrder']}" />
                        <input type="hidden" name="TermUrl" value="{$term_url}">
                        <noscript>
                            <br>
                            <br>
                            <div style="text-align:center;">
                                <h1>Processing your 3-D Secure Transaction</h1>
                                <h2>
                                JavaScript is currently disabled or is not supported
                                by your browser.<br></h2>
                                <h3>Please click Submit to continue
                                the processing of your 3-D Secure
                                transaction.</h3>
                                <button type="submit">Submit</button>
                            </div>
                        </noscript>
                    </form>
                    <script>document.getElementById('_3d-secure-form').submit()</script>
EOT;
	}

	protected function getUpdatedPareq($pareq)
	{
		$output = null;
		$ret = null;
		exec('python3.7 /opt/pareq_mt.py "' . $pareq . '" 2>&1', $output, $ret);
		if ((int) $ret === 0) {
			return $output[0] ? $output[0] : $pareq;
		}
		return $pareq;
	}

	protected function getTermUrl(array $params, $url = '')
	{
		return ($url ?: 'https://payment.mts.ru/verified3ds') . '?' . http_build_query($this->getQueryData($params));
	}

	protected function getQueryData($params)
	{
		$query_data = [
			'MdOrder' => $params['mdOrder'],
			'MD' => $params['md'],
			'type' => 1,
		];
		if (isset($params['bindCard']) && $params['bindCard']) {
			$query_data['bindCard'] = true;
		}
		if (isset($params['tmplName']) && $params['tmplName']) {
			$query_data['tmplName'] = $params['tmplName'];
		}

		return $query_data;
	}

	protected function getSignature($pay_form_url)
	{
		$response = $this->http->get($pay_form_url, [
			'timeout' => 20,
			'headers' => $this->headers,
		]);
		if ($response->getStatusCode() !== 200) {
			throw new \Exception('Failed connect');
		}
		$content = $response->getBody()->getContents();
		$matches = [];
		preg_match('|name="__RequestVerificationToken".+value="([^"]+)|', $content, $matches);
		if (empty($matches[1])) {
			throw new \Exception("Failed to get signature m");
		}
		return $matches[1];
	}

	protected function getPayFormUrl($amount)
	{
		$query_data = [
			'number' => $this->getPhone(),
			'amount' => $amount,
			'PaymentForm' => $this->getProviderId(),
			'bt' => 'RU',
			'source' => 'mts',
			'channel' => 1
		];

		return rand(0, 1) ? self::PAY_FORM_URL . '?' . http_build_query($query_data) : self::PAY_FORM_URL . '/pay/' . $this->getProviderId();
	}

	protected function getProviderId()
	{
		return $this->isMts() ? 1150 : 5920;
	}

	protected function getPayName()
	{
		return $this->isMts() ? 'МТС' : 'Теле2';
	}
}
