<?
	$this->title = 'Инструменты';
	/**
	 * @var array() $params
	 * @var array() $result
	 */
?>

<h1 align="center"><?=$this->title?></h1>
<br>

<div>
	<h2>Найти номера телефонов</h2>

	<?if($result['markedText']){?>
		<p><strong>Результат:</strong> найдено <?=$result['replaceCount']?> из <?=$result['findCount']?></p>
		<div style="border: 1px solid black;">
			<?=$result['markedText']?>
		</div>
	<?}?>

	<form method="post">
		<p>
			<table class="std padding invisible">
				<tr>
					<td><strong>Что найти (+79237482732)</strong><br></td>
					<td><strong>Где найти</strong><br></td>
				</tr>

				<tr>
					<td>
						<textarea name="params[findWhat]" cols="55" rows="10"><?=$params['findWhat']?></textarea>
					</td>

					<td>
						<textarea name="params[findWhere]" cols="55" rows="10"><?=$params['findWhere']?></textarea>
					</td>
				</tr>
			</table>

		</p>

		<p>
			<input type="submit" name="markPhones" value="Найти">
		</p>
	</form>

</div>
