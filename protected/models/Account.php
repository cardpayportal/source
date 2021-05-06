<?php

/**
 * check_priority:
 *        -1 - кошелек в завершенной заявке
 *        0 - пустой, не использованный кошелек (по умолчанию)
 *        1 - кошелек в активной заявке
 *        2 - кошелек, помеченный для немедленной проверки
 *
 * @property int id
 * @property string login
 * @property string card
 * @property string cardNumberStr
 * @property string type
 * @property string error
 * @property int date_check
 * @property int date_out_of_limit
 * @property int date_used
 * @property int date_pick
 * @property string dateCheckStr
 * @property int check_priority
 * @property Client client
 * @property string status
 *
 * @property bool is_email прикреплен ли к кошельку email(смотрим в настройки безопасности)
 * @property int email_id id в таблице  account_email
 * @property int email_link_date дата прикрепления
 * @property AccountEmail email
 * @property User user
 * @property string isEmailStr
 * @property string comment
 * @property float managerLimit	лимит входящего кошелька для менеджера
 * @property float reserveAmount	сколько средств зарезервировано под приход
 * @property float limit_in	входящий лимит
 * @property float limit_out исходящий лимит
 * @property float inAmount
 * @property float balance
 * @property string balanceStr
 * @property int client_id
 * @property int group_id
 * @property int limitMax
 * @property string pass
 * @property string label
 * @property int user_id
 * @property bool is_ecomm
 * @property float amount_in
 * @property float amount_out
 * @property bool isOldCheck	давно не проверялся(скрытие части номера)
 * @property Transaction lastTransaction
 * @property int date_priority_now 	когда была нажата кнопка Проверить сейчас (влияет на понижение приоритета проверки)
 * @property int date_add
 * @property int enabled отключает проверку и выдачу кошельков
 * @property QiwiBot bot
 * @property QiwiBotTest botTest
 * @property float balance_kzt
 * @property float balanceKztStr
 * @property int dayLimit
 * @property string dayLimitStr
 * @property string dateAddStr
 * @property string statusStr
 * @property string limitInStr
 * @property bool hidden скрытие из виду кошелоков манов
 * @property bool isRat
 * @property string browser
 * @property string typeStr
 * @property string userStr
 * @property string datePickStr
 * @property string dateUsedStr
 * @property string currentProxy
 * @property int date_last_request
 * @property string dateLastRequestStr
 * @property int checkInterval
 * @property string proxy
 * @property bool is_old
 * @property string orderMsg
 * @property string hiddenLogin 	скрытый
 * @property string hiddenLoginStr 	скрытый с ошибками
 * @property bool isInOrder 		находится ли в активной заявке ManagerOrder
 * @property string amountStr
 * @property string managerLimitStr
 * @property string labelStr
 * @property Transaction[] transactionsManager
 * @property int commission 0|1|2
 * @property bool isCritical 		критический ли кошелек
 * @property float maxBalance
 * @property string api_token
 * @property QiwiApi api
 * @property string passStr
 * @property QiwiBot botAntiCaptcha
 * @property bool is_kzt 			на кошельке были зафиксированы приходы в KZT(изменяет режим отображения истории и балансов)
 * @property int wallets_count		количество уникальных кошельков в истории за сегодня(для комсы)
 * @property bool commission_extra
 * @property string botContent
 * @property int commission_estmated	ожидаемая комса(если все условия для нее соблюдены но еще не врубилась)
 * @property bool commissionEstmated	есть или нет ожидаемая комса по уникам
 * @property Proxy proxyObj
 * @property int mobile_id
 * @property AccountMobile mobile
 * @property int date_reg
 *
 *
 *
 */
class Account extends Model
{
	const SCENARIO_ADD = 'add';

	const TYPE_IN = 'in';
	const TYPE_TRANSIT = 'transit';
	const TYPE_OUT = 'out';

	const PRIORITY_SMALL = -1;
	const PRIORITY_STD = 0;
	const PRIORITY_BIG = 1;
	const PRIORITY_NOW = 2;
	const PRIORITY_STORE = 3;

	const ERROR_IDENTIFY = 'identify_anonim';
	const ERROR_SMS = 'sms_enabled';
	const ERROR_PASSWORD_EXPIRED = 'password_expired';
	const ERROR_NOT_NULL_BALANCE = 'not_null_balance'; //ненулевой баланс на новом кошельке(не юзать такие)
	const ERROR_OUT_OF_LIMIT = 'out_of_limit';
	const ERROR_LIMIT_OUT = 'limit_out'; //ошибка платежа превышен лимит исходящих транзакций
	const ERROR_BAN = 'ban';
	const ERROR_RAT = 'rat_trans'; //украли кошелек
	const ERROR_CHECK = 'check_wait';
	const ERROR_EXIST = 'login already exist';
	const ERROR_OLD = 'old'; //устарел(после is_old=1 слили баланс и пометили ошибкой)

	const STATUS_NEW = 'new'; //незарегистрированный
	const STATUS_ANONIM = 'anonim';
	const STATUS_HALF = 'half';
	const STATUS_FULL = 'full';

	const BALANCE_MAX = 50000;
	const BALANCE_MIN = 2;

	const DAY_LIMIT_MAX  = 100000;	//в день можно прогнать через кош

	public $inAmount = 0; //буфер для использования getInAmount и getInLimit в одном месте

	public $errorCode = '';
	public $lastContent;

	public $botObj = false;
	public $botError = false;
	public $botErrorCode = false;
	public $botContent = '';

	public $botWithoutAuth = false; // если установлено в true перед вызовом getBot() - то не будет авторизовываться

	public $cacheTransactionsManager = array();

	private $_isCritical = false;

