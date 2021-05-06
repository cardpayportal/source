
<?if($statsType=='labels'){?>
	<?foreach($stats as $label=>$arr){?>

		<div class="col-md-12">
			<label>
				<strong><?=$label?></strong>
			</label>
			<label>
				<strong>Сегодня: <?=formatAmount($arr['today'], 0)?> руб (c 00:00 по <?=date('H:i')?>)</strong>
			</label>
			<label>
				<strong>Вчера: <?=formatAmount($arr['yesterday'], 0)?> руб (c 00:00 по 00:00)</strong>
			</label>
		</div>
		<div class="col-md-12">
			<label>
				<strong>Вчера</strong>: <?=formatAmount($stats['yesterday'], 0)?> руб
			</label>
		</div>
	<?}?>
<?}else{?>
	<div class="col-md-12">
		<label>
			<strong>Сегодня</strong>: <?=formatAmount($stats['today'], 0)?>руб
		</label>
	</div>
	<div class="col-md-12">
		<label>
			<strong>Вчера</strong>: <?=formatAmount($stats['yesterday'], 0)?> руб
		</label>
	</div>
<?}?>