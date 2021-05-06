<?$this->title = 'Приход';?>


<h1><?=$this->title?></h1>


<?//if($this->isControl() or $this->isAdmin() or $this->isFinansist()){?>
    <p>
        <form method="post">
            с <input type="text" name="params[date_from]" value="<?=$params['date_from']?>" />
            до <input type="text" name="params[date_to]" value="<?=$params['date_to']?>" />
            <input  type="submit" name="stats" value="Показать"/>
        </form>
    </p>
<?//}?>


<p>
<form method="post" style="display: inline">
    <input type="hidden" name="params[date_from]" value="<?=date('d.m.Y')?>" />
    <input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', time()+24*3600)?>" />
    <input  type="submit" name="stats" value="За сегодня"/>
</form>

&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<form method="post" style="display: inline">
    <input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', time()-24*3600)?>" />
    <input type="hidden" name="params[date_to]" value="<?=date('d.m.Y')?>" />
    <input  type="submit" name="stats" value="За вчера"/>
</form>

&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<?
//текущая неделю (неделя начинается с субботы 5 утра)
$curWeekEnd = 0;
$curDayStart = strtotime(date('d.m.Y'));

//если сегодня суббота чтобы считал правильно
if(date('w')==6 and $curDayStart > time())
    $curDayStart += 3600*24;
elseif(date('w')==6 and $curDayStart < time())
    $curDayStart -= 3600*24;

for($i = $curDayStart;$i<=$curDayStart+3600*24*7 ; $i += 3600*24)
{
    if(date('w', $i)==6)
    {
        $curWeekEnd = $i;
        break;
    }
}

$curWeekStart = $curWeekEnd - 3600*24*7;

?>

<form method="post" style="display: inline">
    <input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', $curWeekStart)?>" />
    <input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', $curWeekEnd)?>" />
    <input  type="submit" name="stats" value="За неделю"/>
</form>

<?if($this->isControl() or $this->isAdmin() or $this->isFinansist()){?>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <form method="post" style="display: inline">
        <input type="hidden" name="params[date_from]" value="<?=date('d.m.Y', time()-24*30*3600)?>" />
        <input type="hidden" name="params[date_to]" value="<?=date('d.m.Y', time()+24*3600)?>" />
        <input  type="submit" name="stats" value="За месяц"/>
    </form>
<?}?>

</p>

<p>
	<strong>Всего принято: </strong>
	<?=formatAmount($allAmount, 0)?>
	<?if($ratAmount){?>
		<span class="ratTransaction withComment" title="Минусы">-<?=formatAmount($ratAmount)?></span>
		=
		<span class="withComment green" title="Итог"><strong><?=formatAmount($allAmountWithRat, 0)?></strong></span>
	<?}?>

</p>

<table class="std padding">

    <tr>
        <td>Пользователь</td>
        <td>Сумма за период</td>
        <td>Кошельки</td>
    </tr>


    <?foreach($result as $id=>$arr1){?>
        <tr>
            <td><strong><?=$arr1['name']?></strong></td>
            <td>
				<?=formatAmount($arr1['amount'], 0)?>
			</td>
            <td>
                <?if($arr1['children']){?>
                    <table class="std padding">
                        <?foreach($arr1['children'] as $child1){?>
                            <tr>
                                <td><strong><?=$child1['name']?></strong></td>
                                <td><?=formatAmount($child1['amount'], 0)?></td>

                                <td style="text-align: left;">
                                    <?if($child1['children']){?>
                                        <table class="std padding">
                                            <?foreach($child1['children'] as $child2){?>
                                                <tr>

													<?
														$childAmount = 0;

														foreach($child2['stats'] as $wallet=>$amount)
															$childAmount += $amount;
													?>

                                                    <td>
														<strong><?=$child2['name']?></strong>
														<br/><?=formatAmount($childAmount, 0)?></td>
                                                    <td style="text-align: left">
                                                        <?foreach($child2['stats'] as $wallet=>$amount){?>
                                                            <?=$wallet?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?=formatAmount($amount, 0)?><br/>
                                                        <?}?>
                                                    </td>
                                                </tr>
                                            <?}?>
                                        </table>
                                    <?}else{?>
                                        <?foreach($child1['stats'] as $wallet=>$amount){?>
                                            <?=$wallet?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?=formatAmount($amount, 0)?><br/>
                                        <?}?>
                                    <?}?>
                                </td>
                            </tr>
                        <?}?>
                    </table>
                <?}elseif($arr1['wallets']){?>
					<span style="text-align: left">
						<?foreach($arr1['wallets'] as $wallet=>$amount){?>
							<?=$wallet?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?=formatAmount($amount, 0)?><br/>
						<?}?>
					</span>
				<?}?>
            </td>
        </tr>

    <?}?>

</table>


<?if($ratTransactions){?>

	<h2>Минусы (<?=count($ratTransactions)?>) <span class="ratTransaction">-<?=formatAmount($ratAmount, 0)?> руб</span></h2>

	<table class="noBorder trHeight" style="margin-left: 10px; width: 100%;">
		<?foreach($ratTransactions as $num=>$trans){?>
			<?=$this->renderPartial('//manager/_transaction', array(
				'num'=>$num,
				'trans'=>$trans,
				'showLogin'=>true,
			))?>
		<?}?>
	</table>

	<?if($num > 2){?>
		<br /><button class="showTransactions">Показать все</button>
	<?}?>
<?}?>
