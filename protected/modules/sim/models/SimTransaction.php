<?php

/**todo: сделать однопоточную проверку и получение банк параметров
 * todo: поставить таск для проверки заявок на фон а не по запросу
 * @property int id
 * @property int account_id		когда акк гуляет по юзерам чтобы стата от одного не переносилась к другому,
 * стата идет по client_id, user_id
 * @property int client_id		платеж может прикрепляться к клиенту (user_id=0)
 * @property int user_id		может прикрепляться к пользвателю и к клиенту (user_id>0)
 * @property float amount
 * @property string currency
 * @property string status
 * @property string error
 * @property string comment
 * @property int date_add   дата добавления
 * @property Client client
 * @property User user
 * @property string amountStr
 * @property string statusStr
 * @property string order_id	номер заказа на стороне клиента(для возможной сверки отдельных платежей руками)
 * @property int date_pay	дата оплаты
 * @property string pay_params	json данные карты (возможно зашифрованные)
 * @property string pay_url 	ссылка на оплату
 * @property SimAccount account
 * @property string hash
 * @property int client_order_id	передает клиент
 * @property string success_url переброс на сайт клиента после успешной оплаты
 * @property string fail_url	переброс на сайт клиента при неудаче
 * @property string payment_type	тип платежки: yandex, mts, ...
 * @property string proxy	прокси для статы
 * @property array payParams
 * @property string dateAddStr
 * @property string datePayStr
 * @property string bank_url
 * @property string card_number
 * @property string bot_id
 */
class SimTransaction extends Model
{
	const SCENARIO_ADD = 'add';
	const SCENARIO_WITHDRAW = 'withdraw';

	const STATUS_SUCCESS = 'success';
	const STATUS_WAIT = 'wait';
	const STATUS_ERROR = 'error';

	const PAYMENT_TYPE_YANDEX = 'yandex';
	const PAYMENT_TYPE_MTS = 'api';
	const PAYMENT_TYPE_TELE2 = 'tele2';
	const PAYMENT_TYPE_A3 = 'a3';
	const PAYMENT_TYPE_MEGAFON = 'megafon';

	//оплата через форму ввода карты либо напрямую редирект в банк
	const PAYMENT_METHOD = 'bank'; //form|bank

	//test
	const MTS_METHOD = 'api';	//curl|selenium|api
	const YANDEX_METHOD = 'selenium';	//curl|selenium
	const TELE2_METHOD = 'curl';	//curl|selenium
	const A3_METHOD = 'curl';	//curl|selenium

	const CURRENCY_RUB = 'RUB';

	const AMOUNT_MIN = 10;	//tele2:100, mts:10
	const AMOUNT_MAX = 3000;
	const PAYMENT_TYPE_DEFAULT = 'mts';

	const ERROR_BAN = 'ban';
	const ERROR_WAIT = 'wait';

	const CANCEL_TIMEOUT = 1800;	//автоотмена через

