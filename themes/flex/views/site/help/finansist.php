<?$this->title = 'Инструкция для финансиста'?>

<h2><?=$this->title?></h2>

<p>
	После авторизации вы попадаете на <a href="<?=url('finansist/accountList')?>">страницу</a> с Выходными кошельками: <br />
	<img src="<?=Yii::app()->theme->baseUrl?>/img/finansist1.jpg" alt="Список выходных кошельков" class="border" />
</p>

<p>
	При нажатии на кошелек, вы можете увидеть историю переводов: <br />
	<img src="<?=Yii::app()->theme->baseUrl?>/img/finansist2.jpg" alt="История переводов" class="border" />
</p>

<p>
	При нажатии на ссылку <a href="<?=url('finansist/orderAdd')?>">Перевод средств</a>,
	Вы попадаете на страницу добавления нового перевода:<br />
	<img src="<?=Yii::app()->theme->baseUrl?>/img/finansist3.jpg" alt="Добавление перевода" class="border" />
</p>
<p>	
	Здесь вы можете указать, на какой кошелек перевести средства и в каком размере, указать комментарий, И нажать Отправить.
	<br />
 	Если в данный момент на кошельках нет необходимой суммы, она будет переводиться по мере поступления средств.
</p>

<p>
	Добавленные переводы, можно отслеживать на странице <a href="<?=url('finansist/orderList')?>">Список переводов</a>
	<img src="<?=Yii::app()->theme->baseUrl?>/img/finansist4.jpg" alt="Переводы" class="border" />
</p>