<?php

class Config extends Model
{	
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{config}}';
	}

	public function rules()
	{
		return array(
			array('name, value, descr', 'safe'),
		);
	}

	/**
	 * @param string $name
	 * @param bool|false $value
	 * @return string|bool
	 */
	public static function val($name, $value=false)
	{
		$model = self::model()->findByPk($name);
		
		if($value!==false)
		{
			if(!$model)
			{
				$className = __CLASS__;
				
				$model = new $className;
				$model->name = $name;
			}
			
			$model->value = $value;
			
			if($model->save(false))
				return true;
		}
		else
			return $model->value;
	}
}