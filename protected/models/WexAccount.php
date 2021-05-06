<?php

/**
 *
 * Class WexAccount
 * @property int id
 * @property string login
 * @property string pass
 * @property string browser
 * @property string email_pass
 * @property string proxy
 * @property int user_id
 * @property int date_check
 * @property User user
 * @property float balance_ru
 * @property float balance_btc
 * @property float balance_zec
 * @property float balance_usd
 * @property float balance_usdt
 * @property float balance_total
 * @property int is_blocked
 * @property Proxy proxyObj
 *
 */
class WexAccount extends Model
{
	const SCENARIO_ADD = 'add';
	const SCENARIO_UPDATE = 'update';

	const MIN_AMOUNT  = 10;
	const MAX_AMOUNT  = 50000;

	private $_bot;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{wex_account}}';
	}

	public function rules()
	{
		return [
			['login', 'unique', 'className'=>__CLASS__, 'attributeName'=>'login', 'message'=>'login уже был добавлен',
				'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD],
			//['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD],
			//['user_id', 'unique', 'className'=>__CLASS__, 'attributeName'=>'user_id', 'message'=>'user уже был добавлен',
			//	'on'=>self::SCENARIO_ADD],
			['login, pass, browser', 'length', 'min'=>1, 'max'=>200, 'allowEmpty'=>false],
			['email_pass', 'default', 'value'=> ''],
			['user_id', 'default', 'value'=> ''],
			['proxy', 'default', 'value'=> ''],
			['balance_ru', 'numerical', 'min'=>0],
			['balance_btc', 'numerical', 'min'=>0],
			['balance_zec', 'numerical', 'min'=>0],
			['balance_usd', 'numerical', 'min'=>0],
			['balance_usdt', 'numerical', 'min'=>0],
			['balance_total', 'numerical', 'min'=>0],
			['is_blocked', 'default', 'value'=>''],
			['date_check', 'safe'],
		];
	}

	/**
	 * @param int $userId
	 * @return self
	 */
	public static function getModelByUserId($userId)
	{
		return self::model()->find("`user_id`=$userId");
	}

	/**
	 * @return int
	 * получаем кол-во свободных акков, на которые можно заменить старые
	 */
	public static function getCountFreeAccounts()
	{
		return count(self::model()->findAllByAttributes(['user_id'=>0]));
	}

	/**
	 * @return mixed
	 * получаем модель свободного аккаунта векс
	 */
	public static function getFreeAccount()
	{
		return self::model()->findByAttributes(['user_id'=>0]);
	}

	public function getPayUrlParams($amount)
	{
		if($amount < self::MIN_AMOUNT or $amount > self::MAX_AMOUNT)
		{
			self::$lastError = 'неверная сумма (должна быть от '.self::MIN_AMOUNT.' до '.self::MAX_AMOUNT.')';
			return false;
		}

		$bot = $this->getBot();

		//$transactions = $bot->wexHistory();

		//теперь getPayUrl возвращает не просто ссылку, а массов с параметрами 'url' и 'apiId' чтобы потом идентифицировать платеж

		//TODO: убрать потом костыль чтобы ссылки были доступны для манов кл5
//
//		$allowedUserId = [
//			354,
//			353,
//			352,
//			300,
//			299,
//			298,
//			297,
//			421,
//			702,
//			703,
//			704,
//			705,
//			706,
//			707,
//			708,
//			709,
//			922,
//		];
//
//		if(array_search($this->user_id, $allowedUserId))
//			$confirmParams = $bot->getYandexPayUrlParams($amount);
		//до этой строки

		//а тут раскомментировать потом
		$confirmParams = $bot->getYandexPayUrlParams($amount);


		if(!$confirmParams)
		{
			self::$lastError = 'ошибка '.$bot->error;
			return false;
		}

		return $confirmParams;
	}

	private function getBot()
	{
		if($this->is_blocked)
			return false;

		if(!$this->_bot)
		{
			$this->_bot =  new WexCurlBot($this->login, $this->pass, $this->proxy, $this->browser);

			if(preg_match('!Аккаунт заблокирован!iu', WexCurlBot::$lastError))
			{
				$this->is_blocked = 1;
				$this->save();
				return false;
			}
		}

		return $this->_bot;
	}

	/**
	 * получение приходов с яндекса
	 * @return array
	 */
	public function getHistory()
	{
		$bot = $this->getBot();

		$history = $bot->wexHistory();

		if(is_array($history))
		{
			if(!$this->updateHistory($history))
				return false;

			return array_reverse($history);
		}
		else
			return $history;
	}

	/**
	 * получение  всех транзакций с векса
	 * @return array
	 */
	public function getHistoryAdmin($pageNum)
	{
		$bot = $this->getBot();

		$history = $bot->historyForAdmin($pageNum);

//		if(is_array($history))
//		{
////			if(!$this->updateHistory($history))
////				return false;
//
//			return array_reverse($history);
//		}
//		else
			return $history;
	}

	public function getRate()
	{
		$bot = $this->getBot();
		return $bot->getYandexFee();
	}

	/**
	 * @return array|bool
	 */
	public function getBalance()
	{
		$bot = $this->getBot();
		return $bot->getBalance();
	}

	/**
	 * @param array $accounts
	 * @return int
	 */
	public static function saveMany($accounts)
	{
		$done = 0;

		foreach ($accounts as $userId=>$params)
		{
			if(!$params['login'])
				continue;

			if(!$model = WexAccount::model()->find("`user_id`='$userId'"))
			{
				$model = new self;
				$model->scenario = self::SCENARIO_ADD;
				$model->user_id = $userId;
			}

			//$model->scenario = self::SCENARIO_ADD;
			$model->attributes = $params;
//			$model->login = $params['login'];
//			$model->pass = $params['pass'];
//			$model->browser = $params['browser'];
//			$model->proxy = $params['proxy'];

			if($model->save())
				$done++;
			else
				break;
		}

		return $done;
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	public function getUser()
	{
		return User::getUser($this->user_id);
	}

	public function updateAccount()
	{
		$history = $this->getHistory();
		$balance = $this->getBalance();

		if($balance !== false and $history !== false)
		{
			$this->balance_ru = $balance['ru'];
			$this->balance_btc = $balance['btc'];
			$this->balance_zec = $balance['zec'];
			$this->balance_usd = $balance['usd'];
			$this->balance_usdt = $balance['usdt'];
			$this->balance_total = $balance['total'];
			$this->date_check = time();
			return $this->save();
		}
		else
		{
			self::$lastError = 'ошибка обновления аккаунта';
			return false;
		}
	}

	/**
	 * @param int $clientId
	 * @return int 				количество обновленных аккаунтов
	 */
	public static function updateClientAccounts($clientId)
	{
		$accounts = self::getClientAccounts($clientId);

		$result = 0;

		foreach($accounts as $account)
		{
			if($account->updateAccount())
			{
				$result++;
			}
			else
				self::$lastError .= '<br>ошибка обновления '.$account->login;
		}

		return $result;
	}

	/**
	 * @param int $clientId
	 * @return self[]
	 */
	public static function getClientAccounts($clientId)
	{
		$client = Client::getModel($clientId);

		if(!$client)
			return false;

		$result = [];

		foreach ($client->users as $user)
		{
			if($account = self::getModelByUserId($user->id))
				$result[] = $account;
		}

		return $result;
	}

	/**
	 * @param array $history
	 * @return bool
	 */
	public function updateHistory(array $history)
	{

		foreach($history as $trans)
		{
			if(!$trans['id'])
			{
				self::$lastError = 'no id in transaction: '.Tools::arr2Str($trans);
				return false;
			}

			if(!$model = TransactionWex::model()->findByAttributes([
				'account_id' => $this->id,
				'wex_id' => $trans['id']
			]))
			{
				//неизменяемые поля
				$model = new TransactionWex;
				$model->scenario = TransactionWex::SCENARIO_ADD;
				$model->account_id = $this->id;
				$model->wex_id = $trans['id'];
				$model->type = $trans['type'];
				$model->status = $trans['status'];
				$model->amount = $trans['amount'];
				$model->currency = ($trans['currency'] == 'RUR') ? TransactionWex::CURRENCY_RUB : '';
				$model->date_add = $trans['date'];
				$model->category = $trans['category'];

				if(!$model->save())
					return false;
			}
		}

		return true;
	}

	/**
	 * @return bool|string
	 * покупаем биток за рубли
	 */
	public function buyBtcRu()
	{
		$bot = $this->getBot();
		return $bot->buyBtcRu();
	}

	/**
	 * @return bool|string
	 * покупаем бакс за рубли
	 */
	public function buyUsdRu()
	{
		$bot = $this->getBot();
		return $bot->buyUsdRu();
	}

	/**
	 * @return bool|string
	 * покупаем ZEC за USD
	 */
	public function buyZecUsd()
	{
		$bot = $this->getBot();
		return $bot->buyZecUsd();
	}

	/**
	 * @return bool|string
	 * покупаем USDT за USD
	 */
	public function buyUsdtUsd()
	{
		$bot = $this->getBot();
		return $bot->buyUsdtUsd();
	}

	/**
	 * @param $address
	 * @return array|bool
	 * делаем запрос на вывод BTC
	 */
	public function withdrawBtc($address)
	{
		$bot = $this->getBot();
		return $bot->withdrawBtc($address);
	}


	/**
	 * @param $address
	 * @return array|bool
	 * делаем запрос на вывод ZEC
	 */
	public function withdrawZec($address)
	{
		$bot = $this->getBot();
		return $bot->withdrawZec($address);
	}

	/**
	 * @param $address
	 * @return array|bool
	 * делаем запрос на вывод USDT
	 */
	public function withdrawUsdt($address)
	{
		$bot = $this->getBot();
		return $bot->withdrawUsdt($address);
	}

	/**
	 * @return bool
	 * подтверждаем вывод через почту
	 */
	public function confirmPaymentTutanota()
	{
		$bot = $this->getBot();
		$bot->emailPass = $this->email_pass;
		return $bot->confirmPaymentTutanota();
	}

	/**
	 * повторная отправка письма с подтверждением вывода
	 */
	public function withdrawControl($transactionId, $action)
	{
		$bot = $this->getBot();
		return $bot->withdrawControl($transactionId, $action);
	}


	/**
	 * @return bool
	 * отправка письма с подтверждением привязки к аккаунту
	 */
	public function sendMessageToConfirmEmail()
	{
		$bot = $this->getBot();
		return $bot->sendMessageToConfirmEmail();
	}

	/**
	 *проверяем авторизован аккаунт или нет
	 */
	public function getAuthStatus()
	{
		$bot = $this->getBot();
		return $bot->isAuth;
	}

	/**
	 * @return mixed
	 * подтверждаем привязку почты к аккаунту
	 */
	public function confirmLinkMailTutanota()
	{
		$bot = $this->getBot();
		$bot->emailPass = $this->email_pass;
		return $bot->confirmLinkMailTutanota();
	}

	/**
	 * @param $params
	 *
	 * @return int
	 * добавление свободных аккаунтов без привязки к user_id
	 */
	public static function addMany($params)
	{
		$accountStr = trim($params['accounts']);
		$addCount = 0;
		$regExp = cfg('regExpWexAccountAdd');

		$res = [];

		if(preg_match_all($regExp, $accountStr, $res))
		{
			foreach($res[1] as $key => $email)
			{
				$wexAccount = new self;
				$wexAccount->scenario = self::SCENARIO_ADD;

				$wexAccount->login = $email;
				$wexAccount->pass = trim($res[2][$key]);
				$wexAccount->email_pass = trim($res[3][$key]);
				if($params['browser'])
					$wexAccount->browser = $params['browser'];
				else
					$wexAccount->browser = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36';

				if($wexAccount->login === 'null')
				{
					self::$lastError = 'не указан логин для аккаунта '.$email.__METHOD__;
					return $addCount;
				}

				if($wexAccount->pass === 'null')
				{
					self::$lastError = 'не указан пароль для аккаунта '.$email.__METHOD__;
					return $addCount;
				}

				if($wexAccount->email_pass === 'null')
				{
					self::$lastError = 'не указан пароль от почты для аккаунта '.$email.__METHOD__;
					return $addCount;
				}

				if ($wexAccount->save())
				{
					toLogRuntime('добавлено 1 : '.$wexAccount->login);
					$addCount++;
				}
				else
				{
					self::$lastError = $email.': '.$wexAccount::$lastError;
					break;
				}
			}
		}
		else
			self::$lastError = 'аккаунтов не найдено';

		return $addCount;
	}

	/**
	 * @return Proxy|null
	 */
	public function getProxyObj()
	{
		if(!$this->proxy)
			return false;

		if(preg_match('!(([^:]+?):([^@]+?)@|)(.+?):(\d{2,7})!', $this->proxy, $res))
		{
			if($model = Proxy::model()->find("`ip`='$res[4]' and `port`='$res[5]'"))
				return $model;
			else
				return false;
		}
		else
			return false;
	}

	/*
	 * если прокси нет либо его нет в бд то return false
	 */
	private function isActualProxy()
	{
		if(!$this->proxy)
			return false;

		if($proxyObj = $this->getProxyObj())
		{
			//не менять прокси если уже был выдан персональный
			//if($proxyObj->is_personal)
			//	return true;

			if($this->getClient()->personal_proxy)
			{
				//на персональных рейтинг нас интересует во вторую очередь
				/*if($proxyObj->account_id == $this->id)
				{
					return true;
				}
				else
					return false;
				*/
			}

			if($proxyObj->rating !== null and $proxyObj->rating < Proxy::RATING_MIN)
				return false;

			foreach(AccountProxy::model()->findAll("`proxy_id`='{$proxyObj->id}'") as $accountProxy)
			{
				if($accountProxy->client_id == $this->client_id and $accountProxy->group_id == $this->group_id)
					return true;
			}

			return false;

		}
		else
			return false;
	}

	public static function getNewProxy($wexAccountId)
	{
		$wexAccount = self::getModel(['id' => $wexAccountId]);

		prrd($wexAccount);

		if($wexAccount)
		{
			$condition = [];

			//'condition'=>"`date_check`<$dateCheck and (`error`='identify_anonim' or `error`='check_wait')",
//			$dateFullCheck = time() - 3600*2;
//			$dateCheckStart = time() - 3600*10;
			//'condition'=>"`date_full_check`<$dateFullCheck and `date_check`>$dateCheckStart and `type` in('transit', 'out') and `error`='' and `limit`>0 and `is_old`=0",

			//(`date_check`<$dateCheck and (`error`='identify_anonim' or `error`='check_wait'))
			//or
			//(`date_full_check`<$dateFullCheck and `date_check`>$dateCheckStart and `type` in('out') and `error`='' and `limit`>0 and `is_old`=0)

			//$condition = "`client_id`=$clientId AND `group_id`=$groupId AND `date_check`<$dateCheck AND `enabled`=1";


			$freeWexProxies = Proxy::getProxies($condition);
		}
	}
}