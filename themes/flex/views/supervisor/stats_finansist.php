<?$this->title = 'Статистика по переводам финансистов'?>

<?$this->renderPartial('_stats', array(
	'user'=>$user,
	'users'=>$users,
	'stats'=>$stats,
	'params'=>$params,
	'stats_type'=>'finansist',
));
?>