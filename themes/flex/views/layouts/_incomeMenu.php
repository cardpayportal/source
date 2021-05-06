<?
/**
 * @var int $currentAccountCount
 */

$user = User::getUser();
$client = $user->client;

$incomeMode = $user->client->income_mode;
?>

<?if($client->checkRule('walletS')){?>
	<li>
		<a href="<?=url('walletS/TransactionWalletS/list')?>">
			<span class="menu-icon"><i class="fa fa-th-list"></i></span>
			<span class="menu-text">WalletS</span>
		</a>
	</li>
<?}?>

<?if($client->checkRule('qiwi2')){?>
	<li>
		<a href="<?=url('merchant/qiwi/history')?>">
			<span class="menu-icon"><i class="fa fa-th-list"></i></span>
			<span class="menu-text">Qiwi 2</span>
		</a>
	</li>
<?}?>

<?if($client->checkRule('qiwi1')){?>
	<?if($incomeMode == Client::INCOME_WALLET){?>
		<li>
			<a href="<?=url('manager/accountList')?>">
				<span class="menu-icon"><i class="fa fa-th-list"></i></span>
				<span class="menu-text">Qiwi 1 (кошельков <?=$currentAccountCount?>)</span>
			</a>
		</li>
		<li>
			<a href="<?=url('manager/accountAdd')?>">
				<span class="menu-icon"><i class="fa fa-th-list"></i></span>
				<span class="menu-text">Получить кошельки</span>
			</a>
		</li>
	<?}elseif($incomeMode == Client::INCOME_ORDER){?>
		<li>
			<a href="<?=url('manager/orderList')?>">
				<span class="menu-icon"><i class="fa fa-th-list"></i></span>
				<span class="menu-text">Активные заявки</span>
			</a>
		</li>

		<li>
			<a href="<?=url('manager/orderAdd')?>">
				<span class="menu-icon"><i class="fa fa-th-list"></i></span>
				<span class="menu-text">Добавить заявку</span>
			</a>
		</li>

		<li>
			<a href="<?=url('manager/orderUsed')?>">
				<span class="menu-icon"><i class="fa fa-th-list"></i></span>
				<span class="menu-text">Старые заявки</span>
			</a>
		</li>
	<?}?>
<?}?>
<?if($client->control_yandex_bit == true){?>
	<li>
		<a href="<?=url('manager/exchangeYandexBit')?>">
			<span class="menu-icon"><i class="fa fa-th-list"></i></span>
			<span class="menu-text">Обмен яндекс</span>
		</a>
	</li>
<?}?>
<?if($client->checkRule('yandex')){?>
	<li>
		<a href="<?=url('manager/newYandexPay')?>">
			<span class="menu-icon"><i class="fa fa-th-list"></i></span>
			<span class="menu-text">Карты</span>
		</a>
	</li>
<?}?>

<?if($client->checkRule('adgroupMerchYad')){?>
	<li>
		<a href="<?=url('merchant/yandex/history')?>">
			<span class="menu-icon"><i class="fa fa-th-list"></i></span>
			<span class="menu-text">Мерчант Яндекс</span>
		</a>
	</li>
<?}?>

<?if($client->checkRule('testCard')){?>
	<li>
		<a href="<?=url('testCard/manager/list')?>">
			<span class="menu-icon"><i class="fa fa-th-list"></i></span>
			<span class="menu-text">Тест карты</span>
		</a>
	</li>
<?}?>

<?=Yii::app()->getModule('yandexAccount')->getMenuManager();?>

<?if($client->checkRule('sim')){?>
	<li>
		<a href="<?=url('sim/transaction/list')?>">
			<span class="menu-icon"><i class="fa fa-th-list"></i></span>
			<span class="menu-text">Sim платежи</span>
		</a>
	</li>
<?}?>

<?//todo: перенести в новый алг вот такие исключения тоже?>
<?if($user->client_id == 19){?>
	<li>
		<a href="<?=url('manager/nextQiwiPay')?>">
			<span class="menu-icon"><i class="fa fa-th-list"></i></span>
			<span class="menu-text">Next Qiwi</span>
		</a>
	</li>
<?}?>

<?if($client->checkRule('stats')){?>
	<li>
		<a href="<?=url('manager/stats')?>">
			<span class="menu-icon"><i class="fa fa-th-list"></i></span>
			<span class="menu-text">Статистика</span>
		</a>
	</li>
<?}?>