<?php
/**
 * @var array $accountStats
 */
?>

<p>
	Активных: <?=formatAmount($accountStats['countActive'], 0)?> (баланс: <?=formatAmount($accountStats['balanceActive'], 0)?> руб)
</p>

<p>
	Забаненых: <?=formatAmount($accountStats['countBan'], 0)?> (<?=formatAmount($accountStats['balanceBan'], 0)?> руб)
</p>