	public $transAmount = 0;	//при подсчете суммы успешных платежей

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{sim_transaction}}';
	}

	public function rules()
	{
		return [
			['account_id', 'exist', 'className'=>'SimAccount', 'attributeName'=>'id',
				'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD],
			['client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id',
				'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD],
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id',
				'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD],
			['amount', 'numerical', 'min'=>self::AMOUNT_MIN, 'max'=>self::AMOUNT_MAX, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],
			['amount', 'numerical', 'min'=>-999999, 'max'=>0, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_WITHDRAW],
			['currency', 'in', 'range'=>array_keys(self::getCurrencyArr())],
			['status', 'in', 'range'=>array_keys(self::getStatusArr())],
			['error', 'length', 'max'=>255],
			['comment', 'length', 'max'=>100],
			['date_add', 'numerical', 'max'=>time()+3600],
			['order_id', 'length', 'max'=>255],
			['pay_params', 'length', 'max'=>30000],
			['hash', 'length', 'max'=>255],
			['client_order_id', 'numerical', 'min'=>1, 'max'=>999999999, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],
			['client_order_id', 'clientOrderIdValidator', 'on'=>self::SCENARIO_ADD],
			['success_url', 'url', 'message'=>'неверный successUrl','on'=>self::SCENARIO_ADD],
			['fail_url', 'url', 'message'=>'неверный failUrl', 'on'=>self::SCENARIO_ADD],
			['payment_type', 'in', 'range'=>array_keys(self::getPaymentTypeArr())],
			['proxy', 'length', 'max'=>100],
			['bot_id', 'safe'],
		];
	}

	public function amountValidator()
	{
		if(!$user = User::getUser($this->user_id) or $user->client_id != $this->client_id)
			$this->addError('login', 'Ошибка проверки на уникальность: '.self::$lastError);
	}

	//соответствие юзера клиенту
	public function userValidator()
	{
		if(!$user = User::getUser($this->user_id) or $user->client_id != $this->client_id)
			$this->addError('login', 'Ошибка проверки на уникальность: '.self::$lastError);
	}

	public function clientOrderIdValidator()
	{
		if(self::getModel(['user_id'=>$this->user_id, 'client_order_id'=>$this->client_order_id]))
			$this->addError('client_order_id', 'этот orderId уже использовался');
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			if(!$this->date_add)
				$this->date_add = time();

			$this->hash = md5($this->order_id.Tools::generateCode('dsfbfksdf934532', 8));
		}

		$this->error = strip_tags($this->error);
		$this->comment = strip_tags($this->comment);

		return parent::beforeSave();
	}

	/**
	 * @return array
	 */
	public static function getCurrencyArr()
	{
		return [
			self::CURRENCY_RUB => 'руб',
		];
	}

	/**
	 * @return array
	 */
	public static function getStatusArr()
	{
		return [
			self::STATUS_SUCCESS => 'завершен',
			self::STATUS_WAIT => 'ожидание',
			self::STATUS_ERROR => 'ошибка',
		];
	}

	/**
	 * @return Client
	 */
	public function getClient()
	{
		return Client::getModel($this->client_id);
	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		return User::getUser($this->user_id);
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	/**
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param int $clientId
	 * @param int $userId
	 * @param string $type (successIn|in)
	 * @return  self[]
	 */
	public static function getModels($timestampStart, $timestampEnd, $clientId=0, $userId = 0, $type='')
	{
		$timestampStart *= 1;
		$timestampEnd *= 1;
		$clientId *= 1;
		$userId *= 1;

		$cond =  [];

		$cond[] = "`date_add` >= $timestampStart AND `date_add` < $timestampEnd";

		if($clientId)
			$cond[] = "`client_id` = '$clientId'";

		if($userId)
			$cond[] = "`user_id` = '$userId'";

		if($type == 'in')
			$cond[] = "`amount` > 0";

		if($type == 'successIn')
			$cond[] = "`amount` > 0 AND `status`='".SimTransaction::STATUS_SUCCESS."'";


		return self::model()->findAll([
			'condition' => implode(" AND ", $cond),
			'order' => "`date_add` DESC",
		]);
	}


	public function getAmountStr()
	{
		$result = formatAmount($this->amount, 2);

		return $result.' '.$this->currency;
	}

	public function getStatusStr()
	{
		return self::getStatusArr()[$this->status];
	}

	/**
	 * статистика по платежам за период
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param int $clientId
	 * @param int $userId
	 * @return array
	 */
	public static function getStats($timestampStart, $timestampEnd, $clientId = 0, $userId = 0)
	{
		$result = [
			'amountWait' => 0,	//поступления в ожидании
			'amountIn' => 0,	//успешных поступлений
			'amountOut' => 0,
		];

		$models = self::getModels($timestampStart, $timestampEnd, $clientId, $userId, 'in');

		foreach($models as $model)
		{
			if($model->status === self::STATUS_SUCCESS)
			{
				$result['amountSuccess'] += $model->amount;

				if($model->amount > 0)
					$result['amountIn'] += $model->amount;
				elseif($model->amount < 0)
					$result['amountOut'] += $model->amount;
			}
			elseif($model->status === self::STATUS_WAIT and $model->amount > 0)
				$result['amountWait'] += $model->amount;
		}

		$result['amountOut'] = abs($result['amountOut']);

		//if(YII_DEBUG)
		//	prrd($result);

		return $result;
	}

	/**
	 * генерация уникального order_id заданной длины
	 * @param int $length
	 * @return int|false
	 */
	public static function generateOrderId($length = 16)
	{
		$code = false;

		//ограниченное число попыток на генерацию
		for($i=1; $i<=1000; $i++)
		{
			$code = Tools::generateCode('123456789', $length);

			if(!self::getModel(['order_id'=>$code]))
				break;
		}

		return $code;
	}

	/**
	 * генерация уникального client_order_id заданной длины
	 * @param int $length
	 * @param int $clientId
	 * @return int|false
	 */
	public static function generateClientOrderId($length = 16, $clientId)
	{
		$code = false;

		//ограниченное число попыток на генерацию
		for($i=1; $i<=1000; $i++)
		{
			$code = Tools::generateCode('123456789', $length);

			if(!self::getModel(['client_order_id'=>$code, 'client_id'=>$clientId]))
				break;
		}

		return $code;
	}

	/**
	 * ссылка  на форму для оплаты
	 * @param int $userId
	 * @param int $amount
	 * @param int $clientOrderId
	 * @param string $successUrl
	 * @param string $failUrl
	 * @param string $paymentType
	 * @param string $phone			для отладки
	 * @return bool|string
	 */
	public static function getPayUrl($userId, $amount, $clientOrderId, $successUrl = ''
		, $failUrl = '', $paymentType = self::PAYMENT_TYPE_DEFAULT, $phone='')
	{
		$config = Yii::app()->getModule('sim')->config;

		$user = User::getUser($userId);

		if(!$user)
		{
			self::$lastError = 'юзер не найден';
			return false;
		}

		$clientOrderId *= 1;

		if(!$clientOrderId)
			$clientOrderId = self::generateClientOrderId(9, $user->client_id);

		$client = $user->client;

		if(!$client->pick_accounts)
		{
			self::$lastError = 'выдача реквизитов отключена';

			//toLogError('выдача реквизитов отключена '.$client->name);
			return false;
		}

		$amount = floor(preg_replace('!\s!', '', $amount));

		$orderId = self::generateOrderId(16);

		//test
		if($phone)
			$simAccount = SimAccount::getModel(['login'=>$phone]);
		else
			$simAccount = SimAccount::getWallet($amount, $user->id);

		if(!$simAccount)
		{
			self::$lastError = 'техническая ошибка 3';
			self::log('нет кошельков у '.$user->client->name);
			return false;
		}

		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->account_id = $simAccount->id;
		$model->client_id = $client->id;
		$model->user_id = $user->id;
		$model->amount = $amount;
		$model->currency = self::CURRENCY_RUB;
		$model->status = self::STATUS_WAIT;
		$model->order_id = $orderId;
		$model->client_order_id = $clientOrderId;
		$model->success_url = $successUrl;
		$model->fail_url = $failUrl;
		$model->payment_type = $paymentType;

		$urlTpl = $config['payUrlTpl'];
		$model->pay_url = str_replace(['{orderId}'], [$model->order_id], $urlTpl);

		if($model->save())
		{
			self::$someData['orderId'] = $model->client_order_id;
			return $model->pay_url;
		}
		else
			return false;
	}

	/**
	 * @return SimAccount
	 */
	public function getAccount()
	{
		return SimAccount::getModel(['id'=>$this->account_id]);
	}


	/**
	 * @param array $params
	 * [
	 *		'cardNumber' => '',
	 *		'cardM' => '',
	 *		'cardY' => '',
	 *		'browser' => '',
	 *		'headers' => '',
	 *		'phoneNumber' => '9026353545',
	 *		'amount' => '',
	 *		'orderid' => '',
	 * ]
	 * @return array ['url'=>'', 'postArr'=>[]]
	 */
	private static function getBankUrlYandex($params)
	{
		if(self::YANDEX_METHOD == 'selenium')
			return self::getBankUrlYandexSelenium($params);

		$config = Yii::app()->getModule('sim')->config;

		$params['amount'] *= 1;

		if(!$params['proxy'])
		{
			self::log('нет прокси');
			return [];
		}

		//test
		//$params['browser'] = 'Mozilla/5.0 (X11; Linux i686; rv:63.0) Gecko/20100102 Firefox/63.0';

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');

		$sender = new Sender;
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser']).'.txt';
		$sender->pause = 1;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		$sender->browser = $params['browser'];
		//$sender->additionalHeaders = $params['headers'];

		//1) запрос на главную страницу
		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$url = 'https://money.yandex.ru/phone';
		$content = $sender->send($url, false, $params['proxy']);

		if(!preg_match('!teamHash \= "(.+?)"!', $content, $match))
			return self::cardError('error1', $params, $sender, 'error1 code: '.$sender->info['httpCode'][0]);

		$teamHash = $match[1];

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Pragma: no-cache',
			'Connection: keep-alive',
			'Cache-Control: no-cache',
			'Referer: https://money.yandex.ru/phone',
		];
		//1.1)запрос на https://money.yandex.ru/ajax/makeupd/client-js-monitoring

		$postArr = [
			'url' => 'https://money.yandex.ru/phone',
			'stacktrace' => 'bc</o.componentDidMount/<@https://money.yandex.ru/layout-service/static/portal/client.ru.c2a576cf88f7b1ccff88.js:59:582685',
			'userAgent' => $params['browser'],
			'teamHash' => trim($teamHash),
		];

		$url = 'https://money.yandex.ru/ajax/makeupd/client-js-monitoring';

		$sender->send($url, http_build_query($postArr), $params['proxy']);

		if($sender->info['httpCode'][0] !== 200)
			return self::cardError('error1.1', $params, $sender, 'error1.1 code: '.$sender->info['httpCode'][0]);

		//2) инфа об операторе
		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://money.yandex.ru/phone',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$url = 'https://money.yandex.ru/ajax/efos/get-phone-info?phone='.$params['phoneNumber'];
		$content = $sender->send($url, false, $params['proxy']);

		if(!$operatorArr = json_decode($content, true) or $operatorArr['status'] != 'success')
			return self::cardError('error2', $params, $sender, 'error2 code: '.$sender->info['httpCode'][0].', content: '.$content);



		//3)информация о комсе
		$url = 'https://money.yandex.ru/ajax/efos/get-payment-options?shopId='.$operatorArr['showcase']['shopId']
		.'&shopArticleId='.$operatorArr['showcase']['shopArticleId'].'&paymentTypeList=%5B%22AC%22%2C%22PC%22%5D&netSum='.$params['amount'];
		$content = $sender->send($url, false, $params['proxy']);

		if(!$commissionArr = json_decode($content, true) or $commissionArr['status'] != 'success')
			return self::cardError('error3', $params, $sender, 'error3 code: '.$sender->info['httpCode'][0].', content: '.$content);


		$totalAmount = $commissionArr['data']['totalSum'];


		//4)отсылаем начальную форму

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://money.yandex.ru/phone',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$phonePart1 = substr($params['phoneNumber'], 0, 3);
		$phonePart2 = substr($params['phoneNumber'], 3);

		$postData = http_build_query([
			'customerNumber' => $phonePart1.'+'.$phonePart2,
			'netSum' => $params['amount'],
			'scid' => $operatorArr['showcase']['scid'],
			'sum' => $totalAmount,
			'PROPERTY1' => $phonePart1,
			'PROPERTY2' => $phonePart2,
			'from-efos-showcase' => '',
		]);

		$url = 'https://money.yandex.ru/select-wallet.xml';
		$content = $sender->send($url, $postData, $params['proxy']);

		if(!preg_match('!Location: (.+)!', $sender->info['header'][0], $match))
			return self::cardError('error4', $params, $sender, 'error4 code: '.$sender->info['httpCode'][0].', content: '.$content);


		//5)переброс на форму карты по 302 от предыдущего запроса
		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: https://money.yandex.ru/phone',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		//https://money.yandex.ru/payments/internal?customerNumber=901%203548060&netSum=12&scid=928&sum=12.24&PROPERTY1=901&PROPERTY2=3548060&from-efos-showcase=
		$url = trim($match[1]);

		$content = $sender->send($url, false, $params['proxy']);

		if(!preg_match('!any-card\?orderId=(.+?)"!', $content, $match))
			return self::cardError('error5.1', $params, $sender, 'error5.1 code: '.$sender->info['httpCode'][0]);

		$orderId = $match[1];

		if(!preg_match('!"sk":"(.+?)"!', $content, $match))
			return self::cardError('error5.2', $params, $sender, 'error5.2 code: '.$sender->info['httpCode'][0]);

		$sk = $match[1];

		if(!preg_match('!teamHash \= "(.+?)"!', $content, $match))
			return self::cardError('error5.3', $params, $sender, 'error5.3 code: '.$sender->info['httpCode'][0]);

		$teamHash = $match[1];

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Pragma: no-cache',
			'Connection: keep-alive',
			'Cache-Control: no-cache',
			'Referer: https://money.yandex.ru/payments/internal/confirmation?orderId='.$orderId,
		];

		//5.1)запрос на https://money.yandex.ru/ajax/makeupd/client-js-monitoring

		$postArr = [
			'url' => 'https://money.yandex.ru/payments/internal/confirmation?orderId='.$orderId,
			'stacktrace' => 'bc</o.componentDidMount/<@https://money.yandex.ru/layout-service/static/portal/client.ru.c2a576cf88f7b1ccff88.js:59:582685',
			'userAgent' => $params['browser'],
			'teamHash' => trim($teamHash),
		];

		$url = 'https://money.yandex.ru/ajax/makeupd/client-js-monitoring';

		$sender->send($url, http_build_query($postArr), $params['proxy']);

		if($sender->info['httpCode'][0] !== 200)
			return self::cardError('error5.1.1', $params, $sender, 'error5.1.1 code: '.$sender->info['httpCode'][0]);

		//todo: разобраться с clear.png

		//6)получение синонима для карты
		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'Origin: https://money.yandex.ru',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://money.yandex.ru/',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$postData = json_encode([
			'cardholder' => 'CARD HOLDER',
			'csc' => $params['cardCvv'],
			'pan' => $params['cardNumber'],
			'expireDate' => '20'.$params['cardY'].$params['cardM'],
		]);

		$url = 'https://paymentcard.yamoney.ru/webservice/storecard/api/storeCardForPayment';
		$content = $sender->send($url, $postData, $params['proxy']);

		if(!$arr = json_decode($content, true) or !$arr['result']['cardSynonym'])
			return self::cardError('error6', $params, $sender, 'error6 code: '.$sender->info['httpCode'][0]);

		$cardSynonym = $arr['result']['cardSynonym'];

		//7)отправка формы с картой циклом
		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://money.yandex.ru/payments/internal/confirmation?orderId='.$orderId,
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$postArr = [
			'isSuperConversionForm' => 'false',
			'isNeedCreateWallet' => 'false',
			'cardBindToWalletIntention' => 'false',
			'needPrepareNewPayment' => 'false',
			'isPaymentRechoiceSupported' => 'false',
			'paymentCardSynonym' => $cardSynonym,
			'extAuthFailUri' => 'https://money.yandex.ru/payments/internal/land3ds?orderId='.$orderId
				.'&isPaymentShop=true&couldPayByWallet=false&notEnoughMoney=false'
				.'&isBindCardCheckboxPreselected=false&canCardBindToWallet=false',
			'extAuthSuccessUri' => 'https://money.yandex.ru/payments/internal/land3ds?orderId='.$orderId
				.'&is3dsAuthPassed=true&isPaymentShop=true&couldPayByWallet=false&notEnoughMoney=false'
				.'&isBindCardCheckboxPreselected=false&canCardBindToWallet=false',
//			'extAuthFailUri' => $params['failUrl'],
//			'extAuthSuccessUri' => $params['successUrl'],
			'cps_phone' => '',
			'isDonationApprovedByPayer' => 'false',
			'sk' => $sk,
		];

		$url = 'https://money.yandex.ru/payments/internal/confirm/any-card?orderId='.$orderId;

		for($i=1; $i<=5; $i++)
		{


			$content = $sender->send($url, http_build_query($postArr), $params['proxy']);

			$arr = @json_decode($content, true);

			if(!$arr)
				return self::cardError('error7.1', $params, $sender, 'error7.1 content: '.$content);



			if($arr['status'] == 'progress')
			{
				sleep($arr['timeout']);
			}
			else
				break;

			$postArr = $arr['retryParams'];
		}

		if($arr['status'] != 'success')
			return self::cardError('error7.2', $params, $sender, 'error7.2 code: '.$sender->info['httpCode'][0].'. content: '.$content);

		if(!$arr['params'])
		{
			if(preg_match('!reason=InstrumentNotAllowed-Card%20country%20is%20forbidden!', $arr['url']))
				return self::cardError('страна карты не поддерживается', $params, $sender, 'error7.3 content: '.$content);
			else
				return self::cardError('эта карта не подходит', $params, $sender, 'error7.3 content: '.$content);
		}

		$termUrlYandex = $arr['params']['TermUrl'];
		$arr['params']['TermUrl'] = $params['checkUrl'];

		//для редиректа постом
		$result = [
			'url'=>$arr['url'],
			'postArr'=>$arr['params'],
			'termUrlProvider'=>$termUrlYandex,
			'orderIdProvider'=>$orderId,
		];

		self::log('получена ссылка на банк Yandex: '.$result['url'].',  orderId='.$params['orderId']);


		return $result;
	}

	/**
	 * @param string $msgUser
	 * @param array $params
	 * @param Sender|bool $sender
	 * @param string $msgAdmin
	 * @return array
	 */
	private static function cardError($msgUser, $params, $sender = false, $msgAdmin = '')
	{
		self::$lastError = $msgUser;

		if(!$msgAdmin)
			$msgAdmin = $msgUser;

		if($sender)
			$msgAdmin .= ', httpCode '.$sender->info['httpCode'][0];

		$msgAdmin .= Tools::arr2Str($params);

		self::log($msgAdmin);

		return [];
	}

	public static function log($msg)
	{
		Tools::log('CardPayTest: '.$msg, null, null, 'test');
	}

	/**
	 * @param string $error
	 * @return bool
	 */
	public function cancelOrder($error = '')
	{
		if($this->status === self::STATUS_ERROR)
			return true;
		elseif($this->status !== self::STATUS_WAIT)
		{
			self::$lastError = 'заявка уже подтверждена или не актуальна';
			return false;
		}

		$this->status = self::STATUS_ERROR;
		//$this->error = 'отменен в '.date('d.m.Y H:i');

		if($error)
			$this->error = $error;

		if($this->save())
		{
			self::log('заявка отменена '.$this->order_id."(причина: $error)"
				.' params: '.Tools::arr2Str($this->payParams));
			return true;
		}
		else
			return false;
	}

	public function confirmOrder()
	{
		if($this->status === self::STATUS_SUCCESS)
			return true;
		elseif($this->status !== self::STATUS_WAIT)
		{
			self::$lastError = 'заявка не актуальна';
			return false;
		}

		$this->status = self::STATUS_SUCCESS;
		$this->date_pay = time();

		if($this->save())
		{
			self::log('заявка подтверждена order_id='.$this->order_id);
			return true;
		}
		else
			return false;
	}

	public static function startApiNotification()
	{
		//todo: реализовать
		$cfg = cfg('storeApi');
		/**
		 * @var User[] $users
		 */
		$users = User::model()->findAll();

		foreach($users as $user)
		{
			if(!$user->url_result)
				continue;

			$successPayments = self::model()->findAll([
				'condition' => "`user_id`=$user->id AND `status`='".self::STATUS_SUCCESS."' AND `is_notified`=0",
				'order' => "`date_pay` DESC",
			]);
			/**
			 * @var self[] $successPayments
			 */

			$payments = [];

			foreach($successPayments as $successPayment)
			{
				$params = [
					'id' => $successPayment->id,
					'orderId' => $successPayment->order_id,
					'url' => $successPayment->url,
					'amount' => $successPayment->amount,
					'currency' => 'RUB',
					'status' => $successPayment->status,
					'timestampPay' => $successPayment->date_pay,
					'error' => $successPayment->error
				];

				$params['hash'] = self::hash($params, $user->api_secret);
				$payments[] = $params;
			}

			if($payments)
			{
				$sender = new Sender;
				$sender->followLocation = false;
				$sender->proxyType = $cfg['notificationProxyType'];
				$sender->useCookie = false;

				$result = [
					'payments' => $payments,
				];

				$content = $sender->send($user->url_result, json_encode($result), $cfg['notificationProxy']);

				toLogRuntime('API MANAGER content notification'.$content);

				if($content === 'OK')
				{
					foreach($successPayments as $successPayment)
					{
						$successPayment->is_notified = 1;

						if($successPayment->save())
						{
							toLogRuntime('API MANAGER платеж order_id='.$successPayment->order_id.' уведомлен');
						}
						else
						{
							toLogRuntime('API MANAGER ошибка уведомления платежа id='.$successPayment->id);
							return false;
						}
					}
				}
				else
				{
					//вернять в логи после того как кл у себя настроит
					toLogRuntime('API MANAGER ошибка уведомления: url='.$user->url_result.' contentLength='.strlen($content).', httpCode='
						.$sender->info['httpCode'][0].', url = '.$user->url_result);
				}

			}
		}

		return true;
	}

	public static function clean()
	{
		//todo: дописать очистку-автоотмену заявок
	}

	//выдавать по возможности один и тот же прокси на один и тот же браузер
	public static function selectProxy($browser = '')
	{
		//test
		$cfgFile = realpath(__DIR__.'/../').'/config/proxy.txt';
		$proxyStr = file_get_contents($cfgFile);

		$proxyArr = explode("\n", trim($proxyStr));

		$randProxy = $proxyArr[array_rand($proxyArr)];

		$randProxy = str_replace('http://', '', $randProxy);

		if(!$browser)
			return $randProxy;

		$runtimePath = DIR_ROOT.'protected/runtime/';

		$filePath = $runtimePath.'cardPay/browserProxy.txt';

		if(!file_exists($filePath))
		{
			if(file_put_contents($filePath, '') === false)
			{
				self::log('error1 write file '.$filePath);
				return false;
			}
		}

		$content = file_get_contents($filePath);
		$arr = unserialize($content);

		$hash = md5($browser);

		//если нет соответствий или такого прокси больше
		if(!isset($arr[$hash]) or array_search($arr[$hash], $proxyArr) === false)
		{
			$arr[$hash] = $randProxy;
			$content = serialize($arr);

			if(!file_put_contents($filePath, $content))
			{
				self::log('error2 write file '.$filePath);
				return false;
			}
		}

		return $arr[$hash];
	}

	/**
	 * @param array $params
	 * [
	 *		'cardNumber' => '',
	 *		'cardM' => '',
	 *		'cardY' => '',
	 *		'browser' => '',
	 *		'headers' => '',
	 *		'phoneNumber' => '9026353545',
	 *		'amount' => '',
	 *		'orderId' => '',
	 *
	 * ]
	 * @return array ['url'=>'', 'postArr'=>[]]
	 */
	private static function getBankUrlMts($params)
	{
		if(self::MTS_METHOD == 'selenium')
			return self::getBankUrlMtsSelenium($params);
		elseif(self::MTS_METHOD == 'api')
			return self::getBankUrlMtsApi($params);

		$result = [];
		//$config = Yii::app()->getModule('sim')->config;


		$params['cardNumber'] = preg_replace('![^\d]!', '', $params['cardNumber']);
		$params['cardNumber'] = substr($params['cardNumber'], 0, 4).' '
			.substr($params['cardNumber'], 4, 4).' '
			.substr($params['cardNumber'], 8, 4).' '
			.substr($params['cardNumber'], 12, 4);
		$params['cardCvv'] = preg_replace('![^\d]!', '', $params['cardCvv']);
		$params['cardM'] = preg_replace('![^\d]!', '', $params['cardM']);
		$params['cardY'] = preg_replace('![^\d]!', '', $params['cardY']);

		$params['amount'] *= 1;

		if(!$params['proxy'])
			return self::cardError('техническая ошибка 1', $params, false, 'MtsError0 нет прокси');

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');

		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser']).'.txt';

		//очистить куки перед каждой заявкой
		//file_put_contents($sender->cookieFile, '');
		$sender->pause = 0;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		//$sender->browser = $params['browser'];
		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send('https://payment.mts.ru/pay/5920', false, $params['proxy']);

		if(!preg_match('!<input name="__RequestVerificationToken" type="hidden" value="(.+?)"!', $content, $res))
			return self::cardError('Error1', $params, $sender, 'MtsError1 content: '.$content);

		sleep(rand(5, 7));

		$token = $res[1];

		$postParams = [
			'__RequestVerificationToken' => $token,
			'CardholderName' => '',
			'Cvc' => $params['cardCvv'],
			'ExpiryMonth' => $params['cardM'],
			'ExpiryYear' => $params['cardY'],
			'IsMtsPayment' => 'true',
			'IsZeroCommision' => 'False',
			//'IsZeroCommision' => 'True',
			'Location' => 'https://payment.mts.ru/pay/5920',
			'Name' => 'Теле2',
			//'Name' => 'МТС',
			'Pan' => $params['cardNumber'],
			'Parameters[0].Name' => 'id1',
			//'Parameters[0].Name' => 'NUMBER',
			'Parameters[0].Type' => 'PhoneField',
			'Parameters[0].Val1' => $params['phoneNumber'],
			'PaymentSumMax' => '15000',
			'PaymentSumMin' => '10',
			'PaymentToken' => '',
			'ProviderId' => '5920',
			'SelectedInstrumentId' => 'ANONYMOUS_CARD',
			'Sum' => $params['amount'],
		];

		$postData = http_build_query($postParams);

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: https://payment.mts.ru/pay/5920',
			'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send('https://payment.mts.ru/payment/payform/GetCommissions',
			$postData, $params['proxy']);

		if(!$arr = json_decode($content, true))
			return self::cardError('Error2', $params, $sender, 'MtsError2 content: '.$content);

		//сумма с комиссией
		$postParams['Sum'] = $arr['sumWithVat'];

		$content = $sender->send('https://payment.mts.ru/payment/pay',
			$postData, $params['proxy']);

		$contentJson = $content;

		//test  пока выводим то что оператор отдает (сделать валидацию ранее)
		if(!$arr = json_decode($content, true))
			return self::cardError('Error: '.strip_tags($content), $params, $sender, 'MtsError3 content: '.$content);

		self::$lastErrorCode = '';

		if(!$arr['model']['acsUrl'])
		{
			if($arr['error'])
			{
				if($arr['error'] == '70908')
					return self::cardError('Проверьте данные и повторите запрос', $params, $sender, 'MTSerror3.7'
						.$params['phoneNumber'].' contentJson='.$contentJson);
				elseif($arr['error'] == '20999')
				{
					//test попробуем без бана
					//self::$lastErrorCode = self::ERROR_BAN;
					return self::cardError('технические неполадки, повторите запрос на оплату', $params, $sender, 'MTS ban '
						.$params['phoneNumber'].' contentJson='.$contentJson);
				}

				$url = 'https://payment.mts.ru/receipt?mdOrder='.$arr['mdOrder'].'&error='.$arr['error']
					.'&createap=false&direct=true';

				$content = $sender->send($url, $postData, $params['proxy'], 'https://payment.mts.ru/pay/5920');

				if(!preg_match('!<div class="b-content__title b-content__red">(.+)</div>!', $content, $match))
				{
					return self::cardError('error36', $params, $sender, 'errorMTS3.6 content='.$content.', contentJson='.$contentJson);
				}

				$error = trim(strip_tags(html_entity_decode($match[1])));

				if($error and mb_strlen($error) < 200)
				{
					return self::cardError($error, $params, $sender, $error.', contentJson='.$contentJson);
				}
				else
					return self::cardError('error35', $params, $sender, 'MtsError3.5 content: '.$content);
			}
			else
				return self::cardError('Error4', $params, $sender, 'MtsError4 content: '.$content
					.', header: '.$sender->info['header'][0]);
		}

		$redirParams = [
			'MD' => $arr['model']['md'],
			'MdOrder' => $arr['model']['mdOrder'],
			'PaReq' => $arr['model']['paReq'],
			'TermUrl' => $params['checkUrl'],
		];

		//test замена провайдера в банке
