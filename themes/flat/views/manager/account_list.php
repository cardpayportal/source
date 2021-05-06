<?
/**
 * @var ManagerController $this
 * @var Account[] $slowCheckAccounts
 * @var User $user
 */

if(!$this->title)
	$this->title = 'Текущие кошельки';

if($this->isAdmin() or $this->isFinansist())
	$this->title .=  " ($allCount)";
?>


<?if($accounts){?>
	
	<p>
		Нажмите на "Проверить сейчас", если нужно как можно быстрее обновить список платежей.
	</p>

    <p>
        Кошельки, не используемые более 30 дней, отправляются в отстойник
    </p>

	<p>
		СУТОЧНЫЙ оборот в 100к на одном кошельке превышать нежелательно
	</p>

	<?if($this->isAdmin()){?>
		<p>
			<a href="<?=url('control/massCheck')?>">массовое обновление входящих (админ)</a>
		</p>
	<?}?>

	<?/* отобразить аккаунты, проверенные более получаса назад */?>
	<?if($this->isAdmin() and $slowCheckAccounts){?>
		<p><strong>Давно не проверялись: <?=count($slowCheckAccounts)?></strong></p>
		<?$banAmount = 0;?>
		<?foreach($slowCheckAccounts as $model){?>
			<p class="error">
				<a href="<?=url('account/list', array('login'=>trim($model->login, '+')))?>" target="_blank"><?=$model->login?></a> (<a target="_blank" href="<?=url('account/historyAdmin', array('id'=>$model->id))?>">история</a>)

				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				(<?=$model->client->name?>, groupId=<?=$model->group_id?>)

				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<?=$model->dateCheckStr?>

				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<?if($model->is_rill){?> rill<?}?>

				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				приоритет: <?=$model->check_priority?>

				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<?=formatAmount($model->balance, 0)?> руб

				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<?if($model->error){?><?=$model->error?><?}?>

				<?if($model->error=='ban'){/*?>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="<?=url('account/unban', array('id'=>$model->id))?>">разбанить</a>
				<?*/}?>
				<br>
				<?=$model->proxy?>


			</p>
		<?}?>

		<form method="post">
			<p>
				<input type="submit" name="clearCookiesSlowCheck" value="Очистить куки у всех медленных">
			</p>
		</form>
	<?}?>

    <?/* отобразить аккаунты, взятые давно */?>
    <?if($this->isAdmin() and $oldPickAccounts){?>

        <p><strong>Старше <?=(cfg('old_pick_interval')/3600/24)?> дней: <?=count($oldPickAccounts)?></strong></p>

        <?foreach($oldPickAccounts as $model){?>
            <p>
                <?=$model->login?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <?=$model->datePickStr?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?=$model->managerLimitStr?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cl<?=$model->client_id?>
            </p>
        <?}?>
    <?}?>

	<?if(count($accounts)>1){?>
		<?foreach($accounts as $userName=>$models){?>
			<?$this->renderPartial('//manager/_accounts', array('models'=>$models, 'user'=>$user, 'title'=>$userName))?>
		<?}?>
	<?}else{?>
		<?$this->renderPartial('//manager/_accounts', array('models'=>array_shift($accounts), 'user'=>$user))?>
	<?}?>
	
	<?$this->renderPartial('//manager/_stats', array(
		'stats'=>$stats,
		'statsType'=>$statsType,
	))?>
<?}else{?>
	не получено кошельков
<?}?>

<script>
	$(document).ready(function(){
		setInterval(function(){
			location.reload();
		}, <?if($this->isAdmin()){?>600000<?}else{?>90000<?}?>);
	});

</script>

<?$this->renderPartial('//manager/_transactionJs')?>