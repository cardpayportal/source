<?/**
 * @var NewsController $this
 * @var array $params
 * @var News[] $models
 */?>
<?$this->title = 'Новости'?>

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
					(<a href="<?=url('news/list', array('editId'=>$model->id))?>">ред</a> )
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