<?
/**
 * @var ControlController $this
 */

	$user = User::getUser();
	$client = $user->client;
	$currentAccounts = $user->currentAccounts;
?>
	
<?if($this->isAdmin()){?>
	<span><a href="<?=url('client/qiwiNewStat')?>">Qiwi New</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('manager/accountList')?>">Входящие</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/yadStat')?>">Яндекс</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<a href="<?=url('manager/accountUsed')?>">Юзаные</a>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('account/list')?>">Кошельки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('user/list')?>">Юзеры</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('finansist/accountList')?>">Кошельки(Фин)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('finansist/orderList')?>">Переводы (Фин)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('panel/control')?>">Панель</a></span>
	<br>
	<span><a href="<?=url('control/manager')?>">Приход (Контроль)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/finansist')?>">Оплаты (Контроль)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('site/help', array('page'=>'about'))?>">Помощь</a></span>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/list')?>">Клиенты</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/moduleRule')?>">Настройка модулей клиентов</a></span>

	<br>
	<span><a href="<?=url('control/transactionStats')?>">Статистика переводов</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/globalStats')?>">Глобальная статистика</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('finansist/globalOrderList')?>">Переводы(globalFin)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('news/list')?>">Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/CalculateClientList')?>">Расчеты клиентов(globalFin)</a></span>
	<br>
	<span><a href="<?=url('control/tools')?>">Инструменты(globalFin)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/accountInfo')?>">Информация об аккаунте</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/commission')?>">Комиссии клиентов</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/orderConfig')?>">Настройка заявок</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<?/*<span><a href="<?=url('control/latestOrders')?>">Последние заявки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*/?>
	<span><a href="<?=url('user/profile')?>">Профиль</a></span>

	<br><br>
	<span><a href="<?=url('panel/log', array('category'=>'error'))?>">Логи</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('panel/logSorted', array('category'=>'error'))?>">Логи Test(sorted)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/stats')?>">Лимиты клиентов</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('proxy/list')?>">Список прокси</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('proxy/account')?>">Привязка прокси</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('account/autoAdd')?>" title="осталось в запасе">Авто-добавление (<?=Account::autoAddCount()?>)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/storeApi')?>">StoreApi</a></span>

	<br>
	<span><a href="<?=url('control/commentMonitor')?>">Монитор комментов(админ)</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('email/')?>">Email</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('user/groups')?>">Цепочки юзеров</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/antiCaptcha')?>">AntiCaptcha</a></span>
	<br>
	<span><a href="<?=url('control/transactionMonitor')?>">Монитор платежей</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/antiban')?>">Antiban</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/banChecker')?>">BanChecker</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/massCheck')?>">MassCheck</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('yandexAccount/admin/accountList')?>">Яндекс Кошельки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('panel/SearchYandexPayment')?>">Поиск заявки Yandex</a></span>
	<br><br>
	<span><a href="<?=url('merchant/adminMerchant/userList')?>">Пользователи Qiwi2</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('merchant/adminMerchant/walletList')?>">Все кошельки Qiwi2/MerchantYad</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<?/*<span><a href="<?=url('qiwi/main/directWalletList')?>">Свободные кошельки Qiwi2</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('qiwi/main/ModernMerchantView')?>">Qiwi2 Контроль</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*/?>
	<span><a href="<?=url('tools/index')?>">Tools</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<span><a href="<?=url('sim/account/list')?>">Sim-кошельки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<span><a href="<?=url('card/account/list')?>">Card-кошельки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<span><a href="<?=url('intellectMoney/admin/accountList')?>">Intellect Money</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<br><br>

	<span><a href="<?=url('testCard/admin/list')?>">Тест карта</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('p2pService/admin/list')?>">Сделки p2pService</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;


