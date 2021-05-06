<?
	$this->title = 'Полная проверка аккаунта';
	
	if($model)
		$this->title .= ' '.$model->login;	
?>

<h2><?=$this->title?></h2>

<?if(!$this->hasError()){?>
	<font color="green"><strong>Кошелек проверен, ошибок не обнаружено</strong></font>
<?}?>