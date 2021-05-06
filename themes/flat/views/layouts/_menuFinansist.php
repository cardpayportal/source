<?
$user = User::getUser();
//расчеты
$client = Client::modelByPk($user->client_id);

if($client and $client->lastCalc)
{
	$calcAmount = $client->getNewCalcAmount(true);
}
?>
<div id="navigation">
	<div class="container">
		<ul class='main-nav'>
			<?if($client->checkRule('sim')){?>
				<li>
					<a href="<?=url('control/manager')?>">
						<span>Статистика</span>
					</a>
				</li>
				<li><a href="<?=url('finansist/calculateList')?>"><span>Расчет<?=(isset($calcAmount) ? ' ('.formatAmount($calcAmount, 0).' руб)' : '')?></span></a></li>
				<?if($client->checkRule('qiwi1')){?>
					<li><a href="<?=url('control/latestOrders')?>">Последние заявки</a></li>
				<?}?>
			<?}else{?>
				<li>
					<a href="<?=url('control/manager')?>">
						<span>Приход</span>
					</a>
				</li>

				<?if(!$user->client->global_fin){?>
					<li>
						<a href="<?=url('/')?>" data-toggle="dropdown" class='dropdown-toggle'>
							<span>Расход</span>
							<span class="caret"></span>
						</a>

						<ul class="dropdown-menu">
							<li><a href="<?=url('finansist/orderAdd')?>">Добавить перевод</a></li>
							<li><a href="<?=url('finansist/orderList')?>">Список переводов</a></li>
							<li><a href="<?=url('control/finansist')?>">Статистика</a></li>
						</ul>
					</li>
				<?}?>
				<li>
					<?if($client->checkRule('qiwi1')){?>
						<a href="<?=url('control/activeWallets')?>">
							<span>Кошельки в работе</span>
						</a>
					<?}?>
				</li>

				<?$this->renderPartial('//layouts/_incomeMenu')?>

				<li><a href="<?=url('finansist/calculateList')?>"><span>Расчет<?=(isset($calcAmount) ? ' ('.formatAmount($calcAmount, 0).' руб)' : '')?></span></a></li>

				<?$cfg = cfg('storeApi');
				if($user->client_id != $cfg['clientId']){?>
					<?if($client->checkRule('news')){?>
						<li><a href="<?=url('news/list')?>"><span>Новости<?if($newsCount = News::newsCount($user->id)){?> (<?=$newsCount?>)<?}?></span></a></li>
					<?}?>
				<?}?>
			<?}?>
		</ul>
		<div class="user">
			<div class="dropdown">
				<a href="#" class='dropdown-toggle' data-toggle="dropdown"><?=mb_ucfirst(Yii::app()->user->name, 'utf-8')?>
					<img src="<?=Yii::app()->theme->baseUrl?>/img/icon_user.png" alt="">
				</a>
				<ul class="dropdown-menu pull-right">
					<?if($client->checkRule('profile')){?>
						<li><a href="<?=url('user/profile')?>">Профиль</a></li>
					<?}?>
					<li><a href="<?=url('site/exit')?>">Выход</a></li>
				</ul>
			</div>
		</div>
	</div>
</div>