<?
/**
 * @var ControlController $this
 * @var StoreApi $store
 * @var StoreApiTransaction[] $notPaidTransactions
 * @var float $notPaidAmount
 */

$this->title = 'Рассчитать магазин '.$store->store_id;

/**
 * * @var ControlController $this
 *
 *
 */
?>

<h1><?=$this->title?></h1>
<i>
	Баланс клиента - это сумма его невыведенных платежей.
	При нажатии на кнопку создается искуственный вывод в рублях и все неоплаченные платежи текущего магазина становятся оплаченными.
</i>

<p>
	<?=$this->renderPartial('_storeApiMenu')?>
</p>

<p>
	<b>Баланс: <?=formatAmount($notPaidAmount)?></b>
</p>

<?if($notPaidTransactions){?>

	<form method="post">

		<p>
			<input type="submit" name="markPaid" value="Пометить все платежи оплаченными">
		</p>

		<table class="std padding">
			<tr>
				<td style="text-align: left"><input type="checkbox" id="checkAll" checked="checked"> ID</td>
				<td>Сумма</td>
				<td>Кошелек</td>
				<td>От</td>
				<td>Статус</td>
				<td>ID</td>
				<td>Дата добавления</td>
			</tr>

			<?foreach($notPaidTransactions as $model){?>
				<tr>
					<td style="text-align: left">
						<input type="checkbox" class="check" name="params[transactions][]" value="<?=$model->id?>" checked="checked">
						 <?=$model->id?>
					</td>
					<td><?=$model->amountStr?></td>
					<td><?=$model->account->login?></td>
					<td><?=$model->wallet_from?></td>
					<td><?=$model->statusStr?></td>
					<td><?=$model->qiwi_id?></td>
					<td><?=$model->dateAddStr?></td>
				</tr>
			<?}?>
		</table>

		<input type="hidden" name="params[storeId]" value="<?=$store->store_id?>">

	</form>


<?}else{?>
	неоплаченных платежей не найдено
<?}?>


