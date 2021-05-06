<?
	/**
	 * @var Email[] $models
	 * @var int $allCount
	 * @var int $freeCount
	 * @var int $workCount
	 * @var int $notCheckCount
	 * @var array $params

	 */

	$this->title = 'Email';
?>


<h1><?=$this->title?></h1>

<?if($models){?>
	<p>
		All count: <?=$allCount?><br>
		Free count: <?=$freeCount?><br>
		Work count: <?=$workCount?><br>
		Not check count: <?=$notCheckCount?><br>
	</p>

	<table class="std padding">
		<tr>
			<td>ID</td>
			<td>Email</td>
			<td>DateCheck</td>
			<td>DateAdd</td>
			<td>Error</td>
		</tr>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><?=$model->email?></td>
				<td><?=$model->dateCheckStr?></td>
				<td><?=$model->dateAddStr?></td>
				<td><font color="red"><?=$model->error?></font></td>
			</tr>
		<?}?>

	</table>
<?}else{?>
	email не найдено
<?}?>


<h2>Добавить</h2>
<form method="post">

	<p>
		<textarea name="params[emails]" rows="20" cols="55"><?=$params['emails']?></textarea>
	</p>

	<p>
		<input type="submit" name="add" value="Добавить">
	</p>

</form>