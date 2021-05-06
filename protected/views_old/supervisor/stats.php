<?$this->title = 'Статистика по менеджерам'?>

<p>
	<a href="<?=url('supervisor/statsUsed')?>">Статистика по запоздалым платежам</a>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<a href="<?=url('supervisor/statsFinansist')?>">Статистика по переводом финансистов</a>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <a href="<?=url('supervisor/statsBtc')?>">Статистика по btc-переводам</a>
</p>

<?
if($this->isAdmin())
{
	$this->renderPartial('_stats', array(
		'user' => $user,
		'users' => $users,
		'stats' => $stats,
		'params' => $params,
	));
}
?>

