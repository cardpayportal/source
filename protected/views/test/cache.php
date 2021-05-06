<?php if($this->beginCache('name123123', array('duration'=>30))) { ?>
	sdfsdf
	<?php $this->endCache(); }else{?>
…другое HTML-содержимое…
<?}?>