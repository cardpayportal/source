<?
/**
 * @var $params array();
 */
$this->title = 'Тест ApiManager'

?>

<h1><?=$this->title?></h1>


<form method="post" target="_blank">

	<p>
		<b>Post Data</b><br>
		<textarea name="params[postData]" cols="90" rows="10"><?=$params['postData']?></textarea>
	</p>

	<p>
		<b>Debug</b><br>
		<input type="checkbox" name="params[debug]" value="true" <?=($params['debug']) ? 'checked="checked"': ''?> />
	</p>

	<p>
		<input type="submit" name="submit" value="Отправить">
	</p>

</form>