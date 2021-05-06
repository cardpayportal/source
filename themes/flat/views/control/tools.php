<?
	$this->title = 'Инструменты';
	/**
	 * @var array() $params
	 * @var array() $result
	 */
?>


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
			<table class="table table-bordered table-colored-header">
				<tr>
					<td><strong>Что найти (+79237482732)</strong><br></td>
					<td><strong>Где найти</strong><br></td>
				</tr>

				<tr>
					<td>
						<textarea name="params[findWhat]" cols="55" rows="10" class="form-control"><?=$params['findWhat']?></textarea>
					</td>

					<td>
						<textarea name="params[findWhere]" cols="55" rows="10" class="form-control"><?=$params['findWhere']?></textarea>
					</td>
				</tr>
			</table>
		</p>

		<p>
			<button type="submit" class="btn btn-primary" name="markPhones" value="markPhones">Найти</button>
		</p>
	</form>

</div>
