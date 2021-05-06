<?php
/**
 * @var WexAccount $wexAccount
 * @var User $user
 */

$this->title = 'Управление аккаунтом Yandex '.$user->name
?>


<form action="" method="post">


	<?if($wexAccount){?>
		<p>
			<b><?=$user->name?></b>
		</p>

		<p>Account#<?=$wexAccount->id?></p>

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

		<br>

		<p>
			<input type="submit" name="save" value="Сохранить">
		</p>
	<?}?>
</form>
<br><hr><br>

<form action="" method="post" name="buyBtcRur">
	<?if($wexAccount){?>
		<p>
			<input type="submit" name="buyBtcRur" value="Обменять рубли на BTC">
		</p>
	<?}?>
</form>

<form action="" method="post" name="withdrawBtc">
	<?if($wexAccount){?>
		<p>
			<b>Куда вывести</b><br>
			<input type="text" name="params[<?=$user->id?>][address]" value="<?=($params[$user->id]['address'])?>">

			<input type="submit" name="withdrawBtc" value="Вывести BTC">
		</p>
	<?}?>
</form>

<form action="" method="post" name="сonfirmPaymentTutanota">
	<?if($wexAccount){?>
		<p>
			<input type="submit" name="сonfirmPaymentTutanota" value="Подтвердить вывод">
		</p>
	<?}?>
</form>