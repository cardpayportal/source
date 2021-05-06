<?
/**
 * @var ControlController $this
 * @var Client[] $clients
 * @var array $params
 * @var int $managerOrderTimeout
 *

 */

$this->title = 'Настройка заявок';
?>

<form method="post">
	<p>
		<b>Время жизни заявок</b> (<i>влияет и на текущие заявки</i>)
		<br>
		<input type="text" name="managerOrderTimeout" value="<?=$managerOrderTimeout?>" size="10"> часов
	</p>

	<table class="table table-nomargin table-bordered table-colored-header">
		<thead>
			<th>Клиент</th>
			<th>Кол-во заявок на кл</th>
			<th>Сумма на заявку (макс)</th>
			<th>Сумма на кошелек (мин)</th>
			<th>Кол-во заявок на мана</th>
			<th>Тип приема</th>
			<th>Тип расчета</th>
		</thead>
		<tbody>
			<?foreach($clients as $client){?>
				<tr>
					<?
					$config = $client->orderConfig;
					$orderCountMax = ($params[$client->id]['client_order_count_max']) ? $params[$client->id]['client_order_count_max'] : $config['client_order_count_max'];
					$orderAmountMax = ($params[$client->id]['order_amount_max']) ? $params[$client->id]['order_amount_max'] : $config['order_amount_max'];
					$walletAmountMax = ($params[$client->id]['wallet_amount_min']) ? $params[$client->id]['wallet_amount_min'] : $config['wallet_amount_min'];
					$managerAmountMax = ($params[$client->id]['manager_order_count_max']) ? $params[$client->id]['manager_order_count_max'] : $config['manager_order_count_max'];
					?>

					<td><?=$client->name?></td>
					<td><input type="text" name="params[<?=$client->id?>][client_order_count_max]" value="<?=$orderCountMax?>" size="10"/></td>
					<td><input type="text" name="params[<?=$client->id?>][order_amount_max]" value="<?=$orderAmountMax?>" size="10"/></td>
					<td><input type="text" name="params[<?=$client->id?>][wallet_amount_min]" value="<?=$walletAmountMax?>" size="10"/></td>
					<td><input type="text" name="params[<?=$client->id?>][manager_order_count_max]" value="<?=$managerAmountMax?>" size="10"/></td>

					<td>
						<?
						$incomeMode = ($params[$client->id]['income_mode']) ? $params[$client->id]['income_mode'] : $client->income_mode;
						?>
						<select name="params[<?=$client->id?>][income_mode]">
							<?foreach(Client::incomeModeArr() as $key=>$mode){?>
								<option value="<?=$key?>"
								<?if($key == $incomeMode){?>
									selected="selected"
								<?}?>
								>
									<?=$mode?>
								</option>
							<?}?>
						</select>
					</td>

					<td>
						<?
						$calcMode = ($params[$client->id]['calc_mode']) ?
							$params[$client->id]['calc_mode'] : $client->calc_mode;
						?>
						<select name="params[<?=$client->id?>][calc_mode]">
							<?foreach(Client::calcModeArr() as $key=>$mode){?>
								<option value="<?=$key?>"
									<?if($key == $calcMode){?>
										selected="selected"
									<?}?>
								>
									<?=$mode?>
								</option>
							<?}?>
						</select>
					</td>

				</tr>
			<?}?>
		</tbody>

	</table>

	<button type="submit" class="btn btn-primary" name="save" value="save">Сохранить</button>
</form>