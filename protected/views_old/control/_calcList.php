<?
/**
 * @var ControlController $this
 * @var ClientCalc[] $models
 */
?>

<table class="std padding">
	<tr>
		<td>ID</td>
		<td>Клиент</td>
		<td>Сумма руб</td>
		<td>Сумма USD</td>
		<td>Примечание</td>
		<td>Добавлен</td>
		<td>Автор</td>
		<td>Долг</td>
		<td>Приход</td>
		<td>Действие</td>
	</tr>

	<?
	//для вывода кнопки удаления у последнего расчета клиента
	$uniqueClients = array();
	?>

	<?foreach($models as $model){?>
		<tr
		<?if($model->is_control){?>
			class="selected"
			title="контрольный"
		<?}?>
		>
			<td><?=$model->id?></td>
			<td><?=$model->client->name?></td>
			<td><?=$model->amountRubStr?></td>
			<td><?=$model->amountUsdStr?></td>
			<td><?=$model->comment?></td>
			<td><?=$model->dateAddStr?></td>
			<td><?=$model->user->name?></td>
			<td><?=$model->debtRubStr?> руб</td>
			<td><?=$model->statsInStr?></td>
			<td>
				<?if(in_array($model->client->id, $uniqueClients)===false){//удалить только последний расчет?>
					<?
					$uniqueClients[] = $model->client->id;
					?>
					<a href="<?=url('control/CalculateClientList', array('clientId'=>$model->client->id, 'deleteLastCalc'=>'true'))?>">удалить</a>
				<?}?>
			</td>
		</tr>
	<?}?>
</table>