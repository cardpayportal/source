<?php
/**
 * @var array $history
 * @var string $user
 * @var string $wexAccountId
 * @var string $yandex
 * @var string $exchange
 * @var string $out
 * @var string $error
 * @var WexAccount $account
 */
$this->title = 'История Яндекс '.$user;
?>

<p>
	<h2><?=$user?></h2>
</p>

<b><nobr>
		<?if($account->balance_ru > 100){?>
			<font color="red">
				<?=formatAmount($account->balance_ru, 0)?>
			</font>
		<?}else{?>
			<font color="green">
				<?=formatAmount($account->balance_ru, 0)?>
			</font>
		<?}?>

		руб &nbsp

		<?if($account->balance_btc > 0.00001){?>
			<font color="red">
				<?=formatAmount($account->balance_btc, 4)?>
			</font>
		<?}else{?>
			<font color="green">
				<?=formatAmount($account->balance_btc, 4)?>
			</font>
		<?}?>

		BTC &nbsp

		<?if($account->balance_zec > 0.00001){?>
			<font color="red">
				<?=formatAmount($account->balance_zec, 5)?>
			</font>
		<?}else{?>
			<font color="green">
				<?=formatAmount($account->balance_zec, 5)?>
			</font>
		<?}?>

		ZEC &nbsp

		<?if($account->balance_usdt > 0.01){?>
			<font color="red">
				<?=formatAmount($account->balance_usdt, 2)?>
			</font>
		<?}else{?>
			<font color="green">
				<?=formatAmount($account->balance_usdt, 2)?>
			</font>
		<?}?>

		USDT</nobr>

		<?if($account->balance_usd > 0.01){?>
			<font color="red">
				<?=formatAmount($account->balance_usd, 2)?>
			</font>
		<?}else{?>
			<font color="green">
				<?=formatAmount($account->balance_usd, 2)?>
			</font>
		<?}?>

		USD</nobr>
</b>
&nbsp&nbsp
<button type="button" class="updateWexHistory" value="<?=$account->id?>"><nobr>обновить</nobr></button>
<br><br>Дата проверки: <i><?=($account->date_check) ? date('d.m.Y H:i', $account->date_check) : ''?></i><br><br>
<strong>Платежей на текущей странице (<?=count($history)?>)</strong><br>

