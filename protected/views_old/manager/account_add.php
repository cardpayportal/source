<?
/**
 * @var ManagerController $this
 * @var array $params
 * @var int $freeCountHalf
 * @var int $freeCountFull
 * @var string $incomeMode	режим приема
 * @var array $orderConfig	для заявочной системы
 */

$this->title = 'Получить кошельки'
?>

<h2><?=$this->title?></h2>

<form method="post">
	<p>
		<b>Свободных:</b>
		обычных: <?=$freeCountHalf?>, идентифицированных: <?=$freeCountFull?>
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
		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" name="add_full" value="Получить идент кошельки" title="Лимит идент кошельков 10 000 000" style="background-color: #33CC00" />
	</p>
</form>
