<?php
/**
 * @property int id
 * @property int intellect_account_id
 * @property float amount
 * @property string status
 * @property int created_by_api
 * @property string direction
 * @property int date_add
 * @property string error
 * @property string user_id
 * @property string client_id
 * @property string service_transaction_id
 * @property IntellectAccount account
 * @property string amountStr
 * @property string statusStr
 * @property string order_id	номер заказа на стороне клиента(для возможной сверки отдельных платежей руками)
 * @property int date_pay	дата оплаты
 * @property string pay_params	json данные карты
 * @property string pay_url 	ссылка на оплату
 * @property string urlShort
 * @property string term_url
 * @property string hash
 * @property int client_order_id	передает клиент
 * @property string success_url переброс на сайт клиента после успешной оплаты
 * @property string fail_url	переброс на сайт клиента при неудаче
 * @property string proxy	прокси для статы
 * @property array payParams
 * @property string dateAddStr
 * @property string datePayStr
 * @property string bank_url
 * @property string card_number
 * @property string bot_id
 */
class IntellectTransaction extends Model
{
	const SCENARIO_ADD = 'add';

	const DIRECTION_IN = 'in';
	const DIRECTION_OUT = 'out';

	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';
	const STATUS_WAIT = 'wait';
	const STATUS_PROCCESS = 'proccess';

	const ERROR_OUT_OF_LIMIT = 'out_of_limit';
	const ERROR_BAN = 'ban';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function beforeValidate()
	{
		if(!$this->user_id)
			unset($this->user_id);

		if(!$this->client_id)
			unset($this->client_id);

		return parent::beforeSave();
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}

	public function attributeLabels()
	{
		return [];
	}

	public function tableName()
	{
		return '{{intellect_transaction}}';
	}

	public function rules()
	{
		return array(
			['id, intellect_account_id, amount, status, created_by_api, date_add', 'safe'],
			['error, user_id, client_id, service_transaction_id', 'safe'],
			['order_id, date_pay, pay_params, pay_url, hash, client_order_id, success_url', 'safe'],
			['fail_url, direction, proxy, bank_url, term_url, card_number, bot_id', 'safe'],
		);
	}

	/**
	 * @return IntellectAccount
	 */
	public function getAccount()
	{
		return IntellectAccount::getModel(['id'=>$this->intellect_account_id]);
	}

	/**
	 * @return string
	 */
	public function getStatusStr()
	{
		return self::statusArr()[$this->status];
	}

	public static function statusArr()
	{
		return [
			self::STATUS_WAIT => 'в ожидании',
			self::STATUS_SUCCESS => 'оплачено',
			self::STATUS_ERROR => 'ошибка',
			self::STATUS_PROCCESS => 'в работе',
		];
	}

