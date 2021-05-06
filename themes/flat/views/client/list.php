<?
/**
 * @var ClientController $this
 * @var Client[] $models
 * @var array $params
 * @var bool $pickAccountEnabled
 */

$this->title = 'Клиенты'
?>
<?/*	<p>
		<?if($pickAccountEnabled){?>
			<span class="success">Выдача включена</span>
			<input type="submit" name="togglePickAccount" value="Отключить">
		<?}else{?>
			<span class="error">Выдача Отключена</span>
			<input type="submit" name="togglePickAccount" value="Включить">
		<?}?>
	</p>
*/?>

<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-bars"></i>Выдача кошельков манагерам</h3>
	</div>
	<div class="box-content">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<?if($pickAccountEnabled){?>
					<label for="textfield1" class="control-label">Выдача включена</label>
					<button type="submit" class="btn btn-danger" name="togglePickAccount" value="Отключить">
						Отключить
					</button>
				<?}else{?>
					<span class="error">Выдача Отключена</span>
					<button type="submit" class="btn btn-success" name="togglePickAccount" value="Включить">
						Включить
					</button>
				<?}?>
			</div>
		</form>
	</div>
</div>
<br>
<br>
<br>


<div class="box box-bordered">
	<div class="box-title">
		<h3>
			<i class="fa fa-bars"></i>Список клиентов

			<form method="post">
				<button type="submit" class="btn btn-success btn-mini" name="cancelFinOrdersAll" value="Отменить Все сливы" title="Отменяет Все сливы всех клиентов">
					Отменить ВСЕ сливы
				</button>
			</form>
		</h3>
	</div>
	<div class="box-content">
		<?if($models){?>
			<table class="table table-bordered table-colored-header">
				<thead>
					<th>ID</th>
					<th>Имя</th>
					<th>Описание</th>
					<th>Выдача кошелей</th>
					<th>Добавлен</th>
					<th>Активен</th>
					<th>Global Fin</th>
					<td>Расчет</td>
					<th>Отмена сливов</th>
				</thead>
				<tbody>
					<?foreach($models as $model){?>
						<tr>
							<td><?=$model->id?></td>
							<td><?=$model->name?></td>
							<td><?=$model->description?></td>
							<td>
								<?=$model->pickAccountsStr?><br>
								<form method="post">
									<?if($model->pick_accounts){?>
										<input type="hidden" name="params[enabled]" value="0">
										<button type="submit" class="btn btn-danger btn-mini" name="pickAccountsSwitch" value="Отключить">
											Отключить
										</button>
									<?}else{?>
										<input type="hidden" name="params[enabled]" value="1">
										<button type="submit" class="btn btn-success btn-mini" name="pickAccountsSwitch" value="Включить">
											Включить
										</button>
									<?}?>
									<input type="hidden" name="params[client_id]" value="<?=$model->id?>">
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

											<button type="submit" class="btn btn-danger btn-mini" name="disableClient" value="отключить кл" title="отключает всех юзеров клиента, замораживает кошельки">
												Отключить кл
											</button>
										</form>
									<?}?>
								<?}else{?>
									<span class="error">нет</span>
									<?if($this->isAdmin()){?>
										<br>
										<form method="post">
											<input type="hidden" name="params[id]" value="<?=$model->id?>">

											<button type="submit" class="btn btn-success btn-mini" name="enableClient" value="включить кл">
												Включить кл
											</button>
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
											<button type="submit" class="btn btn-danger btn-mini" name="disableGlobalFin" value="Отключить">
												Отключить
											</button>
										<?}elseif(!$model->global_fin){?>
											<button type="submit" class="btn btn-success btn-mini" name="enableGlobalFin" value="Включить">
												Включить
											</button>
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
										<?=count($model->finOrdersInProcess)?>
										<button type="submit" class="btn btn-success btn-mini" name="cancelFinOrders" value="Отменить" title="Отменяет все сливы с указанного клиента">
											Отменить
										</button>
									</form>
								<?}?>
							</td>
						</tr>
					<?}?>
				</tbody>
			</table>
		<?}else{?>
			нет клиентов
		<?}?>
	</div>
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