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


<h1><?=$this->title?></h1>

<div>
	<form method="post">

		<p>
			<b>Добавить несколько:</b><br>
			<textarea name="params[coupons]" rows="10" cols="45"><?=$params['coupons']?></textarea>
		</p>

		<p>
			<input type="submit" name="add" value="Активировать">
		</p>

	</form>
</div>

<br><hr><br>

	<div>
		<form method="post">
			с <input type="text" name="params[dateStart]" value="<?=$interval['dateStart']?>" />
			до <input type="text" name="params[dateEnd]" value="<?=$interval['dateEnd']?>" />
			<input  type="submit" name="stats" value="Показать"/>
		</form>
	</div>
	<br>

	<div>
		<form method="post" style="display: inline">
			<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
			<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()+24*3600))?>" />
			<input  type="submit" name="stats" value="За сегодня"/>
		</form>

		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

		<form method="post" style="display: inline">
			<input type="hidden" name="params[dateStart]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()-24*3600))?>" />
			<input type="hidden" name="params[dateEnd]" value="<?=date('d.m.Y H:i', Tools::startOfDay(time()))?>" />
			<input  type="submit" name="stats" value="За вчера"/>
		</form>
	</div>
	<br>


<?if($models){?>

	<p>
		<b>Всего:</b> <?=formatAmount($stats['count'], 0)?> кодов на сумму <?=formatAmount($stats['amount'], 0)?>
	</p>


	<table class="std padding">

		<thead>
			<th>Код</th>
			<th>Статус</th>
			<th>Сумма</th>
			<th>Добавлен</th>
			<?if(!$this->isManager()){?>
				<th>Юзер</th>
			<?}?>
		</thead>

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
	</table>
<?}else{?>
	записей не найдено
<?}?>