	/**
	 * @return string
	 */
	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	/**
	 * @return string
	 */
	public function getDatePayStr()
	{
		return date('d.m.Y H:i', $this->date_pay);
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
	 * @param array $params
	 * [
	 * 		'amount' => 100.01,
	 * 		'successUrl' => 'site.com/success',
	 * 		'failUrl' => 'site.com/fail',
	 * 		'orderId' => 143123412,
	 * ]
	 * @param $apiKey
	 *
	 * @return bool|mixed|string
	 */
	public static function getPayUrl($params = [], $apiKey)
	{
		$payUrl = '';
		$cfg = cfg('intellectMoney');

		/**
		 * @var User $userModel
		 */
		$userModel = User::getModel(['api_key'=>$apiKey]);

		if(!$userModel)
		{
			self::$lastError = 'Не найден пользователь';
			return false;
		}

		//этот хеш будет обратно передаваться из формы оплаты, чтобы идентифицировать пользователя
		$hash = md5(uniqid(rand(),1));

		$transaction = new IntellectTransaction;
		$transaction->success_url = $params['successUrl'] ? $params['successUrl'] : str_replace('{hash}', $hash, $cfg['defaultSuccessUrl']);
		$transaction->fail_url = $params['failUrl'] ? $params['failUrl'] : str_replace('{hash}', $hash, $cfg['defaultFailUrl']);
		$transaction->order_id = $params['orderId'] ? $params['orderId'] : false;
		$transaction->amount = formatAmount($params['amount'], 2);
		$transaction->proxy = $params['proxy'];
		$transaction->scenario = self::SCENARIO_ADD;
		$transaction->user_id = $userModel->id;
		$transaction->client_id = $userModel->client_id;
		$transaction->hash = $hash;
		$transaction->status = self::STATUS_WAIT;

		$payUrl = str_replace('{hash}', $hash, $cfg['url']);

		$transaction->pay_url = $payUrl;
		$transaction->save();

		return $payUrl;
	}

	/**
	 * дополняем транзакцию информацией о карте
	 * @param array $params
	 * [
		'hash'=>$hash,
		'orderId'=>$orderId,
		'cardNumber' => $postData['cardNumber'],
		'cardM' => $postData['cardM'],
		'cardY' => $postData['cardY'],
		'cardCvv' => $postData['cardCvv'],
		'headers' => $headers,
		'browser' => '',
		'referer' => '',
		]
	 * @param $hash
	 *
	 * @return bool|mixed|string
	 */
	public static function updateTransactionDetails($params = [])
	{
		$payUrl = '';
		$cfg = cfg('intellectMoney');

		/**
		 * @var IntellectTransaction $transaction
		 */
		$transaction = self::model()->findByAttributes(['hash'=>$params['hash']]);

		if(!$transaction)
		{
			self::$lastError = 'Не найдена заявка';
			return false;
		}

		$transaction->pay_params = json_encode($params);
		$transaction->status = self::STATUS_PROCCESS;

		return $transaction->update();
	}

	/**
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param int $clientId
	 * @param int $userId
	 * @param string $type (successIn|)
	 * @return  self[]
	 */
	public static function getModels($timestampStart, $timestampEnd, $clientId=0, $userId = 0, $type='')
	{
		$timestampStart *= 1;
		$timestampEnd *= 1;
		$clientId *= 1;
		$userId *= 1;

		$cond =  [];

		if($clientId)
			$cond[] = "`client_id` = '$clientId'";

		if($userId)
			$cond[] = "`user_id` = '$userId'";

		if($type == 'successIn')
			$cond[] = "`status`='".IntellectTransaction::STATUS_SUCCESS."'";

		$cond[] = "`direction` = 'in'";

		return self::model()->findAll([
			'condition' => "`date_add` >= $timestampStart AND `date_add` < $timestampEnd AND "
				.implode(" AND ", $cond),
			'order' => "`date_add` DESC",
		]);
	}

	/**
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return array
	 */
	public static function getStatsByInterval($timestampStart, $timestampEnd, $userId, $clientId)
	{
		$result = [
			'count'=>0,
			'countSuccess'=>0,
			'amount'=>0,	//оплаченные
			'allAmount'=>0,	//все
		];

		$timestampStart *= 1;
		$timestampEnd *= 1;

		if($timestampEnd <= $timestampStart)
			return $result;

		$timestampCond = "`date_add` >= $timestampStart AND `date_add` <= $timestampEnd";

		if($clientId)
			$clientCond = " and `client_id`=".$clientId;

		if($userId)
			$userCond = " and `user_id`=".$userId;

		$directionCond = " and `direction`='".self::DIRECTION_IN."'";

		//$countSuccess = self::model()->count("$timestampCond AND `status`='".self::STATUS_SUCCESS."'");


		$models = self::model()->findAll($timestampCond.$clientCond.$userCond.$directionCond);

		/**
		 * @var self[] $models
		 */

		foreach($models as $model)
		{
			$result['count']++;

			if($model->status == self::STATUS_SUCCESS)
			{
				$result['amount'] += $model->amount;
				$result['countSuccess']++;
			}

			$result['allAmount'] += $model->amount;
		}

		return $result;
	}

	/**
	 * @return string
	 */
	public function getAmountStr()
	{
		$result = ($this->amount > 0) ? formatAmount(floor($this->amount), 0).' RUB' : '';

		return $result;
	}

	/**
	 * @return string
	 */
	public function getUrlShort()
	{
		if(preg_match('!card/form&hash=([\w\d\-]+)!', $this->pay_url, $res))
			return '...'.$res[1];
		else
			return '';
	}

	/**
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
//		var_dump($params);die;

		$config = Yii::app()->getModule('intellectMoney')->config;

		if(!$model = self::getModel(['hash'=>$params['hash']]))//, 'status'=>self::STATUS_WAIT]))
		{
			$errorMsg = 'заявка не найдена или не актуальна';
			self::$lastError = $errorMsg;
			return IntellectMoneyBot::cardError($errorMsg, $params, false, 'заявка не найдена или не актуальна');
		}

		$threadName = 'intellectMoneyTransactionGetParams'.$model->id;

		if(!Tools::threader($threadName))
		{
			//todo: тянуть время sleep-ом
			//todo: сохранять полученные параметры в бд и возвращать при повторном запросе
			return IntellectMoneyBot::cardError('данные для оплаты уже были получены, повторите запрос через несколько секунд', $params);
		}

		$params['cardNumber'] = preg_replace('![^\d]!', '', $params['cardNumber']);
		$params['cardCvv'] = preg_replace('![^\d]!', '', $params['cardCvv']);
		$params['cardM'] = preg_replace('![^\d]!', '', $params['cardM']);
		$params['cardY'] = preg_replace('![^\d]!', '', $params['cardY']);


		if(strlen($params['cardNumber']) != 16)
			return IntellectMoneyBot::cardError('неверный  номер карты', $params);

		if(strlen($params['cardM']) === 1)
			$params['cardM'] = '0'.$params['cardM'];

		if(strlen($params['cardM']) != 2)
			return IntellectMoneyBot::cardError('неверно указан месяц', $params);

		if(strlen($params['cardY']) != 2)
			return IntellectMoneyBot::cardError('неверно указан год', $params);

		if(
			!$timestamp = strtotime('01.'.$params['cardM'].'.20'.$params['cardY'])
			or
			$timestamp + 3600*24*30 < time()
		)
			return IntellectMoneyBot::cardError('проверьте месяц и год, возможно ваша карта уже истекла', $params);


		if(strlen($params['cardCvv']) != 3)
			return IntellectMoneyBot::cardError('неверный  CVV-код с обратной стороны карты', $params);

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
			$params['proxy'] = 'd5dUOyGOgd:pytivcev@193.164.16.160:41589';//self::selectProxy($params['browser']);

		if(!$params['proxy'])
			return IntellectMoneyBot::cardError('техническая ошибка 3', $params, false, 'нет прокси');

		IntellectMoneyBot::log('client: '.$params['browser'].', proxy: '.$params['proxy']);

		/**
		 * @var IntellectAccount $account
		 */
		$account = $model->account;

		$params[] = '';

		$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';
//		$params['proxy'] = 'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
//		$params['proxyType'] = 'http';
//		$params['email'] = 'GeorgeFrank12@tutanota.com';
//		$params['successUrl'] = 'https://google.com';
//		$params['failUrl'] = 'https://ya.ru';
//		$params['cardNumber'] = '5246029706290881';
//		$params['cardM'] = '04';
//		$params['cardY'] = '22';
//		$params['cardCvv'] = '275';
//		$params['cardHolder'] = 'Ivan Ivanov';
//		$params['amount'] = 50;

		$result = IntellectMoneyBot::getPayParams($params, $account);

		$params = array_merge($params, $result);

		if(!$result)
		{
			$account->error_count++;
			$account->save();
		}

		unset($params['MD']);
		unset($params['PaRes']);

		$model->pay_params = json_encode($params);
		$model->proxy = $params['proxy'];
		$model->bank_url = ($result) ? $result['url'] : '';
		$model->term_url = ($result) ? $result['postArr']['TermUrl'] : '';
		$model->card_number = ($result) ? $params['cardNumber'] : '';

//		if($result['botId'])
//			$model->bot_id = $result['botId'];

		$model->save();

		$account = $model->account;

//		$params['email'] = $account->email;

		if(self::$lastErrorCode === self::ERROR_BAN)
		{
			//бан
			$account->status = IntellectAccount::STATUS_BAN;
			$account->date_error = time();
			$account->save();
		}

		//test замена провайдера в банке
		$paReq = $result['postArr']['PaReq'];
		$strOut = gzuncompress(base64_decode($paReq));
		$strOut = preg_replace('!<name>.+?</name>!', '<name>PAYMENT</name>', $strOut);
		$result['postArr']['PaReq'] = base64_encode(gzcompress($strOut));

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

}