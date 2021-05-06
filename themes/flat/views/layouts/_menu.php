<?if($this->isManager()){?>
	<?$this->renderPartial('//layouts/_menuManager')?>
<?}elseif($this->isFinansist()){?>
	<?$this->renderPartial('//layouts/_menuFinansist')?>
<?}elseif($this->isGlobalFin()){?>
	<?$this->renderPartial('//layouts/_menuGlobalFin')?>
<?}elseif($this->isAdmin()){?>
	<?$this->renderPartial('//layouts/_menuAdminTop')?>
<?}elseif($this->isControl()){?>
	<?$this->renderPartial('//layouts/_menuControl')?>
<?}?>
