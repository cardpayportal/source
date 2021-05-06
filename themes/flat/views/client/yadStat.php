<?php
/**
 * @var array $clientsYadArr
 * @var float $totalAmountRu
 * @var float $totalAmountBtc
 */

$this->title = 'Статистика Yandex'
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

		<?if($totalAmountBtc > 0.00001){?>
			<font color="red">
				<?=formatAmount($totalAmountBtc, 4)?>
			</font>
		<?}else{?>
			<font color="green">
				<?=formatAmount($totalAmountBtc, 4)?>
			</font>
		<?}?> btc

	</strong>
</p>

<table class="std padding">

		<tr>
			<td>Clients</td>
			<td>Users</td>
			<td>Баланс Yad</td>
			<td><nobr>Дата проверки</nobr></td>
			<td>История</td>
			<td>Действие</td>
			<td></td>
			<td></td>
		</tr>
		<?foreach($clientsYadArr as $info){?>
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

					<?if($info['clientAmountBtc'] > 0.00001){?>
						<font color="red">
							<?=formatAmount($info['clientAmountBtc'], 4)?>
						</font>
					<?}else{?>
						<font color="green">
							<?=formatAmount($info['clientAmountBtc'], 4)?>
						</font>
					<?}?>

					btc
				</b>
			</td>
		</tr>
		<?foreach($info['users'] as $user){?>
		<tr>
				<?$wexAccount = $user->wexAccount?>
				<td><?=$user->name?></td>
				<td>
					<b>
						<?if($wexAccount->balance_ru > 100){?>
							<font color="red">
								<?=formatAmount($wexAccount->balance_ru, 0)?>
							</font>
						<?}else{?>
							<font color="green">
								<?=formatAmount($wexAccount->balance_ru, 0)?>
							</font>
						<?}?>

						руб<br>

						<?if($wexAccount->balance_btc > 0.00001){?>
							<font color="red">
								<?=formatAmount($wexAccount->balance_btc, 4)?>
							</font>
						<?}else{?>
							<font color="green">
								<?=formatAmount($wexAccount->balance_btc, 4)?>
							</font>
						<?}?>

						btc
					</b>
				</td>
				<td>
					<i><?=($wexAccount->date_check) ? date('d.m.Y H:i', $wexAccount->date_check) : ''?></i>
				</td>
				<td>
					<a href="<?=url('client/yandexHistoryAdmin', ['wexAccountId'=>$wexAccount->id, 'user'=>$user->name])?>" target="_blank">Перейти</a>
				</td>
				<td>
					<button type="button" class="updateWexHistory" value="<?=$wexAccount->id?>">обновить баланс</button>
				</td>
				<td>
					<a href="<?=url('client/editWexAccount', ['userId'=>$user->id,'wexAccountId'=>$wexAccount->id])?>" target="_blank"><button type="button" value="<?=$wexAccount->id?>">Управление</button></a>
				</td>
				<td>
					<a href="<?=url('manager/YandexPayGlobalFin', ['userId'=>$user->id])?>" target="_blank"><button type="button" value="<?=$wexAccount->id?>">Войти в акк</button></a>
				</td>
		</tr>
		<?}?>
<?}?>
</table>

<script>
	$(document).ready(function(){

		$('.updateWexHistory').click(function () {
			$('#updateWexAccountForm [name*=accountId]').val($(this).attr('value'));
			$('#updateWexAccountForm [type=submit]').click();
		})

	});
</script>

<form method="post" style="display: none" id="updateWexAccountForm">
	<input type="hidden" name="params[accountId]" value="" />

	<p>
		<input type="submit" name="updateWexAccount" value="обновить аккаунт">
	</p>
</form>

<form action="" method="post">
	<p>
		<label>
			<strong>Указать кошелек для New Yandex</strong><br/>
			<br>
			<input type="text" name="params[yandexWallet]" value="<?=$newYandexPayWallet?>">
		</label>
	</p>

	<p>
		<input type="submit" name="setYandexNewWallet" value="Сохранить кошелек"/>
	</p>

</form>