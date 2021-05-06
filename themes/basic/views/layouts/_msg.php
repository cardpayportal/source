<div>
<?php
if(!$this->result)
	$this->result = $_SESSION['msg'];

if(!$this->result)
	$this->result = array();
	
$_SESSION['msg'] = array();

if($this->result){?>
	<?if($this->result['success']){?>
		<?foreach($this->result['success'] as $msg){?>
			<p>
				<font color="green"><?=$msg?></font>
			</p>		
		<?}?>
	<?}?>
				
	<?if($this->result['error']){?>
		<?foreach($this->result['error'] as $msg){?>
			<p>
				<font color="red"><?=$msg?></font>
			</p>
		<?}?>
	<?}?>
<?}?>
 </div>