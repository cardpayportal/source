<?$this->title = 'Кошельки'?>

    <h2><?=$this->title?></h2>

    <p>
        <strong>
            Ошибок: <?=$info['count_error']?><br /><br />
            Всего кошельков: <?=$info['count']?><br /><br />
            Суммарный баланс: <?=$info['balance_out']?>
        </strong>
    </p>

<?/*
<?if($models){?>
    <table class="std padding">

        <tr>
            <td>ID</td>
            <td>Логин</td>
			<?if($this->isAdmin()){?><td>Client</td><?}?>
            <td>Баланс</td>
            <?if($this->isAdmin()){?><td>Лимит</td><?}?>
            <td>Проверка</td>
            <td>Ошибка</td>
        </tr>

        <?foreach($models as $model){?>
            <tr>
                <td>
                    <?=$model->id?>
                </td>
                <td>
                    <a title="Последние платежи" href="<?=url('account/history', array('id'=>$model->id))?>">
                        <?=$model->login?><?if($transCount = $model->transactionCount){?> (<?=$transCount?>)<?}?>
                    </a>
                </td>
				<?if($this->isAdmin()){?><td><?=$model->client->id?></td><?}?>
                <td><?=$model->balanceStr?></td>
                <?if($this->isAdmin()){?><td><?=$model->limit_in?></td><?}?>
                <td><?=$model->dateCheckStr?></td>
                <td><font color="red"><?=$model->error?></font></td>
            </tr>
        <?}?>

    </table>
<?}else{?>
    аккаунтов не найдено
<?}?>
*/?>