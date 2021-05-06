<?php
/**
 * работа с апи мерчанта киви
 */
class MerchantApi
{
	public static $lastError;
	public static $lastErrorCode;

	protected $_baseUrl = '';
	protected $_clienId; //данные из апи
	protected $_clientSecret; //данные из апи
	protected $_authToken; //токен авторизации
	public $proxy;


	public function __construct($apiClientId, $apiClientSecret, $proxy=false, $test = false)
	{
		$this->_clienId = $apiClientId;
		$this->_clientSecret = $apiClientSecret;
		$this->_authToken = 'Basic '.base64_encode($this->_clienId.':'.$this->_clientSecret);
		$this->proxy = $proxy;
		if($test)
			$this->_baseUrl = 'http://18.185.170.86';
		else
			$this->_baseUrl = 'https://api.adgroup.finance';

	}

	protected function _sendRequest($method, $postData)
	{
		$sender = new Sender;
		$sender->followLocation = false;
		$sender->additionalHeaders = [
			"Authorization:'.$this->_authToken.'",
			'Content-Type: application/json',
		];
		$content = $sender->send($this->_baseUrl.$method, $postData, $this->proxy);
		$contentArr = json_decode($content, true);

		if($contentArr['result']['status'] == 1)
		{
			return $contentArr['responseData'];
		}
		elseif($contentArr['result']['message'] == 'Transaction Failed')
		{
			self::$lastError = $contentArr;
			toLogError('Merchant API error 1: '.arr2str($contentArr));
			return false;
		}
		else
		{
			self::$lastError = arr2str($contentArr);
			//TODO: убрать вывод в логи, не всегда однозначно правильно ошибку сюда попадают
			toLogError('Merchant API error 2, content: '.$content);
			return false;
		}

	}

