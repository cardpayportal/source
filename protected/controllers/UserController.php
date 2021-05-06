<?php
class UserController extends Controller
{
	public $defaultAction = 'list';
	
	public function actionList()
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));
		
		$params = $_POST['params'];
		
		if($_POST['login'])
		{
			if($this->isAdmin())
			{
				if(User::loginAs($params['id']))
					$this->redirect(cfg('index_page'));
				else
					$this->error(User::$lastError);
			}
		}
		elseif($_POST['disable'])
		{
			if(User::disable($params['id']))
				$this->success('пользователь '.$params['id'].' отключен');
			else
				$this->error('ошибка отключения юзера');
		}
		elseif($_POST['enable'])
		{
			if(User::enable($params['id']))
				$this->success('пользователь '.$params['id'].' задействован');
			else
				$this->error('ошибка активации юзера');
		}
		elseif($_POST['changePass'])
		{
			if(User::changePass($params['id']))
				$this->success(User::$msg);
			else
				$this->error('ошибка смены пароля: '.User::$lastError);
		}


		if($this->isAdmin())
			$cond = "";
		else
		{
			$user = User::getUser();
			$cond = " AND `client_id`='{$user->client_id}' AND (`role`='".User::ROLE_USER."' OR `role`='".User::ROLE_FINANSIST."')";
		}


		
		$models = User::model()->findAll(array(
			'condition'=>"`role`!='".User::ROLE_ADMIN."'".$cond,
			'order'=>"`client_id`, `id`",
		));

		/*
		if($this->isAdmin())
		{
			print_r($models);die;
		}
		*/

		$this->render('list', array(
			'models'=>$models,
			'params'=>$params,
		));
	}

	/**
	 * регистрация новых юзеров(только для админа)
	 */
	public function actionRegister()
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		$user = User::getUser();

		$registerUser = ($user->role == User::ROLE_ADMIN) ? false : $user;

		if($_POST['register'])
		{
			if(User::register($params, $registerUser))
			{
				if(User::$passGenerated)
					$msg = "$params[login] ".User::$passGenerated;
				else
					$msg = 'регистрация прошла успешно';

				if(!cfg('default_user_active'))
					$msg .= ', дождитесь активации аккаунта';

				$this->success($msg);
				$this->redirect('user/register');
			}
			else
				$this->error('ошибка регистрации: '.User::$lastError);
		}

		$this->render('register', array(
			'params'=>$params,
		));
	}

	/**
	 * опраделение активности пользователя
	 * возвращает массив данных
	 * к этому экшну обращается js при любом действии юзера
	 * todo: доделать
	 */
	public function actionGetState()
	{
		$result = array();

		if($this->isActive())
		{

		}

		$this->renderPartial('//system/json', array(
			'result'=>$result,
		));
	}

	public function actionProfile()
	{
		if(!$this->isActive())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		$params = $_POST['params'];

		if($_POST['save'])
		{

			if($user->saveProfile($params))
			{
				$this->success('профиль сохранен');
				$this->redirect('user/profile');
			}
			else
				$this->error('ошибка сохренения: '.User::$lastError);
		}
		elseif($_POST['changeApi'])
		{
			if($user->changeApiKeys())
			{
				$this->success('API ключи изменены');
				$this->redirect('user/profile');
			}
		}
		elseif($_POST['saveUrl'])
		{
			if($user->saveUrl($params))
			{
				$this->success('Данные URL сохранены');
				$this->redirect('user/profile');
			}
			else
				$this->error('Ошибка сохранения URL');
		}
		elseif($_POST['clearUrl'])
		{
			if($user->clearUrl())
			{
				$this->success('Данные URL очищены');
				$this->redirect('user/profile');
			}
			else
				$this->error('Ошибка удаления URL');
		}

		$params = $user->getProfile();


		$this->render('profile', array(
			'params'=>$params,
			'user'=>$user,
		));
	}

	/**
	 * хз что это и что делает надо разобраться
	 */
	public function actionGroups()
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$groups = $_POST['groups'];

		if($_POST['save'])
		{
			if(User::updateGroups($groups))
				$this->success(User::$msg);
			else
				$this->error('ошибка: '. User::$lastError);
		}

		$this->render('groups', array(
			'users'=>User::activeUsersForGroups(),
			'groups'=>$groups,
		));
	}
}