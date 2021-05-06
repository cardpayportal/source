<?$this->title = 'Панель управления'?>

<h2><?=$this->title?></h2>

<form method="post" >
	<p>
		<input type="submit" name="clear_account_cookies" value="Удалить куки всех аккаунтов" />
	</p>

    <p>
		<syrong>Нагрузка на систему:</syrong><?if(PHP_OS != 'WINNT') {$process = sys_getloadavg(); echo $process[2];}?>
	</p>

	
</form>

<h2>BalanceOut</h2>

<p><strong>Всего выведено: </strong><?=formatAmount(config('balance_out_amount'))?></p>

<?if($balanceOutModels){?>

	<table class="std padding">
		<tr>
			<td>ID</td>
			</a>Логин</a>
			<td>Баланс</td>
			<td>Ошибка</td>
		</tr>

		<?foreach($balanceOutModels as $model){?>
			<tr>
				<td><?=$model->id?></td>
				<td>
					<a target="_blank" href="<?=url('account/historyAdmin', array('id'=>$model->id))?>"><?=$model->login?></a>
				</td>
				<td><?=$model->balanceStr?></td>
				<td><?=$model->error?></td>
			</tr>
		<?}?>
	</table>

	<strong>Всего: </strong><?=formatAmount($balanceOutAmount, 0)?></p>

	<form method="post" >
		<p>
			<input type="submit" name="balance_out" value="Вывести"/>
		</p>
</form>

<?}?>