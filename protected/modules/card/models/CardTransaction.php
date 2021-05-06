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
class CardTransaction extends Model
{
	const SCENARIO_ADD = 'add';
	const SCENARIO_WITHDRAW = 'withdraw';

	const STATUS_SUCCESS = 'success';
	const STATUS_WAIT = 'wait';
	const STATUS_ERROR = 'error';

	const PAYMENT_TYPE_CARD2CARD = 'card2card';

	//оплата через форму ввода карты либо напрямую редирект в банк
	const PAYMENT_METHOD = 'bank'; //form|bank

	const CURRENCY_RUB = 'RUB';

	const AMOUNT_MIN = 100;	//tele2:100, mts:10
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
		return '{{card_transaction}}';
	}

	public function rules()
	{
		return [
			['account_id', 'exist', 'className'=>'CardAccount', 'attributeName'=>'id',
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
			$cond[] = "`amount` > 0 AND `status`='".CardTransaction::STATUS_SUCCESS."'";


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
	 * @param string $cardTo
	 * @return bool|string
	 */
	public static function getPayUrl($userId, $amount, $clientOrderId, $successUrl = ''
		, $failUrl = '', $paymentType, $cardTo='')
	{
		$config = Yii::app()->getModule('card')->config;

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
		if($cardTo)
			$cardAccount = CardAccount::getModel(['pan'=>$cardTo]);
		else
			$cardAccount = CardAccount::getWallet($amount, $user->id);

		if(!$cardAccount)
		{
			self::$lastError = 'техническая ошибка 3';
			self::log('нет кошельков у '.$user->client->name.': '.CardAccount::$lastError);
			return false;
		}

		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->account_id = $cardAccount->id;
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
	 * @return CardAccount
	 */
	public function getAccount()
	{
		return CardAccount::getModel(['id'=>$this->account_id]);
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

		$filePath = $runtimePath.'card/browserProxy.txt';

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


	public static function startCancelOldTransactions()
	{
		$countAtOnce = 1000;
		$timestampMin = time() - self::CANCEL_TIMEOUT;
		$error = 'отменен по таймауту';

		if(!Tools::threader('cardCancelOldTransactions'))
		{
			self::$lastError = 'already run';
			return false;
		}

		//как часто запускать
//		$interval = 50;
//		$lastOptimization = config('cardLastCancel') * 1;
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

		config('cardLastCancel', time());
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

	/**
	 * @return array
	 */
	public static function getPaymentTypeArr()
	{
		return [
			self::PAYMENT_TYPE_CARD2CARD => 'Card2Card',
		];
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

		$config = Yii::app()->getModule('card')->config;


		if(!$model = self::getModel(['order_id'=>$params['orderId'], 'status'=>self::STATUS_WAIT]))
			return self::cardError('заявка не найдена или не актуальна', $params, false, 'заявка не найдена или не актуальна');

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

		$params['cardTo'] = $model->account->pan;
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

		if($model->payment_type == self::PAYMENT_TYPE_CARD2CARD)
			$result = self::getBankUrlCard2Card($params);
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
			$account->status = CardAccount::STATUS_BAN;
			$account->date_error = time();
			$account->save();
		}
		elseif(self::$lastErrorCode === self::ERROR_WAIT)
		{
			//холд до следующего дня(ограничение на платежи)
			$account->status = CardAccount::STATUS_WAIT;
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

	private static function parseForm($content)
	{
		if(!preg_match('!<form (.+?)</form>!is', $content, $match))
			return false;

		$formContent = $match[1];

		if(!preg_match('!action="(.+?)"!is', $formContent, $match))
			return false;

		$action = $match[1];
		//браузер при редиректе почему то сам убирает это
		$action = str_replace(':443/', '/', $action);


		if(!preg_match_all('!<input type="(hidden|text)" name="(.+?)".+?value="([^"]*)"!i', $formContent, $match))
			return false;

		$formParams = [];

		foreach($match[2] as $key=>$val)
			$formParams[$val] = $match[3][$key];

//		if(preg_match('!<input type="submit" name="(.+?)" value="(.*)"!i', $formContent, $match))
//			$formParams[$match[1]] = $match[2];

		$result = [
			'action' => trim($action),
			'params' => $formParams,
		];

//		echo htmlentities($formContent);
//		prrd($result);

		return $result;
	}

	//прокси, сендер и тд
	/**
	 * @param array $params ['browser'=>'', 'proxy'=>'']
	 * @return array [
	 * 	'sender' => ,
	 * ]
	 */
	private static function initCard2Card($params)
	{
		$runtimePath = DIR_ROOT.'protected/runtime/card/';

		if(!file_exists($runtimePath))
			mkdir($runtimePath);

		if(!file_exists($runtimePath.'cookie/'))
			mkdir($runtimePath.'cookie/');

		$sender = new Sender;
		$sender->useCookie = true;
		$sender->cookieFile = $runtimePath.'cookie/'.md5($params['browser'].$params['proxy']).'.txt';

		$sender->pause = 0;
		$sender->followLocation = false;
		$sender->proxyType = $params['proxyType'];

		return [
			'sender' => $sender,
		];
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
	private static function getBankUrlCard2Card($params)
	{
		$result = [];

		$params['cardNumber'] = preg_replace('![^\d]!', '', $params['cardNumber']);

		$params['cardCvv'] = preg_replace('![^\d]!', '', $params['cardCvv']);
		$params['cardM'] = preg_replace('![^\d]!', '', $params['cardM']);
		$params['cardY'] = preg_replace('![^\d]!', '', $params['cardY']);

		$pan = $params['cardNumber'];
		$params['cardNumber'] = substr($pan, 0, 4) .' '. substr($pan, 4, 4)
			.' '. substr($pan, 8, 4) .' '. substr($pan, 12, 4);

		$pan = $params['cardTo'];
		$params['cardTo'] = substr($pan, 0, 4) .' '. substr($pan, 4, 4)
			.' '. substr($pan, 8, 4) .' '. substr($pan, 12, 4);

		$params['amount'] *= 1;

		$init = self::initCard2Card($params);
		$sender = $init['sender'];
		/**
		 * @var Sender $sender
		 */

//		0)https://card2card.net
		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send('https://card2card.net', false, $params['proxy']);

		$content = str_replace("\n", '', $content);

		if(!preg_match('!input type="hidden" name="CSRFToken" id="CSRFToken" \s+ value="(.+?)"!', $content, $match))
			return self::cardError('Error0', $params, $sender, 'Card2CardError0 content: '.$content);

		$token = $match[1];

//		1) https://card2card.net/libs/Helpers/Ajax/Actions.php
		$url = 'https://card2card.net/libs/Helpers/Ajax/Actions.php';

		$postArr = [
			'action' => 'Transfer',
			'for' => '#form',
			'function' => 'functionTransfer',
			'style' => 'zoom-in',
			'url_page' => '',
			'CBTransferType' => '0',
			'fromNumber' => $params['cardNumber'],
			'fromExpDateMonth' => $params['cardM'],
			'fromExpDateYear' => $params['cardY'],
			'fromCVC' => $params['cardCvv'],
			'fromCardname' => 'CARD HOLDER',
			'CBTransferSrcFName' => '',
			'CBTransferSrcLName' => '',
			'CBTransferSrcPostcode' => '',
			'CBTransferSrcCity' => '',
			'CBTransferSrcAddress' => '',
			'toNumber' => $params['cardTo'],
			'CBTransferDstFName' => '',
			'CBTransferDstLName' => '',
			'CBTransferDstCountry' => 'AUT',
			'amount' => $params['amount'],
			'CSRFToken' => $token,
			'currency' => '643',
			'type' => 'set',
			'switch' => 'start',
		];

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With: XMLHttpRequest',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
			'Origin: https://card2card.net',
			'Referer: https://card2card.net',
		];

		$content = $sender->send($url, http_build_query($postArr), $params['proxy']);

		if(!$arr = json_decode($content, true) or $arr['status'] != 'ok' or !$url = $arr['redirect'])
		{
			if($arr['status'] == 'error')
				return self::cardError('Error1', $params, $sender, 'Card2CardError1 msg: '.$arr['message']);
			else
				return self::cardError('Error1', $params, $sender, 'Card2CardError1 content: '.$content);
		}

//		2) https://securepay.rsb.ru/ecomm2/ClientHandler?trans_id=glFmGNpg9WzPSkL8UKFp7w5B2aw%3D
		$url = $arr['redirect'];
		$referer1 = $url;

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: https://card2card.net/',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send($url, false, $params['proxy']);

		if(!$form = self::parseForm($content))
			return self::cardError('Error2', $params, $sender, 'Card2CardError2 content: '.$content);

		$url = $form['action'];
		$formParams = $form['params'];

		if(!$formParams['PaReq'])
		{
//			3) https://securepay.rsb.ru/mdpaympi/MerchantServer
			$sender->additionalHeaders = [
				'User-Agent: '.$params['browser'],
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
				'DNT: 1',
				'Connection: keep-alive',
				'Origin: https://securepay.rsb.ru',
				'Referer: '.$referer1,
				'Upgrade-Insecure-Requests: 1',
				'Pragma: no-cache',
				'Cache-Control: no-cache',
			];


			$content = $sender->send($url, http_build_query($formParams), $params['proxy']);

			if(!$form = self::parseForm($content))
				return self::cardError('Error3', $params, $sender, 'Card2CardError3 content: '.$content);

			$url = $form['action'];
			$formParams = $form['params'];

			if(!$formParams['PaReq'])
				return self::cardError('Error3.1', $params, $sender, 'Card2CardError3.1 content: '.$content);
		}

		$termUrl = $formParams['TermUrl'];
		$formParams['TermUrl'] = $params['checkUrl'];

		$paReq = $formParams['PaReq'];
		$strOut = gzuncompress(base64_decode($paReq));
		$strOut = preg_replace('!<name>.+?</name>!', '<name>PAYMENT</name>', $strOut);
		$formParams['PaReq'] = base64_encode(gzcompress($strOut));


		//для редиректа постом
		$result = [
			'url'=>$url,
			'postArr'=>$formParams,
			'termUrl' => $termUrl,
		];

		self::log('получена ссылка на банк Card2Card: '.$result['url'].' params='.arr2str($params).',  orderId='.$params['orderId']);

		return $result;
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

		$threadName = 'cardTransactionCheck'.$this->id;

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

			if($this->payment_type == self::PAYMENT_TYPE_CARD2CARD)
				$checkResult = self::checkBankUrlCard2Card($params);
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
	 * @param array $params
	 * @param Sender $sender
	 * @param string $content
	 * @return bool
	 */
	private static function checkSecurePay($params, $sender, $content)
	{
		$form = self::parseForm($content);

//		5) https://securepay.rsb.ru/ecomm2/ClientHandler

		$url = $form['action'];
		$formParams = $form['params'];

		$content = $sender->send($url, http_build_query($formParams), $params['proxy']);

		if(!$form = self::parseForm($content))
			return self::cardError('Error5', $formParams, $sender, 'Card2CardError5 content: '.$content);

//		6) https://card2card.net/transaction

		$url = $form['action'];
		$formParams = $form['params'];

		$sender->send($url, http_build_query($formParams), $params['proxy']);
		$header = $sender->info['header'][0];

		if(!preg_match('!Location:(.+)!', $header, $match))
			return self::cardError('Error5', $formParams, $sender, 'Card2CardError5 header: '.$header);

		$url = 'https://card2card.net'.trim($match[1]);
		$content = $sender->send($url, false, $params['proxy']);

		return self::Card2CardResult($content);
	}

	/**
	 * @param array $params
	 * @param Sender $sender
	 * @param string $content
	 * @return bool
	 */
	private static function checkExpaPay($params, $sender, $content)
	{
		preg_match('!"(https://gw\.expapay\.com/result/.+?)"!', $content, $match);

		$url = $match[1];

//		8)https://gw.expapay.com/result/5e1f28a4e3cc171cca78c39d

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'DNT: 1',
			'Connection: keep-alive',
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send($url, false, $params['proxy']);

		if(!$form = self::parseForm($content))
			return self::cardError('Error9', [], $sender, 'Card2CardError9 content: '.$content);

//		9)https://card2card.net/finish

		$url = $form['action'];
		$content = $sender->send($url, http_build_query($form['params']), $params['proxy']);
		$header = $sender->info['header'][0];

		if(!preg_match('!Location:(.+)!', $header, $match))
			return self::cardError('Error10', [], $sender, 'Card2CardError10 content: '.$content);

		$url = 'https://card2card.net'.trim($match[1]);
		$content = $sender->send($url, false, $params['proxy']);

		return self::card2CardResult($content);
	}

	public static function card2CardResult($content)
	{
		$result = [
			'status' => '',
			'msg' => '',	//если ошибка
		];

		if(preg_match('!<p class="check-title">Перевод выполнен успешно</p>!', $content))
		{
			$result['status'] = self::STATUS_SUCCESS;
		}
		else
		{
			$error = '';

			if(preg_match('!<div class="error-text">(.+?)</div>!is', $content, $match))
			{
				$error = trim($match[1]);

				if($error == 'Транзакция отклонена системой, так как ECI находится в списке заблокированных ECI.')
					$error = 'перевод отменен';

				if($error == 'Отказ (без комментария банка).')
					$error = 'отказ банка, возможно неверный cvv-код';

				if($error == 'Отказ: недостаточно средств.')
					$error = 'недостаточно средств';

				if($error == 'Ошибка при проведении перевода.')
					$error = 'ошибка при проведении перевода, возможно неверно указан CVV-код';
			}

			$result['status'] = self::STATUS_ERROR;

			if($error)
			{
				$result['msg'] =  $error;
			}
			else
			{
				$result['msg'] =  'Error7';
				self::cardError('Error7', [], false, 'Card2CardError7 content: '.$content);
			}
		}

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
	private static function checkBankUrlCard2Card($params)
	{
		$init = self::initCard2Card($params);
		$sender = $init['sender'];
		/**
		 * @var Sender $sender
		 */

		$refererParts = parse_url(strtolower($params['bankUrl']));
		$referer = $refererParts['scheme'].'://'.$refererParts['host'].'/';

//		4) https://securepay.rsb.ru/mdpaympi/MerchantServer/msgid/108462063

		$url = $params['termUrl'];

		$sender->additionalHeaders = [
			'User-Agent: '.$params['browser'],
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'Origin: '.$referer,
			'DNT: 1',
			'Connection: keep-alive',
			'Referer: '.$referer,
			'Upgrade-Insecure-Requests: 1',
			'Pragma: no-cache',
			'Cache-Control: no-cache',
		];

		$content = $sender->send($url, http_build_query($_POST), $params['proxy']);

		if(preg_match('!<a href="https:\/\/gw\.expapay\.com/result/.+?"!', $content))
			return self::checkExpaPay($params, $sender, $content);
		elseif(self::parseForm($content))
			return self::checkSecurePay($params, $sender, $content);
		else
			return self::cardError('Error4', [], $sender, 'Card2CardError5 content: '.$content);
	}
}