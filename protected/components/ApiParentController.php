<?php

/**
 * родительский для всех апи классов
 * ключ $_POST['key']
 */
class ApiParentController extends CController
{
	const ERROR_TEST_MODE = 'debugMode';
	const ERROR_ACCESS = 'accessDenied';

	protected $errorCode = '';

	public function beforeAction($action)
	{
		session_write_close();

		$apiKey = $_REQUEST['key'];

		if(YII_DEBUG and !Tools::isAdminIp())
		{
			$this->errorCode = self::ERROR_TEST_MODE;
			$this->resultOut();
		}

		//TODO: убрать временную заплатку, поставил чтобы не проверялся ключ
		if($action->id == 'ConfirmNewYandexPayment')
			return true;

		if($this->checkAccess($apiKey))
			return true;
		else
			$this->resultOut();
	}


	protected function getErrorMsg()
	{
		$arr = array(
			self::ERROR_TEST_MODE => 'тех работы',
			self::ERROR_ACCESS => 'доступ запрещен',
		);

		if($this->errorCode)
			return $arr[$this->errorCode];
		else
			return '';
	}


	protected function checkAccess($pass)
	{
		if($pass === cfg('apiKey'))
			return true;
		else
		{
			$this->errorCode = self::ERROR_ACCESS;

			return false;
		}

	}

	protected function resultOut($result = array())
	{
		$result = array(
			'result'=>$result,
			'errorCode'=>$this->errorCode,
			'errorMsg'=>$this->getErrorMsg(),
		);

		$this->renderPartial('//system/json', array(
			'result'=>$result,
		));
		Yii::app()->end();
	}
}