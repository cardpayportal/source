<?php

/**
 * @property string role
 * @property string name
 * @property int id
 * @property Client client
 * @property Account[] accounts	список кошельков менеджера(или для админа список кошельков всех менеджеров)
 * @property int last_seen
 * @property string lastSeenStr
 * @property array myManagerAccounts
 * @property int client_id
 * @property bool active
 * @property int is_wheel
 * @property string jabber
 * @property string login
 * @property string roleStr
 * @property int group_id	только для менеджеров и финов(тоже могут принимать средства). если не указано то может брать кошельки любой цепочки
 * @property int groupRepeatCount кол-во юзеров с той же группой и тем же клиентом
 * @property Account[] currentAccounts
 * @property string theme тема сайта
 * @property bool send_notifications вкл-выкл уведомлений в жабу
 * @property WexAccount wexAccount
 * @property MerchantUser merchantUser
 * @property MerchantWallet[] merchantWallets
 * @property MerchantWallet[] qiwiMerchantWallets
 * @property MerchantWallet[] yandexMerchantWallets
 * @property YandexAccount[] yandexAccounts
 * @property string api_key
 * @property string api_secret
 * @property PayeerAccount payeerAccount
 * @property StoreApi store
 * @property string success_url
 * @property string fail_url
 * @property string url_return
 * @property string url_result
 * @property string telegram
 *
 */
class User extends Model
{
	//todo: заменить ROLE_USER на ROLE_MANAGER и ROLE_MODER на ROLE_FINANSIST
	const ROLE_USER = 'user';	//менеджер
	const ROLE_MANAGER = 'user';	//менеджер
	const ROLE_MODER = 'moderator';	//финансист
	const ROLE_FINANSIST = 'moderator';	//финансист
	const ROLE_SIM = 'sim';	//симочник
	const ROLE_ADMIN = 'admin';	//видит все
	const ROLE_CONTROL = 'control';	//видит стату менеджеров и стату оплат(киви и биткоин) у своего дерева
	const ROLE_GLOBAL_FIN = 'global_fin';	//видит стату менеджеров и стату оплат(киви и биткоин) у своего дерева

	const SCENARIO_ADD = 'add';
	const SCENARIO_UPDATE = 'update';

	const CURRENT_WALLET_LIMIT = 150;

	const ERROR_NO_WALLETS = 'no wallets';
	const ERROR_WALLET_LIMIT = 'wallets limit';

	public static $passGenerated = false;

	public $clientObj = null;
	
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
	
	public function attributeLabels()
	{
		return array(
			'login'=>'Логин',
			'pass'=>'Пароль',
			'name'=>'Имя',
			'category'=>'Категория',//чтобы менеджер мог заходить по несколькими юзерами сразу
            'parent_id'=>'Parent ID',
            'is_wheel'=>'За штурвалом',
            'jabber'=>'Jabber для уведомлений',
            'group_id'=>'Номер цепочки',
            'send_notifications'=>'Уведомления',
		);
	}

	public function tableName()
	{
		return '{{user}}';
	}
	
	public function beforeValidate()
	{
		return parent::beforeValidate();
		
	}

	public function beforeSave()
	{
		if($this->scenario == self::SCENARIO_ADD)
		{
			$this->pass = self::hash($this->pass);
			$this->active = cfg('default_user_active');

			//контроль количества финов(если не нужно много финов)
			if($this->role == self::ROLE_FINANSIST and self::model()->find("`role`='".self::ROLE_FINANSIST."' and `client_id`='{$this->client_id}'"))
			{
				//self::$lastError = 'финансист уже зарегистрирован';
				//return false;
			}

			$this->login = strip_tags($this->login);
			$this->name = strip_tags($this->name);
			$this->jabber = strip_tags($this->jabber);

			if(in_array($this->role, [self::ROLE_MANAGER, self::ROLE_FINANSIST]))
				$this->group_id = $this->setGroupId();
		}

		return parent::beforeSave();
	}
		
