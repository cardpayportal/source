<?
/**
 * @var ClientController $this
 * @var Client[] $models
 * @var array $params
 * @var bool $pickAccountEnabled
 */

$this->title = 'Список клиентов'
?>

	<h2>Выдача кошельков манагерам</h2>

	<form method="post">
		<p>
			<?if($pickAccountEnabled){?>
				<span class="success">Выдача включена</span>
				<input type="submit" name="togglePickAccount" value="Отключить">
			<?}else{?>
				<span class="error">Выдача Отключена</span>
				<input type="submit" name="togglePickAccount" value="Включить">
			<?}?>
		</p>

	</form>

<h2><?=$this->title?></h2>

<div>
	<?if($models){?>

		<table class="std padding">
			<tr>
				<td>ID</td>
				<td>Имя</td>
				<td>Описание</td>
				<td>Выдача кошелей</td>
				<td>Добавлен</td>
				<td>Активен</td>
				<td>Global Fin</td>
				<td>Отмена сливов</td>
			</tr>

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
								<input type="submit" name="pickAccountsSwitch" value="Отключить" class="red">
							<?}else{?>
								<input type="hidden" name="params[enabled]" value="1">
								<input type="submit" name="pickAccountsSwitch" value="Включить" class="green">
							<?}?>
							<input type="hidden" name="params[client_id]" value="<?=$model->id?>">
						</form>
					</td>
					<td><?=$model->dateAddStr?></td>
					<td><?=$model->isActiveStr?></td>
					<td>
						<?if($model->is_active){?>
							<?=$model->globalFinStr?><br>
							<form method="post">
								<input type="hidden" name="params[id]" value="<?=$model->id?>">

								<?if($model->global_fin){?>
									<input type="submit" name="disableGlobalFin" value="отключить" class="red">
								<?}elseif(!$model->global_fin){?>
									<input type="submit" name="enableGlobalFin" value="включить" class="green">
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