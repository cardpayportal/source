<?php

/**
 * @property int id
 * @property string email
 * @property int used
 */
class WalletSEmail extends Model
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{wallet_s_email}}';
	}

	public function rules()
	{
		return [
			['email', 'email', 'message'=>'неверный email'],
			['used', 'safe'],
		];
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel(array $params)
	{
		return self::model()->findByAttributes($params);
	}

	/**
	 * @return mixed
	 */
	public static function getFreeEmail()
	{
		return self::model()->findByAttributes(['used'=>0]);
	}

	public static function markUsed($id)
	{
		return self::model()->updateByPk($id, ['used'=>1]);
	}

	/**
	 * @return int
	 */
	public static function getFreeEmailCount()
	{
		return count(self::model()->findAllByAttributes(['used'=>0]));
	}

	/**
	 * @param string $emails
	 */
	public static function addMany($emailStr)
	{
		$result = [];

		if(!preg_match_all('!(.+?@\w+?\.\w+?)\s+!', $emailStr, $emailsArr))
		{
			self::$lastError = 'email не найдено';
			return false;
		}

		foreach($emailsArr[1] as $email)
		{
			$email = trim($email);

			if($model = self::model()->findByAttributes(['email'=>$email]))
			{
				continue;
			}

			$model = new self;
			$model->email = $email;
			$model->used = 0;

			if($model->save())
				$result[] = $model;
			else
			{
				if(YII_DEBUG)
					var_dump(self::$lastError);

				return false;
			}
		}

		return count($result);
	}

}