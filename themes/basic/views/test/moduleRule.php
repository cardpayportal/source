<?
/**
 * @var ClientController $this
 * @var Client[] $models
 * @var array $params
 */

$this->title = 'Настройка модулей клиентов'
?>

<h2><?=$this->title?></h2>

<div>
	<?if($models){?>
		<form method="post" action="">
			<input type="submit" name="saveRules" value="Сохранить">
			<br><br>
			<table class="std padding">
				<tr>
					<td>ID</td>
					<td>Имя</td>
					<td>Описание</td>
					<?foreach(ClientModuleRule::getRuleArr() as $module){?>
						<td><?=$module?></td>
					<?}?>
				</tr>
				<?foreach($models as $model){?>
				<tr>
					<td><?=$model->id?></td>
					<td><?=$model->name?></td>
					<td><?=$model->description?></td>
					<?foreach(ClientModuleRule::getRuleArr() as $module){?>
						<td>
							<input type="text" readonly name="params[<?=$model->id;?>][<?=$module;?>]" value="<?=(int)$model->checkRule($module)?>" size="1"/>
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
					var count = parseInt($input.val());
					count = count < 0 ? 0 : count;
					$input.val(count);
					$input.change();
					return false;

					console.log('Кнопка переключена в состояние on');
				});

				$('.switch-btn').on('off.switch', function(){

					var $input = $(this).parent().find('input');
					var count = parseInt($input.val())-1;
					count = count < 0 ? 0 : count;
					$input.val(count);
					$input.change();
					return false;

					console.log('Кнопка переключена в состояние off');
				});

				$('.switch-btn').each(function(){
					$(this).triggerHandler('click', false);
				});

			});
		</script>


			<? /*
			<?foreach($models as $model){?>
				<tr>
					<td><?=$model->id?></td>
					<td><?=$model->name?></td>
					<td><?=$model->description?></td>
					<td>
						<?=$model->pickAccountsStr?><br>
						<?if($model->pick_accounts){?>
							<button type="button" class="pickAccountsSwitch red" value="<?=$model->id?>"><nobr>Отключить</nobr></button>
						<?}else{?>
							<button type="button" class="pickAccountsSwitch green" value="<?=$model->id?>"><nobr>Включить</nobr></button>
						<?}?>
						<?if($model->pick_accounts_next_qiwi){?>
							<button type="button" class="pickAccountsNextQiwiSwitch red" value="<?=$model->id?>"><nobr>Отключить API</nobr></button>
						<?}else{?>
							<button type="button" class="pickAccountsNextQiwiSwitch green" value="<?=$model->id?>"><nobr>Включить API</nobr></button>
						<?}?>
						<?if($model->control_yandex_bit){?>
							<button type="button" class="controlYandexBit red" value="<?=$model->id?>"><nobr>Отключить YandexBit</nobr></button>
						<?}else{?>
							<button type="button" class="controlYandexBit green" value="<?=$model->id?>"><nobr>Включить YandexBit</nobr></button>
						<?}?>
					</td>
					<td>
						<form method="post" action="">
							<input type="hidden" name="params[client_id]" value="<?=$model->id?>"/>
							<p>
								<label>
									<select name="params[yandex_payment_type]">
										<?foreach(Account::paymentTypeArr() as $name=>$value){?>
											<option value="<?=$name?>"
												<?if($name==$model->yandex_payment_type){?>
													selected="selected"
												<?}?>
											><?=$value?></option>
										<?}?>
									</select>
								</label>
							</p>
							<p>
								<input type="submit" value="Сохранить" name="savePaymentType"/>
							</p>
						</form>
					</td>
					<td><?=$model->dateAddStr?></td>
					<td>
						<?if($model->is_active){?>
							<span class="success">да</span>
							<?if($this->isAdmin()){?>
								<br>
								<form method="post">
									<input type="hidden" name="params[id]" value="<?=$model->id?>">
									<input type="submit" name="disableClient" value="отключить кл" class="red" title="отключает всех юзеров клиента, замораживает кошельки">
								</form>
							<?}?>
						<?}else{?>
							<span class="error">нет</span>
							<?if($this->isAdmin()){?>
								<br>
								<form method="post">
									<input type="hidden" name="params[id]" value="<?=$model->id?>">
									<input type="submit" name="enableClient" value="включить кл" class="green">
								</form>
							<?}?>
						<?}?>
					</td>
					<td>
						<?if($model->is_active){?>
							<?=$model->globalFinStr?><br>
							<form method="post">
								<input type="hidden" name="params[id]" value="<?=$model->id?>">

								<?if($model->global_fin){?>
									<input type="submit" name="disableGlobalFin" value="отключить GF" class="red">
								<?}elseif(!$model->global_fin){?>
									<input type="submit" name="enableGlobalFin" value="включить GF" class="green">
								<?}?>
							</form>
						<?}?>
					</td>
					<td>
						<?if($model->is_active){?>
							<?=$model->calcEnabledStr?><br>
							<form method="post">
								<input type="hidden" name="params[id]" value="<?=$model->id?>">

								<?if($model->calc_enabled){?>
									<input type="submit" name="calcDisable" value="отключить Расчеты" class="red">
								<?}elseif(!$model->calc_enabled){?>
									<input type="submit" name="calcEnable" value="включить Расчеты" class="green">
								<?}?>
							</form>
						<?}?>
					</td>
					<td>
						<?if($model->finOrdersInProcess and $model->global_fin){?>
							<form method="post">
								<input type="hidden" name="params[id]" value="<?=$model->id?>">
								<?=count($model->finOrdersInProcess)?> <input type="submit" name="cancelFinOrders" value="отменить" class="orange" title="Отменяет все сливы с указанного клиента">
							</form>
						<?}?>
					</td>
					<td>
						<form method="post">
							<input type="hidden" name="params[id]" value="<?=$model->id?>">
							<nobr>
								<input type="text" name="params[email]" value="<?=$model->email?>" size="6">
								<input type="submit" name="edit" value="Save" size="4"/>
							</nobr>
						</form>
					</td>

					<td>
						<a href="<?=url('client/wexAccounts', ['clientId'=>$model->id])?>" title="кол-во WEX-аккаунтов"><?=$model->wexCount?></a>
					</td>
					<td>
						<a href="<?=url('client/qiwiNewAccounts', ['clientId'=>$model->id])?>" title="кол-во QiwiNew-аккаунтов"><?=$model->qiwiNewCount?></a>
					</td>
				</tr>
			<?}?>
		</table>
	<?}else{?>
		clients not found
	<?}?>
</div>



<?if($this->isAdmin()){?>
	<br/>
	<hr>

	<div>
		<h2>Добавить</h2>
		<form method="post">
			<p>
				<strong>Id</strong><br/>
				<input type="text" name="params[id]" value="<?=$params['id']?>">
			</p>

			<p>
				<strong>Name</strong><br/>
				<input type="text" name="params[name]" value="<?=$params['name']?>">
			</p>

			<p>
				<strong>Manager Count</strong><br/>
				<input type="text" name="params[managerCount]" value="<?=$params['managerCount']?>">
			</p>

			<p>
				<strong>Descr</strong><br/>
				<input type="text" name="params[description]" value="<?=$params['description']?>">
			</p>

			<p>
				<input type="submit" name="add" value="Submit">
			</p>
		</form>
	</div>

	<br><hr><br>

	<div>
		<h2>Сброс клиента</h2>
		<p>
			<i>Удаление транзакций, удаление юзаных кошельков, удаление расчетов, смена паролей, удаление платежей фина</i>
		</p>
		<form method="post">
			<p>
				<strong>Client ID</strong><br/>
				<input type="text" name="params[clientId]" value="<?=$params['clientId']?>">
			</p>

			<p>
				<strong>Пароль на подтверждение</strong><br/>
				<input type="text" name="params[confirmPass]" value="<?=$params['confirmPass']?>">
			</p>

			<p>
				<input type="submit" name="resetClient" value="Submit">
			</p>
		</form>
	</div>

<?}?>

<script>
	$(document).ready(function(){
		$('.pickAccountsSwitch').click(function () {
			$('#pickAccountsSwitchForm [name*=client_id]').val($(this).attr('value'));
			$('#pickAccountsSwitchForm [type=submit]').click();
		})
	});

	$(document).ready(function(){
		$('.pickAccountsNextQiwiSwitch').click(function () {
			$('#pickAccountsNextQiwiSwitchForm [name*=client_id]').val($(this).attr('value'));
			$('#pickAccountsNextQiwiSwitchForm [type=submit]').click();
		})
	});
	$(document).ready(function(){
		$('.controlYandexBit').click(function () {
			$('#controlYandexBitForm [name*=client_id]').val($(this).attr('value'));
			$('#controlYandexBitForm [type=submit]').click();
		})
	});
</script>

<form method="post" style="display: none" id="pickAccountsSwitchForm">
	<input type="hidden" name="params[client_id]" value="" />
	<p>
		<input type="submit" name="pickAccountsSwitch" value="Вкл/откл">
	</p>
</form>

<form method="post" style="display: none" id="pickAccountsNextQiwiSwitchForm">
	<input type="hidden" name="params[client_id]" value="" />
	<p>
		<input type="submit" name="pickAccountsNextQiwiSwitch" value="Вкл/откл API">
	</p>
</form>
<form method="post" style="display: none" id="controlYandexBitForm">
	<input type="hidden" name="params[client_id]" value="" />
	<p>
		<input type="submit" name="controlYandexBit" value="Вкл/откл YandexBit">
	</p>
</form>


 	*/ }?>


