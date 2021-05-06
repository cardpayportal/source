<?php

/**
 * Class StoreApiRequest
 * сохранения EcommApi-параметров запроса и ответ на него
 *
 *  @property  int id
 *  @property  int store_id
 *  @property  int request_number
 *  @property  string method
 *  @property  string params
 *  @property  string answer
 *  @property  int error_code
 *  @property  int date_add microtime запроса
 *  @property  string dateAddStr
 */
class EcommApiRequest extends Model
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
			'store_id' => 'ID Магазина',
			'request_number' => 'Номен запроса',
			'method' => 'Название метода',
			'params' => 'Весь запрос целиком',
			'answer' => 'Ответ',
			'date_add' => 'Дата запроса',
		);
	}

	public function tableName()
	{
		return '{{ecomm_api_request}}';
	}

	public function rules()
	{
		return array(
			//array('id', 'unique', 'className' => __CLASS__, 'attributeName' => 'id', 'on' => self::SCENARIO_ADD),
			array('store_id', 'exist', 'className' => 'StoreApi', 'attributeName' => 'store_id', 'allowEmpty'=>true),
			array('request_number', 'numerical', 'min'=>1, 'max'=>99999999999, 'allowEmpty'=>true),
			array('method', 'length', 'max'=>20, 'allowEmpty'=>true),
			array('params', 'length', 'max'=>60000, 'allowEmpty'=>false),
			array('answer', 'length', 'max'=>60000, 'allowEmpty'=>false),
			array('error_code', 'numerical', 'max'=>20, 'allowEmpty'=>false),
		);
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->date_add = Tools::microtime();

		return parent::beforeSave();
	}

	public function getDateAddStr()
	{
		if($this->date_add)
			return Tools::microtimeDate($this->date_add);
		else
			return '';
	}

	/**
	 * список моделей для отображения
	 * записывает в self::$someDate['stats'] статистику
	 * @param array $filter ['storeId'=>,'dateStart'=>'01.08.2001','dateEnd'=>'01.08.20017']
	 * @return self[]
	 */
	public static function getListModels(array $filter = array())
	{
		$limit = 1000;

		$storeId = $filter['storeId']*1;
		$timestampStart = strtotime($filter['dateStart']) * 10000;
		$timestampEnd = strtotime($filter['dateEnd']) * 10000;

		$conditionArr = array();

		if($storeId)
			$conditionArr[] = "`store_id` = '$storeId'";

		if($timestampStart)
			$conditionArr[] = "`date_add` >= $timestampStart";

		if($timestampEnd)
			$conditionArr[] = "`date_add` <= $timestampEnd";

		$conditionStr = implode(' AND ', $conditionArr);

		$models = self::model()->findAll(array(
			'condition'=>$conditionStr,
			'order'=>"`date_add` DESC",
			'limit'=>$limit,
		));

		/**
		 * @var self[] $models
		 */

		self::$someData['stats'] = array(
			'count'=>count($models),
		);

		return $models;
	}

}