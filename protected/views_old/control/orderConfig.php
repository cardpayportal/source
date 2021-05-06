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

<h1><?=$this->title?></h1>


<form method="post">
	<p>
		<b>Время жизни заявок</b> (<i>влияет и на текущие заявки</i>)
		<br>
		<input type="text" name="managerOrderTimeout" value="<?=$managerOrderTimeout?>" size="10"> часов
	</p>

	<table class="std padding">
		<tr>
			<td>Клиент</td>
			<td>Кол-во заявок на кл</td>
			<td>Сумма на заявку (макс)</td>
			<td>Сумма на кошелек (мин)</td>
			<td>Кол-во заявок на мана</td>
			<td>Тип приема</td>
		</tr>

		<?foreach($clients as $client){?>
			<tr>
				<?
				$config = $client->orderConfig;
				$orderCountMax = ($params[$client->id]['client_order_count_max']) ? $params[$client->id]['client_order_count_max'] : $config['client_order_count_max'];
				$orderAmountMax = ($params[$client->id]['order_amount_max']) ? $params[$client->id]['order_amount_max'] : $config['order_amount_max'];
				$walletAmountMax = ($params[$client->id]['wallet_amount_min']) ? $params[$client->id]['wallet_amount_min'] : $config['wallet_amount_min'];
				$managerAmountMax = ($params[$client->id]['manager_order_count_max']) ? $params[$client->id]['manager_order_count_max'] : $config['manager_order_count_max'];
				?>

				<td><?=$client->id?></td>
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

			</tr>
		<?}?>

	</table>

	<p><input type="submit" name="save" value="Сохранить"></p>
</form>