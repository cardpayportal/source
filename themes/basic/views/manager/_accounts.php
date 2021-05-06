<?
/**
 * @var ManagerController $this
 * @var Account[] $models
 * @var User $user
*/
?>

<?$hiddenCount = 0;//для вывода кнопки Отобразить внизу?>

<table class="std padding" style="width: 100%">
		<tr>
			<td>кошелек</td>
			<td>принято</td>
			<td>дата проверки</td>

            <?if($this->isAdmin()){?>
                <td>дата пика</td>
            <?}?>

			<td>сообщение</td>
			<td>баланс</td>
			<td>суточный<br>лимит</td>
			<td>общий<br>лимит</td>
			<td>метка</td>
			<?if($this->isFinansist()){?>
				<td>дата выдачи</td>
			<?}?>
		</tr>

	<?foreach($models as $account){?>

		<?
			$class = '';
			$title = '';

			if($account->error == Account::ERROR_BAN)
				$class = 'error ';
			elseif($account->isRat)
			{
				$class = 'ratTransaction ';
				$title = 'Минусы на кошельке';
			}

			if($account->hidden and $account->user_id == $user->id)
			{
				$class .= 'hidden';
				$hiddenCount++;
			}
		?>

		<tr
			class="<?=$class?>"
			title="<?=$title?>"
		>
			<td>
					<?if($this->isAdmin()){?>
						<?if($account->check_priority == Account::PRIORITY_STD){?>
						<?//пометить кошельки с пониженным приоритетам?>
							&#9660;
						<?}elseif($account->check_priority == Account::PRIORITY_NOW or $account->check_priority == Account::PRIORITY_STORE){?>
							&#x25B2;
						<?}?>
					<?}elseif($account->check_priority == Account::PRIORITY_STORE){?>
						&#x25B2;
					<?}?>

					<span class="<?=($account->status == Account::STATUS_FULL) ? 'statusFull' : ''?>">

					<?//скрытие части номера если кошелек давно не проверялся?>
					<?if(YII_DEBUG and $account->isOldCheck){?>
						<span class="dotted" title="давно не проверялся, юзерам не отобразится часть номера"><?=$account->login?></span>
					<?}elseif($account->isOldCheck){?>
						<?if($account->api_token){?>
							<?=$account->login?>
						<?}else{?>
							<?=$account->hiddenLoginStr?>
						<?}?>
					<?}else{?>
						<?if($account->card){?>
							<nobr><?=$account->cardNumberStr?></nobr>
						<?}else{?>
							<?=$account->login?>
						<?}?>
					<?}?>

					<?if($account->api_token and !cfg('tokenAccountsAsSimple')){?>
						&nbsp; <?=$account->passStr?>
					<?}?>
				</span>

				<?if($account->check_priority != Account::PRIORITY_NOW and !$account->date_used){?>
					<br />
					<form method="post"<?=($this->isAdmin()) ? 'target="_blank"' : ''?>>
						<input type="hidden" name="params[id]" value="<?=$account->id?>" />
						<input type="submit" name="setPriorityNow" value="Проверить сейчас"/>
					</form>
				<?}elseif($account->date_check < time() - 180 and $account->check_priority == Account::PRIORITY_NOW){?>

				<?}?>

				<?if(YII_DEBUG){?>
					<form method="post" target="_blank">
						<input type="hidden" name="params[id]" value="<?=$account->id?>" />
						<input type="submit" name="return" value="Вернуть в базу" style="background-color: #CC0033" title="Изменяет аккаунт так как будто он небыл взят юзером"/>
					</form>
				<?}?>
			</td>
			<td>
				<strong><?=$account->amountStr?></strong>

				<?if(YII_DEBUG){?>
					<br>
					<?$reserved = $account->reserveAmount;?>
					<?if($reserved > 0){?>
						<span class="noWrap" style="color:lightslategray">резерв:<?=formatAmount($reserved, 0)?></span>
					<?}?>
				<?}?>
			</td>
			<td>
				<b><?=date('H:i', $account->date_check)?></b>
				<br />
				<?=date('d.m', $account->date_check)?>
			</td>

            <?if($this->isAdmin()){?>
                <td><?=$account->datePickStr?></td>
            <?}?>

			<td><?=$account->orderMsg?></td>
			<td>
				<nobr><strong><?=$account->balanceStr?></strong></nobr>
				<?if($account->is_kzt){?>
					<br><nobr><span class="success smallText"><?=$account->balanceKztStr?></span></nobr>
				<?}?>
			</td>
			<td class="noWrap"><?=$account->dayLimitStr?></td>
			<td class="noWrap"><strong><?=$account->managerLimitStr?></strong></td>
			<td>
					<strong><?=$account->labelStr?></strong> 
					<?if(!$account->date_used){?><a data-id="<?=$account->id?>" class="changeLabel" href="javascript:void(0)">изм</a><?}?>

					<?if($user->id == $account->user_id){?>
						<br>
					<form method="post">
						<input type="hidden" name="id" value="<?=$account->id?>">

						<?if($account->hidden){?>
							<input type="submit" class="orange" name="toggleHidden" value="Отменить скрытие" title="Возвращает кошелек в общий список видимых">
						<?}else{?>
							<input type="submit" class="orange" name="toggleHidden" value="Скрыть кошелек" title="Скрывает кошелек из списка видимых, помещает в список скрытых">
						<?}?>
					</form>
					<?}?>
			</td>
			<?if($this->isFinansist()){?>
				<td><?=$account->datePickStr?></td>
			<?}?>
		</tr>
				
		<?if($transactions = $account->transactionsManager){?>
			<tr
				<?if($account->hidden){?>class="hidden"<?}?>
			>
						<td colspan="<?=($this->isFinansist()) ? '9' : '9'?>">
							<table data-id="<?=$account->id?>" class="noBorder trHeight" style="margin-left: 10px; width: 100%;">
								<?foreach($transactions as $num=>$trans){?>
									<?=$this->renderPartial('//manager/_transaction', array(
										'num'=>$num, 
										'trans'=>$trans,
									))?>

									<?//чтобы админу страница быстрее грузилась?>
									<?if($this->isAdmin() and  $num > 5){?>
										<tr>
											<td colspan="6">..............скрыты платежи</td>
										</tr>
										<?break;?>
									<?}?>

								<?}?>
							</table>
							
							<?if($num > 2){?>
								<br /><button class="showTransactions">Показать все</button>
							<?}?>
						</td>
			</tr>
		<?}?>
	<?}?>
	</table>

