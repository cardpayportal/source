<?/**
 * @var NewsController $this
 * @var array $params
 * @var News[] $models
 * @var string $globalMsg
 */?>
<?$this->title = 'Новости'?>

<?if($this->isNewsEditor()){?>
	<form method="post">
		<p>
			<label>
				<b>Сообщение для всех юзеров (красным сверху)</b>
				<br>
				<textarea name="params[globalMsg]" rows="5" cols="35"><?=$globalMsg?></textarea>
			</label>
		</p>
		<p>
			<input type="submit" name="setGlobalMsg" value="Отправить">
		</p>

	</form>
	<br><hr><br>
<?}?>

<h2><?=$this->title?></h2>

<?if($this->isNewsEditor()){?>
	<?$this->renderPartial('_add', array('params'=>$params))?>
	<br>
	<br>
<?}?>

<?
$user = User::getUser();
?>

<?if($models){?>
	<?foreach($models as $model){?>
		<div class="news_row">
			<p>
				<b><a href="<?=url('news/view', array('id'=>$model->id))?>"><?=$model->title?></a></b> (<?=$model->dateAddStr?>)

				<?if(!$model->isRead($user->id)){?>
					<span style="color:#8b0000">не прочитано</span>
				<?}?>

				<?if($this->isNewsEditor()){?>
					(
					<a href="<?=url('news/list', array('editId'=>$model->id))?>">ред</a>
					&nbsp;&nbsp;&nbsp;
					<a href="<?=url('news/list', array('deleteId'=>$model->id))?>">удалить</a>
					)
				<?}?>
			</p>

			<p>
				<?=$model->shortText?>
			</p>

			<?if($this->isNewsEditor()){?>
				<p>
					Автор: <b><?=$model->authorStr?></b>
				</p>
			<?}?>

			<?if($this->isNewsEditor()){?>
				<p>
					Для: <b><?=$model->rolesArrStr?></b>
				</p>
			<?}?>

		</div>
	<?}?>
<?}else{?>
	<p>нет новостей</p>
<?}?>