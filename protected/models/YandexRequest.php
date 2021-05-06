<?php

/**
 * Яндекс ответы
 *
 * Class YandexRequest
 * @property string json
 * @property string wallet
 * @property int client_id
 * @property string unaccepted
 * @property string label
 *
 */

class YandexRequest extends Model
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{yandex_request}}';
	}

	public function rules()
	{
		return [
			['json, wallet, client_id, unaccepted, label', 'safe'],
		];
	}

	protected function beforeSave()
	{
		return parent::beforeSave();
	}

	protected function afterSave()
	{
		parent::afterSave();
	}

	/**
	 * @param int $clientId
	 * @return self[]
	 */
	public static function getModels($wallet)
	{
		$userCond = " `wallet`='$wallet' ";

		$models = self::model()->findAll([
			'condition'=>"
				$userCond
			",
			'order'=>"`id` DESC",
			'limit' => 500
		]);

		return $models;
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
	 * @return self
	 */
	public static function getModelById($id)
	{
		return self::model()->findByPk($id);
	}

	public static function add($params)
	{
		$model = new YandexRequest;
		$model->json = arr2str($params);
		$model->unaccepted = $params['unaccepted'];
		$model->wallet = $params['wallet'];
		$model->label = $params['label'];

		return $model->save();
	}
}