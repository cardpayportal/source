<?php

/**
 * Class Model
 * @property string scenario
 */
class Model extends CActiveRecord
{	
	public static $errors;
	public static $lastError;
	public static $lastErrorCode;
	public static $someData; //некоторые данные(буфер), сюда можно записать все что угодно
	public static $msg;
	const ERROR_EXIST = 'exist';	//если модель существует не засорять лог
	const SCENARIO_ADD = 'add';


	
	public function save($runValidation = true, $attributes = NULL)
	{
		$result = parent::save($runValidation, $attributes);

		$this->setLastError();
		
		return $result;
	}
	
	public function validate($attributes = NULL, $clearErrors = true)
	{
		$result = parent::validate($attributes, $clearErrors);
		
		$this->setLastError();
		
		return $result;
	}
	
	private function setLastError()
	{
		self::$errors = $this->getErrors();
			
		if(self::$errors)
		{
			self::$lastError = current(current(self::$errors));

			if(self::$lastErrorCode != self::ERROR_EXIST)
				toLogError('Model::lastError ('.__CLASS__.'): '.self::$lastError.'('.Tools::arr2Str($this).')');
		}
	}

	/*
	 *  имя атрибута из модели
	 */
	public function attributeLabel($key)
	{
		$arr = $this->attributeLabels();

		return $arr[$key];
	}
}