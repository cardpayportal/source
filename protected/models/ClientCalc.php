<?php

/**
 * расчеты клиентов - отчет о выдаче usd
 * @property int id
 * @property int client_id
 * @property float amount_rub
 * @property float amount_usd
 * @property int user_id
 * @property int date_add
 * @property string comment
 * @property string scenario
 * @property User user
 * @property Client client
 * @property string dateAddStr
 * @property float amountRubStr
 * @property float amountUsdStr
 * @property float debt_rub
 * @property float debtRubStr
 * @property float statsIn
 * @property string statsInStr
 * @property ClientCalc prevCalc
 * @property Transaction[] latePayments
 * @property bool is_control
 * @property string status
 * @property string statusStr
 * @property string client_comment
 * @property float amount_btc
 * @property string btc_address
 * @property string btc_rate курс в баксах
 * @property ManagerOrder[] orders
 * @property string btcAddressShort
 * @property string addressShort
 * @property float usd_rate курс из таблицы Комиссии клиентов на момент расчета
 * @property string ltc_address
 * @property bool isLast
 *
 */
class ClientCalc extends Model
{
	const SCENARIO_ADD = 'add';
	const SCENARIO_ADD_FIRST = 'add_first';
	const ADD_MIN_INTERVAL = 60;	//минимальный интервал между добавлениями расчетов
	const RATE_USD_MIN = 50;	//минимальный курс
	const RATE_USD_MAX = 80;	//максимальный курс

	const STATUS_NEW = 'new';
	const STATUS_WAIT = 'wait';
	const STATUS_DONE = 'done';
	const STATUS_CANCEL = 'cancel';

