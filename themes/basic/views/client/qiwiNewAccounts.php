<?
/**
 *
 * @var ClientController $this
 * @var User[] $users
 * @var array $params

 * @var Client $client
 */


$this->title = 'QiwiNew - аккаунты';

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
			$qiwiNewAccount = $user->payeerAccount;
			?>

			<?if($qiwiNewAccount){?>
				<p>Account#<?=$qiwiNewAccount->id?></p>

				<b>
					<?if($qiwiNewAccount->balance_ru > 100){?>
						<font color="red">
							<?=formatAmount($qiwiNewAccount->balance_ru, 0)?>
						</font>
					<?}else{?>
						<font color="green">
							<?=formatAmount($qiwiNewAccount->balance_ru, 0)?>
						</font>
					<?}?>

					руб
				</b>
				<br>
				Дата проверки: <i><?=($qiwiNewAccount->date_check) ? date('d.m.Y H:i', $qiwiNewAccount->date_check) : ''?></i>

			<?}?>

			<p>
				<b>Login</b><br>
				<input type="text" name="params[<?=$user->id?>][login]" value="<?=($params[$user->id]['login']) ? $params[$user->id]['login'] : $qiwiNewAccount->login?>"
			</p>

			<p>
				<b>Pass</b><br>
				<input type="text" name="params[<?=$user->id?>][pass]" value="<?=($params[$user->id]['pass']) ? $params[$user->id]['pass'] : $qiwiNewAccount->pass?>"
			</p>

			<p>
				<b>Browser</b><br>
				<input type="text" name="params[<?=$user->id?>][browser]" value="<?=($params[$user->id]['browser']) ? $params[$user->id]['browser'] : (($qiwiNewAccount->browser) ? $qiwiNewAccount->browser : cfg('defaultPayeerBrowser'))?>"
			</p>

			<p>
				<b>Proxy</b><br>
				<input type="text" name="params[<?=$user->id?>][proxy]" value="<?=($params[$user->id]['proxy']) ? $params[$user->id]['proxy'] : $qiwiNewAccount->proxy?>"
			</p>

			<p>
				<b>Email</b><br>
				<input type="text" name="params[<?=$user->id?>][email]" value="<?=($params[$user->id]['email']) ? $params[$user->id]['email'] : $qiwiNewAccount->email?>"
			</p>

			<p>
				<b>Email Pass</b><br>
				<input type="text" name="params[<?=$user->id?>][email_pass]" value="<?=($params[$user->id]['email_pass']) ? $params[$user->id]['email_pass'] : $qiwiNewAccount->email_pass?>"
			</p>

			<p>
				<b>Секретное слово</b><br>
				<input type="text" name="params[<?=$user->id?>][secret_word]" value="<?=($params[$user->id]['secret_word']) ? $params[$user->id]['secret_word'] : $qiwiNewAccount->secret_word?>"
			</p>

			<p>
				<b>Master key</b><br>
				<input type="text" name="params[<?=$user->id?>][master_key]" value="<?=($params[$user->id]['master_key']) ? $params[$user->id]['master_key'] : $qiwiNewAccount->master_key?>"
			</p>

			<p>
				<b>Super Field</b><br>
				<textarea name="params[<?=$user->id?>][textarea]" cols="30" rows="4"><?=$params[$user->id]['textarea']?></textarea>
			</p>

		</div>

		<br><hr><br><br>

	<?}?>


	<p>
		<input type="submit" name="save" value="Сохранить">
	</p>

</form>




