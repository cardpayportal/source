<?$this->title = 'Инструкция для наблюдателя'?>

<h2><?=$this->title?></h2>

<p>
	После авторазации Вы попадаете на стриницу <a href="<?=url('supervisor/stats')?>">статистики по переводам</a>:	
	<img src="<?=Yii::app()->theme->baseUrl?>/img/supervisor1.jpg" alt="Статистика по переводам" class="border" />
	
</p>

<p>
	Здесь вы можете выбрать пользователя, по которому просматривать подневную статистику, и нужный период(по умолчанию - последние 7 дней).
</p>