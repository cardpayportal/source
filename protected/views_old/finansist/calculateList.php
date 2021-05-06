<?
/**
 * @var ClientCalc $models
 */

$this->title = 'Расчеты';

?>

<h1><?=$this->title?></h1>


<?if($models){?>
	<table class="std padding">
		<tr>
			<td>ID</td>
			<td>Сумма руб</td>
			<td>Сумма USD</td>
			<td>Примечание</td>
			<td>Добавлен</td>
		</tr>

		<?
		//для вывода кнопки удаления у последнего расчета клиента
		$uniqueClients = array();
		?>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><?=$model->amountRubStr?></td>
				<td><?=$model->amountUsdStr?></td>
				<td><?=$model->commentShort?></td>
				<td><?=$model->dateAddStr?></td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	расчетов не найдено
<?}?>
