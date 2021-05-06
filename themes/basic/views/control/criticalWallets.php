<?
/**
 * @var ControlController $this
 * @var array $params
 * @var Account $models
 */
$this->title = 'Critical wallets';
?>

<h1><?=$this->title?></h1>
<p><i></i></p>

<?if($models){?>
	<form method="post">

		<p>
			<input type="submit" name="save" value="Сохранить">
		</p>

		<table class="std padding">
			<tr>
				<td>Клиент</td>
				<td>Кошелек</td>
				<td>Тип</td>
				<td>Critical</td>
			</tr>
			<?foreach($models as $model){?>
				<tr>
					<td><?=$model->client->name?></td>
					<td><?=$model->login?></td>
					<td><?=$model->typeStr?></td>
					<td>
						<?//передача текущего значения если ?>
						<input type="hidden" name="params[<?=$model->id?>]" value="<?=($model->isCritical) ? '1' : '0'?>">
						<input type="checkbox" name="params[<?=$model->id?>]" value="1"
							<?if($model->isCritical){?>checked="checked"<?}?> >
					</td>
				</tr>
			<?}?>
		</table>
		<p>
			<input type="submit" name="save" value="Сохранить">
		</p>
	</form>

<?}else{?>
	кошельков не найдено
<?}?>

