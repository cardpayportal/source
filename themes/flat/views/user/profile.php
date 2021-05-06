<?
/**
 * @var UserController $this
 * @var array $params
 */

$this->title = 'Профиль';

?>
<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-user"></i>настройка профиля</h3>
	</div>
	<div class="box-content">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<label for="textfield1" class="control-label">Jabber для уведомлений</label>
				<input type="text" name="params[jabber]" value="<?=$params['jabber']?>" id="textfield1" class="form-control"/>
				<br>
				<input type="checkbox" name="params[send_notifications]"
					   value="1"
					<?if($params['send_notifications']){?>
						checked="checked"
					<?}?>>
				<span class="dotted"
					title="на указанный Jabber будут приходить уведомления о блокировке кошельков и перелимите">
					Вкл уведомления
				</span>
			</div>

			<div class="form-group">
				<label for="textfield2" class="control-label">Тема</label>
				<select name="params[theme]" class="form-control" id="textfield2">
					<?foreach(cfg('themeArr') as $key=>$name){?>
						<option value="<?=$key?>"
						<?= ($key == $params['theme']) ? 'selected="selected"' : ''?> >
							<?=$name?>
						</option>
					<?}?>
				</select>
				<span class="help-block">выбор темы оформления сайта</span>
			</div>
			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="save" value="Сохранить">Сохранить</button>
				<a href="<?=url(cfg('index_page'))?>"><button type="button" class="btn">Отмена</button></a>
			</div>
		</form>
	</div>

	<div class="box-title">
		<h3><i class="fa fa-user"></i>API</h3>
	</div>
	<div class="box-content">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<p>
					<b>KEY: </b><?=$params['apiKey']?>
					<br>
					<b>SECRET: </b><?=$params['apiSecret']?>
				</p>
			</div>
			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="changeApi"
						value="true"><?=($params['apiKey']) ? 'Сменить ключи' : 'Получить ключи'?></button>
			</div>
		</form>
	</div>

	<div class="box-title">
		<h3><i class="fa fa-user"></i>Настройка ссылок</h3>
	</div>
	<div class="box-content">
		<h2>Настройка уведомлений</h2>
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<p>
					<b>Url для получения уведомлений об оплате: </b><?=$user->url_result?><br>
					<input type="text" size="80" name="params[urlResult]" value="<?=$params['urlResult']?>"
				</p>
			</div>
			<div class="form-actions">
				<p>
					<input type="submit" name="saveUrl" class="btn btn-primary" value="Сохранить">&nbsp;&nbsp;
					<input type="submit" name="clearUrl" value="Очистить">
				</p>
			</div>
		</form>
	</div>
</div>