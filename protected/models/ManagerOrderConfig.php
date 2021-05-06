<?php

/**
 * Class ManagerOrderConfig
 * @property int id
 * @property int client_id
 * @property int client_order_count_max		кол-во ордеров на кл todo: при нуле не ограничено
 * @property int manager_order_count_max	кол-во ордеров на мана
 * @property int order_amount_max 			максимальная сумма ордера
 * @property int wallet_amount_min 			минимум на каждый кошель заявки
 */

class ManagerOrderConfig extends Model
{
	const TIMEOUT_MIN = 3600;
	const TIMEOUT_MAX = 604800;	//неделя

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{manager_order_config}}';
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'client_id' => 'ID клиента',
			'client_order_count_max' => 'Кол-во заявок на клиента',
			'manager_order_count_max' => 'Кол-во заявок на мана',
			'order_amount_max' => 'Сумма на заявку',
			'wallet_amount_min' => 'Сумма на кошелек',
		);
	}

	public function rules()
	{
		$cfg = cfg('managerOrder');

		return [
			['client_id', 'exist', 'className'=>'Client', 'attributeName'=>'id', 'allowEmpty'=>false],
			['client_id', 'unique',  'attributeName'=>'client_id', 'allowEmpty'=>false],
			['client_order_count_max', 'numerical', 'min'=>0, 'max'=>$cfg['clientOrderCountMax'], 'allowEmpty'=>false],
			['manager_order_count_max', 'numerical', 'min'=>0, 'max'=>$cfg['managerOrderCountMax'], 'allowEmpty'=>false,],
			['order_amount_max', 'numerical', 'min'=>$cfg['walletAmountMin'], 'max'=>$cfg['orderAmountMax'], 'allowEmpty'=>false],
			['wallet_amount_min', 'numerical', 'min'=>$cfg['walletAmountMin'], 'max'=>$cfg['walletAmountMax'], 'allowEmpty'=>false],
			['order_amount_max', 'compare', 'compareAttribute'=>'wallet_amount_min', 'operator'=>'>='],
			['manager_order_count_max', 'compare', 'compareAttribute'=>'client_order_count_max', 'operator'=>'<='],
		];
	}

	protected function beforeSave()
	{
		return parent::beforeSave();
	}

	/**
	 * @param int $clientId
	 * @return self
	 */
	public static function getModelByClientId($clientId)
	{
		return self::model()->findByAttributes(['client_id'=>$clientId]);
	}

	/**
	 * @param array $params [ clientId=>['client_order_count_max'=>10, ...], ... ]
	 * @return int done count
	 */
	public static function saveConfig($params)
	{
		$doneCount = 0;

		foreach($params as $clientId=>$config)
		{
			if(!$model = self::getModelByClientId($clientId))
			{
				$model = new self;
				$model->scenario = self::SCENARIO_ADD;
				$model->client_id = $clientId;
			}

			$incomeMode = $config['income_mode'];
			unset($config['income_mode']);

			$calcMode = $config['calc_mode'];
			unset($config['calc_mode']);

			$model->attributes = $config;

			if($model->save())
			{
				$client = Client::getModel($model->client_id);
				$client->income_mode = $incomeMode;
				$client->calc_mode = $calcMode;

				if($client->save())
					$doneCount++;
				else
				{
					self::$lastError = $client::$lastError;
					break;
				}
			}
			else
			{
				self::$lastError = 'ошибка у клиента'.$clientId.': '.self::$lastError;
				break;
			}
		}

		return $doneCount;
	}

	/**
	 * @param float $hours
	 * @return bool
	 */
	public static function setTimeout($hours)
	{
		$seconds = floor($hours * 3600);

		if($seconds >= self::TIMEOUT_MIN and $seconds <= self::TIMEOUT_MAX)
		{
			config('managerOrderTimeout', $seconds);
			return true;
		}
		else
		{
			self::$lastError = 'неверное значение, мин: '.floor(self::TIMEOUT_MIN/3600).', макс: '.floor(self::TIMEOUT_MAX/3600);
			return false;
		}
	}

}