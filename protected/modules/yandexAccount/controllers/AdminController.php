<?php

class AdminController extends Controller
{
	public $layout='//layouts/main';

	/**
	 * список использованных кошельков для манагера
	 * возрождение киви
	 */
	public function actionAccountList()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$params = Yii::app()->request->getPost('params');

		if(Yii::app()->request->getPost('add'))
		{
			$addCount = YandexAccount::addMany($params['wallets'], $_POST['clientId'], $_POST['userId']);

			$this->success("добавлено $addCount");

			if(YandexAccount::$lastError)
				$this->error(YandexAccount::$lastError);
			else
				$this->redirect('yandexAccount/admin/accountList');
		}
		elseif(Yii::app()->request->getPost('toggleHidden'))
		{
			if(YandexAccount::toggleHidden($_POST['id']))
			{
				$this->success(YandexAccount::$msg);
				$this->redirect('yandexAccount/admin/accountList');
			}
			else
				$this->error(YandexAccount::$lastError);
		}
		elseif(Yii::app()->request->getPost('updateTransactions'))
		{
			$model = YandexAccount::getModel(['id'=>$_POST['id']]);

			if($model->updateTransactions($_POST['timestampStart']))
			{
				$this->success('платежи обновлены');
				$this->redirect('yandexAccount/admin/accountList');
			}
			else
				$this->error('ошибка '.YandexAccount::$lastError);
		}

		$this->render('account_list', [
			'models' => YandexAccount::getModels(),
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