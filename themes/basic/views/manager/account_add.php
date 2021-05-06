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

<h2><?=$this->title?></h2>

<form method="post">
	<p>
		<b>Свободных:</b>
		обычных: <?=$freeCountHalf?>
		, идентифицированных: <?=$freeCountFull?>
		, с паролями: <?=$apiCountHalf?>
		, идент с паролями: <?=$apiCountFull?>
	</p>

	<p>
		<strong>Кол-во кошельков: (1-10)</strong><br />
		<input size="10" type="text" name="params[count]" value="<?=($params['count']) ? $params['count'] : 1?>" />
	</p>

	<p>
		<strong>Комментарий:</strong><br />
		<input size="100" type="text" name="params[label]" value="<?=$params['label']?>" />
	</p>

	<p>
		<input type="submit" name="add" value="Получить обычные кошельки" style="" />

		<?if($freeCountFull > 0){?>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="submit" name="add_full" value="Получить идент кошельки" title="Лимит идент кошельков 10 000 000" style="background-color: #33CC00" />
		<?}?>

		<?if($apiCount > 0){?>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

		<?}?>

		<?if($apiCountHalf > 0){?>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="submit" name="addApiHalf" value="Обычные с паролями" title="" style="background-color: #ccb373" />
		<?}?>

		<?if($apiCountFull > 0){?>
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="submit" name="addApiFull" value="Идент кошельки с паролями" title="" style="background-color: #00aba9" />
		<?}?>
	</p>
</form>
