<?
/**
 * @var $models User[]
 */
$this->title = 'Юзеры'
?>

<p>
	<a href="<?=url('user/register')?>">Регистрация</a>

	<?if($this->isAdmin()){?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<a href="<?=url('user/register')?>">Цепочки юзеров</a>
	<?}?>
</p>

<p>Всего: <strong><?=count($models)?></strong></p>

<?if($models){?>

	<table class="table table-bordered">
		<thead>
			<th>ID</th>
			<th>Логин</th>
			<th>Имя</th>
			<th>Роль</th>
			<th>Client</th>
			<th>Jabber</th>
			<th>Действие</th>
		</thead>
		<tbody>
		<?foreach($models as $model){?>
			<tr
				style="background-color: <?=($model->active) ? '#DCFFE5' : '#FF9394'?>;">
				<td><?=$model->id?></td>
				<td><?=$model->login?></td>
				<td><?=$model->name?></td>
				<td><?=$model->roleStr?></td>
				<td><?=$model->client->name?></td>
				<td><?=$model->jabber?></td>

				<td style="text-align:left;">
					<form method="post">
						<input type="hidden" name="params[id]" value="<?=$model->id?>" />

						<?if($this->isAdmin()){?>
							<input type="submit" name="login" value="Войти" class="btn btn-primary btn-small" /><br />
						<?}?>

						<?if($model->active){?>
							<input type="submit" name="disable" value="Деактивировать" class="btn btn-small btn-inverse" />
						<?}else{?>
							<input type="submit" name="enable" value="Задействовать" class="btn btn-small btn-inverse" />
						<?}?>
						<br/>
						<input type="submit" name="changePass" value="Сменить пароль" class="btn btn-small btn-danger" /><br />
					</form>
				</td>
			</tr>
		<?}?>
		</tbody>
	</table>
	
<?}else{?>
	юзеров не найдено
<?}?>