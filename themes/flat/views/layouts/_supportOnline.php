<?
	//отображение кто на смене для фина и контроля
	$supportName = User::getSupportOnlineName();
?>

<?if($supportName){?>
	<li class='grey-4'>
		<i class="fa fa-webchat"></i>
		<div class="details">
			<span class="big">На смене:</span>
			<span><?=User::getSupportOnlineName()?></span>
		</div>
	</li>
<?}?>
