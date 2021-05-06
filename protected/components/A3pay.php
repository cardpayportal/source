<?php

Class A3pay
{
	private $key;
	private $sender;

	public function __construct($key)
	{
		$this->key = $key;
		$this->sender = new Sender;
		$this->sender->followLocation = false;
		$this->sender->useCookie = false;


	}

	/**
	 * @param string $method
	 * @param array $data
	 */
	protected function request($method, array $data)
	{

	}

}