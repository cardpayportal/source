<?php

/**
 * todo: очистка старых запросов
 * Class ManagerApiRequest
 * @property int user_id
 * @property int request_id
 * @property string body
 * @property string response
 * @property string raw_response
 * @property string error
 * @property int date_add
 * @property string method
 */

class ManagerApiRequest extends Model
{
	const SCENARIO_ADD = 'add';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{

	}

	public function tableName()
	{
		return '{{manager_api_request}}';
	}

	public function rules()
	{
		return [

		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}

	//очистка старых запросов
	public static function clean()
	{
		$interval = 24*3600;
		$lastOptimization = config('managerApiOptimizationTimestamp')*1;

		if(time() - $lastOptimization < $interval)
			return false;

		$timestampMin = time() - $interval;

		$delCount = self::model()->deleteAll("`date_add` < $timestampMin");
		$optimizeRes = Yii::app()->db->createCommand("OPTIMIZE TABLE ".self::model()->tableSchema->name)->execute();

		config('managerApiOptimizationTimestamp', time());
		toLogRuntime('очистка ManagerApiRequest: удалено записей '.$delCount.', оптимизация: '.$optimizeRes);
	}
}