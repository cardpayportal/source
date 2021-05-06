<?php

/**
 * Class UserApi
 * @static User $user
 * @static string $errorMsg
 * @static string $errorCode
 */

class UserApi extends Model
{
	const SCENARIO_ADD = 'add';
	const ERROR_TEST_MODE = 'siteMaintenance';
	const ERROR_ACCESS = 'accessDenied';
	const ERROR_WRONG_WALLET = 'wrongWallet';
	const ERROR_NOT_ENOUGH_WALLETS = 'notEnoughWallets';
	const ERROR_WRONG_QUERY_PARAMETERS = 'wrongQueryParameters';	//ошибка в запросе
	const ERROR_WRONG_STATUS = 'wrongStatus';	//неверно указан статус при запросе

	public static $errorMsg = '';
	public static $errorCode = '';
	public static $user;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'user_id' => 'Пользователь',
			'key' => 'Ключ',
		);
	}

	public function tableName()
	{
		return '{{user_api}}';
	}

	public function beforeValidate()
	{
		if($this->scenario == self::SCENARIO_ADD)
			$this->key = self::generateKey();

		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('user_id, key', 'required'),
			array('user_id', 'exist', 'className' => 'User', 'attributeName' => 'id'),
			array('user_id', 'unique', 'className' => __CLASS__, 'attributeName' => 'user_id', 'on' => self::SCENARIO_ADD),
			array('key', 'length', 'min'=>20, 'max'=>256),
			array('key', 'unique', 'className' => __CLASS__, 'attributeName' => 'key', 'on' => self::SCENARIO_ADD),
		);
	}

	public static function generateKey()
	{
		return Tools::generateCode('ABCDEFGHIJKLMNOPQRSTUVabcdefghijklmnopqrst0123456789', rand(20,30));
	}

	/**
	 * проверяет запрос на правильность
	 * проверяет есть ли ключ в таблице, есть ли юзер и активен ли юзер
	 * устанавливает self::$user
	 * @param array $params
	 * @return bool
	 */
	public static function checkAccess($params)
	{
		if(YII_DEBUG and !Tools::isAdminIp())
		{
			self::$errorCode = self::ERROR_TEST_MODE;
			return false;
		}

		if(!$params['key'])
		{
			self::$errorCode = self::ERROR_WRONG_QUERY_PARAMETERS;
			self::$errorMsg = 'не указан key';
			return false;
		}

		if(!$params['method'])
		{
			self::$errorCode = self::ERROR_WRONG_QUERY_PARAMETERS;
			self::$errorMsg = 'не указан method';
			return false;
		}

		if(in_array($params['method'], self::getAllowedMethods()) === false)
		{
			self::$errorCode = self::ERROR_WRONG_QUERY_PARAMETERS;
			self::$errorMsg = 'неизвестный метод';
			return false;
		}

		if(!method_exists(__CLASS__, $params['method']))
		{
			self::$errorCode = self::ERROR_WRONG_QUERY_PARAMETERS;
			self::$errorMsg = 'ошибка в вызове метода';
			return false;
		}

		$key = $params['key'];

		if($model = self::model()->findByAttributes(['key'=>$key]))
		{
			if($user = User::getUser($model->user_id) and $user->active)
			{
				self::$user = $user;
				return true;
			}
			else
			{
				self::$errorCode = self::ERROR_ACCESS;
				return false;
			}
		}
		else
		{
			self::$errorCode = self::ERROR_ACCESS;
			return false;
		}
	}

	public static function getErrorMsg()
	{
		$arr = array(
			self::ERROR_TEST_MODE => 'тех работы',
			self::ERROR_ACCESS => 'доступ запрещен',
			self::ERROR_WRONG_QUERY_PARAMETERS => 'ошибка в запросе',
			self::ERROR_WRONG_WALLET => 'кошелек не найден',
			self::ERROR_NOT_ENOUGH_WALLETS => 'недостаточно кошельков',
		);

		if(self::$errorMsg)
			return self::$errorMsg;
		elseif(isset($arr[self::$errorCode]))
			return $arr[self::$errorCode];
		else
			return '';
	}

	public static function getAllowedMethods()
	{
		return array(
			'getWallets',
			'checkWallets',
		);
	}

	/**
	 * @param array $params
	 * @return array
	 */
	public static function getWallets($params)
	{
		$amountMin = 1000;
		$amountMax = 100000;

		$amount = $params['amount'];

		$result = [];

		if($amount < $amountMin or $amount > $amountMax)
		{
			self::$errorCode = self::ERROR_WRONG_QUERY_PARAMETERS;
			return $result;
		}

		if($accounts = self::$user->pickAccountsByAmount($amount))
		{
			foreach($accounts as $arr)
				$result[$arr['account']->login] = $arr['amount'];
		}
		else
			self::$errorCode = self::ERROR_NOT_ENOUGH_WALLETS;

		return $result;
	}

	/**
	 * @param array $params ['wallets'=>['78975355454', '79786354545']]
	 * @return int кол-во установленных на проверку кошельков
	 */
	public static function checkWallets($params)
	{
		$wallets = $params['wallets'];

		$setPriorityCount = 0;

		$result = [];

		foreach($wallets as $loginStr)
		{
			if(preg_match('!(\d+)!', $loginStr, $res))
			{
				
				$login = '+'.$res[1];

				if($account = Account::model()->findByAttributes(['login'=>$login, 'user_id'=>self::$user->id]))
				{
					if(Account::setPriorityNow($account->id))
						$setPriorityCount++;
				}
				else
				{
					self::$errorMsg = 'wallet '.$loginStr.' not found';
					self::$errorCode = self::ERROR_WRONG_WALLET;
				}
			}
		}

		return $setPriorityCount;
	}
}