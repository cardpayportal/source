<?
/**
 * @var MainController $this
 * @var User $user
 * @var MerchantWallet[] $models
 * @var string $title
*/
?>

<?$hiddenCount = 0;//для вывода кнопки Отобразить внизу?>

<!-- row -->
<div class="row">
	<div class="col-md-12">
		<div class="panel widget">
			<div class="panel-heading vd_bg-grey">
				<h3 class="panel-title"> <span class="menu-icon"> <i class="fa fa-dot-circle-o"></i> </span> <?=$this->title?> </h3>
			</div>
			<?if($models){?>

				<div class="panel-body  table-responsive">
					<table class="table table-bordered">
						<thead>
						<th>кошелек</th>
						<th>карта</th>
						<th>дата проверки</th>
						<th>сообщение</th>
						<th>баланс</th>
						<th>суточный<br>лимит</th>
						<th>общий<br>лимит</th>
						</thead>

						<tbody>
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
								<td class="noWrap"><?=$wallet->dayLimitStr?></td>
								<td class="noWrap"><strong><?=$wallet->managerLimitStr?></strong></td>

							</tr>

							<?if($transactions = $wallet->transactionsManager){?>
								<tr
									<?if($wallet->hidden){?>class="hidden"<?}?>
								>
									<td colspan="<?=($this->isFinansist()) ? '9' : '9'?>">
										<table data-id="<?=$wallet->id?>" class="noBorder trHeight" style="margin-left: 10px; margin-top: 5px;width: 100%;">
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
											<div class="mgbt-xs-10">
												<button type="button" class="btn btn-info btn-mini btn-sm btn-block showTransactions"><span class="append-icon"><i class="fa fa-fw"></i></span><i class="fa fa-caret-down"></i>показать все</button>
											</div>

											<div class="mgbt-xs-10" >
												<button type="button" class="btn btn-info btn-mini btn-sm btn-block hideTransactions" style="display: none">><span class="append-icon"><i class="fa fa-fw"></i></span><i class="fa fa-caret-up"></i>скрыть</button>
											</div>
										<?}?>
									</td>
								</tr>
							<?}?>

							</tr>

						<?}?>
						</tbody>
					</table>
				</div>
			<?}else{?>
				<div class="col-md-12">
					<label>
						записей не найдено
					</label>
				</div>

			<?}?>
		</div>
	</div>
	<!-- Panel Widget -->
</div>
<!-- col-md-12 -->

<script>
	$(document).ready(function(){

		<?//показать все транзакции аккаунта?>
		$(document).on( "click", ".showTransactions", function(){
			$(this).closest('td tr[data-param=toggleRow]').show();
			$(this).text('Скрыть');
			$(this).removeClass('showTransactions');
			$(this).addClass('hideTransactions');
		});

		<?//скрыть старые транзакции?>
		$(document).on( "click", ".hideTransactions", function() {
			$(this).closest('td tr[data-param=toggleRow]').hide();
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