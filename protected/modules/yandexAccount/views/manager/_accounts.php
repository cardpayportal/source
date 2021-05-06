<?
/**
 * @var ManagerController $this
 * @var User $user
 * @var YandexAccount[] $models
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
			<thead>
			<tr>
				<th>Кошелек</th>
				<th><span class="withComment" title="Карта яндекс деньги это тоже самое что и ваш кошелек ЯД">Карта</span>
				</th>
				<th>Баланс</th>
				<th>Статус</th>
				<th>Суточный<br>лимит</th>
				<th>Общий<br>лимит</th>
				<th>Проверен</th>
			</tr>
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
						elseif(time() - $wallet->date_check > 3600)
						{
							$class = 'error';
							$title = "ВНИМАНИЕ!!! ОСТАНОВИТЕ ЗАЛИВ";
						}
						else
						{
							$class = "";
						}

					?>

					<tr
						class="<?=$class?>"
						title="<?=$title?>"
					>
						<td>
							<span class='statusFull'>
								<b><?=$wallet->wallet?></b>
							</span>
						</td>
						<td>
							<b><?=$wallet->cardNumberStr?></b>
						</td>
						<td>
							<?= formatAmount($wallet->balance, 0) ?>
						</td>
						<td>
							<? if($wallet->error){ ?>
								<span class="error"><?= $wallet->error ?></span>
							<? }elseif(time() - $wallet->date_check > 3600){ ?>
								<span class="error">ВНИМАНИЕ!!!<br> ОСТАНОВИТЕ ЗАЛИВ </span>
							<? }else{ ?>
								<span class="success">активен</span>
							<? } ?>
						</td>
						<td class="noWrap">
							<?= $wallet->limitInDayStr ?>
						</td>
						<td class="noWrap">
							<?= $wallet->limitInMonthStr ?>
						</td>
						<td>
							<?= $wallet->dateCheckStr ?>
						</td>
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