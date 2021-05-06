<?
/**
 * @var $params array();
 */
$this->title = 'Тест ApiStore'

?>

<h1><?=$this->title?></h1>


<form method="post" target="_blank">

	<p>
		<b>StoreId</b><br>
		<input type="text" name="params[storeId]" value="<?=$params['storeId']?>">
	</p>

	<p>
		<b>RequestNumber</b><br>
		<input type="text" name="params[requestNumber]" value="<?=$params['requestNumber']?>">
	</p>

	<p>
		<b>Method</b><br>
		<input type="text" name="params[method]" value="<?=$params['method']?>">
	</p>

	<p>
		<b>Params</b><br>
		<textarea name="params[postData]" cols="65" rows="10"><?=$params['postData']?></textarea>
	</p>

	<p>
		<b>Debug</b><br>
		<input type="text" name="params[debug]" value="<?=($params['debug']) ? $params['debug'] : 'true'?>">
	</p>

	<p>
		<input type="submit" name="submit" value="Отправить">
	</p>

</form>