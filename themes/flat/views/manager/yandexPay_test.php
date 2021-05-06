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

	<a href="<?=url('manager/yandexPay')?>">
		<button class="btn">назад</button>
	</a>

	<br>скопировать ссылку на оплату: <input class="form-control click2select" type="text" size="70" value="<?=$payUrl?>" class="click2select">

<?}else{?>

	<div class="box box-bordered">
		<div class="box-title">
			<h3>
				<i class="fa fa-bars"></i>Добавить
			</h3>
		</div>

		<div class="box-content nopadding">
			<form method="post" class="form-vertical form-bordered">
				<div class="form-group">
					<label for="textfield1" class="control-label">Сумма</label>
					<input type="text" name="params[amount]" value="<?=$params['amount']?>" class="form-control"> руб
				<span class="help-block">

				</span>
				</div>

				<div class="form-actions">
					<button type="submit" class="btn btn-primary" name="pay" value="Перейти к оплате">Перейти к оплате</button>
				</div>
			</form>
		</div>
	</div>

<?}?>

<?
$dateTo = time()+24*3600;
$now = time();
$periods = [
	['dateStart'=>date('d.m.Y', $now), 'dateEnd'=>date('d.m.Y', $dateTo), 'name'=>'за сегодня'],
	['dateStart'=>date('d.m.Y', $now - 3600*24), 'dateEnd'=>date('d.m.Y'), 'name'=>'за вчера'],
];
?>
<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-filter"></i>выберите интервал</h3>
	</div>
	<div class="box-content">
		<form method="post" class="form-vertical">
			<div class="row">
				<div class="col-sm-3">
					<label for="textfield1" class="control-label">С</label>
					<input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" id="textfield1" class="form-control"/>
				</div>

				<div class="col-sm-3">
					<label for="textfield2" class="control-label">По</label>
					<input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" id="textfield2" class="form-control"/>
				</div>

				<div class="col-sm-3">
					<br>&nbsp;
					<br>&nbsp;
					<button type="submit" class="btn btn-primary" name="stats" value="Показать">Показать</button>
				</div>
			</div>
		</form>
	</div>

	<?foreach($periods as $period){?>
		<form method="post" style="display: inline" >
			<input type="hidden" name="params[dateStart]" value="<?=$period['dateStart']?>" />
			<input type="hidden" name="params[dateEnd]" value="<?=$period['dateEnd']?>" />
			<button type="submit" class="btn" name="stats" value="Показать"><?=$period['name']?></button>
		</form>
	<?}?>
</div>

<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-bars"></i><?=$this->title?></h3>
	</div>
	<div class="box-content">
		<p>
			<b>Всего:</b> <?=formatAmount($stats['count'], 0)?> платежей на сумму <?=formatAmount($stats['allAmount'], 0)?>
			(<span class="success">оплачено:</span> <b><?=formatAmount($stats['amount'], 0)?></b>)
		</p>

		<form method="post" action="">
			<input type="hidden" name="params[accountId]" value="<?=$wexAccount->id?>">
			<p>
				Дата проверки: <?=($wexAccount->date_check) ? date('d.m.Y H:i') : ''?>
				<input type="submit" name="updateHistory" value="обновить платежи" class="btn btn-primary">
				&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="<?=url('manager/yandexPayHistory')?>">
					<button type="button" class="btn btn-green">Вся история поступлений</button>
				</a>
			</p>
		</form>

		<?if($models){?>
			<table class="table table-bordered table-colored-header">
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
				<tbody>
				<?foreach($models as $model){?>
					<tr>
						<td><?=$model->id?></td>
						<td><?=$model->amountStr?></td>
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
							<input style="display: none" type="text" size="40" value="<?=$model->url?>" class="click2select fullContent form-control">
						</td>
						<td>
							<?if($model->mark == YandexPay::MARK_CHECKED){?>
								<form method="post" action="">
									<input type="hidden" name="params[id]" value="<?=$model->id?>">
									<input type="submit" name="cancel" value="выдано" class="green form-control" >
								</form>
							<?}else{?>
								<form method="post" action="">
									<input type="hidden" name="params[id]" value="<?=$model->id?>">
									<input type="submit" name="check" value="отдать" class="form-control">
								</form>
							<?}?>
						</td>
					</tr>
				<?}?>
				</tbody>
			</table>
		<?}else{?>
			записей не найдено
		<?}?>
	</div>
</div>



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
