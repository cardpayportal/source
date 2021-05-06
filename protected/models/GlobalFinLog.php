<?php

/**
 * @property int id
 * @property string msg
 * @property int user_id
 * @property int date_add
 * @property User user
 * @property string msgShort
 */

class GlobalFinLog extends Model
{
	const SCENARIO_ADD = 'add';
	const MSG_SHORT_LENGTH = 80;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'msg' => 'Сообщение',
			'user_id'=>'Пользователь',
			'date_add' => 'Дата',
		);
	}

	public function tableName()
	{
		return '{{global_fin_log}}';
	}

	public function beforeValidate()
	{
		if ($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('msg, user_id, date_add', 'required'),
			array('msg', 'length', 'min' => 3, 'max'=>255),
			array('user_id', 'exist', 'className'=>'User', 'attributeName'=>'id'),
		);
	}

	public function getDateAddStr()
	{
		return ($this->date_add) ? date('d.m.Y H:i', $this->date_add) : '';
	}

	public function getMsgShort()
	{
		return Tools::shortText($this->msg, self::MSG_SHORT_LENGTH);
	}

	/**
	 * список по дате убывания
	 * @return GlobalFnLog[]
	 */
	public static function getList($limit=0)
	{
		$params = array(
			'condition'=>"",
			'order'=>"`date_add` DESC",
		);

		if($limit)
			$params['limit'] = $limit;

		return self::model()->findAll($params);
	}

	/**
	 * @return User
	 */
	public function getUser()
	{
		return User::getUser($this->user_id);
	}

	public static function add($msg, $userId)
	{
		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->attributes = array(
			'msg'=>Tools::shortText($msg, 250),
			'user_id'=>$userId,
		);

		$result = $model->save();

		if(!$result)
			toLog('ошибка сохранения лога GlobalFInLog '.Tools::shortText($model::$lastError, 200));

		return $result;
	}


}