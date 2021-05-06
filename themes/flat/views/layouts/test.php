<div class="box">
	<div class="box-title">
		<form method="post">
			<h3>
				<i class="fa fa-bars"></i>
				Заявка #<?=$order->id?>

				<span style="margin-left: 50px">
					принято: <?=formatAmount($order->amountIn)?> из <?=formatAmount($order->amount_add)?>
					<input type="hidden" name="params[orderId]" value="<?=$order->id?>">
					<button type="submit" name="setPriorityNow" class="btn" value="Проверить все">
								<i class="fa fa-refresh"></i>Проверить все
							</button>

							<span title="заявка будет автоматически завершена">(Осталось <?=$order->timeoutStr?>)</span>
				</span>
			</h3>
		</form>
	</div>
	<div class="box-content">
		<table class="table table-nomargin table-bordered table-colored-header">
			<thead>
			<tr>
				<th>Кошелек</th>
				<th>Принято</th>
				<th>Проверка</th>
				<th>Баланс</th>
				<th>Сообщение</th>
			</tr>
			</thead>
			<tbody>
			<?foreach($order->orderAccounts as $orderAccount){?>
				<tr>
					<td>
						<strong><?=$orderAccount->account->login?></strong>
						<?if($orderAccount->account->check_priority >= Account::PRIORITY_NOW){?>
							<br>проверяется
						<?}else{?>
							<form method="post">
								<input type="hidden" name="params[accountId]" value="<?=$orderAccount->account_id?>" />
								<button type="submit" name="setPriorityNow" class="btn btn-mini" value="проверить">
									<i class="fa fa-refresh"></i>проверить
								</button>
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
							<table data-id="<?=$orderAccount->account->id?>" class="table table-nomargin table-condensed" style="margin-left: 10px;">

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
								<button type="button" class="btn btn-info btn-mini showTransactions">
									<i class="fa fa-caret-down"></i>показать все
								</button>

								<button type="button" class="btn btn-info btn-mini hideTransactions" style="display: none">
									<i class="fa fa-caret-up"></i>скрыть
								</button>
							<?}?>
						</td>
					</tr>
				<?}?>

			<?}?>
			</tbody>
		</table>

		<form method="post">
			<p>
				<input type="hidden" name="params[orderId]" value="<?=$order->id?>">
				<button type="submit" name="complete" class="btn btn-primary" value="завершить">
					<i class="fa fa-check"></i>завершить
				</button>
				(Осталось <?=$order->timeoutStr?> до автоматического завершения)
			</p>
		</form>
	</div>
</div>