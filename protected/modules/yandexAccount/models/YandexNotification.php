<?php
/**
 * @property int id
 * @property int account_id
 * @property int date_add
 * @property float limit_in
 * @property string notified
 * @property int is_global_notified  //флаг уведомления в телеграмм о перелимите для глобалов
 */
class YandexNotification extends Model
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
		return '{{yandex_notification}}';
	}

	public function beforeValidate()
	{
		return parent::beforeSave();
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();
		}

		return parent::beforeSave();
	}

	public function rules()
	{
		return [
			['id, account_id, date_add, limit_in, is_global_notified', 'safe'],
		];
	}

	public function setNotified()
	{
		$this->is_global_notified = 1;
		return $this->save();
	}
}