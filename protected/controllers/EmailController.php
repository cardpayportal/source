<?
class EmailController extends Controller
{

	public $defaultAction = 'list';

	public function actionList()
	{
		if (!$this->isAdmin())
			$this->redirect(cfg('index_page'));

		$params = $_POST['params'];

		if($_POST['add'])
		{
			$addCount = AccountEmail::addMany($params);

			$this->success('добавлено '.$addCount);

			if(AccountEmail::$lastError)
				$this->error(AccountEmail::$lastError);
			else
				$this->redirect('email/list');
		}

		$this->render('list', array(
			'models' => AccountEmail::modelArr('', 0, 100),
			'allCount' => AccountEmail::modelCount(),
			'freeCount' => AccountEmail::freeModelCount(),
			'workCount' => AccountEmail::workModelCount(),
			'notCheckCount' => AccountEmail::notCheckModelCount(),
			'params' => $params,
		));
	}
}