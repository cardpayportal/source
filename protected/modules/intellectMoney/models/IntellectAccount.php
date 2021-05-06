<?php
/**
 * 	Текущий оборот на кошельке
	Перевод пользователям IntellectMoney
	Суточный оборот: 0,00 из 0,01
	Месячный оборот: 0,00 из 0,01

	Оплата в пользу интернет-магазинов
	Суточный оборот: 0,00 из 15 000,00
	Месячный оборот: 650,11 из 40 000,00

	Вывод средств
	Суточный оборот: 0,00 из 0,01
	Месячный оборот: 0,00 из 0,01

	Оборот по кошельку
	Максимальный остаток: 233,89 из 15 000,00
	Месячный лимит принятых средств: 0,00 из 40 000,00
 *
 * @property int id
 * @property int internal_account_id номер счета в системе интеллект мани
 * @property int client_id
 * @property int user_id
 * @property int form_id
 * @property int date_add
 * @property int date_check
 * @property string error
 * @property int date_error
 * @property string status
 * @property float balance
 * @property int error_count
 *
 * @property float LimitIn
 * @property string limitInMonthStr
 * @property string limitInDayStr
 * @property float limitIn
 * @property IntellectTransaction[] transactionsManager
 *
 * @property Client client
 * @property User user
 * @property string dateCheckStr
 * @property IntellectMoneyBot bot
 * @property string email
 * @property string pass
 * @property int pin_code
 * @property string proxy
 * @property string money_form_code код формы для приема переводов
 */
class IntellectAccount extends Model
{
	const SCENARIO_ADD = 'add';

	const STATUS_ACTIVE = 'active';		//активен
	const STATUS_WAIT = 'wait';			//не активен
	const STATUS_BAN = 'ban';			//забанен
	const STATUS_HIDDEN = 'hidden';		//забанен

	const ERROR_BAN = 'ban';
	const ERROR_WAIT = 'wait';