//		$paReq = $redirParams['PaReq'];
//		$strOut = gzuncompress(base64_decode($paReq));
//		$strOut = preg_replace('!<name>.+?</name>!', '<name>HELL PROVIDER</name>', $strOut);
//		$redirParams['PaReq'] = base64_encode(gzcompress($strOut));


		//для редиректа постом
		$result = [
			'url'=>$arr['model']['acsUrl'],
			'postArr'=>$redirParams,
		];

		self::log('получена ссылка на банк Mts: '.$result['url'].' params='.arr2str($params).',  orderId='.$params['orderId']);

		return $result;
	}

	/**
	 * @param array $params
	 * [
	 * 		'MD' => '',
	 * 		'PaRes' => '',
	 * 		'browser' => '',
	 * 		'headers' => [],
	 * ]
	 * @return array|false
	 */
	private static function checkBankUrlMts($params)
	{
		if(self::MTS_METHOD == 'selenium')
			return self::checkBankUrlMtsSelenium($params);
		if(self::MTS_METHOD == 'api')
			return self::checkBankUrlMtsApi($params);

		$result = [
			'status' => '',
			'msg' => '',	//если ошибка
		];

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');


		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser']).'.txt';
		$sender->pause = 1;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		$refererParts = parse_url(strtolower($params['bankUrl']));
		$referer = $refererParts['scheme'].'://'.$refererParts['host'].'/';

		$url = 'https://payment.mts.ru/verified3ds?MdOrder='.$params['MD'].'&MD='.$params['MD'].'&type=1&referer=1';
		$postArr = [
			'MD' => $params['MD'],
			'PaRes' => $params['PaRes'],
		];

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: '.$referer,
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send($url, http_build_query($postArr), $params['proxy']);

		$header = $sender->info['header'][0];
		///success/53568201711/0?is3ds=True&createAp=False&type=Payment
		///receipt/53556050811/25?is3ds=True&createAp=False&type=Payment

		if(preg_match('!Location:\s*(/receipt/.+)!i', $header, $match))
		{
			self::log('test: '.$match[1]);
			//получаем причину отказа

			$content = $sender->send('https://payment.mts.ru'.trim($match[1]), false, $params['proxy']);
			$header = $sender->info['header'][0];
			$httpCode = $sender->info['httpCode'][0];

			if(!preg_match('!<div class="b-content__title b-content__red">(.+)</div>!', $content, $match))
			{
				self::log('checkBankUrlMts Возможно бан прокси или браузера orderId='.$params['orderId']
					.', httpCode='.$httpCode.', header='.$header);
			}

			$error = trim(@html_entity_decode($match[1]));

			if($error and mb_strlen($error) < 200)
			{
				$result['msg'] = $error;

				//test
				if($result['msg'] == 'Банк отклонил платеж. Попробуйте позже')
					$result['msg'] = 'Банк отклонил платеж. Возможно недостаточно средств или неверный CVV-код';
			}

			$errorMsg = ($result['msg']) ? $result['msg'] : $error;

			self::log('checkBankUrlMtsFail причина: |'.$errorMsg.'|, orderId='
				.$params['orderId'].', header='.$header);
			$result['status'] = self::STATUS_ERROR;
		}
		elseif(preg_match('!Location:\s*(/success/.+)!i', $header))
		{
			$sender->send('https://payment.mts.ru'.$match[1], false, $params['proxy'], $url);

			self::log('checkBankUrlMtsSuccess header: '.$header.', orderId='.$params['orderId']);
			$result['status'] = self::STATUS_SUCCESS;
		}
		elseif(preg_match('!Location:\s*/error/brokenlink!i', $header))
		{
			$sender->send('https://payment.mts.ru'.$match[1], false, $params['proxy'], $url);

			self::log('checkBankUrlMts Error1 header='.$header.',content= orderId='.$params['orderId']);
			return false;
		}
		else
		{
			self::log('checkBankUrlMts ERROR content='.$content.', httpCode='.$sender->info['httpCode'][0]
				.', header='.$header.', url='.$url.', postData='.arr2str($postArr));
			return false;
		}

		return $result;
	}

	/**
	 * @return array
	 */
	public static function getPaymentTypeArr()
	{
		return [
			self::PAYMENT_TYPE_YANDEX => 'Яндекс',
			self::PAYMENT_TYPE_MTS => 'МТС',
			self::PAYMENT_TYPE_TELE2 => 'Теле2',
			self::PAYMENT_TYPE_A3 => 'A3',
			self::PAYMENT_TYPE_MEGAFON => 'Мегaфон',
		];
	}

	/**
	 * проверяет платеж на шлюзе, возвращает ошибку елси возможно
	 * @param array $params ['MD'=>'', 'PaRes'=>'']
	 * @return array['msg'=>'', 'status'=>'']
	 */
	public function checkOrder($params)
	{
		$result = [
			'status'=>$this->status,
			'msg'=>'',
		];

		$threadName = 'simTransactionCheck'.$this->id;

		if($this->status == self::STATUS_SUCCESS)
			return $result;
		elseif($this->status == self::STATUS_ERROR)
		{
			$result['msg'] = $this->error;
			return $result;
		}
		elseif($this->status == self::STATUS_WAIT)
		{
			if(!Tools::threader($threadName))
			{
				//todo: тянуть время циклом со sleep
				$result['msg'] = 'эта заявка уже проверяется, повторите запрос через несколько секунд';
				self::log($result['msg']);
				return $result;
			}

			$payParams = @json_decode($this->pay_params, true);

			if(!$payParams)
			{
				self::log('no pay params in order_id='.$this->order_id);
				return $result;
			}

			$params = array_merge($params, $payParams);

			if(!$params['browser'])
				$params['browser'] = $payParams['browser'];

			$params['headers'] = $payParams['headers'];
			$params['termUrlProvider'] = $payParams['termUrlProvider'];
			$params['orderIdProvider'] = $payParams['orderIdProvider'];


			$params['orderId'] = $this->order_id;
			$params['cardNumber'] = $payParams['cardNumber'];
			$params['phoneNumber'] = $payParams['phoneNumber'];
			$params['referer'] = $payParams['referer'];

			$params['proxyType'] = 'http';

			//test
			if(!$params['proxy'])
				$params['proxy'] = self::selectProxy($params['browser']);

			$params['bankUrl'] = $this->bank_url;
			$params['botId'] = $this->bot_id;

			if($this->payment_type == self::PAYMENT_TYPE_MTS)
				$checkResult = self::checkBankUrlMts($params);
			elseif($this->payment_type == self::PAYMENT_TYPE_YANDEX)
				$checkResult = self::checkBankUrlYandex($params);
			elseif($this->payment_type == self::PAYMENT_TYPE_TELE2)
				$checkResult = self::checkBankUrlTele2($params);
			elseif($this->payment_type == self::PAYMENT_TYPE_A3)
				$checkResult = self::checkBankUrlA3($params);
			else
			{
				self::log('неизвестный тип платежа order_id='.$this->order_id);
				return $result;
			}

			if(!$checkResult)
				return $result;

			if($checkResult['status'] === self::STATUS_SUCCESS)
			{
				if($this->confirmOrder())
					$result['status'] = $checkResult['status'];
				else
				{
					$result['msg'] = 'ошибка подтверждения заявки';
					self::log('ошибка подтверждения заявки orderId='.$this->order_id);
				}
			}
			elseif($checkResult['status'] === self::STATUS_ERROR)
			{
				if($this->cancelOrder($checkResult['msg']))
				{
					$result['status'] = self::STATUS_ERROR;
					$result['msg'] = $checkResult['msg'];
				}
				else
				{
					$result['msg'] = 'ошибка отмены заявки';
					self::log('ошибка отмены заявки orderId=' . $this->order_id);
				}
			}
			else
			{
				self::log($result['msg'].' orderId=' . $this->order_id);
				$result['msg'] = 'ошибка оплаты заявки. повторите попытку позже';
			}
		}

		return $result;
	}

	/**
	 *	todo: сделать нестатичный метод, который не срабатывает если у модели уже есть bank_url
	 *
	 *
	 * @param array $params
	 * [
	 *		'cardNumber' => '',
	 *		'cardM' => '',
	 *		'cardY' => '',
	 *		'cardCvv' => '',
	 *		'browser' => '',
	 *		'headers' => '',
	 *		'amount' => '',
	 *		'orderId' => '',
	 * ]
	 * @return array данные для редиректа в банк
	 */
	public static function getBankUrl($params)
	{
		//test
		//return self::cardError('closed', $params);

		$config = Yii::app()->getModule('sim')->config;


		if(!$model = self::getModel(['order_id'=>$params['orderId'], 'status'=>self::STATUS_WAIT]))
			return self::cardError('заявка не найдена или не актуальна', $params, false, 'заявка не найдена или не актуальна');

		$threadName = 'simTransactionGetParams'.$model->id;

		if(!Tools::threader($threadName))
		{
			//todo: тянуть время sleep-ом
			//todo: сохранять полученные параметры в бд и возвращать при повторном запросе
			return self::cardError('данные для оплаты уже были получены, повторите запрос через несколько секунд', $params);
		}

		$params['cardNumber'] = preg_replace('![^\d]!', '', $params['cardNumber']);
		$params['cardCvv'] = preg_replace('![^\d]!', '', $params['cardCvv']);
		$params['cardM'] = preg_replace('![^\d]!', '', $params['cardM']);
		$params['cardY'] = preg_replace('![^\d]!', '', $params['cardY']);

		if(strlen($params['cardNumber']) != 16)
			return self::cardError('неверный  номер карты', $params);

		if(strlen($params['cardM']) === 1)
			$params['cardM'] = '0'.$params['cardM'];

		if(strlen($params['cardM']) != 2)
			return self::cardError('неверно указан месяц', $params);

		if(strlen($params['cardY']) != 2)
			return self::cardError('неверно указан год', $params);

		if(
			!$timestamp = strtotime('01.'.$params['cardM'].'.20'.$params['cardY'])
			or
			$timestamp + 3600*24*30 < time()
		)
			return self::cardError('проверьте месяц и год, возможно ваша карта уже истекла', $params);


		if(strlen($params['cardCvv']) != 3)
			return self::cardError('неверный  CVV-код с обратной стороны карты', $params);

		$params['phoneNumber'] = $model->account->login;
		$params['amount'] = $model->amount;

		$params['successUrl'] = str_replace(
			['{orderId}', '{hash}'],
			[$model->order_id, $model->hash],
			$config['successUrlTpl']);
		$params['failUrl'] = str_replace(
			['{orderId}'],
			[$model->order_id],
			$config['failUrlTpl']);

		if(!$params['checkUrl'])
			$params['checkUrl'] = str_replace(
				['{orderId}'],
				[$model->order_id],
				$config['checkUrlTpl']);

		//test
		$params['proxyType'] = 'http';

		if(!$params['proxy'])
			$params['proxy'] = self::selectProxy($params['browser']);

		if(!$params['proxy'])
			return self::cardError('техническая ошибка 3', $params, false, 'нет прокси');

		self::log('client: '.$params['browser'].', proxy: '.$params['proxy']);

		//процессить Мир через яд принудительно
//		if(substr($params['cardNumber'], 0, 1) == '2' or $model->payment_type == SimTransaction::PAYMENT_TYPE_YANDEX)
//			$result = self::getBankUrlYandex($params);
//		elseif($model->payment_type == SimTransaction::PAYMENT_TYPE_MTS) {
//			$result = self::getBankUrlMts($params);
//		}

		if($model->payment_type == self::PAYMENT_TYPE_MTS)
			$result = self::getBankUrlMts($params);
		elseif($model->payment_type == self::PAYMENT_TYPE_YANDEX)
			$result = self::getBankUrlYandex($params);
		elseif($model->payment_type == self::PAYMENT_TYPE_TELE2)
			$result = self::getBankUrlTele2($params);
		elseif($model->payment_type == self::PAYMENT_TYPE_A3)
			$result = self::getBankUrlA3($params);
		elseif($model->payment_type == self::PAYMENT_TYPE_MEGAFON)
			$result = self::getBankUrlMegafon($params);
		else
			return self::cardError('неизвестный тип платежа', $params, false, 'getBankUrl');

		$params = array_merge($params, $result);

		if(!$result)
		{
			$account = $model->account;
			$account->error_count++;
			$account->save();
		}

		unset($params['MD']);
		unset($params['PaRes']);

		$model->pay_params = json_encode($params);
		$model->proxy = $params['proxy'];
		$model->bank_url = ($result) ? $result['url'] : '';
		$model->card_number = ($result) ? $params['cardNumber'] : '';

		if($result['botId'])
			$model->bot_id = $result['botId'];

		$model->save();

		$account = $model->account;

		if(self::$lastErrorCode === self::ERROR_BAN)
		{
			//бан
			$account->status = SimAccount::STATUS_BAN;
			$account->date_error = time();
			$account->save();
		}
		elseif(self::$lastErrorCode === self::ERROR_WAIT)
		{
			//холд до следующего дня(ограничение на платежи)
			$account->status = SimAccount::STATUS_WAIT;
			$account->date_error = time();
			$account->save();
		}

		if($result)
		{
			return[
				'url' => $result['url'],
				'postArr' => $result['postArr'],
			];
		}
		else
			return [];
	}

	public function getPayParams()
	{
		if($result = @json_decode($this->pay_params, true))
			return $result;
		else
			return [];
	}

	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	public function getDatePayStr()
	{
		return ($this->date_pay) ? date('d.m.Y H:i', $this->date_add) : '';
	}


	/**
	 * @param array $params
	 * [
	 * 		'MD' => '',
	 * 		'PaRes' => '',
	 * 		'browser' => '',
	 * 		'headers' => [],
	 * ]
	 * @return array|false
	 */
	private static function checkBankUrlYandex($params)
	{
		if(self::YANDEX_METHOD == 'selenium')
			return self::checkBankUrlYandexSelenium($params);

		$result = [
			'status' => '',
			'msg' => '',	//если ошибка
		];


		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');


		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser']).'.txt';
		$sender->pause = 1;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		$refererParts = parse_url(strtolower($params['bankUrl']));
		$referer = $refererParts['scheme'].'://'.$refererParts['host'].'/';

		$sender->browser = $params['browser'];

		//1)посылаем md и paRes

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: '.$referer,
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$url = 'https://paymentcard.yamoney.ru/gates/brs3ds_4';
		$postData = http_build_query([
			'MD' => $params['MD'],
			'PaRes' => $params['PaRes'],
		]);

		$content = $sender->send($url, $postData, $params['proxy']);

		$header = $sender->info['header'][0];

		if(!preg_match('!Location:\s*(/processing/.+)!i', $header, $match))
		{
			self::log('checkBankUrlYandex error1 header: '.$header.', content: '.$content.', orderId='.$params['orderId']);
			return false;
		}

		//2) 302 редирект на /processing/3ds?KeyRES=ypwe%2FSglBP8XLWG%2BEGFhksbqk%2BA%3D.000
		$url = 'https://paymentcard.yamoney.ru'.trim($match[1]);

		$content = $sender->send($url, false, $params['proxy']);

		$header = $sender->info['header'][0];

		//отмена: https://money.yandex.ru/payments/internal/land3ds?orderId=254de07c-000f-5000-9000-1347acd7f19d&isPaymentShop=true&couldPayByWallet=false&notEnoughMoney=false&isBindCardCheckboxPreselected=false&canCardBindToWallet=false
		//успех: https://money.yandex.ru/payments/internal/land3ds?orderId=254e5c4e-000f-5000-9000-1e1315d39944&is3dsAuthPassed=true&isPaymentShop=true&couldPayByWallet=false&notEnoughMoney=false&isBindCardCheckboxPreselected=true&canCardBindToWallet=false
		if(!preg_match('!Location:(.+?/internal/land3ds.+)!i', $header, $match))
		{
			self::log('checkBankUrlYandex error2 header: '.$header.', content: '.$content.', orderId='.$params['orderId']);
			return false;
		}

		//3)редирект на https://money.yandex.ru/payments/internal/land3ds?orderId=254de07c-000f-5000-9000-1347acd7f19d&isPaymentShop=true&couldPayByWallet=false&notEnoughMoney=false&isBindCardCheckboxPreselected=false&canCardBindToWallet=false
		$landUrl = trim($match[1]);
		$content = $sender->send($landUrl, false, $params['proxy']);

		$header = $sender->info['header'][0];

		if(!preg_match('!"sk":"(.+?)"!', $content, $match))
		{
			self::log('checkBankUrlYandex error3 header: '.$header.', content: '.$content.', orderId='.$params['orderId']);
			return false;
		}

		$sk = $match[1];

		if(!preg_match('!teamHash \= "(.+?)"!', $content, $match))
			return self::cardError('error3', $params, $sender, 'error3 code: '.$sender->info['httpCode'][0]);

		$teamHash = $match[1];

		//3.1)запрос на https://money.yandex.ru/ajax/makeupd/client-js-monitoring

		$postArr = [
			'url' => $landUrl,
			'stacktrace' => 'bc</o.componentDidMount/<@https://money.yandex.ru/layout-service/static/portal/client.ru.c2a576cf88f7b1ccff88.js:59:582685',
			'userAgent' => $params['browser'],
			'teamHash' => trim($teamHash),
		];

		$url = 'https://money.yandex.ru/ajax/makeupd/client-js-monitoring';

		$sender->send($url, http_build_query($postArr), $params['proxy']);

		if($sender->info['httpCode'][0] !== 200)
			return self::cardError('error3.1', $params, $sender, 'error1.1 code: '.$sender->info['httpCode'][0]);

		//5)
		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://money.yandex.ru/payments/internal/land3ds?orderId='.$params['orderIdProvider'].'&isPaymentShop=true&couldPayByWallet=false&notEnoughMoney=false&isBindCardCheckboxPreselected=false&canCardBindToWallet=false',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$url = 'https://money.yandex.ru/payments/internal/confirm/any-card?orderId='.$params['orderIdProvider'].'&is3dsAuthPassed=true';

		$postData = http_build_query([
			'sk' => $sk,
		]);

		$content = $sender->send($url, $postData, $params['proxy']);

		$header = $sender->info['header'][0];

		if(!$arr = json_decode($content, true) or $arr['status'] != 'success')
		{
			self::log('checkBankUrlYandex error5 header: '.$header.', content: '.$content.', orderId='.$params['orderId']);
			return false;
		}

		//internal/fail...
		//internal/success...
		$resultUrl = $arr['url'];

		if(preg_match('!internal/success!', $resultUrl))
		{
			$result['status'] = 'success';
			return $result;
		}
		elseif(preg_match('!internal/fail!', $resultUrl))
		{
			//7) забираем сообщение об ошибке
			$sender->additionalHeaders = [
				'User-Agent: '.$params['browser'],
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: '.$landUrl,
				'Upgrade-Insecure-Requests: 1',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
			];

			$content = $sender->send($resultUrl, false, $params['proxy']);


			if(
				!preg_match('!<p class="paragraph docbook-para inline">(.+?)</p>!', $content, $match1)
				or
				!preg_match('!docbook-title"\>(.+?)<\/h1!', $content, $match2)
			)
			{
				self::log('checkBankUrlYandex error7 header: '.$header.', content: '.$content.', orderId='.$params['orderId']);
			}

			$errorMsg = trim(strip_tags($match1[1])) .'. '.trim(strip_tags($match2[1]));
			$result['status'] = 'error';
			$result['msg'] = $errorMsg;

			if($result['msg'] == 'Из-за ошибки при подтверждении оплаты. Попробуйте заплатить ещё раз.')
				$result['msg'] = 'Отмена оплаты';

			self::log('checkBankUrlYandex msg:|'.$errorMsg.'|');

			return $result;
		}
		else
		{
			self::log('checkBankUrlYandex error6 header: '.$header.', content: '.$content.', orderId='.$params['orderId']);
			return false;
		}

		//$header = $sender->info['header'][0];

//		if($sender->info['httpCode'][0] == 200)
//		{
//			if(preg_match('!<h1 class\="title title_last_yes">Платеж прошел</h1>!', $content))
//			{
//				self::log('checkBankUrlYandex SUCCESS url='.$url.', httpCode='.$sender->info['httpCode'][0].' orderId='.$params['orderId']);
//				$result['status'] = self::STATUS_SUCCESS;
//				return $result;
//			}
//			else
//			{
//				self::log('checkBankUrlYandex0 url='.$url.', httpCode='.$sender->info['httpCode'][0].' orderId='.$params['orderId']);
//				return false;
//			}
//		}
//		elseif(!preg_match('!Location: (.+)!', $header, $match))
//		{
//			self::log('checkBankUrlYandex2 content='.$content.', header='.$header
//				.', httpCode='.$sender->info['httpCode'][0].' orderId='.$params['orderId'].' url: '.$url);
//			return false;
//		}

//		$location = $match[1];

		//6)забираем результат текстом


//		if(preg_match('!https://money\.yandex\.ru/payments/internal/fail\?orderId\=.+?&reason=(.+)!', $resultUrl, $match))
//		{
//			self::log('checkBankUrlYandex3 причина: |'.$match[1].'|, orderId='.$params['orderId']);
//			$result['status'] = self::STATUS_ERROR;
//
//			if(preg_match('!AuthorizationRejected!', $match[1]))
//				$result['msg'] = 'Эта карта не подходит. возможно Вы ввели неверный CVV-код';
//			elseif(preg_match('!InsufficientFunds!', $match[1]))
//				$result['msg'] = 'Недостаточно средств';
//			elseif(preg_match('!LimitExceeded!', $match[1]))
//				$result['msg'] = 'Карта достигла лимита, попробуйте заплатить завтра или с другой карты';
//			elseif(preg_match('!PayerAuthenticationFailed!', $match[1]))
//				$result['msg'] = 'Платеж отменен';
//			else
//			{
//				self::log('checkBankUrlYandex4 неизвестный статус |'.$match[1].'|');
//				$result['msg'] = '';
//			}
//		}
//		else
//		{

//
//			self::log('checkBankUrlYandex5: |'.$match[1].'|, location='.$resultUrl.', orderId='.$params['orderId']);
//			return false;
//		}

		return $result;
	}

	public static function startCancelOldTransactions()
	{
		$countAtOnce = 1000;
		$timestampMin = time() - self::CANCEL_TIMEOUT;
		$error = 'отменен по таймауту';

		if(!Tools::threader('simCancelOldTransactions'))
		{
			self::$lastError = 'already run';
			return false;
		}

		//как часто запускать
//		$interval = 50;
//		$lastOptimization = config('simLastCancel') * 1;
//
//		if(time() - $lastOptimization < $interval)
//		{
//			self::$lastError = 'not now';
//			return false;
//		}

		$models = self::model()->findAll([
			'condition' => "`status`='".self::STATUS_WAIT."' AND `date_add` < $timestampMin",
			'limit' => $countAtOnce,
			'order' => "`date_add` DESC",
		]);

		/**
		 * @var self[] $models
		 */

		$count = 0;

		//нужно именно циклом чтобы баланс на аккаунтах обновлялся
		foreach($models as $model)
		{
			if($model->cancelOrder($error))
				$count++;
		}

		if($count > 0)
			self::log('отменено '.$count.' заявок');

		config('simLastCancel', time());
	}

	protected function afterDelete()
	{
		$this->account->updateInfo();
		parent::afterDelete();
	}

	protected function afterSave()
	{
		$this->account->updateInfo();
		parent::afterSave();
	}


	private static function getBankUrlMtsSelenium($params)
	{
		set_time_limit(180);
		session_write_close();

		$result = [];

		$params['paymentType'] = self::PAYMENT_TYPE_MTS;

		if(!$bot = SimBot::getBot($params))
			return self::cardError('техническая ошибка100', $params, false, 'Mts SimBot не получен1');

		$bot->request('https://payment.mts.ru/pay/phone');
		$bot->loadCookies();
		$bot->request('https://payment.mts.ru/pay/phone');


		//тел
		if(!$element =$bot->findElement("//input[@data-type='phone']", 10))
			return self::cardError("техническая ошибка101", $params, false, 'mtsSelen error101');

		if(!$bot->sendKeys($element, $params['phoneNumber']))
			return self::cardError("техническая ошибка102", $params, false, 'mtsSelen error102');

		//сумма
		if(!$element =$bot->findElement("//input[@data-type='amount']"))
			return self::cardError("техническая ошибка103", $params, false, 'mtsSelen error103');

		$element->clear();

		if(!$bot->sendKeys($element, $params['amount']))
			return self::cardError("техническая ошибка104", $params, false, 'mtsSelen error104');


//		$script = "$('input[data-type=\"amount\"]').val('{$params['amount']}')";
//		$bot->driver->executeScript($script);

		sleep(1);


		if(!$element = $bot->findElement("//input[@value='ANONYMOUS_CARD']"))
			return self::cardError("техническая ошибка105", $params, false, 'mtsSelen error105');

		$bot->click($element);

		sleep(1);


		//карта
		if(!$element = $bot->findElement("//input[@name='Pan']"))
			return self::cardError("техническая ошибка106", $params, false, 'mtsSelen error106');

		$element->clear();

		$script = "$('input[name=\"Pan\"]').val('{$params['cardNumber']}')";
		$bot->driver->executeScript($script);

		if(!$element =$bot->findElement("//input[@name='ExpiryMonth']"))
			return self::cardError("техническая ошибка108", $params, false, 'mtsSelen error108');

		if(!$bot->sendKeys($element, $params['cardM']))
			return self::cardError("техническая ошибка109", $params, false, 'mtsSelen error109');


		if(!$element =$bot->findElement("//input[@name='ExpiryYear']"))
			return self::cardError("техническая ошибка110", $params, false, 'mtsSelen error110');

		if(!$bot->sendKeys($element, $params['cardY']))
			return self::cardError("техническая ошибка111", $params, false, 'mtsSelen error111');


		if(!$element =$bot->findElement("//input[@name='Cvc']"))
			return self::cardError("техническая ошибка112", $params, false, 'mtsSelen error112');

		if(!$bot->sendKeys($element, $params['cardCvv']))
			return self::cardError("техническая ошибка113", $params, false, 'mtsSelen error113');

		//$bot->includeJs('', 'https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js');
		//$bot->includeJs('', 'https://94.140.125.237/artur/mtsExtra.js');
		//$bot->includeJs('alert(\'fff\');');
		//var_dump($bot->includeJs(file_get_contents(BASE_DIR.'mtsExtra.js')));

		$script = <<<EOD
			var form = $('#payment_form');
			var url = 'https://payment.mts.ru/pay/phone';
			return $.ajax({
				type: 'POST',
				url: '/payment/pay',
				data: form.serialize(),
				dataType: 'json',
				cache: false,
				timeout: 0,
				async: false,
			}).responseText;
EOD;

		$content = $bot->driver->executeScript($script);
		$arr = json_decode($content, true);

		if(!$arr['model']['acsUrl'])
			return self::cardError("техническая ошибка114", $params, false, 'mtsSelen не получен url: '.$content);

		$result = [
			'url' => $arr['model']['acsUrl'],
			'postArr' => [
				'MD' => $arr['model']['md'],
				'MdOrder' => $arr['model']['mdOrder'],
				'PaReq' => $arr['model']['paReq'],
				'TermUrl' => $params['checkUrl'],
			],
			'botId'=>$bot->id,
		];


		self::log('получена ссылка на банк MtsSelen: '.$result['url'].' params='.arr2str($params).',  orderId='.$params['orderId']);


		return $result;
	}

	private static function checkBankUrlMtsSelenium($params)
	{
		set_time_limit(180);
		session_write_close();

		self::log('начало подтверждения: params: '.Tools::arr2Str($params));

		$result = [];

		$params['paymentType'] = self::PAYMENT_TYPE_MTS;

		if(!$bot = SimBot::getBot($params))
			return self::cardError('техническая ошибка134', $params, false, 'SimBot не получен4');

		$url = 'https://payment.mts.ru/verified3ds?MdOrder='.$params['MD'].'&MD='.$params['MD'].'&type=1&referer=1';
		$postArr = [
			'MD' => $params['MD'],
			'PaRes' => $params['PaRes'],
		];

		$bot->request('https://payment.mts.ru/pay/phone');
		$bot->loadCookies();
		$bot->request('https://payment.mts.ru/pay/phone');


		$bot->requestPost($url, $postArr);
		sleep(2);
		$content = $bot->driver->getPageSource();
		$currentUrl = $bot->driver->getCurrentURL();

		if(preg_match('!<div class="b-content__title b-content__red">(.+)</div>!', $content, $match))
		{
			$error = trim(@html_entity_decode($match[1]));

			if($error and mb_strlen($error) < 200)
			{
				$result['msg'] = $error;
				$result['status'] = 'error';
			}
			else
				return self::cardError("техническая ошибка124", $params, false, 'error124 url: '.$currentUrl);
		}
		elseif(preg_match('!payment\.mts\.ru\/success\/!', $currentUrl))
		{
			$result['status'] = 'success';
		}
		else
		{

			return self::cardError("техническая ошибка125", $params, false, 'error124 url: '.$currentUrl);
		}


		return $result;
	}


	private static function getBankUrlYandexSelenium($params)
	{
		set_time_limit(180);
		session_write_close();

		$result = [];

		$params['paymentType'] = self::PAYMENT_TYPE_YANDEX;

		if(!$bot = SimBot::getBot($params))
			return self::cardError('техническая ошибка25', $params, false, 'Yandex SimBot не получен1');




//		$screenshotsWebPath = 'https://94.140.125.237/artur/screenshots/';

		$url = 'https://money.yandex.ru/phone?sum=&netSum='.$params['amount']
			.'&phone-prefix=&phone-number='.$params['phoneNumber'].'&scid=928&phone-operator=tele2-928';

//		$url = 'https://money.yandex.ru/phone';

//		$url = 'https://google.com';

//		$url = 'http://188.138.57.110/requestInfo.php';
//		$bot->request($url);
//		die($bot->getCurrentContent());




		$bot->request($url);
		$bot->loadCookies();
		$bot->request($url);
		sleep(3);




		if(!$element = $bot->findElement("//button[@type='submit']", 20))
		{

			return self::cardError('техническая ошибка22', $params);
		}

		$bot->click($element);

		//карта
		if(!$element = $bot->findElement("//input[@name='skr_card-number']", 10))
			return self::cardError('техническая ошибка23', $params);

		$bot->sendKeys($element, $params['cardNumber']);

		//месяц
		if(!$element = $bot->findElement("//input[@name='skr_month']"))
			return self::cardError('техническая ошибка24', $params);

		$bot->sendKeys($element, $params['cardM']);

		//год
		if(!$element = $bot->findElement("//input[@name='skr_year']"))
			return self::cardError('техническая ошибка26', $params);

		$bot->sendKeys($element, $params['cardY']);

		//цвв
		if(!$element = $bot->findElement("//input[@name='skr_cardCvc']"))
			return self::cardError('техническая ошибка27', $params);

		$bot->sendKeys($element, $params['cardCvv']);


		//sleep(10);

		$content = $bot->getCurrentContent();

		if(!preg_match('!\&quot;sk\&quot;:\&quot;(.+?)\&quot;!', $content, $match))
			return self::cardError('техническая ошибка28', $params);

		$sk = $match[1];

		$currentUrl = $bot->getCurrentUrl();
		parse_str(parse_url($currentUrl)['query'], $parse2);

		if(!$orderId = $parse2['orderId'])
			return self::cardError('техническая ошибка29', $params);

		$url = 'https://paymentcard.yamoney.ru/webservice/storecard/api/storeCardForPayment';
		$postData = '{"cardholder":"CARD HOLDER","csc":"'.$params['cardCvv']
			.'","pan":"'.$params['cardNumber'].'","expireDate":"20'.$params['cardY'].$params['cardM'].'"}';

		$content = $bot->requestAjax($url, $postData);

		if(!$content or !$arr = json_decode($content, true) or $arr['status'] != 'success')
			return self::cardError('техническая ошибка30', $params, false, 'техническая ошибка30: '.$content);

		$cardSynonym = $arr['result']['cardSynonym'];

		$url = 'https://money.yandex.ru/payments/internal/confirm/any-card?orderId='.$orderId;

		$postData = http_build_query([
			'orderId' => $orderId,
			'isSuperConversionForm' => 'false',
			'isNeedCreateWallet' => 'false',
			'cardBindToWalletIntention' => 'false',
			'needPrepareNewPayment' => 'false',
			'isPaymentRechoiceSupported' => 'false',
			'paymentCardSynonym' => $cardSynonym,
			'extAuthFailUri' => 'https://money.yandex.ru/payments/internal/land3ds?orderId='.$orderId
				.'&isPaymentShop=true&couldPayByWallet=false&notEnoughMoney=false'
				.'&isBindCardCheckboxPreselected=true&canCardBindToWallet=false',
			'extAuthSuccessUri' => 'https://money.yandex.ru/payments/internal/land3ds?orderId='.$orderId
				.'&is3dsAuthPassed=true'
				.'&isPaymentShop=true&couldPayByWallet=false&notEnoughMoney=false'
				.'&isBindCardCheckboxPreselected=true&canCardBindToWallet=false',
			'cps_phone' => '',
			'isDonationApprovedByPayer' => 'false',
			'sk' => $sk,
		]);


		for($i=1; $i<=5; $i++)
		{
			$content = $bot->requestAjax($url, $postData);
			sleep(2);

			$arr = @json_decode($content, true);

			if(!$arr)
				return self::cardError('техническая ошибка31', $params);

			if($arr['status'] != 'progress')
				break;

			$postData = http_build_query($arr['retryParams']);
		}

		if($arr['status'] != 'success')
			return self::cardError('техническая ошибка32', $params, false, arr2str($arr));

		if(!$arr['params'])
			return self::cardError('техническая ошибка33', $params, false, 'bot '.$bot->id.' auth rejected');

		$termUrlYandex = $arr['params']['TermUrl'];
		$arr['params']['TermUrl'] = $params['checkUrl'];

		$result = [
			'url'=>$arr['url'],
			'postArr'=>$arr['params'],
			'termUrlProvider'=>$termUrlYandex,
			'orderIdProvider'=>$orderId,
			'botId'=>$bot->id,
		];

		self::log('получена ссылка на банк YandexSelen: '.$result['url'].' params='.arr2str($params).',  orderId='.$params['orderId']);


		return $result;
	}

	private static function checkBankUrlYandexSelenium($params)
	{
		set_time_limit(180);
		session_write_close();

		$result = [];

		$params['paymentType'] = self::PAYMENT_TYPE_YANDEX;


		if(!$bot = SimBot::getBot($params))
			return self::cardError('техническая ошибка34', $params, false, 'SimBot не получен2');

		$bot->request('https://money.yandex.ru/phone');
		$bot->loadCookies();
		$bot->request('https://money.yandex.ru/phone');
		sleep(3);

		$url = $params['termUrlProvider'];
		$postArr = [
			'MD' => $params['MD'],
			'PaRes' => $params['PaRes'],
		];

		$content = $bot->requestPost($url, $postArr);
		sleep(10);

		for($i=1; $i<=3; $i++)
		{
			$content = $bot->getCurrentContent();
			$currentUrl = $bot->driver->getCurrentURL();

			if(preg_match('!/processing/3ds!', $currentUrl))
			{
				$bot->request($currentUrl);
				$result['status'] = 'wait';
				sleep(10);
			}
			else
			{
				$result = self::subCheckYandex($currentUrl, $content, $params['orderIdProvider']);
				break;
			}

		}
		return $result;
	}

	private static function subCheckYandex($currentUrl, $content, $orderIdYandex)
	{
		$result = [];

		if(preg_match('!<h1 class="title title_level_1 docbook-title">(.+?)</h1><p class="paragraph docbook-para inline">(.+?)</p><p class="paragraph docbook-para inline">!', $content, $match))
		{
			$errorTitle = $match[1];
			$errorText = $match[2];

			if($errorText and mb_strlen($errorText) < 200)
			{
				$result['msg'] = $errorText;
				$result['status'] = 'error';
			}
			else
			{

				return self::cardError('техническая ошибка35', []);
			}
		}
		elseif(
			//preg_match('!<h1 class="title title_last_yes">(.+?)</h1>!', $content) and
			preg_match('!payments/internal/success\?orderId='.$orderIdYandex.'!', $currentUrl)
		)
		{
			$result['status'] = 'success';
		}
		else
		{

			return self::cardError('техническая ошибка36', []);

		}

		return $result;
	}

	private static function getBankUrlTele2($params)
	{
		if(self::TELE2_METHOD == 'selenium')
			return self::getBankUrlTele2Selenium($params);

		$result = [];
		//$config = Yii::app()->getModule('sim')->config;


		$params['amount'] *= 1;

		if(!$params['proxy'])
			return self::cardError('техническая ошибка 1', $params, false, 'Tele2Error0 нет прокси');

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');

		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser'].$params['proxy']).'.txt';

		//очистить куки перед каждой заявкой
		//file_put_contents($sender->cookieFile, '');
		$sender->pause = 0;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		//$sender->browser = $params['browser'];
		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send('https://msk.tele2.ru/payments/refill', false, $params['proxy']);

//		if(!preg_match('!<input name="__RequestVerificationToken" type="hidden" value="(.+?)"!', $content, $res))
//			return self::cardError('Error1', $params, $sender, 'MtsError1 content: '.$content);

		sleep(rand(5, 7));
		$postParams = [
			'msisdn'=>'7'.$params['phoneNumber'],
			'amount'=>ceil($params['amount'] * 100),
			'pan'=>$params['cardNumber'],
			'expirationDate'=>['month'=>$params['cardM'], 'year'=>$params['cardY']],
			'cvv'=>$params['cardCvv'],
			'receive'=>["email"=>null,"phoneNumber"=>null],
		];

		$postData = json_encode($postParams);

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: application/json',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'Origin: https://oplata.tele2.ru',
			'Content-Type: application/json',
			'Referer: https://oplata.tele2.ru/',
		];

		$content = $sender->send('https://public.oplata.tele2.ru/api/v1/payments/3ds/prepare',
			$postData, $params['proxy']);

		if(!$statusArr = @json_decode($content, true) or !$statusArr['paymentId'])
			return self::cardError('Error2', $params, $sender, 'Tele2Error2 content: '.$content);

		for($i=1; $i<=5; $i++)
		{
			sleep(5);

			$content = $sender->send('https://public.oplata.tele2.ru/api/v1/payments/'.$statusArr['paymentId'].'/status',
				false, $params['proxy']);

			$arr = @json_decode($content, true);

			if(!$arr)
				return self::cardError('error3', $params, $sender, 'Tele2 error3 content: '.$content);

			if($arr['status'] != 'Pending' or $arr['Pending'])
				break;
		}

		$content = $sender->send('https://public.oplata.tele2.ru/api/v1/payments/'.$statusArr['paymentId'].'/3ds-data',
			false, $params['proxy']);


		if(!$arr = @json_decode($content, true) or !$arr['md'])
		{
			//превышено
			if(preg_match('!Платёж находится в недопустимом статусе 7!', $content))
			{
				self::$lastErrorCode = self::ERROR_WAIT;
				return self::cardError('error5', $params, $sender, 'Tele2 лимит платежей на номер '
					.$params['phoneNumber'] );
			}

			return self::cardError('error4', $params, $sender, 'Tele2Error4 content: '.$content);
		}


		$redirParams = [
			'MD' => $arr['md'],
			'PaReq' => $arr['pareq'],
			'TermUrl' => $params['checkUrl'],
		];

		//test замена провайдера в банке
		$paReq = $redirParams['PaReq'];
		$strOut = gzuncompress(base64_decode($paReq));
		$strOut = preg_replace('!<name>.+?</name>!', '<name>PAYMENT</name>', $strOut);
		$redirParams['PaReq'] = base64_encode(gzcompress($strOut));


		//для редиректа постом
		$result = [
			'url'=>$arr['redirectUrl'],
			'postArr'=>$redirParams,
			'orderIdProvider'=>$statusArr['paymentId'],
//			'termUrlProvider'=>$arr['redirUrl'],
		];

		self::log('получена ссылка на банк Tele2:(orderId='.$params['orderId'].') '
			.$result['url'].' params='.arr2str($params));

		return $result;
	}

	private static function checkBankUrlTele2($params)
	{
//		if(self::TELE2_METHOD == 'selenium')
//			return self::checkBankUrlTele2Selenium($params);

		$result = [
			'status' => '',
			'msg' => '',	//если ошибка
		];

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');


		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser'].$params['proxy']).'.txt';
		$sender->pause = 1;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		$refererParts = parse_url(strtolower($params['bankUrl']));
		$referer = $refererParts['scheme'].'://'.$refererParts['host'].'/';

		$url = 'https://public.oplata.tele2.ru/api/v1/payments/3ds/confirm/'.$params['orderIdProvider'].'/payment';

		$postArr = [
			'MD' => $params['MD'],
			'PaRes' => $params['PaRes'],
		];

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Referer: '.$referer,
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send($url, http_build_query($postArr), $params['proxy']);

		$header = $sender->info['header'][0];

		if(!preg_match('!Location:\s*(.+)!i', $header, $match))
		{

			return self::cardError('error1', $params, false, 'Tele2errorConfirm1, content: '.$content);
		}

		$url = 'https://public.oplata.tele2.ru/api/v1/payments/'.$params['orderIdProvider'].'/status';

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: application/json',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'Referer: https://oplata.tele2.ru/',
		];

		$arr = [];

		for($i=1; $i<=10; $i++)
		{
			$content = $sender->send($url, false, $params['proxy']);

			//{"status":"Rejected","reason":{"name":"8","description":"Не удалось оплатить. Повторите позднее."}}
			if(!$arr = @json_decode($content, true))
				return self::cardError('error2', $params, false, 'Tele2errorConfirm2, content: '.$content);

			if($arr['status'] == 'Pending' or $arr['status'] == 'Waiting3Ds')
				sleep(3);
			else
				break;
		}

		if($arr['status'] == 'Rejected')
		{
			$result['status'] = self::STATUS_ERROR;
			$result['msg'] = $arr['reason']['description'];
		}
		elseif($arr['status'] == 'Succeed')
		{
			$result['status'] = self::STATUS_SUCCESS;
			$result['msg'] = $arr['reason']['description'];
		}
		elseif($arr['status'] == 'Pending')
		{
			$result['status'] = self::STATUS_WAIT;
			$result['msg'] = 'повторите запрос позже';
			self::log('Tele2 pending: '.$content);
		}
		else
		{
			return self::cardError('error3', $params, false, 'Tele2errorConfirm3, content: '
				.$content.', header: '.$sender->info['header'][0]);
		}

