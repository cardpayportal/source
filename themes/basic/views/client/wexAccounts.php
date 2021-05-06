<?
/**
 *
 * @var ClientController $this
 * @var User[] $users
 * @var array $params
 * @var array $history  выдает историю платежей если запрошено

 * @var Client $client
 */


$this->title = 'WEX - аккаунты';

if($client)
	$this->title .= " {$client->name}";
?>



<h1><?=$this->title?></h1>

<form action="" method="post">

	<?foreach($users as $user){?>

		<div>
			<p>
				<b><?=$user->client->name?> - <?=$user->name?></b>
			</p>

			<?
			$wexAccount = $user->wexAccount;
			?>

			<?if($wexAccount){?>
				<p>Account#<?=$wexAccount->id?></p>

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

					руб
				</b>
				<br>
				Дата проверки: <i><?=($wexAccount->date_check) ? date('d.m.Y H:i', $wexAccount->date_check) : ''?></i>
				<p>
					<button type="button" class="updateWexHistory" value="<?=$wexAccount->id?>">обновить баланс</button>
				</p>

				<?if($history[$wexAccount->id]){?>
					<table class="std padding">

						<?foreach($history[$wexAccount->id] as $trans){?>
							<tr
							<?if($trans['status'] == 'success'){?>
								class="success"
							<?}else{?>
								class="error"
							<?}?>
							>
								<td>#<?=$trans['id']?></td>
								<td><?=$trans['amount']?> <?=$trans['currency']?></td>
								<td><?=date('d.m.Y H:i', $trans['date'])?></td>
							</tr>
						<?}?>

					</table>
				<?}else{?>
					<p>
						<button type="button" class="getWexHistory" value="<?=$wexAccount->id?>">история</button>
					</p>
				<?}?>
			<?}?>

			<p>
				<b>Login</b><br>
				<input type="text" name="params[<?=$user->id?>][login]" value="<?=($params[$user->id]['login']) ? $params[$user->id]['login'] : $wexAccount->login?>"
			</p>

			<p>
				<b>Pass</b><br>
				<input type="text" name="params[<?=$user->id?>][pass]" value="<?=($params[$user->id]['pass']) ? $params[$user->id]['pass'] : $wexAccount->pass?>"
			</p>

			<p>
				<b>Browser</b><br>
				<input type="text" name="params[<?=$user->id?>][browser]" value="<?=($params[$user->id]['browser']) ? $params[$user->id]['browser'] : $wexAccount->browser?>"
			</p>

			<p>
				<b>Proxy</b><br>
				<input type="text" name="params[<?=$user->id?>][proxy]" value="<?=($params[$user->id]['proxy']) ? $params[$user->id]['proxy'] : $wexAccount->proxy?>"
			</p>


			<p>
				<b>Email Pass</b><br>
				<input type="text" name="params[<?=$user->id?>][email_pass]" value="<?=($params[$user->id]['email_pass']) ? $params[$user->id]['email_pass'] : $wexAccount->email_pass?>"
			</p>

		</div>

		<br><hr><br><br>

	<?}?>


	<p>
		<input type="submit" name="save" value="Сохранить">
	</p>

</form>


<form method="post">
	<input type="hidden" name="params[clientId]" value="<?=$client->id?>" />

	<p>
		<input type="submit" name="updateWexHistory" value="обновить балансы аккаунтов">
	</p>

</form>

<script>
	$(document).ready(function(){

		$('.updateWexHistory').click(function () {
			$('#updateWexAccountForm [name*=accountId]').val($(this).attr('value'));
			$('#updateWexAccountForm [type=submit]').click();
		})

		$('.getWexHistory').click(function () {
			$('#getWexHistoryForm [name*=accountId]').val($(this).attr('value'));
			$('#getWexHistoryForm [type=submit]').click();
		})

	});
</script>

<form method="post" style="display: none" id="updateWexAccountForm">
	<input type="hidden" name="params[accountId]" value="" />

	<p>
		<input type="submit" name="updateWexAccount" value="обновить аккаунт">
	</p>
</form>

<form method="post" style="display: none" id="getWexHistoryForm">
	<input type="hidden" name="params[accountId]" value="" />

	<p>
		<input type="submit" name="getWexHistory" value="получить историю">
	</p>
</form>