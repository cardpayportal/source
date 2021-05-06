<?
/**
 * @var ClientCommission[] $models
 * @var array $params
 */
$this->title = 'Комиссии клиентов';

?>


<h1><?=$this->title?></h1>

<form method="post">

	<p>
		<input type="submit" name="save" value="Сохранить все">
	</p>

	<table class="std padding">
		<tr>
			<td>№</td>
			<td>Клиент</td>
			<td>Источник</td>
			<td>Процент</td>
			<td>Бонус руб</td>
			<td><span class="withComment" title="если задан, то курс всегда постоянный">Фикс usd</span></td>
			<td>Активен</td>
			<td>Изменен</td>
			<td>Курс</td>
		</tr>

		<?foreach($models as $model){?>
			<tr class="<?if($model->is_active){?>success<?}else{?>error<?}?>">
				<td><?=$model->id?></td>
				<td><b><?=$model->clientStr?></b></td>
				<td><?=$model->rateSourceSelect?></td>
				<td><input type="text" name="params[<?=$model->id?>][bonus_percent]" value="<?=$model->bonus_percent?>" size="10"/></td>
				<td><input type="text" name="params[<?=$model->id?>][bonus_rub]" value="<?=$model->bonus_rub?>" size="10"/></td>
				<td><input type="text" name="params[<?=$model->id?>][fix]" value="<?=$model->fix?>" size="10"/></td>
				<td><?=$model->isActiveSelect?></td>
				<td><?=$model->dateEditStr?></td>
				<td><?=formatAmount($model->rateValue, 2)?></td>
			</tr>
		<?}?>
	</table>

	<p>
		<i>Примечание: из результата вычитается 1%</i>
	</p>

</form>



<h2>Добавить правило</h2>


<form method="post">

	<p>
		<b>Клиент</b><br>
		<select name="params[client_id]">
			<?foreach(Client::getArr() as $id=>$name){?>
				<option value="<?=$id?>" <?=($params['client_id'] == $id) ? 'selected="selected"': ''?>><?=$name?></option>
			<?}?>
		</select>
	</p>

	<p>
		<b>Источник</b><br>
		<select name="params[rate_source]">
			<?foreach(ClientCommission::rateSourceArr() as $id=>$name){?>
				<option value="<?=$id?>" <?=($params['rate_source'] == $id) ? 'selected="selected"': ''?>><?=$name?></option>
			<?}?>
		</select>
	</p>

	<p>
		<b>Процент</b><br>
		<input type="text" name="params[bonus_percent]" value="<?=$params['bonus_percent']?>" size="10">%
	</p>

	<p>
		<b>Бонус</b><br>
		<input type="text" name="params[bonus_rub]" value="<?=$params['bonus_rub']?>"> руб
	</p>

	<p>
		<input type="submit" name="add" value="Добавить">
	</p>

</form>