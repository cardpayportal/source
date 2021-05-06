<?php
/**
 *
 * @property int id
 * @property string wallet
 * @property float balance
 * @property string card_number
 * @property int client_id
 * @property float total_limit
 * @property int user_id
 * @property int date_add
 * @property string error
 * @property int hidden
 * @property string status
 * @property string dateAddStr
 * @property Client client
 * @property User user
 *
 */

class TestCardModel extends Model
{
	const ERROR_OUT_OF_LIMIT = 'out_of_limit';
	const ERROR_LIMIT_OUT = 'limit_out'; //ошибка платежа превышен лимит исходящих транзакций

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
				'wallet, balance, total_limit, card_number, client_id, user_id, date_add, error, hidden, status',
				'safe'
			],
		];

	}

	public function tableName()
	{
		return '{{test_card}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();

	}

	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	public static function getAll()
	{
		return self::model()->findAll([
			'condition'=>"",
			'order'=>"`client_id`",
		]);
	}

	public function getCardNumberStr()
	{
		if(!$this->card_number)
			return  '';

		return substr($this->card_number, 0, 4)
		.' '.substr($this->card_number, 4, 4)
		.' '.substr($this->card_number, 8, 4)
		.' '.substr($this->card_number, 12, 4);
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

		$transactions = TestTransactionModel::model()->findAll([
			'condition' => "
				`wallet_id` = '{$this->id}' AND `status` = '".TestTransactionModel::STATUS_SUCCESS."'
				AND `date_add` >= $timestampStart and `date_add` < $timestampEnd
				AND `client_id`='{$this->client_id}' AND `user_id`='{$this->user_id}'
			",
		]);

		/**
		 * @var TestTransactionModel[] $transactions
		 */

		foreach($transactions as $trans)
		{
//			if($trans->direction == YandexTransaction::DIRECTION_IN)
				$result['amountIn'] += $trans->amount;
//			elseif($trans->direction == YandexTransaction::DIRECTION_OUT)
//				$result['amountOut'] += $trans->amount;
		}

		return $result;
	}

	/**
	 * остаток лимита на кошельке дневной
	 * пользователи переливают часто, нужно было разделить
	 */
	public function getLimitInDayStr()
	{
		$cfg = cfg('yandexAccount');

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $this->total_limit - $statsMonth['amountIn'] - $this->balance;
		$limitOutMonth = $this->total_limit - $statsMonth['amountOut'] - $this->balance;

		$statsDay = $this->getTransactionStats(Tools::startOfDay(), time());
		$limitInDay = $cfg['limitInDay'] - $statsMonth['amountIn'] - $this->balance;

		$limit = min($limitInMonth, $limitOutMonth, $limitInDay);

		if($limit < 30000)
			return '<span class="error">'.formatAmount($limit, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limit, 0).'</span>';
	}

	public function getBalanceStr()
	{
		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		return '<span class="success">'.formatAmount($statsMonth['amountIn'], 0).'</span>';
	}

	public function getTotalAmount()
	{
		$statsMonth = $this->getTransactionStats($this->date_add, time());
		return '<span class="success">'.formatAmount($statsMonth['amountIn'], 0).'</span>';
	}

	/**
	 * остаток лимита на кошельке месячный
	 * пользователи переливают часто, нужно было разделить
	 */
	public function getLimitInMonthStr()
	{
		$cfg = cfg('yandexAccount');

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $this->total_limit - $statsMonth['amountIn'] - $this->balance;
		$limitOutMonth = $this->total_limit - $statsMonth['amountOut'] - $this->balance;

		$limitMonth = min($limitInMonth, $limitOutMonth);

		if($limitMonth < 30000)
			return '<span class="error">'.formatAmount($limitMonth, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limitMonth, 0).'</span>';
	}

	/**
	 * @return TestTransactionModel[]
	 */
	public function getTransactionsManager()
	{
		$transactions = TestTransactionModel::model()->findAll([
			'condition' => "`wallet_id`='{$this->id}' and `status`='".TestTransactionModel::STATUS_SUCCESS."' AND `client_id`='{$this->client_id}'  AND `user_id`='{$this->user_id}'",
			'order' => "`date_add` DESC",
		]);

		return $transactions;
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
			'order' => "`date_add` DESC",
		]);
	}
}