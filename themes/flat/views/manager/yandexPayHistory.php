<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var string $payUrl
 * @var TransactionWex[] $models
 * @var WexAccount $wexAccount
 */

$this->title = 'История поступлений';
?>

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
		<h3>
			<form method="post" action="<?=url('manager/yandexPay')?>">
				<input type="hidden" name="params[accountId]" value="<?=$wexAccount->id?>">
				<p>
					Дата проверки: <?=($wexAccount->date_check) ? date('d.m.Y H:i') : ''?>
					<br>
					<input type="submit" name="updateHistory" value="обновить платежи" class="btn btn-primary">
					&nbsp;&nbsp;&nbsp;&nbsp;
					<a href="<?=url('manager/yandexPay')?>">
						<button type="button" class="btn btn-blue">История по ссылкам</button>
					</a>
				</p>
			</form>
		</h3>

	</div>

	<div class="box-content">

		<?if($models){?>
			<table class="table table-bordered table-colored-header">
				<thead>
					<th>Сумма</th>
					<th>Статус</th>
				</thead>

				<tbody>
				<?foreach($models as $model){?>
					<tr>
						<td><b><?=$model->originalAmount?> <?=$model->currency?></b></td>
						<td>
							<?if($model->status == TransactionWex::STATUS_SUCCESS){?>
								<span class="success">оплачен</span>
							<?}elseif($model->status == TransactionWex::STATUS_ERROR){?>
								<span class="error">ошибка</span>
							<?}?>
							<br>
							<?=date('d.m.Y H:i', $model->date_add)?>
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
