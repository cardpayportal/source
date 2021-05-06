<?
/**
 * @var ControlController $this
 * @var GlobalFinLog[] $models
 */

$this->title = 'Лог действий';
?>


<?if($models){?>

	<p>Кол-во: <?=count($models)?></p>

	<?
		$currentModel = current($models);
	?>
	<table class="std padding">
		<tr>
			<td><?=$currentModel->attributeLabel('id')?></td>
			<td><?=$currentModel->attributeLabel('msg')?></td>
			<td><?=$currentModel->attributeLabel('user_id')?></td>
			<td><?=$currentModel->attributeLabel('date_add')?></td>
		</tr>

		<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td title="<?=$model->msg?>"><?=$model->msgShort?></td>
				<td><?=$model->user->name?></td>
				<td><?=$model->dateAddStr?></td>
			</tr>
		<?}?>

	</table>
<?}else{?>
	нет действий
<?}?>