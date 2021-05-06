<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var ManagerOrder[] $orders
 */

$this->title = 'Активные заявки';
?>

<h1><?=$this->title?></h1>

<?if($orders){?>

	<?foreach($orders as $order){?>
		<div>
			<h3>Заявка #<?=$order->id?></h3>
			<form method="post">
				<p>
					Принято: <?=formatAmount($order->amountIn)?> из <?=formatAmount($order->amount_add)?>
					<input type="hidden" name="params[orderId]" value="<?=$order->id?>">
					<input type="submit" name="setPriorityNow" value="Проверить все">
				</p>
			</form>
				<table class="std padding">
					<tr>
						<td>Кошелек</td>
						<td>Принято</td>
						<td>Проверка</td>
						<td>Баланс</td>
						<td>Сообщение</td>
					</tr>

					<?foreach($order->orderAccounts as $orderAccount){?>
						<tr>
							<td>
								<?=$orderAccount->account->login?>
								<?if($orderAccount->account->check_priority >= Account::PRIORITY_NOW){?>
									<br> проверяется
								<?}else{?>
									<br />
									<form method="post">
										<input type="hidden" name="params[accountId]" value="<?=$orderAccount->account_id?>" />
										<input type="submit" name="setPriorityNow" value="Проверить сейчас"/>
									</form>
								<?}?>
							</td>
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
							<td><?=$orderAccount->account->orderMsg?></td>
						</tr>

						<?if($transactions = $orderAccount->transactions){?>
							<tr>
								<td colspan="<?=($this->isFinansist()) ? '9' : '9'?>">
									<table data-id="<?=$orderAccount->account->id?>" class="noBorder trHeight" style="margin-left: 10px; width: 100%;">

										<?$num=0?>

										<?foreach($transactions as $num=>$trans){?>
											<?=$this->renderPartial('//manager/_transaction', array(
												'num'=>$num,
												'trans'=>$trans,
											))?>

											<?//чтобы админу страница быстрее грузилась?>
											<?if($this->isAdmin() and  $num > 5){?>
												<tr>
													<td colspan="6">..............скрыты платежи</td>
												</tr>
												<?break;?>
											<?}?>

										<?}?>
									</table>

									<?if($num > 2){?>
										<br /><button class="showTransactions" type="button">Показать все</button>
									<?}?>
								</td>
							</tr>
						<?}?>

					<?}?>
				</table>

			<form method="post">
				<p>
					<input type="hidden" name="params[orderId]" value="<?=$order->id?>">
					<input type="submit" name="complete" value="Завершить">
					<span>(Осталось <?=$order->timeoutStr?> до автоматического завершения)</span>
				</p>
			</form>
		</div>
	<?}?>
<?}else{?>
	<a href="<?=url('manager/accountAdd')?>">Добавить</a>
<?}?>

<script>
	$(document).ready(function(){
		setInterval(function(){
			location.reload();
		}, <?if($this->isAdmin()){?>600000<?}else{?>90000<?}?>);
	});
</script>