<?if($history){?>
	<div>
		<div style="width: 25%; float:left">
			<fieldset>
				<legend class="legend">Выберите тип отображаемых платежей</legend>
				<form method="post" action="" name="filter">
					<div>
						<input type="checkbox" id="yandex" name="yandex"
							   value="yandex" <?if($yandex){?>checked="checked"<?}?> />
						<label for="yandex">Яндекс</label>
					</div>

					<div>
						<input type="checkbox" id="exchange" name="exchange"
							   value="exchange" checked="checked" />
						<label for="exchange">Обмен</label>
					</div>

					<div>
						<input type="checkbox" id="out" name="out"
							   value="out"  checked="checked" />
						<label for="out">Вывод</label>
					</div>

					<div>
						<input type="checkbox" id="error" name="error"
							   value="error"  checked="checked" />
						<label for="error">Неподтвержденный вывод</label>
					</div>
					<br>
					<input type="submit" name="show" value="Отобразить">
				</form>
			</fieldset>
		</div>
		<div style="width: 5%; float:right;">
		</div>
		<div style="width: 35%; float:right;">
			<form action="" method="post" name="buyBtcRu">
				<?if($account){?>
					<p>
						<input type="submit" name="buyBtcRu" value="Обменять рубли на BTC">
					</p>
				<?}?>
			</form>

			<form action="" method="post" name="withdrawBtc">
				<?if($account){?>
					<p>
						<b>Куда вывести</b><br>
						<input type="text" name="params[<?=$account->user_id?>][address]" value="<?=($params[$account->user_id]['address'])?>">

						<input type="submit" name="withdrawBtc" value="Вывести BTC">
					</p>
				<?}?>
			</form>

			<form action="" method="post" name="confirmPaymentTutanota">
				<?if($account){?>
					<p>
						<input type="submit" name="confirmPaymentTutanota" value="Подтвердить вывод">
					</p>
				<?}?>
			</form>
		</div>
		<div style="width: 35%; float:right">
			<form action="" method="post" name="buyZecForm">
				<?if($account){?>
					<p>
						<input type="submit" name="buyZec" value="Обменять рубли и USD на ZEC">
					</p>
				<?}?>
			</form>

			<form action="" method="post" name="withdrawZecForm">
				<?if($account){?>
					<p>
						<b>Куда вывести</b><br>
						<input type="text" name="params[<?=$account->user_id?>][address]" value="<?=($params[$account->user_id]['address'])?>">

						<input type="submit" name="withdrawZec" value="Вывести ZEC">
					</p>
				<?}?>
			</form>

			<form action="" method="post" name="confirmPaymentTutanota">
				<?if($account){?>
					<p>
						<input type="submit" name="confirmPaymentTutanota" value="Подтвердить вывод">
					</p>
				<?}?>
			</form>
		</div>
	</div>

	<br><br><br><br><br><br><br><br><br><br>

	<form action="" method="post" name="buyUsdtForm">
		<?if($account){?>
			<p>
				<input type="submit" name="buyUsdt" value="Обменять рубли и USD на USDT">
			</p>
		<?}?>
	</form>

	<form action="" method="post" name="withdrawUsdtForm">
		<?if($account){?>
			<p>
				<b>Куда вывести</b><br>
				<input type="text" name="params[<?=$account->user_id?>][address]" value="<?=($params[$account->user_id]['address'])?>">

				<input type="submit" name="withdrawUsdt" value="Вывести USDT">
			</p>
		<?}?>
	</form>

	<form action="" method="post" name="confirmPaymentTutanota">
		<?if($account){?>
			<p>
				<input type="submit" name="confirmPaymentTutanota" value="Подтвердить вывод">
			</p>
		<?}?>
	</form>

	<div>
		<strong>страница <?=$pageNum?></strong>
		<br>
		<div class="navigation">
			<?for($i = 1; $i <= $history[0]['pageCount']; $i++){?>
				<a href="<?=url('client/yandexHistoryAdmin', ['wexAccountId'=>$wexAccountId, 'user'=>$user, 'pageNum'=>$i])?>"><?=$i?></a>&nbsp
			<?}?>
		</div>
		<br>
		<table class="std padding">

			<?foreach($history as $trans){?>
				<!--если установлен фильтр на яндекс платежи-->
				<?if(($yandex && $trans['comment'] == 'Payment from Yandex.Money') ||
					//если установлен фильтр на обмен
					($exchange && $trans['type'] == 'Расход') ||
					//если установлен фильтр на вывод
					($out && $trans['type'] == 'Вывод' && $trans['status'] == 'Завершено') ||
					//если установлен фильтр на неподтвержденный вывод
					($error && $trans['type'] == 'Вывод' && $trans['status'] == 'Не подтверждено')
				){?>
					<tr
						<?if($trans['type'] == 'Вывод' && $trans['status'] == 'Не подтверждено'){?>
							class="error"
						<?}elseif($trans['type'] == 'Вывод' && $trans['status'] == 'Завершено'){?>
							class="new"
						<?}elseif($trans['type'] == 'Расход'){?>
							class="wait"
						<?}else{?>
							class="success"
						<?}?>
					>
						<td>#<?=$trans['id']?></td>
						<td>
							<?if($trans['comment'] == 'Payment from Yandex.Money'){?>
								<?=YandexPay::getAmountWithFee($trans['amount'])?>
							<?}else{?>
								<?=$trans['amount']?>
							<?}?>
							<?=$trans['currency']?>
						</td>
						<td><?=$trans['type']?></td>
						<td><?=date('d.m.Y H:i', $trans['date'])?></td>
						<td>
							<?if($trans['type'] == 'Вывод' && $trans['status'] == 'Не подтверждено'){?>
								<?=str_replace([
									'Отменить',
									'Прислать письмо еще раз',
									'|',
								],
									[
										'<form method="post" action="" name="cancelWithdraw'.$wexAccountId.'"><input type="hidden" name="params[transactionId]" value="'.$trans["id"].'"/><input type="submit" name="cancel" value="Отменить"></form>',
										'<form method="post" action="" name="resendWithdraw'.$wexAccountId.'"><input type="hidden" name="params[transactionId]" value="'.$trans["id"].'"/><input type="submit" name="resend" value="Прислать письмо еще раз"></form>',
										' ',
									],
									$trans['comment']
								);?>
							<?}else{?>
								<?=$trans['comment']?>
							<?}?>
							<?if($trans['txid']){?>
								<br>
								<span class="shortContent"><button><nobr>Copy TXID</nobr></button></span>
								<input style="display: none" type="text" size="30" value="<?=$trans['txid']?>" class="click2select fullContent">
							<?}?>
						</td>
					</tr>
				<?}?>
			<?}?>

		</table>
		<?}else{?>
			нет платежей
		<?}?>

		<form method="post" style="display: none" id="updateWexAccountForm">
			<input type="hidden" name="params[accountId]" value="" />

			<p>
				<input type="submit" name="updateWexAccount" value="обновить аккаунт">
			</p>
		</form>
	</div>


<script>

	$(document).ready(function(){

		$('.updateWexHistory').click(function () {
			$('#updateWexAccountForm [name*=accountId]').val($(this).attr('value'));
			$('#updateWexAccountForm [type=submit]').click();
		})

	});
</script>

