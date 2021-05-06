<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var string $payUrl
 * @var QiwiYandex[] $models
 * @var array $stats
 */

$this->title = 'Qiwi=>Yandex';
?>

<h1><?=$this->title?></h1>

<?if($payUrl){?>

	<a href="<?=url('manager/qiwiYandex')?>">назад</a>


	<br>скопировать ссылку на оплату:
	<input type="text" size="90" value="<?=$payUrl?>" id="payUrl" class="click2select" title="">


	<button onclick="myFunction()" onmouseout="outFunc()">
		<span id="tooltip">Копировать</span>
	</button>

<?}else{?>
	<form method="post" action="<?=url('manager/qiwiYandex')?>">

		<p>
			<b>Сумма:</b><br>
			<input type="text" name="params[amount]" value="<?=$params['amount']?>"> руб
		</p>

		<p>
			<input type="submit" name="pay" value="Перейти к оплате"/>
		</p>

	</form>
<?}?>


<br><hr>

<div>
	<form method="post" action="<?=url('manager/qiwiYandex')?>">
		с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
		до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
		<input  type="submit" name="stats" value="Показать"/>
	</form>
</div>
<br>

<div>
	<form method="post" action="<?=url('manager/qiwiYandex')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
		<input  type="submit" name="stats" value="За сегодня"/>
	</form>

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<form method="post" action="<?=url('manager/qiwiYandex')?>" style="display: inline">
		<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
		<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
		<input  type="submit" name="stats" value="За вчера"/>
	</form>
</div>
<br>

<p>
	<b>Всего:</b> <?=formatAmount($stats['count'], 0)?> платежей на сумму <?=formatAmount($stats['allAmount'], 0)?>
	(<span class="success">оплачено:</span> <b><?=formatAmount($stats['countSuccess'], 0).' платежей на сумму '?><?=formatAmount($stats['amount'], 0)?></b>)
</p>

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
					<?if($model->status == QiwiYandex::STATUS_SUCCESS){?>
						<span class="success"><?=$model->statusStr?></span>
						<br><?=$model->datePayStr?>
					<?}elseif($model->status == QiwiYandex::STATUS_ERROR){?>
						<span class="error">
							<?=$model->statusStr?>
							<br><?=$model->error?>
						</span>
					<?}elseif($model->status == QiwiYandex::STATUS_WAIT){?>
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
				<?if(!$this->isManager()){?>
					<th><?=$model->user->name?></th>
				<?}?>
				<td>
					<?=htmlentities($model->url)?>
				</td>
				<td>
					<?if($model->mark == QiwiYandex::MARK_CHECKED){?>
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

	function myFunction() {
		var copyText = document.getElementById("payUrl");
		copyText.select();
		document.execCommand("copy");
		var tooltip = document.getElementById("tooltip");
		tooltip.innerHTML = "Скопировано";
	}
</script>
