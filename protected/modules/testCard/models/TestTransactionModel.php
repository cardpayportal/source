<?php
/**
 *
 * @property int id
 * @property string wallet_id
 * @property float amount
 * @property int client_id
 * @property int user_id
 * @property int date_add
 * @property string error
 * @property string comment
 * @property string status
 * @property string dateAddStr
 *
 */

class TestTransactionModel extends Model
{
	const STATUS_SUCCESS = 'success';
	const STATUS_WAIT = 'wait';
	const STATUS_ERROR = 'error';

	const SCENARIO_ADD = 'add';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return [
		];
	}

	public function rules()
	{
		return [
			[
				'wallet, card_name, client_id, user_id, date_add, error, hidden, comment, status',
				'safe'
			],
		];

	}

	public function tableName()
	{
		return '{{test_transaction}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();

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
		];
	}

	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	public static function getAll()
	{
		return self::model()->findAll();
	}

	/**
	 * @param int $userId			стата либо по юзеру либо по клиенту
	 * @param int $storeId
	 * @param int $timestampStart
	 * @param int $timestampEnd
	 * @param bool $successOnly
	 * @return self[]
	 */
	public static function getModels($timestampStart, $timestampEnd, $clientId=0, $userId=0, $successOnly = true)
	{
		$userId = intval($userId);
		$clientId = intval($clientId);

		$timestampStart = intval($timestampStart);
		$timestampEnd = intval($timestampEnd);

		if($userId)
			$condition = " AND `user_id`='$userId'";

		if($clientId)
			$condition .= " AND `client_id`='$clientId'";

		if($successOnly)
			$condition .= " AND `status`='".self::STATUS_SUCCESS."'";

		$models = self::model()->findAll([
			'condition'=>"
				`date_add`>=$timestampStart AND `date_add`<$timestampEnd
				 $condition
			",
			'order'=>"`date_add` DESC",
		]);

		return $models;
	}


	/**
	 * @param self[] $models
	 * @return array
	 *
	 * важно передавать модели по киви и яду отдельными запросами
	 */
	public static function getStatsUser($models)
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
}



