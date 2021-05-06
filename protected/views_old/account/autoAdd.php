<?
/**
 * @var string $content
 */
$this->title = 'Авто-добавление';
?>

<h1><?=$this->title?></h1>

<form method="post">

	<p>
		<input type="submit" name="edit" value="Сохранить">
	</p>

	<p>
		<b>Количество: <?=Account::autoAddCount()?></b><br>
		<textarea name="params[content]" rows="30" cols="45"><?=$content?></textarea>
	</p>

</form>

<script>
	$(document).ready(function(){
		setInterval(function(){
			location.reload();
		}, 600000);<?//автообновление страницы каждые 10мин (чтобы ктото из админов не затер добавленные другим кошельки)?>
	});
</script>
