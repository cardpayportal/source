<?php

/**
 *
 * Class ExchangeYadBit
 * @property int id
 * @property int api_id
 * @property int user_id
 * @property int client_id
 * @property string url
 * @property float amount
 * @property string comment
 * @property string order_id
 * @property string number
 * @property string status
 * @property string wallet
 * @property int date_add
 * @property int date_pay
 * @property string error
 * @property bool created_by_api			создано через апи
 *
 * @property string datePayStr
 * @property string statusStr
 * @property string amountStr
 * @property string dateAddStr
 * @property User user
 * @property string mark
 * @property string urlShort
 * @property bool is_notified	уведомление при успешном платеже
 * @property int date_withdraw	дата вывода за платеж
 * @property string card_month
 * @property string card_no
 * @property string card_year
 * @property string cvv
 * @property string pic_id
 * @property string card_name
 * @property string sms_code
 * @property string url_yandex
 * @property string unique_id
 */
class ExchangeYadBit extends Model
{
	const SCENARIO_ADD = 'add';

	const STATUS_WAIT = 'wait';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';
	const STATUS_WORKING = 'working';
	const STATUS_WAIT_SMS = 'wait sms';

	const MARK_CHECKED  = 'checked';
	const MARK_UNCHECKED = '';

	private $_clientCache = null;
	private $_userCache = null;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{exchange_yad_bit}}';
	}

	public function rules()
	{
		return [
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false],
			['url', 'unique', 'className'=>__CLASS__, 'attributeName'=>'url', 'message'=>'url уже был добавлен',
				'on'=>self::SCENARIO_ADD],
			['unique_id', 'unique', 'className'=>__CLASS__, 'attributeName'=>'unique_id', 'message'=>'unique_id уже был добавлен',
				'on'=>self::SCENARIO_ADD],
			['amount', 'numerical', 'min'=>1, 'max'=>1000000000, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],
			['status', 'in', 'range' => array_keys(self::statusArr()), 'allowEmpty'=>false],
			['date_add, date_pay, date_withdraw, created_by_api, comment, order_id, number, wallet,is_notified,
				comment, url_yandex, card_month, card_no, card_year, cvv, pic_id, card_name, sms_code, unique_id', 'safe'],
			['error', 'length', 'min'=>0, 'max'=>200],
			['mark', 'length', 'max'=>100],
		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();
			$this->client_id = $this->getUser()->client_id;
		}

		$this->mark = strip_tags($this->mark);

		return parent::beforeSave();
	}

	protected function afterSave()
	{

		parent::afterSave();
	}


	/**
	 * @return Client|null
	 */
	public function getClient()
	{
		if(!$this->_clientCache)
			$this->_clientCache = Client::modelByPk($this->client_id);

		return $this->_clientCache;
	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		if(!$this->_userCache)
			$this->_userCache = User::getUser($this->user_id);

		return $this->_userCache;
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
		return ($this->date_pay) ? date('d.m.Y H:i', $this->date_pay) : '';
	}

	/**
	 * @return string
	 */
	public function getAmountStr()
	{
		$result = ($this->amount > 0) ? formatAmount($this->amount, 0).' RUB' : '';

		return $result;
	}

	/**
	 * @return string
	 */
	public function getStatusStr()
	{
		return self::statusArr()[$this->status];
	}


	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $clientId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param bool $onlySuccess		только активированные
	 * @return self[]
	 */
	public static function getModels($timestampStart=0, $timestampEnd=0, $userId = 0, $clientId = 0, $onlySuccess=false)
	{
		$intervalMax = 3600 * 24 * 365;

		$userId *=1;
		$clientId *=1;

		$timestampStart *=1;
		$timestampEnd *=1;

		if($timestampEnd > time())
			$timestampEnd = time();

		if($timestampEnd - $timestampStart > $intervalMax)
		{
			$timestampStart = $timestampEnd - $intervalMax;
			//self::$lastError = 'максимальный интервал статистики: 30 дней';
			//return [];
		}

		//либо по юзеру либо по клиенту
		$userCond = ($userId) ? " AND `user_id`='$userId'" :
			(($clientId) ? " AND `client_id`='$clientId'" : '');

		$successCond = '';

		if($onlySuccess)
			$successCond = " AND `status`='".self::STATUS_SUCCESS."'";


		$models = self::model()->findAll([
			'condition'=>"
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				$userCond
				$successCond
			",
			'order'=>"`date_add` DESC",
		]);

		return $models;
	}


	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $clientId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param bool $onlySuccess		только активированные
	 *
	 * @return array
	 */
	public static function getModelsForPagination($timestampStart=0, $timestampEnd=0, $userId = 0, $clientId = 0, $onlySuccess=false)
	{
		$intervalMax = 3600 * 24 * 365;

		$userId *=1;
		$clientId *=1;

		$timestampStart *=1;
		$timestampEnd *=1;

		if($timestampEnd > time())
			$timestampEnd = time();

		if($timestampEnd - $timestampStart > $intervalMax)
		{
			$timestampStart = $timestampEnd - $intervalMax;
			//self::$lastError = 'максимальный интервал статистики: 30 дней';
			//return [];
		}

		//либо по юзеру либо по клиенту
		$userCond = ($userId) ? " AND `user_id`='$userId'" :
			(($clientId) ? " AND `client_id`='$clientId'" : '');

		$successCond = '';

		if($onlySuccess)
			$successCond = " AND `status`='".self::STATUS_SUCCESS."'";

		$criteria = new CDbCriteria();
		$criteria->condition = "
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				$userCond
				$successCond
			";
		$criteria->order = 'date_add DESC';

		$count = self::model()->count($criteria);

		$pagination = new CPagination($count);
		$pagination->pageSize = 20; // Количество элементов на страницу

		$pagination->applyLimit($criteria);
		$models = self::model()->findAll($criteria);

		return [
			'models' => $models,
			'pages' => $pagination
		];
	}

	/**
	 * @param self[] $models
	 * @return array
	 */
	public static function getStats($models)
	{
		$result = [
			'count'=>0,
			'countSuccess'=>0,
			'amount'=>0,	//оплаченные
			'allAmount',	//все
		];

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


	public static function statusArr()
	{
		return [
			self::STATUS_WAIT => 'в ожидании',
			self::STATUS_SUCCESS => 'оплачено',
			self::STATUS_ERROR => 'ошибка',
			self::STATUS_WORKING => 'в работе',
			self::STATUS_WAIT_SMS => 'ожидание смс',
		];
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
	 * @return self
	 */
	public static function getModelById($id)
	{
		return self::model()->findByPk($id);
	}

	public static function cancelPayment($id, $userId)
	{
		$model = self::getModelById($id);

		if(!$model or $model->user_id != $userId)
		{
			self::$lastError = 'платеж не найден или у вас нет прав на его отмену';
			return false;
		}

		if($model->status == self::STATUS_ERROR)
		{
			self::$lastError = 'платеж уже отменен';
			return false;
		}

		$model->status = self::STATUS_ERROR;
		$model->error = 'отменен пользователем';

		return $model->save();
	}

	public static function mark($id, $userId, $label)
	{
		$id *= 1;
		$userId *= 1;

		if(!$model = self::model()->find("`id`='$id' and `user_id`=$userId"))
		{
			self::$lastError = 'у вас нет прав на выполнение этого действия';
			return false;
		}

		$model->mark = $label;

		return $model->save();
	}

	/**
	 * @return string
	 */
	public function getUrlShort()
	{
		if(preg_match('!requestid\%3D([\w\d\-]+)!', $this->url, $res))
			return '...'.$res[1];
		elseif(preg_match('!hash=([^\&]+)!', $this->url, $res))
			return '...'.$res[1];
		elseif(preg_match('!receiver=(\d+)!', $this->url, $res))
			return '...'.$res[1];
		elseif(preg_match('!h=([\w\d\-]+)!', $this->url, $res))
			return '...'.$res[1];
		elseif(preg_match('!param=([\w\d\-]+)!', $this->url, $res))
			return '...'.$res[1];
		else
			return $this->url;
	}

	/**
	 * ссылка на оплату с яндекса
	 * создает и сохраняет модель, если не удалось получить ссылку то вернет false
	 *
	 * @param int $userId
	 * @param float $amount
	 * @param bool $byApi		выдана через апи
	 * @param bool $byApi		выдана через апи
	 * @param int $orderId		orderId присваивается клиентом
	 * @return string|bool
	 */
	public static function getPayUrlYm($userId, $amount, $byApi = false, $orderId=0)
	{
		$cfg = cfg('newYandexPayYm');

		$amount = trim($amount);
		$interval = $cfg['getUrlInterval'];

		$payParams = [];

		$user = User::getUser($userId);

		if(!config('pickAccountEnabled'))
		{
			$error = 'выдача ссылок отключена';
			toLogError($error);
			self::$lastError = $error;
			return false;
		}

		if(preg_match('!^([\d\.]+)$!', $amount, $res))
			$amount = $res[1];
		else
		{
			self::$lastError = 'сумма должна быть числом';
			return false;
		}

		//интервал между созданием урлов
		$lastModel = self::model()->find([
			'condition' => "`user_id`='$userId'",
			'order' => "`id` DESC",
		]);

		if($lastModel and time() - $lastModel->date_add < $interval)
		{
			//пауза вместо ошибки
			sleep(ceil($interval - time() + $lastModel->date_add));
			//self::$lastError = 'подождите '.ceil($interval - time() + $lastModel->date_add).' секунд';
			//return false;
		}

		if(!config('newYandexPayWallet'))
		{
			self::$lastError = 'не указан кошелек, обратитесь к администратору';
			return false;
		}

		//уникальность user_id order_id
		if($orderId and ($model = self::model()->findByAttributes(['user_id'=>$userId, 'order_id'=>$orderId])))
		{
			//toLogRuntime('В методе getPayUrlYm найден повтор order_id, id заявки'.$model->id.' user_id='.$userId.' order_id='.$orderId.' unique_id='.$model->unique_id);
//			self::$someData['yandexPayId'] = $model->id;
//
//			if($model->url_yandex)
//				return $model->url_yandex;
//			else
//				return $model->url;
		}
//		else
//		{
		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->user_id = $user->id;
		$model->client_id = $user->client_id;
		$model->amount = $amount;
		$model->status = self::STATUS_WAIT;
		$model->order_id = ($orderId) ? $orderId : Tools::microtime();
		$model->unique_id = uniqid("btc_", true);
		$model->comment = 'Exchange #'.$model->order_id;//.' number '.$model->number;
		$model->date_add = time();
		$model->created_by_api = ($byApi) ? 1 : 0;
		$model->wallet = config('newYandexPayWallet');

		$cfg = cfg('newYandexPayYm');

		//куда перенаправлятьюзера
		$urlReturn = ($user->store) ? $user->store->url_return.'?status=success&orderId='.$model->order_id : '';

		$urlReturn = urlencode($urlReturn);

		$model->url = str_replace(
			['{wallet}', '{amount}', '{orderId}', '{successUrl}', '{comment}'],
			[$model->wallet, $model->amount, $model->unique_id, $urlReturn, urlencode($model->comment)],
			$cfg['yandexUrlPC']
		);
//		}

		if($model->save())
		{
			self::$someData['yandexPayId'] = $model->id;
			return $model->url;
		}
		else
		{
			self::$lastError = 'системная ошибка, обратитесь к администратору';
			return false;
		}

	}


	/**
	 * будет выдавать ссылки для обменника в направлении: яндекс кошелек -> биток
	 * @param int $userId
	 * @param float $amount
	 * @param bool $byApi
	 * @param int $orderId
	 * @return string|false
	 */
	public static function getPayUrlExchangeTest($userId, $amount, $byApi, $orderId)
	{
		$cfg = cfg('newYandexPayYm');

		$user = User::getUser($userId);

		if(!$user)
			return false;

		$commissionRule = $user->client->commissionRule;

		$rateUsd = str_replace(',' ,'.', $commissionRule->rateValue);

		//доп комса при оплате картой(меню=>Комиссии клиентов=>Яд процент)
		$commissionRule->ym_card_percent *= 1;

		$cardPercent = $commissionRule->ym_card_percent/100 + 1;
		$rateUsd = $rateUsd * $cardPercent;
		$amountWithPercent = $amount * $cardPercent;

		$btcRateUsd = config('btc_usd_rate_btce');
		//self::parseBtcRuYad();
		$btcRateRub = $rateUsd * $btcRateUsd;

		if(!$btcRateRub)
		{
			toLogError('Ошибка парсинга курса btc в обменнике');
			return false;
		}

		//их пустыми оставить если не нужны
		$successUrl = '';
		$failUrl = '';

		//$key = 'HM6Ly9tb25leS55YW5kZXgucnUvdHJhbnNmZXI/cmVjZWl2ZXI9NDEwMDE3NDE2Njk4NzA4JnN1b';

		$urlYandex = self::getPayUrlYm($userId, $amountWithPercent, $byApi, $orderId);

		/**
		 * @var NewYandexPay $model
		 */
		$model = self::model()->findByAttributes([
			'id' => self::$someData['yandexPayId'],
		]);

		$proxy = $cfg['proxy'];

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->proxyType = 'http';

		$amountRub = $amountWithPercent;
		$amountBtc = formatAmount(($amountWithPercent/$btcRateRub), 4);

		$btcInComment = '';

		if($user->client->fake_btc)
			$btcInComment .= "&btc=".$user->client->fake_btc;
		else
			$btcInComment .= "&btc=1EbvFmQoPMMbXj5RiappjzXhsCzTcR1Vur";

		$newComment = 'Обмен с Yandex на BTC '.$btcInComment.', №'.$model->order_id;
		$urlYandex = str_replace(urlencode($model->comment), urlencode($newComment), $urlYandex);

		$model->comment = $newComment;

		$postData = "yandex_link=".base64_encode($urlYandex)."&success_link=".base64_encode($successUrl)."&fail_link="
			.base64_encode($failUrl)."&yad=".$amountRub."&bit=".$amountBtc."&order_id=".$model->order_id;



		//уведомить обменник при проценте
		//if($commissionRule->ym_card_percent > 0)
		if($commissionRule->ym_card_percent)
			$postData .= '&percent='.$commissionRule->ym_card_percent;
		else
			$postData .= '&percent=0';

		//проверяем какой будет платеж: яндекс деньги -> биток или карта -> биток
//		if($paymentType == Client::YANDEX_PAYMENT_TYPE_EXCHANGE_YM)
//		{
		if($user->client->fake_btc)
			$postData .= "&btc=".$user->client->fake_btc;
		else
			$postData .= "&btc=1EBESB1QyBG1y8xFyP3Gk8BQCBkTho1b87";
		//ключ API псевдо обмена
		$key = $cfg['exchangeKeyMyBitstore'];

		$hash = md5($postData.$key);
		$url = 'https://mybitstore.ru/api.php?key='.$hash;

		$sender->timeout = 30;
		$content = $sender->send($url, $postData, $proxy);

		//бывает сервер тупит и снова ждет запрос
		if($sender->info['httpCode'][0] == 100)
			$content = $sender->send($url, $postData, $proxy);

		$arr = json_decode($content, true);

		if($arr['status'] == 'success')
		{
			toLogRuntime('выдача ссылки: '.$user->client->name.' '.$postData);

			if($url && $model)
			{
				$model->amount = $amount;
				$model->url_yandex = $urlYandex;
				$model->url = $arr['link'];
				$model->save();
			}
			else
				return false;

			return $arr['link'];
		}
		else
		{
			$model->url = '';
			$model->save();
			toLogError('Ошибка получения ссылки с обменника, юзер: '
				.$user->name.' проверить покси и ключ api, ответ: '.Tools::arr2Str($arr)
				.', httpCode='.$sender->info['httpCode'][0]);
			return false;
		}


	}


	/*
	 * получаем курс для яндекса с обменника
	 */
	public static function parseBtcRuYad()
	{
		$url = 'https://btc-exchange.biz/tarifs/';

		$sender = new Sender;
		$cfg = cfg('newYandexPayYm');
		$proxy = $cfg['proxy'];

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->proxyType = 'socks5';

		$content = $sender->send($url, null, $proxy);

		$pregStr = '!class="tarif_curs_in_ins">([\d\.]+?)&nbsp;RUB!iu';

		if(preg_match_all($pregStr, $content, $matches))
			return $matches[1][1];
		else
			return false;
	}


	public static function getPayUrl($userId, $amount, $byApi = false, $orderId=0)
	{
		$user = User::getUser($userId);
		$client = $user->client;

		if(!$client->pick_accounts)
		{
			self::$lastError = 'выдача реквизитов отключена';
			//toLogError('выдача реквизитов отключена '.$client->name);
			return false;
		}

		if(!$user)
		{
			self::$lastError = 'юзер не найден';
			return false;
		}

		$payUrl = false;


		if($client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_CARD)
			$payUrl = NewYandexPay::getPayUrlCard($userId, $amount, $byApi, $orderId);
		elseif(
			$client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_YM
		)
			$payUrl = self::getPayUrlYm($userId, $amount, $byApi, $orderId);
		elseif(
			$client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_EXCHANGE
		)
			$payUrl = self::getPayUrlExchange($userId, $amount, $byApi, $orderId);
		elseif(
			$client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_CARD_UNIVER
		)
			$payUrl = self::getPayUrlCardUniver($userId, $amount, $byApi, $orderId);
		elseif(
			$client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_MEGAKASSA_FAKER
		)
			$payUrl = self::getPayUrlMegakassa($userId, $amount, $byApi, $orderId);
		elseif(
			$client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_MEGAKASSA_YANDEX
		)
			$payUrl = self::getPayUrlMegakassa($userId, $amount, $byApi, $orderId);
		else
			self::$lastError = 'неизвестный метод получения ссылки';

		return $payUrl;
	}


	//test
	/**
	 * ссылка на оплату с яндекса через фейкер (карты)
	 * создает и сохраняет модель, если не удалось получить ссылку то вернет false
	 *
	 * @param int $userId
	 * @param float $amount
	 * @param bool $byApi		выдана через апи
	 * @param int $orderId		orderId присваивается клиентом
	 * @return string|bool
	 */
	public static function getPayUrlCard($userId, $amount, $byApi = false, $orderId=0)
	{
		$cfg = cfg('newYandexPay');

		$amount = trim($amount);
		$interval = $cfg['getUrlInterval'];

		$user = User::getUser($userId);

		if(!config('pickAccountEnabled') or !$user->client->pick_accounts)
		{
			$error = 'выдача ссылок отключена';
			//toLogError($error);
			self::$lastError = $error;
			return false;

		}

		if(preg_match('!^([\d\.]+)$!', $amount, $res))
			$amount = floor($res[1]);
		else
		{
			self::$lastError = 'сумма должна быть числом';
			return false;
		}

		//интервал между созданием урлов
		$lastModel = self::model()->find([
			'condition' => "`user_id`='$userId'",
			'order' => "`id` DESC",
		]);

		if($lastModel and time() - $lastModel->date_add < $interval)
		{
			//пауза вместо ошибки
			sleep(ceil($interval - time() + $lastModel->date_add)+1);
			//self::$lastError = 'подождите '.ceil($interval - time() + $lastModel->date_add).' секунд';
		}

		if(!config('newYandexPayWallet'))
		{
			self::$lastError = 'не указан кошелек, обратитесь к администратору';
			return false;
		}

		//уникальность user_id order_id
		if($orderId and ($model = self::model()->findByAttributes(['user_id'=>$userId, 'order_id'=>$orderId])))
		{
			//toLogRuntime('В методе getPayUrlCard найден повтор order_id, id заявки'.$model->id.' user_id='.$userId.' order_id='.$orderId.' unique_id='.$model->unique_id);

//			self::$someData['yandexPayId'] = $model->id;
//			if($model->url_yandex)
//				return $model->url_yandex;
//			else
//				return $model->url;
		}
//		else
//		{
		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->user_id = $user->id;
		$model->client_id = $user->client_id;
		$model->amount = $amount;
		$model->status = self::STATUS_WAIT;
		$model->order_id = ($orderId) ? $orderId : Tools::microtime();
		$model->unique_id = uniqid("i_", true);
		$model->comment = 'Exchange #'.$model->order_id;//.' number '.$model->number;
		$model->date_add = time();
		$model->created_by_api = ($byApi) ? 1 : 0;
		$model->wallet = config('newYandexPayWallet');

		//куда перенаправлятьюзера
		$urlReturn = ($user->store) ? $user->store->url_return.'?orderId='.$model->order_id : '';

		$url = str_replace(
			['{token}', '{amount}', '{orderId}', '{paymentType}', '{urlRedirect}', '{walletNumber}'],
			[$cfg['token'], $model->amount, $model->unique_id, $cfg['paymentType'], $urlReturn, $model->wallet],
			$cfg['url']
		);

		$sender = new Sender;
		$sender->followLocation = false;

		$content = $sender->send($url, false, $cfg['proxy']);

		$contentArr = json_decode($content, 1);

		if(!$contentArr['url'])
		{
			self::$lastError = 'не получена ссылка для оплаты';
			toLogError(self::$lastError.': user '.$user->login.', content: '.$content);
			return false;
		}

		$model->url = $contentArr['url'];

//		}
		if($model->save())
		{
			self::$someData['yandexPayId'] = $model->id;
			return $model->url;
		}
		else
		{
			self::$lastError = 'системная ошибка, обратитесь к администратору';
			return false;
		}

	}


	/**
	 * @param string $datePay
	 * @return bool
	 */
	public function confirmManual($datePay = '')
	{
		if($this->status != self::STATUS_WAIT)
		{
			self::$lastError = '';
			return false;
		}

		$timestampPay = time();

		if($datePay and strtotime($datePay))
			$timestampPay = strtotime($datePay);

		$this->date_pay = $timestampPay;
		$this->status = self::STATUS_SUCCESS;
		$this->error = '';

		return $this->save();
	}

	public static function setRandomWallet()
	{
		$walletStr = config('newYandexPayWalletStr');

		if($walletStr)
		{
			$yandexWalletArr = [];

			if(preg_match_all(cfg('regExpAccountAddYandex'), $walletStr, $matches))
			{
				foreach($matches[1] as $key=>$number)
					$yandexWalletArr[] = trim($matches[1][$key]);
			}
			else
				return false;

			$wallet = $yandexWalletArr[array_rand($yandexWalletArr)];
			config('newYandexPayWallet', $wallet);

			return true;
		}
		else
			return false;


	}

	public static function setWalletStr($str, $userId)
	{
		//$str = '410017394097107 410017394097123 410017394097133';

		$user = User::getUser($userId);

		if($user->role !== User::ROLE_ADMIN and !$user->is_wheel)
		{
			self::$lastError = 'может изменять рулевой или админ';
			return false;
		}

		$yandexWalletStr = '';

		if(preg_match_all(cfg('regExpAccountAddYandex'), $str, $matches))
		{
			foreach($matches[1] as $key=>$number)
				$yandexWalletStr .= trim($matches[1][$key]).' ';
		}

		config('newYandexPayWalletStr', $yandexWalletStr);

		return true;
	}

	/**
	 * платежи, за которые не рассчитались (для подсчета баланса)
	 * @param int $userId
	 * @return self[]
	 */
	public static function getNotWithdrawTransactions($userId)
	{
		$userId *= 1;

		return self::model()->findAll("`user_id`=$userId AND `status`='".self::STATUS_SUCCESS."' AND `date_withdraw`=0");
	}

	public static function startApiNotification()
	{
		$cfg = cfg('storeApi');

		//обновить инфу о платежах перед уведомлением
		self::updateQiwiPayments();

		$stores = StoreApi::model()->findAll();
		/**
		 * @var StoreApi[] $stores
		 */

		foreach($stores as $store)
		{
			if(!$store->url_result)
				continue;

			$userId = $store->user_id;

			$successPayments = self::model()->findAll([
				'condition' => "`user_id`=$userId AND `status`='".self::STATUS_SUCCESS."' AND `is_notified`=0",
				'order' => "`date_pay` DESC",
			]);

			/**
			 * @var self[] $successPayments
			 */

			$payments = [];

			foreach($successPayments as $successPayment)
			{
				$params = [
					'storeId' => StoreApi::getModelByUserId($successPayment->user_id)->store_id,
					'orderId' => $successPayment->order_id,
					'url' => $successPayment->url,
					'amount' => $successPayment->amount,
					'currency' => 'RUB',
					'status' => $successPayment->status,
					'timestampPay' => $successPayment->date_pay,
					'error' => $successPayment->error,
				];

				$params['hash'] = StoreApi::hash($params);
				$payments[] = $params;
			}

			if($payments)
			{
				//test
				//echo json_encode($result);die;
				$sender = new Sender;
				$sender->followLocation = false;
				$sender->proxyType = $cfg['notificationProxyType'];
				$sender->useCookie = false;

				$result = [
					'payments' => $payments,
				];

				//prrd($result);

				$content = $sender->send($store->url_result, json_encode($result), $cfg['notificationProxy']);

				if($content === 'OK')
				{
					foreach($successPayments as $successPayment)
					{
						$successPayment->is_notified = 1;

						if($successPayment->save())
						{
							toLogStoreApi('платеж order_id='.$successPayment->order_id.' уведомлен');
						}
						else
						{
							toLogError('ошибка уведомления платеж id='.$successPayment->id);
							return false;
						}
					}
				}
				else
				{
					//вернять в логи после того как кл у себя настроит
					toLogError('ошибка уведомления по АПИ: content='.$content.', httpCode='
						.$sender->info['httpCode'][0].', url = '.$store->url_result);
					return false;
				}

			}
		}


		return true;
	}


	/**
	 * возвращает массив найденных но не оплаченых транзакций по store_id
	 * @param int $storeId
	 * @return self[] | false
	 */
	public static function getNotPaidTransactions($storeId)
	{
		$user = StoreApi::getModelByStoreId($storeId)->user;

		if(!$user)
		{
			self::$lastError = 'магазин не найден';
			return false;
		}

		$result =  self::model()->findAll(array(
			'condition'=>"`user_id`='{$user->id}' AND `date_withdraw`=0 AND `status`='".self::STATUS_SUCCESS."'",
		));

		return $result;
	}


	/**
	 * поиск заявок в ожидании
	 */
	public static function getWaitPayments($userId = 0, $clientId = 0)
	{
		$userId *=1;
		$clientId *=1;

		//либо по юзеру либо по клиенту
		$userCond = ($userId) ? " AND `user_id`='$userId'" :
			(($clientId) ? " AND `client_id`='$clientId'" : '');

		$waitCond = " `status`='".self::STATUS_WAIT."'";


		$models = self::model()->findAll([
			'condition'=>"
				$waitCond
				$userCond
			",
			'order'=>"`date_add` DESC",
		]);

		return $models;
	}

}