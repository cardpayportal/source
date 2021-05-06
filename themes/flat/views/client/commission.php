<?
/**
 * @var ClientCommission[] $models
 * @var array $params
 */
$this->title = 'Комиссии клиентов';
?>
<div class="box box-bordered">
	<div class="box-title">
		<h3>
			<i class="fa fa-bars"></i>Список правил
		</h3>
	</div>
	<div class="box-content">
		<form method="post">
			<p>
				<button type="submit" class="btn btn-primary" name="save" value="Сохранить все">Сохранить все</button>
			</p>

			<table class="table table-bordered table-striped table-colored-header">
				<thead>
					<th>№</th>
					<th>Клиент</th>
					<th>Источник</th>
					<th>Процент</th>
					<th>Бонус руб</th>
					<th><span class="withComment" title="если задан, то курс всегда постоянный">Фикс usd</span></th>
					<td><span class="withComment" title="Доп процент при оплате картой на яндексе">Яд процент</span></td>
					<th>Активен</th>
					<th>Изменен</th>
					<th>Курс</th>
				</thead>
				<tbody>
				<?foreach($models as $model){?>
					<tr class="<?if($model->is_active){?>success<?}else{?>error<?}?>">
						<td><?=$model->id?></td>
						<td><b><?=$model->clientStr?></b></td>
						<td><?=$model->rateSourceSelect?></td>
						<td><input type="text" name="params[<?=$model->id?>][bonus_percent]" value="<?=$model->bonus_percent?>" size="10" class="form-control"/></td>
						<td><input type="text" name="params[<?=$model->id?>][bonus_rub]" value="<?=$model->bonus_rub?>" size="10" class="form-control"/></td>
						<td><input type="text" name="params[<?=$model->id?>][fix]" value="<?=$model->fix?>" size="10" class="form-control"/></td>
						<td><input type="text" name="params[<?=$model->id?>][ym_card_percent]" value="<?=$model->ym_card_percent?>" size="10"/></td>
						<td><?=$model->isActiveSelect?></td>
						<td><?=$model->dateEditStr?></td>
						<td><?=$model->rateValue?></td>
					</tr>
				<?}?>
				</tbody>
			</table>

			<p>
				<i>Примечание: из результата вычитается 1%</i>
			</p>
		</form>
	</div>
</div>




<div class="box box-bordered">
	<div class="box-title">
		<h3>
			<i class="fa fa-bars"></i>Добавить правило
		</h3>
	</div>
	<div class="box-content">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<label for="textfield1" class="control-label">Клиент</label>
				<select name="params[client_id]" class="form-control" id="textfield1">
					<?foreach(Client::getArr() as $id=>$name){?>
						<option value="<?=$id?>" <?=($params['client_id'] == $id) ? 'selected="selected"': ''?>><?=$name?></option>
					<?}?>
				</select>
			</div>

			<div class="form-group">
				<label for="textfield2" class="control-label">Источник</label>

				<select name="params[rate_source]" class="form-control" id="textfield2">
					<?foreach(ClientCommission::rateSourceArr() as $id=>$name){?>
						<option value="<?=$id?>" <?=($params['rate_source'] == $id) ? 'selected="selected"': ''?>><?=$name?></option>
					<?}?>
				</select>
			</div>

			<div class="form-group">
				<label for="textfield3" class="control-label">Процент</label>
				<input type="text" name="params[bonus_percent]" value="<?=$params['bonus_percent']?>" size="10" class="form-control" id="textfield3">
			</div>

			<div class="form-group">
				<label for="textfield4" class="control-label">Бонус (руб)</label>
				<input type="text" name="params[bonus_rub]" value="<?=$params['bonus_rub']?>" class="form-control" id="textfield4">
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="add" value="Добавить">Добавить</button>
				<a href="<?=url(cfg('index_page'))?>"><button type="button" class="btn">Отмена</button></a>
			</div>
		</form>
	</div>
</div>