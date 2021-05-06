<?php
/**
 * @var array $history
 * @var string $user
 * @var string $qiwiNewAccountId
 * @var string $exchange
 * @var string $out
 * @var string $error
 * @var PayeerAccount $account
 */
$this->title = 'История QiwiNew '.$user;
?>

<p>
	<h2><?=$user?></h2>
</p>

<b><nobr>
		<?if($account->balance_ru > 100){?>
			<font color="red">
				<?=formatAmount($account->balance_ru, 0)?>
			</font>
		<?}else{?>
			<font color="green">
				<?=formatAmount($account->balance_ru, 0)?>
			</font>
		<?}?>

		руб &nbsp
</b>
&nbsp&nbsp
<br><br>Дата проверки: <i><?=($account->date_check) ? date('d.m.Y H:i', $account->date_check) : ''?></i><br><br>
<strong>Платежей на текущей странице (<?=count($history['history'])?>)</strong><br>

<?if($history){?>
	<table class="std padding">
		<?foreach($history['history'] as $trans){?>
			<tr class="success">
				<td><?=$trans['id']?></td>
				<td><?=$trans['creditedAmount'].' '.$trans['creditedCurrency']?></td>
				<td><?=$trans['paySystem']?></td>
				<td><?=$trans['to']?></td>
				<td><?=$trans['type']?></td>
				<td><?=$trans['date']?></td>
			</tr>
		<?}?>
	</table>
<?}else{?>
	нет платежей
<?}?>