	public $orderIds = [];

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'client_id' => 'Клиент',
			'amount_rub' => 'Сумма RUB',	//сумма платежей руб
			'amount_usd' => 'Сумма USD',	//сумма платежей руб
			'user_id' => 'Автор',
			'date_add' => 'Дата добавления',
			'comment' => 'Примечание',
			'is_control' => 'Контрольный',
			'status' => 'Статус',
			'client_comment' => 'Комментарий',
			'btc_rate' => 'Курс BTC',
			'ltc_address' => 'Адрес LTC',
		);
	}

	public function tableName()
	{
		return '{{client_calc}}';
	}

	public function beforeValidate()
	{
		$this->amount_rub = str_replace(',', '.', $this->amount_rub);
		$this->amount_usd = str_replace(',', '.', $this->amount_usd);
		$this->comment = strip_tags($this->comment);
		$this->client_comment = strip_tags($this->client_comment);

		//когда расчет создает клиент,  user_id=0
		if(!$this->user_id)
			unset($this->user_id);

		if(!$this->amount_btc or $this->amount_btc <= 0)
			unset($this->amount_btc);


		if($this->scenario == self::SCENARIO_ADD_FIRST)	//добавление первого
		{
			$this->date_add = strtotime($this->date_add);
			$this->is_control = 1;
		}

		return parent::beforeValidate();
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			if(self::model()->find("`client_id`='{$this->client_id}'"))
			{
				$recalc = $this->getClient()->recalc($this->date_add);

				//при контрольном никаких долгов
				if(!$this->is_control)
					$this->debt_rub = $recalc['amountRub'] - $this->amount_rub;
			}

			if($this->is_control)
			{
				$datePay = time();

				foreach($this->getClient()->notPaidOrders as $order)
				{
					$order->date_pay = $datePay;

					if(!$order->save())
					{
						$this->addError('is_control', 'ошибка оплаты заявки '.$order->id);
						return false;
					}
				}
			}
		}

		return parent::beforeSave();
	}


	/*
	 * todo: логировать в globalFinLog
	 */
	protected function afterSave()
	{
		if(in_array($this->scenario, [self::SCENARIO_ADD, self::SCENARIO_ADD_FIRST]))
		{
			foreach($this->orderIds as $id => $amount)
			{
				$order = ManagerOrder::getModelById($id);

				ManagerOrder::model()->updateByPk($order->id, ['date_pay'=>time(), 'calc_id'=>$this->id]);
			}

			toLogSecurity('добавлен расчет для клиента '.$this->client_id.' на сумму '.$this->amount_rub.' (автор: '.$this->user->name.', ip: '.Tools::getClientIp().')');
		}
		elseif($this->status == self::STATUS_CANCEL)
		{
			foreach($this->orders as $order)
				ManagerOrder::model()->updateByPk($order->id, ['date_pay'=>0, 'calc_id'=>0]);
		}

		parent::afterSave();
	}

	protected function afterDelete()
	{
		foreach($this->orders as $order)
			ManagerOrder::model()->updateByPk($order->id, ['date_pay'=>0, 'calc_id'=>0]);

		toLogRuntime('удален расчет '.$this->id.' для Cl'.$this->client_id.' на сумму '.$this->amount_rub.' (автор: '.$this->user->name.')');

		parent::afterDelete();
	}

	public function rules()
	{
		return array(
			//общее
			array('client_id, user_id amount_rub, amount_usd, date_add', 'required'),
			array('client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id'),
			array('date_add', 'numerical', 'max'=>time(), 'min'=>time()-3600*24*30, 'message'=>'Неверная дата добавления'),	//защита от повторного добавления
			array('date_add', 'addIntervalValidator'),	//защита от повторного добавления
			array('user_id', 'exist', 'className'=>'User', 'attributeName'=>'id'),
			array('user_id', 'userValidator'),
			array('amount_rub', 'numerical', 'min'=>0, 'max'=>99999999),	//если есть долг то сумма может быть и 0
			array('amount_usd', 'numerical', 'min'=>0, 'max'=>999999),
			array('comment, client_comment', 'length', 'max'=>255),
			array('is_control', 'isControlValidator'),
			//new
			array('status', 'in', 'range'=>array_keys(self::getStatusArr()), 'allowEmpty'=>true),
			array('amount_btc', 'numerical', 'min'=>0.00000001, 'max'=>99.99999999, 'allowEmpty'=>true),
			array('btc_address', 'match', 'pattern'=>cfg('btcAddressRegExp'), 'allowEmpty'=>true),
			array('btc_rate', 'numerical', 'min'=>0, 'max'=>20000, 'allowEmpty'=>true),
			array('orderIds', 'orderIdsValidator', 'on'=>[self::SCENARIO_ADD, self::SCENARIO_ADD_FIRST], 'allowEmpty'=>true),
			array('amount_rub', 'amountRubValidator', 'on'=>[self::SCENARIO_ADD, self::SCENARIO_ADD_FIRST]),
			array('usd_rate', 'safe'),
			array('ltc_address', 'match', 'pattern'=>cfg('ltcAddressRegExp'), 'allowEmpty'=>true),
			array('ltc_address', 'ltcWithBtcValidator'),

		);
	}

	/*
	 * првоерить тип пользователя и стоит ли юзер у руля
	 */
	public function userValidator()
	{
		$user = User::getUser($this->user_id);

		if($user->role == User::ROLE_FINANSIST)
		{
			if($this->status != self::STATUS_NEW)
			{
				$this->addError('user_id', 'Неверный статус расчета');
				return false;
			}
		}
		elseif($user->role == User::ROLE_GLOBAL_FIN or $user->role == User::ROLE_ADMIN)
		{
			if(!$user->is_wheel and $user->role != User::ROLE_ADMIN)
				$this->addError('user_id', 'Рассчитать клиента может только рулевой');
		}
		else
		{
			$this->addError('user_id', 'У вас нет прав на это действие');
			return false;
		}

		return true;
	}

	public function addIntervalValidator()
	{
		if(
			$lastCalc = $this->getClient()->lastCalc
			and time() - $lastCalc->date_add < self::ADD_MIN_INTERVAL
			and $lastCalc->user->role != User::ROLE_ADMIN
		)
			$this->addError('date_add', 'Слишком частый расчет клиента: подождите '.(time() - $lastCalc->date_add).' сек');
	}

	public function orderIdsValidator()
	{
		foreach($this->orderIds as $id=>$amount)
		{
			if($order = ManagerOrder::getModelById($id))
			{
				if($this->client_id != $order->user->client_id)
				{
					$this->addError('orderIds', 'заявка #'.$id.' не принадлежит текущему пользователю');
					return false;
				}

				if($order->date_pay or $order->calc_id)
				{
					$this->addError('orderIds', 'заявка #'.$id.' уже была оплачена');
					return false;
				}
			}
			else
			{
				$this->addError('orderIds', 'не найдена заявка #'.$id);
				return false;
			}
		}
	}

	public function amountRubValidator()
	{
		if($this->getClient()->calc_mode == Client::CALC_MODE_ORDER)
		{
			//если заявки не выбраны то ошибка
			if(!$this->orderIds)
			{
				$this->addError('orderIds', 'Заявки не выбраны');
				return false;
			}

			//соответствует ли сумма заявок сумме расчета
			$ordersAmount = 0;

			foreach($this->orderIds as $id=>$amount)
			{
				$order = ManagerOrder::getModelById($id);
				$ordersAmount += $order->amountIn;
			}

			if(floor($ordersAmount) != floor($this->amount_rub))
				$this->addError('amount_rub', 'Ошибка при проверке суммы заявок. Обратитесь к администратору.');
		}
	}

	/**
	 * если контрольный расчет то не должно быть текущих заявок
	 */
	public function isControlValidator()
	{
		if($this->is_control and $this->scenario == self::SCENARIO_ADD)
		{
			if($this->getClient()->currentManagerOrders)
			{
				$this->addError('is_control', 'при контрольном расчете не должно быть незавершенных заявок');
				return false;
			}
		}
	}

	public function ltcWithBtcValidator()
	{
		if($this->btc_address and $this->ltc_address)
		{
			$this->addError('ltc_address', 'нужно заполнить только один адрес (BTC или LTC)');
			return false;
		}
	}


	/**
	 * @return User
	 */
	public function getUser()
	{
		return User::getUser($this->user_id);
	}

	/**
	 * @return Client
	 */
	public function getClient()
	{
		return Client::modelByPk($this->client_id);
	}

	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	public function getCommentShort()
	{
		$maxLen = 30;

		$str = Tools::shortText($this->comment, $maxLen);

		if(strlen($str) < strlen($this->comment))
			$str = '<span class="withComment" title="'.$this->comment.'">'.$str.'</span>';

		return $str;
	}

	public function getStatusStr()
	{
		return self::getStatusArr()[$this->status];
	}

	/**
	 * подсчет usd по курсу
	 *
	 * @param float $amountRub
	 * @param float $rateUsd
	 * @return float
	 */
	public static function calcUsdAmount($amountRub, $rateUsd)
	{
		return round($amountRub / $rateUsd, 2);
	}

	/**
	 * подсчет btc по курсу
	 * @param float $amountUsd
	 * @param float $rateBtc
	 * @return float
	 */
	public static function calcBtcAmount($amountUsd, $rateBtc)
	{
		return round($amountUsd / $rateBtc, 8);
	}

	public static function calcUsdRate()
	{
		$usdRate = config('usd_rate_parse_value');

		$percent = config('client_calc_percent')/100 + 1;
		$bonusRub = config('client_calc_bonus')*1;
		$rate = $usdRate * $percent + $bonusRub;

		$rate = $rate * 0.99;	//новое условие: минус 1 проц от результата


		return round($rate, 2);
	}

	/**
	 * @param int $clientId
	 * @param string $dateStart	(при первом расчете)
	 * @param int $timestampEnd	(при первом расчете)
	 * @return array если нет lastCalc то вернет false
	 * если нет lastCalc то вернет false
	 * параметр slowAccounts //array('id'=>..,'login'=>.., 'dateCheckStr'=>..) - order by date_check
	 *
	 */
	public static function getCalcParams($clientId, $dateStart = '', $timestampEnd = null)
	{
		$result = array();

		$dateFormat = 'd.m.Y H:i';

		if(!$client = Client::modelByPk($clientId))
		{
			self::$lastError = 'не найден clientId='.$clientId;
			return false;
		}

		$firstCalc = $client->firstCalc;	//для подсчета прихода
		$lastCalc = $client->lastCalc;	//для подсчета выводов клФина

		if($lastCalc)
		{
			$timestampStart = $lastCalc->date_add;
		}
		elseif($dateStart)
			$timestampStart = strtotime($dateStart);
		else
			$timestampStart = time();

		if($timestampEnd === null)
			$timestampEnd = time();


		if($lastCalc)
			$result['dateStart'] = date($dateFormat, $lastCalc->date_add);
		else
			$result['dateStart'] = date($dateFormat, $timestampStart);

		$result['dateEnd'] = date($dateFormat, $timestampEnd);


		$recalc = $client->recalc();


		$result['amount_rub'] = $recalc['amountRub'];

		//источник курса
		$result['rateSourceName'] = $client->commissionRule->rateSourceStr;

		$result['rateUsd'] = str_replace(',' ,'.', $client->commissionRule->rateValue);

		$result['rateUsdSource'] = str_replace(',' ,'.', $client->commissionRule->rateValueSource);

		$result['amount_usd'] = str_replace(',' ,'.', self::calcUsdAmount($result['amount_rub'], $result['rateUsd']));

		$timestampStartForOrders = ($lastCalc) ? $lastCalc->date_add : $timestampStart;
		$result['finOrdersDateStart'] = date('d.m.Y H:i', $timestampStartForOrders);
		$result['finOrdersDateEnd'] = date('d.m.Y H:i');

		//список выводов клФина
		$result['clFinOrders'] = $client->getClFinOrders($timestampStartForOrders);
		$result['clFinOrdersTotal'] = 0;

		//есть ли средит них платежи в ожидании
		$result['clFinOrdersInProcess'] = false;

		//для fin-а
		$result['rateBtc'] = config('btc_usd_rate_btce');
		$result['amountBtc'] = self::calcBtcAmount($result['amount_usd'], $result['rateBtc']);

		foreach($result['clFinOrders'] as $order)
		{
			/**
			 * @var FinansistOrder $order
			 */

			if($order->status == FinansistOrder::STATUS_WAIT)
				$result['clFinOrdersInProcess'] = true;

			$result['clFinOrdersTotal'] += $order->amount_send;
		}

		//список выводов глобалФина
		/*
		$result['globalFinOrders'] = $client->getGlobalFinOrders($timestampStartForOrders);
		$result['globalFinOrdersTotal'] = 0;

		foreach($result['globalFinOrders'] as $order)
		{
			$result['globalFinOrdersTotal'] += $order->amount_send;
		}
		*/

		return $result;
	}



	/*
	 * добавление нового расчета
	 * $auto - если уже есть прошлый расчет то автоматом заполнаять все поля кроме примечания
	 */
	public static function add($params)
	{
		$model = new self;

		//если уже были расчеты то другой сценарий
		if(self::model()->find("`client_id`='{$params['client_id']}'"))
			$model->scenario = self::SCENARIO_ADD;
		else
			$model->scenario = self::SCENARIO_ADD_FIRST;

		$params['status'] = self::STATUS_DONE;
		$params['usd_rate'] = $params['rateUsd'];

		$model->attributes = $params;

		if($model->save())
		{
			//очистить кэш баланса для клиента
			Yii::app()->cache->delete('recalcClient'.$params['client_id']);
			return true;
		}
		else
			return false;
	}

	/**
	 * добавление расчета клиентом (выставление счета)
	 * @param array $params [
	 * 	'amount_rub'=>'сумма в рублях',
	 * 	'btc_address'=>'',
	 * 	'extra'=>'платежный пароль'
	 * 	'user_id'=>'id юзера который добавляет',
	 * 	'client_comment'=>'комментарий клиента',
	 * ]
	 *
	 * @return bool
	 */
	public static function addFromClient($params)
	{
		//проверка
		$user = User::getUser($params['user_id']);

		if(!$user or $user->role != User::ROLE_FINANSIST)
		{
			self::$lastError = 'нет прав для данного действия';
			return false;
		}

		if($params['btc_address'] and !preg_match(cfg('btcAddressRegExp'), $params['btc_address']))
		{
			self::$lastError = 'неверный BTC-адрес';
			return false;
		}

		if($params['ltc_address'] and !preg_match(cfg('ltcAddressRegExp'), $params['ltc_address']))
		{
			self::$lastError = 'неверный LTC-адрес';
			return false;
		}

		if(!PayPass::check($params['extra'], $user->id))
		{
			self::$lastError = 'неверный платежный пароль';
			return false;
		}

		$calcParams = ClientCalc::getCalcParams($user->client_id);

		if($params['amount_rub'] > $calcParams['amount_rub'])
		{
			if($user->client->calc_mode == Client::CALC_MODE_ORDER)
			{
				self::$lastError = 'ошибка при проверке суммы заявок, обратитесь к администратору';
				return false;
			}
			else
			{
				self::$lastError = 'сумма в рублях не должна превышать максимальную';
				return false;
			}
		}

		unset($params['extra']);

		$params['client_id'] = $user->client_id;
		$params['status'] = self::STATUS_NEW;
		$params['amount_usd'] = self::calcUsdAmount($params['amount_rub'], $calcParams['rateUsd']);
		$params['btc_rate'] = $calcParams['rateBtc'];
		$params['amount_btc'] = self::calcBtcAmount($params['amount_usd'], $params['btc_rate']);
		$params['usd_rate'] = $params['rateUsd'];

		//print_r($params); self::$lastError = 'test';return false;

		$model = new self;

		//если уже были расчеты то другой сценарий
		if(self::model()->find("`client_id`='{$params['client_id']}'"))
			$model->scenario = self::SCENARIO_ADD;
		else
			$model->scenario = self::SCENARIO_ADD_FIRST;

		$model->attributes = $params;

		if($model->save())
		{
			$noticeText = 'Добавлен новый расчет от Cl'.$model->client_id.' #'.$model->id;

			User::noticeGf($noticeText);

			//test clearRecalcCache
			//очистить кэш баланса для клиента
			Yii::app()->cache->delete('recalcClient'.$user->client_id);

			return true;
		}
		else
			return false;
	}

	/**
	 * добавление расчета клиентом (выставление счета)
	 * @param array $params [
	 * 	'amount_rub'=>'сумма в рублях',
	 * 	'btc_address'=>'',
	 * 	'extra'=>'платежный пароль'
	 * 	'user_id'=>'id юзера который добавляет',
	 * 	'client_comment'=>'комментарий клиента',
	 * ]
	 *
	 * @return bool
	 */
	public static function addFromClientTest($params)
	{
		//проверка
		$user = User::getUser($params['user_id']);

		if(!$user or $user->role != User::ROLE_FINANSIST)
		{
			self::$lastError = 'нет прав для данного действия';
			return false;
		}

		if($params['btc_address'] and !preg_match(cfg('btcAddressRegExp'), $params['btc_address']))
		{
			self::$lastError = 'неверный BTC-адрес';
			return false;
		}

		if($params['ltc_address'] and !preg_match(cfg('ltcAddressRegExp'), $params['ltc_address']))
		{
			self::$lastError = 'неверный LTC-адрес';
			return false;
		}

		if(!PayPass::check($params['extra'], $user->id))
		{
			self::$lastError = 'неверный платежный пароль';
			return false;
		}

		$calcParams = ClientCalc::getCalcParams($user->client_id);

		if($params['amount_rub'] > $calcParams['amount_rub'])
		{
			if($user->client->calc_mode == Client::CALC_MODE_ORDER)
			{
				self::$lastError = 'ошибка при проверке суммы заявок, обратитесь к администратору';
				return false;
			}
			else
			{
				self::$lastError = 'сумма в рублях не должна превышать максимальную';
				return false;
			}


		}

		unset($params['extra']);

		$params['client_id'] = $user->client_id;
		$params['status'] = self::STATUS_NEW;
		$params['amount_usd'] = self::calcUsdAmount($params['amount_rub'], $calcParams['rateUsd']);
		$params['btc_rate'] = $calcParams['rateBtc'];
		$params['amount_btc'] = self::calcBtcAmount($params['amount_usd'], $params['btc_rate']);
		$params['usd_rate'] = $params['rateUsd'];

		//print_r($params); self::$lastError = 'test';return false;

		$model = new self;

		//если уже были расчеты то другой сценарий
		if(self::model()->find("`client_id`='{$params['client_id']}'"))
			$model->scenario = self::SCENARIO_ADD;
		else
			$model->scenario = self::SCENARIO_ADD_FIRST;

		$model->attributes = $params;

		if($model->save())
		{
			$noticeText = 'Добавлен новый расчет от Cl'.$model->client_id.' #'.$model->id;

			User::noticeGf($noticeText);

			return true;
		}
		else
			return false;
	}


	public static function getList($clientId=false, $limit = 100, $order = "`date_add` DESC")
	{
		$condition = "";

		if($clientId)
			$condition .= "`client_id`='$clientId'";

		return self::model()->findAll(array(
			'condition'=>$condition,
			'order'=>$order,
			'limit'=>($limit) ? $limit : null,
		));
	}

	public function getAmountRubStr()
	{
		return '<nobr>'.formatAmount($this->amount_rub, 2).'</nobr>';
	}

	public function getAmountUsdStr()
	{
		return '<nobr>'.formatAmount($this->amount_usd, 2).'</nobr>';
	}

	/**
	 * @param int $clientId
	 * @param int $limit
	 * @return self[]
	 */
	public static function getLastCalcArr($clientId, $limit=3)
	{
		return self::model()->findAll(array(
			'condition'=>"`client_id`=$clientId",
			'order'=>"`date_add` DESC",
			'limit'=>$limit,
		));
	}

	/**
	 * сумма прихода с момента последнего расчета + долг последнего
	 * @param int $clientId
	 * @param int|bool $timestampStart
	 * @return int|bool
	 */
	public static function calcAmountRub($clientId, $timestampStart = false)
	{
		if($client = Client::modelByPk($clientId))
		{
			$lastCalc = $client->lastCalc;

			if($lastCalc)
			{
				$timestampStart = $lastCalc->date_add;
			}
			elseif(!$timestampStart)
				return 0;

			//сумма прихода на входящие кошельки
			$result =  $client->statsIn($timestampStart, time());

			//прибавить последний долг
			$result += $client->lastCalc->debt_rub;

			return round($result);

			/*
			//вычитаем сумму всех расчетов(первый игнорим)
			$calcArr = $client->calcArr;

			foreach($calcArr as $key=>$calc)
			{
				if($key == 0)
					continue;

				$result -= $calc->amount_rub;
			}

			return round($result);
			*/
		}
		else
			return false;
	}

	/**
	 * @return string
	 */
	public function getDebtRubStr()
	{
		if($this->debt_rub != 0)
		{
			$value = formatAmount($this->debt_rub, 0);

			if($this->debt_rub < 0)
				return '<span class="dotted error" title="клиент должен">'.$value.'</span>';
			else
				return '<span class="dotted success" title="клиенту должны">'.$value.'</span>';
		}
		else
			return '0';
	}

	/**
	 * парсит и возвращает курс бакса с финама
	 * @return float|false
	 */
	public static function parseFinamUsdRate()
	{
		$url = 'https://www.finam.ru';

		//$sender = new Sender();
		//$sender->followLocation = false;

		//$sender->timeout = 20;
		//$sender->inCharset = 'cp-1251';

		//$content = $sender->send($url, false);

		$ctx = stream_context_create(array('http'=>
			array(
				'timeout' => 20,  //таймаут 20 сек
			)
		));

		$content = file_get_contents($url, false, $ctx);

		$content = iconv('CP1251', 'UTF8', $content);


		if(preg_match('!"currency":"usd","select":true,"price":"(.+?)"!', $content, $res))
		{
			$result = round($res[1], 2)*1;
		}
		else
		{
			self::$lastError = 'ошика парсинга курса';
			toLogError('ошика парсинга курса1: ИСПРАВИТЬ!!! ('.__METHOD__.') ');
			return false;
		}

		if($result >= self::RATE_USD_MIN and $result <= self::RATE_USD_MAX)
			return $result;
		else
		{
			self::$lastError = 'неверное значение при парсинге курса финама: '.$result;
			toLogError('ошика парсинга курса2: ИСПРАВИТЬ!!! ('.__METHOD__.') '.self::$lastError);
			return false;
		}

	}

	/**
	 * парсит и возвращает курс бакса с BTCE
	 * @return float|false
	 */
	public static function parseWexUsdRateSell()
	{
		$sender = new Sender();
		$sender->followLocation = false;
		$sender->timeout = 20;

		$content = $sender->send('https://wex.nz/api/2/usd_rur/ticker');

		if($json = json_decode($content, true))
		{
			$result = $json['ticker']['sell'];

			$result = round($result, 2)*1;

			if($result >= self::RATE_USD_MIN and $result <= self::RATE_USD_MAX)
			{
				return $result;
			}
			else
			{
				toLogError('ошика парсинга курса btce 2: httpCode='.$sender->info['httpCode'][0].' ИСПРАВИТЬ!!! ('.__METHOD__.')');
				return false;
			}
		}
		else
		{
			//toLogError('ошика парсинга курса btce 1: httpCode='.$sender->info['httpCode'][0].' ИСПРАВИТЬ!!! ('.__METHOD__.')');
			return false;
		}
	}

	/**
	 * парсит и возвращает курс btc_usd
	 * @param string $pair
	 * @return float|false
	 */
	public static function parseBtceLastPrice($pair = 'btc_usd')
	{
		if($btcRate = WexBot::getInstance()->getLastPrice($pair))
		{
			return $btcRate;
		}
		else
		{
			toLogError('ошибка получения курса ClientCalc::parseBtceLastPrice  ИСПРАВИТЬ!!! ('.__METHOD__.')');
			return false;
		}
	}

	/**
	 * обновляет курс usd(finam, btce-lastPrice) и btc-lastPrice
	 * @return null
	 */
	public static function startUpdateRates()
	{
		$config = array(
			'threadName'=>'updateRates',	//работа в 1 поток
			'startInterval'=>120,	//интервал запуска в сек
		);

		if(!Tools::threader($config['threadName']))
			die('already run');

		$lastStart = config('updateRatesLastTimestamp');

		if(time() - $lastStart < $config['startInterval'])
		{
			echo "\n ".'wait for '.($config['startInterval'] - (time() - $lastStart)) .' sec';
			return false;
		}

		if($rate = self::parseFinamUsdRate())
			config('usd_rate_parse_value', str_replace(',', '.', $rate));
		else
		{
			echo "\n ".'error1: '.self::$lastError;
			return false;
		}

		$btcUsdRate = '';
		$btcUsdRateSource = config('btc_usd_rate_source');
		$parseRateError = '';


		if($btcUsdRateSource == 'bitfinex')
		{
			$btcUsdRate = self::parseBitfinexLastPrice();
			$parseRateError = self::$lastError;
		}
		elseif($btcUsdRateSource == 'exmo')
		{
			$btcUsdRate = self::parseExmoLastPrice();
			$parseRateError = self::$lastError;
		}
		else
		{
			toLogError('ИСПРАВИТЬ: не выбрать источник курса btc_usd_rate_source');
		}

		if($btcUsdRate and !$parseRateError)
		{
			config('btc_usd_rate_btce', str_replace(',', '.', $btcUsdRate));
			config('btc_usd_rate_timestamp_btce', time());
		}
		else
		{
			echo "\n ".'error3.1: '.self::$parseRateError;
			return false;
		}

		if($rate = self::parseWexUsdRateSell())
		{
			config('usd_rur_sell_wex', str_replace(',', '.', $rate));
			config('usd_rur_sell_wex_timestamp', time());
		}
		else
		{
			echo "\n ".'error3.2: '.self::$lastError;
			return false;
		}

		//test меняем на битфинекс
		/*
		if($rate = self::parseBtceLastPrice())
		{
			config('btc_usd_rate_btce', str_replace(',', '.', $rate));
			config('btc_usd_rate_timestamp_btce', time());
		}
		else
		{
			echo "\n ".'error3.3: '.self::$lastError;
			return false;
		}
		*/

		config('updateRatesLastTimestamp', time());

		echo "\n done";
	}


	/**
	 * парсит и возвращает курс btc_usd
	 * @param string $pair
	 * @return float|false
	 */
	public static function parseBitfinexLastPrice($pair = 'btc_usd')
	{
		$api =  Bitfinex::getInstance();

		if($rate = $api->getLastPrice($pair))
		{
			return $rate;
		}
		else
		{
			self::$lastError = $api->errorMsg;
			toLogError('ошибка получения курса ClientCalc::parseBitfinexLastPrice:'.$rate.': '.$api->errorMsg.'  ИСПРАВИТЬ!!!');
			return false;
		}
	}

	/**
	 * предыдущий расчет(если есть)
	 * @return self
	 */
	public function getPrevCalc()
	{
		return self::model()->find([
			'condition'=>"`id` < {$this->id} AND `client_id`={$this->client_id}",
			'order'=>"`id` DESC",
			'limit'=>1,
		]);
	}

	/**
	 * приход клиента за интервал текущего расчета
	 * если у клиента нет предыдущего расчета то null
	 * @return float|null
	 */
	public function getStatsIn()
	{
		$prevCalc = $this->getPrevCalc();

		if(!$prevCalc)
		{
			//self::$lastError = 'нет предыдущего расчета';
			return null;
		}

		$timestampStart =  $prevCalc->date_add;
		$timestampEnd = $this->date_add;

		return $this->getClient()->statsIn($timestampStart, $timestampEnd);
	}

	/**
	 * @return string
	 */
	public function getStatsInStr()
	{
		$stats = $this->getStatsIn();

		if($stats === null)
			return '';
		else
			return formatAmount($stats, 0).' руб';
	}

	/**
	 * платежи date_add которых входит в текущий расчет но date_add_db не входит
	 * @return Transaction[]|null
	 */
	public function getLatePayments()
	{
		$prevCalc = $this->getPrevCalc();

		if(!$prevCalc)
		{
			//self::$lastError = 'нет предыдущего расчета';
			return null;
		}

		$timestampStart =  $prevCalc->date_add;
		$timestampEnd = $this->date_add;

		$tblAccount = Account::model()->tableSchema->name;
		$accountCond = "`account_id` IN(SELECT `id` FROM `".$tblAccount."` WHERE `type`='".Account::TYPE_IN."' AND `client_id`={$this->client_id} AND `date_check` > $timestampStart)";

		$transactions = Transaction::model()->findAll([
			'condition'=>"
					`type`='".Transaction::TYPE_IN."'
					AND `status`='".Transaction::STATUS_SUCCESS."'
					AND `date_add`>=$timestampStart and `date_add`<$timestampEnd
					AND `date_add_db`>=$timestampEnd
					AND $accountCond
					 ",
			'order'=>"`id` DESC",
		]);

		return $transactions;
	}


	public static function getStatusArr()
	{
		return [
			self::STATUS_NEW => 'Новый',
			self::STATUS_WAIT => 'В процессе',
			self::STATUS_DONE => 'Оплачен',
			self::STATUS_CANCEL => 'Отменен',
		];
	}

	/**
	 * кол-во расчетов со статусом new, wait
	 * @return int
	 */
	public static function getUnpaidCount()
	{
		return self::model()->count("`status` IN('".self::STATUS_NEW."','".self::STATUS_WAIT."')");
	}


	public function cancel()
	{
		if($this->status == self::STATUS_DONE and $this->btc_address)
		{
			self::$lastError = 'расчет уже оплачен, его невозможно отменить';
			return false;
		}
		else
		{
			$this->status = self::STATUS_CANCEL;

			toLogRuntime('отменен расчет '.$this->id.' для Cl'.$this->client_id.' на сумму '.$this->amount_rub.' (автор: '.$this->user->name.')');

			return $this->save();
		}
	}

	/**
	 * @param int $id
	 * @return self
	 */
	public static function getModelById($id)
	{
		return self::model()->findByPk($id);
	}

	/**
	 * меняет статус нового расчета на В работе
	 * @return bool
	 */
	public function changeStatusWait()
	{
		if($this->status == self::STATUS_NEW)
		{
			$this->status = self::STATUS_WAIT;

			if($this->save())
			{
				self::$msg = 'статус расчета изменен на '.self::getStatusArr()[self::STATUS_WAIT];
				return true;
			}
			else
				return false;
		}
		elseif($this->status == self::STATUS_WAIT)
			return true;
		else
		{
			self::$lastError = 'неверный статус расчета';
			return false;
		}
	}

	/**
	 * пометить расчет оплаченым
	 * @return bool
	 */
	public function changeStatusPay()
	{
		if($this->status != self::STATUS_WAIT)
		{
			self::$lastError = 'неверный статус расчета';
			return false;
		}

		$this->status = self::STATUS_DONE;
		return $this->save();
	}

	/**
	 * @return ManagerOrder[]
	 */
	public function getOrders()
	{
		return ManagerOrder::model()->findAll("`calc_id`={$this->id}");
	}

	/**
	 * @return string
	 */
	public function getBtcAddressShort()
	{
		if($this->btc_address)
			return substr($this->btc_address, 0, 5).'...'.substr($this->btc_address, -5);
		else
			return '';
	}

	public function deleteCalc()
	{
		if($this->status == self::STATUS_DONE and $this->btc_address)
		{
			self::$lastError = 'расчет уже оплачен, его невозможно удалить';
			return false;
		}
		else
		{
			if($this->delete())
			{
				toLogRuntime('удален расчет '.$this->id.' для Cl'.$this->client_id.' на сумму '.$this->amount_rub.' (автор: '.$this->user->name.')');
				return true;
			}
			else
				return false;
		}
	}

	public function getAddressShort()
	{
		$addressFull = ($this->ltc_address) ? $this->ltc_address : $this->btc_address;

		if($addressFull)
			return substr($addressFull, 0, 5).'...'.substr($addressFull, -5);
		else
			return '';
	}

	//последний ли это расчет у клиента
	public function getIsLast()
	{
		$lastCalc = $this->client->lastCalc;

		return $lastCalc->id === $this->id;
	}

	/**
	 * @param self[] $models
	 * @return array
	 */
	public static function getStats($models)
	{
		$result = [
			'amountRub' => 0,
			'amountUsd' => 0,
			'amountBtc' => 0,
		];

		foreach($models as $model)
		{
			$result['amountRub'] += $model->amount_rub;
			$result['amountUsd'] += $model->amount_usd;
			$result['amountBtc'] += $model->amount_btc;
		}

		return $result;
	}

	/**
	 * @param array $clientIds
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return self[]
	 */
	public static function getListByParams($clientIds, $timestampStart, $timestampEnd)
	{
		$condition = "`client_id` IN(".implode(',', $clientIds).")";
		$condition .= " AND `date_add`>=$timestampStart";
		$condition .= " AND `date_add`<$timestampEnd";

		return self::model()->findAll([
			'condition'=>$condition,
			'order' => "`id` DESC",
		]);
	}

	/**
	 * парсит и возвращает курс btc_usd
	 * @param string $pair
	 * @return float|false
	 */
	public static function parseExmoLastPrice($pair = 'BTC_USD')
	{
		$api = Yii::app()->exmoApi;

		/**
		 * @var ExmoApi $api
		 */

		if($rate = $api->getRate($pair, 'buy'))
		{
			return $rate;
		}
		else
		{
			self::$lastError = $api->error;
			toLogError('ошибка получения курса ClientCalc::parseExmoLastPrice:'.$rate.': '.$api->error.'  ИСПРАВИТЬ!!!');
			return false;
		}
	}

	/**
	 * @return array
	 */
	public static function getCurrentBtcUsdRateSource()
	{
		$source = config('btc_usd_rate_source');
		$sourceArr = self::getBtcUsdRateSourceArr();

		return [
			'id' => $source,
			'name' => $sourceArr[$source],
			'value' => config('btc_usd_rate_btce'),
		];
	}

	/**
	 * @return array
	 */
	public static function getBtcUsdRateSourceArr()
	{
		return [
			'bitfinex' => 'Bitfinex',
			'exmo' => 'Exmo',
		];
	}


	/**
	 * @param $source
	 * @return bool
	 */
	public static function setBtcUsdRateSource($source)
	{
		$sourceArr = self::getBtcUsdRateSourceArr();

		if(!isset($sourceArr[$source]))
		{
			self::$lastError = 'неверное значение';
			return false;
		}

		return config('btc_usd_rate_source', $source);
	}
}