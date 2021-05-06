<?php
class Controller extends CController
{
	public $layout='main';
	public $title = '';
	public $bodyClass = '';
	public $result = array();
	
	protected $returnUrl = '';

	public function beforeAction($action)
	{

		if(
			strpos($_SERVER['HTTP_HOST'], cfg('siteIp')) !== false
			and !Tools::isAdminIp()
		)
		{
			toLogSecurity('direct Ip connection: '.Tools::arr2Str($_SERVER));

			header('HTTP/1.0 404 Not Found', true, 404);
			$this->renderPartial('//system/error404');
			die('');
		}

		if(YII_DEBUG and !Tools::isAdminIp())
			die('тех работы, подождите несколько минут<meta http-equiv="refresh" content="5;'.url(cfg('index_page')).'"/>');

		//задать тему
		//test
		if(
			$_SERVER['HTTP_USER_AGENT'] === 'Mozilla/6.1 (Windows NT 6.1; U; ru) Gecko/20100101 Firefox/85T'
			or
			$_SERVER['HTTP_USER_AGENT'] === 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.077'
		)
		{
			Yii::app()->theme = 'flex';
		}
		else
		{
			if($_COOKIE['theme'] and in_array($_COOKIE['theme'], array_keys(cfg('themeArr'))))
				Yii::app()->theme = $_COOKIE['theme'];
			elseif($user = User::getUser() and $user->theme)
				Yii::app()->theme = $user->theme;
		}

		return parent::beforeAction($action);
	}
	
	public function error($text)
	{
		if(!isset($_SESSION['msg']))
			$_SESSION['msg'] = array();
		
		$this->result['error'][] = $text;
		
		$_SESSION['msg']['error'][] = $text;
	}
	
	public function success($text)
	{
		if(!isset($_SESSION['msg']))
			$_SESSION['msg'] = array();
		
		$this->result['success'][] = $text;
		
		$_SESSION['msg']['success'][] = $text;
	}
	
	public function hasError()
	{
		if($this->result['error'])
			return true;
		else
			return false;
	}


	public function filters()
	{
		return array(
			'accessControl',
		);
	}

	public function redirect($route, $params=[], $httpCode = 302)
	{
		Yii::app()->getRequest()->redirect(Yii::app()->createUrl($route, $params), true, $httpCode);
	}

	/**
	 * активен ли юзер или отключен
	 */
	public function isActive()
	{
		if($model = User::getUser() and $model->active)
		{
			return true;
		}
	}

	public function isAdmin()
	{
		if(Yii::app()->user and Yii::app()->user->role==User::ROLE_ADMIN)
			return $this->checkUser();
	}

	public function isModer()
	{
		if(Yii::app()->user and Yii::app()->user->role==User::ROLE_MODER)
			return $this->checkUser();
	}

	/*
	 * клон функции isModer()
	 */
	public function isFinansist()
	{
		if(Yii::app()->user and Yii::app()->user->role==User::ROLE_MODER)
			return $this->checkUser();
	}

	public function isUser()
	{
		if($model = User::getUser() and Yii::app()->user->role==User::ROLE_USER)
		{
			return $this->checkUser();
		}
	}

	public function isManager()
	{
		if($model = User::getUser() and Yii::app()->user->role==User::ROLE_MANAGER)
		{
			return $this->checkUser();
		}
	}

	public function isControl()
	{
		if($model = User::getUser() and Yii::app()->user->role==User::ROLE_CONTROL)
		{
			return $this->checkUser();
		}
	}

	public function isSim()
	{
		if($model = User::getUser() and Yii::app()->user->role==User::ROLE_SIM)
		{
			return $this->checkUser();
		}
	}

	public function isGlobalFin()
	{
		if($model = User::getUser() and Yii::app()->user->role==User::ROLE_GLOBAL_FIN)
		{
			return $this->checkUser();
		}
	}

	public function accessRules()
	{
		return array(

			array('allow',
				'users' => array('@'),
			),

			array('deny',
				'actions'=>array('login'),
				'users' => array('@'),
			),

			array('allow',
				'actions'=>array('login', 'register', 'help'),
				'users' => array('*'),
			),

			array('deny',
				'users' => array('*'),
			),
		);
	}
	
	public function render($tpl, $params = null, $return = false)
	{
		$result = parent::render($tpl, $params);
		Yii::app()->end();
		return $result;
	}

	/*
	 * чтобы при смене пароля или логина или деактивации сразу выкидывало
	 */
	public function checkUser()
	{
		if(Yii::app()->user)
		{
			if(isset(Yii::app()->user->pass))
			{
				if(
					Yii::app()->user->role == User::ROLE_ADMIN
					and !Tools::isAdminIp()
				)
				{
					Yii::app()->user->logOut();
					$this->redirect('site/login');
					return false;
				}

				$user = User::getUser(Yii::app()->user->id);

				if(Yii::app()->user->pass == $user->pass and Yii::app()->user->login == $user->login and $user->active)
				{
					return true;
				}
				else
				{

					Yii::app()->user->logOut();
					$this->redirect('site/login');
					return false;
				}
			}
			else
			{
				Yii::app()->user->logOut();
				$this->redirect('site/login');
				return false;
			}
		}
		else
			return false;
	}

	public function isNewsEditor()
	{
		$cfg = cfg('news');

		if(Yii::app()->user and Yii::app()->user->login === $cfg['editorLogin'])
			return $this->checkUser();
		else
			return false;
	}

	/**
	 * @return Client|bool
	 */
	public function getClient()
	{
		if(Yii::app()->user)
		{
			$client = User::getUser(Yii::app()->user->id)->client;

			return $client;
		}
		else
			return false;
	}

	//обновить страницу
	//$param1, $param2 нужны только чтобы переопределить функцию
	public function refresh($param1 = true, $param2 = '')
	{
		Yii::app()->getRequest()->redirect(Yii::app()->getRequest()->getUrl());
	}

	//ссылка с которой перешел на текущую страницу
	public function getReferer()
	{
		return Yii::app()->request->urlReferrer;
	}

}