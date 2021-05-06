<?$this->title = 'Инструкция для менеджера'?>

<h2><?=$this->title?></h2>

<p>
	После <a href="<?=url('site/help', array('page'=>'auth'))?>">входа</a> в Cистему
	Вы попадаете на страницу кошельков. Если вы еще не запрашивали кошельки, то она выглядит так:<br />
	<img class="border" src="style/img/manager1.jpg" alt="страница без кошельков" />
</p>

<p>
	Если у вас нет текущих кошельков, то перейдите по ссылке <a href="<?=url('manager/accountAdd')?>">Получить кошельки</a>.<br />
	<img class="border" src="style/img/manager2.jpg" alt="страница получения кошельков" />
	<br /><br />
	Укажите: Количество кошельков, которое хотите получить, комментарий(необязательное поле, помогает не забыть для чего запрашивался кошелек), и нажмите на кнопку Получить кошельки.
</p>

<p>
	Возможные ошибки на этом этапе:
	<br /><br />
	<font color="red">не указано количество кошельков</font> - вы указали неверное кол-во кошельков в поле Количество кошельков<br /><br />
	<font color="red">недостаточно проверенных кошельков</font> - в системе, по техническим причинам, на данный момент недостаточно проверенных кошельков, подождите некоторое время<br /><br />

</p>

<p>
	Если вы ввели все данные правильно и в системе есть свободные кошельки, 
	то Вы попадаете на <a href="<?=url('manager/accountList')?>">Страницу с кошельками</a>:<br /> 
	<img class="border" src="style/img/manager3.jpg" alt="страница с кошельками" />
</p>

<p>
	<strong>Принято</strong> - сколько рублей пришло на кошелек с того момента, как вы его получили<br /><br />
	<strong>Дата проверки</strong> - когда в последний раз система проверяла этот кошелек<br /><br />
	<strong>Сообщение</strong> - сообщение для менеджера(Если система обнаружила ошибку на этом кошельке и тут будет сказано: <font color="red">Остановите переводы на этот кошелек</font>, вам необходимо сообщить клиентам, чтобы они больше не делали переводы на данный кошелек)<br /><br />
	<strong>Баланс</strong> - текущий баланс на кошельке. Система автоматически переводит средства с ваших кошельков. 
		<br/>Если по какой-то причине баланс будет превышать 60 000 руб, клиент не сможет перевести средства на этот кошелек. Скажите чтобы переводил за раз не больше 50 000 руб.<br /><br />
	<strong>Лимит</strong> -  количество денег, которое может принять данный кошелек. Если вы ожидаете прихода средств на данный кошелек, то убедитесь что на нем достаточно лимита. 
		<br />Рекомендуем не принимать платежи на кошельки, с лимитом меньше 60 000 руб.<br /><br />
	<strong>Метка</strong> -  комментарий к кошелькам, который вы ввели при получении<br /><br />
</p>

<p>
	Когда средства поступят, Вы увидите список входящих переводов под каждым кошельком:<br />
	<img class="border" src="style/img/manager4.jpg" alt="кошельки с переводами" />
</p>

<p>
	Только если цвет входящего перевода зеленый - это означает что деньги пришли.
	<br /><br />
	<strong>Принято</strong> - если на кошельке в колонке Принято стоит 0, а баланс положительный, это значит что Система еще не перевела деньги с этого кошелька.
</p>

<p>
	В любое время вы можете запросить еще кошельков. Следите за балансом и не превышайте лимит.
</p>