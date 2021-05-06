<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var ManagerOrder[] $models
 */

$this->title = 'Старые заявки';
?>

<h1><?=$this->title?></h1>

<?if($models){?>

	<?foreach($models as $order){?>
		<div>
			<h3>Заявка #<?=$order->id?> (<?=$order->dateAddStr?> - <?=$order->dateEndStr?>)</h3>
			<p>Принято: <?=formatAmount($order->amountIn)?> из <?=formatAmount($order->amount_add)?></p>

			<table class="std padding">
				<tr>
					<td>Кошелек</td>
					<td>Принято</td>
					<td>Проверка</td>
				</tr>

				<?foreach($order->orderAccounts as $orderAccount){?>
					<tr>
						<td><?=$orderAccount->account->hiddenLogin?></td>
						<td>
							<?=formatAmount($orderAccount->amountIn)?>
							из <?=formatAmount($orderAccount->amount)?> руб
						</td>
						<td>
							<b><?=date('H:i:s', $orderAccount->account->date_check)?></b>
							<br />
							<?=date('d.m', $orderAccount->account->date_check)?>
						</td>
					</tr>
				<?}?>
			</table>
		</div>
	<?}?>
<?}else{?>
	<a href="<?=url('manager/accountAdd')?>">Добавить</a>
<?}?>
