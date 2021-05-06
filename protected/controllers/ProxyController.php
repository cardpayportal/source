<?php
/**
 * контроллер для добавления и проверки проксей
 */
class ProxyController extends Controller
{
	public $defaultAction = 'list';

	/*
	 * список прокси с информацией о стабильности
	 */
	public function actionList($category=false)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$replaceEnabled = config('proxyReplaceEnabled');

		$params = $_POST['params'];

		if($_POST['clearStats'])
		{
			if(Proxy::model()->findByPk($_POST['id'])->clearStats())
				$this->success('статистика для прокси '.$_POST['id'].' очищена');
			else
				$this->error('ошибка очистки статистики');
		}
		elseif($_POST['clearAllStats'])
		{
			if(Proxy::clearAllStats())
				$this->success('статистика для всех прокси очищена');
			else
				$this->error('ошибка очистки статистики: '.Proxy::$lastError);
		}
		elseif($_POST['toggleReplace'])
		{
			//вкл-выкл замену покси
			if($replaceEnabled)
				config('proxyReplaceEnabled', '');
			else
				config('proxyReplaceEnabled', '1');

			$replaceEnabled = config('proxyReplaceEnabled');
		}
		elseif($_POST['reboot'])
		{
			//перезагрузить прокси
			if(Proxy::model()->findByPk($_POST['id'])->reboot())
			{
				$this->success('перезагружен прокси '.$_POST['id']);
				$this->redirect('proxy/list');
			}
			else
				$this->error('ошибка: '.Proxy::$lastError);
		}
		elseif($_POST['add'])
		{
			$count = Proxy::addMany($params['proxyContent'], $params['isPersonal'], 0, $params['isYandex'], $params['category']);

			$this->success('добавлено '.$count.' прокси');

			if(Proxy::$lastError)
				$this->error(Proxy::$lastError);
			else
				$this->redirect('proxy/list');

		}
		elseif($_POST['delete'])
		{
			//перезагрузить прокси
			if(Proxy::model()->findByPk($_POST['id'])->delete())
			{
				$this->success('удален прокси '.$_POST['id']);
				$this->redirect('proxy/list');
			}
			else
				$this->error('ошибка: '.Proxy::$lastError);
		}

		$searchCond = '';

		if($category !== false)
			$searchCond  = "`category`='$category'";

		$models = Proxy::model()->findAll(array(
			'condition'=>$searchCond,
			'order'=>"`id` ASC",

		));

		$this->render('list', array(
			'models'=>$models,
			'params'=>$params,
			'replaceEnabled'=>$replaceEnabled,
			'stats'=>Proxy::getStats($models),
		));
	}

	/*
	 * редактирование привязки прокси к клиенту-группе
	 */
	public function actionAccount()
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		if($_POST['save'])
		{
			$done = AccountProxy::editProxyId($_POST['accountProxy']);

			$this->success('изменено '.$done.' записей');

			if(!AccountProxy::$lastError)
				$this->redirect('proxy/account');
			else
				$this->error(AccountProxy::$lastError);

		}

		//привязки только у активных клиентов
		$clientIdArr = array();

		foreach(Client::getActiveClients() as $client)
			$clientIdArr[] = $client->id;

		$models = AccountProxy::model()->findAll(array(
			'condition'=>"`client_id` IN(".implode(',', $clientIdArr).")",
			'order'=>"`proxy_id` ASC",
		));


		$this->render('account', array(
			'models'=>$models,
		));
	}

}
