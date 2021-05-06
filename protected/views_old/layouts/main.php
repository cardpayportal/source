<?
/**
 * @var PanelController $this
 */
$this->renderPartial('//layouts/_header')

?>

<body>
	
	<div id="content" class="center">

		<?/*
		<h1 style="color: red">
			ВНИМАНИЕ! Проблемы с обновлением платежей на кошельках. Тех работы до 15:30 мск.
		</h1>
		*/?>
	<?if($user = User::getUser() and ($user->client_id == 6)){?>

		<?//сообщение для клиентов?>
		<?/*?>
		<h1 style="color: red">
			ВНИМАНИЕ! Остановите залив средств на кошельки!!! Баны.
		</h1>

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
				<?}?>

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

	<script>
		$(document).ready(function(){

			<?//показать все транзакции аккаунта?>
			$(document).on( "click", ".showTransactions", function(){
				$(this).parent().find('tr[data-param=toggleRow]').show();
				$(this).text('Скрыть');
				$(this).removeClass('showTransactions');
				$(this).addClass('hideTransactions');
			});

			<?//скрыть старые транзакции?>
			$(document).on( "click", ".hideTransactions", function() {
				$(this).parent().find('tr[data-param=toggleRow]').hide();
				$(this).text('Показать все');
				$(this).removeClass('hideTransactions');
				$(this).addClass('showTransactions');
			});

			<?//изменить метку кошелька?>
			$(document).on('click', '.changeLabel', function(){
				$(this).parent().append('<input type="text" data-id="'+$(this).attr('data-id')+'" class="changeLabelText" value="'+$(this).parent().find('strong').text()+'"/>');
				$(this).parent().find('strong,a').hide();
			});

			$(document).on('keyup', '.changeLabelText', function(event){
				if(event.keyCode==13)
				{
					var obj = $(this);

					var postData = 'id='+$(this).attr('data-id')+'&label='+$(this).val();

					sendRequest('<?=url('manager/ajaxChangeLabel')?>', postData, function(response){

						if(response)
						{
							if(response.error==0)
							{
								obj.parent().find('strong').text(obj.val());
								obj.parent().find('strong,a').show();
								obj.remove();
							}
							else
								alert(response.error);
						}
						else
							alert('ошибка запроса 1');
					});
				}

			});

		});

	</script>

	<?if($this->isAdmin()){?>
		<script>
			$(document).on('click', 'body', function(event){

			});
		</script>
	<?}?>
</body>
</html>