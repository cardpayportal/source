<?php

class SimModule extends CWebModule
{
	//public $defaultController = 'TransactionController';
	public $config = [];

	public function init()
	{
		$this->setImport([
			'sim.models.*',
			'sim.views.*',
			'sim.controllers.*',
			'sim.components.*',
		]);

	}
}