//		{
//			self::log('test: '.$match[1]);
//			//получаем причину отказа
//
//			$content = $sender->send('https://payment.mts.ru'.trim($match[1]), false, $params['proxy']);
//			$header = $sender->info['header'][0];
//			$httpCode = $sender->info['httpCode'][0];
//
//			if(!preg_match('!<div class="b-content__title b-content__red">(.+)</div>!', $content, $match))
//			{
//				self::log('checkBankUrlMts Возможно бан прокси или браузера orderId='.$params['orderId']
//					.', httpCode='.$httpCode.', header='.$header);
//			}
//
//			$error = trim(@html_entity_decode($match[1]));
//
//			if($error and mb_strlen($error) < 200)
//			{
//				$result['msg'] = $error;
//
//				//test
//				if($result['msg'] == 'Банк отклонил платеж. Попробуйте позже')
//					$result['msg'] = 'Банк отклонил платеж. Возможно недостаточно средств или неверный CVV-код';
//			}
//
//			$errorMsg = ($result['msg']) ? $result['msg'] : $error;
//
//			self::log('checkBankUrlMtsFail причина: |'.$errorMsg.'|, orderId='
//				.$params['orderId'].', header='.$header);
//			$result['status'] = self::STATUS_ERROR;
//		}
//		elseif(preg_match('!Location:\s*(/success/.+)!i', $header))
//		{
//			$sender->send('https://payment.mts.ru'.$match[1], false, $params['proxy'], $url);
//
//			self::log('checkBankUrlMtsSuccess header: '.$header.', orderId='.$params['orderId']);
//			$result['status'] = self::STATUS_SUCCESS;
//		}
//		elseif(preg_match('!Location:\s*/error/brokenlink!i', $header))
//		{
//			$sender->send('https://payment.mts.ru'.$match[1], false, $params['proxy'], $url);
//
//			self::log('checkBankUrlMts Error1 header='.$header.',content= orderId='.$params['orderId']);
//			return false;
//		}
//		else
//		{
//			self::log('checkBankUrlMts ERROR content='.$content.', httpCode='.$sender->info['httpCode'][0]
//				.', header='.$header.', url='.$url.', postData='.arr2str($postArr));
//			return false;
//		}

		return $result;
	}

	private static function getBankUrlTele2Selenium($params)
	{
		set_time_limit(180);
		session_write_close();

		$result = [];

		$params['paymentType'] = self::PAYMENT_TYPE_TELE2;

		if(!$bot = SimBot::getBot($params))
			return self::cardError('техническая ошибка100', $params, false, 'Mts SimBot не получен1');

//		$bot->request('https://msk.tele2.ru/payments/refill');
//		$bot->loadCookies();
//		$bot->request('https://msk.tele2.ru/payments/refill');
//		$content = $bot->request('https://yandex.ru/');
//		$content = $bot->request('https://whoer.net');

		$content = $bot->request('https://msk.tele2.ru/payments/refill');


		//тел
		if(!$element =$bot->findElement("//input[@data-reactid='292']", 10))
			return self::cardError("техническая ошибка101", $params, false, 'mtsSelen error101');

		prrd($element);

		if(!$bot->sendKeys($element, $params['phoneNumber']))
			return self::cardError("техническая ошибка102", $params, false, 'mtsSelen error102');

		//сумма
		if(!$element =$bot->findElement("//input[@data-type='amount']"))
			return self::cardError("техническая ошибка103", $params, false, 'mtsSelen error103');

		$element->clear();

		if(!$bot->sendKeys($element, $params['amount']))
			return self::cardError("техническая ошибка104", $params, false, 'mtsSelen error104');


//		$script = "$('input[data-type=\"amount\"]').val('{$params['amount']}')";
//		$bot->driver->executeScript($script);

		sleep(1);


		if(!$element = $bot->findElement("//input[@value='ANONYMOUS_CARD']"))
			return self::cardError("техническая ошибка105", $params, false, 'mtsSelen error105');

		$bot->click($element);

		sleep(1);


		//карта
		if(!$element = $bot->findElement("//input[@name='Pan']"))
			return self::cardError("техническая ошибка106", $params, false, 'mtsSelen error106');

		$element->clear();

		$script = "$('input[name=\"Pan\"]').val('{$params['cardNumber']}')";
		$bot->driver->executeScript($script);

		if(!$element =$bot->findElement("//input[@name='ExpiryMonth']"))
			return self::cardError("техническая ошибка108", $params, false, 'mtsSelen error108');

		if(!$bot->sendKeys($element, $params['cardM']))
			return self::cardError("техническая ошибка109", $params, false, 'mtsSelen error109');


		if(!$element =$bot->findElement("//input[@name='ExpiryYear']"))
			return self::cardError("техническая ошибка110", $params, false, 'mtsSelen error110');

		if(!$bot->sendKeys($element, $params['cardY']))
			return self::cardError("техническая ошибка111", $params, false, 'mtsSelen error111');


		if(!$element =$bot->findElement("//input[@name='Cvc']"))
			return self::cardError("техническая ошибка112", $params, false, 'mtsSelen error112');

		if(!$bot->sendKeys($element, $params['cardCvv']))
			return self::cardError("техническая ошибка113", $params, false, 'mtsSelen error113');

		//$bot->includeJs('', 'https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js');
		//$bot->includeJs('', 'https://94.140.125.237/artur/mtsExtra.js');
		//$bot->includeJs('alert(\'fff\');');
		//var_dump($bot->includeJs(file_get_contents(BASE_DIR.'mtsExtra.js')));

		$script = <<<EOD
			var form = $('#payment_form');
			var url = 'https://payment.mts.ru/pay/phone';
			return $.ajax({
				type: 'POST',
				url: '/payment/pay',
				data: form.serialize(),
				dataType: 'json',
				cache: false,
				timeout: 0,
				async: false,
			}).responseText;
EOD;

		$content = $bot->driver->executeScript($script);
		$arr = json_decode($content, true);

		if(!$arr['model']['acsUrl'])
			return self::cardError("техническая ошибка114", $params, false, 'mtsSelen не получен url: '.$content);

		$result = [
			'url' => $arr['model']['acsUrl'],
			'postArr' => [
				'MD' => $arr['model']['md'],
				'MdOrder' => $arr['model']['mdOrder'],
				'PaReq' => $arr['model']['paReq'],
				'TermUrl' => $params['checkUrl'],
			],
			'botId'=>$bot->id,
		];


		self::log('получена ссылка на банк MtsSelen: '.$result['url'].' params='.arr2str($params).',  orderId='.$params['orderId']);


		return $result;
	}


	private static function getBankUrlMtsApi($params)
	{
		set_time_limit(180);
		session_write_close();
		$config = Yii::app()->getModule('sim')->config;

		$result = [];

		$params['paymentType'] = self::PAYMENT_TYPE_MTS;

		$sender = new Sender();
		$sender->timeout = 60;
		$sender->followLocation = false;
		$sender->useCookie = false;


		$apiUrl = $config['mtsApiUrlGet'];


		$postData = http_build_query([
			'amount' => $params['amount'],
			'phoneNumber' => $params['phoneNumber'],
			'cardNumber' => $params['cardNumber'],
			'cardM' => $params['cardM'],
			'cardY' => $params['cardY'],
			'cardCvv' => $params['cardCvv'],
			'proxy' => $params['proxy'],
			'username' => 'ANONYMOUS CARD',
			'userAgent' => $params['browser'],
			'phoneType' => 'tele2',
		]);

		$content = $sender->send($apiUrl, $postData);

		if(!$arr = json_decode($content, true) or !$arr['url'])
			return self::cardError('error1', $params, $sender, 'MtsApi error1, content: '.$content);


		$result = [
			'url' => $arr['url'],
			'postArr' => $arr['postParams'],
		];

//		$result['postArr']['PaReq'] = $params['paReq'];
//		$result['postArr']['MD'] = $params['md'];
//		$result['postArr']['MdOrder'] = $params['mdOrder'];
		$result['postArr']['TermUrl'] = $params['checkUrl'];


		self::log('получена ссылка на банк MtsApi: '.$result['url'].' params='.arr2str($params).',  orderId='.$params['orderId']);

		return $result;
	}

	public static function checkBankUrlMtsApi($params)
	{
		set_time_limit(180);
		session_write_close();
		$config = Yii::app()->getModule('sim')->config;

		$result = [
			'status' => '',
			'msg' => '',	//если ошибка
		];

		$sender = new Sender();
		$sender->timeout = 60;
		$sender->followLocation = false;
		$sender->useCookie = false;

		$apiUrl = $config['mtsApiUrlCheck'];

		$content = $sender->send($apiUrl, http_build_query([
			'MD' => $params['MD'],
			'PaRes' => $params['PaRes'],
			'userAgent' => $params['browser'],
			'proxy' => $params['proxy'],
			'referer' => $params['referer'],
		]));

		if(!$arr = @json_decode($content, true) or !$arr['status'])
			return self::cardError('error2', $params, $sender, 'MtsApi error2, content: '.$content);


		return $arr;
	}


	private static function getBankUrlA3($params)
	{
		$params['amount'] *= 1;

		if(!$params['proxy'])
			return self::cardError('техническая ошибка 1', $params, false, 'A3Error0 нет прокси');

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');

		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser'].$params['proxy']).'.txt';


		if(preg_match('!901\d+!', $params['phoneNumber']))
			return self::cardError("техническая ошибка 1", $params, false, 'A3 error: Номера формата 901... не подходят');

		//очистить куки перед каждой заявкой
		//file_put_contents($sender->cookieFile, '');
		$sender->pause = 0;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

//		0)
		$url = 'https://www.a-3.ru/pay_mobile/';
		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, null, $params['proxy']);

		if(!preg_match('!main\.(.+?)\.js!iu', $content, $matches))
			return self::cardError("error0", $params, false, 'A3 error0, content:'.$content);

		$hashInScritpName = $matches[1];