	private $bot;
	private $cfg;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return [];
	}

	public function tableName()
	{
		return '{{intellect_account}}';
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

	public function rules()
	{
		return [
			['email', 'unique', 'className'=>__CLASS__, 'attributeName'=>'email', 'allowEmpty'=>false],
			['client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id', 'allowEmpty'=>false],
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>true],
			//['card_number', 'match', 'pattern'=>'!^\d{16}$!', 'message'=>'неверный номер карты'],
			['id, internal_account_id, user_id, date_add, date_check, error, balance', 'safe'],
			['pass, pin_code, proxy, form_id, error_count, money_form_code ', 'safe'],
		];
	}

	/*
	 * выдача прокси либо по клиенту-группе, либо персональный пркоси либо по категории(при замене)
	 * выдает прокси учитывая
	 */
	public static function getGoodProxy($clientId = false)
	{
		if(!$client = Client::modelByPk($clientId))
		{
			self::$lastError = 'клиент кошелька не найден';
			return false;
		}

		//если у клиента флаг персональные то выдаем
		if($client->personal_proxy)
		{
			$proxyModels = Proxy::model()->findAll([
				'condition'=>"`is_personal`=1 AND `account_id`=0 AND `enabled`=1",
			]);

			/**
			 * @var Proxy[] $proxyModels
			 */

			shuffle($proxyModels);

			foreach($proxyModels as $proxy)
			{
				if($proxy->isGoodRating)
					return $proxy;
			}

			self::$lastError = 'все персональные прокси уже заняты';
			return false;

		}
		else
		{
			$condition = "`client_id`=$clientId and `group_id`=$groupId";

			/**
			 * @var self[] $accountProxyModels
			 */
			$accountProxyModels = self::model()->findAll(array(
				'condition'=>$condition,
			));

			shuffle($accountProxyModels);


			foreach($accountProxyModels as $accountProxyModel)
			{
				if($accountProxyModel->proxy->rating >= Proxy::RATING_MIN)
					return $accountProxyModel->proxy;
			}

			self::$lastError = 'низкий рейтинг или нет подходящих прокси';
			return false;
		}


	}

	/**
	 * статистика по платежам для кошелька
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return array ['amountIn'=>0, 'amountOut'=>0]
	 */
	public function getTransactionStats($timestampStart = 0, $timestampEnd = 0)
	{
		$result = [
			'amountIn'=>0,
			'amountOut'=>0,
		];

		$timestampStart *= 1;
		$timestampEnd *= 1;

		$transactions = IntellectTransaction::model()->findAll([
			'condition' => "
				`intellect_account_id` = '{$this->id}' AND `status` = '".IntellectTransaction::STATUS_SUCCESS."'
				AND `date_add` >= $timestampStart and `date_add` < $timestampEnd
				AND `client_id`='{$this->client_id}' AND `user_id`='{$this->user_id}'
			",
		]);

		/**
		 * @var IntellectTransaction[] $transactions
		 */

		foreach($transactions as $trans)
		{
			if($trans->direction == IntellectTransaction::DIRECTION_IN)
				$result['amountIn'] += $trans->amount;
			elseif($trans->direction == IntellectTransaction::DIRECTION_OUT)
				$result['amountOut'] += $trans->amount;
		}

		return $result;
	}

	/**
	 * кошельки юзера
	 * @param int $userId
	 * @return self[]
	 */
	public static function getUserModels($userId)
	{
		return self::model()->findAll([
			'condition' => "`user_id`='$userId'",
			'order' => "`date_pick` DESC",
		]);
	}

	/**
	 * остаток лимита на кошельке
	 * если месячный лимит больше максимального дневного то чекать оставшийся дневной
	 * иначе отображать оставшийся месячный
	 */
	public function getLimitIn()
	{
		$cfg = cfg('intellectMoney');

		$statsDay = $this->getTransactionStats(Tools::startOfDay(), time());
		$limitInDay = $cfg['limitInDay'] - $statsDay['amountIn'] - $this->balance;

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $cfg['limitInMonth'] - $statsMonth['amountIn'] - $this->balance;

		return floor(min($limitInDay, $limitInMonth));
	}


	/**
	 * остаток лимита на кошельке дневной
	 * пользователи переливают часто, нужно было разделить
	 */
	public function getLimitInDayStr()
	{
		$cfg = cfg('intellectMoney');

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $cfg['limitInMonth'] - $statsMonth['amountIn'] - $this->balance;
		$limitOutMonth = $cfg['limitInMonth'] - $statsMonth['amountOut'] - $this->balance;

		$statsDay = $this->getTransactionStats(Tools::startOfDay(), time());
		$limitInDay = $cfg['limitInDay'] - $statsDay['amountOut'] - $this->balance;

		$limit = min($limitInMonth, $limitOutMonth, $limitInDay);

		if($limit < 2000)
			return '<span class="error">'.formatAmount($limit, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limit, 0).'</span>';
	}

	/**
	 * остаток лимита на кошельке месячный
	 * пользователи переливают часто, нужно было разделить
	 */
	public function getLimitInMonthStr()
	{
		$cfg = cfg('intellectMoney');

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $cfg['limitInMonth'] - $statsMonth['amountIn'] - $this->balance;
		$limitOutMonth = $cfg['limitInMonth'] - $statsMonth['amountOut'] - $this->balance;

		$limitMonth = min($limitInMonth, $limitOutMonth);
		$limitMonth = $limitInMonth;

		if($limitMonth < 2000)
			return '<span class="error">'.formatAmount($limitMonth, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limitMonth, 0).'</span>';
	}


	/**
	 * @return YandexTransaction[]
	 */
