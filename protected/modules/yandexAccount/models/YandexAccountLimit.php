<?php
/**
 * @property int id
 * @property int in_amount_per_month
 * @property int limit
 * @property int month
 * @property int year
 * @property int wallet_id
 * @property int date_add
 * @property int date_calc
 * @property float out_amount_per_month
 */
class YandexAccountLimit extends Model
{
	const SCENARIO_ADD = 'add';


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
		return '{{yandex_account_limit}}';
	}

	public function beforeValidate()
	{
		return parent::beforeSave();
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}

	public function rules()
	{
		return [
			//['wallet', 'unique', 'className'=>__CLASS__, 'attributeName'=>'wallet', 'allowEmpty'=>false],
			['wallet_id', 'exist', 'className'=>'YandexAccount', 'attributeName'=>'id', 'allowEmpty'=>true],
			['id, in_amount_per_month, limit, month, year, date_add, date_calc, out_amount_per_month', 'safe'],
		];
	}

	/**
	 * остаток лимита на кошельке
	 * если месячный лимит больше максимального дневного то чекать оставшийся дневной
	 * 	иначе отображать оставшийся месячный
	 */
	public function getLimitIn()
	{
		$cfg = cfg('yandexAccount');


		$statsDay = $this->getTransactionStats(Tools::startOfDay(), time());
		$limitInDay = $cfg['limitInDay'] - $statsDay['amountIn'];

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $cfg['limitInMonth'] - $statsMonth['amountIn'];


		return floor(min($limitInDay, $limitInMonth));
	}


	/**
	 * остаток лимита на кошельке дневной
	 * пользователи переливают часто, нужно было разделить
	 */
	public function getLimitInDayStr()
	{
		$cfg = cfg('yandexAccount');

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $cfg['limitInMonth'] - $statsMonth['amountIn'];

		$statsDay = $this->getTransactionStats(Tools::startOfDay(), time());
		$limitInDay = $cfg['limitInDay'] - $statsDay['amountIn'];

		$limit = min($limitInMonth, $limitInDay);

		if($limit < 30000)
			return '<span class="error">'.formatAmount($limit, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limit, 0).'</span>';
	}

	/**
	 * остаток лимита на кошельке месячный
	 * пользователи переливают часто, нужно было разделить
	 */
	public function getLimitInMonthStr()
	{
		$cfg = cfg('yandexAccount');

		$statsMonth = $this->getTransactionStats(Tools::startOfMonth(), time());
		$limitInMonth = $cfg['limitInMonth'] - $statsMonth['amountIn'];

		if($limitInMonth < 30000)
			return '<span class="error">'.formatAmount($limitInMonth, 0).'</span>';
		else
			return '<span class="success">'.formatAmount($limitInMonth, 0).'</span>';
	}


	/**
	 * @return YandexTransaction[]
	 */
	public function getTransactionsManager()
	{
		$transactions = YandexTransaction::model()->findAll([
			'condition' => "`account_id`='{$this->id}' AND `date_add`>{$this->date_pick} AND `direction`='".YandexTransaction::DIRECTION_IN."' and `status`='".YandexTransaction::STATUS_SUCCESS."' AND `client_id`='{$this->client_id}'  AND `user_id`='{$this->user_id}'",
			'order' => "`date_add` DESC",
		]);

		return $transactions;
	}

	/**
	 * все аккаунты по дате добавления
	 * @return self[]
	 */
	public static function getModels()
	{
		return self::model()->findAll([
			'condition' => "",
			'order' => "`date_add` DESC",
		]);
	}

	public function getDateAddStr()
	{
		if($this->date_check)
			return date('d.m.Y H:i', $this->date_add);
		else
			return '';
	}


	public function getDateCalcStr()
	{
		if($this->date_calc)
			return date('d.m.Y H:i', $this->date_add);
		else
			return '';
	}


	/**
	 * @param array $params
	 * @return self
	 */
	public static function modelByAttribute(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel($params)
	{
		return self::model()->findByAttributes($params);
	}
}