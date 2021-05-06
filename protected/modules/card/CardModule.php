<?php

class CardModule extends CWebModule
{
	//public $defaultController = 'TransactionController';
	public $config = [];

	public function init()
	{
		$this->setImport([
			'card.models.*',
			'card.views.*',
			'card.controllers.*',
			'card.components.*',
		]);

	}
}
