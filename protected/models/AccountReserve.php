<?php
/**
 * @property int id
 * @property int account_id
 * @property float amount
 * @property int date_add
 * @property Account account
 *
 */
class AccountReserve extends Model
{
	const SCENARIO_ADD = 'add';
	const AMOUNT_MIN = 0;
	const AMOUNT_MAX = 50000;
	const CLEAN_INTERVAL = 900;	//очищать старые резервы

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'account_id' => 'account_id',
			'amount' => 'amount',
			'date_add' => 'date_add',
		);
	}

	public function tableName()
	{
		return '{{account_reserve}}';
	}

	public function beforeValidate()
	{
		$this->amount = str_replace(',', '.', $this->amount);

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('account_id', 'exist', 'className'=>'Account', 'attributeName'=>'id', 'allowEmpty'=>false),
			array('amount', 'numerical', 'min'=>self::AMOUNT_MIN, 'max'=>self::AMOUNT_MAX, 'allowEmpty'=>false),
			array('amount', 'limitValidator'),
			array('date_add', 'numerical', 'allowEmpty'=>true),
		);
	}

	public function limitValidator()
	{
		$account = $this->getAccount();

		if($account->limit_in - Account::BALANCE_MIN >= $this->amount)	//погрешность на минимальный баланс
			return true;
		else
		{
			$this->addError('amount', 'невозможно зарезервировать '.$this->amount.' руб на '.$account->login);
			return false;
		}

	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}

	/**
	 * @return Account
	 */
	public function getAccount()
	{
		return Account::model()->findByPk($this->account_id);
	}

	/**
	 * удаляет старые резервы
	 * @return bool
	 */
	public static function cleanOldReserve()
	{
		$date = time() - self::CLEAN_INTERVAL;

		$models = self::model()->findAll(array(
			'condition'=>"`date_add` < $date",
		));

		/**
		 * @var self[] $models
		 */

		foreach($models as $model)
		{
			if($model->delete())
				toLogStoreApi('очистка резерва для кошелька '.$model->account->login);
			else
			{
				toLogStoreApi('ошибка очистки резерва id='.$model->id);
				return false;
			}
		}

		return true;
	}
}