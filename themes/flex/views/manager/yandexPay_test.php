<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var string $payUrl
 * @var YandexPay[] $models
 * @var WexAccount $wexAccount
 * @var array $stats
 */

$this->title = 'Yandex Money';
?>

	<h1><?=$this->title?></h1>

<?if($payUrl){?>

	<a href="<?=url('manager/yandexPay')?>">назад</a>


	<br>скопировать ссылку на оплату: <input type="text" size="90" value="<?=$payUrl?>" id="payUrl" class="click2select">


<?}else{?>
	<form method="post" action="<?=url('manager/yandexPay')?>">

		<p>
			<b>Сумма:</b><br>
			<input type="text" name="params[amount]" value="<?=$params['amount']?>"> руб
		</p>

		<p>
			<input type="submit" name="pay" value="Перейти к оплате"/>
		</p>

	</form>
<?}?>


	<br><hr><br>

	<div>
		<form method="post" action="<?=url('manager/yandexPay')?>">
			с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
			до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
			<input  type="submit" name="stats" value="Показать"/>
		</form>
	</div>
	<br>

	<div>
		<form method="post" action="<?=url('manager/yandexPay')?>" style="display: inline">
			<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
			<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
			<input  type="submit" name="stats" value="За сегодня"/>
		</form>

		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

		<form method="post" action="<?=url('manager/yandexPay')?>" style="display: inline">
			<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
			<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
			<input  type="submit" name="stats" value="За вчера"/>
		</form>
	</div>
	<br>

	<p>
		<b>Всего:</b> <?=formatAmount($stats['count'], 0)?> платежей на сумму <?=formatAmount($stats['allAmount'], 0)?>
		(<span class="success">оплачено:</span> <b><?=formatAmount($stats['amount'], 0)?></b>)
	</p>

	<form method="post" action="<?=url('manager/yandexPay')?>">
		<input type="hidden" name="params[accountId]" value="<?=$wexAccount->id?>">
		<p>
			Дата проверки: <?=($wexAccount->date_check) ? date('d.m.Y H:i') : ''?>
			<input type="submit" name="updateHistory" value="обновить платежи">
		</p>
	</form>

	<p><a href="<?=url('manager/yandexPayHistory')?>">Вся история поступлений</a></p>

<?if($models){?>

	<table class="std padding">

		<thead>
		<th>ID</th>
		<th>Сумма</th>
		<th>Статус</th>
		<th>Добавлен</th>
		<?if(!$this->isManager()){?>
			<th>Юзер</th>
		<?}?>
		<th>Ссылка</th>
		<th>Действие</th>
		</thead>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><b><?=$model->amountStr?></b></td>
				<td>
					<?if($model->status == YandexPay::STATUS_SUCCESS){?>
						<span class="success"><?=$model->statusStr?></span>
						<br><?=$model->datePayStr?>
					<?}elseif($model->status == YandexPay::STATUS_ERROR){?>
						<span class="error"><?=$model->statusStr?></span>
						<br>(<?=$model->error?>)
					<?}elseif($model->status == YandexPay::STATUS_WAIT){?>
						<span class="wait"><?=$model->statusStr?></span>
					<?}?>
				</td>
				<td><?=$model->dateAddStr?></td>
				<?if(!$this->isManager()){?>
					<th><?=$model->user->name?></th>
				<?}?>
				<td>
					<span class="shortContent"><?=$model->urlShort?></span>
					<input style="display: none" type="text" size="40" value="<?=$model->url?>" class="click2select fullContent">
				</td>
				<td>
					<?if($model->mark == YandexPay::MARK_CHECKED){?>
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