<?if($hiddenCount){?>
	<p>
		<button id="showHiddenWallets" class="orange" value="0">Показать скрытые кошельки (<?=$hiddenCount?>)</button>
	</p>

	<script>

		var text = $('#showHiddenWallets').text();

		$('#showHiddenWallets').click(function(){

			if($(this).attr('value') == '0')
			{
				$('tr.hidden').show();
				$(this).attr('value', '1');
				$(this).text('Скрыть кошельки');
			}
			else
			{
				$('tr.hidden').hide();
				$(this).attr('value', '0');
				$(this).text(text);
			}

		});
	</script>


<?}?>

<script>
	$(document).ready(function(){

		<?//показать все транзакции аккаунта?>
		$(document).on( "click", ".showTransactions", function(){
			$(this).parent().find('tr[data-param=toggleRow]').show();
			$(this).text('Скрыть');
			$(this).removeClass('showTransactions');
			$(this).addClass('hideTransactions');
		});

		<?//скрыть старые транзакции?>
		$(document).on( "click", ".hideTransactions", function() {
			$(this).parent().find('tr[data-param=toggleRow]').hide();
			$(this).text('Показать все');
			$(this).removeClass('hideTransactions');
			$(this).addClass('showTransactions');
		});

		<?//изменить метку кошелька?>
		$(document).on('click', '.changeLabel', function(){
			$(this).parent().append('<input type="text" data-id="'+$(this).attr('data-id')+'" class="changeLabelText" value="'+$(this).parent().find('strong').text()+'"/>');
			$(this).parent().find('strong,a').hide();
		});

		$(document).on('keyup', '.changeLabelText', function(event){
			if(event.keyCode==13)
			{
				var obj = $(this);

				var postData = 'id='+$(this).attr('data-id')+'&label='+$(this).val();

				sendRequest('<?=url('manager/ajaxChangeLabel')?>', postData, function(response){

					if(response)
					{
						if(response.error==0)
						{
							obj.parent().find('strong').text(obj.val());
							obj.parent().find('strong,a').show();
							obj.remove();
						}
						else
							alert(response.error);
					}
					else
						alert('ошибка запроса 1');
				});
			}

		});

	});
</script>


