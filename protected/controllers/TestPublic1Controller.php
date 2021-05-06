<?php
class TestPublic1Controller extends Controller
{
	public function filters()
	{
		//чтобы работало без авторизации
		return [];
	}

	public function actionCardPayment()
	{
		$paymentType = CardTransaction::PAYMENT_TYPE_CARD2CARD;

		$apiKey = 'DXPRYWYKFJTBKCGG';
		$user = User::getModel(['api_key'=>$apiKey]);

		$params = ($_POST['params']) ? $_POST['params'] : $_SESSION['params'];
		$result = [];

		if($_POST['submit'])
		{
			$_SESSION['params'] = $params;

			$orderId = CardTransaction::generateOrderId(6);

			if(!$params['browser'])
				$params['browser'] = $_SERVER['HTTP_USER_AGENT'];

			$params['headers'] = [
				'Accept: '.$_SERVER['HTTP_ACCEPT'],
				'Accept-Language: '.$_SERVER['HTTP_ACCEPT_LANGUAGE'],
			];

			if($url = CardTransaction::getPayUrl($user->id, $params['amount'],
				$orderId, '', '', $paymentType, $params['phone']))
			{
				if(CardTransaction::PAYMENT_METHOD == 'form')
				{
					$result['url'] = $url;
					$result['method'] = 'get';
				}
				elseif(CardTransaction::PAYMENT_METHOD == 'bank')
				{
					$model = CardTransaction::getModel(['client_order_id'=>CardTransaction::$someData['orderId'],
						'user_id'=>$user->id]);

					$params['orderId'] = $model->order_id;
					$params['checkUrl'] = 'https://moneytransfer.life/test.php?r=testPublic1/checkOrder&orderId='.$params['orderId'];
					$params['referer'] = $_SERVER['HTTP_HOST'];

					if($arr = CardTransaction::getBankUrl($params))
					{
						$result = $arr;
						$result['method'] = 'post';
					}
					else
					{
						$model->delete();
						$this->error(CardTransaction::$lastError);
					}
				}
			}
			else
				$this->error(CardTransaction::$lastError);
		}
		else
		{
			if(!$params['browser'])
				$params['browser'] = $_SERVER['HTTP_USER_AGENT'];
		}

		$this->render('cardPayment', [
			'params' => $params,
			'redirParams' => $result,
		]);
	}

	public function actionCheckOrder($orderId = '')
	{
		$params = $_POST;

		if(!$params)
			die('error1');

		if(!$model = CardTransaction::getModel(['order_id'=>$orderId]))
			die('orderId not found');

		if($_SESSION['params']['proxy'])
			$params['proxy'] = $_SESSION['params']['proxy'];

		if($_SESSION['params']['browser'])
			$params['browser'] = $_SESSION['params']['browser'];

		$result = $model->checkOrder($params);

		if($result['status'] == CardTransaction::STATUS_SUCCESS)
			echo 'оплачено';
		elseif($result['status'] == CardTransaction::STATUS_ERROR)
			echo 'ошибка: '.$result['msg'];
		elseif($result['status'] == CardTransaction::STATUS_WAIT)
			echo 'заявка в ожидании';
		else
		{
			prrd($result);
		}

		echo '<br> <a  href="'.url('testPublic1/cardPayment').'">назад</a>';
	}

}