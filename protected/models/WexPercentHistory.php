<?php
/**
 * @property int id
 * @property float value
 * @property int date_add
 */
class WexPercentHistory extends Model
{
	const SCENARIO_ADD = 'add';

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{wex_percent_history}}';
	}

	public function rules()
	{
		return [
			['value', 'numerical', 'min'=>0, 'max'=>1],
		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = time();

		return parent::beforeSave();
	}

	public static function updatePercent($value)
	{
		$lastModel = self::model()->find([
			'condition'=>'',
			'order'=>"`id` DESC",
		]);

		/**
		 * @var self $lastModel
		 */

		if(!$lastModel or $lastModel->value != $value)
		{
			$model = new self;
			$model->scenario = self::SCENARIO_ADD;
			$model->value = $value;

			if($model->save())
				return true;
			else
				toLogError('ошибка обновления истории процента!!!');
		}

		return true;
	}

	/*
	 * получение значения процента по дате
	 * @param int $date
	 * @return float|bool
	 */
	public static function getPercent($timestamp)
	{
		$model = self::model()->find([
			'condition' => "`date_add` <= $timestamp",
			'order' => "`id` DESC",
		]);

		/**
		 * @var self $model
		 */

		if($model)
			return $model->value;
		else
			return false;
	}

}