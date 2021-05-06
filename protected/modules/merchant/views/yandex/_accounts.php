<?
/**
 * @var MainController $this
 * @var User $user
 * @var MerchantWallet[] $models
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
		<table class="std padding" style="width: 100%">
			<tr>
				<td>кошелек</td>
				<td>карта</td>
				<td>дата проверки</td>
				<td>сообщение</td>
				<td>баланс</td>
				<td>суточный<br>лимит</td>
				<td>общий<br>лимит</td>
			</tr>
			<?foreach($models as $wallet){?>
			<tr>
					<?if($wallet->hidden == 1) continue;?>
					<?
						$class = '';
						$title = '';

						if($wallet->error == Account::ERROR_BAN)
							$class = 'error ';

						if($wallet->hidden and $wallet->user_id == $user->id)
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
							<span class='statusFull'>
								<?=$wallet->login?>
							</span>
						</td>
						<td>
							<?=$wallet->card_number?>
						</td>
						<td>
							<b><?=date('H:i', $wallet->date_check)?></b>
							<br />
							<?=date('d.m', $wallet->date_check)?>
						</td>


						<td><?=$wallet->orderMsg?></td>
						<td>
							<nobr><strong><?=$wallet->balanceStr?></strong></nobr>
						</td>
						<td class="noWrap"><?=$wallet->yadDayLimitStr?></td>
						<td class="noWrap"><strong><?=$wallet->managerLimitStr?></strong></td>

					</tr>

					<?if($transactions = $wallet->transactionsManager){?>
						<tr
							<?if($wallet->hidden){?>class="hidden"<?}?>
						>
									<td colspan="<?=($this->isFinansist()) ? '9' : '9'?>">
										<table data-id="<?=$wallet->id?>" class="noBorder trHeight" style="margin-left: 10px; width: 100%;">
											<?foreach($transactions as $num=>$trans){?>
												<?=$this->renderPartial('_transaction', array(
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

			</tr>
			<?}?>
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