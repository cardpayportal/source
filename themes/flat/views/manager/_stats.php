<div class="box">
	<div class="box-title">
		<h3>Статистика по платежам</h3>
	</div>
	<div class="box-content">
		<?if($statsType=='labels'){?>
			<?foreach($stats as $label=>$arr){?>
				<p>
					<strong><?=$label?></strong><br />
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					Сегодня: <?=formatAmount($arr['today'], 0)?> руб (c 00:00 по <?=date('H:i')?>)<br />
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					Вчера: <?=$arr['yesterday']?> (c 00:00 по 00:00)
				</p>
			<?}?>
		<?}else{?>
			<p>
				<strong>Сегодня</strong>: <?=formatAmount($stats['today'], 0)?>руб<br /><br />
				<strong>Вчера</strong>: <?=formatAmount($stats['yesterday'], 0)?> руб
			</p>
		<?}?>
	</div>
</div>