<?}elseif($this->isFinansist()){?>
	<?$client = Client::modelByPk(User::getUser()->client_id);?>
	<?if($client->checkRule('sim')){?>
		<span><a href="<?=url('control/manager')?>">Статистика</a></span>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<span>
			<a href="<?=url('finansist/calculateList')?>">Расчеты</a>
			<?
			if($client and $client->lastCalc)
				echo '('.formatAmount($client->getNewCalcAmount(true), 0).')';
			?>
		</span>
		<?if($client->checkRule('profile')){?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<span><a href="<?=url('user/profile')?>">Профиль</a></span>
		<?}?>
	<?}else{?>
		<b>Финансы:</b><br>
		<span><a href="<?=url('control/manager')?>">Приход</a></span>
		<?if(!$this->getClient()->global_fin){?>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<span><a href="<?=url('control/finansist')?>">Оплаты</a></span>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<span><a href="<?=url('finansist/orderAdd')?>">Перевод средств</a></span>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<span><a href="<?=url('finansist/orderList')?>">Список переводов</a></span>
		<?}?>
		<?if($client->checkRule('qiwi1')){?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<span><a href="<?=url('control/activeWallets')?>">Кошельки в работе</a></span>
		<br>
		<span><a href="<?=url('control/latestOrders')?>">Последние заявки</a></span>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<?}?>
		<?$cfg = cfg('storeApi');
		if($user->client_id != $cfg['clientId']){?>
			<?if($client->checkRule('news')){?>
			<span><a href="<?=url('news/list')?>">Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></a></span>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<?}?>
		<?}?>

		<span>
			<a href="<?=url('finansist/calculateList')?>">Расчеты</a>
			<?
			$client = Client::modelByPk(User::getUser()->client_id);

			if($client and $client->lastCalc)
				echo '('.formatAmount($client->getNewCalcAmount(true), 0).')';
			?>
		</span>
		<?if($client->checkRule('profile')){?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<span><a href="<?=url('user/profile')?>">Профиль</a></span>
		<?}?>
		<br><br>
		<b>Работа с кошельками:</b><br>
		<?$this->renderPartial('//layouts/_incomeMenu', ['currentAccountCount'=>count($currentAccounts)])?>
	<?}?>
<?}elseif($this->isManager()){?>

	<?/*if($user->client_id == $cfg['clientId']){?>
		<span><a href="<?=url('manager/newYandexPay')?>">Карты</a></span>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<span><a href="<?=url('manager/storeApiWithdraw')?>">Выводы</a></span>
	<?}else{*/?>
		<?$this->renderPartial('//layouts/_incomeMenu', ['currentAccountCount'=>count($currentAccounts)])?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

		<?if($client->checkRule('news')){?>
			<span><a href="<?=url('news/list')?>">Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></a></span>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<?}?>

		<?if($client->checkRule('profile')){?>
			<span><a href="<?=url('user/profile')?>">Профиль</a></span>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<?}?>
	<?/*}*/?>

<?}elseif($this->isControl()){?>
	<span><a href="<?=url('control/manager')?>">Приход</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('news/list')?>">Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('user/profile')?>">Профиль</a></span>
<?}elseif($this->isGlobalFin()){?>
	<span><a href="<?=url('manager/accountList')?>">Входящие QIWI</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('account/addAccountByGlobal')?>">Добавление QIWI</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/globalFinLog')?>">Лог действий</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/qiwiNewStat')?>">Qiwi New</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/yadStat')?>">Яндекс</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('panel/SearchYandexPayment')?>">Поиск заявки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('qiwi/main/walletList')?>">Merchant Wallets</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/globalStats')?>">Глобальная статистика</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('finansist/globalOrderList')?>">Переводы</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('finansist/globalOrderAdd')?>">Добавить перевод</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/list')?>">Клиенты</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/moduleRule')?>">Настройка модулей клиентов</a></span>
	<br>
	<span>
		<a href="<?=url('control/CalculateClientList')?>">Расчеты клиентов</a>
		<?if($unpaidCount = ClientCalc::getUnpaidCount()){?>
			<span style="color: #981f20; font-weight: bold" title="неоплаченных расчетов">
				(<?=$unpaidCount?>)
			</span>
		<?}?>
	</span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/tools')?>">Инструменты</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/accountInfo')?>">Информация об аккаунте</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('news/list')?>">Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/commission')?>">Комиссии клиентов</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/storeApi')?>">StoreApi</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('user/profile')?>">Профиль</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/transactionMonitor')?>">Монитор платежей</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/orderConfig')?>">Настройка заявок</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/latestOrders')?>">Последние заявки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('client/stats')?>">Лимиты клиентов</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('control/banChecker')?>">BanChecker</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('account/list')?>">Кошельки QIWI</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('yandexAccount/admin/accountList')?>">Яндекс Кошельки</a></span>
	<br><br>
	<span><a href="<?=url('merchant/adminMerchant/userList')?>">Пользователи Qiwi2</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('merchant/adminMerchant/walletList')?>">Все кошельки Qiwi2/MerchantYad</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<span><a href="<?=url('sim/account/list')?>">Sim-кошельки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('card/account/list')?>">Card-кошельки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<br><br>
	<span><a href="<?=url('testCard/admin/list')?>">Тест карта</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('p2pService/admin/list')?>">Сделки p2pService</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}elseif($this->isControl()){?>
    <span><a href="<?=url('sim/account/list')?>">Sim-кошельки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('card/account/list')?>">Card-кошельки</a></span>
<?}?>


