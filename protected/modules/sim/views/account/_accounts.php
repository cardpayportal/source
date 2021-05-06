<?php
/**
 * @var SimAccount[] $models
 * @var array $transactionStats
 */
?>

<?foreach($models as $model){?>
	<tr>
		<td><input type="checkbox" class="check" value="<?=$model->id?>"></td>
		<td><?=$model->client->name?></td>
		<td><b><?=$model->login?></b></td>
		<td>
			<?=formatAmount($model->balance, 0)?>
		</td>
		<td>
			<form method="post" class="withdrawForm">
				<nobr>
					<?/*<b><?$amountOut = $model->amountOut; echo formatAmount($amountOut, 0)?> руб</b>
					<?if($amountOut > 0){?>
						(<a href="<?=url('sim/account/transactions', ['accountId'=>$model->id, 'type'=>'out'])?>">
							<?=formatAmount($model->getTransactionCount('out'))?>
						</a>)
					<?}?>
					*/?>
					<input type="text" name="params[amount]" value="<?=$params['amount']?>" size="6">
					<input type="hidden" name="withdraw" value="списать">
				</nobr>
				<input type="hidden" name="params[account_id]" value="<?=$model->id?>">
				<input type="hidden" name="params[currency]" value="<?=SimTransaction::CURRENCY_RUB?>">
				<input type="hidden" name="params[status]" value="<?=SimTransaction::STATUS_SUCCESS?>">
			</form>
		</td>
		<td>
			<form method="post" class="limitForm">
				<nobr>
					<input type="text" name="params[limit]" value="<?=formatAmount($model->limitIn, 0)?>" size="6">
				</nobr>
				<input type="hidden" name="params[account_id]" value="<?=$model->id?>">
				<input type="hidden" name="limit" value="OK">
			</form>
		</td>
		<td>
			<nobr><b><?$amountIn = $model->amountIn; echo formatAmount($amountIn, 0)?> руб</b></nobr>
			<?if($amountIn > 0){?>
				<br>
				<nobr>
					(<a href="<?=url('sim/account/transactions', ['accountId'=>$model->id])?>">
					 <?=formatAmount($model->getTransactionCount('successIn'))?>
					 из <?=formatAmount($model->getTransactionCount('in'))?>
					</a>)
				</nobr>
			<?}?>

		</td>
		<td>
			<form method="post">
				<select name="params[status]" class="changeStatus">
					<?foreach(SimAccount::getStatusArr() as $statusId=>$statusName){?>

						<option value="<?=$statusId?>"
								<?if($statusId == $model->status){?>selected<?}?>
						>
							<?=$statusName?>
						</option>

					<?}?>
				</select><br>
				<input type="hidden" name="changeStatus" value="сменить">
				<input type="hidden" name="params[account_id]" value="<?=$model->id?>">
			</form>
		</td>
		<td><?=date('d.m.Y', $model->date_add)?></td>
	</tr>
<?}?>

<tr>
	<td><b>Всего</b></td>
	<td></td>
	<td></td>
	<td><?=formatAmount($transactionStats['amountOut'], 0)?> руб</td>
	<td></td>
	<td><?=formatAmount($transactionStats['amountIn'], 0)?> руб</td>
	<td></td>
	<td></td>
</tr>