	public function rules()
	{
		return array(
			array('login', 'match', 'pattern'=>cfg('loginRegExp'), 'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD),
			array('login', 'unique', 'className'=>__CLASS__, 'attributeName'=>'login', 'on'=>self::SCENARIO_ADD, 'message'=>'Логин уже занят другим пользователем'),
			array('pass', 'length', 'min'=>3, 'max'=>100, 'allowEmpty'=>true, 'message'=>'Неверная длина пароля', 'on'=>'add'),
			array('name', 'match', 'pattern'=>'!^[a-zA-Z_0-9а-яА-Я ]{3,15}$!u', 'allowEmpty'=>false, 'on'=>self::SCENARIO_ADD),
			array('name', 'unique', 'className'=>__CLASS__, 'attributeName'=>'name', 'on'=>self::SCENARIO_ADD, 'message'=>'Имя уже занято другим пользователем'),
			array('active', 'in', 'range'=>array(0, 1)),
			array('role', 'in', 'range'=>array_keys(self::roleArr()), 'allowEmpty'=>false),
            array('parent_id', 'exist', 'className'=>'User', 'attributeName'=>'id', 'allowEmpty'=>true, 'on'=>self::SCENARIO_ADD),
			array('client_id', 'clientIdValidator', 'on'=>self::SCENARIO_ADD),
			array('is_wheel', 'in', 'range'=>array(0, 1)),
			array('is_wheel', 'isWheelValidator', 'allowEmpty'=>true),
			array('jabber', 'length', 'min'=>5, 'max'=>30, 'allowEmpty'=>true),
			array('jabber', 'match', 'pattern'=>cfg('emailRegExp'), 'allowEmpty'=>true),
			array('jabber', 'unique', 'className'=>__CLASS__, 'attributeName'=>'jabber', 'allowEmpty'=>true),
			array('theme', 'in', 'range'=>array_keys(cfg('themeArr')), 'allowEmpty'=>true),
			array('send_notifications', 'numerical', 'min'=>0, 'max'=>1, 'allowEmpty'=>true),
			array('send_notifications', 'sendNotificationsValidator'),
			['api_key', 'unique', 'className'=>__CLASS__, 'attributeName'=>'api_key', 'on'=>self::SCENARIO_ADD, 'allowEmpty'=>true],
			['api_secret', 'unique', 'className'=>__CLASS__, 'attributeName'=>'api_secret', 'on'=>self::SCENARIO_ADD, 'allowEmpty'=>true],
			['success_url, fail_url, url_return, url_result', 'safe'],
			['telegram', 'safe'],
		);
	}

	public function clientIdValidator($attribute=false, $params=false)
	{
		//если глобал фин то у него не должно быть parent_id и client_id
		if($this->role == self::ROLE_GLOBAL_FIN and ($this->client_id or $this->parent_id))
			$this->addError('login', 'У globalFin не должно быть client_id и parent_id');
		//если простой фин или контроль то не дожно быть parent_id но должен быть client_id
		elseif(($this->role == self::ROLE_FINANSIST or $this->role == self::ROLE_CONTROL) and (!$this->client_id or $this->parent_id))
			$this->addError('login', 'У финансиста или мастера не должно быть parent_id и должен быть client_id');
		elseif($this->role == self::ROLE_MANAGER and (!$this->client_id or !$this->parent_id))
			$this->addError('login', 'У менеджера должно быть parent_id и должен быть client_id');
	}

	public function isWheelValidator($attribute=false, $params=false)
	{
		//если глобал фин то у него не должно быть parent_id и client_id
		if($this->is_wheel and $this->role == self::ROLE_GLOBAL_FIN)
		{
			if($user = self::getWheelUser() and $user->id != $this->id)
				$this->addError('is_wheel', 'Двоем за штурвал нельзя, '.$user->name.' должен нажать на Отпустить штурвал');
		}
	}

	public function sendNotificationsValidator()
	{
		if($this->send_notifications and !$this->jabber)
			$this->addError('send_notifications', 'при включении уведомлений нужно указать Jabber');
	}
	
	public static function roleArr($key=false)
	{
		$result = array(
			self::ROLE_USER => 'Менеджер',
			self::ROLE_MODER => 'Финансист',
			self::ROLE_SIM => 'Симочник',
			self::ROLE_CONTROL => 'Контроль',
			self::ROLE_GLOBAL_FIN => 'GlobalFin',
			self::ROLE_ADMIN => 'Admin',
		);
		
		if($key)
			return $result[$key];
		else
			return $result;
	}
	
	public static function auth($login, $pass)
	{
		$login = trim($login);

		if(!preg_match(cfg('loginRegExp'), $login))
		{
			sleep(cfg('auth_pause'));
			toLogSecurity('ошибка авторизации : login='.$login.', pass='.$pass.', ip='
				.Tools::getClientIp().' ('.Tools::arr2Str($_SERVER).')');

			self::$lastError = 'Неверный логин или пароль';
			return false;
		}

		$identity = new UserIdentity($login, $pass);

		if(!Tools::isAdminIp())
			sleep(cfg('auth_pause'));
		
		if($identity->authenticate())
		{
			Yii::app()->user->login($identity);
			$ip = Tools::getClientIp();
			toLogSecurity("успешная авторизация: login=$login, ip=$ip");
			return true;
		}
		else
		{
			toLogSecurity('ошибка авторизации: login='.strip_tags($login).', pass='.strip_tags($pass).' ('.Tools::arr2Str($_SERVER).')');
			self::$lastError = $identity->errorMessage;
			return false;
		}

	}

	/*
	 * регистрация нового пользователя
	 * если регистрирует админ то может выбрать тип пользователя и клиента
	 * Control может зарегать только манагера
	 *
	 * Если регистрируется фин, то для него создается доп запись в pay_pass
	 *
	 * $params = array('client_id'=>1, 'login'=>'man1', 'role'=>'manager', 'pass'=>'необязательно', 'name'=>'необязательно')
	 *
	 * $registerUser - к какому юзеру прикрепляется
	 *
	 */
	public static function register($params, $registerUser = false)
	{
		//если регает не админ то только манагеров

		self::$passGenerated = false;
		
		if(!$params['pass'])
		{
			$params['pass'] = self::passGenerator();
			self::$passGenerated = $params['pass'];
		}
		
		if(!$params['name'])
			$params['name'] = $params['login'];

		$attributes = $params;


		if(!$registerUser)
		{
			//если админ регает то сам присваивает клиента
			$attributes['client_id'] = $params['client_id'];
			$attributes['role'] = $params['role'];
		}
		else
		{
			$attributes['client_id'] = $registerUser->client_id;
			$attributes['role'] = self::ROLE_MANAGER;
		}

		//parent_id берется от первого control-юзера
		if($attributes['role'] === self::ROLE_MANAGER)
		{
			$parent = User::model()->find("`role`='".self::ROLE_CONTROL."' and `client_id`='".$attributes['client_id']."'");

			if(!$parent)
			{
				self::$lastError = 'не найден control-юзер';
				return false;
			}

			$attributes['parent_id'] = $parent->id;
		}
		
		$model = new self;
		$model->scenario = self::SCENARIO_ADD;
		$model->attributes =$attributes;

		if($model->save())
		{
			if($attributes['role'] == self::ROLE_FINANSIST or $attributes['role'] == self::ROLE_GLOBAL_FIN)
			{
				$payPassModel = new PayPass;
				$payPassModel->scenario = PayPass::SCENARIO_ADD;
				$payPassModel->user_id = $model->id;

				if($payPassModel->save())
				{
					self::$passGenerated .= ' платежный пароль: '.$payPassModel->generatedPass;
				}
				else
				{
					$model->delete();
					toLogError('ошибка создания платежного пароля');
					return false;
				}
			}

			toLogRuntime('зарегистрирован новый '.self::roleArr($model->role).' '.$model->login);
		}
		else
			return false;

		return $model;
	}
	
	public static function hash($value)
    {
    	return md5($value);
    }
    
    public static function activeUsers()
    {
    	return self::model()->findAll("`active`=1 and `role`!='".self::ROLE_ADMIN."'");
    }

    /**
     * возварщает объект текущего пользователя системы
	 * @return User
     */
    public static function getUser($userId=null)
    {
    	if($userId!==null)
    		return self::model()->findByPk($userId);
    	elseif(Yii::app()->user)
    		return self::model()->findByPk(Yii::app()->user->id);
    }
    
    public static function loginAs($id)
    {
    	if($model = self::model()->findByPk($id) and $model->active)
		{
			if($model->role != $model::ROLE_ADMIN)
			{
				$identity = new AdminAuth($model->login, 'someValue');

				if($identity->authenticate())
				{
					Yii::app()->user->login($identity);
					return true;
				}
			}
		}
		else
			self::$lastError = 'пользователь не найден или не активирован';
    }
    
    public static function enable($id)
    {
    	if($model = self::model()->findByPk($id))
    	{
			if($model->role != User::ROLE_ADMIN)
			{
				$model->active = 1;

				return $model->save();
			}
			else
				return false;
    	}
    }
    
    public static function disable($id)
    {
    	if($model = self::model()->findByPk($id))
    	{
			if($model->role != User::ROLE_ADMIN)
			{
				$model->active = 0;

				return $model->save();
			}
			else
				return false;
    	}
    }

	public static function changePass($id)
	{
		if($model = self::model()->findByPk($id))
		{
			/**
			 * @var self $model
			 */

			if($model->role != User::ROLE_ADMIN)
			{
				$newPass = self::passGenerator();

				$model->pass = self::hash($newPass);

				self::$msg = $model->login.' '.$newPass;

				$model->save();

				if(in_array($model->role, [self::ROLE_FINANSIST, self::ROLE_GLOBAL_FIN]))
				{
					if($newPayPass = $model->changePayPass())
						self::$msg .= ' платежный пароль: '.$newPayPass;
					else
						return false;
				}

				toLogSecurity('смена пароля: '.self::$msg);

				return true;
			}
			else
				return false;
		}
	}

	/**
	 * смена платежного пароля
	 * @return string|false
	 */
	public function changePayPass()
	{
		if(!in_array($this->role, [self::ROLE_FINANSIST, self::ROLE_GLOBAL_FIN]))
		{
			self::$lastError = 'у юзера нет платежного пароля';
			return false;
		}

		$payPassModel = PayPass::model()->find("`user_id`='{$this->id}'");

		if(!$payPassModel)
		{
			self::$lastError = 'не найдено платежного пароля для пользователя';
			return false;
		}

		/**
		 * @var PayPass $payPassModel
		 */

		$newPayPass = $payPassModel->changePass();

		if(!$newPayPass)
		{
			self::$lastError = 'ошибка смены платежного пароля';
			return false;
		}

		return $newPayPass;
	}
    
    public function getMaxOrdersCount()
    {
    	return config('user_max_orders');
    }
    
    /**
     * выдает менеджеру список его кошельков, админу - список кошельков юзеров
     * группирует кошельки по Имени юзера (у админа и фина)
     * $used - если true, то возварщает только использованные кошельки, иначе - все остальные
	 * todo: убрать вывод кошельков админу в отдельную функцию
	 * @var bool $groupByUser
	 * @var bool $used
	 * @var int $limit
	 * @return Account[]
     */
    public function getAccounts($groupByUser=true, $used=false, $limit = 0)
    {
    	$result = array();

		$limit *= 1;
    	
    	if($used)
    		$usedCond = "AND `date_used`>0";
   		else
   			$usedCond = "AND `date_used`=0";

		//$orderStr = "`status` DESC, `date_pick` DESC";

		$orderStr = "`status` DESC, `limit_in` DESC";

		if($used)
			$orderStr = "`date_used` DESC";

		$findParams = [];

    	if($this->role == self::ROLE_MANAGER or $this->role == self::ROLE_FINANSIST)
    	{
			if($this->role == self::ROLE_MANAGER)
				$orderStr = "`hidden` ASC, `status` DESC, `limit_in` DESC";

			$findParams = [
    			'condition'=>"`type`='".Account::TYPE_IN."' AND `user_id`={$this->id} ".$usedCond,
    			'order'=>$orderStr,
			];

			if($limit > 0)
				$findParams['limit'] = $limit;
		}
		elseif($this->role==self::ROLE_ADMIN)
		{
			//админ видит кошельки, с которыми работают менеджеры
			$findParams = [
				'condition'=>"
					`type`='".Account::TYPE_IN."'
					AND `enabled`=1
					AND `user_id`!=0 $usedCond
				",
				//'limit'=>100,
				'order'=>"`status` DESC, `date_pick` DESC",
			];

			if($limit > 0)
				$findParams['limit'] = $limit;
		}
		else
			$models = array();

		if(!isset($models))
			$models = $models = Account::model()->findAll($findParams);

		
		if($models)
		{
			if($groupByUser)
			{
				//сначала кошельки текущего юзера, потом остальных
				foreach($models as $model)
				{
					if($model->user->id==$this->id)
						$result[$model->user->name][] = $model;
				}
				
				foreach($models as $model)
				{
					if($model->user->id!=$this->id)
						$result[$model->user->name][] = $model;
				}
			}
			else
				$result = $models;
		}
		
		return $result;
    }

	/*
	 * работает только на фине
	 * выводит все текущие аккаунты манагеров
	 */
	public function getMyManagerAccounts()
	{
		$result = array();

		if($this->role == self::ROLE_FINANSIST)
		{
			$orderStr = "`status` DESC, `date_pick` DESC";

			$models = Account::model()->findAll(array(
				'condition'=>"
					`client_id`='{$this->client_id}'
					AND `type`='".Account::TYPE_IN."'
					AND `user_id`!=0
					AND `date_used`=0
					AND `enabled`=1
				",
				'order'=>$orderStr,
			));

			self::$someData['count'] = count($models);

			//сначала кошельки текущего юзера, потом остальных
			foreach($models as $model)
			{
				if($model->user->id == $this->id)
					$result[$model->user->name][] = $model;
			}

			foreach($models as $model)
			{
				if($model->user->id != $this->id)
					$result[$model->user->name][] = $model;
			}
		}

		return $result;
	}
    
    /**
     * выдает менеджеру или фину список его кошельков
	 * если критический мод то выдает только критические кошельки
	 * @param string $order сортировка
	 * @return Account[]
     */
    public function getCurrentAccounts($order = '')
    {
		$result = [];

    	if($this->role == self::ROLE_MANAGER or $this->role == self::ROLE_FINANSIST)
		{
			$accounts = Account::model()->findAll(array(
				'condition'=>"
    				`user_id`='{$this->id}'
    				AND `type`='".Account::TYPE_IN."'
    				AND `date_used`=0
    				AND `enabled`=1
					AND `comment` <> 'stopping'
    			",
				'order'=>($order) ? $order : "`date_pick` DESC",
			));

			/**
			 * @var Account[] $accounts
			 */

			foreach($accounts as $account)
			{
				if(config('criticalMode'))
				{
					if($account->isCritical)
						$result[] = $account;
				}
				else
				{
					if(!$account->isCritical)
						$result[] = $account;
				}
			}
		}

		return $result;
    }
    
    public function getRoleStr()
	{
		return self::roleArr($this->role);
	}
    
    public static function getManagers()
    {
    	return self::model()->findAll(array(
			'condition'=>"`role`='".self::ROLE_USER."'",
			'order'=>"`name`",
		));
    }
    
    /**
     * получить транзакции, после $timestamp для юзера
     */
    public function getNewTransactions($timestamp)
    {
    	$result = array();
    	
    	$accounts = $this->getAccounts(false);
    	
    	foreach($accounts as $account)
    	{
    		foreach($account->transactions as $trans)
    		{
    			if($trans->date_add >= $timestamp)
    				$result[] = $trans;
    		}
    	}
    	
    	return $result;
    }
    
    /**
     * возвращяет всех пользователей той же категории что и юзер
     */
    public function getCategoryUsers()
    {
    	$result = array();
    	
    	if($this->category)
    	{
    		$result[$this->id] = $this;
    		
    		if($models = self::model()->findAll("`category`='{$this->category}' and `id`!='{$this->id}'"))
    		{
    			foreach($models as $model)
    				$result[$model->id] = $model;
    		}
    	}
    	
    	return $result;
    }
    
    public static function passGenerator()
    {
    	return Tools::generateCode('ABCDEFGHIJKLMNOPQRSTUVabcdefghijklmnopqrst0123456789', rand(10,15));
    }

	/*
	 * активные кошельки менеджера в данный период
	 * для control
	 */
	public function getActiveWallets($timestampFrom, $timestampTo)
	{
		return Account::model()->findAll(array(
			'condition'=>"
				`user_id`='{$this->id}'
				AND `date_pick` <= $timestampFrom
				AND (`date_used` >= $timestampTo OR `date_used`=0)
				AND `enabled`=1
			",
			'order'=>"`date_pick` ASC",
		));
	}

    public static function buildManagerTree($userObj, $stats)
    {
        $result = array();

        if($userObj->role == self::ROLE_ADMIN or $userObj->role == self::ROLE_FINANSIST)
		{
			$rootId = User::model()->find("`role`='".self::ROLE_CONTROL."' and `parent_id`=0 and `client_id`='{$userObj->client_id}'")->id;
		}
		else
            $rootId = $userObj->id;

        $user = User::getUser($rootId);

        $result[$rootId] = array(
            'name'=>$userObj->name,
            'children'=>array(),
            'amount'=>0,
        );


        if($children1 = User::model()->findAll("`parent_id`='$rootId'"))
        {

            foreach($children1 as $child1)
            {

                $result[$rootId]['children'][$child1->id] = array(
                    'name'=>$child1->name,
                    'children'=>array(),
                );

                if($child1->role==self::ROLE_MANAGER)
                    $result[$rootId]['children'][$child1->id]['stats'] = $stats[$child1->id];
                else
                    $result[$rootId]['children'][$child1->id]['stats'] = array();

                $result[$rootId]['children'][$child1->id]['amount'] = array_sum($result[$rootId]['children'][$child1->id]['stats']);
                $result[$rootId]['amount'] += $result[$rootId]['children'][$child1->id]['amount'];

                if($children2 = User::model()->findAll("`parent_id`='{$child1->id}'"))
                {

                    foreach($children2 as $child2)
                    {
                        $result[$rootId]['children'][$child1->id]['children'][$child2->id] = array(
                            'name'=>$child2->name,
                        );

                        if($child2->role==self::ROLE_MANAGER)
                            $result[$rootId]['children'][$child1->id]['children'][$child2->id]['stats'] = $stats[$child2->id];
                        else
                            $result[$rootId]['children'][$child1->id]['children'][$child2->id]['stats'] = array();

                        $result[$rootId]['children'][$child1->id]['children'][$child2->id]['amount'] = array_sum($result[$rootId]['children'][$child1->id]['children'][$child2->id]['stats']);

                        $result[$rootId]['children'][$child1->id]['amount'] += array_sum($result[$rootId]['children'][$child1->id]['children'][$child2->id]['stats']);

                        $result[$rootId]['amount'] += array_sum($result[$rootId]['children'][$child1->id]['children'][$child2->id]['stats']);
                    }
                }
            }


        }


		//отдельно вывести стату фина
		if($userObj->role == self::ROLE_ADMIN or $userObj->role == self::ROLE_FINANSIST)
		{
			foreach($stats as $userId=>$stat)
			{
				if($user = User::getUser($userId) and $user->role == User::ROLE_FINANSIST)
				{
					$result[$user->id] = array(
						'name'=>$user->name,
						'children'=>array(),
						'wallets'=>$stat,
						'amount'=>array_sum($stat),
					);

					break;
				}
			}
		}


        return $result;
    }

	/**
	 * @return Client
	 */
	public function getClient()
	{
		if($this->clientObj === null)
			$this->clientObj = Client::model()->findByPk($this->client_id);

		return $this->clientObj;
	}
//
	/**
	 *  возвращает модель globalFin юзера который сейчас у штурвала
	 * @return self
	 */
	public static function getWheelUser()
	{
		return self::model()->find("`role`='".self::ROLE_GLOBAL_FIN."' AND `is_wheel`=1");
	}

	/*
	 * ставит флаг is_wheel если не занят
	 */
	public function takeWheel()
	{
		$this->is_wheel = 1;

		if($this->save())
		{
			GlobalFinLog::add('штурвал взят', $this->id);
			toLogRuntime('штурвал взят пользователем: '.$this->name);
			return true;
		}
		else
			return false;
	}

	/*
	 * удаляет флаг is_wheel
	 */
	public function dropWheel()
	{
		$this->is_wheel = 0;

		if($this->save())
		{
			GlobalFinLog::add('штурвал оставлен', $this->id);
			return true;
		}
		else
			return false;
	}

	//отображение кто на смене для фина и контроля
	public static function getSupportOnlineName()
	{
		if($user = self::getWheelUser())
			return $user->name;
		else
			return false;
	}

	/*
	 * прочитать новость
	 */
	public function readNews($newsId)
	{
		return News::read($this->id, $newsId);
	}

	/**
	 * менеджер или фин запрашивает кошельки
	 * @param array $params = array(
	 * 	'count'=>2,//кол-во
	 * 	'label'=>'test',//метка(не обязательно),
	 * 	'status'=>full|half|api,
	 * )
	 *
	 * todo: лимиты получения кошельков
	 * todo: проверку роли юзера
	 *
	 * @return Account[]|bool
	 */
	public function pickAccounts($params)
	{
		if(
			!config('pickAccountEnabled')
			or !$this->client->pick_accounts
		)
		{
			self::$lastError = 'выдача новых кошельков временно прекращена';
			return false;
		}

		$count = $params['count']*1;

		if(!$count)
		{
			self::$lastError = 'не указано количество кошельков';
			return false;
		}

		$label = strip_tags($params['label']);
		$status = $params['status'];

		$userId = $this->id;

		//ограничение на выдачу
		$currentAccounts = $this->getCurrentAccounts();

		$goodCurrentAccountsCount = 0;

		foreach($currentAccounts as $currentAccount)
		{
			if($currentAccount->error)
				continue;

			if(config('criticalMode') and $currentAccount->limit_in < cfg('criticalAccountMinLimit'))
				continue;

			$goodCurrentAccountsCount++;
		}

		$maxAccountCount = (config('criticalMode')) ? cfg('criticalAccountCountPerUser') : cfg('managerAccountLimit');

		if($goodCurrentAccountsCount + $params['count'] > $maxAccountCount)
		{
			self::$lastError = 'ограничение на выдачу кошельков (максимум '.$maxAccountCount.')';
			toLogError('ограничение на выдачу кошельков '.$this->client->name.': '.$this->name);
			return false;
		}

		$accounts = Account::getFreeInAccounts($this->client_id, $status);

		if(count($accounts)>=$count)
		{
			if(count($accounts) < cfg('in_warn_count'))
				toLogRuntime('осталось '.count($accounts).' свободных аккаунтов у кл'.$this->client_id, false, true);

			$accounts = array_slice($accounts, 0, $count);

			$datePick = time();

			self::$msg = '';

			foreach($accounts as &$account)
			{
				$updateParams = [
					'user_id'=>$userId,
					'check_priority'=>Account::PRIORITY_BIG,	//приоритет выше среднего
					'label'=>$label,
					'date_pick'=>$datePick,
				];

				if($this->group_id and !$account->user_id)
				{
					$updateParams['group_id'] = $this->group_id;
					$account->group_id = $this->group_id;
				}

				Account::model()->updateByPk($account->id, $updateParams);

				//максимальный приоритет при взятии кошелька чтобы сразу проверился
				Account::setPriorityNow($account->id);

				$account->user_id = $userId;
				$account->check_priority = Account::PRIORITY_BIG;
				$account->label = $label;
				$account->date_pick = $datePick;

				self::$msg .= "<br>{$account->login}";

				if($account->api_token and !cfg('tokenAccountsAsSimple'))
					self::$msg .= " {$account->pass}";
			}

			toLogRuntime('взято: '.$count.' кошельков у '.$this->client->name.' ('.$account->login.')');


			return $accounts;
		}
		else
		{
			self::$lastError = 'недостаточно проверенных кошельков , в наличии: '.count($accounts);
			toLogError('недостаточно проверенных кошельков у кл'.$this->client_id.' (user='.$this->name.', type='.$params['status'].'), в наличии: '.count($accounts), false, true);
			return false;
		}
	}

	/**
	 * Возвращает массив кошелек-сумма (используется в StoreApi, EcommApi, ManagerOrder)
	 * Если есть текущие кошельки и на них нет зарезервированных сумм то отдать их, если таких нет то отдать новые,
	 * 	если и их нет то вернуть те на которых еще можно зарезервировать
	 *
	 * кооды ошибок: self::ERROR_NO_WALLETS, ERROR_WALLET_LIMIT
	 *
	 * @param float $amount	заливаемая сумма
	 * @return array|bool array(array('account'=>model, 'amount'=>50000))
	 *
	 * todo: если режим Свои кошельки, то не выдавать наши, и наоборот
	 */
	public function pickAccountsByAmount($amount)
	{
		if(
			!config('pickAccountEnabled')
			or !$this->client->pick_accounts
		)
		{
			self::$lastError = 'выдача новых кошельков временно прекращена';
			self::$lastErrorCode = self::ERROR_NO_WALLETS;
			return false;
		}

		$limitMin = 10000;	//не выдавать кошельки если лимита останется меньше ...
		$minAmount = 2;

		$currentAccounts = $this->getCurrentAccounts("`limit_in` DESC");

		$ostatok = $amount;	//на какую сумму еще осталось подобрать кошельков, если 0 то цикл завершить

		$resultAccounts = array();	//кошелек-сумма

		$pickNewCount = 0;	//сколько новых выдано

		if($currentAccounts)
		{
			foreach($currentAccounts as $account)
			{
				if($account->error)
					continue;

				$reservedAmount = $account->reserveAmount;

				$possibleAmount = $account->limit_in - $reservedAmount - $minAmount;

				$maxBalance = $account->maxBalance;

				if($possibleAmount > $maxBalance - $reservedAmount)
					$possibleAmount = $maxBalance - $reservedAmount;

				if($possibleAmount <= $limitMin)
					continue;

				if($possibleAmount > $ostatok)
					$possibleAmount = $ostatok;

				if($account->dayLimit - $reservedAmount < $ostatok)
					continue;

				if($possibleAmount >= $minAmount)
				{
					$resultAccounts[$account->id] = array('account'=>$account, 'amount'=>$possibleAmount);
					$ostatok = $ostatok - $possibleAmount;
				}

				//если остаток меньше 2 рублей то добавить к последнему кошельку
				if($ostatok < Account::BALANCE_MIN and $resultAccounts[$account->id])
				{
					$resultAccounts[$account->id]['amount'] += $ostatok;
					$ostatok  = 0;
				}

				if($ostatok < 0)
				{
					toLogStoreApi('User::pickAccountsByAmount() исключение001');
					return false;
				}

				if($ostatok == 0)
					break;
			}
		}


		if($ostatok > 0)
		{
			//если текущих кошельков больше чем self::CURRENT_WALLET_LIMIT то не выдавать
			$currentAccounts = $this->getCurrentAccounts();

			$goodCurrentAccountsCount = 0;

			foreach($currentAccounts as $currentAccount)
			{
				if($currentAccount->error)
					continue;

				if(config('criticalMode') and $currentAccount->limit_in < cfg('criticalAccountMinLimit'))
					continue;

				$goodCurrentAccountsCount++;
			}

			$maxAccountCount = (config('criticalMode')) ? cfg('criticalAccountCountPerUser') : cfg('managerAccountLimit');

			if($goodCurrentAccountsCount >= $maxAccountCount)
			{
				self::$lastErrorCode = self::ERROR_WALLET_LIMIT;
				self::$lastError = 'ограничение на выдачу кошельков';
				toLogError('ограничение на выдачу кошельков '.$this->client->name.': '.$this->name);
				return false;
			}

			$freeInAccounts = Account::getFreeInAccounts($this->client_id);

			if($freeInAccounts)
			{
				foreach($freeInAccounts as $account)
				{
					//опять то же самое повторяем для новых кошельков

					$possibleAmount = $account->limit_in;

					$maxBalance = $account->maxBalance;

					if($possibleAmount > $maxBalance)
						$possibleAmount = $maxBalance;

					if($possibleAmount > $ostatok)
						$possibleAmount = $ostatok;

					if($account->limit_in - $possibleAmount < $limitMin)
						$possibleAmount = $account->limit_in - $limitMin;

					if($possibleAmount > $minAmount)
					{
						$resultAccounts[$account->id] = array('account'=>$account, 'amount'=>$possibleAmount);
						$ostatok -= $possibleAmount;

						$pickNewCount++;
					}

					//если остаток меньше 2 рублей то добавить к последнему кошельку
					if($ostatok < Account::BALANCE_MIN and $resultAccounts[$account->id])
					{
						$resultAccounts[$account->id]['amount'] += $ostatok;
						$ostatok  = 0;
					}

					if($ostatok < 0)
					{
						toLog('User::pickAccountsByAmount() исключение001');
						return false;
					}

					if($ostatok == 0)
						break;
				}
			}
		}

		if($ostatok > 0)
		{
			//не хватило кошельков
			self::$lastError = 'недостаточно кошельков';
			self::$lastErrorCode = self::ERROR_NO_WALLETS;
			toLogError('недостаточно проверенных кошельков у кл'
				.$this->client_id.' , для суммы : ' .formatAmount($amount).', '
				. $this->name, false, true);
			return false;
		}

		$date = time();

		foreach($resultAccounts as $arr)
		{
			/**
			 * @var $model Account
			 */
			$model = $arr['account'];

			if(!$model->user_id)
			{
				$params = array(
					'check_priority'=>Account::PRIORITY_NOW,	//приоритет выше среднего
				);

				if(!$model->user_id)
				{
					$params['user_id'] = $this->id;
					$model->user_id = $this->id;

					// с 16 не продумывал, лучше пока не менять ничего, может на работе проксей сказаться
					if($this->group_id and $this->client_id != 16)
					{
						$params['group_id'] = $this->group_id;
						$model->group_id = $this->group_id;
					}
				}


				if(!$model->date_pick)
				{
					$params['date_pick'] = $date;
					$model->date_pick = $date;
				}

				Account::model()->updateByPk($model->id, $params);

				$model->check_priority = Account::PRIORITY_NOW;
			}

			if(!$model->reserveAmount($arr['amount']))
			{
				self::$lastErrorCode = self::ERROR_NO_WALLETS;
				toLogError('ошибка резервирования суммы на '.$model->login.'(amount: '.$arr['amount'].', limit_in: '.$model->limit_in.', reserved: '.$model->reserveAmount.') : '.$model::$lastError);
				return false;
			}
		}

		if($pickNewCount)
			toLogRuntime('взято '.$pickNewCount.' новых аккаунтов');

		return $resultAccounts;
	}

	/**
	 * получение кошельков при критическом режиме
	 * выдаем критические кошельки по 1 на манагера
	 * @param $params array [
	 * 	'count'=>2,//кол-во
	 * 	'label'=>'test',//метка(не обязательно),
	 *
	 * ]
	 * @return Account[]|false
	 */
	public function pickAccountsCritical(array $params)
	{

	}

	/**
	 * @return string
	 */
	public function getLastSeenStr()
	{
		if($this->last_seen)
			return date(cfg('dateFormat'), $this->last_seen);
		else
			return '';
	}

	/**
	 * возвращает кошельки менеджера в список свободных, если нет платежей $interval секунд
	 * @param int $interval
	 * @return int
	 */
	public function returnToFree($interval)
	{
		$returnCount = 0;

		$accounts  = $this->getAccounts(false);

		foreach($accounts as $account)
		{
			if(
				time() - $account->date_pick > $interval
				and !$account->getLastTransaction()
				//не убирать если есть в заявке
				and !$account->reserveAmount()
			)
			{
				if($account->returnInToFree())
					$returnCount++;
			}
		}

		return $returnCount;
	}

	public function saveProfile($params)
	{
		$time = time() + 3600*24*365;	//куки на год

		$this->jabber = $params['jabber'];
		$this->theme = $params['theme'];
		$this->send_notifications = $params['send_notifications'];

		if($this->save())
		{
			setcookie('theme', $this->theme, $time);
			return true;
		}
		else
			return false;
	}

	public function getProfile()
	{
		return array(
			'jabber'=>$this->jabber,
			'theme'=>Yii::app()->theme->name,
			'send_notifications'=>$this->send_notifications,
			'apiKey'=>$this->api_key,
			'apiSecret'=>$this->api_secret,
			'successUrl'=>$this->success_url,
			'failUrl'=>$this->fail_url,
		);
	}

	/**
	 * магазин юзера
	 * @return StoreApi|null
	 */
	public function getStore()
	{
		if($store = StoreApi::model()->find("`user_id`={$this->id}"))
			return $store;
		else
			return null;
	}


	public static function generateLogin($clientId, $role)
	{
		if($role == self::ROLE_MANAGER)
			$loginPart = 'man';
		elseif($role == self::ROLE_CONTROL)
			$loginPart = 'control';
		elseif($role == self::ROLE_FINANSIST)
			$loginPart = 'fin';
		else
			return false;

		$tryCount = 100;
		$i = 1;

		do
		{
			$login = $loginPart.$clientId.$i;

			if(!User::model()->find("`login`='$login'"))
				return $login;

			$i++;

		} while($i < $tryCount);

		toLog('ошибка генерации логина clientId='.$clientId.', role='.$role);

		return false;
	}

	public static function noticeGf($text)
	{
		if($wheelUser = User::getWheelUser() and $wheelUser->jabber)
			$to = $wheelUser->jabber;
		else
		{
			toLogError('jabberBot: невозможно передать сообщение для гф, никого нет у штурвала: '.$text);
			return false;
		}

		return self::sendJabberMsg($to, $text);
	}

	public static function noticeAdmin($text)
	{
		if($adminUser = User::model()->find("`role`='".User::ROLE_ADMIN."'") and $adminUser->jabber)
			$to = $adminUser->jabber;
		else
		{
			toLogError('jabberBot: невозможно передать сообщение админу(нет жабы): '.$text);
			return false;
		}

		return self::sendJabberMsg($to, $text);
	}

	/**
	 * уведомление манагеров и финов о банах и перелимитах
	 * @param string $text
	 */
	public function noticeManager($text)
	{
		if(
			$this->jabber
			and $this->send_notifications
		)
			self::sendJabberMsg($this->jabber, $text);
	}

	/**
	 * изменение групп пользователей
	 * @param array $paramsArr ['userId'=>groupId, ...]
	 * @return bool
	 */
	public static function updateGroups(array $paramsArr)
	{
		print_r($paramsArr);die;
		$modelsForSave = array();

		//проверка
		foreach($paramsArr as $userId=>$groupId)
		{
			if(!$user = User::getUser($userId))
			{
				self::$lastError = 'пользователь '.$userId.' не найден';
				return false;
			}

			if(
				$user->active
				and ($user->role == User::ROLE_MANAGER or $user->role == User::ROLE_FINANSIST)
			)
			{
				if(in_array($groupId, array_keys(Account::getGroupArr())))
				{
					if($groupId != $user->group_id)
					{
						$user->group_id = $groupId;
						$modelsForSave[] = $user;
					}
				}
				else
				{
					self::$lastError = 'что-то не так номером группы у пользователя '.$user->login;
					return false;
				}
			}
			else
			{
				self::$lastError = 'что-то не так с пользователем '.$user->login;
				return false;
			}
		}

		//обновление
		foreach($modelsForSave as $user)
		{
			if(!$user->save())
			{
				self::$lastError = 'ошибка сохранения '.$user->name.': '.self::$lastError;
				return false;
			}
		}

		self::$msg = 'изменено '.count($modelsForSave).' записей';

		self::$lastError = 'не изменено ни одной записи';
		return false;
	}

	/**
	 * активные юзеры с возможностью добавления групп (менеджеры и фины)
	 * @return User[]
	 */
	public static function activeUsersForGroups()
	{
		return self::model()->findAll(array(
			'condition'=>"`active`=1 AND `role` IN('".self::ROLE_MANAGER."', '".self::ROLE_FINANSIST."')",
			'order'=>"`client_id` ASC, `group_id` ASC",
		));
	}

	/**
	 * если есть группа то вернет кол-во юзеров с такой группой
	 * @return int
	 */
	public function getGroupRepeatCount()
	{
		if($this->group_id)
			return self::model()->count("`client_id`={$this->client_id} AND `group_id`={$this->group_id}");
		else
			return 0;
	}

	/**
	 * устанавливает group_id новому юзеру
	 */
	public function setGroupId()
	{
		$groupCountArr = [];

		foreach(Account::getGroupArr() as $groupId=>$arr)
			$groupCountArr[$groupId] = 0;

		$users = self::model()->findAll("`client_id`={$this->client_id} AND `active`=1 AND `group_id`>0");
		/**
		 * @var self[] $users
		 */

		if($users)
		{
			foreach($users as $user)
				$groupCountArr[$user->group_id]++;

			asort($groupCountArr);
		}

		return key($groupCountArr);
	}

	/**
	 * todo: разобраться в новом боте, нужна нормлаьная обработка ошибок
	 *
	 * @param string $to
	 * @param string $text
	 * @return bool
	 */
	private static function sendJabberMsg($to, $text)
	{
		$cfg = cfg('notice_test');

		//антиспам
		$dateStr = date('H:i') . ': ';

		//антизадолбименясообщениями
		$lastMessageTimestamp = Yii::app()->cache->get('jabberMsg'.$to);

		if($lastMessageTimestamp and time() - $lastMessageTimestamp < $cfg['interval'])
		{
			toLogRuntime('jabber msg Пропуск сообщения: '.Tools::shortText($dateStr.$text, 50).' => '.$to);
			return true;
		}

		if(!YII_DEBUG)
		{
			$conn = new XMPPHP($cfg['botServer'], 5222, $cfg['botLogin'], $cfg['botPass'], 'xmpphp', $cfg['botServer']);
			$conn->connect();
			$conn->processUntil('session_start');
			$conn->presence();
			$conn->message($to, $dateStr . $text);
			$conn->disconnect();
		}

		Yii::app()->cache->set('jabberMsg'.$to, time(), $cfg['interval']);

		toLogRuntime('jabber msg: '.Tools::shortText($dateStr.$text, 50).' => '.$to);

		return true;
	}

	/**
	 * @return WexAccount[]
	 */
	public function getWexAccount()
	{
		return WexAccount::model()->find("`user_id`='{$this->id}'");
	}


	/**
	 * меняет ключи апи у юзера
	 */
	public function changeApiKeys()
	{
		$this->api_key = Tools::generateCode('ASDFGHJKWQEYRTBZVXCMVNBLKGPOUYRH', 16);

		if(self::model()->find("`api_key`='{$this->api_key}'"))
		{
			self::$lastError  = 'не удалось сменить доступы, попробуйте еще раз';
			return false;
		}

		$this->api_secret = Tools::generateCode('ASDFGHJKWQEYRTBZVXCMVNBLKGPOUYRHasdfghjklqwertyuiopzxcvbnm1234567890', 32);

		if(self::model()->find("`api_secret`='{$this->api_secret}'"))
		{
			self::$lastError  = 'не удалось сменить доступы, попробуйте еще раз';
			return false;
		}

		return $this->save();
	}

	/**
	 * добавляем urlResult, туда будут приходить уведомления об оплате
	 */
	public function saveUrl($params)
	{
		if($params['urlResult'])
			$this->url_result = trim($params['urlResult']);
		else
		{
			self::$lastError  = 'Ошибка сохранения url';
			return false;
		}

		return $this->save();
	}

	/**
	 * очищаем urlResult
	 */
	public function clearUrl()
	{
		$this->url_result = '';

		return $this->save();
	}

	/**
	 * @return WexAccount[]
	 */
	public function getPayeerAccount()
	{
		return PayeerAccount::model()->find("`user_id`='{$this->id}'");
	}

	/**
	 * Возвращает аккаунт платежа (используется в NextQiwiPay)
	 * Если есть текущие кошельки и на них нет зарезервированных сумм то отдать их, если таких нет то отдать новые,
	 * 	если и их нет то вернуть те на которых еще можно зарезервировать
	 *
	 * кооды ошибок: self::ERROR_NO_WALLETS, ERROR_WALLET_LIMIT
	 *
	 * @param float $amount	заливаемая сумма
	 * @return Account|bool
	 *
	 * todo: если режим Свои кошельки, то не выдавать наши, и наоборот
	 */
	public function pickAccountForPayment($amount)
	{
		if(
			!config('pickAccountEnabled')
			or !$this->client->pick_accounts_next_qiwi
		)
		{
			self::$lastError = 'выдача новых кошельков временно прекращена';
			self::$lastErrorCode = self::ERROR_NO_WALLETS;
			return false;
		}

		$limitMin = 10000;	//не выдавать кошельки если лимита останется меньше ...
		$minAmount = 2;

		$currentAccounts = $this->getCurrentAccounts("`limit_in` DESC");

		$ostatok = $amount;	//на какую сумму еще осталось подобрать кошельков, если 0 то цикл завершить

		$resultAccounts = array();	//кошелек-сумма

		$pickNewCount = 0;	//сколько новых выдано

		if($currentAccounts)
		{
			foreach($currentAccounts as $account)
			{
				if($account->error or $account->comment == 'stopping')
					continue;

				$firstTransaction = end($account->getAllTransactions());

				if($firstTransaction)
				{
					if((time() - $firstTransaction->date_add) > NextQiwiPay::TIME_TO_DISABLE_PAY_URL)
					{
						$account->comment = 'stopping';
						$account->save();
						continue;
					}
				}

				$reservedAmount = $account->reserveAmount;

				$possibleAmount = $account->limit_in - $reservedAmount - $minAmount;

				$maxBalance = $account->maxBalance;

				if($possibleAmount > $maxBalance - $reservedAmount)
					$possibleAmount = $maxBalance - $reservedAmount;

				if($possibleAmount <= $limitMin)
					continue;

				if($possibleAmount > $ostatok)
					$possibleAmount = $ostatok;

				if($account->dayLimit - $reservedAmount < $ostatok)
					continue;

				if($possibleAmount >= $minAmount)
				{
					$resultAccounts[$account->id] = array('account'=>$account, 'amount'=>$possibleAmount);
					$ostatok = $ostatok - $possibleAmount;
				}

				//если остаток меньше 2 рублей то добавить к последнему кошельку
				if($ostatok < Account::BALANCE_MIN and $resultAccounts[$account->id])
				{
					$resultAccounts[$account->id]['amount'] += $ostatok;
					$ostatok  = 0;
				}

				if($ostatok < 0)
				{
					toLogStoreApi('User::pickAccountsByAmount() исключение001');
					return false;
				}

				if($ostatok == 0)
					break;
			}
		}


		if($ostatok > 0)
		{
			//если текущих кошельков больше чем self::CURRENT_WALLET_LIMIT то не выдавать
			$currentAccounts = $this->getCurrentAccounts();

			$goodCurrentAccountsCount = 0;

			foreach($currentAccounts as $currentAccount)
			{
				if($currentAccount->error)
					continue;

				if(config('criticalMode') and $currentAccount->limit_in < cfg('criticalAccountMinLimit'))
					continue;

				$goodCurrentAccountsCount++;
			}

			$maxAccountCount = (config('criticalMode')) ? cfg('criticalAccountCountPerUser') : cfg('managerAccountLimit');

			if($goodCurrentAccountsCount >= $maxAccountCount)
			{
				self::$lastErrorCode = self::ERROR_WALLET_LIMIT;
				self::$lastError = 'ограничение на выдачу кошельков';
				toLogError('ограничение на выдачу кошельков '.$this->client->name.': '.$this->name);
				return false;
			}


			$freeInAccounts = Account::getFreeInAccounts($this->client_id);
			
			if($freeInAccounts)
			{
				foreach($freeInAccounts as $account)
				{
					//опять то же самое повторяем для новых кошельков

					$possibleAmount = $account->limit_in;

					$maxBalance = $account->maxBalance;

					if($possibleAmount > $maxBalance)
						$possibleAmount = $maxBalance;

					if($possibleAmount > $ostatok)
						$possibleAmount = $ostatok;

					if($account->limit_in - $possibleAmount < $limitMin)
						$possibleAmount = $account->limit_in - $limitMin;

					if($possibleAmount > $minAmount)
					{
						$resultAccounts[$account->id] = array('account'=>$account, 'amount'=>$possibleAmount);
						$ostatok -= $possibleAmount;

						$pickNewCount++;
					}

					//если остаток меньше 2 рублей то добавить к последнему кошельку
					if($ostatok < Account::BALANCE_MIN and $resultAccounts[$account->id])
					{
						$resultAccounts[$account->id]['amount'] += $ostatok;
						$ostatok  = 0;
					}

					if($ostatok < 0)
					{
						toLog('User::pickAccountsByAmount() исключение001');
						return false;
					}

					if($ostatok == 0)
						break;
				}
			}
		}

		if($ostatok > 0)
		{
			//не хватило кошельков
			self::$lastError = 'недостаточно кошельков';
			self::$lastErrorCode = self::ERROR_NO_WALLETS;
			toLogError('недостаточно проверенных кошельков у кл'
				.$this->client_id.' , для суммы : ' .formatAmount($amount).', '
				. $this->name, false, true);
			return false;
		}

		$date = time();

		foreach($resultAccounts as $arr)
		{
			/**
			 * @var $model Account
			 */
			$model = $arr['account'];

			if(!$model->user_id)
			{
				$params = array(
					'check_priority'=>Account::PRIORITY_NOW,	//приоритет выше среднего
				);

				if(!$model->user_id)
				{
					$params['user_id'] = $this->id;
					$model->user_id = $this->id;

					// с 16 не продумывал, лучше пока не менять ничего, может на работе проксей сказаться
					if($this->group_id and $this->client_id != 16)
					{
						$params['group_id'] = $this->group_id;
						$model->group_id = $this->group_id;
					}
				}


				if(!$model->date_pick)
				{
					$params['date_pick'] = $date;
					$model->date_pick = $date;
				}

				Account::model()->updateByPk($model->id, $params);

				$model->check_priority = Account::PRIORITY_NOW;
			}

			if(!$model->reserveAmount($arr['amount']))
			{
				self::$lastErrorCode = self::ERROR_NO_WALLETS;
				toLogError('ошибка резервирования суммы на '.$model->login.'(amount: '.$arr['amount'].', limit_in: '.$model->limit_in.', reserved: '.$model->reserveAmount.') : '.$model::$lastError);
				return false;
			}
		}

		//если один элемент в массиве почему то current возвращает false
		if(count($resultAccounts) == 1)
		{
			reset($resultAccounts);
		}

		if($pickNewCount)
			toLogRuntime('взято '.$pickNewCount.' новых аккаунтов');

		if($resultAccounts)
			return current($resultAccounts)['account'];
		else
			return false;
	}


	/*
	 * выводит все текущие коши мерчанта манагеров
	 */
	public function getMerchantWallets()
	{
		$result = array();

		if($this->role == self::ROLE_FINANSIST)
		{
			$models = MerchantWallet::model()->findAll(array(
				'condition'=>"
					`client_id`='{$this->client_id}'
					AND `user_id`!=0
					AND `date_used`=0
					AND `enabled`=1
					AND `hidden` < 1
				",
				'order'=>"`user_id` DESC, `date_check` DESC",
			));

			self::$someData['count'] = count($models);

			//сначала кошельки текущего юзера, потом остальных
			foreach($models as $model)
			{
				if($model->user->id == $this->id)
					$result[$model->user->name][] = $model;
			}

			foreach($models as $model)
			{
				if($model->user->id != $this->id)
					$result[$model->user->name][] = $model;
			}
		}
		elseif($this->role == self::ROLE_MANAGER)
		{
			$models = MerchantWallet::model()->findAll(array(
				'condition'=>"
    				`user_id`='{$this->id}'
    				AND `date_used`=0
    				AND `enabled`=1
					AND `comment` <> 'stopping'
    			",
				'order'=>"`user_id` DESC, `date_check` DESC",
			));

			/**
			 * @var MerchantWallet[] $models
			 */

			foreach($models as $model)
			{
				$result[$model->user->name][] = $model;
			}
		}

		return $result;
	}

	/*
	 * выводит все текущие коши мерчанта манагеров
	 */
	public function getYandexMerchantWallets()
	{
		$result = array();

		$walletType = array(
			'yandex'
		);

		$typeCond = " ('".implode("', '", $walletType)."') ";

		if($this->role == self::ROLE_FINANSIST)
		{
			$models = MerchantWallet::model()->findAll(array(
				'condition'=>"
					`client_id`='{$this->client_id}'
					AND `user_id`!=0
					AND `date_used`=0
					AND `enabled`=1
					AND `hidden` < 1
					AND `type` in $typeCond
				",
				'order'=>"`user_id` DESC, `date_check` DESC",
			));

			self::$someData['count'] = count($models);

			//сначала кошельки текущего юзера, потом остальных
			foreach($models as $model)
			{
				if($model->user->id == $this->id)
					$result[$model->user->name][] = $model;
			}

			foreach($models as $model)
			{
				if($model->user->id != $this->id)
					$result[$model->user->name][] = $model;
			}
		}
		elseif($this->role == self::ROLE_MANAGER)
		{
			$models = MerchantWallet::model()->findAll(array(
				'condition'=>"
    				`user_id`='{$this->id}'
    				AND `date_used`=0
    				AND `enabled`=1
					AND `comment` <> 'stopping'
					AND `type` in $typeCond
    			",
				'order'=>"`user_id` DESC, `date_check` DESC",
			));

			/**
			 * @var MerchantWallet[] $models
			 */

			foreach($models as $model)
			{
				$result[$model->user->name][] = $model;
			}
		}

		return $result;
	}

	/*
	 * выводит все текущие коши мерчанта манагеров
	 */
	public function getQiwiMerchantWallets()
	{
		$result = array();

		$walletType = array(
			'qiwi_wallet',
			'qiwi_card'
		);

		$typeCond = " ('".implode("', '", $walletType)."') ";

		if($this->role == self::ROLE_FINANSIST)
		{
			$models = MerchantWallet::model()->findAll(array(
				'condition'=>"
					`client_id`='{$this->client_id}'
					AND `user_id`!=0
					AND `date_used`=0
					AND `enabled`=1
					AND `hidden` < 1
					AND `type` in $typeCond
				",
				'order'=>"`user_id` DESC, `date_check` DESC",
			));

			self::$someData['count'] = count($models);

			//сначала кошельки текущего юзера, потом остальных
			foreach($models as $model)
			{
				if($model->user->id == $this->id)
					$result[$model->user->name][] = $model;
			}

			foreach($models as $model)
			{
				if($model->user->id != $this->id)
					$result[$model->user->name][] = $model;
			}
		}
		elseif($this->role == self::ROLE_MANAGER)
		{
			$models = MerchantWallet::model()->findAll(array(
				'condition'=>"
    				`user_id`='{$this->id}'
    				AND `date_used`=0
    				AND `enabled`=1
					AND `comment` <> 'stopping'
					AND `type` in $typeCond
    			",
				'order'=>"`user_id` DESC, `date_check` DESC",
			));

			/**
			 * @var MerchantWallet[] $models
			 */

			foreach($models as $model)
			{
				$result[$model->user->name][] = $model;
			}
		}

		return $result;
	}

	public function getYandexAccounts()
	{
		$result = array();

		if($this->role == self::ROLE_FINANSIST)
		{
			$models = YandexAccount::model()->findAll(array(
				'condition'=>"
					`client_id`='{$this->client_id}'
					AND `user_id`!=0
					AND `error`=''
					AND `date_pick` > 0
					AND `hidden` < 1
				",
				'order'=>"`user_id` DESC, `date_check` DESC",
			));

			self::$someData['count'] = count($models);

			//сначала кошельки текущего юзера, потом остальных
			foreach($models as $model)
			{
				if($model->user->id == $this->id)
					$result[$model->user->name][] = $model;
			}

			foreach($models as $model)
			{
				if($model->user->id != $this->id)
					$result[$model->user->name][] = $model;
			}
		}
		elseif($this->role == self::ROLE_MANAGER)
		{
			$models = YandexAccount::model()->findAll(array(
				'condition'=>"
    				`user_id`='{$this->id}'
    				AND `error`=''
					AND `date_pick` > 0
					AND `hidden` < 1
    			",
				'order'=>"`user_id` DESC, `date_check` DESC",
			));

			/**
			 * @var YandexAccount[] $models
			 */

			foreach($models as $model)
			{
				$result[$model->user->name][] = $model;
			}
		}

		return $result;
	}

	public function getMerchantUser()
	{
		return MerchantUser::model()->findByAttributes(['uni_user_id'=>$this->id]);
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public static function getModel(array $params)
	{
		return self::model()->findByAttributes($params);
	}

}