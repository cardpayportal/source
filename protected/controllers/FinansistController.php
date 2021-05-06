<?php

class FinansistController extends Controller
{
    public $defaultAction = 'index';

    public function actionIndex()
    {
        $this->redirect('control/manager');
    }

    /**
     * список кошельков для
     */
    public function actionAccountList()
    {
        if(!$this->isAdmin())
            $this->redirect(cfg('index_page'));

		$clientCond = '';

		if(!$this->isAdmin())
		{
			$user = User::getUser();
			$clientCond = " AND `client_id`='{$user->client->id}'";
		}

        $condition = "`type`='".Account::TYPE_OUT."' and `error`='' AND `enabled`=1".$clientCond;

        $models = Account::model()->findAll(array(
            'condition'=>$condition,
            'order'=>"`date_check` DESC",
        ));

        $info = Account::getInfo($condition);

        $this->render('account_list', array(
            'models'=>$models,
            'info'=>$info,
        ));
    }

    public function actionOrderList()
    {
        if(!$this->isModer() and !$this->isAdmin())
            $this->redirect(cfg('index_page'));

        $user = User::getUser();

		if($user->client->global_fin)
			$this->redirect(cfg('index_page'));

		$dateFormat = 'd.m.Y H:i';

        $params = $_POST['params'];

        $filter = ($_SESSION['filter']) ? $_SESSION['filter'] : array(
            'date_start'=>date('d.m.Y'),
            'date_end'=>date($dateFormat, time()+3600*24),
        );

        if($_POST['cancelOrder'])
        {
            $id = $params['id'];

            if(FinansistOrder::forCancel($id, $user->id))
            {
                $this->success('платеж ID='.$id.' поставлен на отмену, дождитесь изменения статуса');
                $this->redirect('finansist/orderList');
            }
            else
                $this->error('ошибка отмены платежа: '.FinansistOrder::$lastError);
        }
        elseif($_POST['filter'])
        {
            $filter = $_POST['filter'];

			$filter['to'] = trim($filter['to']);
        }

        $conditionArr = array();

        if(!$this->isAdmin())
            $conditionArr[] = "`client_id`='{$user->client_id}'";

        $conditionStr = '';

        if($filter)
        {
            $_SESSION['filter'] = $filter;

            if($filter['to'])
			{
				if(preg_match('!^[\+\d+]$!', $filter['to']))
					$conditionArr[] = "`to` LIKE '%".$filter['to']."%'";
				else
					$this->error('неверно указан телефон');
			}

            if($filter['date_start'])
            {
                $timestampStart = strtotime($filter['date_start']);

				if($timestampStart)
				{
					$filter['date_start'] = date($dateFormat, $timestampStart);
					$conditionArr[] = "`date_add` >= $timestampStart";
				}
				else
					$this->error('неверно указана дата');
            }

            if($filter['date_end'])
            {
				$timestampEnd = strtotime($filter['date_end']);

				if($timestampEnd)
				{
					$filter['date_end'] = date($dateFormat, $timestampEnd);
					$conditionArr[] = "`date_add` < $timestampEnd";
				}
				else
					$this->error('неверно указана дата');
            }

			if($filter['date_start'] and $filter['date_end'] and $timestampEnd <= $timestampStart)
				$this->error('неверно указана дата');
        }

        if($conditionArr)
            $conditionStr = implode(' AND ', $conditionArr);

        $models = FinansistOrder::model()->findAll(array(
            'condition'=>$conditionStr,
            'order'=>"`date_add` DESC",
        ));

        $todayStart = strtotime(date('d.m.Y'));
        $todayEnd = $todayStart + 3600*24;

        $yesterdayStart = $todayStart - 3600*24;
        $yesterdayEnd = $todayStart;


        //текущая неделюл (неделя начинается с субботы 5 утра)
        $curWeekEnd = 0;
        $curDayStart = $todayStart;

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

        //прошлая неделя
        $lastWeekEnd = $curWeekStart;
        $lastWeekStart = $lastWeekEnd - 3600*24*7;

        /*
        if($this->isAdmin())
        {
            echo date('d.m.Y H:i:s', $curWeekStart);
            echo '<br>';
            echo date('d.m.Y H:i:s', $curWeekEnd);
            echo '<br>';

            echo date('d.m.Y H:i:s', $lastWeekStart);
            echo '<br>';
            echo date('d.m.Y H:i:s', $lastWeekEnd);
            //die;

        }
        */

        //$weeks2Start = $todayStart - 3600*24*14;;
        //$weeks2End = $todayEnd;


        if(true)//$this->isFinansist() or $this->isAdmin()
        {
            $stats = array(
                'today'=>array(
                    'fact_amount'=>Transaction::outStats($todayStart, $todayEnd, $user->client_id),
                    'late_amount'=>Transaction::outStats($todayStart, $todayEnd, $user->client_id, true),
                ),
                'yesterday'=>array(
                    'fact_amount'=>Transaction::outStats($yesterdayStart, $yesterdayEnd, $user->client_id),
                    'late_amount'=>Transaction::outStats($yesterdayStart, $yesterdayEnd, $user->client_id, true),
                ),
                'curWeek'=>array(
                    'fact_amount'=>Transaction::outStats($curWeekStart, $curWeekEnd, $user->client_id),
                    'late_amount'=>Transaction::outStats($curWeekStart, $curWeekEnd, $user->client_id, true),
                ),
                'lastWeek'=>array(
                    'fact_amount'=>Transaction::outStats($lastWeekStart, $lastWeekEnd, $user->client_id),
                    'late_amount'=>Transaction::outStats($lastWeekStart, $lastWeekEnd, $user->client_id, true),
                ),
            );
        }


        $info = FinansistOrder::getInfo($models);

        $this->render('order_list', array(
            'models'=>$models,
            'stats'=>$stats,
            'info'=>$info,
            'filter'=>$filter,
        ));
    }

