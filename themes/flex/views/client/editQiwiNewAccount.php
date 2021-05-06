<?php
/**
 * @var PayeerAccount $qiwiNewAccount
 * @var User $user
 * @var array $params
 */

$this->title = 'Редактирование аккаунта Qiwi New '.$user->name
?>


<form action="" method="post" name="editQiwiNewAccount">


	<?if($qiwiNewAccount){?>
		<p>
			<b><?=$user->name?></b>
		</p>

		<p>Account#<?=$qiwiNewAccount->id?></p>

		<p>
			<b>Login *</b><br>
			<input type="text" name="params[<?=$user->id?>][login]" value="<?=($params[$user->id]['login']) ? $params[$user->id]['login'] : $qiwiNewAccount->login?>"
		</p>

		<p>
			<b>Pass *</b><br>
			<input type="text" name="params[<?=$user->id?>][pass]" value="<?=($params[$user->id]['pass']) ? $params[$user->id]['pass'] : $qiwiNewAccount->pass?>"
		</p>

		<p>
			<b>Browser *</b><br>
			<input type="text" name="params[<?=$user->id?>][browser]" value="<?=($params[$user->id]['browser']) ? $params[$user->id]['browser'] : $qiwiNewAccount->browser?>"
		</p>

		<p>
			<b>Proxy *</b><br>
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

		<?if($qiwiNewAccount->api_id){?>
		<p>
			<b>Api ID</b><br>
			<input type="text" name="params[<?=$user->id?>][api_id]" value="<?=($params[$user->id]['api_id']) ? $params[$user->id]['api_id'] : $qiwiNewAccount->api_id?>"
		</p>

		<p>
			<b>Api Secret Key</b><br>
			<input type="text" name="params[<?=$user->id?>][api_secret_key]" value="<?=($params[$user->id]['api_secret_key']) ? $params[$user->id]['api_secret_key'] : $qiwiNewAccount->api_secret_key?>"
		</p>
		<?}else{?>
			<br>
			<p>
				<button type="button" class="createApiParams">Создать параметры API</button>
			</p>
		<?}?>
		<p>
			<b>Номер для смс</b><br>
			<input type="text" name="params[<?=$user->id?>][sms_phone]" value="<?=($params[$user->id]['sms_phone']) ? $params[$user->id]['sms_phone'] : $qiwiNewAccount->sms_phone?>"
		</p>
		<p>
			<b>Дата окончания аренды номера (формат: 28.08.18 00:34)</b><br>
			<input type="text" name="params[<?=$user->id?>][sms_phone_expire]" value="<?=($params[$user->id]['sms_phone_expire']) ? $params[$user->id]['sms_phone_expire'] : $qiwiNewAccount->getSmsPhoneExpireStr()?>"
		</p>
		<br>

		<p>
			<input type="submit" name="save" value="Сохранить">
		</p>
	<?}?>
</form>
<br><hr><br>



<script>
	$(document).ready(function(){
		$('.createApiParams').click(function () {
			$('#createApiParamsForm [type=submit]').click();
		})
	});
</script>


<form method="post" style="display: none" id="createApiParamsForm">
	<p>
		<input type="submit" name="createApiParams" value="Создать параметры API">
	</p>
</form>




