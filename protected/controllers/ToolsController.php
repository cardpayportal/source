<?php

/*
 * полезные скрипты на каждый день
 */
class ToolsController extends Controller
{
	public function beforeAction($action)
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		return parent::beforeAction($action);
	}

	public function actionIndex()
	{
		if(!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];
		$result = array();

		if($_POST['timestamp'])
		{
			$result['timestamp'] = strtotime($params['timestampStr']);
		}
		elseif($_POST['date'])
		{
			$result['timestamp'] = date('d.m.Y H:i:s', $params['timestampStr']);
		}
		elseif($_POST['formatProxy'])
		{
			preg_match_all('!((\d+\.\d+\.\d+\.\d+)(:\d+|))!', $params['proxyStr'], $res);

			$result['proxy'] = '';

			foreach($res[2] as $key=>$ip)
			{
				if(!trim($ip))
					continue;

				$result['proxy'] .= "\n".$params['proxyLogin']
					.':'.$params['proxyPass']
					.'@'.$ip;

				if($res[3][$key])
					$result['proxy'] .= "{$res[3][$key]}";
				else
					$result['proxy'] .= ":{$params['proxyPort']}";

			}

			$result['proxy'] = trim($result['proxy']);
		}
		elseif($_POST['universalCondition'])
		{
			$result['content'] = '';

			foreach(explode("\n", $params['content']) as $row)
			{
				$row = trim($row);
				if(!$row) continue;

				$result['content'] .= "\n"."'$row',";
			}

			$result['content'] = trim($result['content'], ',');
		}

		if(!$_POST['timestamp'] and !$_POST['date'])
		{
			$params['timestampStr'] = date('d.m.Y H:i');
		}

		$this->render('index', [
			'params' => $params,
			'result' => $result,
		]);
	}

}
