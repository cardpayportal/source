<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var int $freeCountHalf
 * @var int $freeCountFull
 * @var string $incomeMode	режим приема
 * @var array $orderConfig	для заявочной системы
 * @var int $apiCountHalf	для выдачи апи кошельков
 * @var int $apiCountFull	для выдачи апи кошельков
 */

$this->title = 'Получить кошельки'
?>
<div class="box box-bordered">
	<div class="box-title">
		<h3>
		<i class="fa fa-bars"></i>Новые кошельки</h3>
	</div>
	<div class="box-content nopadding">
		обычных: <?=$freeCountHalf?>
		, идентифицированных: <?=$freeCountFull?>
		, с паролями: <?=$apiCountHalf?>
		, идент с паролями: <?=$apiCountFull?>
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<label for="textfield1" class="control-label">Кол-во кошельков</label>
				<input type="text" name="params[count]" value="<?=($params['count']) ? $params['count'] : 1?>" id="textfield1" class="form-control"/>
				<span class="help-block">
					минимум 1, максимум 10
				</span>
			</div>
			<div class="form-group">
				<label for="textfield2" class="control-label">Комментарий (метка)</label>
				<input type="text" name="params[label]" value="<?=$params['label']?>" id="textfield2" class="form-control"/>
				<span class="help-block">
					текст
				</span>
			</div>
			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="add" value="Получить обычные кошельки">Получить обычные кошельки</button>

				<?if($freeCountFull > 0){?>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<button type="submit" class="btn btn-success" name="add_full" value="Получить идент кошельки" title="Лимит идент кошельков 10 000 000">Получить идент кошельки</button>
				<?}?>


				<?if($apiCountHalf > 0){?>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<button type="submit" class="btn btn-warning" name="addApiHalf" value="Получить обычные кошельки с паролями" title="">Обычные с паролями</button>
				<?}?>

				<?if($apiCountFull > 0){?>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<button type="submit" class="btn btn-teal" name="addApiFull" value="Получить идент кошельки с паролями" title="">Идент кошельки с паролями</button>
				<?}?>
			</div>
		</form>
	</div>
</div>
