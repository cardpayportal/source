<?
/**
 * @var $currentCategory string
 * @var $categories array
 */
?>

<?$this->title = 'Логи';?>

<div style="margin: 20px">
	<?foreach($categories as $category){?>
		<?if($category == $currentCategory){?>
			<strong><?=$category?></strong>
		<?}else{?>
			<a href="<?=url('panel/log', array('category'=>$category))?>"><?=$category?></a>
		<?}?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<?}?>

</div>

<a href="<?=url('panel/log', array('category'=>$currentCategory, 'clear'=>'true'))?>">очистить</a>
<strong><?=date('d.m.Y H:i:s')?></strong>
<button id="disableLogs" value="0" >Остановить</button>
<br/>
<div>
	<div id="newLogs">
	</div>
	<br/>
	<?=$logs?>
</div>
<input type="hidden" id="rowCount" value="<?=$rowCount?>" />

<audio id="logError" preload="auto">
	<source src="<?=Yii::app()->theme->baseUrl?>/audio/logError.mp3">
</audio>

<audio id="banError" preload="auto">
	<source src="<?=Yii::app()->theme->baseUrl?>/audio/banError.mp3">
</audio>

<script>

	$(document).ready(function(){

		$("#disableLogs").click(function() {

			if($(this).attr('value')=='0')
			{
				$(this).attr('value', '1');
				$(this).text('Запустить');
			}
			else
			{
				$(this).attr('value', '0');
				$(this).text('Остановить');
				updateLog();
			}
		});

		updateLog();
	});

	var logCategory = '<?=$_GET['category']?>';	//для звукового уведомления
	var logArr = [];	//массив ошибок (оповещать однотипные ошибки через интервал)
	var noticeInterval = 180;

	function updateLog()
	{
		var rowCount = $('#rowCount').val();


		sendRequest('<?=url('panel/log', array('category'=>$currentCategory, 'update'=>'true'))?>', 'rowCount='+rowCount, function(response){
			$('#rowCount').val(response.rowCount);

			if(response.content)
			{

				$('#newLogs').prepend(response.content+'<br/>');

				//для звукового уведомления
				var timestamp = Math.round(Date.now()/1000);

				if(!logArr[response.msg])
					logArr[response.msg] = 0;

				if(logCategory == 'error' && timestamp - logArr[response.msg]*1 > noticeInterval)
				{

					if(response.content.match(/забанен/))
						$('#banError')[0].play();
					else
						$('#logError')[0].play();

					logArr[response.msg] = timestamp;
				}
			}

			if($("#disableLogs").attr('value') == '0')
				setTimeout(updateLog, 3000);
		});
	}
</script>


