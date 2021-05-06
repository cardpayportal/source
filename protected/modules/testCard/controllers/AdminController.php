<?php
/**
 */

class AdminController extends Controller
{
	public $defaultAction = 'list';
	public $layout='//layouts/main';

	public function render($tpl, $params = null, $return = false)
	{
		$result = parent::render($tpl, $params);
		Yii::app()->end();
		return $result;
	}

	public function actionList()
	{
		session_write_close();
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$session = &Yii::app()->session;
		$request = &Yii::app()->request;

		$params = $request->getPost('params');

		$interval = isset($session['testCardStats']) ? $session['testCardStats'] : [];

		$user = User::getUser();

		$params = Yii::app()->request->getPost('params');

		if($request->getPost('addWallet'))
		{

			$model = new TestCardModel;
			$model->wallet = $params['wallet'];
			$model->card_number = $params['cardNumber'];
			$model->balance = $params['balance'];
			$model->total_limit = $params['totalLimit'];
			$model->status = $params['status'];
			$model->client_id = $_POST['client_id'];
			$model->user_id = $_POST['user_id'];
			$model->date_add = time();
			if($model->save())
			{
				$this->success('Кошелек добавлен');
			}
			else
			{
				$this->error('Ошибка добавления');
			}
		}
		elseif($request->getPost('walletVisibleSubmit'))
		{
			//скрываем кошелек из отображения у клиента
			TestCardModel::model()->updateByPk($params['walletId'], ['hidden'=> ($params['hidden'] xor 1)]);
		}
		elseif($request->getPost('deleteWalletSubmit'))
		{
			//удаляем кошелек
			TestCardModel::model()->deleteByPk($params['walletId']);
		}
		elseif($request->getPost('deleteTransactionSubmit'))
		{
			//удаляем транзакцию
			TestTransactionModel::model()->deleteByPk($params['transactionId']);
		}
		elseif($request->getPost('addTransaction'))
		{
			//добавляем транзакцию
			$transacton = new TestTransactionModel;
			$transacton->wallet_id = $params['walletId'];
			$transacton->amount = $params['amount'];
			$transacton->client_id = $params['clientId'];
			$transacton->user_id = $params['userId'];
			$transacton->date_add = strtotime($params['date']);
			$transacton->comment = $params['comment'];
			$transacton->status = TestTransactionModel::STATUS_SUCCESS;
			$transacton->save();
		}
		elseif($params['dateStart'] and $params['dateEnd'])
		{
			$interval = $params;
			$session['testCardStats'] = $interval;
			$this->redirect('testCard/admin/list');
		}
		else
		{
			if($session['testCardStats'])
				$interval = $session['testCardStats'];
			else
			{
				$interval = [
					'dateStart' => date('d.m.Y H:i', Tools::startOfDay(time())),
					'dateEnd' => date('d.m.Y H:i', time()+24*3600),
				];

				$session['testCardStats'] = $interval;
			}
		}

		$transactions = TestTransactionModel::getModels(
			strtotime($interval['dateStart']),
			strtotime($interval['dateEnd']));

		$stats = TestTransactionModel::getStatsUser($transactions);

		$this->render('list', [
			'models' => TestCardModel::getAll(),
			'stats' => $stats,
			'params' => $params,
			'interval'=>$interval,
		]);

	}

	/**
	 * @param $cardId
	 *
	 * редактирование карты
	 */
	public function actionEditCard($cardId)
	{
		session_write_close();
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		/**
		 * @var TestCardModel $model
		 */
		if(!$model = TestCardModel::model()->findByPk($cardId))
		{
			$this->error('Запись не найдена');
			$this->redirect('testCard/admin/list');
		}

		$params = $_POST['params'];
		if($_POST['editWallet'])
		{
//			var_dump($params);die;
			$model->wallet = $params['wallet'];
			$model->card_number = $params['cardNumber'];
			$model->balance = $params['balance'];
			$model->total_limit = $params['totalLimit'];
			$model->status = $params['status'];
			$model->client_id = $_POST['client_id'];
			$model->user_id = $_POST['user_id'];
			if($model->save())
			{
				$this->success('Кошелек изменен');
			}
			else
			{
				$this->error('Ошибка изменения');
			}
		}
		else
		{
			$params['wallet'] = $model->wallet;
			$params['cardNumber'] = $model->card_number;
			$params['balance'] = $model->balance;
			$params['totalLimit'] = $model->total_limit;
			$params['status'] = $model->status;
		}

		$this->render('editCard', [
			'params' => $params,
		]);
	}

	/**
	 * метод вызывается ajax, нужен для динамической подгрузки пользователей в выпадающий список в форме
	 */
	public function actionLoadUsers()
	{
		$data = User::model()->findAll('client_id=:client_id and role in(:fin, :manager)', [
			':client_id' => (int)$_POST['client_id'],
			':fin' => User::ROLE_FINANSIST,
			':manager' => User::ROLE_MANAGER,
		]);

		$data = CHtml::listData($data, 'id', 'login');

		echo "<option value=''>Select User</option>";

		foreach($data as $value => $login)
		{
			echo CHtml::tag('option', ['value' => $value], CHtml::encode($login), true);
		}
	}

}