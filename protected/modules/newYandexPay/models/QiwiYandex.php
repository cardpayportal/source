<?php

/**
 * Class QiwiYandex
 * @property int id
 * @property int user_id
 * @property int client_id
 * @property string url
 * @property float amount
 * @property string status
 * @property int date_add
 * @property string mark
 * @property string statusStr
 * @property string dateAddStr
 * @property User user
 * @property Client client
 * @property int date_pay
 * @property string datePayStr
 * @property bool created_by_api
 * @property string amountStr
 * @property string wallet
 * @property string error
 */
class QiwiYandex extends Model
{
	const STATUS_WAIT = 'wait';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';

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
		return '{{qiwi_yandex}}';
	}

	public function rules()
	{
		return [
			['user_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>false],
			['url', 'length', 'max'=>255, 'allowEmpty'=>false],
			['amount', 'numerical', 'min'=>1, 'max'=>999999, 'allowEmpty'=>false,
				'on'=>self::SCENARIO_ADD],
			['date_add, date_pay, created_by_api, wallet,created_by_api', 'safe'],
			['mark', 'length', 'max'=>50],
		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();
			$this->client_id = $this->getUser()->client_id;
			$this->status = self::STATUS_WAIT;
		}

		$this->mark = strip_tags($this->mark);


		return parent::beforeSave();
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	public static function statusArr()
	{
		return [
			self::STATUS_WAIT => 'в ожидании',
			self::STATUS_SUCCESS => 'оплачено',
			self::STATUS_ERROR => 'ошибка',
		];
	}

	//очистка старых заявок
	public static function clean()
	{
		$cfg = cfg('qiwiYandex');
		$timestampMin = time() - $cfg['cleanInterval'];
		$error = 'canceled';

		$models = self::model()->findAll("`date_add` < $timestampMin AND `status`='".self::STATUS_WAIT."'");
		/**
		 * @var self[] $models
		 */

		foreach($models as $model)
		{
			$model->error = $error;
			$model->status = self::STATUS_ERROR;

			$model->save();
		}
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
			'condition'=>"",
			'order'=>"`date_add` DESC",
		]);

		return $models;
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

	/**
	 * @return string
	 */
	public function getStatusStr()
	{
		return self::statusArr()[$this->status];
	}

	/**
	 * @return string
	 */
	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
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
	 * @return User
	 */
	public function getUser()
	{
		if(!$this->_userCache)
			$this->_userCache = User::getUser($this->user_id);

		return $this->_userCache;
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
		$result = ($this->amount > 0) ? formatAmount($this->amount, 2).' RUB' : '';

		return $result;
	}




}