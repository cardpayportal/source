<?php
/**
 * @var array $logs
 */
$this->title = 'Логи отсортированные'
?>

<style>
	h3 {
		padding: 10px 5px;
		margin: 5px 0;
		background-color: #D2D2D2;
		cursor: pointer;
	}

	.spoyler-content {
		display: none;
		margin: 0;
	}

	.spoyler-content p {
		padding: 0;
		margin: 0;
	}
</style>

<div style="margin: 20px">
	<?foreach($categories as $category){?>
		<?if($category == $currentCategory){?>
			<strong><?=$category?></strong>
		<?}else{?>
			<a href="<?=url('panel/logSorted', array('category'=>$category))?>"><?=$category?></a>
		<?}?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<?}?>

</div>

<a href="<?=url('panel/logSorted', array('category'=>$currentCategory, 'clear'=>'true'))?>">очистить</a>

<div class="spoyler">
	<?foreach($logs as $key=>$category){?>
		<h3 title="Нажмите для просмотра полной информации"><?=(++$key).') '.$category[0]['shortStr'].' ('.(count($category)-1).')'?></h3>
		<div class="spoyler-content">
			<?foreach($category as $content){?>
				<?if(!isset($content['time'])) continue;?>
				<p>
					<?='<strong>'.date('d.m.Y H:i:s', $content['time']).'</strong> '.$content['fullStr']?>
				</p>
			<?}?>
		</div>
	<?}?>
</div>

<script>
	$(function(){
		$('h3').click(function(event) {
			event.preventDefault();

			if ( $(this).next('div').is(':visible') ) {
				$(this).next('div').animate({height: 'hide'}, 500);
			} else {
				$('.spoyler-content').animate({height: 'hide'}, 500);
				$(this).next('div').animate({height: 'show'}, 500);
			}
		});
	});
</script>
