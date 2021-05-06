<?php
/**
 * новости
 */
class NewsController extends Controller
{
	public $defaultAction = 'list';

	/*
	 * список прокси с информацией о стабильности
	 */
	public function actionList($editId=false, $deleteId=false)
	{
		if (!$this->isAdmin() and !$this->isFinansist() and !$this->isControl() and !$this->isGlobalFin() and !$this->isManager())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();


		//убираем новости для кл16
		if($user->client and !$user->client->checkRule('news'))
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['save'])
		{
			//редактировать или добавить
			if(News::updateNews($params, $user->id))
			{
				$this->success('новость сохранена');
				$this->redirect('news/list');
			}
			else
				$this->error('ошибка: '.News::$lastError);
		}
		if($_POST['setGlobalMsg'])
		{
			if($this->isGlobalFin())
			{
				config('globalMsg', strip_tags($params['globalMsg']));
				$this->success('сообщение изменено');
				$this->redirect('news/list');
			}
		}
		elseif($editId and $model = News::getModel($editId, $user->id))
		{
			$params = $model->attributes;
			$params['userRoles'] = $model->userRoles;
		}
		elseif($deleteId)
		{
			if(News::deleteNews($deleteId, $user->id))
			{
				$this->success("новость #$deleteId удалена");
				$this->redirect('news/list');
			}
			else
				$this->error('ошибка удаления новости '.News::$lastError);
		}

		$models = News::getList($user->id);

		$this->render('list', array(
			'models' => $models,
			'params'=>$params,
			'roles'=>News::getRolesArr(),
			'globalMsg'=>config('globalMsg'),
		));
	}

	public function actionView($id)
	{
		if (!$this->isAdmin() and !$this->isFinansist() and !$this->isControl() and !$this->isGlobalFin() and !$this->isManager())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		if($model = News::getModel($id, $user->id))
			$user->readNews($model->id);

		$this->render('view', array(
			'model' => $model,
		));
	}

}