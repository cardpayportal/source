<?
/**
 * @var array $params
 * @var CardAccount[] $models
 * @var array $filter
 * @var array $transactionStats
 * @var array $accountStats
 */

$this->title = 'Список кошельков';
?>

<h1><?=$this->title?></h1>

<p>Обновление через: <span id="countdown"></span></p>

<?//фильтр?>
<form method="post">
	<p>
		<select name="filter[client_id]" class="changeSubmit">
			<option value="">Все</option>
			<?foreach(Client::getActiveClients() as $client){?>
				<option value="<?=$client->id?>"
						<?if($filter['client_id']==$client->id){?>selected<?}?>
				>
					<?=$client->name?>
					<?$countAccount = CardAccount::getCountByClientId($client->id);?>
					<?if($countAccount > 0){?> Всего: <?=$countAccount?><?}?>
				</option>
			<?}?>
		</select>

		<?foreach(CardAccount::getStatusArr() as $statusId=>$statusName){?>
			<input type="checkbox" class="changeSubmit" name="filter[status][]" value="<?=$statusId?>"
				<?if(in_array($statusId, $filter['status'])){?>
					checked="checked"
				<?}?>
			><?=$statusName?> &nbsp;&nbsp;
		<?}?>
	</p>
	<input type="hidden" name="filterItems" value="true">
</form>

<form method="post">
	<p>
		<input type="text" name="params[searchStr]" value="<?=$params['searchStr']?>" placeholder="4456520769705316">
		<input type="submit" name="search" value="Найти">
	</p>
</form>

<span id="accountStats">
	<?$this->renderPartial('_accountStats', [
		'accountStats' => $accountStats,
	])?>
</span>


<?if($models){?>

	<table class="std padding">
		<thead>
			<tr>
				<th><input type="checkbox" title="Выбрать все" id="checkAll"></th>
				<th>Клиент</th>
				<th>Номер</th>
				<th>Баланс</th>
				<th>Списать с баланса</th>
				<th>Лимит</th>
				<th>Поступления <br> за сегодня</th>
				<th>Статус</th>
				<th>Добавлен</th>
			</tr>
		</thead>

		<tbody id="accounts">
			<?$this->renderPartial('_accounts', [
				'models' => $models,
				'transactionStats' => $transactionStats,
			])?>
		</tbody>
	</table>

	<form method="post" id="groupForm" style="display: none">
		<input type="hidden" name="params[account_id]" value="" id="ids">
		<p>
			<b>с отмеченными: </b>
			<select name="params[status]">
				<?foreach(CardAccount::getStatusArr() as $statusId=>$statusName){?>

					<option value="<?=$statusId?>"
							<?if($statusId == $model->status){?>selected<?}?>
					>
						<?=$statusName?>
					</option>

				<?}?>
			</select>
			&nbsp;&nbsp;
			<input type="submit" name="changeStatus" value="Сменить статус">
		</p>

	</form>

	<script>
		startCountdown();
	</script>

<?}else{?>
	кошельков не найдено
<?}?>

<br><br><hr>


<form method="post">

	<p>
		<b>Кошельки</b><br>
		<textarea cols="35" rows="10" name="params[loginStr]"
				  placeholder="5269520022849527<?="\n"?>5269520042829527<?="\n"?>5464520022829527"><?=$params['loginStr']?></textarea>
		<br>(каждый с новой строки)
	</p>

	<p>
		<select name="params[client_id]">
			<?foreach(Client::getActiveClients() as $client){?>
				<option value="<?=$client->id?>"
					<?if($params['client_id']==$client->id){?>selected<?}?>
				>
					<?=$client->name?>
					<?$countAccount = CardAccount::getCountByClientId($client->id);?>
					<?if($countAccount > 0){?> Всего: <?=$countAccount?><?}?>
				</option>
			<?}?>
		</select>
	</p>

	<p>
		<input type="submit" name="add" value="Добавить">
	</p>

</form>

<script>
	var countdown = $('#countdown');
	var timer;
	var countdownInterval = 30000;

	$(document).ready(function(){
		$('body').on('click', 'input.check, #checkAll', function(){

			var idStr = '';

			$('input.check:checked').each(function(){
				if(idStr.length) idStr += ',';
				idStr += $(this).val();
			});

			$('#ids').val(idStr);

			if($('input.check:checked').length)
				$('#groupForm').show();
			else
				$('#groupForm').hide();
		});

		$('body').on('change', 'select.changeStatus', function(){

			var postData = $(this).closest('form').serialize()+'&isAjax=true';
			sendRequest(location.href, postData, function(response){
				if(response)
				{
					if(response.success)
					{
						clearInterval(timer);
						updateInfo();
						startCountdown();
					}
					else
						alert('не удалось изменить статус кошелька');
				}
				else
					alert('ошибка запроса на смену статуса')
			});
		});

		$('body').on('submit', 'form.withdrawForm', function(){

			var postData = $(this).serialize()+'&isAjax=true';
			sendRequest(location.href, postData, function(response){
				if(response)
				{
					if(response.success)
					{
						clearInterval(timer);
						updateInfo();
						startCountdown();
					}
					else
						alert('не удалось сохранить списание');
				}
				else
					alert('ошибка запроса на списание')
			});

			return false;
		});

		$('body').on('submit', 'form.limitForm', function(){

			var postData = $(this).serialize()+'&isAjax=true';
			sendRequest(location.href, postData, function(response){
				if(response)
				{
					if(response.success)
					{
						clearInterval(timer);
						updateInfo();
						startCountdown();
					}
					else
						alert('не удалось изменить лимит');
				}
				else
					alert('ошибка запроса на изменение лимита')
			});

			return false;
		});

		$('body').on('change', '.changeSubmit', function(){
			this.form.submit();
		});

	});

	function updateInfo()
	{
		/**
		 * @param response.accounts
		 * @param response.accountStats
		 */
		sendRequest(location.href, 'ajaxUpdate=true', function(response){

			if(response)
			{
				if($('#accounts').length)
					$('#accounts').html(response.accounts);

				if($('#accountStats').length)
					$('#accountStats').html(response.accountStats);
			}
			else
				alert('ошибка обновления страницы')

		});
	}

	function startCountdown(){
		var countdownIntervalSec = countdownInterval/1000
		countdown.text(countdownIntervalSec);
		timer = setInterval(function(){
			countdown.text(--countdownIntervalSec);
			if(countdownIntervalSec <= 0) {
				clearInterval(timer);
				updateInfo();
				startCountdown();
			}
		},1000);
	}

	function changeStatus(accountId, value)
	{
		sendRequest(location.href, 'ajaxUpdate=true', function(response){

			if(response)
			{
				if($('#accounts').length)
					$('#accounts').html(response.accounts);

				if($('#accountStats').length)
					$('#accountStats').html(response.accountStats);
			}
			else
				alert('ошибка смены статуса')
		});
	}
</script>