//		1)
		$url = 'https://www.a-3.ru/dist/main.'.$hashInScritpName.'.js';
		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, null, $params['proxy']);

		if(!preg_match('!strategy_id\:(\d+),sum!iu', $content, $matches))
			return self::cardError("error1", $params, false, 'A3 javascript error1, content:'.$content);

		$strategyId = $matches[1];

//		2)
		$url = 'https://www.a-3.ru/dist/Chat.'.$hashInScritpName.'.js';
		$content = $sender->send($url, null, $params['proxy']);

		if(!preg_match('!\{paidservice_id\:(\d+),partner_id\:(\d+)\}!iu', $content, $matches))
			return self::cardError("error2", $params, false, 'A3 error2, content:'.$content);

		$paidserviceId = $matches[1];
		$partnerId = $matches[2];

//		3)
		$url = 'https://www.a-3.ru/front/msp/init_step_sequence_obr.do';
		$postData = 'channel=0&paidservice_id='.$paidserviceId.'&partner_id='.$partnerId.'&phone_number='
			.$params['phoneNumber'].'&strategy_id='.$strategyId.'&sum='.$params['amount'];


		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'Origin: https://www.a-3.ru',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

		if(!$data = @json_decode($content, true))
			return self::cardError("error3", $params, false, 'A3 error3, content:'.$content);

		if(!$operationId = $data['item']['operation_id'])
			return self::cardError("error3.1", $params, false, 'A3 error3.1 , content:'.$content);

