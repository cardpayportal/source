<?
/**
 * @var TestController $this
 * @var string $postJson
 * @var array $response
 * @var array $methods
 */

$this->title = 'Тест EcommApi';
?>

<h1><?=$this->title?></h1>


<form method="post">

	<?if($response){?>
		<p>Ответ:</p>
		<div>
			<code>
				<?=$response?>
			</code>
		</div>
	<?}?>

	<p>
		<b>Методы</b><br>
		<?foreach($methods as $method){?>
			<?=$method?><br>
		<?}?>
	</p>

	<p>
		<b>Params</b><br>
		<textarea name="postJson" cols="95" rows="10"><?=$postJson?></textarea>
	</p>

	<p>
		<input type="submit" name="submit" value="Отправить">
	</p>

</form>