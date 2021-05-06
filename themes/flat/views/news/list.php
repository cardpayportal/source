<?/**
 * @var NewsController $this
 * @var array $params
 * @var News[] $models
 */
$this->title = 'Новости';
$user = User::getUser();

?>
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

<?if($this->isNewsEditor()){?>
	<?$this->renderPartial('_add', array('params'=>$params))?>
	<br>
	<br>
<?}?>

<?if($models){?>
	<?foreach($models as $model){?>
		<div class="row">
			<div class="blog-list-post">
				<?/*можно сделать картинку к новости отдельно
				<div class="preview-img">
					<a href="more-blog-post.html">
						<img src="img/demo/big/blog-1.jpg" alt="">
					</a>
				</div>
				*/?>
				<div class="post-content">
					<h4 class="post-title">
						<?/*<a href="<?=url('news/view', array('id'=>$model->id))?>"></a>*/?><?=$model->title?>
					</h4>

					<div class="post-meta">
						<span class="date">
							<i class="fa fa-calendar"></i><?=$model->dateAddStr?>
						</span>

						<?if(!$model->isRead($user->id)){?>
							<span class="tags">
								<i class="fa fa-eye" title="не прочитано"></i>
							</span>
						<?}?>

						<?if($this->isNewsEditor()){?>
							<span class="author">
								<i class="fa fa-user"></i>
								автор: <?=$model->authorStr?>
							</span>
							<span class="author">
								для: <i class="fa fa-users"></i><?=$model->rolesArrStr?>
							</span>

							<span class="author">
								<i class="fa fa-edit"></i>
								<a href="<?=url('news/list', array('editId'=>$model->id))?>">ред</a>
								&nbsp;&nbsp;&nbsp;&nbsp;
								<i class="fa fa-trash-o"></i>
								<a href="<?=url('news/list', array('deleteId'=>$model->id))?>">удалить</a>
							</span>
						<?}?>
					</div>
					<div class="post-text">
						<?=$model->shortText?>

						<?if(strlen($model->shortText) == strlen($model->fullText)) $model::read($user->id, $model->id); ?>
					</div>
				</div>
			</div>
		</div>
	<?}?>
<?}else{?>
	<br><br><b>нет новостей</b>
<?}?>