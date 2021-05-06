<?
/**
 * @var UserController $this
 * @var array $params
 * @var User $user
 */

$this->title = 'Профиль';

?>

<h1><?=$this->title?></h1>


<form method="post">
	<p>
		<b>Jabber для уведомлений</b><br>
		<input type="text" name="params[jabber]" value="<?=$params['jabber']?>"/>
		<input type="checkbox" name="params[send_notifications]" value="1"
			<?if($params['send_notifications']){?>
				checked="checked"
			<?}?>
		>
		<span class="dotted"
			  title="на указанный Jabber будут приходить уведомления о блокировке кошельков и перелимите">
			Вкл уведомления
		</span>
	</p>

	<p>
		<b>Тема оформления сайта</b><br>
		<select name="params[theme]" id="textfield2">
			<?foreach(cfg('themeArr') as $key=>$name){?>
				<option value="<?=$key?>"
					<?= ($key == $params['theme']) ? 'selected="selected"' : ''?> >
					<?=$name?>
				</option>
			<?}?>
		</select>
	</p>

	<p>
		<input type="submit"  name="save" value="Сохранить"/>
	</p>
</form>

<hr>

<h2>API</h2>

<form method="post">
	<p>
		<b>KEY: </b><?=$params['apiKey']?>
		<br>
		<b>SECRET: </b><?=$params['apiSecret']?>
	</p>

	<p>
		<input type="submit" name="changeApi" value="<?=($params['apiKey']) ? 'Сменить ключи' : 'Получить ключи'?>">
	</p>
</form>

<hr>

<h2>Настройка уведомлений</h2>

<form method="post">
	<p>
		<b>Url для получения уведомлений об оплате: </b><?=$user->url_result?><br>
		<input type="text" size="80" name="params[urlResult]" value="<?=$params['urlResult']?>"
	</p>
	<p>
		<input type="submit" name="saveUrl" value="Сохранить">&nbsp;&nbsp;
		<input type="submit" name="clearUrl" value="Очистить">
	</p>
</form>
