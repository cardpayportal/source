<?
/**
 * @var int $currentAccountCount
 */

$user = User::getUser();
$client = $user->client;

$incomeMode = $user->client->income_mode;
?>

<?if($client->checkRule('walletS')){?>
	<span><a href="<?=url('walletS/TransactionWalletS/list')?>">WalletS</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>

<?if($client->checkRule('qiwi2')){?>
	<span><a href="<?=url('merchant/qiwi/history')?>">Qiwi 2</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>

<?if($client->checkRule('qiwi1')){?>
	<?if($incomeMode == Client::INCOME_WALLET){?>
		<span><a href="<?=url('manager/accountList')?>">Qiwi 1 (кошельков <?=$currentAccountCount?>)</a></span>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<span><a href="<?=url('manager/accountAdd')?>">Получить кошельки</a></span>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<?}elseif($incomeMode == Client::INCOME_ORDER){?>
		<span><a href="<?=url('manager/orderList')?>">Активные заявки</a></span>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<span><a href="<?=url('manager/orderAdd')?>">Добавить заявку</a></span>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<a href="<?=url('manager/orderUsed')?>">Старые заявки</a>
	<?}?>
<?}?>
<?if($client->control_yandex_bit == true){?>
	<span><a href="<?=url('manager/exchangeYandexBit')?>">Обмен яндекс</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>
<?if($client->checkRule('yandex')){?>
	<span><a href="<?=url('manager/newYandexPay')?>">Карты</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>

<?if($client->checkRule('adgroupMerchYad')){?>
	<span><a href="<?=url('merchant/yandex/history')?>">Мерчант Яндекс</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>

<?if($client->checkRule('testCard')){?>
	<span><a href="<?=url('testCard/manager/list')?>">Тест карты</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>

<?=Yii::app()->getModule('yandexAccount')->getMenuManager();?>

<?if($client->checkRule('p2pService')){?>
	<span><a href="<?=url('p2pService/manager/list')?>">p2pService платежи</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>

<?if($client->checkRule('sim')){?>
	<span><a href="<?=url('sim/transaction/list')?>">Sim платежи</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>

<?//todo: перенести в новый алг вот такие исключения тоже?>
<?if($user->client_id == 19){?>
	<span><a href="<?=url('manager/nextQiwiPay')?>">Next Qiwi</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}?>

<?if($client->checkRule('stats')){?>
	<a href="<?=url('manager/stats')?>">Статистика</a>
<?}?>