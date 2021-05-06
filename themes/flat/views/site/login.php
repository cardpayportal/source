<?
/**
 * @var SiteController $this
 * @var array $params
 */
$this->layout = 'auth';
$this->title = 'Авторизация';
?>
<h1>
	<a href=""><img src="<?=Yii::app()->theme->baseUrl?>/img/logo-big.png" alt="" class='retina-ready' width="59" height="49">FLAT</a>
</h1>
<div class="login-body">
	<?$this->renderPartial('//layouts/_msg')?>
	<h2>Войти</h2>
	<form action="" method='post' class='form-validate' id="test">
		<div class="form-group">
			<div class="email controls">
				<input type="text" name='params[login]' value="<?=$params['login']?>" placeholder="Логин" class='form-control' data-rule-required="true" data-rule-email="true" id="login" tabindex="1">
			</div>
		</div>
		<div class="form-group">
			<div class="pw controls">
				<input type="password" name="params[pass]" placeholder="Пароль" class='form-control' data-rule-required="true" tabindex="2">
			</div>
		</div>
		<div class="submit">
			<div class="remember">
				<input type="checkbox" name="remember" class='icheck-me' data-skin="square" data-color="blue" id="remember">
				<label for="remember">Запомнить меня</label>
			</div>
			<input type="submit" name="sign_in" value="Войти" class='btn btn-primary' tabindex="3">
		</div>
	</form>
	<div class="forget">
		<a href="<?=url('site/login', ['action'=>'forgotPassword'])?>">
			<span>Забыл пароль?</span>
		</a>
	</div>
</div>

<script>
	$('#login').focus();
</script>
