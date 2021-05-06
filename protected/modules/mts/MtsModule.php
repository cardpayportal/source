<?php

class MtsModule extends CWebModule
{
	//public $defaultController = 'TransactionController';
	public $config = [];

	public function init()
	{
		$this->setImport([
			'mts.models.*',
			'mts.views.*',
			'mts.controllers.*',
			'mts.components.*',
		]);

	}
}
