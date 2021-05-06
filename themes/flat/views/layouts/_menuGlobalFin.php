<?$user = User::getUser()?>

<div id="navigation">
	<div class="container">
		<ul class='main-nav'>
			<li>
				<a href="<?=url('/')?>" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Переводы</span>
					<span class="caret"></span>
				</a>

				<ul class="dropdown-menu">
					<li><a href="<?=url('finansist/globalOrderAdd')?>">Добавить перевод</a></li>
					<li><a href="<?=url('finansist/globalOrderList')?>">Список переводов</a></li>
				</ul>
			</li>

			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Клиенты</span>
					<span class="caret"></span>
				</a>

				<ul class="dropdown-menu">
					<li><a href="<?=url('client/list')?>">Список клиентов</a></li>
					<li><a href="<?=url('control/globalStats')?>">Глобальная статистика</a></li>
					<li><a href="<?=url('control/CalculateClientList')?>">Расчеты</a></li>
					<li><a href="<?=url('client/commission')?>">Комиссии</a></li>
				</ul>
			</li>

			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Заявки</span>
					<span class="caret"></span>
				</a>

				<ul class="dropdown-menu">
					<li><a href="<?=url('control/latestOrders')?>">Последние заявки</a></li>
					<li><a href="<?=url('control/orderConfig')?>">Настройка заявок</a></li>
				</ul>
			</li>

			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Прочее</span>
					<span class="caret"></span>
				</a>

				<ul class="dropdown-menu">
					<li><a href="<?=url('control/accountInfo')?>">Информация о кошельке</a></li>
					<li><a href="<?=url('control/transactionMonitor')?>">Монитор платежей</a></li>
					<li><a href="<?=url('control/storeApi')?>" title="магазины кл16">Store Api</a></li>
					<li><a href="<?=url('control/tools')?>">Инструменты</a></li>
					<li><a href="<?=url('client/stats')?>">Лимиты клиентов</a></li>
					<li><a href="<?=url('control/banChecker')?>">Бан чекер</a></li>
					<li><a href="<?=url('account/list')?>">Кошельки QIWI</a></li>
					<li><a href="<?=url('yandexAccount/admin/accountList')?>">Яндекс Кошельки</a></li>
					<li><a href="<?=url('sim/account/list')?>">Sim-кошельки</a></li>
					<li><a href="<?=url('card/account/list')?>">Card-кошельки</a></li>
				</ul>
			</li>


			<li>
				<a href="<?=url('/')?>">
					<span>Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></span>
				</a>
			</li>
		</ul>
		<div class="user">
			<div class="dropdown">
				<a href="#" class='dropdown-toggle' data-toggle="dropdown"><?=mb_ucfirst(Yii::app()->user->name, 'utf-8')?>
					<img src="<?=Yii::app()->theme->baseUrl?>/img/icon_user.png" alt="">
				</a>
				<ul class="dropdown-menu pull-right">
					<li><a href="<?=url('user/profile')?>">Профиль</a></li>
					<li><a href="<?=url('site/exit')?>">Выход</a></li>
				</ul>
			</div>
		</div>
	</div>
</div>