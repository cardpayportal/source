<?/**
 * @var NewsController $this
 * @var array $params
 */?>
<?/*
<form method="post" >

	<p>
		<b>Заголовок</b><br>
		<input type="text" name="params[title]" value="<?=$params['title']?>"/>
	</p>

	<p>
		<b>Текст</b> (вставка изображения: !ссылкаНаКартинку.jpg!<br>
		<textarea name="params[text]" cols="110" rows="9"><?=$params['text']?></textarea>
	</p>


	<p>
		<b>Для кого</b><br><br>

		<?foreach(News::getRolesArr() as $roleId=>$roleStr){?>
			<?
				if($params)
				{
					if(in_array($roleId, $params['userRoles']))
						$checkedStr = 'checked="checked"';
					else
						$checkedStr = '';
				}
			?>

			<input type="checkbox" name="params[userRoles][]" value="<?=$roleId?>" <?=$checkedStr?>> <?=$roleStr?><br><br>
		<?}?>
	</p>


	<p>

		<input type="submit" name="save" value="Сохранить"/>
	</p>

</form>
*/?>
<div class="box box-bordered">
	<div class="box-title">
		<h3>
			<i class="fa fa-bars"></i><?if($params['id']){?>Изменить<?}else{?>Добавить<?}?> новость</h3>
	</div>
	<div class="box-content nopadding">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<label for="textfield1" class="control-label">Заголовок</label>
				<input type="text" name="params[title]" value="<?=($params['title']) ? $params['title'] : ''?>" id="textfield1" class="form-control"/>
			</div>
			<div class="form-group">
				<label for="textfield2" class="control-label">Текст</label>
				<textarea name="params[text]" rows="9" class="form-control" id="textfield2"><?=($params['text']) ? $params['text'] : ''?></textarea>
				<span class="help-block">
					вставка изображения: !ссылкаНаКартинку.jpg!
				</span>
			</div>
			<div class="form-actions">
				<input type="hidden" name="params[id]" value="<?=$params['id']?>">
				<button type="submit" class="btn btn-primary" name="save" value="Сохранить">Сохранить</button>
			</div>
		</form>
	</div>
</div>
