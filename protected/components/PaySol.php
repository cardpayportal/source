<?php

/**
 * Class Paysol
 */
class Paysol
{
	/**
	 * Merchant ID
	 * @var string
	 */
	private $id;
	/**
	 * Token to access API
	 * @var string
	 */
	private $token;

	/**
	 * Private key to verify IPN
	 * @var string
	 */
	private $privateKey;

	/**
	 * API base url
	 * @var string
	 */
	private $baseUrl = 'https://senses.paymaster.name';

	/**
	 * If true, API will request Post Echo
	 * @var bool
	 */
	private $testMode = false;

	/**
	 * Paysol constructor.
	 * @param $id
	 * @param $token
	 * @param $privateKey
	 */
	public function __construct($id, $token, $privateKey)
	{
		$this->token = $token;
		$this->id = $id;
		$this->privateKey = $privateKey;
	}

	/**
	 * Creates order on payment
	 * @param $amount
	 * @param $orderId
	 * @param $description
	 * @param $notificationUrl
	 * @param $successUrl
	 * @param $failUrl
	 * @param $cardNumber
	 * @param $cardExpirationDate
	 * @param $cardHolderName
	 * @param $cardCVV
	 * @return ArrayObject
	 * @throws Exception
	 */
	public function createOrder($amount, $orderId, $description, $notificationUrl, $successUrl, $failUrl, $cardNumber,
		$cardExpirationDate, $cardHolderName, $cardCVV, $toCard) {
		return $this->request('POST', '/order',
			[
				'amount' => (int) $amount,
				'orderId' => $orderId,
				'description' => $description,
				'notificationUrl' => $notificationUrl,
				'successUrl' => $successUrl,
				'failUrl' => $failUrl,
				'cardNumber' => $cardNumber,
				'cardExpirationDate' => $cardExpirationDate,
				'cardHolderName' => $cardHolderName,
				'cardCVV' => $cardCVV,
				'toCard' => $toCard
			]
		);
	}

	/**
	 * Returns order info
	 * @param $id
	 * @return mixed
	 * @throws Exception
	 */
	public function getOrder($id) {
		return $this->request('GET', '/order/' . $id, []);
	}

	/**
	 * Requests API
	 * @param $method string POST or GET
	 * @param $endpoint
	 * @param $data
	 * @return mixed
	 * @throws Exception
	 */
	private function request($method, $endpoint, $data) {
		$ch = curl_init();

		if ($ch === false) {
			throw new Exception('failed to initialize');
		}

		if ($this->testMode) {
			curl_setopt($ch, CURLOPT_URL, 'https://postman-echo.com/post');
		} else {
			curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);
		}

		if ($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS,  http_build_query($data, '', '&'));
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'token: ' . $this->token,
			'id: ' . $this->id
		]);

		$result = curl_exec($ch);

		if ($result === false) {
			throw new Exception(curl_error($ch), curl_errno($ch));
		}

		curl_close($ch);
		echo $result;
		return json_decode($result);
	}
}