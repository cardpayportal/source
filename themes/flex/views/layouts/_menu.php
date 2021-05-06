<?
/**
 * @var ControlController $this
 */

$user = User::getUser();
$client = $user->client;
$currentAccounts = $user->currentAccounts;
?>

<ul>

<?if($this->isAdmin()){?>

<?}elseif($this->isFinansist()){?>
	<?$client = Client::modelByPk(User::getUser()->client_id);?>
	<?if($client->checkRule('sim')){?>
		<li>
			<a href="<?=url('control/manager')?>">
				<span class="menu-icon"><i class="fa fa-th-list"></i></span>
				<span class="menu-text">Статистика</span>
			</a>
		</li>
		<li>
			<a href="<?=url('finansist/calculateList')?>">
				<span class="menu-icon"><i class="fa fa-th-list"></i></span>
				<span class="menu-text">Расчеты</span>
				<span class="menu-badge">
					<span class="badge vd_bg-red">
						<?
						if($client and $client->lastCalc)
							echo '('.formatAmount($client->getNewCalcAmount(true), 0).')';
						?>
					</span>
				</span>
			</a>
		</li>
		<?if($client->checkRule('profile')){?>
			<li>
				<a href="<?=url('user/profile')?>">
					<span class="menu-icon"><i class="fa fa-th-list"></i></span>
					<span class="menu-text">Профиль</span>
				</a>
			</li>
		<?}?>
	<?}else{?>
		<li>
			<a href="javascript:void(0);" data-action="click-trigger">
				<span class="menu-icon"><i class="icon-palette"> </i></span>
				<span class="menu-text">Финансы</span>
				<span class="menu-badge"><span class="badge vd_bg-black-30"><i class="fa fa-angle-down"></i></span></span>
			</a>
			<div class="child-menu"  data-action="click-target">
				<ul>
					<li>
						<a href="<?=url('control/manager')?>">
							<span class="menu-text">Приход</span>
						</a>
					</li>

					<?if(!$this->getClient()->global_fin){?>

						<li>
							<a href="<?=url('control/finansist')?>">
								<span class="menu-text">Оплаты</span>
							</a>
						</li>
						<li>
							<a href="<?=url('finansist/orderAdd')?>">
								<span class="menu-text">Перевод средств</span>
							</a>
						</li>
						<li>
							<a href="<?=url('finansist/orderList')?>">
								<span class="menu-text">Список переводов</span>
							</a>
						</li>
					<?}?>
					<?if($client->checkRule('qiwi1')){?>

						<li>
							<a href="<?=url('control/activeWallets')?>">
								<span class="menu-text">Кошельки в работе</span>
							</a>
						</li>
						<li>
							<a href="<?=url('control/latestOrders')?>">
								<span class="menu-text">Последние заявки</span>
							</a>
						</li>
					<?}?>

					<?$cfg = cfg('storeApi');
					if($user->client_id != $cfg['clientId']){?>
						<?if($client->checkRule('news')){?>
							<li>
								<a href="<?=url('news/list')?>">
									<span class="menu-text">
										Новости
										<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></a>
									</span>
								</a>
							</li>
						<?}?>
					<?}?>

					<li>
						<a href="<?=url('finansist/calculateList')?>">
							<span class="menu-text">
								Расчеты
								<?
								$client = Client::modelByPk(User::getUser()->client_id);

								if($client and $client->lastCalc)
									echo '('.formatAmount($client->getNewCalcAmount(true), 0).')';
								?>
							</span>
						</a>
					</li>


					<?if($client->checkRule('profile')){?>
						<li>
							<a href="<?=url('user/profile')?>">
								<span class="menu-text">Профиль</span>
							</a>
						</li>
					<?}?>

					<?/*
					<li>
						<a href="<?=url('Работа с кошельками')?>">
							<span class="menu-text">Работа с кошельками</span>
							<?$this->renderPartial('//layouts/_incomeMenu', ['currentAccountCount'=>count($currentAccounts)])?>
						</a>
					</li>
					*/?>
				</ul>
			</div>
		</li>
	<?}?>
<?}elseif($this->isManager()){?>

	<?$this->renderPartial('//layouts/_incomeMenu', ['currentAccountCount'=>count($currentAccounts)])?>

	<?if($client->checkRule('news')){?>
		<li>
			<a href="<?=url('news/list')?>">
				<span class="menu-icon"><i class="fa fa-th-list"></i></span>
				<span class="menu-text">Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></span>
			</a>
		</li>
	<?}?>

	<?if($client->checkRule('profile')){?>
		<li>
			<a href="<?=url('user/profile')?>">
				<span class="menu-icon"><i class="fa fa-th-list"></i></span>
				<span class="menu-text">Профиль</span>
			</a>
		</li>
	<?}?>

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
	<span><a href="<?=url('testCard/admin/list')?>">Тест карта</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<?}elseif($this->isControl()){?>
	<span><a href="<?=url('sim/account/list')?>">Sim-кошельки</a></span>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<span><a href="<?=url('card/account/list')?>">Card-кошельки</a></span>
<?}?>
</ul>

