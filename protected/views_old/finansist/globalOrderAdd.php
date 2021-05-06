<?
/**
 * @var FinansistController $this
 * @var array $params
 * @var float $outAmount
 * @var array $outAmountWithGroups
 *
 */

$this->title = 'Перевод средств'
?>

<h2><?=$this->title?></h2>

<h5>Сейчас на кошельках: <?=formatAmount($outAmount, 0)?> руб</h5>

<form method="post">

	<p>
		<strong>Клиент:</strong>
		<select name="params[clientId]">
			<?foreach(Client::getArrWithGlobalFin() as $id=>$name){?>
				<option value="<?=$id?>"
					<?if($params['clientId']==$id){?>
						selected="selected"
					<?}?>
				><?=$name?> (баланс: <?=formatAmount(Client::getSumOutBalance($id), 0)?>)</option>
			<?}?>
		</select>

	</p>


	<p><strong>Список переводов: (каждый с новой строки)</strong></p>
	<p style="font-style: italic;">
		пример: <br />
		+79994527364;23444.21<br />
		+79494527364;111<br />
		+79494527364;111;flash - приоритетный платеж<br />
	</p>
	<textarea name="params[transContent]" cols="55" rows="10"><?=$params['transContent']?></textarea>

	<div class="payButtons">
		<input type="submit" name="setAmount25" value="25 000" class="blue">

		<input type="submit" name="setAmount50" value="50 000" class="orange">

		<input type="submit" name="setAmount100" value="100 000" class="green">

		<input type="text" name="setAmountValue" value="">
		<input type="submit" name="setAmountCustom" value="ok" class="custom">
	</div>

	
	<p>
		<strong>Комментарий:</strong><br />
		<input type="text" name="params[comment]" value="<?=$params['comment']?>" />
	</p>
	
	<p>
		<strong>Платежный пароль:</strong><br />
		<input type="password" name="params[extra]" value="<?=$params['extra']?>" >
	</p>
	
	<p>
		<input type="submit" name="add" value="Отправить" />
	</p>

	<h3>Подробно</h3>

	<table class="std padding">
		<tr>
			<td>Балансы цепочек</td>
			<?foreach(Account::getGroupArr() as $groupId=>$val){?>
				<td><?=$groupId?></td>
			<?}?>
		</tr>
		<?foreach($outAmountWithGroups as $clientId=>$arr){?>

			<tr>
				<td><b>Client<?=$clientId?></b></td>
				<?foreach($arr as $groupId=>$balance){?>
					<td>
						<?=formatAmount($balance, 0)?> руб
						<span class="wait" title="на сколько кошельков сливается с этой группы в данный момент">(<?=FinansistOrder::getWalletCountAtGroup($clientId, $groupId)?>)</span>
					</td>
				<?}?>
			</tr>

		<?}?>
	</table>

	
</form>

