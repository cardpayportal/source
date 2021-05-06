<?php
/**
 */

class PayController extends CController
{
	public $defaultAction = 'test';
	public $layout='main';

	public function render($tpl, $params = null, $return = false)
	{
		//prrd($basePath=Yii::app()->getViewPath());
		//Yii::app()->theme = null;
		$result = parent::render($tpl, $params);
		Yii::app()->end();
		return $result;
	}

	public function actionTest()
	{
		return $this->render('form');
	}
}