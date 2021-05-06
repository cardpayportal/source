<?/**
 * @var array $params
 */?>

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
		<input type="hidden" name="params[id]" value="<?=$params['id']?>">
		<input type="submit" name="save" value="Сохранить"/>
	</p>

</form>