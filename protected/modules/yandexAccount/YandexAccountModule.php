<?php

class YandexAccountModule extends CWebModule
{
	public $defaultController = 'ManagerController';

	public function init()
	{
		// this method is called when the module is being created
		// you may place code here to customize the module or the application

		// import the module-level models and components
		$this->setImport(array(
			'yandexAccount.models.*',
			'yandexAccount.components.*',
			'yandexAccount.views.*',
			'yandexAccount.controllers.*',
		));
	}

	public function beforeControllerAction($controller, $action)
	{
		if(parent::beforeControllerAction($controller, $action))
		{
			// this method is called before any module controller action is performed
			// you may place customized code here
			return true;
		}
		else
			return false;
	}

	/**
	 * не отображать пункты меню если клиентов нет в списке конфига
	 * @return string
	 */
	public function getMenuManager()
	{
		$cfg = cfg('yandexAccount');
		$user = User::getUser();

		if(!$user->client or in_array($user->client_id, $cfg['clients'])===false)
			return '';

		$themeName = Yii::app()->theme->name;

		if($themeName == 'flat')
		{
			return '<li><a href="'.url('yandexAccount/manager').'"><span>Яндекс кошельки</span></a></li>';
		}
		elseif($themeName == 'basic')
		{
			return '<span><a href="'.url('yandexAccount/manager').'">Яндекс кошельки</a></span>'
				.'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		}
		else
			return '';

	}

}
