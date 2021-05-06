<?php
/**
 * @var ControlController $this
 * @var AntiCaptcha[] $models
 * @var array $stats
 * @var string $dateAdd
 */

$this->title = 'AntiCaptcha';
?>

<h1><?=$this->title?> (Since: <?=date('H:i', $dateAdd)?>)</h1>

<p>
	<b>Free Count:</b> <?=$stats['freeCount']?><br>
	<b>Used Count:</b> <?=$stats['usedCount']?><br>
	<b>Expired Count:</b> <?=$stats['expiredCount']?><br>
</p>

<table class="std padding">
	<tr>
		<td>ID</td>
		<td>Answer</td>
		<td>Date Add</td>
		<td>Used By</td>
		<td>Date Used</td>
	</tr>

	<?foreach($models as $model){?>
		<tr
			<?if($model->isFree){?>
				class="good"
			<?}?>
		>
			<td><?=$model->id?></td>
			<td><?=$model->answerStr?></td>
			<td><?=$model->dateAddStr?></td>
			<td><?=$model->used_by?></td>
			<td><?=$model->dateUsedStr?></td>
		</tr>
	<?}?>

</table>