//		4)
		$url = 'https://www.a-3.ru/front/msp/get_current_step.do?operation_id='.$operationId;

		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, false, $params['proxy']);

//		5)
		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'Origin: https://www.a-3.ru',
			'Content-Length: 0',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$url = rawurldecode('https://www.a-3.ru/front/msp/store_step.do?AMOUNT%2410%2420='.
			$params['amount'].'&NUMBER%2410%2410='.$params['phoneNumber'].'&operation_id='.$operationId.'&phone_number=&sum='.$params['amount']);

		$content = $sender->send($url, [], $params['proxy']);

//		6)
		$url = 'https://www.a-3.ru/front/msp/get_session_data.do';
		$sender->additionalHeaders = false;

		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/'
		];

		$content = $sender->send($url, false, $params['proxy']);

		if(!$dataArr = @json_decode($content, true))
			return self::cardError("error6", $params, false, 'A3 error6');

		$dataValue = $dataArr['data'];

//		7)
		$url = 'https://www.a-3.ru/basket-service/v1/basket/check?multistep_id='.$dataValue;
		$content = $sender->send($url, false, $params['proxy']);

		if(!$dataArr = @json_decode($content, true))
			return self::cardError("error7", $params, false, 'A3 error7');

		if($dataArr['response_status'] !== 1 or $dataArr['response_status']['basket_id' !== ''])
			return self::cardError("error7.1", $params, false, 'A3 error7.1');

