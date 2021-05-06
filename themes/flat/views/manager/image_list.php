<?php

$this->title = 'Список изображений с координатами';
?>

<?if($models){?>

<table class="std padding">
	<tr>
		<td>ID</td>
		<td>Название банка</td>
		<td>Координаты sms кода</td>
		<td>Координаты кнопки</td>
		<td>Статус</td>
		<td>Действие</td>
	</tr>

	<?foreach($models as $model){?>
	<tr>
		<td><?=$model->id?></td>
		<td><?=$model->bank_name?></td>
		<td><?=$model->sms_input_pos?></td>
		<td><?=$model->button_pos?></td>
		<td><?=$model->status?></td>
		<td>
			<a href="<?=url('manager/imagePosition', ['imageId'=>$model->id])?>" target="_blank"><button type="button" title="Редактировать" value="<?=$model->id?>">Редактировать</button></a>
		</td>
		<td>
			<a href="<?=url('manager/imageList', ['imageId'=>$model->id, 'delete'=>true])?>"><button type="button" title="Удалить" value="<?=$model->id?>">Удалить</button></a>
		</td>
	</tr>
	<?}?>
</table>
<?}else{?>
	Нет записей
<?}?>