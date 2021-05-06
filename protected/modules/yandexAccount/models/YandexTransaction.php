<?php
/**
 * @property int id
 * @property int account_id
 * @property float amount
 * @property string title
 * @property string comment
 * @property string status
 * @property int date_add
 * @property string error
 * @property string direction
 * @property string yandex_id
 * @property string user_id
 * @property string client_id
 */
class YandexTransaction extends Model
{
	const SCENARIO_ADD = 'add';

	const DIRECTION_IN = 'in';
	const DIRECTION_OUT = 'out';

	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';
	const STATUS_WAIT = 'wait';

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
		return '{{yandex_transaction}}';
	}

	public function rules()
	{
		return array(
			['id, account_id, amount, title, comment, status, date_add, error, direction, yandex_id', 'safe'],
			['user_id, client_id', 'safe'],
		);
	}

	/**
	 * @param $timestampStart
	 * @param $timestampEnd
	 * @param int $clientId
	 * @param int $userId
	 * @return array
	 */
	public static function getStats($timestampStart, $timestampEnd, $clientId = 0, $userId = 0)
	{
		$result = [
			'amountIn'=>0,
			'amountOut'=>0,
		];

		$timestampStart *= 1;
		$timestampEnd *= 1;

		$clientCond = ($clientId > 0) ? " AND `client_id`='$clientId'" : '';
		$userCond = ($userId > 0) ? " AND `user_id`='$userId'" : '';



		$transactions = self::model()->findAll([
			'condition' => "`date_add`>=$timestampStart AND `date_add`<$timestampEnd" . $clientCond . $userCond,
		]);

		/**
		 * @var YandexTransaction[] $transactions
		 */

		foreach($transactions as $trans)
		{
			if($trans->status === self::STATUS_SUCCESS)
			{
				if($trans->direction == self::DIRECTION_IN)
					$result['amountIn'] += $trans->amount;
				elseif($trans->direction == self::DIRECTION_OUT)
					$result['amountOut'] += $trans->amount;
			}
		}

		return $result;
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
			$cond[] = "`direction` = '".YandexTransaction::DIRECTION_IN."' AND `status`='".YandexTransaction::STATUS_SUCCESS."'";


		return self::model()->findAll([
			'condition' => "`date_add` >= $timestampStart AND `date_add` < $timestampEnd AND "
				.implode(" AND ", $cond),
			'order' => "`date_add` DESC",
		]);
	}

	public static function statusArr()
	{
		return [
			self::STATUS_WAIT => 'ожидание',
			self::STATUS_SUCCESS => 'оплачен',
			self::STATUS_ERROR => 'ошибка',
		];
	}

}