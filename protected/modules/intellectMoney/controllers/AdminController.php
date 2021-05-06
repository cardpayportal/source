<?php

class AdminController extends Controller
{
	public $layout='//layouts/main';

	public function actionAccountList()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = Yii::app()->request->getPost('params');

		if(Yii::app()->request->getPost('add'))
		{
			$addCount = IntellectAccount::addMany($params['accounts'], $_POST['clientId'], $_POST['userId']);

			if($addCount)
				$this->success("добавлено ".$addCount);

			if(IntellectAccount::$lastError)
				$this->error(IntellectAccount::$lastError);
			else
				$this->redirect('intellectMoney/admin/accountList');
		}

		$this->render('account_list', [
			'models' => IntellectAccount::getModels(),
			'params' => $params,
		]);
	}

	public function render($tpl, $params = null, $return = false)
	{
		return  parent::render($tpl, $params);
	}

	/**
	 * метод вызывается ajax, нужен для динамической подгрузки пользователей в выпадающий список в форме
	 */
	public function actionLoadUsers()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$data = User::model()->findAll('client_id=:client_id and role=:role', [
			':client_id' => (int)$_POST['clientId'],
			':role' => User::ROLE_MANAGER,
			]);

		$data = CHtml::listData($data, 'id', 'login');

		echo "<option value=''>Select User</option>";
		foreach($data as $value => $login)
		{
			echo CHtml::tag('option', ['value' => $value], CHtml::encode($login), true);
		}
	}

}