    public function actionOrderAdd()
    {
        if(!$this->isModer() and !$this->isAdmin())
            $this->redirect(cfg('index_page'));

        $user = User::getUser();

		if($user->client->global_fin)
			$this->redirect(cfg('index_page'));

        $params = $_POST['params'];

		if($_POST['setAmount50'])
		{
			$content = FinansistOrder::contentSetAmount(50000, $params['transContent']);

			if($content)
				$params['transContent'] = $content;
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['setAmount100'])
		{
			$content = FinansistOrder::contentSetAmount(100000, $params['transContent']);

			if($content)
				$params['transContent'] = $content;
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['setAmount25'])
		{
			$content = FinansistOrder::contentSetAmount(25000, $params['transContent']);

			if($content)
				$params['transContent'] = $content;
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['setAmountCustom']) {
			$content = FinansistOrder::contentSetAmount($_POST['setAmountValue'], $params['transContent']);

			if ($content)
				$params['transContent'] = $content;
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['add'])
        {
            $_SESSION['last_order_params'] = array();

            $params['user_id'] = $user->id;

            if($count = FinansistOrder::add($params))
            {
                $this->success('добавлено '.$count.' переводов');

				if(FinansistOrder::$lastError)
					$this->error(FinansistOrder::$lastError);

                $this->redirect('finansist/orderList');
            }
            elseif(FinansistOrder::$lastError=='same_payments')
            {
                $_SESSION['last_order_params'] = $params;

                $this->render('order_add_confirm', array(
                    'payments'=>FinansistOrder::$someData,
                ));
            }
            else
                $this->error(FinansistOrder::$lastError);
        }
        elseif($_POST['confirm'])
        {
            if(!$params = $_SESSION['last_order_params'])
            {
                $this->error('неверное перенаправление на странице, попробуйте добавить платежи заново');
                $this->redirect('finansist/orderList');
            }

            $_SESSION['last_order_params'] = array();

            if($count = FinansistOrder::add($params, true))
            {
                $this->success('добавлено '.$count.' переводов');

                $this->redirect('finansist/orderList');
            }
        }

        $this->render('order_add', array(
            'params'=>$params,
            'outAmount'=>Account::outAmount($user->client_id),
			'isGlobalFin'=>$user->client->global_fin,
        ));
    }

//
	/*
	 * список переводов globalFin
	 * фильтрация по клиенту
	 */
	public function actionGlobalOrderList()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$dateFormat = 'd.m.Y H:i';

		$user = User::getUser();

		$params = $_POST['params'];

		$filter = ($_SESSION['filter']) ? $_SESSION['filter'] : array(
			'date_start'=>date('d.m.Y'),
			'date_end'=>date($dateFormat, time()+3600*24),
		);

		$conditionArr = array();

		$conditionStr = '';

		if($filter)
		{
			$_SESSION['filter'] = $filter;

			if($filter['clientId'])
			{
				$conditionArr[] = "`client_id`='{$filter['clientId']}'";
			}

			if($filter['to'])
			{
				if(preg_match('![\d]+!', $filter['to']))
					$conditionArr[] = "`to` LIKE '%".$filter['to']."%'";
				else
					$this->error('неверно указан телефон');
			}

			if($filter['date_start'])
			{
				$timestampStart = strtotime($filter['date_start']);

				if($timestampStart)
				{
					$filter['date_start'] = date($dateFormat, $timestampStart);
					$conditionArr[] = "`date_add` >= $timestampStart";
				}
				else
					$this->error('неверно указана дата');
			}

			if($filter['date_end'])
			{
				$timestampEnd = strtotime($filter['date_end']);

				if($timestampEnd)
				{
					$filter['date_end'] = date($dateFormat, $timestampEnd);
					$conditionArr[] = "`date_add` < $timestampEnd";
				}
				else
					$this->error('неверно указана дата');
			}

			if($filter['date_start'] and $filter['date_end'] and $timestampEnd <= $timestampStart)
				$this->error('неверно указана дата');
		}

		if($conditionArr)
			$conditionStr = implode(' AND ', $conditionArr);


		$models = FinansistOrder::model()->findAll(array(
			'condition'=>$conditionStr,
			'order'=>"`date_add` DESC",
		));


		$selectedWallets = array();

