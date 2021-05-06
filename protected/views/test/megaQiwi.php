<?
/**
 * @var array $params
 * @var string $postData
 */
$this->title = 'Тест MegaQiwi'

?>

<h1><?=$this->title?></h1>


<form method="post" target="_blank">

	<p>
		<b>Post Data</b><br>
		<textarea name="params[postData]" cols="90" rows="10"><?=$postData?></textarea>
	</p>

	<p>
		<b>debug</b><br>
		<input type="checkbox" name="params[debug]">
	</p>

	<p>
		<input type="submit" name="submit" value="Отправить">
	</p>

</form>