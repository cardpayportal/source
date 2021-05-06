<?
$this->title = 'Магазины StoreApi';

/**
 * @var StoreApi[] $models
 */
?>

<h1><?=$this->title?></h1>

<p>
	<?=$this->renderPartial('_storeApiMenu')?>
</p>

<?if($models){?>
	<form method="post">

		<table class="table table-nomargin table-bordered table-colored-header">
			<thead>
				<th>ID</th>
				<th>Кошелек</th>
				<th>Менеджер</th>
				<th>Статус</th>
				<th>Мин вывод (руб)</th>
				<th>Баланс (руб)</th>
				<th>Действие</th>
			</thead>

			<?foreach($models as $model){?>
				<tr>
					<td>store<?=$model->id?></td>
					<td>
						<?=$model->withdraw_wallet?>
						<?if($model->date_wallet_change){?>
							<br><span class="success">изменен: <?=$model->dateWalletChangeStr?></span>
						<?}?>
					</td>
					<td><?=$model->user->name?></td>
					<td><?=$model->statusStr?></td>
					<td>
						<input type="text" name="withdraw_limit[<?=$model->id?>]" value="<?=$model->withdrawLimitVal?>" size="6"/>
						<?//чтобы по энтеру на поле нажималась save?>
						<input type="submit" name="save" value="Сохранить" style="display: none">
					</td>
					<td>
						<?=formatAmount($model->balance, 0)?>

						<?if($model->balance > 0){?>
							<br><a href="<?=url('control/storeApiWithdrawAdd', array('storeId'=>$model->id))?>">рассчитать</a>
						<?}?>
					</td>
					<td>
						<form method="post">
							<input type="hidden" name="params[id]" value="<?=$model->id?>">

							<?if($model->isEnabled){?>
								<input type="submit" name="switchStatus" value="отключить" class="red">
							<?}else{?>
								<input type="submit" name="switchStatus" value="включить" class="green">
							<?}?>
						</form>
					</td>
				</tr>
			<?}?>
		</table>

		<p><input type="submit" name="save" value="Сохранить"></p>

	</form>
<?}else{?>
	магазины не найдены
<?}?>
