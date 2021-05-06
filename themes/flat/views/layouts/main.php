<?
/**
 * @var Controller $this
 * @var string $content
 *
 */
?>

<!doctype html>
<html>
	<?=$this->renderPartial('//layouts/_header')?>
	<body>
	<?$this->renderPartial('//layouts/_menu')?>

	<div class="container <?if(!$this->isAdmin() or !cfg('isAdminLeftMenu')){?> nav-hidden<?}?>" id="content">

		<?if($this->isAdmin() and cfg('isAdminLeftMenu')){?>
			<?$this->renderPartial('//layouts/_menuAdminLeft')?>
		<?}?>

		<div id="main">
			<div class="container-fluid">
				<div class="page-header">
					<div class="pull-left">
						<h1><?=$this->title?></h1>
					</div>
					<div class="pull-right">
						<ul class="stats">
							<?/*
							<li class='lightred' title="ВНИМАНИЕ! Проблемы с обновлением платежей на кошельках. Тех работы до 15:30 мск.">
								<i class="fa fa-warning"></i>
								<div class="details">
									<span class="big">Внимание!!!</span>
									<span>Тех работы</span>
								</div>
							</li>
							*/?>

							<?if($user = User::getUser() and ($user->client_id == 6)){?>

								<?//сообщение для клиентов?>
								<?/*?>
								<li class='lightred' title="ВНИМАНИЕ! Остановите залив средств на кошельки!!! Баны.">
									<i class="fa fa-warning"></i>
									<div class="details">
										<span class="big">Внимание!!!</span>
										<span>Остановите залив</span>
									</div>
								</li>
								<?*/?>
							<?}elseif($user = User::getUser() and $user->role == User::ROLE_GLOBAL_FIN){?>
								<?//сообщение для ГФ?>
								<?if($msg = config('gfBanMessage')){?>
									<?
									$interval = 1800;	//через сколько удалить

									if(time() - config('gfBanMessageTimestamp') > $interval)
									{
										config('gfBanMessage', '');
										config('gfBanMessageTimestamp', time());
										$msg = '';
									}
									?>
									<li class='lightred' title="<?=$msg?>">
										<i class="fa fa-warning"></i>
										<div class="details">
											<span class="big">Внимание!!!</span>
											<span><?=Tools::shortText($msg, 20)?></span>
										</div>
									</li>
								<?}?>
							<?}?>

							<?$globalMsgArr = cfg('globalMsgArr')?>

							<?if($globalMsg = config('globalMsg')){?>
								<li class='lightred'>
									<i class="fa fa-bell"></i>
									<div class="details">
										<span class="big">Внимание</span>
										<span title="<?=$globalMsg?>"><?=Tools::shortText($globalMsg, 20)?></span>
									</div>
								</li>
							<?}elseif($user = User::getUser() and (
								$msg = $globalMsgArr[$user->client_id]
								or
								//если логин указан то только одному юзеру
								$msg = $globalMsgArr[$user->login]
								)){?>
								<li class='lightred'>
									<i class="fa fa-bell"></i>
									<div class="details">
										<span class="big">Внимание</span>
										<span title="<?=$msg?>"><?=Tools::shortText($msg, 20)?></span>
									</div>
								</li>
							<?}?>

							<?$unpaidCount = 0?>
							<?if($this->isGlobalFin() and $unpaidCount = ClientCalc::getUnpaidCount()){?>
								<li class='lightred' title="неоплаченные расчеты">
									<i class="fa fa-warning"></i>
									<div class="details">
										<span class="big">новых расчетов: <?=$unpaidCount?></span>
										<span>
											<a href="<?=url('control/CalculateClientList')?>">
												<button class="btn  btn-small">перейти</button>
											</a>
										</span>
									</div>
								</li>
							<?}?>

							<?if(YII_DEBUG){?>
								<li class='lightred'>
									<i class="fa fa-bell"></i>
									<div class="details">
										<span class="big">Debug Mode</span>
										<span>отладка</span>
									</div>
								</li>

								<li class='lightgrey'>
									<i class="fa fa-cogs"></i>
									<div class="details">
										<span class="big" title="время загрзуки страницы и текущая нагрузка на систему"><?=Tools::timeSpend()?> сек (<?if(PHP_OS != 'WINNT'){?><?=Tools::getSysLoad()?><?}?>)</span>
										<span title="использование файловой системы">inode: <?if(PHP_OS != 'WINNT'){?><?=Tools::getSysInode()?>%<?}?></span>
									</div>
								</li>
							<?}?>

							<?if($this->isGlobalFin()){?>
								<?$this->renderPartial('//layouts/_globalFinWheel')?>
							<?}?>

							<?//todo: баланс для глобалФинов(взять из "В процессе" глобалстаты)?>
							<?if($this->isFinansist() and !User::getUser()->client->global_fin){?>
								<?
								//баланс OUT-кошельков для глобал фина
								$user = User::getUser();
								$balance = formatAmount(Client::getSumOutBalance($user->client_id), 0)
								?>

								<li class='satgreen'>
									<i class="fa fa-money"></i>
									<div class="details">
										<span class="big"><?=$balance?></span>
										<span>Руб</span>
									</div>
								</li>
							<?}?>

							<?if($this->isAdmin() or $this->isFinansist() or $this->isControl()){?>
								<?$this->renderPartial('//layouts/_supportOnline')?>
							<?}?>

							<li class='lightgrey'>
								<i class="fa fa-calendar"></i>
								<div class="details">
									<span class="big"><?=date('F')?> <?=date('d')?>, <?=date('Y')?></span>
									<span><?=date('l')?>, <?=date('H:i')?></span>
								</div>
							</li>
						</ul>
					</div>
				</div>
				<?//сообщения об ошибках?>
				<?$this->renderPartial('//layouts/_msg')?>
				<div class="row">
					<div class="col-sm-12">
						<?=$content?>
					</div>
				</div>
			</div>
		</div>
	</div>
	</body>
</html>