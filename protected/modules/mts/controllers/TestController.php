<?php

class TestController extends Controller
{
	public function actionMts()
	{
		$phone = '';
		$mts = new Mts($phone);

		print_r($mts->getPhone());
	}
}