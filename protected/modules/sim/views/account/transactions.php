<?
/**
 * @var Controller $this
 * @var array $params
 * @var SimAccount $account
 * @var SimTransaction[] $models
 * @var string $transactionsType
 */

if($transactionsType == 'successIn')
	$this->title = 'Платежи кошелька '.$account->login;
elseif($transactionsType == 'out')
	$this->title = 'Списания кошелька '.$account->login;
?>

<h1><?=$this->title?></h1>

<p>
	<a href="<?=url('sim/account/list')?>">назад</a>
</p>

<?if($models){?>
	<table class="std padding">
		<tr>
			<td>TransactionId</td>
			<td>OrderId</td>
			<td>Сумма</td>
			<td>Дата</td>
			<td>Статус</td>
			<td>Действие</td>
		</tr>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><?=$model->order_id?></td>
				<td><?=$model->amountStr?></td>
				<td><?=date('d.m.Y H:i', $model->date_add)?></td>
				<td><?=$model->statusStr?></td>
				<td>
					<?if($model->amount < 0){?>
						<form method="post">
							<input type="hidden" name="params[transactionId]" value="<?=$model->id?>">
							<input type="submit" name="deleteTransaction" value="удалить">
						</form>
					<?}?>
				</td>
			</tr>
		<?}?>
	</table>
<?}?>


