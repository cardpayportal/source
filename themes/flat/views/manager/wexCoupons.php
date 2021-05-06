<?php
/**
 * @var ManagerController $this
 * @var array $params
 * @var Coupon[] $models
 * @var array $stats
 * @var array $interval
 *
 */
$this->title = 'Wex-коды';
?>


<div class="box box-bordered">
	<div class="box-title">
		<h3>
			<i class="fa fa-bars"></i>Добавить
		</h3>
	</div>

	<div class="box-content nopadding">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<label for="textfield1" class="control-label">Добавить несколько:</label>
				<textarea name="params[coupons]" rows="10" cols="45" id="textfield1" class="form-control"><?=$params['coupons']?></textarea>
				<span class="help-block">
					(максимум 10)
				</span>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="add" value="Активировать">Активировать</button>
			</div>
		</form>
	</div>
</div>

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
		<?if($models){?>
			<p>
				<b>Всего:</b> <?=formatAmount($stats['count'], 0)?> кодов на сумму <?=formatAmount($stats['amount'], 0)?>
			</p>

			<table class="table table-bordered table-colored-header">
				<thead>
					<th>Код</th>
					<th>Статус</th>
					<th>Сумма</th>
					<th>Добавлен</th>
					<?if(!$this->isManager()){?>
						<th>Юзер</th>
					<?}?>
				</thead>
				<tbody>
					<?foreach($models as $model){?>
						<tr>
							<td><?=$model->code?></td>
							<td><?=$model->statusStr?></td>
							<td><?=$model->amountStr?></td>
							<td><?=$model->dateAddStr?></td>
							<?if(!$this->isManager()){?>
								<th><?=$model->user->name?></th>
							<?}?>
						</tr>
					<?}?>
				</tbody>
			</table>
		<?}else{?>
			кодов не найдено
		<?}?>
	</div>
</div>