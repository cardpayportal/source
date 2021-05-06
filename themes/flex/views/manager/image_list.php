<?php
/**
 * @var ImagePosition $model
 */
$this->title = 'Список изображений с координатами';
?>

<meta http-equiv="refresh" content="30">

<?if($models){?>
	<table id="table" class="std padding">
		<tr>
			<td>ID</td>
			<td>Название банка</td>
			<td>Координаты sms кода</td>
			<td>Координаты кнопки</td>
			<td>Тип</td>
			<td>Статус</td>
			<td>Действие</td>
		</tr>

		<?foreach($models as $model){?>
		<tr>
			<td><?=$model->id?></td>
			<td><?=$model->bank_name?></td>
			<td><?=$model->sms_input_pos?></td>
			<td><?=$model->button_pos?></td>
			<td><?=$model->type?></td>
			<td><span><?=$model->status?></span></td>
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


<audio id="audio" src="<?=Yii::app()->theme->baseUrl?>/audio/logError.mp3"></audio>

<script>
	function sound(data, d){if(data == "playSound")document.getElementById('audio').play()}
	setInterval(function(){
		var tableElem = document.getElementById('table');
		var elements = tableElem.getElementsByTagName('span');

		for(var i = 0; i < elements.length; i++)
		{
			var input = elements[i];
			if(input.innerHTML == 'wait')
			{
				$.ajax({
					url: "index.php?r=manager/imageList",
					type: "POST",
					data: ({startUpdate: "playSound"}),
					dataType: "html",
					success: sound
				});
			}
		}
	},14000)
</script>

