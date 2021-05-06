<?php

/**
 * Class ManagerOrderAccount
 *
 * связь ордеров с аккаунтами и суммами для них
 *
 * @property int id
 * @property int order_id
 * @property int account_id
 * @property int amount 		сумма которую надо залить(не больше)
 * @property int date_add 		дата добавления кошелька в заявку(чтобы посчитать сумму прихода)
 * @property Account account
 * @property ManagerOrder order
 * @property float amountIn
 * @property Transaction[] transactions
 * @property float amountMore	сколько осталось залить на акк еще

 */

class ManagerOrderAccount extends Model
{
	private $_accountCache = null;
	private $_amountInCache = null;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{manager_order_account}}';
	}

	public function rules()
	{
		return [
			['order_id', 'exist', 'className'=>'ManagerOrder', 'attributeName'=>'id', 'allowEmpty'=>false],
			['account_id', 'exist', 'className'=>'Account', 'attributeName'=>'id', 'allowEmpty'=>false],
			['amount', 'numerical', 'min'=>0, 'allowEmpty'=>false],
			['order_id', 'duplicateValidator', 'on'=>self::SCENARIO_ADD],
		];
	}

	public function duplicateValidator()
	{
		if(!$this->order_id or !$this->account_id)
			return false;

		if(self::model()->find("`order_id`={$this->order_id} AND `account_id`={$this->account_id}"))
		{
			$this->addError('order_id', 'пара order_id - account_id уже существует');
			return false;
		}

		return true;
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}


	/**
	 * @param int $orderId
	 * @return self[]
	 */
	public static function modelsByOrderId($orderId)
	{
		$orderId *= 1;
		return self::model()->findAll([
			'condition'=>"`order_id`=$orderId",
			'order'=>"`amount` DESC",
		]);
	}

	/**
	 * @return ManagerOrder
	 */
	public function getOrder()
	{
		return ManagerOrder::model()->findByPk($this->order_id);
	}

	/**
	 * @return Account
	 */
	public function getAccount()
	{
		if(!$this->_accountCache)
			$this->_accountCache = Account::model()->findByPk($this->account_id);

		return $this->_accountCache;
	}

	/**
	 * входящая сумма на этот кошель по данной заявке
	 * @return float
	 */
	public function getAmountIn()
	{
		if($this->_amountInCache)
			return $this->_amountInCache;

		$result = 0;

		$order = $this->getOrder();

		$timestampStart = $order->date_add;
		$timestampEnd = ($order->date_end) ? $order->date_end : time();

		$transactions = Transaction::model()->findAll([
			'select'=>"`amount`",
			'condition'=>"
				`type`='".Transaction::TYPE_IN."'
				AND `date_add`>=$timestampStart AND `date_add`<$timestampEnd
				AND `status`='".Transaction::STATUS_SUCCESS."'
				AND `account_id`={$this->account_id}
			",
		]);

		/**
		 * @var Transaction[] $transactions
		 */

		foreach($transactions as $transaction)
		{
			$result += $transaction->amount;
		}

		$this->_amountInCache = $result;

		return $result;
	}


	/**
	 * @return Transaction[]
	 */
	public function getTransactions()
	{
		$account = $this->getAccount();
		$order = $this->getOrder();

		$models = Transaction::model()->findAll([
			'condition' => "
				`account_id` = '{$account->id}'
				AND `type` = '".Transaction::TYPE_IN."'
				AND `user_id` = '{$account->user_id}'
				AND `date_add` >= {$order->date_add}
			",
			'order' => "`date_add` DESC",
		]);


		return $models;
	}

	/**
	 * @return float
	 */
	public function getAmountMore()
	{
		$result = $this->amount - $this->getAmountIn();

		return ($result > 0) ? $result : 0;
	}


}