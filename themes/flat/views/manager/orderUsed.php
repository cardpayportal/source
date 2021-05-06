<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var ManagerOrder[] $models
 * @var int $timestampStart
 */

$this->title = 'Старые заявки (с '.date('d.m.Y', $timestampStart).')';
?>

<?if($models){?>

	<?foreach($models as $order){?>
		<div class="box">
			<div class="box-title">
				<h3>
					<i class="fa fa-bars"></i>
					Заявка #<?=$order->id?> (<?=$order->dateAddStr?> - <?=$order->dateEndStr?>)

					<span style="margin-left: 50px">
						Принято: <?=formatAmount($order->amountIn)?> из <?=formatAmount($order->amount_add)?>
					</span>
				</h3>
			</div>
			<div class="box-content">
				<table class="table table-nomargin table-bordered table-colored-header">
					<thead>
					<tr>
						<th>Кошелек</th>
						<th>Принято</th>
						<th>Проверка</th>
					</tr>
					</thead>
					<tbody>
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
					</tbody>
				</table>
			</div>
		</div>
	<?}?>
<?}else{?>
	<p>заявок не найдено</p>
	<a href="<?=url('manager/accountAdd')?>">
		<button class="btn btn-primary btn--icon">
			<i class="fa fa-plus"></i>Добавить
		</button>
	</a>
<?}?>


