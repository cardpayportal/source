<?php
/**
 * @var ImagePosition $model
 */

$this->title = 'Координаты элементов'
?>


<div style="position: fixed;z-index: 101; padding: 8px; background-color: whitesmoke;">

	<p>
		<span id="result">x: y:</span>
	</p>


	<form method="post" action="">
		<p>
			<input type="hidden" value="<?=$model->id?>" name="params[id]"/>
		</p>
		<p>
			<b>Координаты sms кода</b><br>
			<input type="text" value="<?=$model->sms_input_pos?>" name="params[sms_input_pos]"/>
		</p>
		<p>
			<b>Координаты кнопки</b><br>
			<input type="text" value="<?=$model->button_pos?>" name="params[button_pos]"/>
		</p>
		<p>
			<input type="submit" value="Сохранить" name="save"/>
		</p>
	</form>
</div>
<div>
	<img id='picture' onclick="defPosition(event);" src="<?=file_get_contents(DIR_ROOT.'img/'.$model->bank_name)?>" alt="Скриншот страницы подтверждения платежа" class="border" />
</div>
<script>
	function defPosition(event) {
		var x = y = 0;
		var event = event || window.event;

		// Получаем координаты клика по странице, то есть абсолютные координаты клика.

		if (document.attachEvent != null) { // Internet Explorer & Opera
			x = window.event.clientX + (document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft);
			y = window.event.clientY + (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop);
		} else if (!document.attachEvent && document.addEventListener) { // Gecko
			x = event.clientX + window.scrollX;
			y = event.clientY + window.scrollY;
		}

		//Определяем границы объекта, в нашем случае картинки.

		y0=document.getElementById("picture").offsetTop;
		x0=document.getElementById("picture").offsetLeft;

		// Пересчитываем координаты и выводим их алертом.

		x = x-x0;
		y = y-y0;

		document.getElementById('result').innerHTML = 'x:'+x+' y:'+y;
	};

</script>

