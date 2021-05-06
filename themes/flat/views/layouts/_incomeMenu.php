<?
/**
 * @var ManagerController $this
 */

$user = User::getUser();
$client = $user->client;

$incomeMode = $user->client->income_mode;
$currentAccounts = $user->currentAccounts;
?>

<?if($this->isManager()){?>

	<?if($client->checkRule('p2pService')){?>
		<li><a href="<?=url('p2pService/manager/list')?>">p2pService</a></li>
	<?}?>

	<?if($client->checkRule('walletS')){?>
		<li><a href="<?=url('walletS/TransactionWalletS/list')?>">WalletS</a></li>
	<?}?>

	<?if($client->checkRule('qiwi2')){?>
		<li><a href="<?=url('merchant/qiwi/history')?>"><span>Qiwi 2</span></a></li>
	<?}?>


	<?if($client->checkRule('adgroupMerchYad')){?>
		<li><a href="<?=url('merchant/yandex/history')?>">Мерчант Яндекс</a></li>
	<?}?>


	<?if($client->checkRule('qiwi1')){?>
		<?if($incomeMode == Client::INCOME_WALLET){?>
			<li><a href="<?=url('manager/accountList')?>"><span>Кошельки (<?=count($currentAccounts)?>)</span></a></li>
			<li><a href="<?=url('manager/accountAdd')?>"><span>Получить кошельки</span></a></li>
		<?}elseif($incomeMode == Client::INCOME_ORDER){?>
			<li><a href="<?=url('manager/orderList')?>"><span>Активные заявки</span></a></li>
			<li><a href="<?=url('manager/orderAdd')?>"><span>Добавить заявку</span></a></li>
			<li><a href="<?=url('manager/orderUsed')?>"><span>Старые заявки</span></a></li>
		<?}?>
	<?}?>

	<?if($client->control_yandex_bit == true){?>
		<li><a href="<?=url('manager/exchangeYandexBit')?>"><span>Обмен яндекс</span></a></li>
	<?}?>

	<?if($client->checkRule('yandex')){?>
		<li><a href="<?=url('manager/newYandexPay')?>"><span>Карты</span></a></li>
	<?}?>

	<?=Yii::app()->getModule('yandexAccount')->getMenuManager();?>

	<?if($client->checkRule('sim')){?>
		<li><a href="<?=url('sim/account/list')?>"><span>Sim кошельки</span></a></li>
	<?}?>

<?}elseif($this->isFinansist()){?>
	<?if($client->checkRule('p2pService')){?>
	<li>

		<a href="<?=url('/')?>" data-toggle="dropdown" class='dropdown-toggle'>
			<span>p2pService</span>
			<span class="caret"></span>
		</a>
		<ul class="dropdown-menu">
			<li><a href="<?=url('p2pService/manager/list')?>">Личные заявки Fin</a></li>
			<li><a href="<?=url('p2pService/fin/statistic')?>">Статистика по всем</a></li>
		</ul>
	</li>
	<?}?>


	<?/*
	<li>
		<a href="<?=url('/')?>" data-toggle="dropdown" class='dropdown-toggle'>
			<span>Прием платежей</span>
			<span class="caret"></span>
		</a>

		<ul class="dropdown-menu">

	<?if($incomeMode == Client::INCOME_WALLET){?>
				<li><a href="<?=url('manager/accountList')?>"><span>Кошельки (<?=count($currentAccounts)?>)</span></a></li>
				<li><a href="<?=url('manager/accountAdd')?>"><span>Получить кошельки</span></a></li>
			<?}elseif($incomeMode == Client::INCOME_ORDER){?>
				<li><a href="<?=url('manager/orderList')?>"><span>Активные заявки</span></a></li>
				<li><a href="<?=url('manager/orderAdd')?>"><span>Добавить заявку</span></a></li>
				<li><a href="<?=url('manager/orderUsed')?>"><span>Старые заявки</span></a></li>
			<?}?>
			<li><a href="<?=url('manager/wex')?>"><span>WEX-коды</span></a></li>
			<li><a href="<?=url('manager/newYandexPay')?>"><span>Карты</span></a></li>
		</ul>
	</li>
	*/?>
<?}?>

<?if($client->checkRule('stats')){?>
	<li><a href="<?=url('manager/stats')?>"><span>Статистика</span></a></li>
<?}?>