	public $transactionsCache;


	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'login' => 'Логин',
			'pass' => 'Пароль',
		);
	}

	public function tableName()
	{
		return '{{account}}';
	}

	public function beforeValidate()
	{
		$this->balance = str_replace(',', '.', $this->balance);
		$this->balance_kzt = str_replace(',', '.', $this->balance_kzt);
		$this->limit_in = str_replace(',', '.', $this->limit_in);
		$this->limit_out = str_replace(',', '.', $this->limit_out);

		if($this->user_id == 0)
			unset($this->user_id);

		if($this->label)
			$this->label = strip_tags($this->label);

		if($this->error)
			$this->error = strip_tags($this->error);

		if($this->group_id == 0)
			unset($this->group_id);


		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->pass = trim($this->pass);

			$this->limit_in = config('account_'.$this->type.'_limit');

			$fullStr = ($this->status == self::STATUS_FULL) ? '_full' : '';
			$this->limit_out = config('account_'.$this->type.'_limit_out'.$fullStr);
		}


		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('type', 'in', 'range' => array_keys(self::typeArr()), 'allowEmpty'=>false),
			array('client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id', 'allowEmpty'=>false),
			array('login', 'match', 'pattern'=>'!^\+\d{11,12}$!', 'on'=>self::SCENARIO_ADD),
			array('login', 'unique', 'className'=>__CLASS__, 'attributeName'=>'login', 'allowEmpty'=>false, 'message'=>self::ERROR_EXIST, 'on'=>self::SCENARIO_ADD),
			array('login', 'existValidator', 'on'=>self::SCENARIO_ADD),
			array('pass', 'length', 'max'=>50, 'allowEmpty'=>true),
			//не проходили прокси с доменом
			//array('proxy', 'match', 'pattern'=>'!\d+\.\d+\.\d+\.\d+\:\d+!', 'allowEmpty'=>true),
			['proxy', 'safe'],
			array('commission,error', 'safe'),
			array('date_add, date_reg, reg_ip, group_id, browser,date_last_request,hidden', 'safe'),
			array('comment', 'length', 'max'=>255, 'allowEmpty'=>true),

			//ecomm
			array('error', 'length', 'max'=>255, 'allowEmpty'=>true),
			array('user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>true),
			array('date_pick', 'numerical', 'allowEmpty'=>true),
			array('group_id', 'in', 'range' => array_keys(self::getGroupArr()), 'allowEmpty'=>true),
			array('is_ecomm', 'in', 'range' => array(0, 1), 'allowEmpty'=>true),
			array('enabled', 'in', 'range' => array(0, 1), 'allowEmpty'=>true),
			array('api_token', 'length', 'max'=>50, 'allowEmpty'=>true, 'on'=>self::SCENARIO_ADD),

			//фикс продления проверки. дата нажатия кнопки Проверить сейчас (если сутки не прошли то приоритет проверки не понижать ниже PRIORITY_BIG)
			array('date_priority_now', 'numerical', 'allowEmpty'=>true),

			['is_kzt', 'in', 'range' => [0, 1], 'allowEmpty'=>true],
			['wallets_count', 'numerical', 'allowEmpty'=>true],
			['commission_extra', 'safe'],
			['mobile_id, card', 'safe'],

		);
	}

	/*
	 * проверка на существование в других панелях
	 */
	public function existValidator()
	{
		$existResult = self::accountExist($this->login);

		if($existResult === false)
			return true;
		elseif($existResult === true)
		{
			$this->addError('login', 'Ошибка проверки на уникальность: '.self::$lastError);
			self::$lastErrorCode = 'exist';
		}
		else
			$this->addError('login', 'Ошибка проверки на уникальность: '.self::$lastError);

		return false;
	}

	public function beforeSave()
	{
		if ($this->scenario == self::SCENARIO_ADD)
		{
			$this->pass = trim($this->pass);
			$this->date_add = time();
		}

		if($this->label)
			$this->label = strip_tags($this->label);

		if($this->error)
			$this->error = strip_tags($this->error);


		return parent::beforeSave();
	}

	public function afterSave()
	{
		if($this->_isCritical)
		{
			$criticalModel = new AccountCritical;
			$criticalModel->scenario = AccountCritical::SCENARIO_ADD;
			$criticalModel->account_id = $this->id;
			$criticalModel->client_id = $this->client_id;

			if(!$criticalModel->save())
			{
				Account::model()->deleteByPk($this->id);
				self::$lastError = 'ошибка добавления критического свойства кошельку '
					.$this->login.': '.self::$lastError
					.'. Кошелек удален';

				return false;
			}
		}

		return parent::afterSave();
	}

	public static function typeArr()
	{
		return array(
			self::TYPE_IN => 'входящий',
			self::TYPE_TRANSIT => 'транзитный',
			self::TYPE_OUT => 'исходящий',
		);
	}

	public static function paymentTypeArr()
	{
		return array(
			Client::YANDEX_PAYMENT_TYPE_CARD => 'карта',
			Client::YANDEX_PAYMENT_TYPE_EXCHANGE => 'обменник',
			Client::YANDEX_PAYMENT_TYPE_MULTIPLE_EXCHANGE => 'несколько обменников',
			Client::YANDEX_PAYMENT_TYPE_YM => 'яндекс деньги',
			Client::YANDEX_PAYMENT_TYPE_CARD_UNIVER => 'карта(UNI)',
			Client::YANDEX_PAYMENT_TYPE_MEGAKASSA_FAKER => 'MegaKassa(Фейкер)',
			Client::YANDEX_PAYMENT_TYPE_MEGAKASSA_YANDEX => 'MegaKassa(Yandex)',
			Client::YANDEX_PAYMENT_TYPE_BITEXCOIN_YAD => 'Обменник bitexcoin(Yandex)',
			Client::YANDEX_PAYMENT_TYPE_SIM_ACCOUNT => 'Sim накладка',
		);
	}

	public function getCardNumberStr()
	{
		if(!$this->card)
			return  '';

		return substr($this->card, 0, 4)
			.' '.substr($this->card, 4, 4)
			.' '.substr($this->card, 8, 4)
			.' '.substr($this->card, 12, 4);
	}

	public function getTypeStr()
	{
		if ($this->type == self::TYPE_IN)
			return '<span class="accountIn">входящий</span>';
		elseif ($this->type == self::TYPE_TRANSIT)
			return '<span class="accountTransit">транзитный</span>';
		elseif ($this->type == self::TYPE_OUT)
			return '<span class="accountOut">исходящий</span>';
	}

	public static function statusArr()
	{
		return array(
			self::STATUS_NEW => 'Новый',
			self::STATUS_ANONIM => 'Зарегистрирован',
			self::STATUS_HALF => 'Частичная идентификация',
			self::STATUS_FULL => 'Полная идентификация',
		);
	}

	/*
	 * статус для регистрации
	 */
	public function getStatusStr()
	{
		if($this->status == self::STATUS_FULL)
			return '<span class="green">идентифицирован</span>';
		elseif($this->status == self::STATUS_HALF)
		{
			if($this->error == self::ERROR_SMS)
				return 'Отключить смс';
			else
				return '<span class="orange">частичн. идент</span>';
		}
		elseif($this->status == self::STATUS_ANONIM)
		{
			if($this->error == self::ERROR_SMS)
				return 'Отключить смс';
			else
				return 'Зарегистрирован';
		}
		elseif($this->status == 'new')
			return 'регистрировать';
		else
			return '';
	}

	//todo: сделать динамический подсчет сумммы транзакций
	public function getAmountStr()
	{
		return formatAmount($this->getInAmount(), 0);
	}

	public function getDateCheckStr()
	{
		return ($this->date_check) ? date('d.m.Y H:i', $this->date_check) : '';
	}

	public function getBalanceStr()
	{
		if($this->is_kzt)
			$currencyStr = ' руб';
		else
			$currencyStr = '';

		return formatAmount($this->balance, 2)." $currencyStr";
	}

	public function getBalanceKztStr()
	{
		return formatAmount($this->balance_kzt, 0).' тенге';
	}

	public function getLimitInStr()
	{
		$value = formatAmount($this->limit_in - cfg('account_in_safe_limit'), 2) ;

		if ($this->limit_in < config('account_mark_used_after'))
			return '<span style="color:red" title="Лимит приближается к нулю, скоро этот кошелек будет удален">' . $value . '</span>';
		else
			return '<span style="color:green">' . $value . '</span>';
	}

	public function getLimitOutStr()
	{
		$value = formatAmount($this->limit_out, 2);


		if ($this->limit_out < config('account_mark_used_after'))
			return '<span style="color:red" title="Лимит приближается к нулю, скоро этот кошелек будет удален">' . $value . '</span>';
		else
			return '<span style="color:green">' . $value . '</span>';
	}

	public function getManagerLimit()
	{
		$result = $this->limit_in - cfg('account_in_safe_limit');

		return $result;
	}

	/**
	 * отображать манагеру лимит поменьше чем на самом деле
	 */
	public function getManagerLimitStr()
	{
		$limit = $this->getManagerLimit();

		$value = formatAmount($limit, 0);

		if ($limit < config('account_mark_used_after'))
			return '<font color="red" title="Лимит приближается к нулю, скоро этот кошелек будет удален">' . $value . '</font>';
		else
			return '<font color="green">' . $value . '</font>';
	}

	public function getCheckInterval()
	{
		$this->check_priority = $this->check_priority * 1;

		//фикс чтобы зеленые проверялись чаще
		if($this->status == self::STATUS_FULL and $this->check_priority < 1)
			return config('priority_interval_small') * 1;

		if ($this->check_priority === -1)
			return config('priority_interval_big') * 1;
		elseif ($this->check_priority === 0)
			return config('priority_interval_std') * 1;
		elseif ($this->check_priority === 1)
		{
			if($this->status == self::STATUS_FULL)
				return cfg('priority_interval_small_full') * 1;
			else
				return config('priority_interval_small') * 1;
		}
		elseif ($this->check_priority === 2)
			return config('priority_interval_now') * 1;
		elseif ($this->check_priority === 3)
			return config('priority_interval_store') * 1;
	}

	/**
	 * выдает сообщение для менеджера о состоянии кошелька
	 */
	public function getOrderMsg()
	{
		//если не првоерялся больше 30 минут то пишем не заливать
		$dateCheckMin = time() - 3600;

		if ($this->date_used)
			return '<font color="red">кошелек уже был использован ' . $this->getDateUsedStr() . ', переводить на него нельзя</font>';
		elseif ($this->error) {
			if ($this->error == 'ban' or $this->error == 'sms_enabled' or $this->error == self::ERROR_RAT)
				return '<font color="red">ОСТАНОВИТЕ ПЕРЕВОДЫ <br> НА ЭТОТ КОШЕЛЕК!!! <br> кошелек заблокирован</font>';
			else
				return '<font color="red">ОСТАНОВИТЕ ПЕРЕВОДЫ <br> НА ЭТОТ КОШЕЛЕК!!! <br> до разрешения проблемы</font>';
		}
		//elseif($this->date_check < time() - 30*60)
		//    return '<font color="red">ОСТАНОВИТЕ ПЕРЕВОДЫ <br> НА ЭТОТ КОШЕЛЕК!!! <br> до разрешения проблемы</font>';
		elseif ($this->limit_in < cfg('min_balance'))
			return '<font color="red">Исчерпан лимит переводов <br> на данный кошелек</font>';
		elseif ($this->balance >= config('in_max_balance'))
			return '<font color="red">Не превышайте максимальный <br> баланс на кошельке. <br>Дождитесь пока баланс <br> текущего кошелька <br> уменьшится до нуля.</font>';
		elseif ($this->date_out_of_limit)
			return '<font color="red">Кошелек будет удален через ' . $this->getOutOfLimitStr() . '</font>';
		elseif ($this->limit_in < config('in_max_balance'))
			return '<font color="red">Лимит переводов <br> на этот кошелек <br> приближается к нулю</font>';
		elseif($this->date_check < $dateCheckMin and $this->check_priority > 0 and $this->date_pick < $dateCheckMin)	//date_pick: чтобы при взятии кошелька, не писало проблемы
			return '<font color="red"><span class="dotted" title="Проблемы с проверкой кошельков. Не переводите средства.Дождитесь обновления баланса.">Проблемы...</span></font>';
		else
			return '<font color="green">можно переводить</font>';
	}

	/**
	 * сколько времени осталось до удаления кошелька в отстойник
	 *
	 */
	public function getOutOfLimitStr()
	{
		if ($this->date_out_of_limit and !$this->date_used) {
			$last = time() - $this->date_out_of_limit;
			$interval = config('account_mark_used_interval');

			$val = ($interval - $last) / 3600;

			if($val < 0) $val = 0;

			return formatAmount($val, 0) . ' часов';
		}

	}

	public function getDateUsedStr()
	{
		if ($this->date_used)
			return date('d.m.Y', $this->date_used);
	}

	/**
	 * возвращает список транзакций у IN и OUT
	 * если IN то только входящие текущего юзера (от  даты взятия)
	 * если OUT то все
	 */
	public function getTransactions()
	{
		$models = [];

		if ($this->type == self::TYPE_IN)
		{
			if ($user = $this->getUser())
			{
				//для менеджера
				$models = Transaction::model()->findAll(array(
					'condition' => "
							`type`='" . Transaction::TYPE_IN . "'
							AND `account_id`='{$this->id}'
							AND `user_id`='{$user->id}'
							AND `date_add`>={$this->date_pick}
						",
					'order' => "`date_add` DESC",
				));
			}
		}
		elseif ($this->type == self::TYPE_OUT)
		{
			//для финансиста
			$models = Transaction::model()->findAll(array(
				'condition' => "
					`account_id`='{$this->id}'
				",
				'order' => "`date_add` DESC",
			));
		}

		return $models;
	}

	/**
	 * @return Transaction[]
	 */
	public function getTransactionsManager()
	{

		if($this->cacheTransactionsManager)
			return $this->cacheTransactionsManager;

		$models = array();

		if ($this->type == self::TYPE_IN)
		{
			if ($user = $this->getUser()) {
				$date = $this->date_pick;

				//для менеджера
				$models = Transaction::model()->findAll(array(
					'condition' => "
							(`type`='" . Transaction::TYPE_IN . "' or (`type`='". Transaction::TYPE_OUT ."' AND `is_rat`=1))
							AND `account_id`='{$this->id}'
							AND `user_id`='{$user->id}'
							AND `date_add`>='$date'
						",
					'order' => "`date_add` DESC",
				));
			}
		}
		elseif ($this->type == self::TYPE_OUT)
		{
			//для финансиста
			$models = Transaction::model()->findAll(array(
				'condition' => "
						`account_id`='{$this->id}'
					",
				'order' => "`date_add` DESC",
			));
		}

		return $models;
	}

	public function getLabelStr()
	{
		return shortText($this->label, 60);
	}

	public function getUsedStr()
	{
		if ($this->date_used)
			return '<font color="brown">да</font>';
		else
			return '<font color="green">нет</font>';
	}

	public function getTransactionCount()
	{
		return Transaction::model()->count("`account_id`={$this->id}");
	}

	public function getUser()
	{
		if ($this->user_id)
			return User::model()->findByPk($this->user_id);
	}

	public function getUserStr()
	{
		if ($user = $this->getUser())
			return $user->name;
	}

	/**
	 * последняя транзакция кошелька(входящая или исходящая)
	 * $type = 'in' or 'out'
	 * @param string|null $type
	 * @param int|null $timestampFrom
	 * @return Transaction
	 */
	public function getLastTransaction($type = null, $timestampFrom = null)
	{
		$typeCondition = '';

		if($type=='in')
			$typeCondition = " and `type`='in'";
		elseif($type=='out')
			$typeCondition = " and `type`='out'";

		$dateCondition = '';

		if($timestampFrom)
			$dateCondition = " and `date_add`>=$timestampFrom";

		return Transaction::model()->find(array(
			'condition' => "`account_id`='{$this->id}'".$typeCondition.$dateCondition,
			'order' => "`date_add` DESC",
		));
	}

	/*
	 * дата последнего прихода на кошель
	 */
	public function getDateLastTransactionInStr()
	{
		if($trans = $this->getLastTransaction(Transaction::TYPE_IN))
			return date('d.m.Y H:i', $trans->date_add);
		else
			return '';
	}

	/**
	 * amount по сумме входящих транзакций  с даты пика
	 * использовать только для отображения менеджерам!!!!
	 */
	public function getInAmount()
	{
		$result = 0;

		$transactions = Transaction::model()->findAll([
			'select'=>"`amount`",
			'condition'=>"
				`account_id`='{$this->id}'
				AND `type`='in'
				AND `status`='success'
				AND `date_add` > {$this->date_pick}
			",
		]);

		foreach ($transactions as $model)
			$result += $model->amount;

		return $result;
	}

	/**
	 * получить лимит по сумме входящих транзакций всего
	 * использовать только для отображения менеджерам!!!!
	 */
	public function getInLimit_old()
	{
		$result = 0;

		$transactions = Transaction::model()->findAll("
			`account_id`='{$this->id}'
			AND `type`='in'
			AND `status`='success'
		");

		foreach ($transactions as $model)
			$result += $model->amount;

		if ($this->type == self::TYPE_IN)
			return config('account_in_limit') - $result;
		else
			return false;
	}

	/**
	 * остаток лимита на входящие платежи
	 * @return float
	 */
	public function getInLimit()
	{
		$result = 0;

		$date = strtotime(date('01.m.Y'));
		//$date = $this->date_pick;

		$transactions = Transaction::model()->findAll("
			`account_id`='{$this->id}'
			AND `type`='in'
			AND `status`='success'
			AND `date_add` > $date
		");

		foreach ($transactions as $model)
			$result += $model->amount;


		$fullStr = ($this->status == self::STATUS_FULL) ? '_full' : '';

		return config('account_'.$this->type.'_limit'.$fullStr) - $result;
	}

	/**
	 * остаток лимита на исходящие платежи
	 * @return float
	 */
	public function getOutLimit()
	{
		$result = 0;

		$dateStart = strtotime(date('01.m.Y'));

		$transactions = Transaction::model()->findAll([
			'select'=>"`amount`",
			'condition'=>"
				`account_id`='{$this->id}'
				AND `type`='".Transaction::TYPE_OUT."'
				AND `status`='".Transaction::STATUS_SUCCESS."'
				AND `date_add` > $dateStart
			",
		]);

		foreach ($transactions as $model)
			$result += $model->amount + $model->commission;

		$fullStr = ($this->status == self::STATUS_FULL) ? '_full' : '';

		return config('account_'.$this->type.'_limit_out'.$fullStr) - $result;
	}



	public function getDatePickStr()
	{
		if ($this->date_pick)
			return date('d.m.Y', $this->date_pick);
	}

	/**
	 * @return Client
	 */
	public function getClient()
	{
		return Client::model()->findByPk($this->client_id);
	}

	public function setIsCritical($val)
	{
		$this->_isCritical = $val;
	}

	/**
	 * @param array $params
	 * @return int
	 * если $groupId не указан то находит группу с минимальным использованием
	 */
	public static function addMany($params)
	{

		$phoneStr = trim($params['phones']);
		$type = $params['type'];
		$clientId = $params['clientId'];
		$groupId = $params['groupId'];
		$isCritical = $params['isCritical'];
		$withOutCheck = $params['withOutCheck'];

		$addCount = 0;
		$regExp = cfg('regExpAccountAdd');
		$regExpJson = cfg('regExpAccountAddJson');

		if(!$client = Client::getModel($clientId) or !$client->is_active)
		{
			self::$lastError = 'невозможно добавить кошельки к клиенту id='.$clientId.' (клиент отключен)';
			return 0;
		}

		$res = [];

		if(preg_match_all($regExpJson, $phoneStr, $resJson) or preg_match_all($regExp, $phoneStr, $res))
		{

			if(isset($resJson) and count($resJson[0])>0)
				$res = $resJson;

			foreach($res[1] as $key => $phone)
			{
				$account = new self;
				$account->scenario = self::SCENARIO_ADD;

				$account->client_id = $clientId;
				$account->group_id = ($groupId) ? $groupId : self::getGroupIdforAdd($clientId, $type);
				$account->type = $type;
				$account->isCritical = $isCritical;
				if($withOutCheck)
				{
					$account->date_check = time();
					$account->status = self::STATUS_HALF;
				}
				else
				{
					$account->status = self::STATUS_ANONIM;
					$account->error = self::ERROR_IDENTIFY;
				}

				$account->date_reg = time();

				//если в json-формате
				if(isset($resJson) and count($resJson[0])>0)
				{
					$json = json_decode($phone, true);

					$account->login = $json['number'];
					$account->pass = ($json['password']) ? $json['password'] : 'somepasswd123';

					$account->api_token = ($json['apiAndroidAppToken']) ? $json['apiAndroidAppToken'] : $json['apiToken'];

					//добавить привязку к AccountMobile

					if($account->validate())
					{
						if($json['apiAndroidAppDeviceId'])
						{
							if(!$mobile = AccountMobile::modelByAttribute(['device_id'=>$json['apiAndroidAppDeviceId']]))
							{
								$mobile = new AccountMobile;
								$mobile->scenario = AccountMobile::SCENARIO_ADD;
								$mobile->device_id = $json['apiAndroidAppDeviceId'];
								$mobile->device_pin = $json['apiAndroidAppPin'];
								$mobile->token = $json['apiAndroidAppToken'];
								$mobile->access_token= $json['apiAndroidAppAccessToken'];

								if(!$mobile->save())
									self::$lastError = 'error save mobile for '.$json['number'];
							}

							$account->mobile_id = $mobile->id;
						}
					}
					else
						return $addCount;

				}
				//старый способ
				else
				{
					$account->login = $phone;

					if($res[2][$key])
					{
						$account->pass = trim($res[2][$key], '"');

						if($account->pass === 'null')
							$account->pass = 'somepasswd123';
					}
					else
					{
						self::$lastError = 'не указан пароль для кошелька '.$phone.__METHOD__;
						return $addCount;
					}

					if($apiToken = trim($res[3][$key]))
					{
						//api_token
						if(strlen($apiToken) ==32 )
							$account->api_token = $apiToken;

						//if($type !== self::TYPE_IN)
						//{
						//	self::$lastError = 'апи кошельки могут быть только Входящими';
						//	return $addCount;
						//}

//						if($account->api_token and $isCritical)
//						{
//							self::$lastError = 'апи кошельки не могут быть критическими';
//							return $addCount;
//						}
					}

					//персональный прокси
					$proxy = trim($res[4][$key]);

					if($proxy)
						$account->proxy = $res[4][$key];
				}


				if ($account->save())
				{
					if(isset($proxy) and $proxy)
					{
						Proxy::addMany($proxy, true, $account->id);

						if(Proxy::$lastError)
						{
							$account->delete();
							self::$lastError = $phone.': аккаунт удален, ошибка добавления прокси'.Proxy::$lastError;
						}
					}


					toLogRuntime('добавлено 1 '.$type.' акков clientId='.$clientId.', groupId='.$groupId.': '.$account->login);
					$addCount++;
				}
				else
				{
					self::$lastError = $phone.': '.$account::$lastError;
					break;
				}
			}
		}
		else
			self::$lastError = 'аккаунтов не найдено';

		return $addCount;
	}




	/**
	 * обновить баланс на кошельке прибавив или вычитая $amount
	 *
	 * $type = 'deposit';
	 * $type = 'withdraw';
	 *
	 * @param float $amount
	 * @param string|false $type
	 * @return bool
	 */
	public function updateBalance($amount, $type = false)
	{
		$model = self::model()->findByPk($this->id);

		$balance = $model->balance;

		if ($type == 'deposit')
			$balance = $balance + $amount;
		elseif ($type == 'withdraw')
			$balance = $balance - $amount;
		else
			$balance = $amount;

		$updateArr = array(
			'balance' => $balance,
		);


		self::model()->updateByPk($this->id, $updateArr);

		return true;
	}

	public function updateBalanceKzt($amount, $type = false)
	{
		$model = self::model()->findByPk($this->id);

		$balance = $model->balance_kzt;

		if ($type == 'deposit')
			$balance = $balance + $amount;
		elseif ($type == 'withdraw')
			$balance = $balance - $amount;
		else
			$balance = $amount;

		$updateArr = array(
			'balance_kzt' => $balance,
		);


		self::model()->updateByPk($this->id, $updateArr);

		return true;
	}


	/*
	 * $type = 'in' | 'out'
	 */
	public function updateLimit($value, $type)
	{
		$model = self::model()->findByPk($this->id);

		$var = 'limit_in';

		if($type == 'out')
			$var = 'limit_out';

		$limit = $model->$var - $value;

		self::model()->updateByPk($this->id, array($var => $limit));

		return true;
	}

	/**
	 * проверка рабочих Входящих и Транзитных аккаунтов без балансов
	 * @param int $clientId
	 * @param int $groupId
	 * @return int
	 */
	public static function startCheckBalance($clientId, $groupId)
	{
		session_write_close();

		$limitAtOnce = 6;

		$threadName = 'client'.$clientId.'_group'.$groupId.'_check';

		if(!Tools::threader($threadName))
			die('работа с группой уже ведется');

		$threadCond = " AND `client_id`=$clientId and `group_id`='$groupId'";

		$minBalance = cfg('min_balance');
		$minBalanceForTrans = cfg('minBalanceForTrans');

		//не проверять кошели если они заюзаны больше месяца назад
		$dateUsed = time() - cfg('old_account_interval');

		//выбрать Входящие и Транзитные кошели с нулевым балансом
		//не чекать транзитные у которых вышел лимит
		//не чекать входящие у которых дата использования больше месяца
		$models = self::model()->findAll(array(
			'condition' => "
				`balance` < $minBalanceForTrans
				AND `status` IN('".Account::STATUS_HALF."', '".Account::STATUS_FULL."')
				AND (`type`='" . Account::TYPE_IN . "' OR `type`='" . Account::TYPE_TRANSIT . "')
				AND `error` IN ('', '".self::ERROR_RAT."')
				AND `enabled` = 1
				AND ((`type`='".self::TYPE_TRANSIT."' and `limit_in`>$minBalance)  or (`type`='".self::TYPE_IN."' and (`date_used`=0 or `date_used`>$dateUsed or `check_priority`=".self::PRIORITY_NOW.")))
				$threadCond
			",
			'order' => "`check_priority` DESC, `date_check` ASC",
		));

		/**
		 * @var $models Account[]
		 */

		if(cfg('shuffleCheck'))
			shuffle($models);

		/*
		foreach($models as $model)
			echo $model->login.'<br>';
		die;
		*/

		$done = 0;

		if ($models)
		{
			foreach ($models as $model)
			{
				if (Tools::timeOut())
					break;

				if (time() - $model->date_check < $model->checkInterval)
					continue;

				$error = '';

				$updateArr = array();

				echo "checkBalance {$model->login}<br>\r\n";

				if($model->api_token)
				{
					if($model->processAccountByApi())
					{
						//пауза от долбежки
						sleep(rand(2,5));
						$done++;
					}
					else
						echo "\n error:".self::$lastError;

					continue;
				}

				$testKzt = false;

				//if(in_array($model->login, cfg('kztTestAccounts')))
				if(in_array($model->client_id, [16]) and $model->type == self::TYPE_IN)
				{
					$testKzt = true;
					$bot = $model->botTest;
				}
				else
					$bot = $model->bot;

				if($bot)
				{
					$bot->isCommission = $model->commission;

					$hasLockedSecurity = $bot->hasLockedSecurity();

					if($hasLockedSecurity === true)
					{
						$error = 'sms_enabled';
						self::model()->updateByPk($model->id, array('error'=>self::ERROR_SMS));
					}
					elseif ($hasLockedSecurity !== false)
					{
						toLogError('(' . $model->login . ') ошибка при проверке смс-подтверждений: '.$bot->error);
						continue;
					}

					$balance = false;

					if(!$error)
					{
						if($testKzt)
						{
							if(!$model->convertKzt($bot))
								continue;
								//continue;
						}

						$balance = $bot->getBalance();

						if ($balance !== false)
						{
							$model->updateBalance($balance);

							$updateArr['balance'] = $balance;

							$dayCount = $model->getTrUpdateDayCount();

							$transactions = $bot->getLastPayments($dayCount);

							if($transactions !== false)
							{
								$updateArr['date_check'] = time();

								if (!$model->updateTransactions($transactions))
									toLogError('error updateTransactions() on  account_id=' . $model->id . ': ' . $model::$lastError . ':' . Tools::arr2Str($transactions));

								$todayTimestamp = strtotime(date('d.m.Y', $model->date_check));

								if(count($transactions)==0 and $model->getLastTransaction(null, $todayTimestamp))
									toLog('возможно баг1 с getLastPayments на '.$model->login);
							}
							else
							{
								toLogError('error getLastPayments(): (' . $model->login . ') ' . $bot->error);
								continue;
							}
						}
						else
						{
							toLogError('error balance: (' . $model->login . ') ' . $bot->error);
							continue;
						}
					}

				}
				else
				{
					continue;
				}

				//вернуть приоритет после нажатия Проверить сейчас
				//вернуть приоритет после обнуления резерва(StoreApi)
				if(
					$model->check_priority == self::PRIORITY_NOW
					or
					(
						$model->check_priority == self::PRIORITY_STORE
						and
						$model->reserveAmount <= 0
					)
				)
				{
					$updateArr['check_priority'] = self::PRIORITY_BIG;
				}

				//если

				if(isset($updateArr['balance']))
				{
					$updateArr['balance'] = str_replace(',', '.', $updateArr['balance']);

					if($model->is_old and $updateArr['balance'] < $minBalance)
					{
						//закрываем старый кошель с нулевым балансом
						Account::model()->updateByPk($model->id, array('error'=>Account::ERROR_OLD));
					}

				}

				self::model()->updateByPk($model->id, $updateArr);

				$done++;

				if($done >= $limitAtOnce)
					break;
			}
		}
		else
			self::$lastError = 'нечего проверять';

		return $done;
	}

	/*
	 * обработка Входящих и Транзитных кошельков с ненулевым балансом
	 */
	public static function startTransBalance($clientId, $groupId)
	{
		session_write_close();

		$limitAtOnce = 3;

		$threadName = 'client'.$clientId.'_group'.$groupId.'_trans';

		if(!Tools::threader($threadName))
			die('работа с группой уже ведется');

		$minBalance = cfg('min_balance');
		$minBalanceForTrans = cfg('minBalanceForTrans');

		$threadCond = " AND `client_id`=$clientId and `group_id`='$groupId'";

		$models = self::model()->findAll(array(
			'condition' => "
				`balance`>=$minBalanceForTrans
				AND `status` IN('".Account::STATUS_HALF."', '".Account::STATUS_FULL."')
				AND (`type`='" . self::TYPE_IN . "' OR `type`='" . self::TYPE_TRANSIT . "')
				AND `error` IN ('', '".self::ERROR_RAT."')
				AND ((`is_old`=1 AND `balance`>$minBalance) or `is_old`=0)
				AND `enabled` = 1
				$threadCond
			",	//`date_used`=0 фикс против банов, чтобы запоздалые платежи не засоряли новые цепочки
			//'limit'=>20,
			//сначала с транзитных вывести все, потом с входящих
			//todo:если нет кошельков для слива то переключаемся в режим проверки
			'order' => "`type` DESC, `balance` DESC, `check_priority` DESC, `date_check` ASC",
		));



		/**
		 * @var Account[] $models
		 */

		if(cfg('shuffleTrans'))
			shuffle($models);

		$done = 0;

		if ($models)
		{
			foreach ($models as $model)
			{
				if (Tools::timeOut())
					break;

				//переводы с критических идут сразу на сливные
				if($model->isCritical)
					continue;

				echo "transBalance {$model->login}<br>\n";

				if($model->api_token)
				{
					if($model->processAccountByApi())
					{
						//пауза от долбежки
						sleep(rand(2,5));
						$done++;
					}
					else
						echo "\n error:".self::$lastError;

					continue;
				}

				//на случай если в кошельке есть ошибка а с него все равно надо слить баланс

				$balance = $model->balance;

				$updateArr = array();

				if($bot = $model->bot)
				{
					$bot->isCommission = $model->commission;

					$qBalance = $bot->getBalance();

					if ($qBalance !== false)
					{
						$balance = $qBalance;
						$updateArr['balance'] = $balance;

						$model->updateBalance($balance);

						$dayCount = $model->getTrUpdateDayCount();

						$transactions = $bot->getLastPayments($dayCount);

						if ($transactions !== false)
						{
							if($model->updateTransactions($transactions))
							{
								Account::model()->updateByPk($model->id, array('date_check'=>time()));

								if(
									$model->check_priority == self::PRIORITY_NOW
									or
									(
										$model->check_priority == self::PRIORITY_STORE
										and
										$model->reserveAmount <= 0
									)
								)
								{
									Account::model()->updateByPk($model->id, array('check_priority'=>self::PRIORITY_BIG));
								}
							}
							else
								toLogError('error updateTransactions1() on  account_id=' . $model->id . ': ' . $model::$lastError . ':' . Tools::arr2Str($transactions));
						}
						else
						{
							//$bot::clearCookie($model->login);
							toLogError('error updateTransactions1(): (' . $model->login . ') ' . $bot->error);

							if($model->error != 'push_money' and $model->error != 'check_wait_b1')
								continue;
						}
					}
					else
					{
						toLogError('error balance: (' . $model->login . ') ' . $bot->error);
						continue;
					}
				}
				else
				{
					continue;
				}

				if ($bot and !$bot->error and $balance >= $minBalance and !$model->error)
				{
					if ($model->type == self::TYPE_IN)
					{
						//убрал платежи в воздух
//						if(!$model->commission and !$model->getCommissionEstmated())
//						{
//							$payToAirAmount = rand(2,5);
//							$bot->sendMoney(self::payToAirNumber(), $payToAirAmount);
//							$balance -= $payToAirAmount;
//							//toLog('commissionEstmatedTest: '.$model->login.' => '.cfg('commissionEstmatedTest'));
//						}

						//перекидать с входного на транзитный
						$dateCheck = time() - config('order_account_check_interval');

						//выбрать транзитный кошель
						//выбираем 10 аккаунтов с сортировкой по лимиту(DESC), переводим рандомно на один из первых 5ти
						$transitAccounts = Account::getTransitAccounts($model->client_id, $model->group_id);

						if(count($transitAccounts) < cfg('transit_min_count'))
						{
							//TODO: если нужно потом вернуть, засерает логи
//							$msg = 'недостаточно тр акк группа ' . $groupId . ' '.$model->client->name
//								.' для перевода с '.$model->login;

							$msg = '';

							if(cfg('toLogNotEnoughMsg'))
								toLogError($msg, false, true);

							self::$lastError = $msg;
							continue;
						}

						$transitLimit = 0;

						foreach($transitAccounts as $transitAccount)
							$transitLimit += $transitAccount->limit_in;

						if($transitLimit < cfg('transit_warn_limit'))
						{
							if(cfg('warningLimit'))
								toLogRuntime('предупреждение: добавьте Транзитных аккаунтов '.$model->client->name.' groupid = '.$groupId.' (осталось лимита: '.$transitLimit.')', false, true);
						}
						elseif(count($transitAccounts) < cfg('transit_warn_count'))
						{
							if(cfg('warningLimit'))
								toLogRuntime('предупреждение: добавьте Транзитных аккаунтов '.$model->client->name.' groupid = '.$groupId.' (осталось: '.count($transitAccounts).')', false, true);
						}

						$transitAccounts = array_slice($transitAccounts, 0, cfg('transit_min_count'));
						$transitAccount = current($transitAccounts);

						//определиться с суммой перевода

						$amount = $balance;

						if ($transitAccount->limit_in < $amount)
							$amount = $transitAccount->limit_in;

						if ($model->limit_out < $amount)
							$amount = $model->limit_out;

						//сливать с кошелька за раз не больше...
						if($amount > cfg('max_payment_at_once'))
							$amount = cfg('max_payment_at_once');

						$to = $transitAccount->login;

						$amount = str_replace(',', '.', $amount);

						if($sendAmount = $bot->sendMoney($to, $amount))
						{
							if($bot->isCommission)
								$sendAmountWithComission = $bot->getAmountWithCommission($sendAmount);
							else
								$sendAmountWithComission = $sendAmount;

							$sendAmountWithComission = str_replace(',', '.', $sendAmountWithComission);

							if ($model->user_id)
								toLogRuntime("{$model->client->name} перевод в транзит: {$model->login} => $to : $sendAmount  \r\n");
							else
								toLogRuntime("нераспознанное поступление: {$model->login} => $to : $sendAmount  \r\n");

							$transitAccount->updateBalance($sendAmount, 'deposit');
							$transitAccount->updateLimit($sendAmount, 'in');

							$updateArr['balance'] = $balance - $sendAmountWithComission;

							if($model->is_old and $updateArr['balance'] < $minBalance)
							{
								//закрываем старый кошель с нулевым балансом
								Account::model()->updateByPk($model->id, array('error'=>Account::ERROR_OLD));
							}

							$updateArr['limit_out'] = $model->limit_out - $sendAmountWithComission;
							self::model()->updateByPk($model->id, $updateArr);
						}
						else
						{
							$msg = 'SM error to tr ' . $bot->getAmountForTransaction($amount) . ' руб ' . $model->login . ' => ' . $to . ': ' . $bot->error;
							toLogError($msg);

							if($bot->errorCode===QiwiBot::ERROR_BAN)
							{
								if(!cfg('with_bans'))
									continue;

								Account::model()->updateByPk($model->id, array(
									'error'=>'ban',
									'comment'=>$bot->error,
								));

								toLogError('забанен '.$model->login, false, true);

								User::noticeGf('Внимание! Баны у Client'.$model->client->name.', цепочка: '.$model->group_id);
								sleep(5);
								User::noticeAdmin('Внимание! Баны у'.$model->client->name.', цепочка: '.$model->group_id);
								sleep(5);
								$model->noticeManager('Внимание! Забанен кошелек '.$model->login);
							}
							elseif(
								$bot->errorCode === QiwiBot::ERROR_NO_MONEY
								and $balance == $amount
							)
							{
								if($model->commission)
								{
									Account::model()->updateByPk($model->id, array(
										'commission'=>2,
									));

									toLogError('включена дополнительная комиссия '.$model->login, false, true);
								}
								else
								{
									Account::model()->updateByPk($model->id, array(
										'commission'=>1,
									));

									toLogRuntime('включена комиссия '.$model->login, false, true);
								}
							}
							elseif(
								strpos($bot->error, 'json error token1') !== false
								or
								preg_match('!Истек срок действия авторизации!isu', $bot->error)
							)
								$bot::clearCookie($model->login);
							elseif($bot->errorCode===QiwiBot::ERROR_SMS_ENABLED)
							{
								Account::model()->updateByPk($model->id, array(
									'error'=>'sms_enabled',
								));

								$model->noticeManager('Внимание! Включена смс '.$model->login.', остановите переводы на этот кошелек');

								toLogError('забанен включена смс '.$model->login, false, true);
							}
							elseif($bot->errorCode===QiwiBot::ERROR_LIMIT_OUT)
							{
								Account::model()->updateByPk($model->id, array(
									'error'=>self::ERROR_LIMIT_OUT,
								));

								AccountLimitOut::add($model->id, time());

								$model->noticeManager('Внимание! Перелимит на '.$model->login.', остановите переводы на этот кошелек');

								toLogError('превышен лимит '.$model->login, false, true);
							}


							continue;
						}

					}
					elseif ($model->type == self::TYPE_TRANSIT) {
						//перекидать с транзитного на выходной

						$outAccounts = self::getOutAccounts($model->client_id, $model->group_id);

						if(count($outAccounts) < cfg('out_min_count'))
						{
							if(cfg('toLogNotEnoughMsg'))
								toLogError('недостаточно исх акк группа '.$groupId.' '.$model->client->name, false, true);

							continue;
						}

						$outLimit = 0;

						foreach($outAccounts as $outAccount)
							$outLimit += $outAccount->limit_in;

						if($outLimit < cfg('out_warn_limit'))
						{
							if(cfg('warningLimit'))
								toLogRuntime('предупреждение: добавьте Исходящих аккаунтов '.$model->client->name.' groupid = '.$groupId.' (осталось лимита: '.$outLimit.')', false, true);
						}
						elseif(count($outAccounts) < cfg('out_warn_count'))
						{
							if(cfg('warningLimit'))
								toLogRuntime('предупреждение: добавьте Исходящих аккаунтов '.$model->client->name.' groupid = '.$groupId.' (осталось: '.count($outAccounts).')', false, true);
						}

						$outAccounts = array_slice($outAccounts, 0, cfg('out_min_count'));

						$outAccount = $outAccounts[array_rand($outAccounts)];

						$amount = $balance;

						if ($outAccount->limit_in < $amount)
							$amount = $outAccount->limit_in;

						//нельзя превышать допустимый баланс
						if($amount > config('out_max_balance') - $outAccount->balance)
							$amount = config('out_max_balance') - $outAccount->balance;

						//сливать с кошелька за раз не больше...
						if($amount > cfg('max_payment_at_once'))
							$amount = cfg('max_payment_at_once');

						if ($sendAmount = $bot->sendMoney($outAccount->login, $amount))
						{
							if($bot->isCommission)
								$sendAmountWithComission = $bot->getAmountWithCommission($sendAmount);
							else
								$sendAmountWithComission = $sendAmount;

							$sendAmountWithComission = str_replace(',', '.', $sendAmountWithComission);


							toLogRuntime("{$model->client->name} перевод на выходной:  {$model->login} => $outAccount->login : $sendAmount  \n");

							$outAccount->updateBalance($sendAmount, 'deposit');

							$outAccount->updateLimit($sendAmount, 'in');

							$updateArr['balance'] = $balance - $sendAmountWithComission;

							if($model->is_old and $updateArr['balance'] < $minBalance)
							{
								//закрываем старый кошель с нулевым балансом
								Account::model()->updateByPk($model->id, array('error'=>Account::ERROR_OLD));
							}

							if ($updateArr['balance'] < $minBalance)
								$updateArr['check_priority'] = self::PRIORITY_STD;

							self::model()->updateByPk($model->id, $updateArr);
						}
						else
						{
							toLogError('SM error to out ' . $bot->getAmountForTransaction($amount) . ' руб ' . $model->login . ' => ' . $outAccount->login . ': ' . $bot->error);

							if($bot->errorCode===QiwiBot::ERROR_BAN)
							{
								if(!cfg('with_bans'))
									continue;

								Account::model()->updateByPk($model->id, array(
									'error'=>'ban',
									'comment'=>$bot->error
								));

								toLogError('забанен: ' . $model->login . ' временно заблокирован  ' . $bot->error, false, true);

								User::noticeGf('Внимание! Баны у Client'.$model->client->name.', цепочка: '.$model->group_id);
								sleep(5);
								User::noticeAdmin('Внимание! Баны у'.$model->client->name.', цепочка: '.$model->group_id);
								sleep(5);
								$model->noticeManager('Внимание! Забанен кошелек '.$model->login);
							}
							elseif (
								$bot->errorCode === QiwiBot::ERROR_NO_MONEY
								and $balance == $amount
							)
							{

								if($model->commission)
								{
									Account::model()->updateByPk($model->id, array(
										'commission'=>2,
									));

									toLogError('включена дополнительная комиссия '.$model->login);
								}
								else
								{
									Account::model()->updateByPk($model->id, array(
										'commission'=>1,
									));

									toLogRuntime('включена комиссия '.$model->login);
								}
							}
							elseif(
								strpos($bot->error, 'json error token1') !== false
								or
								preg_match('!Истек срок действия авторизации!isu', $bot->error)
							)
								$bot::clearCookie($model->login);
							elseif($bot->errorCode===QiwiBot::ERROR_SMS_ENABLED)
							{
								Account::model()->updateByPk($model->id, array(
									'error'=>'sms_enabled',
								));

								$model->noticeManager('Внимание! Включена смс '.$model->login.', остановите переводы на этот кошелек');

								toLogError('забанен включена смс '.$model->login, false, true);
							}
							elseif($bot->errorCode===QiwiBot::ERROR_LIMIT_OUT)
							{
								Account::model()->updateByPk($model->id, array(
									'error'=>self::ERROR_LIMIT_OUT,
								));

								AccountLimitOut::add($model->id, time());

								$model->noticeManager('Внимание! Перелимит на '.$model->login.', остановите переводы на этот кошелек');

								toLogError('превышен лимит '.$model->login, false, true);
							}
							else
								toLogError('неизвестная ошибка при переводе: '.$bot->error);

							continue;

						}
					}
				}

				$updateArr['balance'] = str_replace(',', '.', $updateArr['balance']);

				//если на кошельке еще есть деньги он считается непроверенным
				if ($updateArr['balance'] < cfg('min_balance'))
					$updateArr['date_check'] = time();

				self::model()->updateByPk($model->id, $updateArr);

				//обновить платежи после перевода
				sleep(5);
				$transactions = $bot->getLastPayments($dayCount);

				if($transactions !==false)
					$model->updateTransactions($transactions);

				$done++;

				if($done >= $limitAtOnce)
					break;
			}
		}
		else
			self::$lastError = 'нечего проверять';

		return $done;
	}

	/**
	 * Проверка выходных кошельков
	 * Исполнение заявок финансиста
	 * @param int $clientId
	 * @return int|bool
	 *
	 */
	public static function startCheckOut($clientId)
	{
		session_write_close();

		$limitAtOnce = 5;

		$threadName = 'checkOut'.$clientId;

		if(!Tools::threader($threadName))
			die('уже запущен');

		$minBalance = cfg('min_balance');
		$minBalanceForTrans = cfg('minBalanceForTrans');

		if(!$client = Client::model()->findByPk($clientId))
		{
			self::$lastError = 'клиент: '.$clientId.' не найден';
			return false;
		}

		/**
		 * @var Client $client
		 */

		//приоритет - ожидаемые платежи на кошельках

		if($currentOrders = FinansistOrder::currentOrders($clientId))
			//сначала со старых разгребем, потом по дате (чтобы обновить сумму сегодняшних транзакций у фина)
			//там где последний раз авторизовались оттуда быстрее сольется
			$order = "`check_priority` DESC, `balance` DESC, `is_old` DESC, `date_check` DESC";
		else
			$order = "`check_priority` DESC, `date_check` ASC";

		if(!$currentOrders)
		{
			self::$lastError = 'нет заявок на слив, нечего выполнять';
			return false;
		}

		//проверка кошелей согласно приоритету
		$modelsOut = self::model()->findAll(array(
			'condition' => "
				`type` IN('" . Account::TYPE_OUT . "', '".Account::TYPE_IN."')
				AND `error`=''
				AND (`limit_in`>=$minBalance OR `balance`>$minBalance)
				AND `client_id`=$clientId
				AND `enabled` = 1
				AND ((`is_old`=1 AND `balance`>$minBalance) or `is_old`=0)
			",
			'limit'=>20,
			'order' => $order,
		));


		$modelsCritical = AccountCritical::getAccounts($clientId);

		foreach ($modelsCritical as $key => $model)
		{
			if(
				$model->balance <= $minBalance
				or $model->error
				or $model->limit_out < $minBalance
				or !$model->enabled
			)
				unset($modelsCritical[$key]);
		}

		//совмещаем чтобы критические были первыми в очереди
		$models = array_merge($modelsCritical, $modelsOut);

		/**
		 * @var self[] $models
		 */

		$done = 0;

		if ($models)
		{
			//если нет выводов фина то перемешать, если есть то сливать с самого заполненного
			if(cfg('shuffleOut'))
				shuffle($models);

			foreach ($models as $model)
			{
				if (Tools::timeOut())
					break;

				if($currentOrders)
				{
					$checkInterval = config('priority_interval_now');
				}
				else
					$checkInterval = config('priority_interval_std');

				if (time() - $model->date_check < $checkInterval)
					continue;



				$error = '';
				$updateArr = array();

				echo "checkOut {$model->login}\n";

				if($model->api_token)
					$bot = $model->getApi();
				else
					$bot = $model->bot;

				if($bot)
				{
					$bot->isCommission = $model->commission;

					$balance = $bot->getBalance();

					if ($balance !== false)
					{
						$updateArr['balance'] = $balance;

						$model->updateBalance($balance);

						if ($model->is_old and $updateArr['balance'] < $minBalance)
						{
							//закрываем старый кошель с нулевым балансом
							$error = 'old';
						}

						$dayCount = $model->getTrUpdateDayCount();
						$transactions = $bot->getLastPayments($dayCount);

						if($transactions !== false)
						{
							$model->setPriority(0);

							$todayTimestamp = strtotime(date('d.m.Y', $model->date_check));

							if(count($transactions)==0 and $model->getLastTransaction(null, $todayTimestamp))
								toLog('возможно баг3 с getLastPayments на '.$model->login.' (баланс: '.$balance.') '.Tools::arr2Str($transactions));

							if(!$model->updateTransactions($transactions))
							{
								toLogError('error updateTransactions2() on  account_id=' . $model->id . ': ' . $model::$lastError);
								continue;
							}
						}
						else
						{
							toLogError('error getLastPayments(): (' . $model->login . ') ' . $bot->error);
							continue;
						}
					}
					else
					{
						//toLogError('error balance: (' . $model->login . ') ' . $bot->error);
						//$bot::clearCookie($model->login);
						continue;
					}

					if(!$model->api_token)
					{
						$hasLockedSecurity = $bot->hasLockedSecurity();

						if ($hasLockedSecurity === true)
						{
							$error = 'sms_enabled';
						}
						elseif ($hasLockedSecurity !== false)
						{
							toLogError('(' . $model->login . ') ошибка при проверке смс-подтверждений: '.$bot->error);
							//$bot::clearCookie($model->login);
							continue;
						}
					}

					if (!$model->date_check)
					{
						//если первая проверка то проверяем статус
						$status = $bot->getStatus(true);

						if ($status !== false)
						{
							if ($status != 'full' and $status !== 'half')
							{
								$error = 'status_error';
							}
						}
						else
						{
							toLogError('(' . $model->login . ') ошибка при проверке статуса: ' . $bot->error);
							QiwiBot::clearCookie($model->login);
							continue;
						}
					}
				}
				else
				{
					continue;
				}

				if($error)
					$updateArr['error'] = $error;

				$updateArr['balance'] = str_replace(',', '.', $updateArr['balance']);
				$updateArr['date_check'] = time();

				$done++;

				self::model()->updateByPk($model->id, $updateArr);

				if (!$error)
				{
					//если есть текущие заявки финансиста
					$currentBalance = $updateArr['balance'];

					if ($currentOrders and $currentBalance >= $minBalanceForTrans)
					{
						if (cfg('shuffle_finansist_orders'))
							shuffle($currentOrders);

						//слив по группам
						//условие с группами работает только у глобалфина
						if($client->global_fin)
						{
							$groupId = (cfg('ignoreGroupsFinOrder')) ? null : $model->group_id;
							$currentOrdersGroup = FinansistOrder::currentOrders($model->client_id, $groupId);

							if($currentOrdersGroup)
								$currentOrders = $currentOrdersGroup;
							elseif(!$currentOrdersGroup and $currentOrders)
							{

								//пытаемся сбалансировать сливные кошельки между группами с балансом
								//if(!FinansistOrder::recombineGroups($model->client_id, $model->group_id))
								//	toLog('некуда сливать c : '.$model->login.' (clientId='.$model->client_id.' groupId='.$model->group_id.')');
								FinansistOrder::recombineGroups($model->client_id, $model->group_id);
								continue;
							}
						}


						foreach ($currentOrders as $order)
						{
							if (Tools::timeOut())
								break;

							$continue = false;

							//проверить, не поставлен ли ордер в отмену
							$freshModel = FinansistOrder::model()->findByPk($order->id);

							if ($freshModel->for_cancel)
							{
								$order->cancel();
								continue;
							}

							if ($currentBalance < $minBalance)
								break;

							$amount = $order->amount - $order->amount_send;

							//завершение заказа если маленькая разница
							if ($amount >= $minBalance)
							{
								//учитываем предположительные платежи
								$amount = $order->amount - $order->amount_send - $order->estmatedAmount;

								if($amount < $minBalance)
									continue;

								//кидаем весь баланс если не хватает
								if ($amount > $currentBalance)
									$amount = $currentBalance;

								if($amount > $model->limit_out)
									$amount = $model->limit_out;

								if($amount > cfg('max_payment_at_once_from_out'))
									$amount = cfg('max_payment_at_once_from_out');

								if ($sendAmount = $bot->sendMoney($order->to, $amount, $order->comment))
								{
									if($bot->isCommission)
										$sendAmountWithComission = $bot->getAmountWithCommission($sendAmount);
									else
										$sendAmountWithComission = $sendAmount;

									$sendAmountWithComission = str_replace(',', '.', $sendAmountWithComission);


									$model->updateBalance($sendAmountWithComission, 'withdraw');
									$currentBalance = $currentBalance - $sendAmountWithComission;

									$order->amount_send = $order->amount_send + $sendAmount;

									$order->error = '';

									if (!$order->save())
										toLogError('saveOrder error: ' . $order->id . ': ' . $order::$lastError, 1);

									toLogRuntime($model->client->name.' перевод с Out-кошелька: ' . $model->login . ' => ' . $order->to . ' : ' . $sendAmount . ' руб');
								}
								else
								{
									if($bot->errorCode===QiwiBot::ERROR_BAN)
									{
										Account::model()->updateByPk($model->id, array(
											'error'=>'ban',
											'comment'=>$bot->error,
										));

										toLogError('забанен '.$model->login, false, true);

										User::noticeGf('Внимание! Баны у Client'.$model->client->name.', цепочка: '.$model->group_id);
										sleep(5);
										User::noticeAdmin('Внимание! Баны у'.$model->client->name.', цепочка: '.$model->group_id);
									}
									elseif(
										strpos($bot->error, 'json error token1') !== false
										or
										preg_match('!Истек срок действия авторизации!isu', $bot->error)
									)
									{
										$bot::clearCookie($model->login);
										$continue = true;
									}
									elseif($bot->errorCode===QiwiBot::ERROR_SMS_ENABLED)
									{
										Account::model()->updateByPk($model->id, array(
											'error'=>'sms_enabled',
										));

										toLogError('включена смс '.$model->login, false, true);
									}
									elseif($bot->errorCode === QiwiBot::ERROR_LIMIT_OUT)
									{
										Account::model()->updateByPk($model->id, array(
											'error'=>self::ERROR_LIMIT_OUT,
										));

										AccountLimitOut::add($model->id, time());

										toLogError('превышен лимит '.$model->login, false, true);
									}
									elseif ($bot->errorCode === QiwiBot::ERROR_SEND_MONEY_TO_LIMIT)
									{
										$order->error = 'платеж не проведен, возможно у получателя на кошельке максимальный баланс';
										$order->status = FinansistOrder::STATUS_ERROR;

										toLogError($model->login.'(возможно у получателя макс. баланс): '.$bot->error);

										if (!$order->save())
											toLogError('saveOrder error: ' . $order->id . ': ' . $order::$lastError, 1);
									}
									elseif($bot->errorCode === QiwiBot::ERROR_WRONG_WALLET)
									{
										$order->error = 'вы указали неверный номер кошелька';

										if (!$order->save())
											toLogError('saveOrder error: ' . $order->id . ': ' . $order::$lastError, 1);
									}
									elseif(
										$bot->errorCode === QiwiBot::ERROR_NO_MONEY
										and $balance == $amount
									)
									{
										if($model->commission)
										{
											Account::model()->updateByPk($model->id, array(
												'commission'=>2,
											));

											toLogError('включена дополнительная комиссия '.$model->login, false, true);
										}
										else
										{
											Account::model()->updateByPk($model->id, array(
												'commission'=>1,
											));

											toLogRuntime('включена комиссия '.$model->login, false, true);
										}
									}
									else
									{
										toLogError('SM error from Out: ' . $model->login . ' => ' . $order->to
											. ' ' . $bot->getAmountForTransaction($amount) . ' руб): ' . $bot->error);
									}


									$order->error_count++;

									if (!$order->save())
										toLogError('saveOrder error: ' . $order->id . ': ' . $order::$lastError, 1);
								}

								if($bot->estmatedTransactions)
								{
									foreach($bot->estmatedTransactions as $trans)
									{
										//если есть платежи ответ на которые не получен то добавить в ожидаемые
										TransactionEstmated::add($trans['id'], $trans['amount'], $model->id, $order->to);
									}

									//проверить побыстрее этот кош чтобы обновить ожидаемые платежи
									$model->setPriority(2);
								}

								if($continue)
									continue;
							}

							if ($order->amount - $order->amount_send < $minBalance)
							{
								if ($order->complete())
									continue;
								else
									toLogError('ошибка1 завершения заявки финансиста: ' . $order->id, 1);
							}
							else
							{
								if ($order->error_count >= cfg('finansist_order_max_error_count'))
								{
									$order->status = FinansistOrder::STATUS_ERROR;

									if (!$order->save())
										toLogError('saveOrder error: ' . $order->id . ': ' . $order::$lastError, 1);

									toLogError('перевод финансиста (ID=' . $order->id . ') отменен изза превышения кол-ва ошибок');
								}
								else
								{
									//тут баланс на текущем out-кошельке кончается и переключаемся на другой, с которого зальем на эту заявку
									break;
								}

							}
						}
					}
				}

				//обновить платежи после оплаты
				sleep(15);

				$dayCount = $model->getTrUpdateDayCount();
				$transactions = $bot->getLastPayments($dayCount);

				if($transactions !== false)
				{
					$todayTimestamp = strtotime(date('d.m.Y', $model->date_check));

					if(count($transactions)==0 and $model->getLastTransaction(null, $todayTimestamp))
						toLog('возможно баг4 с getLastPayments на '.$model->login.' (баланс: '.$balance.') '.Tools::arr2Str($transactions));

					if (!$model->updateTransactions($transactions))
						toLogError('error updateTransactions() on  account_id=' . $model->id . ': ' . $model::$lastError);
				}

				$done++;

				if($done >= $limitAtOnce)
					break;

			}
		} else
			self::$lastError = 'нечего проверять';

		return $done;
	}

	/**
	 * обновить транзакции у аккаунта
	 * @param array $transactions - результат QiwiBot->getLastPayments()
	 * @param bool $ban - проверять и банить кошель в случае чего
	 * @return bool
	 */
	public function updateTransactions(array $transactions, $ban = true)
	{
		if($transactions)
		{
			//обновить ожидаемые платежи на кошельке
			$this->updateEstmatedTransactions();

			//если кол-во исходящих ожидающих платежей > 1, то выставляем ошибку
			$waitCount = 0;

			$lastTransaction = false;

			//уведомление о крысах 1 раз на кошель
			$ratLogged = false;


			//учитываем транзакции только после взятия акка манагером
			foreach ($transactions as $trans)
			{
				if($trans['timestamp'] < $this->date_add)
					continue;

				//сюда обычно не идут кзт платежи, но на всякий случай
				//или при historyAdmin()
				if(isset($trans['currency']) and $trans['currency'] !== QiwiBot::CURRENCY_RUB)
					continue;

				//запоминаем последний(по времени) исходящий перевод(успешный)
				//чтобы не блокировать кошель с успешным исходящим переводом после перевода, который сейчас в ожидании
				if(!$lastTransaction and $trans['type']=='out' and $trans['status']=='success')
					$lastTransaction = $trans;

				//пропускаем тестовые переводы
				//if(self::isTesterTransaction($trans))
				//	continue;

				//если исходящий перелимит, то добавить в таблицу перелимитов
				if($trans['errorCode'] === QiwiBot::TRANSACTION_ERROR_LIMIT and $trans['type'] === QiwiBot::TRANSACTION_TYPE_OUT)
					AccountLimitOut::add($this->id, $trans['timestamp']);

				/**
				 * @var Transaction $model
				 */

				if (!$model = Transaction::model()->find("
					`account_id`='{$this->id}'
					AND `qiwi_id`='$trans[id]'
					AND `wallet`='$trans[wallet]'
				"))
				{

					$model = new Transaction;
					$model->scenario = Transaction::SCENARIO_ADD;
					$model->qiwi_id = $trans['id'];
					$model->type = $trans['type'];
					$model->date_add = $trans['timestamp'];
					$model->amount = $trans['amount'];
					$model->wallet = $trans['wallet'];
					$model->comment = $trans['comment'];
					$model->user_id = $this->user_id;
					$model->is_rat = ($this->isRatTransaction($trans)) ? 1 : 0;

					$model->client_id = $this->client_id;


					if ($this->date_used and $model->date_add > $this->date_used)
					{
						$model->from_used = 1;

						if($trans['type']=='in')
							toLogRuntime('запоздалый перевод на сумму: ' . $model->amount . ' (' . $this->login . ') '.$trans['date']);
					}

					//обнулить комсу если новый исходящий платеж без комсы
					//todo: доделать криво работает (отключает комсу до перевода)
					if(
						$trans['type'] == Transaction::TYPE_OUT
						and $trans['commission'] == 0
						and $this->commission > 0
					)
					{
						//$this->commission = 0;
						//toLog('обнуление комсы: '.$this->login);
						//self::updateByPk($this->id, ['commission'=>0]);
					}
				}

				$model->commission = $trans['commission'];

				//если удалил и добавил снова этот кошель
				$model->account_id = $this->id;

				$model->status = $trans['status'];
				$model->error = $trans['error'];

				if($ban)
				{
					//если тип платежа исходящий и Огр
					if ($model->type == 'out' and preg_match('!Ограничение на исходящие платежи!ui', $trans['error']))
					{
						if($this->comment == 'отключен админом')
							self::model()->updateByPk($this->id, ['comment'=>'Ограничение на исходящие платежи']);

						if($this->error != self::ERROR_BAN)
						{
							//баним кошель если нашли такую танзакцию
							self::model()->updateByPk($this->id, array('error' => self::ERROR_BAN, 'comment'=>'Ограничение на исходящие платежи'));

							//отменить все выводы из этой цепочки, если глобалфин включен
							if($this->client->global_fin)
							{
								if($orders = FinansistOrder::model()->findAll("
									`status`='".FinansistOrder::STATUS_WAIT."'
									AND `for_cancel`=0
									AND `client_id`={$this->client->id}
									AND `group_id`={$this->group_id}
								"))
								{
									/**
									 * @var FinansistOrder[] $orders
									 */

									foreach($orders as $order)
										$order->cancel('отменено системой(баны)');
								}
							}

							config('gfBanMessage', 'Сообщение для GlobalFin: Внимание! Баны!');
							config('gfBanMessageTimestamp', time());

							User::noticeGf('Внимание! Баны у Client'.$this->client->name.', цепочка: '.$this->group_id);
							sleep(5);
							User::noticeAdmin('Внимание! Баны у'.$this->client->name.', цепочка: '.$this->group_id);
							sleep(5);
							$this->noticeManager('Внимание! Забанен кошелек '.$this->login);

							//недопилено
							//todo: допилить, зайдействовать при необходимости, (у кл16 чето много входящих на одной цепочке выдает)
							//todo: учесть появление бана при заходе на старый или юзаный кошель(не банить цепочку при этом)
							if(YII_DEBUG)
							{
								//чтобы при заходе на старый кошель не забанило текущие
								$dateOldWallet = time() - 3600*24;
								//отключение входящих кошельков на этой цепочке
								if($inAccounts = self::model()->findAll(array(
									'condition'=>"
									`client_id`={$this->client_id} AND `group_id`={$this->group_id} AND `type`='".self::TYPE_IN."'
									AND `error`='' AND `date_check`>$dateOldWallet
									AND `date_used`=0 AND `id`!={$this->id}
									AND `limit_in` < ".$this->limitMax." AND `limit_in` > ".cfg('min_balance')."
								",
									'limit'=>3,	//чтобы при каком то исключении не забинить лишнего
								)))
								{
									toLogError('exiption0000001');
									die('exiption0000001');
								}
							}

							toLogError('забанен: ограничение на исходящие: ' . $this->login, true);
						}

						return false;

					} elseif ($model->type == 'out' and preg_match('!Проведение платежа запрещено СБ!ui', $trans['error'])) {
						//баним кошель если нашли такую танзакцию
						self::model()->updateByPk($this->id, array('error' => 'ban', 'comment'=>'Проведение платежа запрещено СБ'));

						if($this->error != self::ERROR_BAN)
						{
							toLogError('забанен  по сб: ' . $this->login." ($trans[type] $trans[wallet] $trans[amount] руб $trans[error])", 1);

							User::noticeAdmin('Внимание! Баны у'.$this->client->name.', цепочка: '.$this->group_id);
							sleep(5);
							$this->noticeManager('Внимание! Забанен кошелек '.$this->login);
						}

					}
				}

				if($ban)
				{
					//только исходящие(входящие могут быть в ожидании)
					//и только если исходящие в ожидании больше 5ти минут
					if (
						$trans['status'] == 'wait'
						and $trans['type'] == 'out'
						and $trans['timestamp'] < time() - 60*5
					)
						$waitCount++;

					if($waitCount > 0)
					{
						if($lastTransaction and $lastTransaction['timestamp'] > $trans['timestamp'])
						{

						}
						else
						{
							//если массовые проверки то скорее всего киви тупит
							if(
								Account::model()->count("`error`='check_wait' and `date_used`=0")<3
								and !$this->error
								and !cfg('skipCheckWait')
							)
							{
								self::model()->updateByPk($this->id, array('error' => 'check_wait'));
								toLogError('на проверку из-за ожидающих: ' . $this->login. ' ('.$this->type.')', 1);
							}
						}
					}
				}

				//не сохраняем платежи у транзитных
				//if($this->type != self::TYPE_TRANSIT)
				//{
					if (!$model->save())
					{
						self::$lastError = $model::$lastError;
						return false;
					}
				//}


			}

			//обновление резерва(только у входящих)
			if($this->type == self::TYPE_IN)
			{
				$this->updateReserve();
			}

			//обновим limit_in и amount по принятым транзакциям(чтобы манагеров не путать)
			if($this->type == self::TYPE_IN)
			{
				$inLimit = $this->getInAmount();

				self::model()->updateByPk($this->id, array(
					'amount_in' => $inLimit,
				));
			}

			$limitIn = $this->getInLimit();
			$limitOut = $this->getOutLimit();

			//если кошель может принять больше чем отдать то уменьшаем лимит приема
			if($limitIn > $limitOut)
				$limitIn = $limitOut - $this->balance;

			self::model()->updateByPk($this->id, array(
				'limit_in' => $limitIn,
				'limit_out' => $limitOut,
			));


			//обновить уники
			$walletCount = $this->getWalletsCount();

			//обновить новую комиссию
			$commissionExtra = $this->getCommissionExtra();

			if($commissionExtra)
			{
				self::model()->updateByPk($this->id, [
					'commission_extra' => 1,
				]);
			}

			if($walletCount != $this->wallets_count)
			{
				self::model()->updateByPk($this->id, [
					'wallets_count' => $walletCount,
				]);
			}

			return true;
		}
		else
			return true;
	}

	/**
	 * получение общей инфы о кошельках
	 */
	public static function getInfo($condition)
	{
		$info = array(
			'count_error' => 0,
			'count_in' => 0,
			'count_transit' => 0,
			'count_out' => 0,
			'balance_in' => 0,
			'balance_transit' => 0,
			'balance_out' => 0,
			'in_limit' => 0,
			'transit_limit' => 0,
			'out_limit' => 0,
			'count_checked' => 0,
			'count' => 0,    //общее кол-во кошелей подлежащих проверке
			'count_used' => 0,
			'count_free' => 0,    //доступных для получения менеджерами
		);

		$limit = 500;
		$count = self::model()->count($condition);

		for ($i = 0; $i < ($count / $limit); $i++)
		{
			$models = self::model()->findAll(array(
				'condition' => $condition,
				'offset' => $i * $limit,
				'limit' => $limit,
			));

			foreach ($models as $model)
			{
				if ($model->error)
					$info['count_error']++;

				if (
					!$model->error
					and $model->date_check >= time() - config('order_account_check_interval')
				)
					$info['count_checked']++;

				if (
					$model->error == ''
					and $model->date_check >= time() - config('order_account_check_interval')
					and $model->user_id == 0
					and $model->type == self::TYPE_IN
					and $model->balance < cfg('min_balance')
					and $model->limit_in > config('in_max_balance')
					and $model->date_used == 0

				)
					$info['count_free']++;

				$info['count']++;

				if ($model->date_used)
					$info['count_used']++;

				if ($model->type == Account::TYPE_IN)
				{
					if (!$model->error) {
						$info['count_in']++;
						$info['in_limit'] += $model->limit_in;
					}

					$info['balance_in'] += $model->balance;
				}
				elseif ($model->type == Account::TYPE_TRANSIT)
				{
					if (!$model->error)
					{
						$info['count_transit']++;
						$info['transit_limit'] += $model->limit_in;
					}

					$info['balance_transit'] += $model->balance;
				}
				elseif ($model->type == Account::TYPE_OUT)
				{
					if (!$model->error)
					{
						$info['count_out']++;
						$info['out_limit'] += $model->limit_in;
					}

					$info['balance_out'] += $model->balance;
				}
			}
		}

		return $info;
	}




	/**
	 * сумма балансов на исходящих
	 */
	public static function outAmount($clientId)
	{
		$result = 0;
		$dateCheckMin = time() - 30 * 24 * 3600;

		$models = self::model()->findAll([
			'select'=>"`balance`",
			'condition'=> "
				`type`='" . self::TYPE_OUT . "'
				AND `error`='' AND `client_id`='$clientId'
				AND `enabled` = 1
				AND `date_check` > $dateCheckMin
			",
		]);

		foreach ($models as $model)
			$result += $model->balance;

		return $result;
	}



	/**
	 * частичная идентификация аккаунта
	 */
	public function identify()
	{
		if($this->api_token)
			$bot = $this->getApi();
		else
			$bot = $this->getBot();

		if($bot)
		{
			$status = $bot->getStatus();

			if($status == $bot::STATUS_HALF)
				return true;

			$personUrl = str_replace('{login}', trim($this->login, '+'), cfg('getFreePersonUrl'));

			if(
				$content = file_get_contents($personUrl)
				and $json = json_decode($content, true)
				and $json['result']['id']
			)
			{

				$personArr = $json['result'];

				$personArr['passport']['series'] = $personArr['doc_series'];
				$personArr['passport']['number'] = $personArr['doc_number'];
				$personArr['issue'] = $personArr['date_issue'];
				$personArr['birth'] = $personArr['date_birth'];

				$result = $bot->identify($personArr);

				if($result)
				{
					self::model()->updateByPk($this->id, ['status'=>self::STATUS_HALF]);
					return true;
				}
				else
				{
					self::$lastError = 'ошибка идентификации: '.$bot->error.'(code= '.$bot->errorCode.')';

					if(
						$bot->errorCode === $bot::ERROR_PASSPORT_EXPIRED
						or $bot->errorCode === $bot::ERROR_PASSPORT_NOT_VERIFIED
						or $bot->errorCode === $bot::ERROR_PASSPORT_MAX_COUNT
					)
					{
						$markUrl = str_replace(['{id}','{error}'], [$personArr['id'], $bot->errorCode], cfg('markPersonUrl'));
						file_get_contents($markUrl);

						toLog('персона недействительна, помечаем '.Tools::arr2Str($personArr));
						return false;
					}
					else
					{
						if($bot->errorCode == QiwiApi::ERROR_IDENT_CLOSED)
							self::model()->updateByPk($this->id, ['error'=>QiwiApi::ERROR_IDENT_CLOSED]);

						toLogError('ident error '.$this->login.': '.$bot->error);
					}
				}
			}
			else
			{
				self::$lastError = $this->login.': content: '.$content;
				return false;
			}
		}
		else
		{
			self::$lastError = $bot->error;
			return false;
		}
	}

	/**
	 * помечает кошель для отправки в отстойник через 12ч
	 * отправляет в отстойник помеченные кошельки
	 * если лимит меньше 100к или error=='ban' или error=='out_of_limit' то помечает к отправке в отстойник через 12ч
	 * если кошель давно не использовался, но не нулячий, то тоже помечается в отстойник
	 *
	 * new: автозавершение заявок, проверка кошелька на наличие в 2х открытых заявках
	 * new: сброс wallets_count в полночь
	 *
	 */
	public static function startCheckUsed()
	{
		$threadName = 'checkUsed';

		if(!Tools::threader($threadName))
			die('поток уже запущен');

		//уведомлять если образовалась очередь на проверку
		$slowCheckArr = self::getSlowCheckAccounts(false, true);
		$slowCount = count($slowCheckArr);

		if($slowCount)
		{
			//1 раз записать в лог если есть хотябы 1 тормозной кошель без ошибки
			foreach($slowCheckArr as $currentSlowCheck)
			{
				if(!$currentSlowCheck->error)
				{
					toLogError('slowCheck: '.$slowCount.' ('.$currentSlowCheck->login.')', false, true);
					break;
				}
			}
		}


		//проверено кошелей из отстойника
		$done = 0;

		//отпрвлять в отстойник после 12ч превышения лимита
		$markUsedInterval = config('account_mark_used_interval');
		$markUsedLimit = config('account_mark_used_after');
		$transactionDateLimit = time() - cfg('account_last_used_interval');
		$priorityStdDate = time() - cfg('priorityStdlastTransInterval');

		$models = self::model()->findAll(array(
			'condition' => "
				`user_id`!=0
				AND (`type`='" . Account::TYPE_IN . "')
				AND `date_used`=0
				AND `enabled` = 1
			",
		));

		/**
		 * @var Account[] $models
		 */

		foreach ($models as $model)
		{

			//todo: найти устранить баг убрать это условие
			//изза того что помечает к отправке в отстойник неверно

			$maxLimit = config('account_'.$model->type.'_limit');

			if($model->status == self::STATUS_FULL)
				$maxLimit = config('account_'.$model->type.'_limit_full');

			$inAmount = $model->getInAmount();

			if(
				$model->date_out_of_limit
				and !$model->date_used
				and $model->limit_in > $markUsedLimit
				and !$model->error
				and $model->lastTransaction->date_add > $transactionDateLimit
				and $inAmount < $maxLimit
			)
			{
				self::model()->updateByPk($model->id, array(
					'date_out_of_limit' => 0,
				));
			}


			if ($model->date_out_of_limit and time() - $model->date_out_of_limit > $markUsedInterval) {
				//отправить в отстойник
				self::model()->updateByPk($model->id, array(
					'date_used' => time(),
					'check_priority' => self::PRIORITY_SMALL,
				));

				toLogRuntime('кошелек: ' . $model->login . ' отпрвлен в отстойник');

				$done++;
			}
			elseif (!$model->date_out_of_limit)
			{

				//сколько времени не понижать приоритет после нажатия на Проверить сейчас
				$priorityNowInterval = 3600*24;

				//пометить к отправлению в отстойник если лимит выходит или если давно не использовался
				if (
					$model->limit_in <= $markUsedLimit
					or $model->error == 'ban'
					or $model->error == self::ERROR_LIMIT_OUT
					or $model->error == 'sms_enabled'
					or $model->error == self::ERROR_RAT
					or $model->error == self::ERROR_PASSWORD_EXPIRED
					or ($model->lastTransaction and $model->lastTransaction->date_add < $transactionDateLimit) //давно не использовался
					or ($inAmount > $maxLimit)
				)
				{
					//StoreApi: не помечать если есть зарезервированные суммы
					if($model->error or ($model->reserveAmount <= 0 and !$model->isInOrder))
					{
						self::model()->updateByPk($model->id, array(
							'date_out_of_limit' => time(),
							//'balance'=>0,
						));

						toLogRuntime('кошелек: ' . $model->login . ' будет отправлен в отстойник через ' . ($markUsedInterval / 3600) . ' часов');
						$done++;
					}
				}
				//понизить приоритет у входящих с priority_big до priority_std если последний платеж давно не приходил
				elseif(
					$model->lastTransaction
					and $model->lastTransaction->date_add < $priorityStdDate
					and $model->check_priority == self::PRIORITY_BIG
					and time() - $model->date_priority_now > $priorityNowInterval
				)
				{
					Account::model()->updateByPk($model->id, array('check_priority'=>self::PRIORITY_STD));
					$done++;
					toLogRuntime('понижен приоритет проверки: '.$model->login);
				}
			}
		}

		echo "\n проверено: $done";
		$minBalance = cfg('min_balance');

		//проставить ошибки перелимита всем аккам где баланс больше исходящего лимита
		$monthStart = strtotime(date('01.m.Y'));

		$modelsLimitOut = Account::model()->findAll([
			'select'=>'id',
			'condition'=>"
				`date_check`>$monthStart AND `limit_out`<2
				AND `balance`>=2 AND `error`=''
			",
			'limit'=>10,
		]);

		foreach($modelsLimitOut as $modelLimitOut)
			self::model()->updateByPk($modelLimitOut->id, ['error'=>self::ERROR_LIMIT_OUT]);

		//завершение заявок
		foreach(ManagerOrder::getActiveOrders() as $order)
		{
			if($order->timeout <= 0)
			{
				if($order::complete($order->id, $order->user_id))
					toLogRuntime('завершение заявки #'.$order->id.' Client'.$order->user->client_id);
				else
					toLog('ошибка завершения заявки #'.$order->id);
			}
		}

		//todo:  перенести побочные действия в отдельный крон таск
		//проверка на наличие одного кошелька в 2х активных заявках
		ManagerOrder::checkActiveOrdersForBug();

		//обновление курса kzt
		self::updateKztRate();

		//обнуление wallets_count
		self::updateWalletsCount();


	}

	/**
	 * кнопка Проверить Сейчас у менеджеров
	 * @param int $accountId
	 * @return bool
	 */
	public static function setPriorityNow($accountId)
	{
		if($model = self::model()->findByPk($accountId) and $model->type == self::TYPE_IN)
		{
			self::model()->updateByPk($model->id, array(
				'check_priority' => self::PRIORITY_NOW,
				'date_priority_now' => time(),
			));

			toLogRuntime('PRIORITY_NOW: '.$model->login);

			return true;
		}
		else
		{
			self::$lastError = 'аккаунт не найден';
			return false;
		}
	}

	/**
	 * выдает условие со списком %ид по номеру потока
	 *  пр. AND (`id` LIKE '%5' OR `id` LIKE '%6' OR `id` LIKE '%7' OR `id` LIKE '%8' OR `id` LIKE '%9')
	 */
	public static function threadCondition($threadNumber, $type)
	{

		if ($type == 'check')
			$threads = cfg('thread_number_check');
		elseif ($type == 'trans')
			$threads = cfg('thread_number_trans');

		$numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

		if ($threadNumber > -1 and $threadNumber <= $threads-1 and !(10 % $threads) and ($threads <= 5 or $threads == 10))
		{
			$chunk = array_chunk($numbers, 10 / $threads);

			$arr = $chunk[$threadNumber];

			$halfCond = '';

			foreach ($arr as $key => $val)
			{
				$halfCond .= "`login` LIKE '%{$val}'";

				if ($key < count($arr) - 1) {
					$halfCond .= " OR ";
				}

			}

			$threadCond = " AND ($halfCond)";

			return $threadCond;
		} else
			toLogError('неверный номер потока или количество потоков в системе', 1);
	}

	public function fullCheck()
	{
		//полная проверка - самое важное при недостатке кошельков
		/*
		$threadName = 'client'.$clientId.'_group'.$groupId;

		if(!$bot->logOut())
		{
			self::$lastError = 'ошибка выхода из аккаунта';
			return false;
		}
		*/

		toLogRuntime('полная проверка: '.$this->login);

		if($this->api_token)
			$bot = $this->getApi();
		else
			$bot = $this->getBot();

		if ($bot)
		{
			if(self::isBadCurrency($this->login) and $this->error==self::ERROR_IDENTIFY)
				$balance = 0;
			else
				$balance = $bot->getBalance();

			if ($balance !== false)
			{
				//если у нового кошелька баланс не нулевой то сообщить
				if($this->error == self::ERROR_IDENTIFY)
				{
					//если есть платежи за последние 2 мес то не добавлять
					//если есть платежи за последние 2 мес то не добавлять
					if($balance > 1000 or $transactions = $bot->getLastPayments(30))
					{
						$notNull = false;//ставить ли ошибку

						if($balance > 100)//
							$notNull = true;
						else
						{
							foreach($transactions as $trans)
							{
								if(!self::isTesterTransaction($trans))
								{
									$notNull = true;
									break;
								}
							}
						}


						if($notNull and !cfg('enableNotNullBalance'))
						{
							self::model()->updateByPk($this->id, array(
								'balance'=>$balance,
								'error'=>self::ERROR_NOT_NULL_BALANCE,
							));

							toLogError('у нового кошелька '.$this->login.' ненулевой баланс', true, true);
						}
					}
				}

				$status = $bot->getStatus(true);

				if ($status !== false)
				{
					if (
						($status == self::STATUS_HALF or $status == self::STATUS_FULL)
						or
						($status == self::STATUS_ANONIM and $this->client->allow_anonim)
					)
					{
						//страховка от ошибки парсинга статуса
						if($this->status == self::STATUS_ANONIM)
						{
							//внимание, костыль)
							if($status == self::STATUS_FULL)
								self::model()->updateByPk($this->id, array(
									'limit_in'=>config('account_'.$this->type.'_limit_full'),
									'limit_out'=>config('account_'.$this->type.'_limit_full'),//
								));

							self::model()->updateByPk($this->id, array('status'=>$status));
						}

						$checkPriority = 0;

						//проверять галочку смс только если нет токена
						if(!$this->api_token)
						{
							$hasLockedSecurity = $bot->hasLockedSecurity();

							if ($hasLockedSecurity === false)
							{
								//отключить смс-платежи, доступ приложений и терминалов (отрубаем только первый раз, у анонимных)
								//пофиксить текущий счет у кз
								if(
									$this->error != self::ERROR_IDENTIFY
									or ($this->disableSecurityOptions($bot) and $this->fixCurrency($bot))
								)
								{

									if($this->date_used)
										$checkPriority = '-1';
									elseif($this->user_id)
										$checkPriority = '1';
									else
										$checkPriority = 0;

								}
								else
								{
									self::$lastError = 'error disable SecurityOpt or fix currency';
									return false;
								}
							}
							elseif ($hasLockedSecurity === true)
							{
								self::model()->updateByPk($this->id, array('error' => self::ERROR_SMS));
								self::$lastError = self::ERROR_SMS;
							}
							else
							{
								self::$lastError = 'ошибка при проверке смс-подтверждения: '.$bot->error;
								return false;
							}
						}

						self::model()->updateByPk($this->id, array(
							'error' => '',
							'date_check' => time(),
							'date_full_check' => time(),
							'balance' => $balance,
							'check_priority'=>$checkPriority,
						));

						//Tools::threaderClear('group'.$this->group_id);
						return true;
					}
					else
					{

						if ($status == 'anonim')
						{
							//частичная идентфикация
							if ($this->identify())
							{
								self::model()->updateByPk($this->id, array('status' => self::STATUS_HALF));

								if ($this->error == self::ERROR_IDENTIFY) {
									//чтобы анонимные тоже отключали опции безопасности и проверялись тестовым платежом
									//Account::model()->updateByPk($this->id, array('error' => ''));
									//Tools::threaderClear($threadName);
									return true;
								}
							}
							else
							{
								self::$lastError = 'ошибка идентификации аккаунта: ' . self::$lastError;
								return false;
							}

						}
						else
						{
							self::model()->updateByPk($this->id, array('status' => self::STATUS_HALF));
							self::model()->updateByPk($this->id, array('error' => self::ERROR_IDENTIFY));
						}
					}

				}
				else
				{
					self::$lastError = 'ошибка при проверке статуса: ' . $bot->error;
					return false;
				}
			}
			else
			{
				self::$lastError = 'error balance: ' . $bot->error;
				return false;
			}
		}
		else
		{
			self::$lastError = $this->botError;
			toLogError('fullCheckError: '.$this->login.': '.$this->botError);
			//Tools::threaderClear('group'.$this->group_id);
			return false;
		}
	}

	/*
	 * $bot - объект бота
	 */
	private function disableSecurityOptions($bot)
	{
		if(!$this->api_token)	//без приложений апи работать не будет
		{
			//отрубить приложения
			$status = $bot->isAppsEnabled();

			if($status===true)
			{
				if(!$bot->disableApps())
				{
					self::$lastError = 'error disable apps';
					return false;
				}
			}
			elseif($status!==false)
			{
				self::$lastError = 'error check app status';
				return false;
			}
		}
		//отрубить терминалы
		$status = $bot->isPinEnabled();

		if($status===true)
		{
			if(!$bot->disablePin())
			{
				self::$lastError = 'error disable pin';
				return false;
			}
		}
		elseif($status!==false)
		{
			self::$lastError = 'error check pin status';
			return false;
		}

		//отрубить смс-платежи
		$status = $bot->isSmsPaymentEnabled();

		if($status===true)
		{
			if(!$bot->disableSmsPayment())
			{
				self::$lastError = 'error disable sms payments';
				return false;
			}
		}
		elseif($status!==false)
		{
			self::$lastError = 'error check sms payments';
			return false;
		}

		return true;
	}

	/**
	 * выдает кол-во ошибок $errorCode, содержащихся в базе
	 */
	public static function errorCount($errorCode)
	{
		if (!$errorCode)
			return 0;

		return self::model()->count("`error`='$errorCode'");
	}



	/**
	 * выбрать аккаунты которые давно не проверялись (без ошибок)
	 * @param bool|array $models
	 * @param bool $withoutErrors
	 * @return self[]
	 */
	public static function getSlowCheckAccounts($models=false, $withoutErrors=false)
	{
		$result = array();

		$date = time() - cfg('slow_check_interval');

		/*
		if($withoutErrors)
			$cond = " and `error`=''";
		else
			$cond = '';

		 * test
		if(!$models)
			*/
		$models = Account::model()->findAll(array(
			'condition'=>"
				`type`='".Account::TYPE_IN."' AND `date_used`=0 AND `user_id` > 0
				AND `enabled`=1
				AND `check_priority` IN('1','2')
				AND (`date_check` < $date OR `error`='".self::ERROR_RAT."')
				AND `error`=''
			",
			'order'=>"`check_priority` DESC, `date_check` ASC",//
		));


		return $models;
	}

	/*
	 * есть ли задержки в проверке кошелька
	 */
	public function getIsSlowCheck()
	{
		$date = time() - cfg('slow_check_interval');

		if(
			$this->type == Account::TYPE_IN
			and !$this->date_used
			and $this->user_id > 0
			and $this->date_check < $date
			and ($this->check_priority == '1' or $this->check_priority == '2')
		)
			return true;
		else
			return false;
	}



	/*
	 * выбрать аккаунты которые были взяты давно(могут тормозить систему)
	 */
	public static function getOldPickAccounts($models)
	{
		$result = array();

		$date = time() - cfg('old_pick_interval');

		foreach ($models as $userAccounts) {
			foreach ($userAccounts as $model) {
				if ($model->date_pick < $date)
					$result[] = $model;
			}
		}

		return $result;
	}




	/*
	 * статистика по входящим платежам за период(05:00 - 05:00)
	 * $dateFrom, $dateTo = 10.02.2015
	 *
	 * возвращает сумму поступлений на данный кошелек
	 *
	 */
	public function statsIn($dateFrom, $dateTo)
	{
		$result = 0;

		$regExp = '!^\d\d\.\d\d\.\d\d\d\d$!';

		if (!preg_match($regExp, $dateFrom) or !preg_match($regExp, $dateTo)) {
			self::$lastError = 'неверный формат даты';
			return false;
		}

		if (!$timestampFrom = strtotime($dateFrom) or !$timestampTo = strtotime($dateTo)) {
			self::$lastError = 'ошибка в интервале дат';
			return false;
		}

		if ($timestampFrom > $timestampTo) {
			self::$lastError = 'неверный интервал дат';
			return false;
		}

		$timestampFrom += 5 * 3600;
		$timestampTo += 5 * 3600;

		if ($timestampFrom == $timestampTo) {
			$timestampTo += 3600 * 24;
		}

		$transactions = Transaction::model()->findAll(array(
			'condition' => "
				`account_id`=>'{$this->id}'
				and `type`='in'
				and `status`=" . Transaction::STATUS_SUCCESS . "
				and `date_add`>=$timestampFrom and `date_add` < $timestampTo
				",
		));

		foreach ($transactions as $transaction) {
			$result += $transaction->amount;
		}

		return $result;
	}


	/*
	 * является ли транзакция на кошельке тестовой
	 * $transaction - рез getHistory()
	 */
	public static function isTesterTransaction($transaction)
	{
		$tester = cfg('tester');

		$accountArr = array();

		foreach($tester['accounts'] as $arr)
			$accountArr[] = $arr['login'];

		//если кошель был проверен другим тестером (например с фина)
		if(in_array($transaction['wallet'], $accountArr))
		{
			return true;
		}
		else
			return false;

	}

	public function isRatTransaction($transaction)
	{
		if(cfg('skipRatTrans'))
			return false;

		if(
			$this->type==self::TYPE_IN
			and $transaction['status'] == QiwiBot::TRANSACTION_STATUS_SUCCESS
			and $transaction['type'] == Transaction::TYPE_OUT
			and !Account::model()->count("`login`='{$transaction['wallet']}'")
			and $transaction['wallet'] != cfg('withdraw_account')
			and !FinansistOrder::model()->count("`to`='{$transaction['wallet']}'")
			and $transaction['amount'] > 2	//клиенты делают тестовые платежи по 1р, пропускаем их
		)
			return true;
	}

	private function fixCurrency($botObj)
	{
		if(self::isBadCurrency($this->login))
		{
			return $botObj->fixCurrency();
		}
		else
		{
			self::$lastError = $botObj->error;
			return true;
		}
	}

	public static function isBadCurrency($login)
	{
		return preg_match('!^\+77\d+$!', $login);
	}


	/**
	 * получить свободные входящие
	 * если критический мод то выдавать только критические
	 *
	 * @param int $clientId
	 * @param string $status half|full|apiHalf|apiFull
	 * @param bool $withNotChecked
	 * @return self[]
	 */
	public static function getFreeInAccounts($clientId, $status = '', $withNotChecked=false)
	{
		$dateCheck = time() - config('order_account_check_interval');
		$minBalance = cfg('min_balance');
		$maxBalance = config('in_max_balance');

		if($status)
		{
			//костыль: не выдает апи кошельки без специального запроса
			if($status == 'apiHalf')
				$statusCond = "AND `api_token`!='' AND `status`='".Account::STATUS_HALF."'";
			elseif($status == 'apiFull')
				$statusCond = "AND `api_token`!='' AND `status`='".Account::STATUS_FULL."'";
			elseif($status == 'full')
				$statusCond = "AND `api_token`='' AND `status`='".Account::STATUS_FULL."'";
			else
			{
				if(cfg('tokenAccountsAsSimple'))
					$statusCond = "AND `status`='".Account::STATUS_HALF."'";
				else
					$statusCond = "AND `status`='$status' AND `api_token`=''";

			}
		}
		else
		{
			if(cfg('tokenAccountsAsSimple'))
				$statusCond = "";
			else
				$statusCond = "AND `api_token`=''";
		}


		$additionalCondition = ($withNotChecked) ?
			"`error` IN('', '".self::ERROR_IDENTIFY."', '".self::ERROR_CHECK."') AND `limit_in` > $minBalance" :
			"`error` = '' AND `date_check` > $dateCheck AND `limit_in` >= $maxBalance";

		$accounts = Account::model()->findAll([
			'condition'=>"
					`type`='".Account::TYPE_IN."'
					AND `user_id`=0
					AND `client_id`='$clientId'
					AND `date_used` = 0
					AND `enabled`=1
					AND $additionalCondition
					$statusCond
				",
			'order'=>"`limit_in` DESC",
		]);

		/**
		 * @var Account[] $accounts
		 */

		$result = [];

		$criticalMode = config('criticalMode');

		foreach($accounts as $account)
		{
			//если мод критический то только критические кошельки
			if($criticalMode)
			{
				if(!$account->isCritical)
					continue;
			}

			$result[] = $account;
		}

		return $result;
	}

	/**
	 * получить транзитные акки для перевода с входящих
	 * $groupId=false  - для статистики
	 * @param int $clientId
	 * @param bool $groupId
	 * @param bool $withNotChecked
	 * @return self[]
	 */
	public static function getTransitAccounts($clientId, $groupId=false, $withNotChecked=false)
	{
		$dateCheck = time() - config('order_account_check_interval');
		$minBalance = cfg('min_balance');
		$maxBalance = config('transit_max_balance') - $minBalance;

		$groupCond = ($groupId) ? "AND `group_id` = '$groupId'" : '';

		// если $withNotChecked то непроверенные тоже считаем в общий котел
		$additionalCondition = ($withNotChecked) ?
			"`error` IN('', '".self::ERROR_IDENTIFY."', '".self::ERROR_CHECK."')" :
			"`error` = '' AND `date_check` > $dateCheck AND `balance` < $maxBalance";

		$walletsCount = cfg('walletsCountMax') - 1;	//максимум уников

		$accounts = Account::model()->findAll(array(
			'condition' => "
				`type`='" . Account::TYPE_TRANSIT . "'
				AND `client_id` = '$clientId'
				AND `is_old`=0
				$groupCond
				AND $additionalCondition
				AND `limit_in` > $minBalance
				AND `enabled`=1
				AND `wallets_count`<$walletsCount
			",//test поставить `limit_in` DESC
			'order' => "`limit_in` ASC",//"`commission` ASC,`wallets_count` ASC"
		));

		/**
		 * @var Account[] $accounts
		 */

		//dayLimitCondition
		if(cfg('dayLimitEnabled'))
		{
			foreach($accounts as $key=>$account)
			{
				if($account->dayLimit < 0)
					unset($accounts[$key]);
			}
		}

		return  $accounts;
	}

	/**
	 * получить исходящие акки для перевода с транзитных
	 * @param int $clientId
	 * @param int $groupId
	 * @param bool $withNotChecked учесть непроверенные(для добавления)
	 * @return self[]
	 */
	public static function getOutAccounts($clientId, $groupId=0, $withNotChecked=false)
	{
		$dateCheck = time() - config('order_account_check_interval');
		$minBalance = cfg('min_balance');
		$maxBalance = config('out_max_balance') - $minBalance;

		$groupCond = ($groupId) ? "AND `group_id` = '$groupId'" : '';

		// если $withNotChecked то непроверенные тоже считаем в общий котел
		$additionalCondition = ($withNotChecked) ?
			"`error` IN('', '".self::ERROR_IDENTIFY."', '".self::ERROR_CHECK."')" :
			"`error` = '' AND `date_check` > $dateCheck";

		//чтобы лишние исходящие не добавлялись
		//$balanceCond = ($withNotChecked) ? "" : "AND `balance`<=$maxBalance";
		$balanceCond = "AND `balance`<$maxBalance";

		$walletsCount = cfg('walletsCountMax')-1;	//максимум уников

		$accounts = Account::model()->findAll(array(
			'condition' => "
				`type`='" . self::TYPE_OUT . "'
				AND `is_old`=0
				AND `client_id` = '$clientId'
				AND `limit_in` > $minBalance
				AND `enabled`=1
				AND `wallets_count`<$walletsCount
				AND $additionalCondition
				$groupCond
				$balanceCond
			",//test поставить `limit_in` DESC
			'order' => "`balance` ASC, `limit_in` ASC"//"`commission` ASC, `wallets_count` ASC"
		));

		/**
		 * @var Account[] $accounts
		 */

		//dayLimitCondition
		if(cfg('dayLimitEnabled'))
		{
			foreach($accounts as $key=>$account)
			{
				if($account->dayLimit < 0)
					unset($accounts[$key]);
			}
		}

		return $accounts;
	}

	/**
	 * текущие кошельки (IN) в работе у менеджеров
	 *
	 * @param int $clientId
	 * @param bool $withError false - использовать если надо выбрать кошельки без ошиок чтобы посчитать общий лимит
	 * @param string $order
	 * @return self[]
	 */
	public static function getCurrentInAccounts($clientId = 0, $withError=true, $order='')
	{
		$clientCond = "";

		if($clientId)
			$clientCond = " AND `client_id`='$clientId'";

		$errorCond = "";

		if(!$withError)
			$errorCond = " AND `error`=''";

		//оптимизация
		//если кошельки неделю не чекались нас не интересуют
		$dateCheck = time() - 3600*24*7;

		return self::model()->findAll(array(
			'condition'=>"
				`date_check` > $dateCheck
				AND `type`='".self::TYPE_IN."' and `user_id`>0 and `date_used`=0
				AND `enabled` = 1
				$clientCond
				$errorCond
				",
			'order'=>($order) ? $order : "`id`",
		));
	}

	/*
	 * вернет текущий прокси если есть
	 */
	public function getCurrentProxy()
	{
		$file = DIR_ROOT.'protected/components/QiwiBot/users/'.trim($this->login, '+').'/config.json';

		if(file_exists($file))
		{
			$content = file_get_contents($file);
			$json = json_decode($content, 1);
			return $json['proxy'];
		}
		else
			return '';
	}


	/*
	 * если авторизация удалась то true
	 * иначе false и пишет инф в $lastError $lastErrorCode
	 *
	 */
	public function getBot()
	{
		//TODO: убрать когда нужен будет бот
		//return false;

		$debug = false;
		//$debug = true;

		if(!$this->botObj)
		{
			//TODO: после теста вернуть смену прокси
//			if(!$this->isActualProxy())
//			{
//				if($this->proxy)
//					toLogRuntime('смена прокси у '.$this->login);
//
//				if($this->proxy = $this->getNewProxy())
//					self::model()->updateByPk($this->id, array('proxy'=>$this->proxy));
//				else
//				{
//					toLogError('ошибка получения прокси '.$this->login.', '.self::$lastError);
//					return false;
//				}
//
//			}

			if(!$this->isActualBrowser())
			{
				if($this->browser)
					toLogRuntime('смена браузера у '.$this->login);

				$this->browser = $this->getNewBrowser();

				self::model()->updateByPk($this->id, array('browser'=>$this->browser));
			}

			if(!$this->proxy or !$this->browser)
				toLogError($this->login.': не указан прокси или браузер', true);

			$additional = array();

			if($this->botWithoutAuth)
				$additional['withoutAuth'] = true;

			$bot = new QiwiBot($this->login, $this->pass, $this->proxy, $this->browser, $additional);

			if(!$bot->error)
			{
				$this->botObj = $bot;
			}
			else
			{
				$this->botError = $bot->error;
				$this->botErrorCode = $bot->errorCode;
				$this->botContent = $bot->lastContent;

				if($this->botErrorCode === QiwiBot::ERROR_CAPTCHA)
				{
					toLogRuntime($this->login.': распознавание капчи');

					$recaptchaCfg = cfg('recaptcha');

					$googleSiteKey = $bot->getGoogleCaptchaKey();

					$captchaId = Tools::anticaptcha('recaptcha', array(
						'step'=>'send',
						'googleApiKey'=>$googleSiteKey,
						'pageUrl'=>'https://qiwi.com',
					));

					$timeStart = time();

					if($captchaId)
					{
						sleep(20);

						$captchaCode = false;

						while(time() - $timeStart < $recaptchaCfg['maxTimeDefault'])
						{
							if($captchaCode = Tools::anticaptcha('recaptcha', array(
								'step'=>'get',
								'captchaId'=>$captchaId,
							)))
							{
								toLogRuntime($this->login.': капча распознана');
								break;
							}
							elseif(Tools::$error == Tools::ANTICAPTCHA_NOT_READY)
							{
								//sleep($recaptchaCfg['sleepTime']);
							}
							else
							{
								$this->botError = $this->login.' ошибка распознавания капчи: '.Tools::$error;
								toLogError('Account::getBot(): '.$this->botError);
							}

							sleep($recaptchaCfg['sleepTime']);
						}

						if($captchaCode)
						{
							$this->botError = '';
							$this->botErrorCode = '';

							$additional['captchaCode'] = $captchaCode;

							$bot = new QiwiBot($this->login, $this->pass, $this->proxy, $this->browser, $additional);


							if(!$bot->error)
								$this->botObj = $bot;
							else
							{
								$this->botError = $bot->error;
								$this->botErrorCode = $bot->errorCode;
							}
						}
						else
						{
							$this->botError = $this->login.' ошибка распознавания капчи (затрачено '.(time() - $timeStart).' сек) '.Tools::$error;
						}

					}
					else
					{
						$this->botError = $this->login.' captchaId  не получен от сервиса антикапчи ('.Tools::$error.')';
						toLogError('Account::getBot()_1: '.$this->botError);
					}
				}

				if($this->botErrorCode === QiwiBot::ERROR_BAN)
				{

					Account::model()->updateByPk($this->id, array('error'=>self::ERROR_BAN));

					if(preg_match('!Неверный логин или пароль!isu', $this->botError))
						Account::model()->updateByPk($this->id, array('comment'=>'Неверный логин или пароль'));

					if($this->error != self::ERROR_BAN)
					{
						toLogError('забанен'.$this->login.': '.$this->botError);

						$this->noticeManager('Внимание! Забанен кошелек '.$this->login);
					}
				}
				elseif($this->botErrorCode === QiwiBot::ERROR_PASSWORD_EXPIRED)
				{

					self::model()->updateByPk($this->id, array('error'=>self::ERROR_PASSWORD_EXPIRED));

					Account::model()->updateByPk($this->id, array('comment'=>'Истек пароль'));

					if($this->error != self::ERROR_PASSWORD_EXPIRED)
					{
						toLogError('забанен истек пароль на '.$this->login.': '.$this->botError);

						$this->noticeManager('Внимание! Забанен кошелек '.$this->login);
					}
				}
				elseif($this->botError)
					toLogError( $this->login.' Account::getBot()_2: '.$this->botError.' (errorCode='.$this->botErrorCode.') proxy='.$this->proxy);
			}
		}

		//записать дату последнего успешного запроса

		if($this->botObj)
			self::model()->updateByPk($this->id, array('date_last_request'=>time()));

		return $this->botObj;
	}

	/*
	 * возвращает случайный прокси из списка группы
	 */
	public function getNewProxy()
	{
		if($model = AccountProxy::getGoodProxy($this->client_id, $this->group_id))
		{
			if($this->getClient()->personal_proxy)
			{
				toLog('link new proxy '.$model->id.' = '.$this->login);
				Proxy::model()->updateByPk($model->id, ['account_id'=>$this->id]);
				$this->proxy = $model->getStr();
			}

			return $model->getStr();
		}
		else
		{
			self::$lastError = $this->login.': '.AccountProxy::$lastError;
			return false;
		}
	}

	/*
	 * возвращает случайный браузер из списка группы
	 */
	public function getNewBrowser()
	{
		$browsers = AccountBrowser::model()->findAll();

		$browser = $browsers[array_rand($browsers)];

		return trim($browser->value);
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

		//todo: убрать
		if($this->proxy)
			return true;

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

	private function isActualBrowser()
	{
		if(!$this->browser)
			return false;

		if(AccountBrowser::model()->find("`value`='{$this->browser}'"))
			return true;
		else
			return false;
	}

	/*
	 * вернет группу с минимальным количеством кошельков типа $type
	 */
	public static function getGroupIdforAdd($clientId, $type)
	{
		$countArr = array();

		$dateCheckMin = time() - 3600*24*2;

		foreach(self::getGroupArr() as $id=>$arr)
			$countArr[$id] = Account::model()->count("`date_check`>$dateCheckMin AND `client_id`=$clientId and `group_id`=$id and `type`='$type' and `error` IN('', '".self::ERROR_IDENTIFY."')");

		$resultArr = array_keys($countArr, min($countArr));

		return $resultArr[0];
	}

	/*
	 * todo: сделать у каждого клиента свои группы, переделать везде где эта функция исользуется
	 * возвращает массив с группами(разделение кошельков и проксей на отдельные формации для минимизации банов)
	 */
	public static function getGroupArr()
	{
		return array(
			'1' => array(),
			'2' => array(),
			'3' => array(),
			'4' => array(),
			'5' => array(),
		);
	}

	public function getDateAddStr()
	{
		if ($this->date_add)
			return date('d.m.Y H:i', $this->date_add);
	}

	/*
	 * есть ли на кошельке кражи
	 */
	public function getIsRat()
	{
		$models = $this->getTransactionsManager();

		foreach($models as $model)
		{
			if($model->is_rat)
			{
				return true;
				break;
			}
		}
	}


	/*
	 * return true|false
	 * return null - если чтото пошло не так
	 */
	public static function accountExist($login)
	{
		$sender = new Sender();
		$sender->followLocation = false;
		$sender->timeout = 10;

		$cfg = cfg('accountAdd');

		foreach($cfg['panels'] as $panelName=>$url)
		{
			$url = str_replace('{login}', trim($login, '+'), $url);

			$content = $sender->send($url);

			if($content and $json = json_decode($content, 1))
			{
				if(!$json['errorCode'] and !$json['error'])
				{
					if($json['result'] === 'true')
					{
						self::$lastError = 'exists in '.$panelName;
						return true;
					}
					elseif($json['result'] === 'false')
						return false;
					else
					{
						self::$lastError = 'unknown response: '.$json['result'];
						return null;
					}
				}
				else
				{
					if($json['errorCode'] == 'debug_mode')
						self::$lastError = $panelName.' in debug mode';
					else
						self::$lastError = 'something wrong: error='.$json['error'].' (errorCode='.$json['errorCode'].')';

					return null;
				}
			}
			else
			{
				self::$lastError = 'error content from '.$panelName.': '.$content;
				return null;
			}
		}

		//если панелей нет в конфиге
		return false;
	}

	//все аккаунты с ошибками за выбранный период
	//todo: сделать даты ошибок у аккаунтов - дата когда была установлена ошибка
	/**
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return Account[]
	 */
	public static function getErrorAccounts($timestampStart, $timestampEnd)
	{
		$timestampStart *= 1;
		$timestampEnd *= 1;

		$minBalance = cfg('min_balance');

		$result = self::model()->findAll(array(
			'condition'=>"
				`error` NOT IN ('not_null_balance', 'old', '')
				AND `date_check`>=$timestampStart AND `date_check`<$timestampEnd
				AND `enabled` = 1
			",
			'order'=>"`date_check` DESC",
		));

		return $result;
	}

	public function getEmail()
	{
		if($this->email_id)
			return AccountEmail::model()->findByPk($this->email_id);
		else
			return false;
	}

	public function getIsEmailStr()
	{
		if($this->is_email)
			return '<font color="green">есть</font>';
		else
			return '<font color="red">нет</font>';
	}

	/**
	 * ищет активные входящие кошельки с полным идентом в цепочке, возвращает true|false (для выделения цепочки  на странице добавления кошельков )
	 * @param int $clientId
	 * @param int $groupId
	 * @return bool
	 */
	public static function findFullInGroup($clientId, $groupId)
	{
		if(self::model()->find("
			`status`='full'
			AND `client_id`='$clientId'
			AND `group_id`='$groupId'
			AND `type`='in'
			AND `date_used`=0
			AND `error`=''
			AND `enabled` = 1
		"))
			return true;
		else
			return false;
	}

	/**
	 * @return AccountReserve|bool
	 */
	public function getReserve()
	{
		if($model = AccountReserve::model()->find("`account_id`='{$this->id}'"))
			return $model;
		else
			return false;
	}

	/**
	 * сумма зарезервированного под приход
	 */
	public function getReserveAmount()
	{
		$result = 0;

		if($model = AccountReserve::model()->find("`account_id`='{$this->id}'"))
			$result = $model->amount;

		return $result;
	}

	/**
	 * @return int
	 */
	public function getReserveDate()
	{
		$result = 0;

		if($model = AccountReserve::model()->find("`account_id`='{$this->id}'"))
			$result = $model->date_add;

		return $result;
	}

	/**
	 * резервирует на кошельке сумму под приход
	 * @param $amount float может быть больше или меньше нуля
	 * @return bool
	 */
	public function reserveAmount($amount)
	{
		$amount = $amount*1;

		if($this->type !== self::TYPE_IN)
		{
			self::$lastError = 'попытка зарезервировать на '.$this->type.'-кошельке: '.$this->login;
			return false;
		}

		if($this->error)
		{
			self::$lastError = 'попытка зарезервировать на кошельке с ошибкой: '.$this->login;
			return false;
		}

		if($this->date_used)
		{
			self::$lastError = 'попытка зарезервировать на уже использованном кошельке: '.$this->login;
			return false;
		}

		if(!$model = AccountReserve::model()->find("`account_id`='{$this->id}'"))
		{
			$model = new AccountReserve;
			$model->scenario = AccountReserve::SCENARIO_ADD;
			$model->account_id = $this->id;
			$model->amount = 0;
		}

		$model->amount += $amount;

		if($model->amount <= 0)
			$model->amount = 0;


		if($model->save())
		{
			//выключить таймер ухода в отстойник
			//повысить приоритет проверки
			if($model->amount > 0)
			{
				self::model()->updateByPk($this->id, array(
					'date_out_of_limit'=>0,
					'check_priority'=>self::PRIORITY_STORE,
				));
			}

			return true;
		}
		else
			return false;
	}

	/**
	 * вернуть в базу кошелек юзера
	 * @return bool
	 */
	public function returnInToFree()
	{
		if(
			$this->type == self::TYPE_IN
			and $this->amount_in == 0
			and $this->user_id > 0
			and $this->balance == 0
			and $this->date_used == 0
			and $this->date_out_of_limit == 0
			and !$this->error
		)
		{
			foreach($this->getTransactions() as $transaction)
			{
				if($transaction->status != Transaction::STATUS_ERROR)
				{
					self::$lastError = 'на кошельке имеются успешные платежи';
					return false;
				}
			}

			self::model()->updateByPk($this->id, array(
				'user_id'=>0,
				'check_priority'=>0,
				'label'=>'',
				'date_pick'=>0,
			));

			//обнулить резервы
			$this->reserveAmount(-$this->getReserveAmount());

			return true;
		}
		else
		{
			self::$lastError = 'не все требования к аккаунту выполнены';
			return false;
		}
	}

	/**
	 * обновить резерв у входящего
	 * получить все входящие платежи на кошелек, вычесть суммы из резерва с даты резерва
	 * @return bool
	 */
	private function updateReserve()
	{
		if($this->type == self::TYPE_IN and $this->getReserveAmount())
		{
			$reserve = $this->getReserve();

			$transactions = Transaction::model()->findAll(array(
				'condition' => "
					`type`='" . Transaction::TYPE_IN . "'
					AND `account_id`='{$this->id}'
					AND `status`='".Transaction::STATUS_SUCCESS."'
					AND `date_add`>{$reserve->date_add}
					AND `error`=''
					",
				'order' => "`date_add` ASC",
			));

			if($transactions)
			{
				$trans = array();

				foreach($transactions as $trans)
				{
					$reserve->amount -= $trans->amount;
				}

				if($reserve->amount <= 0)
					$reserve->amount = 0;

				$reserve->date_add = $trans['date_add'];
				return $reserve->save();
			}
		}

		return true;
	}

	/**
	 * добавить кошельки по приоритету необходимости
	 * todo: добавлять только в те группы в которые нужно
	 * @return null
	 */
	public static function startAutoAdd()
	{
		$threadName = 'accountsAutoAdd';

		if(!Tools::threader($threadName))
			die('поток уже запущен');

		if(!self::autoAddCount())
		{
			//toLogError('недостаточно кошельков для авто-добавления');
			return 0;
		}

		$clientStopList = array();
		//$clientStopList = array(11,13,16);

		//todo: сделать не по лимиту а по кол-ву оставшихся кошельков(как то по умному сбалансировать)
		$addLimit = 1000;	//при каком лимите начинать добавлять акки
		$addInCount = 1;	//если 0 то добавляет разницу между  нормальным значением и текущим (10 - 2)
		$addTrCount = 1;	//за раз в каждую группу
		$addOutCount = 1;	//за раз в каждую группу

		$groupArr = Account::getGroupArr();

		$clients = array_reverse(Client::getActiveClients());

		//исходящие и транзитные
		$typeArr = array(self::TYPE_OUT, self::TYPE_TRANSIT, self::TYPE_IN);

		$result = array();

		foreach($typeArr as $type)
		{
			foreach($clients as $client)
			{
				/**
				 * @var Client $client
				 */

				if(in_array($client->id, $clientStopList) !== false)
					continue;

				if(!$client->pick_accounts)
					continue;

				//приоритет исходящие, транзитные
				if($type == self::TYPE_OUT or $type == self::TYPE_TRANSIT)
				{
					foreach($groupArr as $groupId=>$group)
					{
						$limit = ($type == self::TYPE_OUT) ?
							$client->getLimitOut($groupId, true) :
							$client->getLimitTransit($groupId, true);

						if($limit <= $addLimit)
							$result[$limit] = array(
								'type'=>$type,
								'clientId'=>$client->id,
								'groupId'=>$groupId,
								'count'=>($type == self::TYPE_TRANSIT) ? $addTrCount : $addOutCount,
							);
					}
				}
				elseif($type == self::TYPE_IN)
				{
					ksort($result);

					$inAccounts = Account::getFreeInAccounts($client->id, null, true);

					$inCount = ($addInCount) ? $addInCount : cfg('in_warn_count') - count($inAccounts);

					if(count($inAccounts) < cfg('in_warn_count'))
						$result[] = array(
							'type'=>$type,
							'clientId'=>$client->id,
							'groupId'=>0,
							'count'=>$inCount,
						);
				}
			}
		}

		$autoAddAccounts = self::getAutoAddAccounts();


		if(!$result)
			echo '<br>нечего добавлять';

		foreach($result as $arr)
		{
			$count = $arr['count'];

			unset($arr['count']);

			$params = $arr;

			$phonesStr = '';
			$addCount = 0;

			if(!$autoAddAccounts)
				break;


			foreach($autoAddAccounts as $key=>$autoAddAccount)
			{
				$params['phones'] = "{$autoAddAccount['login']}\t{$autoAddAccount['pass']}";

				if(self::addMany($params))
				{
					echo '<br>добавлен кошелек: '.Tools::arr2Str($params);
					unset($autoAddAccounts[$key]);
					$addCount++;
				}
				else
				{
					if(self::$lastError)
					{
						//todo: костыль конечно но хоть как то
						if(preg_match('!login already exist!', self::$lastError))
						{
							toLogError('ошибка при автодобавлении, удаляем существующий кошелек');
							unset($autoAddAccounts[$key]);
							self::$lastError = '';
						}
						else
						{
							toLogError('ошибка при автодобавлении ('.self::$lastError.') : '.Tools::arr2Str($params));
							break 2;
						}

					}
					else
						toLogError('AutoAdd: ошибка добавления кошелька: '.Tools::arr2Str($params));

				}

				if($addCount >= $count)
					break;
			}


		}

		$autoAddContent = '';

		//то что осталось от запаса перезаписать
		foreach($autoAddAccounts as $account)
			$autoAddContent .= "{$account['login']}\t{$account['pass']}\t{$account['api_token']}".PHP_EOL;

		self::editAutoAddFile($autoAddContent);

		return null;
	}

	/*
	 * добавляем коши массово из файла автодобавления вручную вместо ввода данных с формы
	 */
	public static function addAccountsFromLimitTable($params)
	{
		session_write_close();

		if(config('autoAddEnabled'))
		{
			self::$lastError = 'Отключите автодобавление перед ручным добавлением';
			return false;
		}

		$threadName = 'addAccountsFromLimitTable';

		if(!Tools::threader($threadName))
			die('поток уже запущен');

		if(!self::autoAddCount())
		{
			//toLogError('недостаточно кошельков для добавления');
			return 0;
		}

		foreach($params as $clientId=>$param)
		{
			foreach($param as $groupId=>$arr)
			{
				foreach($arr as $type=>$countToAdd)
				{
					if($countToAdd > 0)
					{

						$result[] = [
							'type'=>$type,
							'clientId'=>$clientId,
							'groupId'=>$groupId,
							'count'=>$countToAdd,
						];
					}
				}
			}
		}

		$manualAddAccounts = self::getAutoAddAccounts();

		//проверка чтобы аккаунтов не существовало в базе, убирает костыль как в автодобе
		foreach($manualAddAccounts as $key=>$account)
		{
			if(Account::modelByAttribute(['login'=>$account['login']]))
				unset($manualAddAccounts[$key]);
		}

		if(!$result)
		{
			self::$lastError = 'Нечего добавлять';
			return false;
		}

		$resultStr = '';

		foreach($result as $arr)
		{
			$count = $arr['count'];

			unset($arr['count']);

			$params = $arr;

			$addCount = 0;

			if(!$manualAddAccounts)
				break;

			foreach($manualAddAccounts as $key=>$manualAddAccount)
			{
				$params['phones'] = "{$manualAddAccount['login']}\t{$manualAddAccount['pass']}\t{$manualAddAccount['api_token']}";

				if(self::addMany($params))
				{
					$resultStr .= 'добавлено: 1 '.$params['type']. ' аккаунтов to '.Client::model()->findByPk($params['clientId'])->name.' (group '.$params['groupId'].') '.$manualAddAccount['login'].'<br>';
					unset($manualAddAccounts[$key]);
					$addCount++;
				}
				else
				{
					if(self::$lastError)
					{
						toLogError('ошибка при автодобавлении ('.self::$lastError.') : '.Tools::arr2Str($params));
						break;
					}
					else
					{
						toLogError('AutoAdd: ошибка добавления кошелька: '.Tools::arr2Str($params));
						break;
					}
				}

				if($addCount >= $count)
					break;
			}
		}

		$manualAddContent = '';

		//то что осталось от запаса перезаписать
		foreach($manualAddAccounts as $account)
			$manualAddContent .= "{$account['login']}\t{$account['pass']}\t{$account['api_token']}".PHP_EOL;

		self::editAutoAddFile($manualAddContent);

		return $resultStr;
	}

	/**
	 * изменяет файл autoAddFile, если передан $content, возвращает содержимое
	 * првоеряет на дубли в базе перед сохранением
	 * @param string|bool $content
	 * @return string
	 */
	public static function editAutoAddFile($content = false)
	{
		$file = cfg('autoAddFile');

		if($content !== false)
		{
			$accounts = self::getAutoAddAccounts($content);

			$forSave = '';

			foreach($accounts as $key=>$account)
			{
				if(!Account::modelByAttribute(['login'=>$account['login']]))
					$forSave .= "{$account['login']}\t{$account['pass']}\t{$account['api_token']}\n";
			}

			if(file_put_contents($file, $forSave) === false)
			{
				self::$lastError = 'ошибка записи в файл';
				toLogError(self::$lastError);
			}

		}

		clearstatcache(true, $file);

		return file_get_contents($file);
	}

	public static function autoAddCount()
	{
		$content = file_get_contents(cfg('autoAddFile'));

		if(preg_match_all(cfg('regExpAccountAdd'), $content, $matches))
		{
			return count($matches[1]);
		}
		else
			return 0;
	}

	public static function getAutoAddAccounts($content = false)
	{
		$result = array();

		if(!$content)
			$content = file_get_contents(cfg('autoAddFile'));

		if(preg_match_all(cfg('regExpAccountAdd'), $content, $matches))
		{
			foreach($matches[1] as $key=>$login)
				$result[] = array('login'=>$login, 'pass'=>$matches[2][$key],'api_token'=>$matches[3][$key]);
		}

		return $result;
	}

	/**
	 * все платежи из бд по аккаунту (сортировка по убыванию даты)
	 * @param int|bool $timestampFrom
	 * @param int|bool $timestampTo
	 * @return Transaction[]
	 */
	public function getAllTransactions($timestampFrom = 0, $timestampTo = 0)
	{
		$timestampFrom *= 1;
		$timestampTo *= 1;

		$dateCond = '';

		if($timestampFrom)
		{
			$dateCond = " AND `date_add` >= $timestampFrom";

			if($timestampTo)
				$dateCond .= " AND `date_add` < $timestampTo";
		}
		elseif($timestampTo)
			$dateCond = " AND `date_add` < $timestampTo";


		$result = Transaction::model()->findAll([
			'condition'=>"`account_id`='{$this->id}'".$dateCond,
			'order'	=>	"`date_add` DESC",
		]);

		return $result;
	}

	/**
	 * блокирует кошельки клиента в выбранной цепочке
	 * пишет в self::$someData['msg'] результат в случае успеха
	 *
	 * @param $clientId
	 * @param $groupId
	 * @return bool
	 */
	public static function banGroup($clientId, $groupId=0, $withCleanWallets)
	{
		$config = array(
			'error'=>'ban',
			'comment'=>'отключен админом',
			'countMax'=>100,
		);

		$clientId *= 1;
		$groupId *= 1;

		if(!$clientId)
		{
			self::$lastError = 'не указан clientId';
			return false;
		}

		$limitHalf = config('account_in_limit');
		$limitFull = config('account_in_limit_full');

		$cleanWalletsCond = '';

		if($withCleanWallets)
			$cleanWalletsCond = "AND `date_used` = 0";
		else
			$cleanWalletsCond = "AND `limit_in`<$limitHalf";

		if($groupId)
			$groupCond = "AND `group_id`=$groupId";
		else
			$groupCond = '';

		$accounts = Account::model()->findAll(array(
			'condition'=>"
				`client_id`=$clientId $groupCond
				AND `error` = '' AND `date_used` = 0
				AND `enabled`=1
				AND (`limit_in` > ".cfg('min_balance')." OR (`limit_in` <= ".cfg('min_balance')." AND `balance`>=".cfg('min_balance')."))
				AND `status` IN ('".self::STATUS_HALF."', '".self::STATUS_FULL."')
				$cleanWalletsCond
			",	//чтобы и кошельки с нулевым лимитом но с балансом тоже банились
			'limit'=>100,
		));
		/**
		 * @var Account[] $accounts
		 */

		/*
		echo "test\n\n";

		foreach($accounts as $account)
		{
			echo $account->login."\n\n";
		}
		die;
		*/

		if(count($accounts) > $config['countMax'])
		{
			self::$lastError = 'отключение не произведено, кошельков слишком много ('.count($accounts).')';
			return false;
		}

		/**
		 * @var Account[] $accounts
		 */

		if($accounts)
		{
			foreach($accounts as $account)
			{
				Account::model()->updateByPk($account->id, [
					'error'=>$config['error'],
					'comment'=>$config['comment'],
					'hidden'=>1,
				]);

				self::$someData['msg'] .= "<br>{$account->login} отключен";
				$logMsg = 'antiBan: отключен '.$account->login.' (clientId='.$clientId.', groupId='.$groupId.')';

				if($account->balance > cfg('min_balance'))
				{
					$logMsg .= ', '.formatAmount($account->balance, 0).' руб';
					self::$someData['msg'] .= ', '.formatAmount($account->balance, 0).' руб';
				}

				toLogRuntime($logMsg);
			}

			return true;
		}
		else
			self::$lastError = 'кошельков не найдено';

		return false;
	}

	/**
	 * максимальный лимит кошелька
	 * @return int
	 */
	public function getLimitMax()
	{
		if($this->status == self::STATUS_HALF)
			return config('account_in_limit_half');
		elseif($this->status == self::STATUS_FULL)
			return config('account_in_limit_full');
		else
			return 0;
	}

	/**
	 * если кошелек давно не проверялся то часть номера будет скрыта
	 * @return bool
	 */
	public function getIsOldCheck()
	{
		$interval = 3600*2;

		if($this->date_check < time() - $interval)
			return true;
		else
			return false;
	}

	public function getHiddenLogin()
	{
		if(YII_DEBUG)
			$login = $this->login;
		else
			$login = '...'.substr($this->login, 5);

		return $login;
	}

	public function getHiddenLoginStr()
	{
		$login = $this->getHiddenLogin();

		if($this->error)
		{
			return '<span class="dotted" title="ошибка на кошельке">'.$login.'</span>';
		}
		else
		{
			if($this->check_priority < self::PRIORITY_NOW)
				return '<span class="dotted" title="кошелек давно не проверялся, нажмите на кнопку Проверить сейчас, чтобы увидеть номер целиком">'.$login.'</span>';
			else
				return '<span class="dotted" title="кошелек скоро будет проверен, номер целиком будет показан после проверки">'.$login.'</span><br><marquee class="checking" behavior="scroll" direction="right">проверяется...</marquee>';
		}
	}


	/**
	 * тестирование новых функций для QiwiBot
	 * QiwiBotTest расширяет класс QiwiBot
	 * @return bool|QiwiBotTest
	 */
	public function getBotTest()
	{
		//TODO: убрать когда нужен будет метод
		return false;


		$debug = false;
		//$debug = true;

		if(!$this->botObj)
		{
			if(!$this->isActualProxy())
			{
				//if($this->proxy)
				//	toLogRuntime('смена прокси у '.$this->login);

				if($this->proxy = $this->getNewProxy())
					self::model()->updateByPk($this->id, array('proxy'=>$this->proxy));
				else
				{
					toLogError('ошибка получения прокси '.$this->login.'. '.self::$lastError);
					return false;
				}
			}

			if(!$this->isActualBrowser())
			{
				if($this->browser)
					toLogRuntime('смена браузера у '.$this->login);

				$this->browser = $this->getNewBrowser();

				self::model()->updateByPk($this->id, array('browser'=>$this->browser));
			}

			if(!$this->proxy or !$this->browser)
				toLogError($this->login.': не указан прокси или браузер', true);

			$additional = array();

			if($debug)
				$additional['testHeaderUrl'] = 'https://89.33.64.174/test.php';

			if($this->botWithoutAuth)
				$additional['withoutAuth'] = true;

			$bot = new QiwiBotTest($this->login, $this->pass, $this->proxy, $this->browser, $additional);

			if(!$bot->error)
				$this->botObj = $bot;
			else
			{
				$this->botError = $bot->error;
				$this->botErrorCode = $bot->errorCode;

				if($this->botErrorCode === QiwiBot::ERROR_CAPTCHA)
				{
					toLogRuntime($this->login.': распознавание капчи');

					$recaptchaCfg = cfg('recaptcha');

					$googleSiteKey = $bot->getGoogleCaptchaKey();

					$captchaId = Tools::anticaptcha('recaptcha', array(
						'step'=>'send',
						'googleApiKey'=>$googleSiteKey,
						'pageUrl'=>'https://qiwi.com',
					));

					$timeStart = time();

					if($captchaId)
					{
						sleep(20);

						$captchaCode = false;

						while(time() - $timeStart < $recaptchaCfg['maxTimeDefault'])
						{
							if($captchaCode = Tools::anticaptcha('recaptcha', array(
								'step'=>'get',
								'captchaId'=>$captchaId,
							)))
							{
								toLogRuntime($this->login.': капча распознана');
								break;
							}
							elseif(Tools::$error == Tools::ANTICAPTCHA_NOT_READY)
							{
								//sleep($recaptchaCfg['sleepTime']);
							}
							else
							{
								$this->botError = $this->login.' ошибка распознавания капчи: '.Tools::$error;
								toLogError('Account::getBot(): '.$this->botError);
							}

							sleep($recaptchaCfg['sleepTime']);
						}

						if($captchaCode)
						{
							$this->botError = '';
							$this->botErrorCode = '';

							$additional['captchaCode'] = $captchaCode;

							$bot = new QiwiBot($this->login, $this->pass, $this->proxy, $this->browser, $additional);


							if(!$bot->error)
								$this->botObj = $bot;
							else
							{
								$this->botError = $bot->error;
								$this->botErrorCode = $bot->errorCode;
							}
						}
						else
						{
							$this->botError = $this->login.' ошибка распознавания капчи (затрачено '.(time() - $timeStart).' сек) '.Tools::$error;
						}

					}
					else
					{
						$this->botError = $this->login.' captchaId  не получен от сервиса антикапчи ('.Tools::$error.')';
						toLogError('Account::getBot()_1: '.$this->botError);
					}
				}

				if($this->botErrorCode === QiwiBot::ERROR_BAN)
				{

					Account::model()->updateByPk($this->id, array('error'=>self::ERROR_BAN));

					if(preg_match('!Неверный логин или пароль!isu', $this->botError))
						Account::model()->updateByPk($this->id, array('comment'=>'Неверный логин или пароль'));

					if($this->error != self::ERROR_BAN)
						toLogError('забанен'.$this->login.': '.$this->botError);
				}
				elseif($this->botErrorCode === QiwiBot::ERROR_PASSWORD_EXPIRED)
				{

					self::model()->updateByPk($this->id, array('error'=>self::ERROR_PASSWORD_EXPIRED));

					Account::model()->updateByPk($this->id, array('comment'=>'Истек пароль'));

					if($this->error != self::ERROR_PASSWORD_EXPIRED)
						toLogError('забанен истек пароль на '.$this->login.': '.$this->botError);
				}
				elseif($this->botError)
					toLogError( $this->login.' Account::getBot()_3: '.$this->botError.' (errorCode='.$this->botErrorCode.') proxy='.$this->proxy);
			}
		}

		if($this->botObj)
			self::model()->updateByPk($this->id, array('date_last_request'=>time()));

		return $this->botObj;
	}



	/**
	 * @param QiwiBotTest|QiwiApi $bot
	 * @return bool
	 */
	public function convertKzt($bot)
	{
		$minKztBalance = cfg('kztTestMinBalance');	//минимальный баланс в тенге для конвертации его в рубли
		$paymentMinAmount = 20;
		$minKztError = 10;	//погрешность (вычитать из суммы конвертации тенге. (ровно никак бывает не сделать изза разной точности валют))
		$maxKztAmount = 67000;

		$kztBalance = $bot->getBalance(QiwiBotTest::CURRENCY_KZT);

		echo "kztBalance: ".$kztBalance.' ';

		if($kztBalance === false)
		{
			toLog('kztTest error get balance '.$this->login.': '.$bot->error);
			return false;
		}

		self::model()->updateByPk($this->id, [
			'balance_kzt'=>$kztBalance,
			'check_priority'=>($kztBalance >= $minKztBalance) ? self::PRIORITY_NOW : self::PRIORITY_BIG,
		]);


		$this->balance_kzt = $kztBalance;

		if($kztBalance < $minKztBalance)
			return true;

		toLogRuntime('convertKzt '.$this->login);

		$dayCount = $this->getTrUpdateDayCount();

		//для работы через апи и без
		if(isset($bot->token))
		{
			$timestampStart = time() - 3600*24*$dayCount - 3600;
			$transactions = $bot->getHistory($timestampStart);
		}
		else
			$transactions = $bot->getLastPayments($dayCount, true);

		if($transactions === false)
		{
			toLog('kztTest error get transactions '.$this->login);
			return false;
		}

		array_reverse($transactions);


		/*
		$rates = $bot->getRates();

		if(!$rates)
			return false;

		$rate = $rates['KZT_RUB'];

		echo "курс: $rate\n\n";
		*/

		if($kztBalance >= $minKztBalance)
		{
			//нужен чтобы не сбить массив при поиске конвертации
			$transactionArr = $transactions;

			foreach($transactions as $transaction)
			{
				if(Tools::timeIsOut(50))
					break;

				if($kztBalance < $minKztBalance)
					break;

				//если есть успешный входящий, несохраненный платеж,
				//то конвертируем в рубли и сохраняем id связанной транзакции
				if(
					$transaction['currency'] === QiwiBotTest::CURRENCY_KZT
					and $transaction['type'] === QiwiBot::TRANSACTION_TYPE_IN
					and $transaction['status'] === QiwiBot::TRANSACTION_STATUS_SUCCESS
					and $transaction['amount'] >= $paymentMinAmount
					and !TransactionKzt::model()->find("`account_id`={$this->id} AND `qiwi_id`='{$transaction['id']}'")	//если нет то еще не сконвертирована
				)
				{
					//возможно конвертация была но не сохранилась, пробуем найти
					$needTransCount = 0;
					$needTrans = false;

					foreach($transactionArr as $trans)
					{
						if(
							$trans['timestamp'] > $transaction['timestamp']
							and $trans['operationType'] === QiwiBot::OPERATION_TYPE_CONVERT
							and $trans['currency'] === QiwiBot::CURRENCY_RUB
							and $trans['currencyFrom'] == QiwiBot::CURRENCY_KZT
							and $trans['type'] === QiwiBot::TRANSACTION_TYPE_IN
							and $trans['status'] === QiwiBot::TRANSACTION_STATUS_SUCCESS
							and $trans['wallet'] == $this->login
							and !Transaction::model()->find("`account_id`={$this->id} AND `qiwi_id`='{$trans['id']}' AND `convert_id`>0")
							//отклонение в сумме не больше 10 проц
							and $trans['amountFrom'] >= $transaction['amount']*0.95 and $trans['amountFrom'] <= $transaction['amount']*1.05
							//разрыв между
							and $trans['timestamp'] - $transaction['timestamp'] <= 3600*24
						)
						{
							$needTransCount++;
							$needTrans = $trans;
						}
					}

					if($needTrans and $needTransCount == 1)
					{

						$modelKzt = new TransactionKzt;
						$modelKzt->scenario = TransactionKzt::SCENARIO_ADD;

						$modelKzt->account_id = $this->id;
						$modelKzt->type = $transaction['type'];
						$modelKzt->qiwi_id = $transaction['id'];
						$modelKzt->wallet = $transaction['wallet'];
						$modelKzt->amount = $transaction['amount'];
						$modelKzt->comment = $transaction['comment'];
						$modelKzt->status = $transaction['status'];
						$modelKzt->error = $transaction['error'];
						$modelKzt->date_add = $transaction['timestamp'];
						$modelKzt->commission = $transaction['commission'];

						if($modelKzt->save())
						{
							if(!$needTransModel = Transaction::model()->find("`account_id`={$this->id} AND `qiwi_id`='{$needTrans['id']}' AND `convert_id`=0"))
							{
								$needTransModel = new Transaction;
								$needTransModel->scenario = Transaction::SCENARIO_ADD;
								$needTransModel->client_id = $this->client_id;
							}

							$needTransModel->account_id = $this->id;
							$needTransModel->user_id = $this->user_id;
							$needTransModel->type = $needTrans['type'];
							$needTransModel->qiwi_id = $needTrans['id'];
							$needTransModel->wallet = $needTrans['wallet'];
							$needTransModel->amount = $needTrans['amount'];
							$needTransModel->comment = $needTrans['comment'];
							$needTransModel->status = $needTrans['status'];
							$needTransModel->error = $needTrans['error'];
							$needTransModel->date_add = $needTrans['timestamp'];
							$needTransModel->commission = $needTrans['commission'];
							$needTransModel->convert_id = $modelKzt->id;

							if($needTransModel->save())
								return true;
							else
								return false;
						}
						else
							return false;
					}

					$convertAmount = $transaction['amount'] - $minKztError;

					echo "convertAmount = $convertAmount\n\n";

					if($convertAmount > $maxKztAmount)
						$convertAmount = $maxKztAmount;

					if(isset($bot->token))
						$convertRub = $bot->convert(QiwiBotTest::CURRENCY_KZT, QiwiBotTest::CURRENCY_RUB, $convertAmount, config('kztRate'));
					else
						$convertRub = $bot->convert(QiwiBotTest::CURRENCY_KZT, QiwiBotTest::CURRENCY_RUB, $convertAmount);

					if($convertRub)
					{
						$kztBalance -= $convertAmount;

						sleep(5);

						if(isset($bot->token))
							$transactions1 = $bot->getHistory();
						else
							$transactions1 = $bot->getLastPayments(0, true);

						if($transactions1 === false)
						{
							toLog('kztTest error getLastPayments after success convert '.$this->login.', transaction_id='.$transaction['id']);
							return false;
						}

						if(!$transactions1)
							die('error1');

						foreach($transactions1 as $transaction1)
						{
							if(
								$transaction1['operationType'] === QiwiBot::OPERATION_TYPE_CONVERT
								and $transaction1['currency'] === QiwiBot::CURRENCY_RUB
								and $transaction1['type'] === QiwiBot::TRANSACTION_TYPE_IN
								and $transaction1['status'] === QiwiBot::TRANSACTION_STATUS_SUCCESS
								and $transaction1['currencyFrom'] == QiwiBot::CURRENCY_KZT
								and $transaction1['timestamp'] > $transaction['timestamp']
								and $transaction1['amount'] == $convertRub
								and $transaction1['wallet'] == $this->login
							)
							{
								//todo: учесть Ограничение на исходящие (как при обычном платеже)

								$modelKzt = new TransactionKzt;
								$modelKzt->scenario = TransactionKzt::SCENARIO_ADD;

								$modelKzt->account_id = $this->id;
								$modelKzt->type = $transaction['type'];
								$modelKzt->qiwi_id = $transaction['id'];
								$modelKzt->wallet = $transaction['wallet'];
								$modelKzt->amount = $transaction['amount'];
								$modelKzt->comment = $transaction['comment'];
								$modelKzt->status = $transaction['status'];
								$modelKzt->error = $transaction['error'];
								$modelKzt->date_add = $transaction['timestamp'];
								$modelKzt->commission = $transaction['commission'];

								if($modelKzt->validate())
								{
									$modelRub = new Transaction;

									$modelRub->scenario = Transaction::SCENARIO_ADD;

									$modelRub->account_id = $this->id;
									$modelRub->client_id = $this->client_id;

									$modelRub->user_id = $this->user_id;
									$modelRub->type = $transaction1['type'];
									$modelRub->qiwi_id = $transaction1['id'];
									$modelRub->wallet = $transaction1['wallet'];
									$modelRub->amount = $transaction1['amount'];
									$modelRub->comment = $transaction1['comment'];
									$modelRub->status = $transaction1['status'];
									$modelRub->error = $transaction1['error'];
									$modelRub->date_add = $transaction1['timestamp'];
									$modelRub->commission = $transaction1['commission'];

									if($modelRub->validate())
									{
										if($modelKzt->save())
										{
											$modelRub->convert_id = $modelKzt->id;

											if($modelRub->save())
											{
												return true;
											}
											else
											{
												toLog('kztTest ошибка сохранения modelRub: '.Tools::arr2Str($modelRub));
												return false;
											}
										}
										else
										{
											toLog('kztTest ошибка сохранения modelKzt: '.Tools::arr2Str($modelKzt));
											return false;
										}
									}
									else
									{
										toLog('kztTest ошибка валидации modelRub: '.Tools::arr2Str($modelRub));
										return false;
									}
								}
								else
								{
									toLog('kztTest ошибка валидации modelKzt: '.Tools::arr2Str($modelKzt));
									return false;
								}
							}
						}

						toLog('kztTest конвертация не найдена: '.$this->login);
						return false;
					}
					else
					{
						toLog('kztTest error convert '.$bot->error.' '.$this->login);

						if($bot->errorCode == QiwiBot::ERROR_NO_MONEY)
						{
							if($this->commission)
							{
								Account::model()->updateByPk($this->id, array(
									'commission'=>2,
								));

								toLogError('включена дополнительная комиссия при конвертации '.$this->login, false, true);
							}
							else
							{
								Account::model()->updateByPk($this->id, array(
									'commission'=>1,
								));

								toLogRuntime('включена комиссия при конвертации '.$this->login, false, true);
							}
						}

						return false;
					}
				}
			}

			return true;
		}
		else
			return true;

	}

	/**
	 * дневной лимит 100к (обход комсы)
	 * @return int
	 */
	public function getDayLimit()
	{
		$dateStart = strtotime(date('d.m.Y'));

		$info = Transaction::model()->findAll(array(
			'select'=>"SUM(`amount`) as 'amnt'",
			'condition'=>"
				`account_id`='{$this->id}'
				AND `status`='".Transaction::STATUS_SUCCESS."'
				AND `type`='".Transaction::TYPE_IN."'
				AND `date_add`>'$dateStart'
			",
		));

		$amount = $info[0]->amnt*1;

		$limit = self::DAY_LIMIT_MAX - $amount;

		if($limit > $this->limit_in)
			return $this->limit_in;
		else
			return $limit;
	}

	/**
	 * @return string
	 */
	public function getDayLimitStr()
	{
		$limit = $this->getDayLimit();
		$managerLimit = $this->limit_in  - cfg('account_in_safe_limit');

		//для менеджера чтобы он видел не ту сумму
		if($limit > $managerLimit)
			$limit = $managerLimit;

		if($limit < 30000)
			return '<span class="error">'.formatAmount($limit, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limit, 0).'</span>';
	}


	/**
	 * слив с error=ban  кошельков на один
	 * запись в self::$msg успешные выводы
	 *
	 * @param string $fromStr кошельки, по 1 на строку
	 * @param string $to кошелек 7864626236... куда слить
	 * @return int|false
	 */
	public static function transFromMany($fromStr, $to)
	{
		$result = 0;
		$maxAmount = 14500;

		if(!preg_match(cfg('wallet_reg_exp2'), $to, $res))
		{
			self::$lastError = 'неверный кошелек для слива';
			return false;
		}

		$to = '+'.$res[1];

		if(preg_match_all(cfg('wallet_reg_exp2'), $fromStr, $res))
		{
			$accounts = array();	//откуда переводить массив моделей
			/**
			 * @var Account[] $accounts
			 */

			foreach($res[1] as $login)
			{
				$login = '+'.$login;

				if($account = Account::model()->find("`login`='$login'"))
				{
					if(!in_array($account->error, array(self::ERROR_BAN, self::ERROR_LIMIT_OUT)))
					{
						self::$lastError = 'на аккаунте нет забана или перелива: '.$login;
						return false;
					}

					$accounts[] = $account;
				}
			}

			foreach($accounts as $account)
			{
				if($bot = $account->bot)
				{
					/**
					 * @var Qiwibot $bot
					 */

					$bot->isCommission = true;
					$bot->commission = 1;

					$balance = $bot->getBalance();

					if($balance === false)
					{
						self::$lastError = 'ошибка баланса '.$account->login;
						return $result;
					}

					if($balance >= cfg('min_balance'))
					{

						$amount = $balance;

						if($amount > 14500)
							$amount = 14500;

						if($sendAmount = $bot->sendMoney($to, $amount))
						{
							self::$msg .= '<br>перевод ' . $account->login.' => '.$to . ' (' . $sendAmount . ' руб)';
							$result += $sendAmount;
						}
						else
						{
							self::$lastError = 'ошибка перевода с '.$account->login.' '.$balance.'руб : '.$bot->error;
							return $result;
						}
					}

				}
				else
				{
					self::$lastError = 'ошибка кошелька '.$account->login.': '.Account::$lastError.' '.$account->botError;
					return $result;
				}
			}

			if(self::$msg)
				self::$msg .= '<br>';
		}

		return $result;

	}

	/**
	 * старые кошельки (добавлены больше 3 месяцев назад)
	 * @return self[]
	 */
	public static function getOldAccounts()
	{
		$interval = 3600*24*30*5;
		$limit = 500;

		return Account::model()->findAll(array(
			'condition' => "
				`error`=''
				AND `limit_in`>2
				AND `date_add` < ".(time() - $interval)."
				AND `date_used`=0 AND `is_old`=0
				AND `enabled`=1
			",
			'order' => "`date_add` ASC",
			'limit' => $limit,
		));
	}

	/**
	 * скрывает(или отображает) кошелек менеджера(на проверку не влияет)
	 * пишет в self::$msg сообщение об успехе
	 * @param int $id
	 * @param int $managerId
	 * @return bool
	 */
	public static function toggleHidden($id, $managerId)
	{
		if(!$account = Account::model()->findByPk($id))
		{
			self::$lastError = 'кошелек не существует';
			return false;
		}

		if($account->type != Account::TYPE_IN)
		{
			self::$lastError = 'неверный кошелек';
			return false;
		}

		/**
		 * @var self $account
		 */

		if($account->user_id != $managerId)
		{
			self::$lastError = 'это не ваш кошелек';
			return false;
		}

		if($account->hidden)
		{
			$value = 0;
			self::$msg = 'кошелек показан';
		}
		else
		{
			$value = 1;
			self::$msg = 'кошелек скрыт';
		}

		Account::model()->updateByPk($account->id, array('hidden'=>$value));

		return true;
	}

	/**
	 * @return string
	 */
	public function getDateLastRequestStr()
	{
		return ($this->date_last_request) ? date('d.m.Y H:i', $this->date_last_request) : '';
	}

	/*
	 * если авторизация удалась то true
	 * иначе false и пишет инф в $lastError $lastErrorCode
	 * @return QiwiBot
	 */
	public function getBotAntiCaptcha()
	{
		$debug = false;
		//$debug = true;

		if(!$this->botObj)
		{
			if(!$this->isActualProxy())
			{
				if($this->proxy)
					toLogRuntime('смена прокси у '.$this->login);

				if($this->proxy = $this->getNewProxy())
					self::model()->updateByPk($this->id, array('proxy'=>$this->proxy));
				else
				{
					toLogError('ошибка получения прокси '.$this->login.', '.self::$lastError);
					return false;
				}

			}

			if(!$this->isActualBrowser())
			{
				if($this->browser)
					toLogRuntime('смена браузера у '.$this->login);

				$this->browser = $this->getNewBrowser();

				self::model()->updateByPk($this->id, array('browser'=>$this->browser));
			}

			if(!$this->proxy or !$this->browser)
				toLogError($this->login.': не указан прокси или браузер', true);

			$additional = array();

			if($debug)
				$additional['testHeaderUrl'] = 'https://89.33.64.174/test.php';

			if($this->botWithoutAuth)
				$additional['withoutAuth'] = true;

			$bot = new QiwiBot($this->login, $this->pass, $this->proxy, $this->browser, $additional);

			if(!$bot->error)
			{
				$this->botObj = $bot;
			}
			else
			{
				$this->botError = $bot->error;
				$this->botErrorCode = $bot->errorCode;

				if($this->botErrorCode === QiwiBot::ERROR_CAPTCHA)
				{
					$captchaCode = self::getCaptchaAnswer();

					if($captchaCode)
					{
						$this->botError = '';
						$this->botErrorCode = '';

						$additional['captchaCode'] = $captchaCode;

						Tools::runtimeLog('antiCaptchaAuth');

						$bot = new QiwiBot($this->login, $this->pass, $this->proxy, $this->browser, $additional);

						if(!$bot->error)
							$this->botObj = $bot;
						else
						{
							$this->botError = $bot->error;
							$this->botErrorCode = $bot->errorCode;
						}

						Tools::runtimeLog('antiCaptchaAuth');
					}
					else
					{
						$this->botError = $this->login.' ошибка получения капчи из бд: code='.self::$lastErrorCode;
					}
				}

				if($this->botErrorCode === QiwiBot::ERROR_BAN)
				{

					Account::model()->updateByPk($this->id, array('error'=>self::ERROR_BAN));

					if(preg_match('!Неверный логин или пароль!isu', $this->botError))
						Account::model()->updateByPk($this->id, array('comment'=>'Неверный логин или пароль'));

					if($this->error != self::ERROR_BAN)
						toLogError('забанен'.$this->login.': '.$this->botError);
				}
				elseif($this->botErrorCode === QiwiBot::ERROR_PASSWORD_EXPIRED)
				{

					self::model()->updateByPk($this->id, array('error'=>self::ERROR_PASSWORD_EXPIRED));

					Account::model()->updateByPk($this->id, array('comment'=>'Истек пароль'));

					if($this->error != self::ERROR_PASSWORD_EXPIRED)
						toLogError('забанен истек пароль на '.$this->login.': '.$this->botError);
				}
				elseif($this->botError)
					toLogError( $this->login.' Account::getBot()_2: '.$this->botError.' (errorCode='.$this->botErrorCode.') proxy='.$this->proxy);
			}
		}

		//записать дату последнего успешного запроса

		if($this->botObj)
			self::model()->updateByPk($this->id, array('date_last_request'=>time()));

		return $this->botObj;
	}

	/**
	 * получить по апи от системы распознавания решенную капчу
	 */
	private static function getCaptchaAnswer()
	{
		$cfg = cfg('antiCaptchaApi');

		if($content = file_get_contents($cfg['url']) and $json = json_decode($content, true))
			return $json['result'];
		else
		{
			if($json)
				self::$lastErrorCode = $json['errorCode'];

			toLogError('ошибка получения капчи getCaptchaAnswer(): content='.$content);
		}
	}


	/**
	 * ставит  выбранным кошелькам: error = 'ban', comment='отключен админом'
	 * записывает в self::$msg успешно отключенные кошельки
	 * @var string $walletsStr
	 * @return bool
	 */
	public static function banMany($walletsStr)
	{
		if(preg_match_all('!(\d{11,12})!', $walletsStr, $res))
		{
			foreach($res[1] as $login)
			{
				$login = '+'.$login;

				if($account = Account::model()->find("`login`='$login'"))
				{
					/**
					 * @var Account $account
					 */

					Account::model()->updateByPk($account->id, array(
						'error'=>Account::ERROR_BAN,
						'comment'=>'отключен админом',
					));

					toLogRuntime('antiBan: отключен '.$account->login.' (clientId='.$account->client_id.', groupId='.$account->group_id.')');

					self::$msg .= "antiBan: отключен {$account->login} (".formatAmount($account->balance, 0).' руб)<br>';
				}
				else
				{
					self::$lastError = 'аккаунт не найден: '.$login;
					return false;
				}
			}
		}
		else
		{
			self::$lastError = 'кошельков не найдено';
			return false;
		}

		return true;
	}

	/**
	 * возвращает кол-во дней прошедших с момента последней проверки (чттобы передавать  в QiwiBot::getLastPayments())
	 *
	 */
	public function getTrUpdateDayCount()
	{
		$timestampMin = time() - 3600 * 24 * 30;
		$lastTransaction = $this->getLastTransaction(null, $timestampMin);

		if($lastTransaction)
		{
			$result = ceil((time() - $lastTransaction->date_add)/3600/24);
		}
		else
		{
			//если сегодня проверялся то 0
			if(date('d.m.Y', $this->date_check) == date('d.m.Y'))
			{
				//чтобы до полуночи не терялись платежи
				if(date('H') < 5)
					return 1;

				return 0;
			}

			$result = ceil((time() - $this->date_check)/3600/24);

		}

		return $result;
	}

	/**
	 * массовая проверка in-кошельков клиента у которых date_check > $date
	 * @param int $clientId
	 * @param string $date 12.12.2017 23:30
	 * @return int|false
	 */
	public static function massCheck($clientId, $date)
	{
		$limit = 200;
		$timestampMin = time() - 3600*24*7;	//дальше этой даты не брать кошельки
		$timestampMax = time() - 1800;

		$doneCount = 0;

		$clientId *= 1;

		$timestamp = strtotime($date);

		if(!Client::model()->findByAttributes(['id'=>$clientId, 'is_active'=>1]))
		{
			self::$lastError = 'client not found or inactive';
			return false;
		}

		if(!$clientId or !$timestamp)
		{
			self::$lastError = 'wrong clientId or date (min: '.date('d.m.Y H:i', $timestampMin).')';
			return false;
		}

		$models = self::model()->findAll([
			'select'=>"`id`,`check_priority`",
			'condition'=>"
				`client_id`=$clientId
				AND `type`='".self::TYPE_IN."'
				AND `error`=''
				AND `date_check`>$timestamp
				AND `check_priority`<".self::PRIORITY_NOW."
				AND `user_id`>0
				AND `date_check`<$timestampMax
				AND (`date_used`>$timestamp OR `date_used`=0)
				AND `enabled` = 1
			",
			'order'=>"`date_check` ASC",
			'limit'=>$limit,
		]);

		/**
		 * @var self[] $models
		 */

		foreach($models as $model)
		{
			self::model()->updateByPk($model->id, ['check_priority'=>self::PRIORITY_NOW, 'date_check'=>$timestamp]);
			$doneCount ++;
		}

		if($doneCount)
			self::$msg = 'check limit: '.$limit;

		return $doneCount;
	}

	/**
	 * проверить на бан по копилке
	 * все ок если вышел код либо пишет: приложения отключены
	 * если can't get wallet status то скорее всего пароль истек
	 * если отключить приложения в настройках безопасности то смс кидать не будет и не спалимся массовой проверкой
	 *
	 *
	 * @param string $login
	 * @return bool|null : true - забанен, false - не забанен, null - ошибка
	 */
	public static function checkBan($login)
	{
		$browsers = AccountBrowser::model()->findAll();
		/**
		 * @var AccountBrowser[] $browsers
		 */
		$browserModel = $browsers[array_rand($browsers)];
		$browser = $browserModel->value;

		//test персональные прокси для првоерки быстрее
		//$proxyCond = "`is_personal`=1";
		$proxyCond = "`category`=''";

		$proxies = array_slice(Proxy::getProxies($proxyCond), 0, 20);
		$proxyModel = $proxies[array_rand($proxies)];
		$proxy = $proxyModel->str;

		$sender = new Sender();
		$sender->followLocation = false;
		$sender->pause = 1;
		$sender->timeout = 30;
		$sender->browser = $browser;
		$sender->useCookie = false;

		$url = 'https://edge.qiwi.com/piggybox-service/sms/request';
		$sender->additionalHeaders = [
			'Accept: application/json',
			'Accept-Language: ru-RU,ru;q=0.8,en-US',
			'Accept-Encoding: gzip, deflate, br',
			'content-type: application/json',
			'origin: https://qiwi.me',
			'Referer: https://qiwi.me/action/goals',
		];

		$postData = '{"phone":'.trim($login, '+').'}';

		$content = $sender->send($url, $postData, $proxy);

		self::$lastErrorCode = '';

		if($json = json_decode($content, true))
		{
			//забанен
			if(isset($json['error']) and $json['error']['message'] == 'phone is unavailable')
				return true;
			elseif(
				isset($json['data']['code'])
				or (isset($json['error']['message']) and $json['error']['message'] == 'application disabled')
			)
				return false;
			else
			{
				if(isset($json['error']['message']))
				{
					if($json['error']['message'] == 'can\'t get wallet status')
						self::$lastErrorCode = BanChecker::ERROR_PASSWORD_EXPIRED;
					elseif($json['error']['message'])
						self::$lastErrorCode = BanChecker::ERROR_UNKNOWN;
				}

				self::$lastError = 'exception001: '.Tools::arr2Str($json);
				return null;
			}
		}
		else
		{
			self::$lastError = 'error json: '.$content.'('.$proxy.')';
			return null;
		}
	}

	/**
	 * @return bool
	 */
	public function getIsInOrder()
	{
		$orders = ManagerOrder::getActiveOrders();

		foreach($orders as $order)
		{
			$orderAccounts = $order->orderAccounts;

			foreach($orderAccounts as $orderAccount)
			{
				if($orderAccount->account_id == $this->id)
					return true;
			}
		}

		return false;
	}

	/**
	 * @param int $id
	 * @return self
	 */
	public static function modelByPk($id)
	{
		return self::model()->findByPk($id);
	}

	/**
	 * критический ли кошелек
	 * @return bool
	 */
	public function getIsCritical()
	{
		if(!$this->id)
			return false;

		return (AccountCritical::model()->find("`account_id`={$this->id}")) ? true : false;
	}

	/**
	 * максимальный допустимый баланс
	 * зависит от статуса
	 * @return float
	 */
	public function getMaxBalance()
	{
		if($this->status == self::STATUS_HALF)
			return config('max_balance_half');
		elseif($this->status == self::STATUS_FULL)
			return config('max_balance_full');
		else
			return config('max_balance_anonim');
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function modelByAttribute(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	public static function sessionExtend($thread)
	{

		$maxTime = 50;
		$threadCount = 10;
		$limitAtOnce = 30;
		$shuffleAccounts = true;

		//от 17 минут после проверки
		$dateStart = time() - 18*60;
		//до 5 минут после проверки
		$dateEnd = time() - 5*60;
		//если c момента прихода прошло больше n секунд, то перестаем поддерживать сессию
		$dateLastTransaction = time() - 3600*24;

		$threadName = 'sessionExtend'.$thread;

		if(!Tools::threader($threadName))
			die('уже запущено');


		$threadCond = Tools::threadCondition($thread, $threadCount);

		$models = Account::model()->findAll(array(
			'condition'=>"
				`date_last_request` > $dateStart
				AND `date_last_request` < $dateEnd
				AND `error`=''
				AND `date_used`=0
				AND `enabled`=1
				AND `api_token`=''
				AND $threadCond
			",
			'order'=>"`date_last_request` DESC",
			'limit'=>200,
		));

		/**
		 * @var Account[] $models
		 *
		 */

		$accounts = array();

		foreach($models as $model)
		{
			if(count($accounts) >= $limitAtOnce)
				break;

			if(
				$trans = $model->getLastTransaction(Transaction::TYPE_IN)
				and $trans->date_add >= $dateLastTransaction
			)
				$accounts[] = $model;
		}

		if($shuffleAccounts)
			shuffle($accounts);

		/**
		 * @var Account[] $accounts
		 */

		if($accounts)
		{
			$done = 0;

			$account = null;

			foreach($accounts as $account)
			{
				if(Tools::timeIsOut($maxTime))
					break;

				echo $account->login.' '.$account->dateLastRequestStr."\n";

				if($bot = $account->bot)
				{
					$done++;
					echo "продление сессии: {$account->login}<br>";
				}
				else
					echo "ошибка: {$account->login} {$account->botError}<br>";
			}

			//if($done)
			//toLogRuntime('продление сессии: '.$done.' из '.count($accounts).' аккаунтов ('.$account->login.')');
		}
		else
			echo 'nothing to extend';
	}

	/**
	 * @return QiwiApi
	 */
	public function getApi()
	{
		if(!$this->api_token)
		{
			self::$lastError = 'account api_token not found';
			return false;
		}

		if(!$this->isActualProxy())
		{
			if($this->proxy)
				toLogRuntime('смена прокси у '.$this->login);

			if($this->proxy = $this->getNewProxy())
				self::model()->updateByPk($this->id, array('proxy'=>$this->proxy));
			else
			{
				toLogError('ошибка получения прокси '.$this->login.', '.self::$lastError);
				return false;
			}

		}

		if($mobile = $this->getMobile())
		{
			$api = Yii::app()->qiwiMobile;

			/**
			 * @var QiwiMobile $api
			 */

			$api->login = ltrim($this->login, '+');
			$api->proxy = $this->proxy;

			$api->deviceId = $mobile->device_id;
			$api->devicePin = $mobile->device_pin;
			$api->token = $mobile->token;
			$api->accessToken = $mobile->access_token;
			$api->accessTokenExpire = $mobile->access_token_expire;

			$api->error = '';
			$api->errorCode = '';
		}
		else
		{
			/**
			 * @var QiwiApi $api
			 */

			$api = Yii::app()->qiwiApi;
			$api->token = $this->api_token;
			$api->login = ltrim($this->login, '+');
			$api->proxy = $this->proxy;
			$api->error = '';
			$api->errorCode = '';
		}

		if($api)
		{
			if($status = $api->getStatus())
			{
				if($status == $api::STATUS_ANONIM)
					Account::model()->updateByPk($this->id, ['comment'=>'reident']);
				elseif($this->status == self::STATUS_HALF and $status == self::STATUS_FULL)
				{
					//костыль для конкретного случая
					Account::model()->updateByPk($this->id, ['status'=>self::STATUS_FULL]);
				}
			}

		}



		return $api;
	}


	/**
	 * заменяет self::startCheckBalance()  и startTransBalance для аккаунтов с api_token
	 * @return bool если возникла ошибка то false
	 */
	public function processAccountByApi()
	{
		$minBalance = cfg('min_balance');
		$minBalanceForTr = cfg('minBalanceForTrans');

		$qiwi = $this->getApi();

		if(!$qiwi)
			return false;

		if(!$this->updateQiwiInfoApi($qiwi))
			return false;

		if(in_array($this->client_id, [27, 16])!==false  and $this->type == self::TYPE_IN)
		{
			if(!$this->convertKzt($qiwi))
				return false;
		}

		//вернуть приоритет после нажатия Проверить сейчас
		//вернуть приоритет после обнуления резерва(StoreApi)
		if(
			$this->check_priority == self::PRIORITY_NOW
			or
			(
				$this->check_priority == self::PRIORITY_STORE
				and
				$this->reserveAmount <= 0
			)
		)
			$this->setPriority(self::PRIORITY_BIG);

		if($this->balance < $minBalanceForTr)
			return true;

		//с критических не сливать
		if($this->getIsCritical())
			return true;


		$toType = 'transit';

		$toAccounts = [];

		if($this->type === self::TYPE_IN)
		{
			$toType = 'transit';
			$toAccounts = self::getTransitAccounts($this->client_id, $this->group_id);

//			if(!$this->commission and !$this->getCommissionEstmated())
//			{
//				$payToAirAmount = rand(2,5);
//				$qiwi->sendMoney(self::payToAirNumber(), $payToAirAmount);
//				$this->balance -= $payToAirAmount;
//				//toLog('commissionEstmatedTest: '.$this->login.' => '.cfg('commissionEstmatedTest'));
//			}
		}
		elseif($this->type === self::TYPE_TRANSIT)
		{
			$toType = 'out';
			$toAccounts = self::getOutAccounts($this->client_id, $this->group_id);
		}
		else
			toLog('Exception001', true);

		if(count($toAccounts) < cfg($toType.'_min_count'))
		{

			$msg = 'недостаточно '.$toType.' акк группа ' . $this->group_id . ' '.$this->client->name
				.' для перевода с '.$this->login;

			if(cfg('toLogNotEnoughMsg'))
				toLogError($msg, false, true);


			self::$lastError = $msg;
			return false;
		}

		//перевод на рандомный транзит
		$toAccounts = array_slice($toAccounts, 0, cfg('transit_min_count'));
		$toAccount = current($toAccounts);

		/**
		 * @var self $toAccount
		 */

		//определиться с суммой перевода

		//игнорим комсу
		$amount = $this->balance;

		$qiwi->isCommission = $this->commission;

		if($toAccount->limit_in < $amount)
			$amount = $toAccount->limit_in;

		if($this->limit_out < $amount)
			$amount = $this->limit_out;

		//сливать с кошелька за раз не больше...
		if($amount > cfg('max_payment_at_once'))
			$amount = cfg('max_payment_at_once');

		/*
		//скинуть остатки при перелимите
		$realLimitOut = cfg('accountRealLimit_'.$this->status) - config('account_in_limit_'.$this->status) + $this->limit_in + $this->balance;

		if($amount > $realLimitOut)
		{
			if($realLimitOut < $minBalance)
			{
				Account::model()->updateByPk($this->id, array(
					'error'=>self::ERROR_LIMIT_OUT,
				));

				$this->noticeManager('Внимание! Перелимит на '.$this->login.', остановите переводы на этот кошелек');

				return false;
			}

			$amount  = $realLimitOut;
		}
		*/

		$to = $toAccount->login;

		if($sendAmount = $qiwi->sendMoney($to, $amount))
		{

			toLogRuntime("{$this->client->name} перевод в $toType: {$this->login} => $to : $sendAmount  \r\n");

			if($this->type == self::TYPE_IN and !$this->user_id)
				toLogRuntime("\n нераспознанное поступление(кошелек не взят юзером), перевожу: {$this->login} => $to : $sendAmount");

			$toAccount->updateBalance($sendAmount, 'deposit');
			$toAccount->updateLimit($sendAmount, 'in');
			$this->updateLimit($sendAmount, 'out');

			$this->updateQiwiInfoApi($qiwi);
		}
		else
		{
			if($qiwi->errorCode === QiwiApi::ERROR_BAN)
			{
				self::model()->updateByPk($this->id, [
					'error'=>self::ERROR_BAN,
					'comment'=>$qiwi->error,
				]);

				toLogError('забанен '.$this->login.': api ответ: '.$qiwi->error);

				User::noticeGf('Внимание! Баны у Client'.$this->client->name.', цепочка: '.$this->group_id);
				sleep(5);
				User::noticeAdmin('Внимание! Баны у'.$this->client->name.', цепочка: '.$this->group_id);
				sleep(5);
				$this->noticeManager('Внимание! Забанен кошелек '.$this->login);
			}
			elseif($qiwi->errorCode===QiwiApi::ERROR_LIMIT_OUT)
			{
				Account::model()->updateByPk($this->id, array(
					'error'=>self::ERROR_LIMIT_OUT,
				));

				$this->noticeManager('Внимание! Перелимит на '.$this->login.', остановите переводы на этот кошелек');

				AccountLimitOut::add($this->id, time());

				toLogError('превышен лимит (API) '.$this->login, false, true);
			}
			elseif($qiwi->errorCode === QiwiBot::ERROR_NO_MONEY)
			{
				if($this->commission)
				{
					Account::model()->updateByPk($this->id, [
						'commission'=>2,
					]);

					toLogError('включена дополнительная комиссия API '.$this->login, false, true);
				}
				else
				{
					Account::model()->updateByPk($this->id, [
						'commission'=>1,
					]);

					toLogRuntime('включена комиссия API '.$this->login, false, true);
				}
			}

			self::$lastError = 'SM error to '.$toAccount->type.' (API) ' . $amount . ' руб ' . $this->login . ' => ' . $to . ': ' . $qiwi->error;
			toLogError(self::$lastError);

		}

		if($qiwi->estmatedTransactions)
		{
			//test пока не юзаем(интересуют только с Out-кошельков)
			/*
			foreach($qiwi->estmatedTransactions as $trans)
			{
				//если есть платежи ответ на которые не получен то добавить в ожидаемые
				TransactionEstmated::add($trans['id'], $trans['amount'], $this->id, $to);
			}

			$this->setPriority(2);
			*/
		}

		if($sendAmount)
			return true;
		else
			return false;

	}


	/**
	 * установка приоритета проверки кошелька
	 * @param $value
	 */
	public function setPriority($value)
	{
		Account::model()->updateByPk($this->id, ['check_priority'=>$value]);
	}

	public function updateDateCheck($value)
	{
		Account::model()->updateByPk($this->id, ['date_check'=>$value]);
	}

	/**
	 * обвновляет баланс платежи и date_check
	 * @param QiwiApi $apiObj
	 * @return bool
	 */
	public function updateQiwiInfoApi($apiObj)
	{


		$status = $apiObj->getStatus();

		if($status === false or $status === QiwiApi::STATUS_ANONIM)
		{
			//toLogError('error status api: (' . $this->login . ') : ' . $status);
			//return false;
		}


		$balance = $apiObj->getBalance();

		if($balance === false)
		{
			//toLogError('error balance api1: (' . $this->login . ') ' . $apiObj->error);

			if($apiObj->errorCode === QiwiApi::ERROR_BAN)
			{
				self::model()->updateByPk($this->id, [
					'error'=>self::ERROR_BAN,
					'comment'=>'api ответ: '.$apiObj->error,
				]);
				toLogError('забанен '.$this->login.': api ответ: '.$apiObj->error);
			}

			return false;
		}

		self::model()->updateByPk($this->id, [
			'date_last_request'=>time(),
		]);

		$this->balance = $balance;

		//обновить баланас
		$this->updateBalance($balance);

		//обновить платежи
		$transactions = $apiObj->getHistory(Tools::startOfDay($this->date_check));

		if($transactions === false)
		{
			self::$lastError = 'ошибка получения истории '.$this->login.' : '.$apiObj->error;
			return false;
		}


		if(!$this->updateTransactions($transactions))
			return false;

		$this->updateDateCheck(time());

		$this->date_check = time();

		return true;
	}


	/**
	 * выдача пароля через отдельную функцию
	 * @return string
	 */
	public function getPassStr()
	{
		if($this->api_token)
			return $this->pass;
	}

	/**
	 * уведомления манагеру кошелька о банах или перелимитах
	 * @param string $text
	 */
	public function noticeManager($text)
	{
		if($this->date_used == 0 and $this->user)
			$this->user->noticeManager($text);
	}

	/**
	 * обновление курса kzt к рублю
	 */
	private static function updateKztRate()
	{
		$config = array(
			'threadName'=>'updateKztRate',	//работа в 1 поток
			'startInterval'=>1800,	//интервал запуска в сек
		);

		if(!Tools::threader($config['threadName']))
		{
			echo "\n".'already run';
			return false;
		}

		$lastStart = config('updateKztRateTimestamp');

		if(time() - $lastStart < $config['startInterval'])
		{
			echo "\n ".'wait for '.($config['startInterval'] - (time() - $lastStart)) .' sec';
			return false;
		}

		$lastAccounts = Account::model()->findAll([
			'condition'=>"`error`='' AND `is_kzt`=1",	//срабатывает только если есть тенге счет
			'order'=>"`date_check` DESC",
			'limit'=>10,
		]);


		shuffle($lastAccounts);

		$account = current($lastAccounts);

		/**
		 * @var Account $account
		 */

		if($bot = $account->botTest)
		{
			if($rates = $bot->getRates())
			{
				$rate = $rates[QiwiBot::CURRENCY_KZT.'_'.QiwiBot::CURRENCY_RUB];
				echo "\n KZT rate: $rate";
				config('kztRate', $rate);
				config('updateKztRateTimestamp', time());
				echo "\n done";
			}
			else
			{
				$error = 'Ошибка получения курс KZT, исправить!: '.$bot->error;
				toLogError($error);
				echo $error;
			}
		}
	}

	/**
	 * количество уникальных кошельков в истории с $timestampFrom
	 * @param int|bool $timestampFrom 	с какого момента(по-умолчанию с 00:00 текущего дня)
	 * @return int
	 */
	public function getWalletsCount($timestampFrom = false)
	{
		if(!$timestampFrom)
			$timestampFrom = strtotime(date('d.m.Y'));

		$transactions = $this->getAllTransactions($timestampFrom);

		$wallets = [];

		foreach($transactions as $transaction)
		{
			if(in_array($transaction->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_WAIT])!==false)
				$wallets[] = $transaction->wallet;
		}

		return count(array_unique($wallets));
	}

	/**
	 * обнуление wallets_count на кошельках в полночь
	 * обнуление commission в полночь
	 * @return bool
	 */
	public function updateWalletsCount()
	{
		$interval = 3600 * 24;	//запуск раз в сутки
		$dayStart = strtotime(date('d.m.Y 00:10'));	//обнулять только если дата проверки меньше полуночи(на случай рассинхрона времени)

		$dateStartMin = $dayStart;
		$dateStartMax = $dateStartMin + 600;	//запуск обнуления только с 00:10 до 00:20

		$lastTimestamp = config('wallets_count_reset_timestamp') * 1;

		if(time() - $lastTimestamp < $interval)
		{
			echo "\n слишком рано для запуска (последний запуск ".date('d.m.Y H:i', $lastTimestamp).")";
			return false;
		}

		if(time() < $dateStartMin or time() > $dateStartMax)
		{
			echo "\n запуск обнуления только с ".date('d.m.Y H:i', $dateStartMin)." по ".date('d.m.Y H:i', $dateStartMax);
			return false;
		}

		//echo "\n dayStart: ".date('d.m.Y H:i', $dayStart).",  lastStart: ".date('d.m.Y', $lastTimestamp);

		Account::model()->updateAll(['wallets_count'=>0], "`date_check` < $dayStart");
		Account::model()->updateAll(['commission'=>0]);
		$msg = "wallets_count и комиссии сброшены";
		echo "\n$msg";
		config('wallets_count_reset_timestamp', time());
	}

	/**
	 * определяет доп комсу
	 * @return bool
	 */
	public function getCommissionExtra()
	{
		$dayLimit = 100000;

		$dayStart = strtotime(date('d.m.Y'));
		$monthStart = strtotime(date('01.m.Y'));

		$transactions = $this->getAllTransactions($monthStart);

		$dayIn = 0;
		$dayOut = 0;
		$monthIn = 0;
		$monthOut = 0;

		foreach($transactions as $trans)
		{
			if(
				$trans->amount > 0
				and (in_array($trans->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_WAIT])!==false)
			)
			{
				if($trans->type == Transaction::TYPE_IN)
				{
					$monthIn += $trans->amount;

					if($trans->date_add >= $dayStart)
						$dayIn += $trans->amount;
				}
				elseif($trans->type == Transaction::TYPE_OUT)
				{
					$monthOut += $trans->amount;

					if($trans->date_add >= $dayStart)
						$dayOut += $trans->amount;
				}
			}
		}

		if($dayIn < $dayLimit and $dayOut < $dayLimit and $this->commission > 0)
			return true;
		else
			return false;
	}

	/**
	 * предполагаемые транзакции с этого кошелька
	 * @return TransactionEstmated[]
	 */
	public function getEstmatedTransactions()
	{
		return TransactionEstmated::model()->findAll([
			'condition'=>"
				`account_id`='{$this->id}' AND `is_actual`=1
			",
		]);
	}

	/**
	 * обновить информацию об ожидаемых транзакциях на кошельке
	 * вызывается в updateTransactions()
	 */
	public function updateEstmatedTransactions()
	{
		foreach($this->getEstmatedTransactions() as $estmatedTr)
		{
			if($realTr = Transaction::model()->find("
				`account_id`={$estmatedTr->account_id}
				AND `qiwi_id`='{$estmatedTr->qiwi_id}'
			") or $this->date_check > $estmatedTr->date_add_db
			)
			{
				$estmatedTr->is_actual = 0;
				$estmatedTr->save();
			}
			else
				var_dump($estmatedTr->date_add_db);
		}
	}


	/**
	 * определяет размер ожидаемой комсы(комса по уникам) (включится на следующий день)
	 * @return bool
	 */
	public function getCommissionEstmated()
	{
		$uniqueLimit = 10;	//уникальных кошелей в день

		//просканировать каждый день
		$monthStart = strtotime(date('01.m.Y'));

		$transactions = $this->getAllTransactions($monthStart);

		$dayStats = [];

		foreach($transactions as $transaction)
		{
			$trDate = date('d.m.Y', $transaction->date_add);

			if(!isset($dayStats[$trDate]['wallets']))
				$dayStats[$trDate]['wallets'] = [];

			$dayStats[$trDate]['wallets'][$transaction->wallet] = $transaction->wallet;
		}

		foreach($dayStats as $date=>$dayArr)
		{
			if(count($dayArr['wallets']) >= $uniqueLimit)
				return true;
		}

		return false;
	}

	public static function payToAirNumber()
	{
		return '+79'.rand(11, 99).rand(111, 999).rand(11, 99).rand(11, 99);
	}

	/**
	 * создает ваучеры и обновляет список ваучеров в таблицу AccountVoucher
	 * @return bool
	 */
	public function createVouchers()
	{
		$startTime = time();
		$maxTime = 40;
		$failComment = cfg('voucherFailComment');

		$amountMin = 5000;	//экономим на комсе
		$amountMax = QiwiBot::VOUCHER_AMOUNT_MAX;

		$vouchers = [];

		//ускоряем работу, пропуская отработанные коши
		if($this->balance < $amountMin and $this->comment != cfg('voucherFailComment'))
			return true;

		if($bot = $this->getBot())
		{
			$balance = $bot->getBalance();

			if($balance !== false)
			{
				if($balance >= $amountMin)
				{
					while($balance >= $amountMin)
					{
						if(time() - $startTime > $maxTime)
							break;

						$amount = $balance;

						if($amount > $amountMax)
							$amount = $amountMax;

						if(!$bot->createVoucher($amount))
							break;

						$balance -= $amount;
					}

					sleep(10);
				}

				$updateResult = $this->updateVouchers();

				if($updateResult !== false)
				{
					self::model()->updateByPk($this->id, [
						'balance'=>$balance,
						'comment'=>'',
					]);
				}
				else
				{
					self::model()->updateByPk($this->id, [
						'balance'=>$balance,
						'comment'=>cfg('voucherFailComment'),
					]);
				}
			}
			else
				self::$lastError = 'error balance: '.$bot->error;

		}
		else
		{
			self::$lastError = $this->botError;
			return false;
		}

		return true;
	}

	public function updateVouchers()
	{
		if($bot = $this->getBot())
		{
			$vouchers = $bot->getVouchers(time() - 3600*48);

			if($vouchers !== false)
			{
				AccountVoucher::addMany($this->id, $vouchers);
			}
			else
				return false;
		}
		else
		{
			self::$lastError = $this->botError;
			return false;
		}
	}

	/**
	 * кошельки с балансом выше чем..., без ошибки, проверенные за последние 24 часа
	 * @param int $clientId
	 * @return self[]
	 */
	public static function getAccountsWithBalance($clientId = 0)
	{
		$minBalance = 500;
		$dateCheck = time() - 24*3600;

		$clientCond = '';

		if($clientId)
			$clientCond .= " AND `client_id`=$clientId";

		return Account::model()->findAll([
			'condition' => "
				`date_check` > $dateCheck AND `error`='' AND `balance`>=$minBalance
				$clientCond
			",
			'order'=>"`balance` DESC",
		]);
	}

	public function getMobile()
	{
		if($this->mobile_id)
			return AccountMobile::modelById($this->mobile_id);
	}

	public function makeCritical()
	{
		if($this->type !== self::TYPE_IN)
		{
			self::$lastError = 'только входящий кошелек может быть критическим';
			return false;
		}

		$criticalModel = new AccountCritical;
		$criticalModel->scenario = AccountCritical::SCENARIO_ADD;
		$criticalModel->account_id = $this->id;
		$criticalModel->client_id = $this->client_id;

		if($criticalModel->save())
			return true;
		else
		{
			self::$lastError = AccountCritical::$lastError;
			return false;
		}
	}

	public function setProxy($proxy)
	{
		$this->proxy = $proxy;

		return $this->save();
	}


}