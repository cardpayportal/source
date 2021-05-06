<?php
/**
 * таблица связи Account <=> Proxy
 * @property int client_id
 * @property int group_id
 * @property int proxy_id
 * @property Proxy proxy
 * @property Client client
 */
class AccountProxy extends Model
{
	const SCENARIO_ADD = 'add';//

	private $_clientObj;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'client_id' => 'ID клиента',
			'group_id' => 'ID группы',
			'proxy_id' => 'ID прокси',
		);
	}

	public function tableName()
	{
		return '{{account_proxy}}';
	}

	public function rules()
	{
		return array(
			array('client_id,group_id,proxy_id', 'required'),
			array('client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id'),
			array('proxy_id', 'exist', 'className'=>'Proxy', 'attributeName'=>'id'),
			array('group_id', 'in', 'range'=>array_keys(Account::getGroupArr())),
			array('client_id,group_id', 'cloneValidator', 'on'=>self::SCENARIO_ADD),
			array('proxy_id', 'stabilityValidator'),
		);
	}

	public function cloneValidator()
	{
		if(self::model()->find("`client_id`='{$this->client_id}' AND `group_id`='{$this->group_id}'"))
			$this->addError('group_id', "Пара client_id={$this->client_id} group_id={{$this->group_id}} уже существует");
	}

	public function stabilityValidator()
	{
		$proxy = Proxy::model()->findByPk($this->proxy_id);

		if(!$proxy->rating or $proxy->rating < Proxy::RATING_MIN)
			$this->addError('proxy_id', 'Стабильность прокси '.$this->proxy_id.' слишком низкая, минимальная: '.Proxy::RATING_MIN);

	}

	protected function beforeSave()
	{
		return parent::beforeSave();
	}

	/**
	 * @return Proxy
	 */
	public function getProxy()
	{
		return Proxy::model()->findByPk($this->proxy_id);
	}

	/*
	 * выдача прокси либо по клиенту-группе, либо персональный пркоси либо по категории(при замене)
	 * выдает прокси учитывая
	 */
	public static function getGoodProxy($clientId = false, $groupId = false)
	{
		if(!$client = Client::modelByPk($clientId))
		{
			self::$lastError = 'клиент кошелька не найден';
			return false;
		}

		//если у клиента флаг персональные то выдаем
		if($client->personal_proxy)
		{
			$proxyModels = Proxy::model()->findAll([
				'condition'=>"`is_personal`=1 AND `account_id`=0 AND `enabled`=1",
			]);

			/**
			 * @var Proxy[] $proxyModels
			 */

			shuffle($proxyModels);

			foreach($proxyModels as $proxy)
			{
				if($proxy->isGoodRating)
					return $proxy;
			}

			self::$lastError = 'все персональные прокси уже заняты';
			return false;

		}
		else
		{
			$condition = "`client_id`=$clientId and `group_id`=$groupId";

			/**
			 * @var self[] $accountProxyModels
			 */
			$accountProxyModels = self::model()->findAll(array(
				'condition'=>$condition,
			));

			shuffle($accountProxyModels);


			foreach($accountProxyModels as $accountProxyModel)
			{
				if($accountProxyModel->proxy->rating >= Proxy::RATING_MIN)
					return $accountProxyModel->proxy;
			}

			self::$lastError = 'низкий рейтинг или нет подходящих прокси';
			return false;
		}


	}


	public static function getModelByPk($id)
	{
		return self::model()->findByPk($id);
	}

	/*
	 * изменяет proxy_id в переданных моделях
	 *
	 * $array - array(
	 * 	'modelId'=>$proxyId,
	 * 	...
	 * )
	 */
	public static function editProxyId(array $array)
	{
		$doneCount = 0;

		foreach($array as $modelId=>$proxyId)
		{
			$model = self::getModelByPk($modelId);

			if(!$model)
			{
				self::$lastError = 'запись '.$modelId.' не найдена';
				break;
			}

			if($model->proxy_id == $proxyId)
				continue;

			$model->proxy_id = $proxyId;

			if($model->save())
				$doneCount++;
			else
				break;
		}

		return $doneCount;
	}

	/**
	 * @return Client
	 */
	public function getClient()
	{
		if(!$this->_clientObj)
			$this->_clientObj = Client::model()->findByPk($this->client_id);

		return $this->_clientObj;
	}

}