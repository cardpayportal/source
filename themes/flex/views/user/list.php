<?
/**
 * @var $models User[]
 */
$this->title = 'Юзеры'

?>

<h2><?=$this->title?></h2>

<p>
	<a href="<?=url('user/register')?>">Регистрация</a>

	<?if($this->isAdmin()){?>
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<a href="<?=url('user/register')?>">Цепочки юзеров</a>
	<?}?>
</p>

<p>Всего: <strong><?=count($models)?></strong></p>



<?if($models){?>

	<table class="std padding">
		<tr>
			<td>ID</td>
			<td>Логин</td>
			<td>Имя</td>
			<td>Роль</td>
			<td>Client</td>
			<td>Jabber</td>
			<td>Действие</td>
		</tr>
		<?foreach($models as $model){?>
			<tr
				style="
				<?if($model->active) {?>
					background-color:#DCFFE5;
				<?}else{?>
					background-color:#FF9394;
				<?}?>
				"
				>
				<td><?=$model->id?></td>
				<td><?=$model->login?></td>
				<td><?=$model->name?></td>
				<td><?=$model->roleStr?></td>
				<td>
					<?if($client = $model->client){?>
						<?=$client->name?> (id=<?=$client->id?>)
					<?}?>
				<td><?=$model->jabber?></td>
				</td>
				<td style="text-align:left;">
					<form method="post">
						<input type="hidden" name="params[id]" value="<?=$model->id?>" />

						<?if($this->isAdmin()){?>
							<input type="submit" name="login" value="Войти" /><br />
						<?}?>

						<?if($model->active){?>
							<input type="submit" name="disable" value="Деактивировать" />	
						<?}else{?>
							<input type="submit" name="enable" value="Задействовать" />
						<?}?>
						<br/>
						<input type="submit" name="changePass" value="Сменить пароль" /><br />
					</form>
				</td>
			</tr>
		<?}?>
	</table>
	
<?}else{?>
	юзеров не найдено
<?}?>