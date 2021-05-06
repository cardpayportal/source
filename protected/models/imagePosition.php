<?php

/**
 *
 * @property int id
 * @property string sms_input_pos
 * @property string button_pos
 * @property string status
 * @property string bank_name
 * @property string type
 */
class ImagePosition extends Model
{
	const SCENARIO_ADD = 'add';
	const TYPE_CLICK_OK = 'click ok';
	const TYPE_ENTER_SMS = 'enter sms';

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{image_position}}';
	}

	public function rules()
	{
		return [
			//['bank_name', 'unique', 'className'=>__CLASS__, 'attributeName'=>'bank_name', 'message'=>'такой банк уже есть'],
			['status, sms_input_pos, button_pos, bank_name, type', 'safe'],
		];
	}

	public static function typeArr()
	{
		return array(
			self::TYPE_ENTER_SMS => 'Ввод СМС и нажатие ОК',
			self::TYPE_CLICK_OK => 'Только нажатие ОК',
		);
	}

	/**
	 * @param $params
	 * добавляем картинку
	 */
	public static function add($params)
	{
		$model = new self;
		//$model->scenario = self::SCENARIO_ADD;
		$model->attributes = $params;
		$model->status = 'wait';
		if($model->save())
		{
			ImagePosition::$someData['picId'] = $model->id;
			return true;
		}
		else
			return false;
	}

	/**
	 * @param $params
	 *
	 * @return bool
	 * сохраняем проставленные координаты на картинке
	 */
	public static function setPosition($params)
	{
		$model = self::model()->findByAttributes(['id'=>$params['id']]);

		if($model)
		{
			if($params['sms_input_pos'])
				$model->sms_input_pos = $params['sms_input_pos'];

			if($params['button_pos'])
				$model->button_pos = $params['button_pos'];

			$model->type = $params['type'];

			$model->status = 'success';

			if($model->update())
				return true;
		}
		else
			return false;

	}

	/**
	 * @param $str
	 *
	 * @return array
	 * парсим координаты из строки
	 */
	public static function parsePosition($str)
	{
		if(preg_match('!x:(\d+) y:(\d+)!iu', $str, $mathes))
		{
			return [
				'x' => $mathes[1]*1,
				'y' => $mathes[2]*1,
			];
		}
	}

	public static function deleteItem($id)
	{
		$model = self::model()->findByPk($id);

		if($model and $model->delete())
		{
			shell_exec('rm '.DIR_ROOT.'img/'.$model->bank_name);
			return true;
		}
		else
			return false;
	}

	public static function getModels()
	{
		return self::model()->findAll([
//			'condition'=>"
//			",
			'order'=>"`id` DESC",
		]);
	}
}
