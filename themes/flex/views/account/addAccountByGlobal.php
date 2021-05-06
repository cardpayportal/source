<?php
 $title = 'Добавление коша киви'
?>

<h1>Добавление входящего коша киви</h1>
<br><hr>

<form method="post" action="">
	<strong>Client *</strong><br>
	<p>
		<label>
			<select name="params[clientId]">
				<?foreach(Client::getArr() as $id=>$name){?>
					<option value="<?=$id?>"
						<?if($params['clientId']==$id){?>
							selected="selected"
						<?}?>
					><b><?=$name?></b> (id=<?=$id?>)</option>
				<?}?>
			</select>
		</label>
	</p>
	<strong>Номер (+7...) *</strong>
	<p>
		<input type="text" name="params[login]" value="<?=$params['login']?>"/>
	</p>
	<strong>Пароль *</strong>
	<p>
		<input type="text" name="params[pass]" value="<?=$params['pass']?>"/>
	</p>
	<strong>Токен (API token) *</strong>
	<p>
		<input type="text" name="params[token]" size="40" value="<?=$params['token']?>"/>
	</p>
	<strong>Прокси (формат: login:pass@IP:PORT) *</strong>
	<p>
		<input type="text" name="params[proxy]" size="40" value="<?=$params['proxy']?>"/>
	</p>
	<strong>Тип (аноним, half - част идент и full - фул идент) если неизвестно, оставить пустым</strong>
	<p>
		<label>
			<select name="params[status]">
				<option value=""></option>
				<?foreach(Account::statusArr() as $id=>$arr){?>
					<option value="<?=$id?>" <?if($params['status']==$id){?>selected="selected"<?}?>><?=$id?></option>
				<?}?>
			</select>
		</label>
	</p>
	<p>
		<input type="hidden" name="params[withOutCheck]" value="1"/>
	</p>
	<br>
	<p>
		<input type="submit" name="addAccount" value="Сохранить"/>
	</p>
</form>
<br><br>