//		8)
		$url = 'https://www.a-3.ru/basket-service/v1/basket?multistep_id='.$dataValue;

		$sender->additionalHeaders = false;

		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'Origin: https://www.a-3.ru',
			'Content-Length: 0'
		];

		$content = $sender->send($url, [], $params['proxy']);

		if(!$dataArr = @json_decode($content, true))
			return self::cardError("error8", $params, false, 'A3 error8');

		if(!$basketId = $dataArr['response_data']['basket_id'])
			return self::cardError("error8.1", $params, false, 'A3 error8.1');

		sleep(1);

//		9)
		$url = 'https://www.a-3.ru/basket-service/v1/basket/'.$basketId.'/item/'.$operationId;

		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'Origin: https://www.a-3.ru',
			'Content-Length: 0',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, [], $params['proxy']);

		if(!$dataArr = @json_decode($content, true) or $dataArr['response_status'] !== 1)
		{
			return self::cardError("error9", $params, false, 'A3 error9, $content: '.$content);
		}

//		10)
		$url = 'https://www.a-3.ru/basket-service/v1/basket/'.$basketId;

		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache'
		];

		$content = $sender->send($url, false, $params['proxy']);

		if(!$dataArr = @json_decode($content, true) or $dataArr['response_status'] !== 1)
			return self::cardError("error10", $params, false, 'A3 error10');

		$paidServiceId = $dataArr['response_data']['description'][0]['paidservice_id'];
		$fee = $dataArr['response_data']['total_fee']*1;
		$totalSum = $params['amount'] + $fee;

//		11)
		$url = 'https://www.a-3.ru/frame/?basketId='.$basketId.'&paidServices='.
			$paidServiceId.'&source=a3&sum='.$totalSum.'&toolListOperId='.$operationId;

		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, false, $params['proxy']);

//		12)
		$url = 'https://www.a-3.ru/front/operation/offer?phoneNumber='.$params['phoneNumber'];
		$sender->additionalHeaders = false;

		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, false, $params['proxy']);

		if(!$dataArr = @json_decode($content, true) or $dataArr['result'] != 1)
			return self::cardError("error12", $params, false, 'A3 error12');

//		13)
		$url = 'https://www.a-3.ru/basket-service/v1/basket/'.$basketId;
		$sender->additionalHeaders = false;

		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'Content-Length: 0'
		];

		$content = $sender->send($url, false, $params['proxy']);

