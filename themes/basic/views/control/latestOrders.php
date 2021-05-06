<?
/**
 * @var ControlController $this
 * @var ManagerOrder[] $models
 * @var int $timestampStart
 */
$this->title = 'Последние заявки';
?>

<h1><?=$this->title?> с <?=date('d.m.Y H:i', $timestampStart)?></h1>

<?if($models){?>
	<?foreach($models as $order){?>
		<div>
			<h3>
				<?if($this->isAdmin() or $this->isGlobalFin()){?>
					<?=$order->user->client->name?>
				<?}?>
				Заявка #<?=$order->id?> (<?=$order->dateAddStr?><?=($order->date_end) ? " - {$order->dateEndStr}" : ''?>)
				<br><?=$order->user->name?>
				<br>
				<?if($calc = $order->calc){?>
					<?if($calc->status == ClientCalc::STATUS_DONE){?>
						<span class="success main">оплачена</span> (расчет от <?=$calc->dateAddStr?>)
					<?}elseif($calc->status == ClientCalc::STATUS_WAIT or $calc->status == ClientCalc::STATUS_NEW){?>
						<span class="warning main">в процессе</span> (расчет от <?=$calc->dateAddStr?>)
					<?}?>
				<?}else{?>
					<?if($order->date_pay){?>
						<span class="success main">оплачена</span> (<?=$order->datePayStr?>)
					<?}?>
				<?}?>
			</h3>
			<p>Принято: <?=formatAmount($order->amountIn)?> из <?=formatAmount($order->amount_add)?></p>

			<table class="std padding">
				<tr>
					<td>Кошелек</td>
					<td>Принято</td>
					<td>Проверка</td>
					<td>Баланс</td>
				</tr>

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
			</table>
		</div>
	<?}?>
<?}else{?>
	нет заявок
<?}?>
