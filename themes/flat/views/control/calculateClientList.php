<?
/**
 * @var ClientCalc[] $models
 * @var array $params
 * @var array $filter
 * @var array $stats
 *
 */

$this->title = "Список расчетов";

?>


<h1><?=$this->title?></h1>

<form method="post">
	<p>
		<b>Клиент: (можно выбрать несколько)</b><br>
		<select name="params[clientId][]" multiple>
			<?foreach(Client::getActiveClients() as $clientModel){?>

				<?
				if(in_array($clientModel->id, $filter['clientIds']))
					$selected = "selected";
				else
					$selected = "";
				?>

				<option <?=$selected?> value="<?=$clientModel->id?>"><?=$clientModel->name?></option>
			<?}?>
		</select>

		<b>От</b> <input type="text" name="params[dateStart]" value="<?=$filter['dateStart']?>">
		<b>До</b> <input type="text" name="params[dateEnd]" value="<?=$filter['dateEnd']?>">

		<input type="submit" name="filter" value="найти">
	</p>
</form>

<?if($models){?>
	<p>
		<b><?=formatAmount($stats['amountRub'], 0)?></b> RUB,
		<b><?=formatAmount($stats['amountUsd'])?></b> USD,
		<b><?=formatAmount($stats['amountBtc'])?></b> BTC
	</p>

	<?$this->renderPartial('_calcList', ['models'=>$models])?>
<?}else{?>
	расчетов не найдено
<?}?>
