<?
/**
 * @var $model News
 */

if($model)
	$this->title = $model->title;
else
	$this->title = 'ошибка';
?>

<p>
	<a href="<?=url('news/list')?>">назад</a>
</p>

<?if($model){?>
<h1><?=$this->title?> (<?=$model->dateAddStr?>)</h1>
	<?=$model->fullText?>
<?}else{?>
	новость не найдена
<?}?>
