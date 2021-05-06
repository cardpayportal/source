<?
/**
 * @var PanelController $this
 */
$this->renderPartial('//layouts/_header');

$user = User::getUser();
?>

<body>
	
	<div id="content" class="center">

	<?$globalMsgArr = cfg('globalMsgArr')?>
	<?$userModel = User::getUser();?>
	<?if($globalMsg = config('globalMsg')){?>
		<h1 style="color: red">
			ВНИМАНИЕ!<br>
			<?=$globalMsg?>
		</h1>
	<?}elseif($user = User::getUser() and (
		$msg = $globalMsgArr[$user->client_id]
		or
		//если логин указан то только одному юзеру
		$msg = $globalMsgArr[$user->login]
		)){?>

		<?//сообщение для клиентов?>
		<h1 style="color: red">
			<?=$msg?>
			<?//ВНИМАНИЕ! Остановите залив средств на кошельки!!! Баны.?>
		</h1>
	<?}?>

	<?if($user = User::getUser() and $user->role == User::ROLE_GLOBAL_FIN){?>

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
			<h1 style="color: red">
				<?=$msg?>
			</h1>
		<?}?>

	<?}?>

	<?if($this->isActive()){?>
			<div>
				Hello, <strong><?=Yii::app()->user->name?></strong> 
				(<a href="<?=url('site/exit')?>">Выход из системы</a>)
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<strong><?=date('d.m.Y H:i:s')?></strong>

				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				(
					время загрузки <?=Tools::timeSpend()?> сек
					<?if($this->isAdmin()){?>
						, <b>нагрузка:</b> <?if(PHP_OS != 'WINNT'){?><?=Tools::getSysLoad()?><?}?>,
						<b>inode:</b> <?if(PHP_OS != 'WINNT'){?><?=Tools::getSysInode()?>%<?}?>
					<?}?>
				)



                <?if(
                    $withdraw = cfg('withdraw') and $withdraw['enabled']
                    and !User::getUser()->parent_id
                ){?>
                <?}?>

				<?if($this->isFinansist() and !	User::getUser()->client->global_fin){?>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<strong>Баланс:</strong>
					<?
						$user = User::getUser();
						echo formatAmount(Client::getSumOutBalance($user->client_id), 0)
					?>
				<?}/*elseif($this->isManager() and $user->client_id == cfg('storeApi')['clientId']){?>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<strong>Баланс:</strong>
					<?
					$store = StoreApi::getModelByUserId($user->id);
					//echo formatAmount($store->getBalance(), 0) . '  руб';
					?>
				<?}*/?>

                <?if(YII_DEBUG){?>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<font color="red">Тестовый режим</font>
				<?}?>

				<?if($this->isGlobalFin()){?>
					<br><br>
					<?$this->renderPartial('//layouts/_globalFinWheel')?>
				<?}?>


			</div>
			
			<div id="menu">
				<?$this->renderPartial('//layouts/_menu')?>
			</div>

			<?if($this->isAdmin() or $this->isFinansist() or $this->isControl()){?>
				<div id="supportOnline">
					<?$this->renderPartial('//layouts/_supportOnline')?>
				</div>
			<?}?>
	<?}?>		
		<br /><br />
		<?$this->renderPartial('//layouts/_msg')?>
		<?=$content?>
	</div>

	<?if($this->isAdmin()){?>
		<script>
			$(document).on('click', 'body', function(event){

			});
		</script>
	<?}?>
</body>
</html>