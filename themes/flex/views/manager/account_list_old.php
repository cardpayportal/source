<?
/**
 * @var ManagerController $this
 * @var Account[] $slowCheckAccounts
 * @var Account[] $accounts
 * @var User $user
 */


if(false){//TODO: убрать если больше не нужно?>
if(!$this->title)
{

	if($user->client->pick_accounts_next_qiwi)
		$this->title = 'Текущие заявки';
	else
		$this->title = 'Текущие кошельки';
}
?>

<h2>
	<?=$this->title?>
	<?if($this->isAdmin() or $this->isFinansist()){?> (<?=$allCount?>)<?}?>
</h2>



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



<?/*if(!$this->isAdmin()){?>
		<p style="">
			Нажмите на "Проверить сейчас", если нужно как можно быстрее обновить список платежей.
		</p>

		<p style="">
			Кошельки, не используемые более 30 дней, отправляются в отстойник
		</p>

		<p style="">
			СУТОЧНЫЙ оборот в 100к на одном кошельке превышать нежелательно
		</p>
<?}*/?>

<?//если включена заявочная система платежей то не показыавать кошельки?>
<?if(!$user->client->pick_accounts_next_qiwi){?>
	<?if(count($accounts)>1){?>
			<?foreach($accounts as $userName=>$models){?>
				<h2><?=$userName?></h2>
					<?$this->renderPartial('//manager/_accounts', array('models'=>$models, 'user'=>$user))?>
			<?}?>
	<?}else{?>
			<?$this->renderPartial('//manager/_accounts', array('models'=>array_shift($accounts), 'user'=>$user))?>
	<?}?>
	<input type="hidden" id="timestamp" value="<?=(time())?>"/>

	<?$this->renderPartial('//manager/_stats', array(
			'stats'=>$stats,
			'statsType'=>$statsType,
	))?>

	<br><hr>
<?}?>

<?}?>
	<p>
		<strong>ПОЛУЧИТЬ РЕКВИЗИТЫ</strong>
	</p>

<?if($payParams){?>

		<a href="<?=url('manager/AccountList')?>">назад</a>

		<p>
			<b>Реквизиты</b><br>
			<input type="text" id="payParams" size="60"  value="<?=$payParams['wallet']?>  <?=$payParams['amount']?> руб <?=$payParams['comment']?>" class="click2select">

			<button onclick="myFunction()" onmouseout="outFunc()">
				<span id="tooltip">Копировать</span>
			</button>
		</p>

	<?}else{?>
		<form method="post" action="<?=url('manager/AccountList')?>">
			<p>
				<b>Сумма:  </b><br>
				<input type="text" name="params[amount]" value="<?=$params['amount']?>"> руб
			</p>

			<p>
				<input type="submit" name="pay" value="Получить реквизиты"/>
			</p>
		</form>
	<?}?>
	<p>
		<strong>Внимание!!! Оплата через терминалы не принимается!!!<br>только прямые переводы с киви кошелька</strong>
	</p>

	<hr><br>
	<div>
		<form method="post" action="<?=url('manager/AccountList')?>">
			с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
			до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
			<input  type="submit" name="stats" value="Показать"/>
		</form>
	</div>
	<br>

	<div>
		<form method="post" action="<?=url('manager/AccountList')?>" style="display: inline">
			<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
			<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
			<input  type="submit" name="stats" value="За сегодня"/>
		</form>

		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

		<form method="post" action="<?=url('manager/AccountList')?>" style="display: inline">
			<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
			<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
			<input  type="submit" name="stats" value="За вчера"/>
		</form>
	</div>
	<br>

	<p>
		<b>Всего:</b> <?=formatAmount($statsQiwi['count'], 0)?> платежей на сумму <?=formatAmount($statsQiwi['allAmount'], 0)?>
		(<span class="success">оплачено:</span> <b><?=formatAmount($statsQiwi['countSuccess'], 0).' платежей на сумму '?><?=formatAmount($statsQiwi['amount'], 0)?></b>)
	</p>

	<?if($models){?>

		<table class="std padding">
			<thead>
			<th>ID</th>
			<th>Кошелек</th>
			<th>Сумма</th>
			<th>Коммент</th>
			<th>Статус</th>
			<th>Добавлен</th>
			<?if(!$this->isManager()){?>
				<th>Юзер</th>
			<?}?>
			<th>Действие</th>
			</thead>

			<?foreach($models as $model){?>
				<tr>
					<td><?=$model->id?></td>
					<td><b><?=$model->wallet?></b></td>
					<td><b><?=$model->amountStr?></b></td>
					<td><b><?=$model->comment?></b></td>
					<td>
						<?if($model->status == QiwiPay::STATUS_SUCCESS){?>
							<span class="success"><?=$model->statusStr?></span>
							<br><?=$model->datePayStr?>
						<?}elseif($model->status == QiwiPay::STATUS_ERROR){?>
							<span class="error"><?=$model->statusStr?></span>
							<br>(<?=$model->error?>)
						<?}elseif($model->status == QiwiPay::STATUS_WAIT){?>
							<span class="wait"><?=$model->statusStr?></span>
						<?}?>
					</td>
					<td>
						<?=$model->dateAddStr?>

						<?if($model->request_api_id){?>
							<br>
							(<span class="success" title="получен через API">API</span>)
						<?}?>
					</td>
					<?if(!$this->isManager()){?>
						<th><?=$model->user->name?></th>
					<?}?>
					<td>
						<?if($model->mark == QiwiPay::MARK_CHECKED){?>
							<form method="post" action="">
								<input type="hidden" name="params[id]" value="<?=$model->id?>">
								<input type="submit" name="cancel" value="выдано" class="green" >
							</form>
						<?}else{?>
							<form method="post" action="">
								<input type="hidden" name="params[id]" value="<?=$model->id?>">
								<input type="submit" name="check" value="отдать">
							</form>
						<?}?>
					</td>
				</tr>
			<?}?>
		</table>
	<?}else{?>
		записей не найдено
	<?}?>

	<script>
		$(document).ready(function(){
			$('.shortContent').click(function(){
				$(this).hide();
				$(this).parent().find('.fullContent').show().select();
			});
		});

		$(document).mouseup(function (e){
			var div = $(".fullContent");
			if (!div.is(e.target)
				&& div.has(e.target).length === 0) {
				div.hide(); // скрываем его
				div.parent().find('.shortContent').show();
			}
		});
	</script>


<script>
	$(document).ready(function(){
		setInterval(function(){
			location.reload();
		}, <?if($this->isAdmin()){?>600000<?}else{?>90000<?}?>);
	});

	function myFunction() {
		var copyText = document.getElementById("payParams");
		copyText.select();
		document.execCommand("copy");
		var tooltip = document.getElementById("tooltip");
		tooltip.innerHTML = "Скопировано";
	}
</script>
