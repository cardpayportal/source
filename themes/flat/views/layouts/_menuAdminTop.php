<?$user = User::getUser()?>

<div id="navigation">
	<div class="container">
		<ul class='main-nav'>
			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Кошельки</span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li><a href="<?=url('manager/accountList')?>">Входящие</a></li>
					<li><a href="<?=url('account/list')?>">Все</a></li>
					<li><a href="<?=url('/')?>">Статистика по кошелькам(new)</a></li>
					<li class='dropdown-submenu'>
						<a href="<?=url('proxy/list')?>" data-toggle="dropdown" class='dropdown-toggle'>Прокси</a>
						<ul class="dropdown-menu">
							<li><a href="<?=url('proxy/list')?>">Список прокси</a></li>
							<li><a href="<?=url('proxy/account')?>">Привязка прокси</a></li>
						</ul>
					</li>
					<li><a href="<?=url('account/autoAdd')?>">Автодобавление  (<?=Account::autoAddCount()?>)</a></li>
					<li><a href="<?=url('control/antiban')?>">Антибан</a></li>
					<li><a href="<?=url('control/banChecker')?>">Бан чекер</a></li>
					<li><a href="<?=url('control/accountInfo')?>">Информация о кошельке</a></li>
					<li><a href="<?=url('email')?>">Мыла</a></li>
				</ul>
			</li>

			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Логи</span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li><a href="<?=url('panel/log', ['category'=>'error'])?>">Error</a></li>
					<li><a href="<?=url('panel/log', ['category'=>'runtime'])?>">Runtime</a></li>
					<li><a href="<?=url('panel/log', ['category'=>'log'])?>">Log</a></li>
					<li><a href="<?=url('panel/log', ['category'=>'storeApi'])?>">Store Api</a></li>
					<li><a href="<?=url('panel/log', ['category'=>'ecommApi'])?>">Ecomm Api</a></li>
					<li><a href="<?=url('panel/log', ['category'=>'antiCaptcha'])?>">AntiCaptcha</a></li>
				</ul>
			</li>


			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Логи(sorted)</span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li><a href="<?=url('panel/logSorted', ['category'=>'error'])?>">Error</a></li>
					<li><a href="<?=url('panel/logSorted', ['category'=>'runtime'])?>">Runtime</a></li>
					<li><a href="<?=url('panel/logSorted', ['category'=>'log'])?>">Log</a></li>
					<li><a href="<?=url('panel/logSorted', ['category'=>'storeApi'])?>">Store Api</a></li>
					<li><a href="<?=url('panel/logSorted', ['category'=>'ecommApi'])?>">Ecomm Api</a></li>
					<li><a href="<?=url('panel/logSorted', ['category'=>'antiCaptcha'])?>">AntiCaptcha</a></li>
				</ul>
			</li>

			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Клиенты</span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li><a href="<?=url('client/list')?>">Список клиентов</a></li>
					<li><a href="<?=url('client/stats')?>">Лимиты клиентов</a></li>
					<li><a href="<?=url('client/commission')?>">Комиссии</a></li>
					<li class='dropdown-submenu'>
						<a href="<?=url('control/orderConfig')?>" data-toggle="dropdown" class='dropdown-toggle'>Заявки</a>
						<ul class="dropdown-menu">
							<li><a href="<?=url('control/orderConfig')?>">Настройка</a></li>
							<li><a href="<?=url('control/latestOrders')?>">Последние заявки</a></li>
						</ul>
					</li>
				</ul>
			</li>

			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Юзеры</span>
					<span class="caret"></span>
				</a>

				<ul class="dropdown-menu">
					<li><a href="<?=url('user/list')?>">Список</a></li>
					<li><a href="<?=url('user/groups')?>">Цепочки юзеров</a></li>
				</ul>
			</li>

			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Переводы</span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li><a href="<?=url('control/globalStats')?>">Глобальная статистика</a></li>
					<li><a href="<?=url('finansist/globalOrderList')?>">Переводы глобал фин</a></li>
					<li><a href="<?=url('control/calculateClientList')?>">Расчеты клиентов</a></li>
					<li><a href="<?=url('control/massCheck')?>">Массовая проверка</a></li>
					<li><a href="<?=url('control/commentMonitor')?>">Монитор комментов</a></li>
					<li><a href="<?=url('control/transactionMonitor')?>">Монитор платежей</a></li>
				</ul>
			</li>

			<li>
				<a href="#" data-toggle="dropdown" class='dropdown-toggle'>
					<span>Прочее</span>
					<span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li><a href="<?=url('control/storeApi')?>">Store Api</a></li>
					<li><a href="<?=url('/')?>">Ecomm Api</a></li>
					<li><a href="<?=url('control/antiCaptcha')?>">AntiCaptcha</a></li>
					<li><a href="<?=url('client/yadStat')?>">Yandex</a></li>
					<li><a href="<?=url('panel/SearchYandexPayment')?>">Поиск заявки Yandex</a></li>
					<li><a href="<?=url('tools/index')?>">Tools</a></li>
					<li><a href="<?=url('sim/account/list')?>">Sim-кошельки</a></li>
				</ul>
			</li>

			<li>
				<a href="<?=url('news/list')?>">
					<span>Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></span>
				</a>
			</li>
		</ul>
		<div class="user">
			<?//if($this->isAdmin())?>
			<ul class="icon-nav">

			</ul>
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