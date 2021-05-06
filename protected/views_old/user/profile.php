<?
/**
 * @var UserController $this
 * @var array $params
 */

$this->title = 'Профиль';

?>

<h1><?=$this->title?></h1>


<form method="post">
	<p>
		<b>Jabber для уведомлений</b><br>
		<input type="text" name="params[jabber]" value="<?=$params['jabber']?>"/>
	</p>

	<p>
		<input type="submit"  name="submit" value="Сохранить"/>
	</p>
</form>