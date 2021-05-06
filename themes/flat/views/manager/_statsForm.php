<?
/**
 * @var string $returnUrl
 * @var array $params
 */

$dateTo = time()+24*3600;
$now = time();
$periods = [
	['dateFrom'=>date('d.m.Y', $now), 'dateTo'=>date('d.m.Y', $dateTo), 'name'=>'за сегодня'],
	['dateFrom'=>date('d.m.Y', $now - 3600*24), 'dateTo'=>date('d.m.Y'), 'name'=>'за вчера'],
	['dateFrom'=>date('d.m.Y', $now - 3600*24*7), 'dateTo'=>date('d.m.Y', $dateTo), 'name'=>'за неделю'],
	['dateFrom'=>date('d.m.Y', $now - 3600*24*30), 'dateTo'=>date('d.m.Y', $dateTo), 'name'=>'за месяц'],
]
?>

<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-filter"></i>выберите интервал</h3>
	</div>
	<div class="box-content nopadding">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<label for="textfield1" class="control-label col-sm-2">С</label>
				<input type="text" name="params[date_from]" value="<?=$params['date_from']?>" id="textfield1" class="form-control"/>
				<span class="help-block">с какого момента показать статистику</span>
			</div>
			<div class="form-group">
				<label for="textfield2" class="control-label col-sm-2">По</label>
				<input type="text" name="params[date_to]" value="<?=$params['date_to']?>" id="textfield2" class="form-control"/>
				<span class="help-block">по какой момент показать статистику</span>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="stats" value="Показать">Показать</button>
				<a href="<?=url($returnUrl)?>"><button type="button" class="btn">Отмена</button></a>
			</div>
		</form>
	</div>

	<?foreach($periods as $period){?>
		<form method="post" style="display: inline" >
			<input type="hidden" name="params[date_from]" value="<?=$period['dateFrom']?>" />
			<input type="hidden" name="params[date_to]" value="<?=$period['dateTo']?>" />
			<button type="submit" class="btn" name="stats" value="Показать"><?=$period['name']?></button>
		</form>
	<?}?>
</div>
