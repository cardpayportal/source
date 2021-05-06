<?
/**
 * @var Controller $this
 * @var array $stats
 * @var Client $client
 */
$this->title = 'CommissionMonitor';

?>
<style>
	div.bigContent{
		overflow-x: scroll;
		height: 100%;
	}
</style>

<h1><?=$this->title?></h1>

<div>
	<h3>Всего:</h3>

	<b>Приход на все</b>: <?=formatAmount($stats['stats']['inAmount'], 0)?> руб<br>
	<b>Расход на все</b>: <?=formatAmount($stats['stats']['outAmount'], 0)?> руб<br>
	<b>Комиссия полная</b>: <?=formatAmount($stats['stats']['commissionAmount'], 0)?> руб (<?=formatAmount($stats['stats']['commissionPercent'], 2)?> %)<br>
	<b>Приход на входящие</b>:<?=formatAmount($stats['stats']['inAmountIn'], 0)?> руб<br>
	<b>Комиссия от прихода</b>: <?=formatAmount($stats['stats']['commissionPercentRelative'], 2)?> %<br>
	<br><br>
</div>

<div class="bigContent">
	<table class="std" style="width: 1400px">
		<thead>
			<th>GroupId</th>
			<?foreach($stats['groups'][1]['types'] as $typeName=>$type){?>
				<th><?=ucfirst($typeName)?></th>
			<?}?>
			<th>All</th>
		</thead>

		<tbody>
			<?foreach($stats['groups'] as $groupId=>$group){?>
				<tr>
					<td><?=$groupId?></td>
					<?foreach($group['types'] as $typeName=>$type){?>
						<td style="text-align: left">
							<?foreach($type['wallets'] as $login=>$wallet){?>
								<?=$login?> &nbsp;&nbsp;
								<b title="сумма входящих"><?=formatAmount($wallet['inAmount'], 0)?>R</b>&nbsp;&nbsp;
								<b title="сумма исходящих"><?=formatAmount($wallet['outAmount'], 0)?>R</b>&nbsp;&nbsp;

								<?if($wallet['uniqueWallets'] > cfg('walletsCountMax')){?>
									<span class="error"><?=$wallet['uniqueWallets']?></span>
								<?}else{?>
									<?=$wallet['uniqueWallets']?>
								<?}?>U
								&nbsp;&nbsp;

								<span class="error"> <?=($wallet['commissionPercent'] > 0) ? formatAmount($wallet['commissionPercent'], 2).'%' : ''?></span>
								<br>
							<?}?>
						</td>
					<?}?>
					<td>
						<b><?=formatAmount($group['stats']['inAmount'], 0)?>R</b><br>
						<b><?=formatAmount($group['stats']['outAmount'], 0)?>R</b><br>
						<b><?=$group['stats']['uniqueWallets']?>U</b><br>
						<span class="error"> <?=($group['stats']['commissionPercent'] > 0) ? formatAmount($group['stats']['commissionPercent'], 2).'%' : ''?></span>
					</td>
				</tr>
			<?}?>
		</tbody>
	</table>
</div>