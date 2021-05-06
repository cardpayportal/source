<?
/**
 * @var ClientController $this
 * @var Client[] $models
 * @var array $params
 */

$this->title = 'Настройка модулей клиентов'
?>

<style>
	#vert{width:10%;}
	#vert td{text-align:center;padding:10px 0;}
	.vert-text{
		-ms-transform: rotate(90deg);
		-moz-transform: rotate(90deg);
		-webkit-transform: rotate(90deg);
		transform: rotate(90deg);

		-ms-transform-origin: left top 0;
		-moz-transform-origin: left top 0;
		-webkit-transform-origin: left top 0;
		transform-origin: left top 0;

		margin-left: 30px;
		padding: 0;
		float: left;
		height:140px;
		width:5px;
		white-space: nowrap;
	}
</style>

<h2><?=$this->title?></h2>

<?if($models){?>
	<form method="post" action="">
		<input type="submit" name="saveRules" value="Сохранить">
		<br><br>
		<div id="header"></div>
		<table class="std padding">
			<thead>
				<tr>
					<th>ID</th>
					<th>Имя</th>
					<th>Описание</th>
					<?foreach(ClientModuleRule::getRuleArr() as $module){?>
						<th><div class="vert-text"><?=$module?></div></th>
					<?}?>
				</tr>
			</thead>
			<?foreach($models as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td><?=$model->name?></td>
				<td><?=$model->description?></td>
				<?foreach(ClientModuleRule::getRuleArr() as $module){?>
					<td>
						<input type="hidden" readonly name="params[<?=$model->id;?>][<?=$module;?>]" value="<?=(int)$model->checkRule($module)?>" size="1"/>
						<?if($model->checkRule($module)){?>
							<div class="switch-btn switch-on"></div>
						<?}else{?>
							<div class="switch-btn"></div>
						<?}?>
					</td>
				<?}?>
			</tr>
			<?}?>
		</table>
		<br><br>
		<input type="submit" name="saveRules" value="Сохранить">
	</form>

	<script>




		$(function(){
			$('.switch-btn').click(function (e, changeState) {
				if (changeState === undefined) {
					$(this).toggleClass('switch-on');
				}
				if ($(this).hasClass('switch-on')) {
					$(this).trigger('on.switch');
				} else {
					$(this).trigger('off.switch');
				}
			});

			$('.switch-btn').on('on.switch', function(){
				var $input = $(this).parent().find('input');
				var count = parseInt($input.val())+1;
				count = count > 0 ? 1 : count;
				$input.val(count);
				$input.change();
				console.log('Кнопка переключена в состояние on');
				return false;
			});

			$('.switch-btn').on('off.switch', function(){
				var $input = $(this).parent().find('input');
				var count = parseInt($input.val())-1;
				count = count < 0 ? 0 : count;
				$input.val(count);
				$input.change();
				console.log('Кнопка переключена в состояние off');
				return false;
			});

			$('.switch-btn').each(function(){
				$(this).triggerHandler('click', false);
			});

		});
	</script>


<?}?>


