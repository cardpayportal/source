<?php
/**
 * @property int id
 * @property int account_id
 * @property int wex_id
 * @property string type
 * @property string status
 * @property float amount
 * @property string currency
 * @property string category
 * @property string comment
 * @property int date_add
 * @property int date_add_db
 * @property int user_id
 * @property int client_id
 *
 * @property string amountStr
 * @property float originalAmount	изначальная сумма(умножается на процент)
 * @property bool isLinked
 */
class TransactionWex extends Model
{

	const STATUS_SUCCESS = 'success';
	const STATUS_WAIT = 'wait';
	const STATUS_ERROR = 'error';

	const TYPE_IN = 'in';
	const TYPE_OUT = 'out';

	const CATEGORY_YANDEX = 'yandex';

	const CURRENCY_RUB = 'RUB';

	const SCENARIO_ADD = 'add';

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(

		);
	}

	public function tableName()
	{
		return '{{transaction_wex}}';
	}

	public function rules()
	{
		//todo: добавить валидацию на уникальность по wex_id - account_id
		//если уник по qiwi_id, то у одного кошеля эта транзакция входящая а у другого исходящая
		return array(
			['account_id', 'exist', 'className'=>'WexAccount', 'attributeName'=>'id', 'allowEmpty'=>false],
			['wex_id', 'length', 'min'=>3, 'max'=>200, 'allowEmpty'=>false],
			['type', 'in', 'range'=>array_keys(self::typeArr()), 'allowEmpty'=>false],
			['status', 'in', 'range'=>array_keys(self::statusArr()), 'allowEmpty'=>false],
			['amount', 'numerical', 'allowEmpty'=>false],
			['currency', 'in', 'range'=>array_keys(self::currencyArr()), 'allowEmpty'=>false],
			['category', 'in', 'range'=>array_keys(self::categoryArr()), 'allowEmpty'=>false],
			['comment', 'length', 'max'=>100, 'allowEmpty'=>true],
			['date_add', 'numerical', 'min'=>1, 'allowEmpty'=>false],
		);
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			if($this->comment)
				$this->comment = strip_tags($this->comment);

			if(!$wexAccount = WexAccount::getModel(['id'=>$this->account_id]))
			{
				$this->addError('account_id', 'ошибка получения векс аккаунта');
				return false;
			}

			$this->user_id = $wexAccount->user_id;
			$user = User::getUser($this->user_id);
			$this->client_id = $user->client_id;

			$this->date_add_db = time();
		}

		return parent::beforeSave();
	}

	public static function typeArr($key=false)
	{
		$result = array(
			self::TYPE_IN => 'приход',
			self::TYPE_OUT => 'расход',
		);

		if($key)
			return $result[$key];
		else
			return $result;
	}

	public static function currencyArr($key=false)
	{
		$result = array(
			self::CURRENCY_RUB => 'руб',
		);

		if($key)
			return $result[$key];
		else
			return $result;
	}

	public static function statusArr($key=false)
	{
		$result = array(
			self::STATUS_SUCCESS => 'успех',
			self::STATUS_WAIT => 'ожидание',
			self::STATUS_ERROR => 'ошибка',
		);

		if($key)
			return $result[$key];
		else
			return $result;
	}

	public static function categoryArr($key=false)
	{
		$result = array(
			self::CATEGORY_YANDEX => 'яндекс',
		);

		if($key)
			return $result[$key];
		else
			return $result;
	}

	public function getTypeStr()
	{
		if($this->type==self::TYPE_IN)
			return self::typeArr(self::TYPE_IN);
		elseif($this->type==self::TYPE_OUT)
			return self::typeArr(self::TYPE_OUT);
	}

	public function getStatusStr()
	{
		if($this->status==self::STATUS_SUCCESS)
			return '<font color="green">'.self::statusArr(self::STATUS_SUCCESS).'</font>';
		elseif($this->status==self::STATUS_WAIT)
			return '<font color="orange">'.self::statusArr(self::STATUS_WAIT).'</font>';
		elseif($this->status==self::STATUS_ERROR)
			return '<font color="red">'.self::statusArr(self::STATUS_ERROR).': '.$this->error.'</font>';
	}


	public static function getModel(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $clientId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @return self[]
	 */
	public static function getModels($timestampStart=0, $timestampEnd=0, $userId = 0, $clientId = 0)
	{
		$intervalMax = 3600 * 24 * 365;

		$userId *= 1;
		$clientId *= 1;

		$timestampStart *= 1;
		$timestampEnd *= 1;

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

		$models = self::model()->findAll([
			'condition'=>"
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				$userCond
			",
			'order'=>"`date_add` DESC",
		]);

		return $models;
	}

	/**
	 * @return float
	 */
	public function getOriginalAmount()
	{
		$yandexPercent = config('yandex_deposit_percent');
		$historyPercent = WexPercentHistory::getPercent($this->date_add);
		$wexPercent = ($historyPercent) ? $historyPercent : config('wex_yandex_percent');

		return round($this->amount / (1 - $wexPercent) / (1 - $yandexPercent));
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
	 * @param self[] $models
	 * @return array
	 */
	public static function getStats($models)
	{
		$result = [
			'count'=>0,
			'amount'=>0,	//оплаченные
			'allAmount',	//все
		];

		foreach($models as $model)
		{
			$result['count']++;

			$originalAmount = $model->originalAmount;

			if($model->status == self::STATUS_SUCCESS)
				$result['amount'] += $originalAmount;

			$result['allAmount'] += $originalAmount;
		}

		return $result;
	}

	public function getIsLInked()
	{
		if(YandexPay::model()->find("`wex_id`='{$this->wex_id}'"))
			return true;
		else
			return false;
	}
}