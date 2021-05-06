<?
/**
 * @var ManagerController $this
 * @var Account[] $models
 * @var User $user
 * @var string $title
*/
?>

<?$hiddenCount = 0;//для вывода кнопки Отобразить внизу?>

<div class="box">
	<?if(isset($title)){?>
		<div class="box-title">
			<h3>
				<i class="fa fa-bars"></i><?=$title?>
			</h3>
		</div>
	<?}?>
	<div class="box-content">
		<table class="table table-nomargin table-bordered table-colored-header">
			<thead>
				<th>кошелек</th>
				<th>принято</th>
				<th>дата проверки</th>
				<?if($this->isAdmin()){?><th>дата пика</th><?}?>
				<th>сообщение</th>
				<th>баланс</th>
				<th>суточный<br>лимит</th>
				<th>общий<br>лимит</th>
				<th>метка</th>
				<?if($this->isFinansist()){?><th>дата выдачи</th><?}?>
			</thead>
			<tbody>
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
								<form method="post" <?=($this->isAdmin()) ? 'target="_blank"' : ''?>>
									<input type="hidden" name="params[id]" value="<?=$account->id?>" />
									<button type="submit" name="setPriorityNow" class="btn btn-mini btn--icon" value="проверить">
										<i class="fa fa-refresh"></i>проверить
									</button>
								</form>
							<?}elseif($account->date_check < time() - 180 and $account->check_priority == Account::PRIORITY_NOW){?>

							<?}?>

							<?if(YII_DEBUG){?>
								<form method="post">
									<input type="hidden" name="params[id]" value="<?=$account->id?>" />
									<button type="submit" name="return" class="btn btn-mini btn-danger btn--icon" value="Вернуть в базу" title="Изменяет аккаунт так как будто он небыл взят юзером">
										<i class="fa fa-arrow-circle-up"></i>вернуть
									</button>
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


								<?if($user->id == $account->user_id){?>
									<?if(!$account->date_used){?><a data-id="<?=$account->id?>" class="changeLabel" href="javascript:void(0)">
											<i class="fa fa-edit" title="редактировать метку"></i>изменить</a>
									<?}?>
									<br>
									<form method="post">
										<input type="hidden" name="id" value="<?=$account->id?>">

										<?if($account->hidden){?>
											<button type="submit" name="toggleHidden" class="btn btn--icon" value="Отменить скрытие" title="Возвращает кошелек в общий список видимых">
												<i class="fa fa-eye"></i>Отменить скрытие
											</button>
										<?}else{?>
											<button type="submit" name="toggleHidden" class="btn btn-mini btn--icon" value="скрыть кошелек" title="Скрывает кошелек из списка видимых, помещает в список скрытых">
												<i class="fa fa-eye-slash"></i>скрыть кошелек
											</button>
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

										<?if(isset($num) and $num > 2){?>
											<button type="button" class="btn btn-info btn-mini showTransactions btn--icon">
												<i class="fa fa-caret-down"></i>показать все
											</button>

											<button type="button" class="btn btn-info btn-mini hideTransactions btn--icon" style="display: none">
												<i class="fa fa-caret-up"></i>скрыть
											</button>
										<?}?>
									</td>
						</tr>
					<?}?>
				<?}?>
			</tbody>
		</table>


		<?if($hiddenCount){?>
			<p>
				<button type="button" id="showWallets" class="btn btn--icon">
					<i class="fa fa-eye"></i>Показать скрытые кошельки (<?=$hiddenCount?>)
				</button>

				<button type="button" id="hideWallets" class="btn btn--icon" style="display: none">
					<i class="fa fa-eye-slash"></i>Скрыть
				</button>
			</p>

			<script>
				$(document).ready(function() {
					$('#showWallets').click(function () {
						$('tr.hidden').removeClass('hidden').addClass('shown');
						$(this).hide();
						$('#hideWallets').show();
					});

					$('#hideWallets').click(function () {
						$('tr.shown').removeClass('shown').addClass('hidden');
						$(this).hide();
						$('#showWallets').show();
					});
				});
			</script>
		<?}?>
	</div>

</div>
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