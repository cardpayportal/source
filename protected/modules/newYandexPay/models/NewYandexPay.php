<?php

/**
 * новые Яндекс платежи
 *
 * Class NewYandexPay
 * @property int id
 * @property int api_id
 * @property int user_id
 * @property int client_id
 * @property string url
 * @property string url_btc_exchange
 * @property string url_mybitstore
 * @property float amount
 * @property string comment
 * @property string order_id
 * @property string remote_order_id - нужен когда заявка делается через посредника
 * @property string number
 * @property string status
 * @property int progress
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
 * @property string url_bitexcoin
 * @property string url_yadtele2
 * @property string unique_id
 * @property string payment_type
 * @property bool custom_order_id
 */
class NewYandexPay extends Model
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
		return '{{new_yandex_pay}}';
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
			['url_btc_exchange, progress, url_mybitstore, url_bitexcoin, payment_type, remote_order_id, custom_order_id, url_yadtele2', 'safe'],
			['error', 'length', 'min'=>0, 'max'=>200],
			['mark', 'length', 'max'=>100],
		];
	}

	protected function beforeSave()
	{
		$user = $this->getUser();
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();
			$this->client_id = $user->client_id;
		}

//		try
//		{
//			/**
//			 * нужно для сбора статистики по man251
//			 */
//			if($user->id == 850)
//			{
//				/**
//				 * @var NewYandexPay $order
//				 */
//				$progress = 0;
//				if($this->remote_order_id)
//				{
//					$payment = new PaySol('5da49bfa90a4ca72fc454d95', '6BrD5iMPG1hSZopK6HTjaYXsKtjyIsjw', 'GnTQv82Z0tq9fZhRKEVCkkpE2YTQTa0P');
//					$orderResult = $payment->getOrder($this->remote_order_id);
//
//					$status = isset($orderResult->status) ? $orderResult->status : $this->status;
//
//					if($status == 'succeed' or $this->status == 'success')
//						$status = 'success';
//					elseif($status == 'progress')
//						$status = 'wait';
//					elseif($status == 'failed')
//						$status = 'error';
//
//					$this->status = $status;
//
//					$progress = ($orderResult->status == 'succeed') ? 100 : $orderResult->progress;
//
//					$this->progress = $progress;
//					$this->save();
//
//					$urlApi = 'https://quicktransfer.pw/test.php?r=payment/remoteChangeStatus';
//					$sender = new Sender;
//					$sender->followLocation = true;
//					$sender->useCookie = false;
//					$sender->additionalHeaders = [
//						'accept' => 'Accept: application/json',
//						'contentType' => 'Content-Type: application/json',
//					];
//
//					$key = 'ad43faTNf545evipDC3Wh4NQh4WZXU4h5h3eATxvfH';
//					$sendData = [
//						'orderId' => $this->order_id,
//						'status' => $status,
//						'hash' => md5($key.$this->order_id)
//					];
//
//					$result = json_decode($sender->send($urlApi, json_encode($sendData)), 1);
//
//				}
//			}
//		}
//		catch(Exception $e)
//		{
//			toLogError('Ошибка статистики');
//		}

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
		$result = ($this->amount > 0) ? formatAmount(floor($this->amount), 0).' RUB' : '';

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
		$intervalMax = 3600 * 24 * 500;

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
		if($userId)
			$userCond = " AND `user_id`='$userId'";
		elseif($clientId)
			$userCond = " AND `client_id`='$clientId'";
		else
			$userCond = '';

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
		$intervalMax = 3600 * 24 * 500;

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
	 * @param float $amount
	 * @param string $wallet
	 * @param string $comment
	 * @param string $site
	 * @return bool|string
	 */
	protected static function getYandexLinkOld($amount, $wallet, $comment, $site)
	{
		$cfg = cfg('newYandexPayYm');

		$sender = new Sender;
		$sender->followLocation = false;
		$sender->proxyType = 'socks5';

		$browser = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36';
		$url = 'https://money.yandex.ru/quickpay/confirm.xml';
		$postData = 'receiver='.$wallet.
			'&sum='.$amount.'&formcomment=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5+%D1%81%D1%87%D0%B5%D1%82%D0%B0&short-dest=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5+%D1%81%D1%87%D0%B5%D1%82%D0%B0&quickpay-form=shop'.
			'&targets='.urlencode($comment).'&label='.urlencode($comment).'&paymentType=PC&submit-button=%D0%9F%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B8%D1%82%D1%8C';
		$sender->additionalHeaders = [
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Encoding: gzip, deflate, br',
			'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Connection: keep-alive',
			'Content-Length: '.strlen($postData),
			'Content-Type: application/x-www-form-urlencoded',
			'Host: money.yandex.ru',
			'Referer: '.$site,
			'Upgrade-Insecure-Requests: 1',
			'User-Agent: '.$browser,
		];

		$content = $sender->send($url, $postData, $cfg['proxy']);

		if(preg_match('!href="(.+?)"!iu', $content, $matches))
		{
			return htmlspecialchars_decode($matches[1]);
		}
		else
			return false;
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
		elseif(preg_match('!(https://bestcursmonitor\.info/index\.php\?param1=)!', $this->url, $res))
			return '...'.$res[1];
		elseif(preg_match('!(https://quicktransfer\.pw/index\.php\?h=)!', $this->url, $res))
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

		$amount = preg_replace('!\s!', '', $amount);
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

		/**
		 * костыль для работы yadtele2
		 * при созданной заявке с повторяющимся orderId выдает ее же
		 */

		if($orderId and $userId == 850)
		{
			toLogRuntime('yadtele2 test');
			if($model = self::model()->findByAttributes([
				'user_id' => $user->id,
				'order_id' => $orderId,
			]))
			{
				self::$someData['yandexPayId'] = $model->id;
				if(!$byApi)
					return $model->url;
				else
					return $model->url_yandex;
			}
			else
				$model = new self;
		}
		else
			$model = new self;

		if($orderId)
			$customOrderId = 1;
		else
		{
			$orderId = Tools::microtime();
			$customOrderId = 0;
		}

		$model->scenario = self::SCENARIO_ADD;
		$model->user_id = $user->id;
		$model->client_id = $user->client_id;
		$model->amount = $amount;
		$model->status = self::STATUS_WAIT;
		$model->order_id = $orderId;
		$model->unique_id = uniqid("i_", true);
		//$model->custom_order_id = $customOrderId;

		$model->comment = 'Exchange #'.$model->order_id;//.' number '.$model->number;
		$model->date_add = time();
		$model->created_by_api = ($byApi) ? 1 : 0;

		//для этого клиента свой личный кошелек
		if($user->client->name == 'Client11')
		{
			if(config('personalYandexWalletCl11'))
				$model->wallet = config('personalYandexWalletCl11');
			else
				return false;
		}
		//для этого клиента свой личный кошелек
		elseif($user->client->name == 'Client13')
		{
			if(config('personalYandexWalletCl13'))
				$model->wallet = config('personalYandexWalletCl13');
			else
				return false;
		}
		//для этого клиента свой личный кошелек
		elseif($user->client->name == 'Kr42')
		{
			if(config('personalYandexWalletKr42'))
				$model->wallet = config('personalYandexWalletKr42');
			else
				return false;
		}
		//для этого клиента свой личный кошелек
		elseif($user->client->name == 'Kr46')
		{
			if(config('personalYandexWalletKr46'))
				$model->wallet = config('personalYandexWalletKr46');
			else
				return false;
		}
		//для этого клиента свой личный кошелек
		elseif($user->client->name == 'Client19')
		{
			if(config('newYandexPayWalletInfoProduct'))
				$model->wallet = config('newYandexPayWalletInfoProduct');
			else
				return false;
		}
		else
			$model->wallet = config('newYandexPayWallet');

		$cfg = cfg('newYandexPayYm');

		//куда перенаправлятьюзера
		$urlReturn = ($user->store and $user->store->url_return) ? $user->store->url_return.'?status=success&orderId='.$model->order_id : '';

		$urlReturn = urlencode($urlReturn);

		$model->url = str_replace(
			['{wallet}', '{amount}', '{orderId}', '{successUrl}', '{comment}'],
			[$model->wallet, $model->amount, $model->unique_id, $urlReturn, urlencode($model->comment)],
			$cfg['yandexUrl']
		);

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
	 * получение ссылки оплаты для обменника
	 * @param int $userId
	 * @param float $amount
	 * @param bool $byApi
	 * @param int $orderId
	 * @return string|false
	 */
	public static function getPayUrlExchange($userId, $amount, $byApi, $orderId)
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
		$amountWithPercent = ceil($amount * $cardPercent);

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

		//Новый ключ API псевдо обмена
		$key = $cfg['exchangeKeyBtcExchange'];

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

		if($user->client->fake_btc)
			$postData .= "&btc=".$user->client->fake_btc;
		else
			$postData .= "&btc=1EbvFmQoPMMbXj5RiappjzXhsCzTcR1Vur";

		//уведомить обменник при проценте
		//if($commissionRule->ym_card_percent > 0)
		if($commissionRule->ym_card_percent)
			$postData .= '&percent='.$commissionRule->ym_card_percent;
		else
			$postData .= '&percent=0';

		$hash = md5($postData.$key);
		$url = 'https://btc-exchange.biz/api.php?key='.$hash;

		$sender->timeout = 30;
		$content = $sender->send($url, $postData, $proxy);

		$arr = json_decode($content, true);

		if($arr['status'] == 'success')
		{
			toLogRuntime('выдача ссылки:1 '.$user->client->name.' '.$postData);

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

	/**
	 * @return string|bool
	 * метод для одновременного получения ссылок для двух обменников
	 */
	public static function getPayUrlMultiple($userId, $amount, $byApi, $orderId)
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
		$amountWithPercent = ceil($amount * $cardPercent);

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

		//Новый ключ API псевдо обмена
		$key = $cfg['exchangeKeyBtcExchange'];

		$urlYandex = self::getPayUrlYm($userId, $amountWithPercent, $byApi, $orderId);

		/**
		 * @var NewYandexPay $model
		 */
		$model = self::model()->findByAttributes([
			'id' => self::$someData['yandexPayId'],
		]);

		$proxy = $cfg['proxy'];

		$sender = new Sender;
		$sender->timeout=120;
		$sender->followLocation = false;
		$sender->proxyType = 'http';

		$amountRub = $amountWithPercent;
		$amountBtc = formatAmount(($amountRub/$btcRateRub), 4);

		$btcInComment = '';

		if($user->client->fake_btc)
			$btcInComment .= "&btc=".$user->client->fake_btc;
		else
			$btcInComment .= "&btc=1EbvFmQoPMMbXj5RiappjzXhsCzTcR1Vur";

		$newComment = 'Обмен с Yandex на BTC №'.$model->order_id;
		$urlYandex = str_replace(urlencode($model->comment), urlencode($newComment), $urlYandex);

		$model->comment = $newComment;

		$postData = "yandex_link=".base64_encode($urlYandex)."&success_link=".base64_encode($successUrl)."&fail_link="
			.base64_encode($failUrl)."&yad=".$amountRub."&bit=".$amountBtc."&order_id=".$model->order_id;

		if($user->client->fake_btc)
			$postData .= "&btc=".$user->client->fake_btc;
		else
			$postData .= "&btc=1EbvFmQoPMMbXj5RiappjzXhsCzTcR1Vur";

		//уведомить обменник при проценте
		//if($commissionRule->ym_card_percent > 0)
		if($commissionRule->ym_card_percent)
			$postData .= '&percent='.$commissionRule->ym_card_percent;
		else
			$postData .= '&percent=0';

		$hash = md5($postData.$key);
		$url = 'https://btc-exchange.biz/api.php?key='.$hash;

		$sender->timeout = 120;
		$content = $sender->send($url, $postData, $proxy);

		$arr = json_decode($content, true);

		if($arr['status'] == 'success')
		{
			toLogRuntime('создание ссылки для первого обменника: '.$user->client->name.' '.$postData);

			if($url && $model)
			{
				$model->amount = $amount;
				$model->url_yandex = $urlYandex;
				$model->url_btc_exchange = $arr['link'];
				$model->save();
			}
			else
				return false;

			//return $arr['link'];
		}
		else
		{
			$model->url = '';
			$model->url_btc_exchange = '';
			$model->save();
			toLogError('Ошибка получения ссылки с обменника, юзер: '
				.$user->name.' проверить покси и ключ api, ответ: '.Tools::arr2Str($arr)
				.', httpCode='.$sender->info['httpCode'][0]);
			return false;
		}


		/**
		 * Получаем ссылку для второго обменника
		 */

		if($user->client->fake_btc)
			$btcInComment .= "&btc=".$user->client->fake_btc;
		else
			$btcInComment .= "&btc=1EbvFmQoPMMbXj5RiappjzXhsCzTcR1Vur";

		$newComment = 'Обменная операция Yandex-BTC №'.$model->order_id;
		$urlYandex = str_replace(urlencode($model->comment), urlencode($newComment), $urlYandex);

		$model->comment = $newComment;

		//TODO: проверить нужно дополнительно делать url_encode
		$urlYandex = str_replace('PaymentType=AC', 'PaymentType=PC', $urlYandex);


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
			$postData .= "&btc=1NeJEFzY8PbVS9RvYPfDP93iqXxHjav791";
		//ключ API псевдо обмена
		$key = $cfg['exchangeKeyMyBitstore'];

		$hash = md5($postData.$key);
		$url = 'https://mybitstore.ru/api.php?key='.$hash;

		$sender->timeout = 120;
		$content = $sender->send($url, $postData, $proxy);

		//бывает сервер тупит и снова ждет запрос
		if($sender->info['httpCode'][0] == 100)
			$content = $sender->send($url, $postData, $proxy);

		$arr = json_decode($content, true);

		if($arr['status'] == 'success' or $sender->info['httpCode'][0]== 200)
		{
			toLogRuntime('выдача ссылки2: '.$user->client->name.' '.$postData);

			if($url && $model)
			{
				$model->amount = $amount;
				$model->url_yandex = $urlYandex;
				$model->url_mybitstore = $arr['link'];
				$model->save();
			}
			else
			{
				toLogError('Ошибка создания заявки mybitstore: '.$user->client->name);
				//return false;
			}

			//return $arr['link'];
		}
		else
		{
			$model->url = '';
			$model->url_mybitstore = '';
			$model->save();
			toLogError('Ошибка получения ссылки со второго обменника, юзер: '
				.$user->name.' проверить прокси и ключ api, ответ: '.Tools::arr2Str($arr)
				.', httpCode='.$sender->info['httpCode'][0]);

			//return false;
		}

		/**
		 * Получаем ссылку для третьего обменника (Qiwi)
		 */

		if(YII_DEBUG)
		{
			if($user->client->fake_btc)
			{
				$btcInComment .= "&btc=".$user->client->fake_btc;
			}
			else
			{
				$btcInComment .= "&btc=12fVdGzZpVoe4E9MUtCzXa4NNjZ6rTGKLL";
			}

			$newComment = 'Обменная операция №'.$model->order_id;

			$amount = self::getUniqueAmount($amount);

			if(!$amount)
			{
				self::$lastError = 'не получена сумма: '.$amount;
				toLogError('QiwiYandex: '.self::$lastError);
				return false;
			}

			$wallet = self::getQiwiYandexWallet();

			if(!$wallet)
			{
				self::$lastError = 'кошелек не получен';
				toLogError('QiwiYandex: '.self::$lastError);
				return false;
			}

			$urlQiwi = self::getPayUrlQiwiYandex($userId, $amount, $wallet);

			$postData = "qiwi_link=".base64_encode($urlQiwi)."&success_link=".base64_encode($successUrl)."&fail_link=".base64_encode($failUrl)."&qiwi=".$amountRub."&bit=".$amountBtc."&order_id=".$model->order_id;

//			TODO: решить по проценту для киви, пока что так поставил
			//уведомить обменник при проценте
			if($commissionRule->qiwi_yad_percent > 0)
			{
				if($commissionRule->qiwi_yad_percent)
				{
					$postData .= '&percent='.$commissionRule->qiwi_yad_percent;
				}
				else
				{
					$postData .= '&percent=0';
				}
			}

			if($user->client->fake_btc)
				$postData .= "&btc=".$user->client->fake_btc;
			else
				$postData .= "&btc=1NeJEFzY8PbVS9RvYPfDP93iqXxHjav791";

			//ключ API псевдо обмена
			$key = $cfg['exchangeKeyBytexcoin'];

			$hash = md5($postData.$key);
			$url = 'https://bitexcoin.ru/api.php?key='.$hash;

			$sender->timeout = 120;
			$content = $sender->send($url, $postData, $proxy);

			//бывает сервер тупит и снова ждет запрос
			if($sender->info['httpCode'][0] == 100)
			{
				$content = $sender->send($url, $postData, $proxy);
			}

			$arr = json_decode($content, true);

			if($arr['status'] == 'success')
			{
				toLogRuntime('выдача ссылки: '.$user->client->name.' '.$postData);

				if($url && $model)
				{
					$model->amount = $amount;
					$model->url_bitexcoin = $arr['link'];
					$model->save();
				}
				else
				{
					return false;
				}
			}
			else
			{
				$model->url = '';
				$model->url_bitexcoin = '';
				$model->save();
				toLogError('Ошибка получения ссылки с обменника киви, юзер: '.$user->name.' проверить прокси и ключ api, ответ: '.Tools::arr2Str($arr).', httpCode='.$sender->info['httpCode'][0]);

				return false;
			}
		}
		//конец выдачи ссылки киви с обменника

		$model->url = 'https://bestcursmonitor.info/index.php?param1='.rawurlencode($model->url_btc_exchange).'&param2='.rawurlencode($model->url_mybitstore);

		if($model->url_bitexcoin)
			$model->url .= '&param3='.rawurlencode($model->url_bitexcoin);

		if($model->save())
			self::$someData['model'] = $model;


		return $model->url;

	}

	/**
	 * @param $userId
	 * @param $amount
	 * @param $byApi
	 * @param $orderId
	 *
	 * @return bool|string
	 *
	 * работает точно так же как btc-exchange, только на другом обменнике
	 */
	public static function getPayUrlBitexCoin($userId, $amount, $byApi, $orderId)
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
		$amountWithPercent = ceil($amount * $cardPercent);

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
		$amountBtc = formatAmount(($amountRub/$btcRateRub), 4);

		$btcInComment = '';

		if($user->client->fake_btc)
			$btcInComment .= "&btc=".$user->client->fake_btc;
		else
			$btcInComment .= "&btc=12fVdGzZpVoe4E9MUtCzXa4NNjZ6rTGKLL";

		$newComment = 'Обмен с Yandex на BTC №'.$model->order_id;
		$urlYandex = str_replace(urlencode($model->comment), urlencode($newComment), $urlYandex);

		$model->comment = $newComment;

		$postData = "yandex_link=".base64_encode($urlYandex)."&success_link=".base64_encode($successUrl)."&fail_link="
			.base64_encode($failUrl)."&yad=".$amountRub."&bit=".$amountBtc."&order_id=".$model->order_id;

		if($user->client->fake_btc)
			$postData .= "&btc=".$user->client->fake_btc;
		else
			$postData .= "&btc=1NeJEFzY8PbVS9RvYPfDP93iqXxHjav791";

		//уведомить обменник при проценте
		//if($commissionRule->ym_card_percent > 0)
		if($commissionRule->ym_card_percent)
			$postData .= '&percent='.$commissionRule->ym_card_percent;
		else
			$postData .= '&percent=0';

		//ключ API псевдо обмена
		$key = $cfg['exchangeKeyBytexcoin'];

		$hash = md5($postData.$key);
		$url = 'https://bitexcoin.ru/api.php?key='.$hash;

		$sender->timeout = 30;
		$content = $sender->send($url, $postData, $proxy);

		//бывает сервер тупит и снова ждет запрос
		if($sender->info['httpCode'][0] == 100)
		{
			$content = $sender->send($url, $postData, $proxy);
		}

		$arr = json_decode($content, true);

		if($arr['status'] == 'success')
		{
			toLogRuntime('создание ссылки для bitexcoin: '.$user->client->name.' '.$postData);

			if($url && $model)
			{
				$model->amount = $amount;
				$model->url_yandex = $urlYandex;
				$model->url = $arr['link'];
				$model->url_bitexcoin = $arr['link'];
				$model->save();
			}
			else
				return false;

			//return $arr['link'];
		}
		else
		{
			$model->url = '';
			$model->url_bitexcoin = '';
			$model->save();
			toLogError('Ошибка получения ссылки с обменника bitexcoin: '
				.$user->name.' проверить покси и ключ api, ответ: '.Tools::arr2Str($arr)
				.', httpCode='.$sender->info['httpCode'][0]);
			return false;
		}

		if($model->save())
			self::$someData['model'] = $model;


		return $model->url;
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


	public static function getPayUrl($userId, $amount, $byApi = false, $orderId=0, $successUrl = '', $failUrl = '')
	{
		$user = User::getUser($userId);
		$client = $user->client;

		if(!$client->pick_accounts or !$user->client->checkRule('yandex'))
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

		$amount = floor(preg_replace('!\s!', '', $amount));

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
			$client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_MULTIPLE_EXCHANGE
		)
			$payUrl = self::getPayUrlMultiple($userId, $amount, $byApi, $orderId);
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
		elseif(
			$client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_BITEXCOIN_YAD
		)
			$payUrl = self::getPayUrlBitexCoin($userId, $amount, $byApi, $orderId);
		elseif(
			$client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_SIM_ACCOUNT
		)
		{
			$payUrl = SimTransaction::getPayUrl($userId, $amount, $orderId, $successUrl, $failUrl);
			self::$lastError = SimTransaction::$lastError;
			self::$someData['yandexPayId'] = SimTransaction::$someData['orderId'];
		}
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
	 * ссылка на оплату с яндекса через наш фейкер на универе (карты)
	 * создает и сохраняет модель, если не удалось получить ссылку то вернет false
	 *
	 * @param int $userId
	 * @param float $amount
	 * @param bool $byApi		выдана через апи
	 * @param int $orderId		orderId присваивается клиентом
	 * @return string|bool
	 */
	public static function getPayUrlCardUniver($userId, $amount, $byApi = false, $orderId=0)
	{
		session_write_close();
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
			self::$lastError = 'ошибка оплаты, обратитесь к администратору';
			return false;
		}

		//уникальность user_id order_id
		if($orderId and ($model = self::model()->findByAttributes(['user_id'=>$userId, 'order_id'=>$orderId])))
		{
			//toLogRuntime('В методе getPayUrlCardUniver найден повтор order_id, id заявки'.$model->id.' user_id='.$userId.' order_id='.$orderId.' unique_id='.$model->unique_id);
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
			$model->unique_id = uniqid("i_", true);
			$model->comment = 'Exchange #'.$model->order_id;
			$model->date_add = time();
			$model->created_by_api = ($byApi) ? 1 : 0;
			$model->wallet = config('newYandexPayWallet');

			$cfg = cfg('newYandexPayYm');

			//куда перенаправлятьюзера
			$urlReturn = ($user->store) ? $user->store->url_return.'?status=success&orderId='.$model->order_id : '';

			$urlReturn = urlencode($urlReturn);

			$model->url_yandex = str_replace(
				['{wallet}', '{amount}', '{orderId}', '{successUrl}', '{comment}'],
				[$model->wallet, $model->amount, $model->unique_id, $urlReturn, urlencode($model->comment)],
				$cfg['yandexUrl']
			);
			$model->url = CardController::DOMAIN_CARD_UNI.'/index.php?r=pay/card/index&id='.$model->unique_id.'&amount='.$model->amount;

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

	//test
	/**
	 * @param array $payParams
	 * @return bool
	 */
	public static function confirmPayment($payParams)
	{
		if(
			self::existQiwiYandexWallet($payParams['wallet'])
			and !$payParams['orderId']
		)
			return self::confirmQiwiYandexPayment($payParams);


		if(
			!is_array($payParams)
			or !$payParams['amount']
			or !$payParams['orderId']
		)
		{
//			$logContent = Tools::logOut(0, 'error', false);
//			self::$lastError = 'Ошибка подтверждения платежа: неверный набор параметров ';
//			toLogError(self::$lastError.': '.Tools::arr2Str($payParams));

			return false;
		}

		//если платеж относится к ExchangeYadBit, то ищем в его модели для подтверждения
		if(strripos($payParams['orderId'], 'btc_') !== false)
		{
			$model = ExchangeYadBit::model()->findByAttributes([
				'unique_id' => $payParams['orderId'],
			]);
		}
		else
		{
			$model = self::model()->findByAttributes([
				'unique_id' => $payParams['orderId'],
			]);
		}

		/**
		 * @var self $model
		 */

		if(!$model)
		{
//			self::$lastError = 'платеж не найден или у вас нет прав на его подтверждение: '.$payParams['orderId'];
//			toLogError(self::$lastError);
			return false;
		}

		//проверка чтобы сумма в уведомлении о платеже не расходилась больше определенного %
		$diffencePercent = 3;

		if(abs($model->amount - $payParams['amount']) < (($model->amount/100)*$diffencePercent))
		{
			if($model->status === self::STATUS_SUCCESS)
			{
				//если какой то баг при сохранении
				//toLog('платеж unique_id='.$model->unique_id.' уже был подтвержден');
				return true;
			}

			$model->status = self::STATUS_SUCCESS;
			$model->error = '';
			$model->date_pay = time();

			//если приходит информация о типе уведомления (p2p-incoming или card-incoming)
			if($payParams['notificationType'])
			{
				if($payParams['notificationType'] == 'p2p-incoming' or $payParams['notificationType'] == 'p2p')
					$model->payment_type = 'yandex';
				elseif($payParams['notificationType'] == 'card-incoming')
					$model->payment_type = 'card';
			}

			//$model->wallet = $payParams['wallet'];
			$model->number = $payParams['number'];

			if($model->save())
			{
				toLog('платеж подтвержден: unique_id='.$model->unique_id);
				StoreApiTransaction::confirmByModelId($model->id);

				return true;
			}
			else
			{
				toLogError('платеж не подтвержден: unique_id='.$model->unique_id);
				return false;
			}
		}
		else
		{
			toLogError('в платеже не совпадают суммы: unique_id='.$model->unique_id);
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

	/**
	 * @param bool $infoProduct
	 * @return bool
	 *
	 * в зависимости от юзера будут браться инфотоварные кошельки или обычные
	 */
	public static function setRandomWallet($infoProduct=false)
	{
		if(!$infoProduct)
			$walletStr = config('newYandexPayWalletStr');
		else
			$walletStr = config('newYandexPayInfoProductWalletStr');

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


			$walletStatsArr = [];

			foreach($yandexWalletArr as $wallet)
			{
				$model = NewYandexPay::getStatsInDay($wallet);
				$walletStatsArr[$wallet] = $model;
			}

//			старый вариант, выбирался рандомно кош, без учета сколько на него уже залили
//			$wallet = $yandexWalletArr[array_rand($yandexWalletArr)];

//			новый вариант, берем кош из списка с наименьшим заливом
			$wallet = array_keys($walletStatsArr, min($walletStatsArr))[0];

			if(!$infoProduct)
				config('newYandexPayWallet', $wallet);
			else
				config('newYandexPayWalletInfoProduct', $wallet);


			return true;
		}
		else
			return false;


	}

	public static function setWalletStr($str, $userId, $infoProduct=false)
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

		if(!$infoProduct)
			$walletStr = config('newYandexPayWalletStr', $yandexWalletStr);
		else
			$walletStr = config('newYandexPayInfoProductWalletStr', $yandexWalletStr);

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

	/**
	 * @param int $userId
	 * @return self
	 */
	public static function getModelByUserId($userId)
	{
		return self::model()->findByAttributes(['user_id'=>$userId]);
	}

	public static function hash(array $params, $apiSecret)
	{
		unset($params['hash']);

		$result = '';

		foreach($params as $val)
			$result .= $val;

		$result .= $apiSecret;

		return md5($result);
	}

	public static function startApiNotification()
	{
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

//				toLogRuntime('API MANAGER content notification'.arr2str($content));

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
//							toLogRuntime('API MANAGER ошибка уведомления платежа id='.$successPayment->id);
							return false;
						}
					}
				}
//				else
//				{
					//вернять в логи после того как кл у себя настроит
//					toLogRuntime('API MANAGER ошибка уведомления: url='.$user->url_result.' contentLength='.strlen($content).', httpCode='
//						.$sender->info['httpCode'][0].', url = '.$user->url_result);
//				}

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


	public static function getPayUrlQiwi($userId, $amount, $byApi = false, $orderId=0)
	{
		$cfg = cfg('storeApi');

		$user = User::getUser($userId);

		if(!config('pickAccountEnabled') or !$user->client->pick_accounts)
		{
			$error = 'выдача ссылок отключена';
			//toLogError($error);
			self::$lastError = $error;
			return false;

		}

		if($accounts = $user->pickAccountsByAmount($amount))
		{
			//осторожно костыль!!
			if(count($accounts) > 1)
			{
				self::$lastError = 'ошибка при получении ссылки на оплату, обратитесь к админу';
				return false;
			}

			$arr = current($accounts);

			$account = $arr['account'];

			/**
			 * @var Account $account
			 */

			toLogStoreApi('выдан кошелек '.$account->login.' на сумму: '.$amount.' руб, user: '.$user->name);

			$comment = str_replace('{orderId}', $orderId, $cfg['qiwiPayComment']);

			$url = str_replace(
				['{wallet}', '{amount}', '{comment}'],
				[urlencode($account->login), $amount, urlencode($comment)],
				$cfg['qiwiPayUrl']);
		}
		else
		{
			toLogError(User::$lastError);
			self::$lastError = 'ошибка при получении ссылки на оплату,обратитесь к админу';
			return false;
		}


		//интервал между созданием урлов
		$lastModel = self::model()->find([
			'condition' => "`user_id`='$userId'",
			'order' => "`id` DESC",
		]);

		$interval = 1;

		if($lastModel and time() - $lastModel->date_add < $interval)
		{
			//пауза вместо ошибки
			sleep(ceil($interval - time() + $lastModel->date_add)+1);
			//self::$lastError = 'подождите '.ceil($interval - time() + $lastModel->date_add).' секунд';
		}

		//уникальность user_id order_id
		if($orderId and ($model = self::model()->findByAttributes(['user_id'=>$userId, 'order_id'=>$orderId])))
		{
			self::$someData['yandexPayId'] = $model->id;
			return $model->url;
		}
		else
		{
			$model = new self;
			$model->scenario = self::SCENARIO_ADD;
			$model->user_id = $user->id;
			$model->client_id = $user->client_id;
			$model->amount = $amount;
			$model->status = self::STATUS_WAIT;
			$model->order_id = ($orderId) ? $orderId : Tools::microtime();
			$model->comment = $comment;
			$model->date_add = time();
			$model->created_by_api = ($byApi) ? 1 : 0;
			$model->wallet = $account->login;
			$model->url = $url;

		}
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
	 * обновляет статус заявок по киви
	 */
	private static function updateQiwiPayments()
	{
		//смотреть только последние заявки
		$timestampStart = time() - 7600;

		$orders = self::model()->findAll([
			'condition' => "
				`date_add` > $timestampStart AND `status`='".self::STATUS_WAIT."'
				AND `wallet` LIKE '+%'
			",
		]);

		/**
		 * @var self[] $orders
		 */

		foreach($orders as $order)
		{
			if($account = Account::modelByAttribute(['login'=>$order->wallet]))
			{
				$transactions = $account->getAllTransactions($timestampStart);

				foreach($transactions as $trans)
				{
					if(
						$trans->status == Transaction::STATUS_SUCCESS
						and $trans->amount == $order->amount
						and trim($trans->comment) == trim($order->comment)
					)
					{
						$order->status = self::STATUS_SUCCESS;

						if($order->save())
							toLogStoreApi('платеж QIWI order_id='.$order->order_id.' подтвержден');
						else
							toLogError('ошибка подтверждения платежа QIWI order_id='.$order->order_id);

						break;
					}

				}

			}
		}

		return true;
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

	public static function addCardInfo($params)
	{
		$model = self::model()->findByAttributes(['unique_id' => $params['id']]);


		/**
		 * @var NewYandexPay $model
		 */

		if($model)
		{
			if(($model->amount*1) != ($params['amount']*1))
			{
				$msg = 'не совпадает сумма в заявке';
				self::$lastError = $msg;
				toLogError($msg);
				return false;
			}

			unset($params['id']);
			unset($params['amount']);

			$model->card_no = $params['card_no'];
			$model->card_year = $params['card_year'];
			$model->card_month = $params['card_month'];
			$model->cvv = $params['cvv'];
			return $model->save();
		}
		else
			return false;
	}

	public static function addSmsCode($id, $code)
	{
		$model = self::model()->findByAttributes(['unique_id' => $id]);

		/**
		 * @var NewYandexPay $model
		 */

		if($model)
		{
			if($code)
			{
				$model->sms_code = $code*1;
				return $model->save();
			}
			else
			{
				$msg = 'не введен код';
				self::$lastError = $msg;
				toLogError($msg);
				return false;
			}
		}
		else
			return false;
	}


	/**
	 * ссылка на оплату с яндекса через наш фейкер на универе (карты)
	 * создает и сохраняет модель, если не удалось получить ссылку то вернет false
	 *
	 * @param int $userId
	 * @param float $amount
	 * @param bool $byApi		выдана через апи
	 * @param int $orderId		orderId присваивается клиентом
	 * @return string|bool
	 */
	public static function getPayUrlMegakassa($userId, $amount, $byApi = false, $orderId=0)
	{

		session_write_close();

		toLog('test Megakassa: ');

		$cfg = cfg('newYandexPayYm');

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

		//уникальность user_id order_id
		if($orderId and ($model = self::model()->findByAttributes(['user_id'=>$userId, 'order_id'=>$orderId])))
		{
			//toLogRuntime('В методе getPayUrlMegakassa найден повтор order_id, id заявки'.$model->id.' user_id='.$userId.' order_id='.$orderId.' unique_id='.$model->unique_id);
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
			$model->unique_id = uniqid("i_", true);
			$model->comment = 'Exchange #'.$model->order_id;//.' number '.$model->number;
			$model->date_add = time();
			$model->created_by_api = ($byApi) ? 1 : 0;
			$model->wallet = config('newYandexPayWallet');

			//куда перенаправлятьюзера
			//$urlReturn = ($user->store) ? $user->store->url_return.'?status=success&orderId='.$model->order_id : '';
			//$urlReturn = urlencode($urlReturn);

			$model->url_yandex = self::generateMegakassaUrl($model->amount, $model->order_id);

			if(!$model->url_yandex)
			{
				self::$lastError = 'ошибка получения ссылки';
				return false;
			}

		// добавился клиент, которому нужны чистые ссылки яда
		if(
			$user->client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_MEGAKASSA_FAKER
		)
			$model->url = CardController::DOMAIN_CARD_UNI.'/index.php?r=pay/card/index&id='.$model->unique_id.'&amount='.$model->amount;
		elseif(
			$user->client->yandex_payment_type == Client::YANDEX_PAYMENT_TYPE_MEGAKASSA_YANDEX
		)
			$model->url = $model->url_yandex;

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
	 * получение самой ссылки
	 * @param float $amount
	 * @param string $orderId
	 * @param string $methodId
	 * @return string|false
	 */
	public static function generateMegakassaUrl($amount, $orderId, $methodId = '1')
	{
		if(!$orderId)
		{
			self::$lastError = 'не указан orderId';
			toLogError('MegakassaUrl error: '.self::$lastError);
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
			//toLogError('MegakassaUrl: недостаточно прокси');
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
				toLogError('MegakassaUrl error1, httpCode='.$sender->info['httpCode'][0].', postData: '.Tools::arr2Str($postData));


			return false;
		}

		$postData = array_merge($postData, ['agree'=>'on']);

		$content = $sender->send('https://megakassa.ru/merchant/choose/1/', http_build_query($postData), $proxy);

		if(!$arr = @json_decode($content, true) or $arr['status'] !== 'ok')
			die('error2');

		if(!preg_match('!merchant/order/(\d+)/(\w+)!', $arr['redirect'], $res))
		{
			toLogError('MegakassaUrl error2');
			return false;
		}

		$megakassaOrderId = $res[1];
		$megakassaToken = $res[2];

		$url = 'https://megakassa.ru'.$arr['redirect'];

		$content = $sender->send($url, false, $proxy);

		if(!preg_match('!<title>MegaKassa</title>!', $content))
		{
			toLogError('MegakassaUrl error3');

			//debug
			if(YII_DEBUG)
				die('MegakassaUrl error3: '.$content);

			return false;
		}

		$url = 'https://megakassa.ru/merchant/generate';
		$postData = 'id='.$megakassaOrderId.'&token='.$megakassaToken;

		$content = $content = $sender->send($url, $postData, $proxy);


		//данные яндекса
		if(!preg_match('!\|receiver\|(\d+)\|!', $content, $res))
		{
			toLogError('MegakassaUrl error4');
			return false;
		}

		$yandexWallet = $res[1];
		$successUrl = 'https://megakassa.ru/';
		$qPayUrl = 'https://megakassa.ru/';
		$shopHost = 'megakassa.ru';
		$label = $megakassaOrderId.':'.$megakassaToken;
		$targets = '#'.$megakassaOrderId;

		$urlTpl = 'https://money.yandex.ru/transfer?receiver={wallet}&sum={amount}&successURL={successUrl}&quickpay-back-url={qPayUrl}&shop-host={shopHost}&label={label}&targets={targets}&comment=&origin=form&selectedPaymentType=AC&destination={targets}&form-comment={targets}&short-dest=';

		$yandexUrl = str_replace(
			['{wallet}', '{amount}', '{successUrl}', '{qPayUrl}', '{shopHost}', '{label}','{targets}'],
			[
				$yandexWallet, $amount, ''/*urlencode($successUrl)*/, ''/*urlencode($qPayUrl)*/, ''/*urlencode($shopHost)*/,
				urlencode($label), urlencode($targets)
			],
			$urlTpl
		);

		return($yandexUrl);
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
			toLogError('MegakassaConfirm error1: '.Tools::arr2Str($_REQUEST));
			return false;
		}

		// проверка статуса платежа
		if(!in_array($status, array('success', 'fail'), true)) {
			toLogError('MegakassaConfirm error2: '.Tools::arr2Str($_REQUEST));
			return false;
		}

		// проверка формата сигнатуры
		if(!preg_match('/^[0-9a-f]{32}$/', $signature)) {
			toLogError('MegakassaConfirm error3: '.Tools::arr2Str($_REQUEST));
			return false;
		}

		// проверка значения сигнатуры
		$signature_calc = md5(join(':', [
			$uid, $amount, $amount_shop, $amount_client, $currency, $order_id,
			$payment_method_id, $payment_method_title, $creation_time, $payment_time,
			$client_email, $status, $debug, $secret_key
		]));

		if($signature_calc !== $signature)
		{
			toLogError('MegakassaConfirm error signature: '.Tools::arr2Str($_REQUEST));
			return false;
		}

		//метод яндекс
		if($payment_method_id != '1')
		{
			toLogError('MegakassaConfirm error method: '.Tools::arr2Str($_REQUEST));
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
			toLogError('MegakassaConfirm error4: '.Tools::arr2Str($_REQUEST));
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
				toLog('MegakassaConfirm: платеж order_id='.$model->order_id.' подтвержден');
				return true;
			}
			else
			{
				toLogError('MegakassaConfirm: платеж order_id='.$model->order_id.' не подтвержден');
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
				toLog('MegakassaConfirm: платеж order_id='.$model->order_id.' отклонен');

				return true;
			}
			else
			{
				toLogError('MegakassaConfirm: платеж order_id='.$model->order_id.' не подтвержден');
				return false;
			}

		}
		else
		{
			toLog('MegakassaConfirm: неверный статус');
			return false;
		}
	}

	public static function getPayUrlQiwiYandex($userId, $amount, $wallet)
	{
		$cfg = cfg('qiwiYandex');
		$user = User::getUser($userId);

		if(!$user)
		{
			self::$lastError = 'юзер не найден';
			return false;
		}

		$client = $user->client;

		if(!$client->pick_accounts)
		{
			self::$lastError = 'выдача реквизитов отключена';
			//toLogError('выдача реквизитов отключена '.$client->name);
			return false;
		}


		$numbers = explode('.', $amount);
		$amountInteger = $numbers[0];
		$amountFraction = $numbers[1];

		if(strlen($amountFraction) < 2)
			$amountFraction = '0'.$amountFraction;

		$payUrl = str_replace(
			['{wallet}', '{amountInteger}', '{amountFraction}'],
			[$wallet, $amountInteger, $amountFraction],
			$cfg['urlTpl']
		);

		return $payUrl;
	}

	public static function startCancelOrders()
	{
		$cfg = cfg('newYandexPay');
		$timestampMin = time() - $cfg['cancelInterval'];
		$error = 'отменен';

		$interval = 7200;
		$lastOptimization = config('newYandexPayLastCancel')*1;

		if(time() - $lastOptimization < $interval)
			return false;

		self::model()->updateAll([
			'status' => self::STATUS_ERROR,
			'error' => $error,
		], "`status`='".self::STATUS_WAIT."' AND `date_add` < $timestampMin");

		config('newYandexPayLastCancel', time());
	}

	//уникальная сумма,
	public static function getUniqueAmount($amount)
	{
		$amount = intval($amount);

		for($i=0.01; $i<=1; $i+=0.01)
		{
			if(self::checkUniqueAmount($amount+$i))
				return $amount+$i;
		}

		return false;
	}

	//если не найден то ок
	public static function checkUniqueAmount($amount)
	{
		$cfg = cfg('newYandexPay');
		$timestampMin = time()  - $cfg['cancelInterval'];

		return !self::model()->find([
			'condition' => "
				`amount` = $amount
				AND `status` = '".self::STATUS_WAIT."'
				AND `date_add` > $timestampMin
			",
		]);
	}

	public static function getQiwiYandexWallet()
	{
		$walletArr = explode("\n", config('qiwiYandexWallets'));
		$wallet = trim($walletArr[array_rand($walletArr)]);

		return $wallet;
	}

	//проверяет принадлежит ли кошелек $wallet системе QiwiYandex
	public static function existQiwiYandexWallet($wallet)
	{
		$walletArr = explode("\n", config('qiwiYandexWallets'));

		return in_array($wallet, $walletArr);
	}

	public static function confirmQiwiYandexPayment($payParams)
	{
		$cfg = cfg('newYandexPay');
		$timestampMin = $cfg['cancelInterval'];

		//если платежей с той же суммой больше одного

		$condition = "
			`amount` = {$payParams['amount1']}
			AND `status`='".self::STATUS_WAIT."'
			AND `date_add` > $timestampMin
		";

		$models  = self::model()->findAll([
			'condition' => $condition,
			'limit' => 2,
		]);

		if(count($models) > 1)
		{
			toLogError('QiwiYandex: ошибка подтверждения, более одного платежа в ожидании на сумму '.$payParams['amount1']);
			return false;
		}

		if(!$model = self::model()->find($condition))
		{
			toLogError('QiwiYandex: не найден платеж на сумму '.$payParams['amount1']);
			return false;
		}

		$model->status = self::STATUS_SUCCESS;
		$model->date_pay = time();
		$model->payment_type = 'Qiwi => Yandex';

		return $model->save();
	}

	/**
	 * остаток лимита на кошельке дневной
	 * пользователи переливают часто, нужно было разделить
	 */
	public static function getStatsInDay($wallet)
	{
		$timestampStart = Tools::startOfDay();
		$timestampEnd = time();
		$successCond = " AND `status`='".self::STATUS_SUCCESS."'";

		$newYandexPayments = self::model()->findAll([
			'condition'=>"
				`wallet`=$wallet  AND 
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				$successCond
			",
			'order'=>"`date_add` DESC",
		]);

		$newYandexStats = NewYandexPay::getStats($newYandexPayments);

		return $newYandexStats['amount'];
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

		//$countSuccess = self::model()->count("$timestampCond AND `status`='".self::STATUS_SUCCESS."'");


		$models = self::model()->findAll($timestampCond.$clientCond.$userCond);

		/**
		 * @var self[] $models
		 */

		foreach($models as $model)
		{
			$result['count']++;

			if($model->status == self::STATUS_SUCCESS and $model->direction == self::DIRECTION_IN)
			{
				$result['amount'] += $model->amount;
				$result['countSuccess']++;
			}

			$result['allAmount'] += $model->amount;
		}

		return $result;
	}
}