<?php
/**
 * @var array $clientsQiwiNewArr
 * @var float $totalAmountRu
 */

$this->title = 'Статистика Qiwi New'
?>

<p>
	<strong>Общий баланс клиентов: <?if($totalAmountRu > 100){?>
			<font color="red">
				<?=formatAmount($totalAmountRu, 0)?>
			</font>
		<?}else{?>
			<font color="green">
				<?=formatAmount($totalAmountRu, 0)?>
			</font>
		<?}?> руб

		<br><br>

		<form method="post" name="walletForWithdrawForm" action="">
			<input type="text" size="12" name="params[walletForWithdraw]" value="<?=config('walletForWithdraw')?>" />

			<input type="submit" name="setWalletForWithdraw" value="Установить кош для вывода">
		</form>

	</strong>
</p>

<table class="std padding">

	<tr>
		<td>Clients</td>
		<td>Users</td>
		<td>Баланс Qiwi</td>
		<td><nobr>Дата проверки</nobr></td>
		<td>История</td>
		<td>Действие</td>
		<td></td>

	</tr>
	<?foreach($clientsQiwiNewArr as $info){?>
		<tr style="border: 2px solid black;">
			<td rowspan="<?=count($info['users'])+1?>">
				<b><?=$info['client']->name?></b>
				<br>(ClientId<?=$info['client']->id?>)

				<?if($info['client']->description){?>
					<br><i><?=$info['client']->descriptionStr?></i>
				<?}?>
				<br><br>
				<b>
					Всего:
					<br>
					<?if($info['clientAmountRu'] > 100){?>
						<font color="red">
							<?=formatAmount($info['clientAmountRu'], 0)?>
						</font>
					<?}else{?>
						<font color="green">
							<?=formatAmount($info['clientAmountRu'], 0)?>
						</font>
					<?}?>

					руб<br>

				</b>
			</td>
		</tr>
		<?foreach($info['users'] as $user){?>
			<tr>
				<?$payeerAccount = $user->payeerAccount?>
				<td><?=$user->name?></td>
				<td>
					<b><nobr>
						<?if($payeerAccount->balance_ru > 100){?>
							<font color="red">
								<?=formatAmount($payeerAccount->balance_ru, 0)?>
							</font>
						<?}else{?>
							<font color="green">
								<?=formatAmount($payeerAccount->balance_ru, 0)?>
							</font>
						<?}?>

						руб</nobr><br><nobr>
					</b>
				</td>
				<td>
					<i><?=($payeerAccount->date_check) ? date('d.m.Y H:i', $payeerAccount->date_check) : ''?></i>
				</td>
				<td>
					<a href="<?=url('client/qiwiNewHistoryAdmin', ['qiwiNewAccountId'=>$payeerAccount->id, 'user'=>$user->name])?>" target="_blank"><button>Перейти</button></a>
				</td>
				<td>
					<a href="<?=url('client/editQiwiNewAccount', ['userId'=>$user->id,'qiwiNewAccountId'=>$payeerAccount->id])?>" target="_blank"><button type="button" title="Редактировать" value="<?=$payeerAccount->id?>">Edit</button></a>
				</td>
				<td>
					<button type="button" class="withdraw" value="<?=$payeerAccount->id?>">Вывести</button>
				</td>

			</tr>
		<?}?>
	<?}?>
</table>
<br><br>

<form method="post" action="" name="seleniumControl">
	<input type="submit" name="rebootSelenium" value="Перезагрузить Selenium"/>
</form>

<script>
	$(document).ready(function(){

		$('.withdraw').click(function () {
			$('#withdrawForm [name*=accountId]').val($(this).attr('value'));
			$('#withdrawForm [type=submit]').click();
		})

	});
</script>

<form method="post" style="display: none" id="withdrawForm">
	<input type="hidden" name="params[accountId]" value="" />

	<p>
		<input type="submit" name="withdraw" value="Вывести">
	</p>
</form>


