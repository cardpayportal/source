<?php

class PayPass extends Model
{
	const SCENARIO_ADD = 'add';
	const SCENARIO_CHANGE_PASS = 'changePass';

	public $generatedPass;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'user_id' => 'Пользователь',
			'pass' => 'Пароль',
		);
	}

	public function tableName()
	{
		return '{{pay_pass}}';
	}

	public function beforeValidate()
	{

		return parent::beforeValidate();

	}

	public function rules()
	{
		return array(
			array('user_id', 'exist', 'className' => 'User', 'attributeName' => 'id', 'allowEmpty' => false),
			array('user_id', 'unique', 'className' => __CLASS__, 'attributeName' => 'user_id', 'on' => self::SCENARIO_ADD),
			array('pass', 'length', 'max' =>100, 'allowEmpty' => false, 'on' => self::SCENARIO_CHANGE_PASS),
		);
	}

	public function beforeSave()
	{
		if ($this->scenario == self::SCENARIO_ADD)
		{
			$pass = self::passGenerator();
			$this->generatedPass = $pass;
			$this->pass = self::hashPass($pass);
		}

		return parent::beforeSave();
	}

	private static function passGenerator()
	{
		return Tools::generateCode('ABCDEFGHIJKLMNOPQRSTUVabcdefghijklmnopqrst0123456789', rand(5,10));
	}

	public static function hashPass($pass)
	{
		return md5($pass);
	}

	public function changePass()
	{
		$pass = self::passGenerator();
		$this->pass = self::hashPass($pass);

		if($this->save())
			return $pass;
		else
			return false;
	}

	public static function check($pass, $userId)
	{
		if($model = self::model()->findByAttributes(array('user_id'=>$userId)))
		{
			return $model->pass === self::hashPass($pass);
		}

		return false;
	}

}