//		14)
		$url = 'https://www.a-3.ru/front/msp/tool_add.do';
		$postData = 'cvv='.$params['cardCvv'].'&exp='.$params['cardY'].$params['cardM'].'&phone='
			.$params['phoneNumber'].'&number='.$params['cardNumber'].'&operation_id='.$operationId;

		$sender->additionalHeaders = [
			'Host: www.a-3.ru',
			'User-Agent: '.$params['browser'],
			'Accept: */*',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://www.a-3.ru/pay_mobile/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

		/**
		 * может быть ошибка такого вида
		 *
		content: {
		 	"response_status":0,
		 	"response_data":"Превышен лимит на количество операций за услуги \"Теле2 (TELE2)\".\nПроведение следующей операции возможно после \"14:00 14.12.2019\". Для получения дополнительной информации обратитесь в Контакт-Центр 8 (800) 100 39 00."}
		 */

		if(!$dataArr = @json_decode($content, true) or !isset($dataArr['response_data']['transaction_id']))
		{
			//временно тут. потом сделать отдельный код и надо собирать инфу отдельно для каждого поставщика
			$account = SimTransaction::getModel(['order_id'=>$params['orderId']])->account;
			$account->status = SimAccount::STATUS_DAY_LIMIT;
			$account->save();

			return self::cardError("error14.1", $params, false, 'A3 error14.1: '.$content);
		}
		elseif(is_array($dataArr) and $dataArr['response_status'] == 0)
		{
			self::cardError("error14.2 a3", $params, false, 'A3 error14.2: '.$content);
//			if(preg_match('!Превышен лимит!iu', $content))
//				continue;

		}

//		15)
		$transactionIdA3 = $dataArr['response_data']['transaction_id'];
		$tryCount = 15;

		for($i = 0; $i < $tryCount; $i++)
		{
			sleep(2);
			//step 12
			$url = 'https://www.a-3.ru/front/msp/apply_3ds.do';
			$sender->additionalHeaders = false;
			$postData = 'operation_id='.$operationId;
			$sender->additionalHeaders = [
				'Host: www.a-3.ru',
				'User-Agent: '.$params['browser'],
				'Accept: */*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'Accept-Encoding: gzip, deflate, br',
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: '.strlen($postData),
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://www.a-3.ru/pay_mobile/',
				'Pragma: no-cache',
				'Cache-Control: no-cache'
			];

			$content = $sender->send($url, $postData, $params['proxy']);

			if(!$dataArr = @json_decode($content, true))
			{
				return self::cardError("error15.1", $params, false, 'A3 error15.1');
			}
			elseif(isset($dataArr['response_data']['pareq']))
			{
				$formData = base64_decode($dataArr['response_data']['pareq']);

//					exit($formData);

				if(preg_match('!name="mainform"\s+action="(.+?)"!iu', $formData, $matches))
					$mainFormUrl = $matches[1];
				else
					return self::cardError("error15.2", $params, false, 'A3 error15.2');

				if(preg_match('!name="PaReq" style="display:none">(.+?)<!iu', $formData, $matches))
					$paReq = $matches[1];
				else
					return self::cardError("error15.3", $params, false, 'A3 error15.3');

				if(preg_match('!name="TermUrl" value="(.+?)"!iu', $formData, $matches))
					$termUrl = $matches[1];
				else
					return self::cardError("error15.4", $params, false, 'A3 error15.4');

				if(preg_match('!name="MD" value="(.+?)"!iu', $formData, $matches))
					$md = $matches[1];
				else
					return self::cardError("error15.5", $params, false, 'A3 error15.5');

				//замена провайдера в банке
				$strOut = gzuncompress(base64_decode($paReq));
				$strOut = preg_replace('!<name>.+?</name>!', '<name>Paymentprocessing</name>', $strOut);
				$updatedPareq = base64_encode(gzcompress($strOut));

				$checkArr = [
					'basketId' => $basketId,
					'paidServices' => $paidServiceId,
					'source' => 'a3',
					'sum' => $totalSum,
					'transactionIdA3' => $transactionIdA3,
					'termUrl' => $termUrl,
					'cardNumber' => $params['cardNumber'],
					'operationId' => $operationId,
				];

				//TODO: тут данные выводились в форму, под апи вроде бы так же будет

				$redirParams = [
					'MD' => $md,
					'PaReq' => $updatedPareq,
					'TermUrl' => $params['checkUrl'],
				];

				$result = [
					'url'=>$mainFormUrl,
					'postArr'=>$redirParams,

					'basketId' => $basketId,
					'paidServices' => $paidServiceId,
					'sum' => $totalSum,
					'transactionIdA3' => $transactionIdA3,
					'termUrl' => $termUrl,
					'operationId' => $operationId,
				];

				self::log('получена ссылка на банк A3:(orderId='.$params['orderId'].') '
					.$result['url'].' params='.arr2str($params));

				return $result;
			}
			else
				continue;
		}

		return self::cardError("error15.6", $params, false, 'A3 error15.6');

//		return false;
	}


	private static function checkBankUrlA3($params)
	{
		$result = [
			'status' => '',
			'msg' => '',	//если ошибка
		];

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'cardPay/'))
			mkdir($runtimePath.'cardPay/');

		if(!file_exists($runtimePath.'cardPay/cookie/'))
			mkdir($runtimePath.'cardPay/cookie/');


		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cardPay/cookie/'.md5($params['browser'].$params['proxy']).'.txt';
		$sender->pause = 1;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		$refererParts = parse_url(strtolower($params['bankUrl']));
		$referer = $refererParts['scheme'].'://'.$refererParts['host'].'/';

		$sender->additionalHeaders = [
			'Host: 3ds.payment.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'Referer: '.$referer,
		];

		$postArr = [
			'MD' => $params['MD'],
			'PaRes' => $params['PaRes'],
		];

		$content = $sender->send($params['termUrl'], http_build_query($postArr), $params['proxy']);

		//перехватываем ответ от банка
		if(preg_match('!(\{.+?\})!iu', $content, $matches))
		{
			/*
			 * ответ такого вида
			 * {"AMOUNT":"120.00","CURRENCY":"RUB","ORDER":"116082672","DESC":"Платеж в пользу Теле2 (TELE2)"
			 * ,"MERCH_NAME":"A3 payment agent","MERCHANT":"000739926441810","TERMINAL":"26441810","EMAIL":""
			 * ,"TRTYPE":"1","TIMESTAMP":"20191204211752","NONCE":"09206342453062BBEA3853BBB7E90568"
			 * ,"BACKREF":"https://www.a-3.ru:443/psBankReceiver/callback/?gogo=116082672","RESULT":"0","RC":"00"
			 * ,"RCTEXT":"Approved","AUTHCODE":"DNZ55Z","RRN":"933992594906","INT_REF":"9D9EA3EA2D2B7DFB"
			 * ,"P_SIGN":"B04B1EDD8E5E555D12CE945B5A548B19A9379F7E","EXT_DIAG_CODE":"NONE"}
			 *
			 */
			$resArr = json_decode($matches[1], true);

			if($resArr["RCTEXT"] == "Approved")
			{
				$result['status'] = self::STATUS_SUCCESS;
				$result['msg'] = 'success';
			}
			elseif($resArr["RCTEXT"] == "Authentication failed")
			{
			/*
				 [AMOUNT] => 40.00
				[ORG_AMOUNT] =>
				[CURRENCY] => RUB
				[ORDER] => 116652929
				[DESC] => Платеж в пользу Теле2 (TELE2)
				[MERCH_NAME] => A3 payment agent
				[MERCHANT] => 000481426479224
				[TERMINAL] => 26479224
				[EMAIL] =>
				[TRTYPE] => 1
				[TIMESTAMP] => 20191210175629
				[NONCE] => 0920772709163C85A8275CC55A7AE3C0
				[BACKREF] => https://www.a-3.ru:443/psBankReceiver/callback/?gogo=116652929
				[RESULT] => 3
				[RC] => -19
				[RCTEXT] => Authentication failed
				[AUTHCODE] =>
				[RRN] =>
				[INT_REF] =>
				[CARD] => 4890XXXXXXXX7845
				[P_SIGN] => 808BCAFB87A1779F74CA7E25A41D69523EAFBAC7
				[EXT_DIAG_CODE] => AS_FAIL
			*/
				$result['status'] = self::STATUS_ERROR;
				$result['msg'] = 'платеж отменен';
			}
			elseif($resArr['RCTEXT'] == 'Transaction declined')
			{
				/*
				 {

					"AMOUNT":"60.00",
					"ORG_AMOUNT":"",
					"CURRENCY":"RUB",
					"ORDER":"116654015",
					"DESC":"Платеж в пользу Теле2 (TELE2)",
					"MERCH_NAME":"A3 payment agent",
					"MERCHANT":"000481426479224",
					"TERMINAL":"26479224",
					"EMAIL":"",
					"TRTYPE":"1",
					"TIMESTAMP":"20191210175950",
					"NONCE":"092077295919349F3C50F67172D92FB9",
					"BACKREF":"https://www.a-3.ru:443/psBankReceiver/callback/?gogo=116654015",
					"RESULT":"2",
					"RC":"05",
					"RCTEXT":"Transaction declined",
					"AUTHCODE":"",
					"RRN":"934492437296",
					"INT_REF":"D710D88D9674C5FC",
					"CARD":"4890XXXXXXXX7845",
					"P_SIGN":"59DA087DB64114E0D100B136EB65F1CB8AE0B284",
					"EXT_DIAG_CODE":"NONE"
					}
				 */
				$result['status'] = self::STATUS_ERROR;
				$result['msg'] = 'платеж отклонен, возможно неверный CVV-код';
			}
			elseif($resArr['RCTEXT'] == 'Not sufficient funds')
			{
				$result['status'] = self::STATUS_ERROR;
				$result['msg'] = 'недостаточно средств';
			}
			else
			{
				self::log('A3 exception1 $resArr: '.arr2str($resArr));
				$result['status'] = self::STATUS_ERROR;
				$result['msg'] = 'error1';
			}
		}
		else
		{
			self::log('A3 exception2, $postArr:'.arr2str($postArr).', $content: '.$content);
			$result['status'] = self::STATUS_ERROR;
			$result['msg'] = 'error2';
		}


		return $result;
	}

	/**
	 * метод для получения параметров формы пополнения с банковской карты на номер мегафон
	 * @param $params
	 *
	  	$params['amount'] = 100;
		$params['phoneNumber'] = '9274820100';
		$params['cardY'] = '2020';
		$params['cardM'] = '12';
		$params['cardNumber'] = '4890494707844221';
		$params['cardCvv'] = '305';
		$params['browser'] = 'User-Agent: '.$_SERVER['HTTP_USER_AGENT'];
		$params['proxy'] = 'dpNS6XtyUo:proxmail1123123@91.107.119.79:42071';
	  	$params['proxyType'] = 'http';
	 *
	 * @return array
	 */
	public static function getBankUrlMegafon($params)
	{
//		$params['amount'] = 100;
//		$params['phoneNumber'] = '9274820100';
//		$params['cardY'] = '2020';
//		$params['cardM'] = '12';
//		$params['cardNumber'] = '4890494707844221';
//		$params['cardCvv'] = '305';
//		$params['browser'] = 'User-Agent: '.$_SERVER['HTTP_USER_AGENT'];
//		$params['proxy'] = 'dpNS6XtyUo:proxmail1123123@91.107.119.79:42071';
//		$params['proxyType'] = 'http';

		if(!$params['amount'] or !$params['phoneNumber'] or !$params['cardY'] or !$params['cardM'] or
			!$params['cardNumber'] or !$params['cardCvv'] or !$params['browser'] or !$params['proxy'] or !$params['proxyType'])
		{
			return self::cardError('техническая ошибка 0, не заданы все параметры', $params, false, 'MegafonError0  не заданы все параметры');
		}

		$tryCount = 2; //число попыток получения ответа от антикаптчи
		$captchaWaitTime = 8; //пауза перед опросом получения ответа каптчи

		$result = [];
		$config = Yii::app()->getModule('sim')->config;

		$params['amount'] *= 1;

		if(!$params['proxy'])
			return self::cardError('техническая ошибка 1', $params, false, 'MegafonError0 нет прокси');

		set_time_limit(120);

		$runtimePath = DIR_ROOT.'protected/runtime/';

		if(!file_exists($runtimePath.'Megafon/'))
			mkdir($runtimePath.'Megafon/');

		if(!file_exists($runtimePath.'Megafon/cookie/'))
			mkdir($runtimePath.'Megafon/cookie/');

		$sender = new Sender();
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'Megafon/cookie/'.md5($params['browser'].$params['proxy']).'.txt';

		//очистить куки перед каждой заявкой
		//file_put_contents($sender->cookieFile, '');
		$sender->pause = 0;
		$sender->followLocation = true;
		$sender->proxyType = $params['proxyType'];

		//этим запросом проверяем будет ли каптча, если да - в ответе будет ее id
		$url = 'https://moscow.megafon.ru/api/captcha/payonlineform/get/';
		$sender->additionalHeaders = [
			'Host: moscow.megafon.ru',
			'User-Agent: '.$params['browser'],
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/json; charset=utf-8',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1'
		];

		$content = $sender->send($url, false, $params['proxy']);
		$contentArr = @json_decode($content, 1);

		$captchaCode = '';
		$captchaId = '';

		//через сервис получаем ответ на простую капчу
		if($contentArr['captchaId'] !== 'null')
		{
			$captchaId = $contentArr['captchaId'];
			$captchaUrl = 'https://moscow.megafon.ru/api/captcha/payonlineform/'.$contentArr['captchaId'].'.png';
			$sender->additionalHeaders = [
				'Host: moscow.megafon.ru',
				'User-Agent: '.$params['browser'],
				'Accept: image/webp,*/*',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'DNT: 1',
				'Connection: keep-alive',
				'Referer: https://moscow.megafon.ru/pay/online_payment_credit_card/'
			];

			$captchaImageBase64 = $sender->send($captchaUrl, false, $params['proxy']);

			//url для решения каптчи на сервисе антикаптчи
			$url = 'https://rucaptcha.com/in.php';

			$postData = [
				'method'=>'base64',
				'key'=>$config['captchaKey'],
				'body'=>base64_encode($captchaImageBase64),
				'json'=>1,
				'numeric'=>1,
			];

			$captchaResponce = $sender->send($url, http_build_query($postData), $params['proxy']);
			$captchaResponceArr = @json_decode($captchaResponce, 1);

			if(!$captchaResponceArr['status'] or !$captchaResponceArr['status'] == 1)
			{
				return self::cardError('error1', $postData, $sender, 'error1 code: Error sending captcha '.$captchaResponce);
			}

			$requestIdCaptcha = $captchaResponceArr['request'];

			for($i = 0; $i < $tryCount; $i++)
			{
				sleep($captchaWaitTime);
				//получение разгаданной каптчи
				$url = 'https://rucaptcha.com/res.php?key='.$postData['key'].'&json=1&action=get&id='.$requestIdCaptcha;

				$captchaAnswer = @json_decode($content = $sender->send($url, false, $params['proxy']), 1);

				if(!$captchaAnswer['status'] or $captchaAnswer['status'] != 1)
					continue;
				else
					break;
			}

			//разгаданное число
			$captchaCode = $captchaAnswer['request'];

		}

		//парсим названия скриптов (с динамическими id в названии)
		$url = 'https://moscow.megafon.ru/xpayment.action';
		$postArr = [
			'number' => $params['phoneNumber'],
			'amount' => $params['amount'],
			'lang' => 'rus',
			'__captcha[code]' => $captchaCode, //могут быть пустыми, когда нет каптчи
			'__captcha[id]' => $captchaId, //могут быть пустыми, когда нет каптчи
			'__captcha[form]' => 'payonlineform'
		];

		$postData = http_build_query($postArr);

		$sender->additionalHeaders = null;
		$sender->additionalHeaders = [
			'Host: moscow.megafon.ru',
			'User-Agent: '.$params['browser'],
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'Content-Length: '.strlen($postData),
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://moscow.megafon.ru/pay/online_payment_credit_card/',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, $postData, $params['proxy']);

		if(!$data=@json_decode($content, 1) or !$data['redirect'])
		{
			return self::cardError('error2', $postData, $sender, 'error2 : Error redirect params '.$content);
		}

		$url = $data['redirect'];
		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://moscow.megafon.ru/',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($url, false, $params['proxy']);

		//нужен будет для создания параметра crypto
		$userDataUrl = $sender->info["referer"][0];
		$modContent = html_entity_decode($content);

		if(preg_match('!body data-options="(.+?)" data-localization!', $modContent, $matches))
		{
			$requestData = (@json_decode($matches[1], 1));
		}
		else
			return self::cardError('error3', $postData, $sender, 'error3 : Error data-options, no requestData received '.$modContent);

		//отправляем данные в формате base64 (в котором упакованы данные платежа) и получаем параметр crypto
		$url = 'https://payment.megafon.ru/cryptogramma';
		$sender->followLocation = false;

		//TODO: сделать парсер параметров UserAgent, пока что для одного все введено
		$postDataArr = [
			"fieldsData" => [
				"InPlat_cardNumber" => "{$params['cardNumber']}",
				"InPlat_cardExpirationMonth" => "{$params['cardM']}",
				"InPlat_cardExpirationYear" => "20{$params['cardY']}",
				"InPlat_cardHolder" => "AAA BBB",
				"InPlat_cardCvv" => "{$params['cardCvv']}",
			],

			"userData" => [
				"screen" => [
					"availHeight" => 877,
					"availWidth" => 1395,
					"colorDepth" => 24,
					"pixelDepth" => 24,
					"height" => 900,
					"width" => 1440,
				],

				"navigator" => [
					"appName" => "Netscape",
					"userAgent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:67.0) Gecko/20100101 Firefox/67.0",
					"language" => "ru-RU",
					"platform" => "MacIntel",
					"oscpu" => "Intel Mac OS X 10.14",
					"cpuClass" => "",
					"vendor" => "",
					"vendorSub" => "",
					"product" => "Gecko",
					"productSub" => "20100101",
					"userLanguage" => "",
					"browserLanguage" => "",
					"systemLanguage" => "",
				],

				"plugins" => [],
				"timezone_offset" => -180,
				"time" => round(microtime(true)*1000),
				"url" => $userDataUrl,
			],
			"apikey"=> $requestData['apikey'],
		];

		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			'User-Agent: '.$params['browser'],
			'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:67.0) Gecko/20100101 Firefox/67.0',
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/json',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: '.$userDataUrl,
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$fullPostData = '{"data":"'.base64_encode(json_encode($postDataArr)).'"}';

		$contentArr = @json_decode($content = $sender->send($url, $fullPostData, $params['proxy']), 1);

		$crypto = '';

		if(!preg_match('!Операция выполнена успешно!', $content, $matches) or !$crypto=$contentArr['crypto'])
		{
			return self::cardError('error4', $fullPostData, $sender, 'error4 : Error get crypto '.$content);
		}

		$url = 'https://payment.megafon.ru/vjet/cryptogramma/bs';
		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			'User-Agent: '.$params['browser'],
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/text',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: '.$userDataUrl,
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'TE: Trailers'
		];

		$refillId = preg_replace('![\s\|]!', '', $requestData['refill_id']);

		$postArr = [
			"crypto" => $crypto,
			"browser_encrypted" => false,
			"apikey" => $requestData['apikey'],
			"refill_id" => "$refillId",
			"client_id" => NULL,
			"action_client_id" => NULL,
			"form_request_id" => $requestData['form_request_id'],
			"sum" => $requestData['sum']*100,
			"save_card" => false,
			"user_checked_in_action" => false,
			"bill_phone" => "$refillId",
		];

		$contentArr = @json_decode($content = $sender->send($url, json_encode($postArr), $params['proxy']), true);

		if($contentArr['code'] !== 0 or !$contentArr['url'])
		{
			return self::cardError('error5', $postArr, $sender, 'error5 : Error get url form '.$content);
		}

		$sender->additionalHeaders = [
			'Host: api2.inplat.ru',
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://payment.megafon.ru/',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$content = $sender->send($contentArr['url'], false, $params['proxy']);

		if(!preg_match('!name="live_update" action="(.+?)"!', $content, $matches))
		{
			return self::cardError('error6', [], $sender, 'error6 : Error parse live_update '.$content);
		}

		$liveUpdate = $matches[1];

		if(!preg_match('!name="PaReq" value="(.+?)"!', $content, $matches))
		{
			return self::cardError('error7', [], $sender, 'error7 : Error parse PaReq '.$content);
		}

		//модифицируем PaReq
		$paReq = $matches[1];
		$strOut = gzuncompress(base64_decode($paReq));
		$strOut = preg_replace('!<name>.+?</name>!', '<name>Payment</name>', $strOut);
		$updatedPareq = base64_encode(gzcompress($strOut));

		if(!preg_match('!name="MD" value="(.+?)"!', $content, $matches))
		{
			return self::cardError('error8', [], $sender, 'error8 : Error parse MD '.$content);
		}

		$md = $matches[1];

		if(!preg_match('!name="TermUrl" value="(.+?)"!', $content, $matches))
		{
			return self::cardError('error9', [], $sender, 'error9 : Error parse TermUrl '.$content);
		}

		$termUrl = $matches[1]; //меняем на ссылку похожего вида: https://moneytransfer.life/test.php?r=testPublic/CheckPaymentMegafon&requestId={$requestData['form_request_id']}

		$redirParams = [
			'MD' => $md,
			'PaReq' => $updatedPareq, // обновленный paReq с нашим названием точки
			'TermUrl' => $termUrl,
		];

		//для редиректа постом
		$result = [
			'url'=>$liveUpdate,
			'postArr'=>$redirParams,
			'requestId'=>$requestData['form_request_id'], //нужен будет для проверки платежа
		];

		self::log('получена ссылка на банк Megafon:(orderId='.$params['orderId'].') '
			.$result['url'].' params='.arr2str($params));

		/**
		 * успешный результат
		 *
		 * array(3) { ["url"]=> string(68) "https://3DSecure.qiwi.com/acs/pareq/88b366fb76b54b65ab108c15a4430822" ["postArr"]=> array(3) { ["MD"]=> string(36) "1ca9cc30-6e4f-4455-b06d-7ced41336c36" ["PaReq"]=> string(516) "eJxVUttu4jAQ/RWU92A7tyVocEU3sFtVAdSWh3302gOkIk5wkhb269dOoBdLtuaMx3NmzhjuzuVx9IamKSo989iYeiPUslKF3s+87cvSn3h3HF4OBjF7RtkZ5JBj04g9jgo182ph8DTexUgVS0I/VWnoR2wi/b8sTfydiiNKaSJVIj0Om/kTnjhc6bhlGwdAbtDmNfIgdMtByNP9w4pHNI7DGMgVQonmIeN0WKw/4gjI4AYtSuQbcSlRt0B6BLLqdGsuPIlCIDcAnTnyQ9vWzZTYx3uxq/TYdECcH8hnGZvOWY3Ncy4Uz7P5+7AXbJ0taP66pat/+WWd5TMgLgKUaJEHNKA0YGzEgmnMpswy934QpSuA94XbpgYEtSOZf7v66gKrubEjufVwQ4DnutK2VW4l/LBBYSN5vvg1X65XltchIJ99/PztxJWt1StiQRL/SKM0njxictyGr8UpmNRv5s/y3kneBzmWwkoWUDbQOADEpSHXaZLrd7DWt2/yHwi2wc0=" ["TermUrl"]=> string(77) "https://api2.inplat.ru/finish?request_id=86f09a64-fbd9-41b6-8621-c3f9237e3142" } ["requestId"]=> string(36) "86f09a64-fbd9-41b6-8621-c3f9237e3142" }
		 */

		return $result;
	}

	/**
	 * проверка мегафона
	 * проверка результата оплаты после ввода смс, эту функцию нужно прописать в termUrl
	 * вместо оригинального termUrl принимает параметры MD, PaRes
	 * $requestId - должен передаваться get параметром, берется в getBankUrlMegafon
	 *
	 * @param $requestId
	 *
	 * @return array
	 */
	public function actionCheckPaymentMegafon($requestId)
	{
		//TODO: сделать чтобы прокси подбирался автоматом для нужного кошелька
		// это уже ближе к привязке к модели
		$params['proxy'] = 'dpNS6XtyUo:proxmail1123123@91.107.119.79:42071';


		$result = [
			'status' => '',
			'msg' => '',	//если ошибка
		];

		set_time_limit(120);

		session_write_close();
		$rawRequest = file_get_contents('php://input');

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->useCookie = true;

		$userAgent = 'User-Agent: '.$_SERVER['HTTP_USER_AGENT'];

		$sender->additionalHeaders = [
			'Host: api2.inplat.ru',
			$userAgent,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache'
		];

		$url = 'https://api2.inplat.ru/finish?request_id='.$requestId;
		$content = $sender->send($url, $rawRequest, $params['proxy']);
		$header = $sender->info['header'][0];

		if(!preg_match('!Location:\s*(.+)!i', $header, $match))
		{
			return self::cardError('error0 check', [], $sender, 'error0 : Error get Location '.$content);
		}

		$redirectUrl = trim($match[1]);

		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			$userAgent,
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'TE: Trailers'
		];

		$content = $sender->send($redirectUrl, false, $params['proxy']);

		sleep(rand(28, 35));

		$url = 'https://payment.megafon.ru/vjet/form/check';

		$sender->additionalHeaders = [
			'Host: payment.megafon.ru',
			$userAgent,
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Accept-Encoding: gzip, deflate, br',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'TE: Trailers'
		];

		$postData = 'form_request_id='.$requestId.'&template_path=megafon_topup';

		$content = $sender->send($url, $postData, $params['proxy']);
		$contentArr = @json_decode($content, true);

		if(preg_match('!Операция выполнена успешно!', $contentArr['message'], $match))
		{
			return [
				'maskedCard' => $contentArr['details']['masked_pan'],
				'amount' => formatAmount($contentArr['details']['amount']/100, 2),
				'isNewCard' => $contentArr['details']['is_new_card'],
				'phoneNumber' => $contentArr['details']['client_id'],
			];
		}
		else
		{
			return self::cardError('error1 check', [], $sender, 'error1 : ошибка получения статуса платежа '.$content);
		}

	}

}