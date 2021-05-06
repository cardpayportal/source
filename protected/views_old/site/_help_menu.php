<p>
	<a href="<?=url('site/help', array('page'=>'about'))?>">О системе</a>
</p>

<p>
	<a href="<?=url('site/help', array('page'=>'auth'))?>">Как войти</a>
</p>

<p>
	<a href="<?=url('site/help', array('page'=>'register'))?>">Как зарегистрироваться</a>
</p>

<p>
	<?if($this->isUser()){?>
		<a href="<?=url('site/help', array('page'=>'manager'))?>">Инструкция для менеджера</a>
	<?}elseif($this->isModer()){?>
		<a href="<?=url('site/help', array('page'=>'finansist'))?>">Инструкция для финансиста</a>
	<?}elseif($this->isSupervisor()){?>
		<a href="<?=url('site/help', array('page'=>'supervisor'))?>">Инструкция для наблюдателя</a>
	<?}?>
	
</p>
