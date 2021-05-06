<?php

class SiteController extends Controller
{
	public $defaultAction = 'login';

	public function actionIndex()
	{
		$user = User::getUser();

		if($this->isManager())
		{
			if($user and $user->id == cfg('imageUserId'))
				$this->redirect('manager/imageList');
			//костыль чтобы оператора сим переадресовать при логине куда надо
			elseif($user->client and $user->client->checkRule('sim'))
				$this->redirect('sim/transaction/list');
			else
				$this->redirect('user/profile');
		}
		elseif($this->isFinansist())
		{
			if($user->client and $user->client->checkRule('sim'))
				$this->redirect('sim/account/list');
			else
				$this->redirect('manager/stats');
		}
		elseif($this->isAdmin())
		{
			$this->redirect('user/list');
		}
		elseif($this->isControl())
		{
			$this->redirect('news/list');
		}
		elseif($this->isGlobalFin())
		{
			$this->redirect('news/list');
		}
		elseif($this->isSim())
		{
			$this->redirect('sim/account/list');
		}

		$this->render('index');
	}
	
	
	public function actionLogin($action = false)
	{
		if($this->isActive())
			$this->redirect(cfg('index_page'));

		if($action == 'forgotPassword')
		{
			$this->error('Забыл пароль?? ну... бывает(');
			$this->redirect('site/login');
		}

		$params = $_POST['params'];
		
		if($_POST['sign_in'])
		{
			if(User::auth($params['login'], $params['pass']))
			{
				$redirUrl = (Yii::app()->user->returnUrl) ? Yii::app()->user->returnUrl : cfg('index_page');
				Yii::app()->getRequest()->redirect($redirUrl);
			}
			else
				$this->error(User::$lastError);
		}
		
		$this->layout = 'auth';

		//для storeAPi
		if(
			strpos($_SERVER['SERVER_NAME'], 'qiwishow.cc')!==false
			or
			strpos($_SERVER['SERVER_NAME'], 'qprocesing.net')!==false
			or
			strpos($_SERVER['SERVER_NAME'], 'apiapi.pw')!==false
		)
			die('');
		
		$this->render('login', array(
			'params'=>$params,
		));
	}
	
	public function actionExit()
	{
		if(!Yii::app()->user->isGuest)
			Yii::app()->user->logOut();
			
		$this->redirect('site/login');
	}
	
	public function actionHelp($page=false)
	{
		$this->redirect(cfg('index_page'));

		$disallowArr = array();
		
		if($this->isUser())
			$disallowArr[] = array(
				'finansist',
				'supervisor',
			);
		elseif($this->isModer())
			$disallowArr[] = array(
				'manager',
				'supervisor',
			);
		elseif($this->isSupervisor())
			$disallowArr[] = array(
				'manager',
				'finansist',
			);
		
		if($page)
		{
			preg_match('!([\w_]+)!', $page, $res);
			$page = $res[1];
		}
		else
			$page = 'about';
		
		
		if(!file_exists(DIR_ROOT.'protected/                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              site/help/'.$page.'.php')
			or
			in_array($page, $disallowArr) 
		)
		{
			$this->error('страница не найдена');
		}
		
		$this->render('help', array(
			'page'=>$page,
		));
	}
	
	
}