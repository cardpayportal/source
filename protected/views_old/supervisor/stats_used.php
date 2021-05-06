<?$this->title = 'Статистика по запоздалым платежам'?>

<?
if($this->isAdmin())
{
	$this->renderPartial('_stats', array(
		'user'=>$user,
		'users'=>$users,
		'stats'=>$stats,
		'params'=>$params,
	));

}
?>

