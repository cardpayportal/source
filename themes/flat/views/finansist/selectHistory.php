<?

/**
 * @var FinansistController $this
 * @var array $history
 * @var int $dateStart
 */

$this->title = 'История выборки кошельков';
?>

<p>
	<a href="<?=url('finansist/globalOrderList')?>">
		<button class="btn">назад</button>
	</a>
</p>

<h1><?=$this->title?> (с <?=date('d.m.Y', $dateStart)?>)</h1>


<?if($history){?>
	<table class="table table-bordered">

		<tr>
			<td>Дата</td>
			<td>Кошельки</td>
		</tr>

		<?foreach($history as $timestamp=>$walletArr){?>
		<tr>
			<td valign="top"><?=date('d.m.Y H:i:s', $timestamp)?></td>
			<td>
				<b>всего: <?=count($walletArr)?></b><br>
				<textarea class="selectAll" cols="15" rows="<?=(count($walletArr)+1)?>"><?=implode(PHP_EOL, $walletArr)?></textarea>
			</td>
		</tr>
		<?}?>

	</table>
<?}else{?>
	кошельков не найдено
<?}?>

<script>
	$('.selectAll').click(function(){
		$(this).select();
	});
</script>
