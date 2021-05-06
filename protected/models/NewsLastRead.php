<?php

/**
 * Последняя новость, которую читал пользователь
 * @property int id
 * @property int user_id
 * @property int news_id
 */
class NewsLastRead extends Model
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
			'user_id' => 'ID пользователя',
			'news_id' => 'ID новости',
		);
	}

	public function tableName()
	{
		return '{{news_last_read}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('user_id', 'unique', 'className' => __CLASS__, 'attributeName' => 'user_id', 'on' => self::SCENARIO_ADD, 'allowEmpty'=>false),
			array('news_id', 'exist', 'className' => 'News', 'attributeName' => 'id', 'allowEmpty' => false),
		);
	}

	public function beforeSave()
	{

		return parent::beforeSave();
	}

}