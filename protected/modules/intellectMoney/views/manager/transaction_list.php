<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var string $payUrl
 * @var IntellectTransaction[] $models
 * @var array $statsIntellect
 * @var int $userId
 */

$this->title = 'Заявки на оплату';
?>

<h1><?=$this->title?></h1>

<?if($payUrl){?>

	<a href="<?=url('intellectMoney/manager/transactionList')?>">назад</a>


	<br>скопировать ссылку на оплату:
	<input type="text" size="90" value="<?=$payUrl?>" id="payUrl" class="click2select" title="">


	<button onclick="myFunction()" onmouseout="outFunc()">
		<span id="tooltip">Копировать</span>
	</button>

<?}else{?>
	<form method="post" action="<?=url('intellectMoney/manager/transactionList')?>">

		<p>
			<b>Сумма:</b><br>
			<input type="text" name="params[amount]" value="<?=$params['amount']?>"> руб
		</p>

		<p>
			<input type="submit" name="getPayParams" value="Перейти к оплате"/>
		</p>

	</form>
<?}?>


<br><hr>

<?php /*
<form method="post">
	<strong>поиск по комменту:</strong>
	<p>
		<input type="text" name="searchStr" placeholder="введите комментарий или его часть"/>&nbsp
		<input type="submit" name="search" value="Поиск">
	</p>
</form>

 */ ?>

<br>

<div>
	<form method="post" action="<?=url('intellectMoney/manager/transactionList')?>">
		с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
		до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</div>
<br>

<div>
	<form method="post" action="<?=url('intellectMoney/manager/transactionList')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
		<input  type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?=url('intellectMoney/manager/transactionList')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input  type="submit" name="stats" value="За вчера"/>
	</form>
</div>
<br>

<p>
	<b>Всего:</b> <?=formatAmount($statsIntellect['count'], 0)?> заявок на сумму <?=formatAmount($statsIntellect['allAmount'], 0)?>
	(<span class="success">оплачено:</span> <b><?=formatAmount($statsIntellect['countSuccess'], 0).' платежей на сумму '?><?=formatAmount($statsIntellect['amount'], 0)?></b>)
</p>



<?if($models){?>

	<table class="std padding">

		<thead>
		<th>ID</th>
		<th>Сумма</th>
		<th>OrderId</th>
		<th>Статус</th>
		<th>Добавлен</th>
		<th>Ссылка</th>
		</thead>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><b><?=$model->amountStr?></b></td>
				<td><b><?=$model->order_id?></b></td>
				<td>
					<?if($model->status == IntellectTransaction::STATUS_SUCCESS){?>
						<span class="success"><?=$model->statusStr?></span>
						<br><?=$model->datePayStr?>
					<?}elseif($model->status == IntellectTransaction::STATUS_ERROR){?>
						<span class="error"><?=$model->statusStr?></span>
						<br>(<?=$model->error?>)
					<?}elseif($model->status == IntellectTransaction::STATUS_PROCCESS){?>
						<span class="accountTransit"><?=$model->statusStr?></span>
					<?}elseif($model->status == IntellectTransaction::STATUS_WAIT){?>
						<span class="wait"><?=$model->statusStr?></span>
					<?}?>
				</td>
				<td>
					<?=$model->dateAddStr?>

					<?if($model->created_by_api){?>
						<br>
						(<span class="success" title="получен через API">API</span>)
					<?}?>
				</td>
				<td>
					<span class="shortContent"><?=$model->urlShort?></span>
					<input style="display: none" type="text" size="40" value="<?=$model->pay_url?>" class="click2select fullContent">
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

	function myFunction() {
		var copyText = document.getElementById("payUrl");
		copyText.select();
		document.execCommand("copy");
		var tooltip = document.getElementById("tooltip");
		tooltip.innerHTML = "Скопировано";
	}
</script>
