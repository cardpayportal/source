<?php
/**
 * @var ImagePosition $model
 */

$this->title = 'Координаты элементов'
?>

<div style="position: fixed;z-index: 101; padding: 8px; background-color: whitesmoke;"
	 xmlns="http://www.w3.org/1999/html">
	<p>
		<span id="result"></span>
	</p>

	<form method="post" action="">
		<p>
			<input type="hidden" value="<?=$model->id?>" name="params[id]"/>
		</p>
		<p>
			<strong>Тип ввода</strong><br/>

			<label>
				<select name="params[type]">
					<?foreach(ImagePosition::typeArr() as $name=>$value){?>
						<option value="<?=$name?>"
							<?if($params['type']==$name){?>
								selected="selected"
							<?}?>
						><?=$value?></option>
					<?}?>
				</select>
			</label>
		</p>
		<b>Координаты sms кода</b><br>
		<nobr>
			<input type="text" id="sms_input_pos" size="12" value="<?=$model->sms_input_pos?>" name="params[sms_input_pos]"/>
			<input id="smsButton" onclick="setPositionSmsButton(event);" type="button" value="Задать"/>
		</nobr>

		<p>
			<b>Координаты кнопки</b><br>
			<nobr>
				<input type="text" id="button_pos" size="12" value="<?=$model->button_pos?>" name="params[button_pos]"/>
				<input id="okButton" onclick="setPositionOkButton(event);" type="button" value="Задать"/>
			</nobr>
		<p>
		<p>
			<input type="submit" value="Сохранить" name="save"/>
		</p>
	</form>




</div>
<div>
	<img id='picture' onclick="defPosition(event);" src="<?=file_get_contents(DIR_ROOT.'img/'.$model->bank_name)?>" alt="Скриншот страницы подтверждения платежа" class="border" />
</div>
<script>

	function setPositionOkButton(event)
	{
		$position = document.getElementById('result').innerHTML;

		document.getElementById('button_pos').value = $position;

	}
	function setPositionSmsButton(event)
	{
		$position = document.getElementById('result').innerHTML;

		document.getElementById('sms_input_pos').value = $position;

	}

	function hideElements(event)
	{

	}

	function defPosition(event)
	{
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

		x = x-x0;
		y = y-y0;

//		var okButton = document.getElementById("okButton");
//		var smsButton = document.getElementById("smsButton");
//
//		okButton.onclick = function()
//		{
//			document.getElementById('button_pos').value = 'x:'+x+' y:'+y;
//		}
//		smsButton.onclick = function()
//		{
//			document.getElementById('sms_input_pos').value = 'x:'+x+' y:'+y;
//		}

		document.getElementById('result').innerHTML = 'x:'+x+' y:'+y;

	};

</script>

