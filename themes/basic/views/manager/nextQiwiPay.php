<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var array $payParams
 * @var NextQiwiPay[] $models
 * @var Acount $account
 * @var array $statsQiwi
 */

$this->title = 'Qiwi Next';
?>

<h1><?=$this->title?></h1>

<?if($payParams){?>

	<a href="<?=url('manager/NextQiwiPay')?>">назад</a>

	<p>
		<b>Реквизиты</b><br>
		<input type="text" size="60"  value="<?=$payParams['wallet']?>  <?=$payParams['amount']?> руб <?=$payParams['comment']?>" class="click2select">
	</p>
	<!--
	<p>
		<b>Сумма</b><br>
		<input type="text"  value="<?=$payParams['amount']?>" class="click2select">
	</p>

	<p>
		<b>Коммент</b><br>
		<input type="text"  value="<?=$payParams['comment']?>" class="click2select">
	</p>
	-->


<?}else{?>
	<form method="post" action="<?=url('manager/nextQiwiPay')?>">
		<p>
			<b>Сумма:  </b><br>
			<input type="text" name="params[amount]" value="<?=$params['amount']?>"> руб
		</p>

		<p>
			<input type="submit" name="pay" value="Получить реквизиты"/>
		</p>
	</form>
<?}?>
<br><br>
<p>
	<strong>Внимание!!! Оплата через терминалы не принимается!!!<br>только прямые переводы с киви кошелька</strong>
</p>

<hr><br>


<br><br>
<div>
	<form method="post" action="<?=url('manager/nextQiwiPay')?>">
		с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
		до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</div>
<br>

<div>
	<form method="post" action="<?=url('manager/nextQiwiPay')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
		<input  type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?=url('manager/nextQiwiPay')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input  type="submit" name="stats" value="За вчера"/>
	</form>
</div>
<br>

<p>
	<b>Всего:</b> <?=formatAmount($statsQiwi['count'], 0)?> платежей на сумму <?=formatAmount($statsQiwi['allAmount'], 0)?>
	(<span class="success">оплачено:</span> <b><?=formatAmount($statsQiwi['amount'], 0)?></b>)
</p>

<form method="post" action="<?=url('manager/nextQiwiPay')?>">
	<input type="hidden" name="params[accountId]" value="<?=$payeerAccount->id?>">
	<p>
		Дата проверки: <?=($payeerAccount->date_check) ? date('d.m.Y H:i') : ''?>
		<input type="submit" name="updateHistory" value="обновить платежи">
	</p>
</form>
<!--
<p><a href="<?=url('manager/qiwiPayHistory')?>">Вся история поступлений</a></p>
-->

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
		<th></th>
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
					<?}elseif($model->status == QiwiPay::STATUS_RESERVED){?>
						<span class="wait dotted" title="Заявка поставлена в очередь создания"><?=$model->statusStr?></span>
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
				<td>
					<form method="post" action="" name="getTransactionStatusForm">
						<input type="hidden" name="params[id]" value="<?=$model->id?>">
						<input type="submit" name="getTransactionStatus" value="обновить">
					</form>
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
