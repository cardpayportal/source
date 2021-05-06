<?php
/**
 *
 * Class TestQiwi
 * @property int id
 * @property float amount
 * @property string currency
 * @property string status
 * @property string order_id
 * @property string error
 * @property int date_add
 * @property string phone
 * @property string url
 * @property int date_pay
 *
 */
class TestQiwi extends Model
{
	const SCENARIO_ADD = 'add';

	const STATUS_WAIT = 'wait';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{test_qiwi}}';
	}

	public function rules()
	{
		return [
			['amount, currency, status, order_id, error, date_add, date_pay,  phone, url', 'safe'],
		];
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel($params)
	{
		return self::model()->findByAttributes($params);
	}

	/**
	 * получение самой ссылки
	 * @param float $amount
	 * @param string $orderId
	 * @param string $phone
	 * @param string $successUrl
	 * @param string $failUrl
	 * @return string|false
	 */
	public static function generateMegakassaUrl($amount, $orderId, $phone, $successUrl, $failUrl)
	{
		$methodId = '22';

		if(!$orderId)
		{
			self::$lastError = 'не указан orderId';
			toLogError('MegaQiwiUrl error: '.self::$lastError);
			return false;
		}

		$cfg = cfg('newYandexPayMegakassa');

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->useCookie = true;
		$sender->cookieFile = DIR_ROOT.'protected/runtime/megakassa'.Tools::microtime().'.txt';

		//сразу зарегаем функцию удаления файла
		register_shutdown_function(function ($cookieFile) {
			@unlink($cookieFile);
		}, $sender->cookieFile);

		$sender->proxyType = $cfg['proxyType'];
		//test сделать разные
		$sender->browser = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.'.rand(36, 51);
		//test сделать разные
		$proxyModel = MegakassaProxyRequest::getProxy();

		if(!$proxyModel)
		{
			//debug
			//toLogError('MegaQiwiUrl: недостаточно прокси');
			return false;
		}

		$proxy = $proxyModel->str;

		$comment = 'Заказ #'.$orderId;

		$shopId = $cfg['shopId'];
		$amount	= number_format($amount, 2, '.', '');
		$currency = 'RUB'; // или "USD", "EUR"
		$description= htmlentities($comment, ENT_QUOTES, 'UTF-8');
		//test сделать разные
		$clientEmail = 'henrycavil'.rand(100, 1000).'@mail'.rand(100, 1000).'.com';
		$debug = $cfg['debug'];
		$secretKey = $cfg['secretKey'];
		$signature = md5($secretKey.md5(join(':', [$shopId, $amount, $currency, $description, $orderId, $methodId, $clientEmail, $debug, $secretKey])));
		$language = 'ru';

		$postData = [
			'shop_id' => $shopId,
			'amount' => $amount,
			'currency' => $currency,
			'description' => $description,
			'order_id' => $orderId,
			'method_id' => $methodId,
			'client_email' => $clientEmail,
			'client_phone' => $phone,
			'debug' => $debug,
			'signature' => $signature,
			'language' => $language,
		];

		$sender->additionalHeaders = [
			'Referer: https://megakassa.ru/merchant/',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
		];

		$content = $sender->send('https://megakassa.ru/merchant/', http_build_query($postData), $proxy);

		//если запрос прошел записываем в стату
		if($sender->info['httpCode'][0] == 200)
			MegakassaProxyRequest::addRequest($proxyModel->id);

		if(!preg_match('!name="agree" id="user_agree"!', $content))
		{
			if(YII_DEBUG)
				die('MegakassaUrl error1: '.$content);
			else
				toLogError('MegaQiwiUrl error1, httpCode='.$sender->info['httpCode'][0].', postData: '.Tools::arr2Str($postData));


			return false;
		}

		$postData = array_merge($postData, ['agree'=>'on']);

		$content = $sender->send('https://megakassa.ru/merchant/choose/1/', http_build_query($postData), $proxy);

		if(!$arr = @json_decode($content, true) or $arr['status'] !== 'ok')
			die('error2');

		if(!preg_match('!merchant/order/(\d+)/(\w+)!', $arr['redirect'], $res))
		{
			toLogError('MegaQiwiUrl error2');
			return false;
		}

		$megakassaOrderId = $res[1];
		$megakassaToken = $res[2];

		$url = 'https://megakassa.ru'.$arr['redirect'];

		$content = $sender->send($url, false, $proxy);

		//print_r($sender->info);
		//die($content);

		if($sender->info['httpCode'][0] !== 302)
		{
			toLogError('MegaQiwiUrl error3');

			//debug
			if(YII_DEBUG)
				die('MegakassaUrl error3: '.$content);

			return false;
		}

		if(!preg_match('!shop=(\d+)&transaction=(\w+)!', $sender->info['header'][0], $res))
		{
			toLogError('MegaQiwiUrl error5: '.$sender->info['header'][0]);
			return false;
		}

		$url = 'https://bill.qiwi.com/order/external/main.action/?shop='.$res[1].'&transaction='.$res[2].'&successUrl='.urlencode($successUrl).'&failUrl='.urlencode($failUrl);

		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->attributes = [
			'amount' => $amount,
			'currency' => $currency,
			'status' => self::STATUS_WAIT,
			'order_id' => $orderId,
			'date_add' => '',
			'phone' => $phone,
			'url' => $url,
		];

		if($model->save())
			return $url;
		else
			return false;
	}

	//test
	/**
	 * @param array $payParams
	 * @return bool true-если подтвердил или отклонил платеж(пометил статус error), false-если ошибка
	 */
	public static function confirmPaymentMegakassa($payParams)
	{
		$cfg = cfg('newYandexPayMegakassa');

		$secret_key	= $cfg['secretKey'];

		// нормализация данных
		$uid					= (int)$payParams["uid"];
		$amount					= (double)$payParams["amount"];
		$amount_shop			= (double)$payParams["amount_shop"];
		$amount_client			= (double)$payParams["amount_client"];
		$currency				= $payParams["currency"];
		$order_id				= $payParams["order_id"];
		$payment_method_id		= (int)$payParams["payment_method_id"];
		$payment_method_title	= $payParams["payment_method_title"];
		$creation_time			= $payParams["creation_time"];
		$payment_time			= $payParams["payment_time"];
		$client_email			= $payParams["client_email"];
		$status					= $payParams["status"];
		$debug					= (!empty($payParams["debug"])) ? '1' : '0';
		$signature				= $payParams["signature"];

		// проверка валюты
		if(!in_array($currency, array('RUB', 'USD', 'EUR'), true)) {
			toLogError('MegaQiwiConfirm error1: '.Tools::arr2Str($_REQUEST));
			return false;
		}

		// проверка статуса платежа
		if(!in_array($status, array('success', 'fail'), true)) {
			toLogError('MegaQiwiConfirm error2: '.Tools::arr2Str($_REQUEST));
			die('error');
		}

		// проверка формата сигнатуры
		if(!preg_match('/^[0-9a-f]{32}$/', $signature)) {
			toLogError('MegaQiwiConfirm error3: '.Tools::arr2Str($_REQUEST));
			die('error');
		}

		// проверка значения сигнатуры
		$signature_calc = md5(join(':', [
			$uid, $amount, $amount_shop, $amount_client, $currency, $order_id,
			$payment_method_id, $payment_method_title, $creation_time, $payment_time,
			$client_email, $status, $debug, $secret_key
		]));

		if($signature_calc !== $signature)
		{
			toLogError('MegaQiwiConfirm error signature: '.Tools::arr2Str($_REQUEST));
			die('error');
		}

		//метод киви
		if($payment_method_id != '22')
		{
			toLogError('MegaQiwiConfirm error method: '.Tools::arr2Str($_REQUEST));
			return false;
		}

		$model = self::model()->findByAttributes([
			'order_id' => $order_id,
			'amount' => $amount,
		]);

		/**
		 * @var self $model
		 */

		if(!$model)
		{
			toLogError('MegaQiwiConfirm error4: '.Tools::arr2Str($_REQUEST));
			return false;
		}

		if($model->status === self::STATUS_SUCCESS)
		{
			return true;
		}

		if($status === 'success')
		{
			$model->status = self::STATUS_SUCCESS;
			$model->error = '';
			$model->date_pay = time();

			if($model->save())
			{
				toLog('MegaQiwiConfirm: платеж order_id='.$model->order_id.' подтвержден');
				return true;
			}
			else
			{
				toLogError('MegaQiwiConfirm: платеж order_id='.$model->order_id.' не подтвержден');
				return false;
			}
		}
		elseif($status === 'fail')
		{
			$model->status = self::STATUS_ERROR;
			$model->error = 'fail';
			$model->date_pay = 0;

			if($model->save())
			{
				toLog('MegaQiwiConfirm: платеж order_id='.$model->order_id.' отклонен');

				return true;
			}
			else
			{
				toLogError('MegaQiwiConfirm: платеж order_id='.$model->order_id.' не подтвержден');
				return false;
			}

		}
		else
		{
			toLog('MegaQiwiConfirm: неверный статус');
			return false;
		}


	}

}

