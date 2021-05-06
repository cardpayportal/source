<?php
/**
 * для хранения кзт-платежей и сопоставления их конвертациям в рублях
 * @property  int $id
 * @property  Account $account
 * @property  string $type
 * @property  int $account_id
 * @property  User $user
 * @property  string $error
 * @property  int $date_add
 * @property  float $amount
 * @property  string $qiwi_id
 * @property  string $status
 * @property  string $comment
 * @property  string $wallet
 * @property  string $amountStr
 * @property  string $statusStr
 * @property  string $dateAddStr
 * @property  bool $isBadComment
 * @property  Transaction transactionModel
 * @property  int date_add_db
 * @property  string dateAddDbStr

 */
class TransactionKzt extends Transaction
{

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
		return '{{transaction_kzt}}';
	}

	public function rules()
	{
		//если уник по qiwi_id, то у одного кошеля эта транзакция входящая а у другого исходящая
		return array(
			array('account_id', 'exist', 'className'=>'Account', 'attributeName'=>'id', 'allowEmpty'=>false),
			array('type', 'in', 'range'=>array_keys(self::typeArr())),
			array('amount', 'numerical', 'allowEmpty'=>false),
			array('status', 'in', 'range'=>array_keys(self::statusArr())),
			array('comment, error, wallet, qiwi_id, commission,date_add_db', 'safe'),
		);
	}

	public function beforeValidate()
	{

		$this->amount = str_replace(',', '.', $this->amount);
		$this->commission = str_replace(',', '.', $this->commission);

		return Model::beforeValidate();
	}

	public function beforeSave()
	{
		//пометить кошель is_kzt
		$account = Account::modelByPk($this->account_id);

		if(!$account->is_kzt)
		{
			Account::model()->updateByPk($account->id, [
				'is_kzt' => 1,
			]);
		}

		return parent::beforeSave();
	}

	/**
	 * модель из Transaction которая является конвертацией из тенге в рубли
	 * @return Transaction
	 */
	public function getTransactionModel()
	{
		return Transaction::model()->find("`account_id`={$this->account_id} AND `convert_id`={$this->id}");
	}

	public function getDateAddDbStr()
	{
		return ($this->date_add_db) ? date('d.m.Y H:i', $this->date_add_db) : '';
	}


}