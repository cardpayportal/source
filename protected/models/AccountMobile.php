<?php

/**
 * данные для авторизации мобильных приложений
 * @property string token
 * @property string access_token
 * @property int access_token_expire
 * @property string device_pin
 * @property string device_id
 */
class AccountMobile extends Model
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{account_mobile}}';
	}

	public function rules()
	{
		return [
			['device_id, device_pin, token, access_token, access_token_expire', 'safe'],
		];
	}

	/**
	 * @return self
	 */
	public static function modelById($id)
	{
		return self::model()->findByPk($id);
	}

	/**
	 * временный костыль пока не придумаем как нормально апдейтить
	 */
	public static function updateAccessToken($deviceId, $accessToken, $expire)
	{
		$model = self::model()->find("`device_id`='$deviceId'");

		self::model()->updateByPk($model->id, [
			'access_token'=>$accessToken,
			'access_token_expire'=>$expire,
		]);
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function modelByAttribute(array $params)
	{
		return self::model()->findByAttributes($params);
	}

}