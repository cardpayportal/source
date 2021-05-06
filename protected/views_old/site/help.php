<?$this->title = 'Помощь'?>

<p>
	<a href="<?=url(cfg('index_page'))?>">На главную</a>
</p>

<div class="menu">
	<?$this->renderPartial('_help_menu', array(
		'page'=>$page,
	))?>
</div>

<?
	if(!$this->hasError())
	{
		$this->renderPartial('//site/help/'.$page);
	}
?>