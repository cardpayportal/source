<?
	//отображение кто на смене для фина и контроля
	$supportName = User::getSupportOnlineName();
?>

<?if($supportName){?>
	<b>На смене:</b>
	<?=User::getSupportOnlineName()?>
<?}?>
