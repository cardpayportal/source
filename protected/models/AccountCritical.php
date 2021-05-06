<?php

/**
 * Class AccountCritical
 * @property int id
 * @property int account_id
 * @property Account account
 * @property int client_id
 *
 *
 */
class AccountCritical extends Model
{
	const SCENARIO_ADD = 'add';

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{account_critical}}';
	}

	public function beforeSave()
	{
		return parent::beforeSave();
	}

	public function rules()
	{
		return array(
			['account_id', 'exist', 'className'=>'Account', 'attributeName'=>'id', 'allowEmpty'=>false],
			['account_id', 'unique', 'className'=>__CLASS__, 'attributeName'=>'id',
				'on'=>self::SCENARIO_ADD, 'allowEmpty'=>false,],
			['account_id', 'typeValidator', 'on'=>self::SCENARIO_ADD],
			['client_id', 'safe'],

		);
	}

	public function typeValidator()
	{
		if($this->getAccount()->type != Account::TYPE_IN)
			$this->addError('account_id', 'критический кошелек должен быть входящим');
	}

	/**
	 * @return Account
	 */
	public function getAccount()
	{
		return Account::modelByPk($this->account_id);
	}

	/**
	 * все критические кошельки
	 * @param int $clientId
	 * @return Account[]
	 */
	public static function getAccounts($clientId = 0)
	{
		$clientId *= 1;

		$condition = '';

		if($clientId)
			$condition = "`client_id`=$clientId";

		$models = self::model()->findAll($condition);

		/**
		 * @var self[] $models
		 */

		$result = [];

		foreach($models as $model)
			$result[] = $model->account;

		return $result;
	}

	/**
	 * @param array $params ['accountId'=>true]
	 */
	public static function setAccounts($params)
	{
		prrd($params);
	}
}