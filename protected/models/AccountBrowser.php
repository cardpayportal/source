<?php
class AccountBrowser extends Model
{
	const SCENARIO_ADD = 'add';

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
		return '{{account_browser}}';
	}

	public function rules()
	{
		return array(
			array('group_id,value', 'safe')
		);
	}

	protected function beforeSave()
	{
		return parent::beforeSave();
	}

}