		if($_POST['cancelOrder'])
		{
			$id = $params['id'];

			if(FinansistOrder::forCancel($id, $user->id))
			{
				$this->success('платеж ID='.$id.' поставлен на отмену, дождитесь изменения статуса');
				$this->redirect('finansist/globalOrderList');
			}
			else
				$this->error('ошибка отмены платежа: '.FinansistOrder::$lastError);
		}
		elseif($_POST['filter'])
		{
			$filter = $_POST['filter'];

			$filter['to'] = trim($filter['to']);

			$_SESSION['filter'] = $filter;
			$this->redirect('finansist/globalOrderList');
		}
		elseif($_POST['selectWallets'])
		{
			$selectedWallets = FinansistOrder::selectWallets($models, $_POST['ids']);

			if(FinansistOrder::$lastError)
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['selectWalletsComplete'])
		{
			$selectedWallets = FinansistOrder::selectWalletsComplete($models);

			if(FinansistOrder::$lastError)
				$this->error(FinansistOrder::$lastError);
		}

		$info = FinansistOrder::getInfo($models);

		$this->render('globalOrderList', array(
			'models'=>$models,
			'info'=>$info,
			'filter'=>$filter,
			'warnings'=>FinansistOrder::getWarnings(),
			'selectedWallets'=>$selectedWallets,
		));
	}

	/*
	 *
	 */
	public function actionGlobalOrderAdd()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		$params = $_POST['params'];

		if($_POST['setAmount50'])
		{
			$content = FinansistOrder::contentSetAmount(50000, $params['transContent']);

			if($content)
				$params['transContent'] = $content;
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['setAmount100'])
		{
			$content = FinansistOrder::contentSetAmount(100000, $params['transContent']);

			if($content)
				$params['transContent'] = $content;
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['setAmount25'])
		{
			$content = FinansistOrder::contentSetAmount(25000, $params['transContent']);

			if($content)
				$params['transContent'] = $content;
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['setAmount15'])
		{
			$content = FinansistOrder::contentSetAmount(15000, $params['transContent']);

			if($content)
				$params['transContent'] = $content;
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['setAmountCustom'])
		{
			$content = FinansistOrder::contentSetAmount($_POST['setAmountValue'], $params['transContent']);

			if($content)
				$params['transContent'] = $content;
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['add'])
		{
			$_SESSION['last_order_params'] = array();

			$params['user_id'] = $user->id;

			if($count = FinansistOrder::add($params))
			{
				$this->success('добавлено '.$count.' переводов');

				$this->redirect('finansist/globalOrderAdd');
			}
			elseif(FinansistOrder::$lastError=='same_payments')
			{
				$_SESSION['last_order_params'] = $params;

				$this->render('order_add_confirm', array(
					'payments'=>FinansistOrder::$someData,
				));
			}
			else
				$this->error(FinansistOrder::$lastError);
		}
		elseif($_POST['confirm'])
		{
			if(!$params = $_SESSION['last_order_params'])
			{
				$this->error('неверное перенаправление на странице, попробуйте добавить платежи заново');
				$this->redirect('finansist/globalOrderList');
			}

			$_SESSION['last_order_params'] = array();

			if($count = FinansistOrder::add($params, true))
			{
				$this->success('добавлено '.$count.' переводов');

				$this->redirect('finansist/globalOrderAdd');
			}
		}

		$sumOutBalance = Client::getSumOutBalance(false, true);
		$sumOutBalanceWithGroups = Client::getSumOutBalanceWithGroups(null, true);

		$this->render('globalOrderAdd', [
			'params'=>$params,
			'outAmount'=>$sumOutBalance,
			'outAmountWithGroups'=>$sumOutBalanceWithGroups,
		]);
	}

	public function actionCalculateList()
	{
		if(!$this->isFinansist())
			$this->redirect(cfg('index_page'));

		$user = User::getUser();

		$params = [];

		if($_POST['add'])
		{
			$params = $_POST['params'];

			$params['user_id'] = $user->id;

			//calcEmlConfirm
			if(YII_DEBUG)
			{
				if(ClientCalc::addFromClientTest($params))
				{

				}
			}
			else
			{
				if(ClientCalc::addFromClient($params))
				{
					$this->success('расчет отправлен на оплату');
					$this->redirect('finansist/calculateList');
				}
				else
					$this->error(ClientCalc::$lastError);
			}


		}

		$calcParams = ClientCalc::getCalcParams($user->client_id);

		$this->render('calculateList', [
			'models'=>ClientCalc::getList($user->client_id),
			'calcParams'=>$calcParams,
			'params'=>$params,
			'orders'=>$user->client->getNotPaidOrders(),
			'client'=>$user->client,
		]);

	}

	/**
	 *  история выборок кошельков с заявок
	 */
	public function actionSelectHistory()
	{
		if(!$this->isAdmin() and !$this->isGlobalFin())
			$this->redirect(cfg('index_page'));

		//ограничение по выдаче: за последние 7 дней
		$dateStart = strtotime(date('d.m.Y')) - 3600*24*7;	//чтобы небыло обрезка по пол сутки

		$this->render('selectHistory', array(
			'history'=>FinansistOrder::getSelectHistory($dateStart),
			'dateStart'=>$dateStart,
		));
	}

}