<?php

class WalletSModule extends CWebModule
{
	public $config = [];

	public function init()
	{
		$this->setImport([
			'walletS.models.*',
			'walletS.views.*',
			'walletS.controllers.*',
			'walletS.components.*',
		]);

	}
}
