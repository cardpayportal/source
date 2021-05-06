<?php
/**
 *
 * @property string login
 * @property string card_number
 * @property int date_add
 *
 */

class MerchantCard extends Model
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
		);
	}

	public function rules()
	{
		return [
			['login, card_number, date_add', 'safe'],
		];
	}


	public function tableName()
	{
		return '{{merchant_card}}';
	}

	public function beforeValidate()
	{
		return parent::beforeValidate();

	}

	/**
	 * @param $str
	 * @param $userId
	 *
	 * @return int
	 * добавляем информацию по картам
	 */
	public static function addManyQiwiCard($str, $userId)
	{
		//$str = '79042632509-4693957580367006
		//		79534449649-4693957580394935
		//		79968036210-4693957580361579';

		$user = User::getUser($userId);

		if($user->role !== User::ROLE_ADMIN and !$user->is_wheel)
		{
			self::$lastError = 'может изменять рулевой или админ';
			return false;
		}

		$countAdd = 0;
		if(preg_match_all(cfg('regExpQiwiCardAdd'), $str, $matches))
		{
			foreach($matches[1] as $key=>$number)
			{
				$model = new MerchantCard;
				$model->login = trim($matches[1][$key]);
				$model->card_number = trim($matches[2][$key]);
				$model->date_add = time();
				if(!MerchantCard::model()->findByAttributes(['login'=>$model->login]))
				{
					$model->save();
					$countAdd++;
				}
				else
				{
					$errorMsg = 'Номер карты уже существует';
					toLogError($errorMsg);
				}
			}
		}

		return $countAdd;
	}

	public static function getAll()
	{
		return self::model()->findAll();
	}
}