<?php

class ThreadController extends Controller
{
	public function actionVoucher($login)
	{
		$login = '+'.trim($login, '+');

		$result = '';

		if($model = Account::modelByAttribute(['login'=>$login]))
		{
			if($model->createVouchers())
				$result = 'OK';
		}

		echo $result;
	}
}