//	public function getTransactionsManager()
//	{
//		$transactions = YandexTransaction::model()->findAll([
//			'condition' => "`account_id`='{$this->id}' AND `date_add`>{$this->date_pick} AND `direction`='".YandexTransaction::DIRECTION_IN."' and `status`='".YandexTransaction::STATUS_SUCCESS."' AND `client_id`='{$this->client_id}'  AND `user_id`='{$this->user_id}'",
//			'order' => "`date_add` DESC",
//		]);
//
//		return $transactions;
//	}

	/**
	 * все аккаунты по дате добавления
	 * @return self[]
	 */
	public static function getModels()
	{
		return self::model()->findAll([
			'condition' => "",
			'order' => "`date_add` DESC",
		]);
	}

	/**
	 * @return Client
	 */
	public function getClient()
	{
		return Client::model()->findByPk($this->client_id);
	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		return User::model()->findByPk($this->user_id);
	}

	public function getDateCheckStr()
	{
		if($this->date_check)
			return date('d.m.Y H:i', $this->date_check);
		else
			return '';
	}

	/**
	 * @param string $accounts - textarea(каждый с новой строки)
	 * @param int $clientId
	 * @return int кол-во добавленных
	 */
	public static function addMany($accounts, $clientId, $userId)
	{
		$result = 0;

		if(!Client::getModel($clientId))
		{
			self::$lastError = 'указанный клиент не найден';
			return $result;
		}

		if(preg_match_all('!(\d{5,20})(\|(.+?@\w+?\.[\w\s]+|))(\|(.+?)|)(\|([\d\s]+)|)(\|(\d{4}))!', $accounts, $res))
		{
			$accountArr = [];

			foreach($res[1] as $key=>$parsedLine)
			{
				$email = $res[3][$key];
				if(self::getModel(['email'=>$email]))
					continue;

				$accountArr[] = [
					'internalId'=>$res[1][$key],
					'email'=>$email,
					'password'=>$res[5][$key],
					'formId'=>$res[7][$key],
					'pinCode'=>$res[9][$key],
				];

				/**
				 * @var IntellectAccount $model
				 */
				$model = new self;
				$model->scenario = IntellectAccount::SCENARIO_ADD;
				$model->client_id = $clientId*1;
				$model->internal_account_id = $res[1][$key]*1;
				$model->email = $email;
				$model->pass = $res[5][$key];
				$model->form_id = $res[7][$key];
				$model->pin_code = $res[9][$key];

				if($userId)
					$model->user_id = $userId*1;

				if($model->save())
					$result++;
				else
					return $result;
			}
		}
		else
			self::$lastError = 'аккаунтов не найдено';

		return $result;
	}

//	/**
//	 * @return YandexApi|bool
//	 */
//	private function getApi()
//	{
//		if(!$this->access_token)
//		{
//			self::$lastError = '';
//			return false;
//		}
//
//		if(!$this->api)
//		{
//			$cfg = cfg('yandexAccount');
//
//			$this->api = new YandexApi;
//			$this->api->accessToken = $this->access_token;
//			$this->api->proxy = $cfg['proxy'];
//			$this->api->proxyType = $cfg['proxyType'];
//		}
//
//		return $this->api;
//	}


	/**
	 * @param array $params
	 * @return self
	 */
	public static function modelByAttribute(array $params)
	{
		return self::model()->findByAttributes($params);
	}

//	public function getCardNumberStr()
//	{
//		if(!$this->card_number)
//			return  '';
//
//		return substr($this->card_number, 0, 4)
//			.' '.substr($this->card_number, 4, 4)
//			.' '.substr($this->card_number, 8, 4)
//			.' '.substr($this->card_number, 12, 4);
//	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel($params)
	{
		return self::model()->findByAttributes($params);
	}

	public function withdrawMoney()
	{
		$config = cfg('intellectMoney');
		$params['eshopId'] = $config['eshopId'];
		$params['eshopInn'] = $config['eshopInn'];
		$params['amount'] = $config['withdrawAmount']; // сумма с точностью 2 знака с разделителем "."

		//TODO: заменить
		$params['browser'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0';

		$params['senderEmail'] = $this->email;//'GeorgeFrank12@tutanota.com'; //email отправителя, должен быть зарегистрирован и иметь баланс на счету не меньше суммы перевода
		$params['senderPass'] = $this->pass; //pass отправителя
		$params['senderCode'] = $this->pin_code; //pinCode отправителя
		$params['proxy'] = $this->proxy;//'gKlPrqwf4l:Sunny11111@194.116.163.239:41536';
		$params['proxyType'] = 'http';

		$result = IntellectMoneyBot::internalTransfer($params);

		$transaction = new IntellectTransaction;
		$transaction->intellect_account_id = $this->id;
		$transaction->amount = $params['amount'];
		$transaction->status = $result['status'];
		$transaction->direction = IntellectTransaction::DIRECTION_OUT;
		$transaction->scenario = IntellectTransaction::SCENARIO_ADD;
		$transaction->user_id = $this->user_id;
		$transaction->client_id = $this->client_id;
		if($result['status'] == IntellectTransaction::STATUS_SUCCESS)
			$transaction->date_pay = time();
		$transaction->proxy = $this->proxy;
		$transaction->save();

		$this->balance = $result['senderBalance'];
		$this->save();

		exit('finish');
	}

}