	/**
	 * @return array|mixed
	 * получаем список созданных пользователей под мерчанта
	 */
	public function userList()
	{
		$method = '/user/list';
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"listUsers",
				"lang":"EN"
		  }
		}';

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * список кошельков мерчанта
	 */
	public function walletList()
	{
		$method = '/merchant/get-wallet-list';
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"fetchWallets",
				"lang":"EN"
		  }
		}';

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * создание ордера p2p
	 */
	public function registerOrder()
	{
		$method = '/transfer/tx-merchant-wallet';
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"p2pInvoiceRequest",
				"lang":"EN"
		  },
		  "reqData":{
		 		"tel":"79530264153",
				"user_id":"e8fcbf3b-4bfc-4577-882a-7971c40ee52d",
				"amount":"2",
				"destCurrencyCode":"RUB"

		  }
		}';

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * создание пользователя
	 *
	 *
	 * "result": {
	"status": true,
	"message": "Transaction completed successfully"
	},
	"responseData": {
	"login": "vvz70025",
	"user_id": "9257b8b2-6e9e-4923-9bab-947482717d29",
	"message": "User successfully registered",
	"accounts": [
	{
	"address": "mrt5LUES6z8Q9kVcc31KJi9VtyRGZouKcR",
	"balance": "0.00000000",
	"provider": "CRYPTO",
	"currency": "BTC"
	},
	{
	"address": "WALLET1121",
	"balance": "0.00000000",
	"provider": "QIWI",
	 */
	public function registerUser($login = '', $name = '', $email = '')
	{
		$method = '/user/signup';
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"signup",
				"lang":"EN"
		  },
		  "reqData":{
					"login":"'.$login.'",
					"name":"'.$name.'",
					"email":"'.$email.'"
		  }
		}';

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * удаление пользователя
	 *
	 * Success:
	{
	"header":{
	"version":"0.1",
	"txName":"deleteUser",
	"lang":"EN"
	},
	"reqData":{
	"user_id":"197bbc32-fe7b-4642-a264-32139e388378"
	}
	}
	 */
	public function deleteUser($userId)
	{
		$method = '/user/delete';
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"deleteUser",
				"lang":"EN"
		  },
		  "reqData":{
					"user_id":"'.$userId.'"
		  }
		}';

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * назначение кошелька киви пользователю
	 */
	public function assignWallet($userId = '', $walletId = '')
	{
		$method = '/wallet/assign';

		if($walletId)
		{
			$postData = '{
			"header":{
					"version":"0.1",
					"txName":"AssignWallet",
					"lang":"EN"
			  },
			  "reqData":{
					"user_id":"'.$userId.'",
					"wallet_id":"'.$walletId.'"
			  }
			}';
		}
		else
		{
			$postData = '{
			"header":{
					"version":"0.1",
					"txName":"AssignWallet",
					"lang":"EN"
			  },
			  "reqData":{
					"user_id":"'.$userId.'"
			  }
			}';
		}

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * отвязать кошелек и вернуть его в общий список свободных кошей
	 */
	public function deassignWallet($userId, $walletId)
	{
		$method = '/wallet/deassign';
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"DeassignWallet",
				"lang":"EN"
		  },
		  "reqData":{
					"user_id":"'.$userId.'",
					"wallet_id":"'.$walletId.'"
		  }
		}';

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * список транзакций пользователя
	 */
	public function getUserTransactions($userId = '', $start = 0, $limit = 200)
	{
		$method = '/transfer/get-user-tx';
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"fetchUserTx",
				"lang":"EN"
		  },
		  "reqData":{
					"user_id":"'.$userId.'",
					"start":"'.$start.'",
					"limit":"'.$limit.'"
		  }
		}';

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * список транзакций мерчанта
	 */
	public function getMerchantTransactions($start = 0, $limit = 100)
	{
		$method = '/transfer/get-merchant-tx';
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"fetchMerchTx",
				"lang":"EN"
		  },
		  "reqData":{
					"start":"'.$start.'",
					"limit":"'.$limit.'",
					"tx_status":["APPROVED"],
					"protocol_type":["TRANSFER","CARD"],
					"universal":"1"
		  }
		}';

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * Fetch Direct Wallets (Optimal)
	 *
	 * Fetch best qualified wallet for User to perform Incoming Transaction.
	Eg: Suppose if User has two wallets. Let maximum balance limit be 100 RUB.
	If Wallet A has 20 RUB, Wallet B has 80 RUB, Wallet C has 50 RUB.
	It will return array holding Wallet A, Wallet C, Wallet B in this particular order.
	Since Wallet A can accept a higher incoming amount than Wallet C followed by Wallet B.
	 */
	public function directWalletList($userId = '')
	{
		$method = '/merchant/direct-wallets-list';

		if($userId)
		{
			$postData = '{
			"header":{
					"version":"0.1",
					"txName":"DirectWalletList",
					"lang":"EN"
			  },
			  "reqData":{
						"user_id":"'.$userId.'"

			  }
			}';
		}
		else
		{
			$postData = '{
			"header":{
					"version":"0.1",
					"txName":"DirectWalletList",
					"lang":"EN"
			  }
			}';
		}

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @param string $userId
	 *
	 * @return bool
	 *
	 * если не задан $userId, то выдает информацию по всем пользователям
	 */
	public function accountBalance($userId = '')
	{
		$method = '/accounts/fetch-balance';

		if($userId)
		{
			$postData = '{
			"header":{
					"version":"0.1",
					"txName":"accountsBalance",
					"lang":"EN"
			  },
			  "reqData":{
						"user_id":"'.$userId.'"

			  }
			}';
		}
		else
		{
			$postData = '{
			"header":{
					"version":"0.1",
					"txName":"DirectWalletList",
					"lang":"EN"
			  }
			}';
		}

		return $this->_sendRequest($method, $postData);
	}

	/**
	 * @return array|mixed
	 * Fetch Direct Cards (Optimal)
	 */
	public function fetchDirectCards()
	{
		$method = '/merchant/direct-card-list';
		$postData = '{
		"header":{
				"version":"0.1",
				"txName":"DirectCardList",
				"lang":"EN"
		  }
		}';

		return $this->_sendRequest($method, $postData);
	}
}