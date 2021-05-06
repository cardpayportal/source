<?php

/**
 * если при отправке платежа не получили ответ то создается запись, после обновления истории привязывается к Transaction
 * пока добавляем сюда только исходящие платежи
 * Class TransactionEstmated
 * @property int id
 * @property int is_actual 		если 1 - то еще не найдена в Transaction и кошелек еще не проверен
 * @property int account_id
 * @property string qiwi_id
 * @property float amount
 * @property float wallet		кому отправлена
 * @property int date_add_db
 * @property Transaction transaction
*/
class TransactionEstmated extends Model
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{transaction_estmated}}';
	}

	public function rules()
	{
		return [
			['is_actual, account_id, qiwi_id, amount, wallet, date_add_db', 'safe'],
		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->is_actual = 1;
			$this->date_add_db = time();
		}

		return parent::beforeSave();
	}

	/**
	 * @param string $qiwiId
	 * @param float $amount
	 * @param int $accountId
	 * @param string $wallet
	 */
	public static function add($qiwiId, $amount, $accountId, $wallet)
	{
		$searchCond = "`account_id`=$accountId AND `qiwi_id`='$qiwiId'";

		//toLog('test1 '."`account_id`=$accountId AND `qiwi_id`='$qiwiId'");

		if(
			!self::model()->count($searchCond)
			and !Transaction::model()->count($searchCond)
		)
		{
			//toLog('test2 '."`account_id`=$accountId AND `qiwi_id`='$qiwiId'");
			$model = new self;
			$model->scenario = self::SCENARIO_ADD;
			$model->qiwi_id = $qiwiId;
			$model->account_id = $accountId;
			$model->amount = $amount;
			$model->wallet = $wallet;
			$model->save();

		}

		//toLog('test3 '."`account_id`=$accountId AND `qiwi_id`='$qiwiId'");
	}




}