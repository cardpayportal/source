<?
/**
 * @var UserController $this
 * @var User[] $users
 * @var array $groups
 */

$this->title = 'Цепочки слива'

?>

<h1><?=$this->title?></h1>


<?if($users){?>

	<p><b>Всего: </b><?=count($users)?> юзера</p>

	<form method="post">

		<p>
			<input type="submit" name="save" value="Сохранить">
		</p>

		<table class="std padding">
			<thead>
				<tr>
					<td></td>
					<td>Клиент<br>
						(кол-во цепочек)
					</td>
					<td>Логин</td>
					<td>Цепочка</td>
					<td>
						<span class="withComment" title="Сколько у клиента юзеров с той же цепочкой">
							Кол-во повторов
						</span>
					</td>
				</tr>
			</thead>

			<tbody>
				<?foreach($users as $key=>$user){?>
					<tr>
						<td><?=($key + 1)?></td>
						<td>
							<?=$user->client->name?><br>
							5
						</td>
						<td><?=$user->login?></td>
						<td><input type="text" name="groups[<?=$user->id?>]" value="<?=$user->group_id?>" size="3"></td>
						<td><?=$user->groupRepeatCount?></td>
					</tr>
				<?}?>
			</tbody>

		</table>
	</form>
<?}else{?>
	активных пользователей с возможностью добавления групп не найдено
<?}?>
