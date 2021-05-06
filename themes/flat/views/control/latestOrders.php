<?
/**
 * @var ControlController $this
 * @var ManagerOrder[] $models
 * @var int $timestampStart
 */
$this->title = 'Последние заявки с '.date('d.m.Y H:i', $timestampStart);
?>

<?if($models){?>

	<?foreach($models as $order){?>
		<div class="box">
			<div class="box-title">
				<h3>
					<i class="fa fa-bars"></i>

					<?if($this->isAdmin() or $this->isGlobalFin()){?>
						Client<?=$order->user->client_id?>
					<?}?>
					Заявка

					<?if($calc = $order->calc){?>
						<?if($calc->status == ClientCalc::STATUS_DONE){?>
							<span class="success main dotted" title="оплачена (расчет от <?=$calc->dateAddStr?>)">#<?=$order->id?></span>
						<?}elseif($calc->status == ClientCalc::STATUS_WAIT or $calc->status == ClientCalc::STATUS_NEW){?>
							<span class="warning main" title="в процессе (расчет от <?=$calc->dateAddStr?>)">#<?=$order->id?></span>
						<?}?>
					<?}elseif($order->date_pay){?>
						<span class="success main dotted" title="оплачена (<?=$order->datePayStr?>)">#<?=$order->id?></span>
					<?}else{?>
						<span class="main" >#<?=$order->id?></span>
					<?}?>

					(<?=$order->dateAddStr?><?=($order->date_end) ? " - {$order->dateEndStr}" : ''?>)
					<?=$order->user->name?>
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
						<th>Баланс</th>
					</tr>
					</thead>
					<tbody>
					<?foreach($order->orderAccounts as $orderAccount){?>
						<tr>
							<td><?=$orderAccount->account->login?></td>
							<td>
								<?=formatAmount($orderAccount->amountIn)?>
								из <?=formatAmount($orderAccount->amount)?> руб
							</td>
							<td>
								<b><?=date('H:i:s', $orderAccount->account->date_check)?></b>
								<br />
								<?=date('d.m', $orderAccount->account->date_check)?>
							</td>
							<td><?=$orderAccount->account->balanceStr?></td>
						</tr>
					<?}?>
					</tbody>
				</table>
			</div>
		</div>
	<?}?>
<?}else{?>
	<p>заявок не найдено</p>
<?}?>