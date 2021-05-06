<?
/**
 * @var TestPublicController Controller $this
 * @var array $params
 * @var array $stats
 */
$this->title = 'Sim статистика';
?>

<p>
	<b>Всего:</b> <?=formatAmount($stats['countAll'], 0)?>
	, <b>Успешных:</b> <?=formatAmount($stats['countSuccess'], 0)?> (<?=formatAmount($successPercent, 0)?>%
</p>

<h2>Карты</h2>

<table>
	<thead>
		<th>Карта</th>
		<th>orderId</th>
		<th>status</th>
		<th>da</th>
		<th></th>
	</thead>
</table>