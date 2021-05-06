<?
/**
 * @var YandexAccount[] $models
 * @var array $params
 * @var array $stats
 */
$this->title = 'Яндекс кошельки'

?>

<h1><?=$this->title?></h1>


<form method="post">
	<p>
		<b>Количество</b>
		&nbsp;
		&nbsp;
		&nbsp;
		<input type="text" name="params[count]" value="<?=$params['count']?>"/>
		&nbsp;
		&nbsp;
		&nbsp;
		<input type="submit" name="pickAccounts" value="Получить кошельки">
	</p>
</form>

<?if($models){?>

	<p>
		<b>Всего принято: <?=formatAmount($stats['amountIn'], 0)?></b>
	</p>

	<table class="std padding">

		<thead>
			<tr>
				<th>Кошелек</th>
				<th>Баланс</th>
				<th>Статус</th>
				<th>Остаток лимита</th>
				<th>Проверен</th>
			</tr>
		</thead>

		<tbody>
			<?foreach($models as $model){?>
				<tr>
					<td><?=$model->wallet?></td>
					<td><?=formatAmount($model->balance, 0)?></td>
					<td>
						<?if(!$model->error){?>
							<span class="success">активен</span>
						<?}else{?>
							<span class="error"><?=$model->error?></span>
						<?}?>
					</td>
					<td>
						<?=formatAmount($model->limitIn)?>
					</td>
					<td><?=$model->dateCheckStr?></td>
				</tr>

				<?if($transactions = $model->transactionsManager){?>
					<tr>
						<td colspan="5">
							<table class="noBorder trHeight" style="margin-left: 10px; width: 100%;">
								<tr>
									<td><b>Сумма</b></td>
									<td><b>Дата</b></td>
									<td><b>Комментарий</b></td>
								</tr>
								<?foreach($transactions as $trans){?>
									<tr>
										<td width="100"><?=$trans->amount?> руб</td>
										<td><?=date('d.m.Y H:i', $trans->date_add)?></td>
										<td><?=$trans->comment?></td>
									</tr>
								<?}?>
							</table>
						</td>
					</tr>
				<?}?>
			<?}?>
		</tbody>
	</table>


<?}?>
