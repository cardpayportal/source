<?php

/**
 * Class SmsActivate
 * @property int id
 * @property string status
 * @property int date_add
 * @property int activation_id
 * @property string error
 * @property string phone
 * @property PayeerAccount payeerAccount
 * @property int payeer_account_id
 */

class SmsActivate extends Model
{
	const SCENARIO_ADD = 'add';

	const STATUS_WAIT = 'wait';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';

	private $_smsActivateApi;

	private $_payeerAccountCache = null;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{sms_activate}}';
	}

	public function rules()
	{
		return [
			['payeer_account_id', 'exist', 'className'=>'PayeerAccount', 'attributeName'=>'id', 'allowEmpty'=>false],
			['status', 'in', 'range' => array_keys(self::statusArr()), 'allowEmpty'=>false],
			['date_add, phone', 'safe'],
			['activation_id', 'default', 'value'=> ''],
			['error', 'length', 'min'=>0, 'max'=>200],

		];
	}

	protected function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->date_add = time();
			$this->payeer_account_id = $this->getPayeerAccount()->id;
		}

		return parent::beforeSave();
	}

	protected function afterSave()
	{
		parent::afterSave();
	}

	private function getSmsBot($proxy)
	{
		if(!$this->_smsActivateApi)
		{
			$this->_smsActivateApi =  new SmsActivateApi($proxy);
		}

		return $this->_smsActivateApi;
	}


	/**
	 * @return PayeerAccount|null
	 */
	public function getPayeerAccount()
	{
		if(!$this->_payeerAccountCache)
			$this->_payeerAccountCache = PayeerAccount::getModelById($this->payeer_account_id);

		return $this->_payeerAccountCache;
	}

	/**
	 * @return string
	 */
	public function getDateAddStr()
	{
		return date('d.m.Y H:i', $this->date_add);
	}

	/**
	 * @return string
	 */
	public function getStatusStr()
	{
		return self::statusArr()[$this->status];
	}

	/**
	 * @return self[]
	 */
	public static function getModels($payeerAccountId = 0)
	{
		$intervalMax = 2400;
		$payeerAccountId *=1;

		$models = self::model()->findAll([
			'condition'=>"
				`date_add`< ".time() - $intervalMax.
				" AND `payeer_account_id`='$payeerAccountId'",
			'order'=>"`date_add` DESC",
		]);

		return $models;
	}

	public static function statusArr()
	{
		return [
			self::STATUS_WAIT => 'в ожидании',
			self::STATUS_ERROR => 'ошибка',
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
	 * @return self
	 */
	public static function getModelById($id)
	{
		return self::model()->findByPk($id);
	}


	/**
	 * заказываем новый номер
	 * @return array|bool
	 */
	public function getNewNumber()
	{
		$url = 'http://sms-activate.ru/stubs/handler_api.php?api_key='.$this->apiKey.
			'&action=getNumber&service=ot&forward=0&operator=any&country=0';
		$content = $this->request($url);

		if(preg_match('!ACCESS_NUMBER:(\d+):(\d+)!iu', $content, $matches))
		{
			return [
				'id' => $matches[1]*1,
				'phone' => $matches[2]*1,
			];
		}
		else
		{
			toLogError($content.' smsApiKey = '.$this->apiKey);
			return false;
		}
	}

	/**
	 * @return bool
	 * остаток баланса на счету
	 */
	public function getBalance()
	{
		$url = 'http://sms-activate.ru/stubs/handler_api.php?api_key='.$this->apiKey.'&action=getBalance';
		$content = $this->request($url);
		if(preg_match('!ACCESS_BALANCE:(\d+)!iu', $content, $matches))
		{
			return $matches[1]*1;
		}
		else
		{
			toLogError($content.' smsApiKey = '.$this->apiKey);
			return false;
		}
	}

	/**
	 * @param $status  статус активации:
	-1 - отменить активацию
	1 - сообщить о готовности номера (смс на номер отправлено)
	3 - запросить еще один код (бесплатно)
	6 - завершить активацию(если был статус "код получен" - помечает успешно и завершает,
	если был "подготовка" - удаляет и помечает ошибка, если был статус "ожидает повтора"
	- переводит активацию в ожидание смс)
	8 - сообщить о том, что номер использован и отменить активацию
	 * @param $id - id активации
	 */
	public function changeActivationStatus($status, $id)
	{
		$url = 'http://sms-activate.ru/stubs/handler_api.php?api_key='.$this->apiKey.
			'&action=setStatus&status='.$status.'&id='.$id.'&forward=$forward';
		$content = $this->request($url);

		if($content == self::ACCESS_READY)
		{
			return true;
		}
		prrd($content);
	}

	/**
	 * @param $id - id активации
	 *
	 * @return bool
	 */
	public function getQiwiActivationStatus($id)
	{
		//STATUS_CANCEL
		$url = 'http://sms-activate.ru/stubs/handler_api.php?api_key='.$this->apiKey.'&action=getStatus&id='.$id;
		$content = $this->request($url);
		prrd($content);
		$result = [
			'code' => '',
			'msg' => '',
		];

		if(preg_match('!vash sms-kod: (\d+)!iu', $content, $matches))
		{
			//получен код
			return $result['code'] = $matches[1];
		}
		elseif($content == self::STATUS_WAIT_CODE || $content == self::STATUS_WAIT_RESEND)
		{
			return $result['msg'] = $matches[1];
		}
		else
		{
			toLogError($content.' smsApiKey = '.$this->apiKey.' id = '.$id);
			return false;
		}

	}

}