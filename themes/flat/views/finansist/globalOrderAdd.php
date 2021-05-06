<?
/**
 * @var FinansistController $this
 * @var array $params
 * @var float $outAmount
 * @var array $outAmountWithGroups
 *
 */

$this->title = 'Перевод средств';
?>

<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-bars"></i>Сейчас на кошельках: <?=formatAmount($outAmount, 0)?> руб</h3>
	</div>
	<div class="box-content">
		<form method="post" class="form-vertical form-bordered">
			<div class="form-group">
				<label for="textfield1" class="control-label">Клиент</label>
				<select name="params[clientId]" id="textfield1" class="form-control">
					<?foreach(Client::getArrWithGlobalFin() as $id=>$name){?>
						<option value="<?=$id?>"
							<?if($params['clientId']==$id){?>
								selected="selected"
							<?}?>
						><?=$name?> (баланс: <?=formatAmount(Client::getSumOutBalance($id, true), 0)?>)</option>
					<?}?>
				</select>
				<span class="help-block">
					список активных клиентов со включенным global_fin
				</span>
			</div>

			<div class="form-group">
				<label for="textfield2" class="control-label">Список переводов</label>
				<textarea name="params[transContent]" cols="55" rows="10" id="textfield2" class="form-control"><?=$params['transContent']?></textarea>

				<span class="help-block">
					каждый с новой строки
					пример: <br />
					+79494527364;111<br />
					+79494527364;111.21;flash - приоритетный платеж<br />
					410013647673235;3000 - на яндекс<br />
				</span>
			</div>

			<div class="form-group">
				<label for="textfield3" class="control-label">Сумма для всех кошельков</label>


				<button type="submit" class="btn btn-small blue" name="setAmount25" value="25 000">25 000</button>
				<button type="submit" class="btn btn-small orange" name="setAmount50" value="50 000">50 000</button>
				<button type="submit" class="btn btn-small green" name="setAmount15" value="15 000">15 000</button>

				<input type="text" name="setAmountValue" value="" id="textfield3" class="amountCustom" placeholder="10472">
				<button type="submit" class="btn btn-small blue" name="setAmountCustom" value="ok">ok</button>

				<span class="help-block">выберите нужную сумму, либо вбейте сумму в поле и нажмите ok</span>
			</div>

			<div class="form-group">
				<label for="textfield4" class="control-label">Платежный пароль</label>
				<input type="password" name="params[extra]" value="<?=$params['extra']?>" id="textfield4" class="form-control">
				<span class="help-block">был выдан при регистрации</span>
			</div>


			<div class="form-group">
				<label for="textfield5" class="control-label">Комментарий</label>
				<input type="password" name="params[comment]" value="<?=$params['comment']?>" id="textfield5" class="form-control">
				<span class="help-block">
					произвольный комментарий для получателя пллатежей
				</span>
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn-primary" name="add" value="Добавить">Добавить</button>
				<a href="<?= url('finansist/globalOrderList') ?>">
					<button type="button" class="btn">Отмена</button>
				</a>
			</div>
		</form>
	</div>
</div>

<div class="box box-bordered">
	<div class="box-title">
		<h3><i class="fa fa-bars"></i>Подробно по балансам (<i>обновляется каждые 5 мин</i>)</h3>
	</div>
	<div class="box-content">
		<table class="table table-bordered">
			<thead>
				<th>Балансы цепочек</th>
				<?foreach(Account::getGroupArr() as $groupId=>$val){?>
					<th><?=$groupId?></th>
				<?}?>
			</thead>
			<tbody>
				<?foreach($outAmountWithGroups as $clientId=>$arr){?>
					<tr>
						<td><b><?=Client::getModel($clientId)->name?></b></td>
						<?foreach($arr as $groupId=>$balance){?>
							<td>
								<?=formatAmount($balance, 0)?> руб
								<span class="wait" title="на сколько кошельков сливается с этой группы в данный момент">
									(<?=FinansistOrder::getWalletCountAtGroup($clientId, $groupId, true)?>)
								</span>
							</td>
						<?}?>
					</tr>
				</tbody>
			<?}?>
		</table>
	</div>
</div>

