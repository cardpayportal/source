<?php

/**
 * доверенные кошельки(переводы на них не помечаются как is_rat)
 * @property int id
 * @property string wallet
 * @property int date_add
 * @property string dateAddStr
 *
 */
class ProvedWallets extends Model
{
	const SCENARIO_ADD = 'add';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'wallet' => 'Кошелек',
			'date_add' => 'Добавлен',
		);
	}

	public function tableName()
	{
		return '{{proved_accounts}}';
	}

	public function beforeValidate()
	{
		if($this->scenario == self::SCENARIO_ADD and !$this->date_add)
			$this->date_add = time();

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('wallet, date_add', 'required'),
			array('wallet', 'unique', 'className'=>__CLASS__, 'attributeName'=>'wallet', 'on'=>self::SCENARIO_ADD),
			array('date_add', 'numerical'),
		);
	}

	public function getDateAddStr()
	{
		if($this->date_add)
			return date('d.m.Y', $this->date_add);
	}
}