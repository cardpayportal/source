<?php

/**
 * Class DataBackup
 * В автоматическом режиме создает бэкапы файлов и базы данных с последующей отправкой на другой сервер
 * и уведомлением по email
 *
 * !!!ПРИ ИСПОЛЬЗОВАНИИ УБЕДИТЬСЯ ЧТО ДИРРЕКТОРИЯ НА СЕРВЕРЕ ГДЕ БУДУТ ХРАНИТЬСЯ БЭКАПЫ ИМЕЕТ ПРАВА ДЛЯ РАБОТЫ ОТ
 * АПАЧА (chown -R apache:apache /путь/к папке)
 * ПАПКА, ГДЕ ХРАНЯТСЯ БЭКАПЫ НА ЗАПУСКАЕЩЕМ СКРИПТ СЕРВЕРЕ НЕ ДОЛЖНА СОДЕРЖАТЬ ПОСТОРОННИЕ ФАЙЛЫ!!!
 * Процесс работы:
 * 1. Создание бэкапа файлов
 * 2. Создание бэкапа базы данных
 * 3. Удаление всех бэкапов давнее заданного времени на сервере, где запущен скрипт
 * 4. Удаление бэкапов файлов на удаленном сервере
 * 5. Копирование бэкапа файлов на удаленный сервер
 * 6. Удаление бэкапов базы данных давнее заданного времени на сервере, где запущен скрипт
 * 4. Удаление бэкапа базы данных на удаленном сервере
 * 5. Копирование бэкапа базы данных на удаленный сервер
 * 6. Отправка email с результатами работы скрипта
 *
 * @property string backupFolder - папка где будут храниться бэкапы
 * @property string backupName - название бэкапа без даты и расширений
 * @property string backupNameFile - файл бэкапа фс
 * @property string backupNameSql - файл бэкапа бд
 * @property string targetDirWithFiles - папка которую будем бекапить
 * @property string delayDelete - время через которое будут удаляться старые бекапы в часах
 * @property string dbHost - хост
 * @property string dbUser - пользователь mysql
 * @property string dbPassword - пароль mysql
 * @property string dbName - название бд
 * @property string maxTableSizeMB - максимальный размер таблиц бд, которые попадут в бэкап
 * @property string mailTo - получатель email
 * @property string mailFrom - отправитель email
 * @property string mailSubject - тема email
 * @property string remoteServerIp - адрес удаленного сервера куда будем копировать бэкапы
 * @property string remoteServerPort - его порт
 * @property string remoteBackupFolder - папка бэкапов на удаленном сервере
 * @property string remoteServerUserName - пользователь под которым входим на удаленный сервер
 * @property string remoteServerPass - пароль пользователя на удаленном сервере
 *
 */

class DataBackup implements DataBackupInterface
{
	const ERROR_CREATE_BACKUP_MYSQL = 'Ошибка создания бэкапа базы данных';
	const ERROR_CREATE_BACKUP_FILES = 'Ошибка создания бэкапа файлов';
	const ERROR_THREAD_ALLREADY_RUN = 'Поток уже запущен';
	const ERROR_CONNECTION = 'Ошибка подключения к удаленному серверу для копирования бэкапов';
	const ERROR_REMOTE_COPY_BACKUP = 'Ошибка копирования бэкапа на удаленный сервер';
	const ERROR_DELETE_REMOTE_FILES = 'Ошибка очистки файлов на удаленном сервере';
	const ERROR_AUTH = 'Ошибка аутентификации при отправке бэкапа';
	const ERROR_SEND_EMAIL = 'Ошибка отправки email';

	const SUCCESS_CREATED_BACKUP_FILES = 'Создан бэкап файлов';
	const SUCCESS_CREATED_BACKUP_MYSQL = 'Создан бэкап базы данных';
	const SUCCESS_REMOTE_COPY_BACKUP = 'Бэкап скопирован на удаленный сервер';

	const SCRIPT_EXECUTION_TIME = 'Время выполнения скрипта';
	const START_PROCEDURE_BACKUP = 'Начата процедура бэкапа';
	const END_PROCEDURE_BACKUP = 'Закончена процедура бэкапа';
	const NO_FILES_TO_DELETE = 'Нет бэкапов для удаления';
	const NO_TABLES_WITH_NEEDED_SIZE = 'Нет таблиц подходящего размера';
	const DELETED_OLD_FILES = 'Очищены старые бэкапы';

	private $_backupFolder = '';		// куда будут сохранятся файлы
	private $_backupNameSql = '';		// название дампа
	private $_fullNameBackupSql = '';	// название дампа плюс путь к нему
	private $_backupNameFile = '';		// название архива
	private $_fullNameBackupFile = '';	// название архива плюс путь к нему
	private $_targetDirWithFiles = '';	// папка которую будем бэкапить
	private $_delayDelete = '';			// время жизни архива (в секундах)

	private $_remoteServerIp = '';		// ip удаленного серверу куда скинем бэкап
	private $_remoteServerPort = '';	// его порт
	private $_remoteBackupFolder = '';	// папка для бэкапа на удаленном сервере
	private $_remoteServerUserName = '';// пользователь под которым устанавливаем ssh соединение для копирования
	private $_remoteServerPass = '';	// пароль для входа

	private $_dbHost = '';
	private $_dbUser = '';
	private $_dbPassword = '';
	private $_dbName = '';
	private $_maxTableSizeMB = '';

	private $_mailTo = '';
	private $_mailFrom = '';
	private $_mailMessage = '';
	private $_mailSubject = '';

	public function __construct(){}

	/**
	 * @param $value
	 * задаем куда будут сохранятся файлы
	 */
	public function setBackupFolder(string $value)
	{
		$this->_backupFolder = $value;
	}

	/**
	 * @return string
	 */
	public function getBackupFolder() : string
	{
		return $this->_backupFolder ? $this->_backupFolder : '/var/www/backup';
	}

	/**
	 * @param $value
	 * задаем название архива
	 */
	public function setBackupNameFile(string $value)
	{
		$this->_backupNameFile = $value;
	}

	/**
	 * @return string
	 * название архива
	 */
	public function getBackupNameFile() : string
	{
		return $this->_backupNameFile . '_'. date("d-m-Y") . '_files.tar.gz';
	}

	/**
	 * @param $value
	 * задаем название дампа
	 */
	public function setBackupNameSql(string $value)
	{
		$this->_backupNameSql = $value;
	}

	/**
	 * @return string
	 * название дампа
	 */
	public function getBackupNameSql() : string
	{
		return $this->_backupNameSql . '_'. date("d-m-Y") . '_sql.gz';
	}

	/**
	 * @param $value
	 * задаем что бэкапим
	 */
	public function setTargetDirWithFiles(string $value)
	{
		$this->_targetDirWithFiles = $value;
	}

	/**
	 * @return string
	 * что бэкапим
	 */
	public function getTargetDirWithFiles() : string
	{
		return $this->_targetDirWithFiles ? $this->_targetDirWithFiles : '/var/www/html';
	}

	/**
	 * @param $value
	 */
	public function setRemoteServerIp(string $value)
	{
		$this->_remoteServerIp = $value;
	}

	/**
	 * @return string
	 */
	public function getRemoteServerIp()
	{
		return $this->_remoteServerIp;
	}

	/**
	 * @param $value
	 */
	public function setRemoteServerPort(int $value)
	{
		$this->_remoteServerPort = $value;
	}

	/**
	 * @return int
	 */
	public function getRemoteServerPort() : int
	{
		return $this->_remoteServerPort ? $this->_remoteServerPort : 22;
	}

	/**
	 * @param $value
	 * задаем путь к папке на удаленном сервере, где будут храниться бэкапы
	 */
	public function setRemoteBackupFolder(string $value)
	{
		$this->_remoteBackupFolder = $value;
	}

	/**
	 * @return string
	 * путь к папке на удаленном сервере, где будут храниться бэкапы
	 */
	public function getRemoteBackupFolder() : string
	{
		return $this->_remoteBackupFolder ? $this->_remoteBackupFolder : '/var/www';
	}

	/**
	 * @param $value
	 * задаем имя пользователя для авторизации по ssh на удаленном сервере
	 */
	public function setRemoteServerUserName(string $value)
	{
		$this->_remoteServerUserName = $value;
	}

	/**
	 * @return string
	 * имя пользователя для авторизации по ssh на удаленном сервере
	 */
	public function getRemoteServerUserName() : string
	{
		return $this->_remoteServerUserName ? $this->_remoteServerUserName : 'root';
	}

	/**
	 * @param $value
	 * задаем пароль пользователя для авторизации по ssh на удаленном сервере
	 */
	public function setRemoteServerPass($value)
	{
		$this->_remoteServerPass = $value;
	}

	/**
	 * @return string
	 * пароль пользователя для авторизации по ssh на удаленном сервере
	 */
	public function getRemoteServerPass()
	{
		return $this->_remoteServerPass;
	}

	/**
	 * @param $value
	 * задаем время жизни архива (принимаем в часах)
	 */
	public function setDelayDelete(int $value)
	{
		$this->_delayDelete = $value;
	}

	/**
	 * @return int
	 * время жизни архива (выдаем в секундах)
	 */
	public function getDelayDelete() : int
	{
		return $this->_delayDelete ? $this->_delayDelete*3600 : 24*3600;
	}

	/**
	 * @param $value
	 * задаем адрес сервера для бэкапа
	 */
	public function setDbHost(string $value)
	{
		$this->_dbHost = $value;
	}

	/**
	 * @return string
	 * адрес сервера для бэкапа
	 */
	public function getDbHost() : string
	{
		return $this->_dbHost ? $this->_dbHost : 'localhost';
	}

	/**
	 * @param $value
	 * задаем имя пользователя базы данных
	 */
	public function setDbUser($value)
	{
		$this->_dbUser = $value;
	}

	/**
	 * @return string
	 * имя пользователя базы данных
	 */
	public function getDbUser()
	{
		return $this->_dbUser ? $this->_dbUser : 'root';
	}

	/**
	 * @param $value
	 * задаем пароль пользователя базы данных
	 */
	public function setDbPassword($value)
	{
		$this->_dbPassword = $value;
	}

	/**
	 * @return string
	 * пароль пользователя базы данных
	 */
	public function getDbPassword()
	{
		return $this->_dbPassword;
	}

	/**
	 * @param $value
	 * задаем название базы данных
	 */
	public function setDbName($value)
	{
		$this->_dbName = $value;
	}

	/**
	 * @return string
	 * название базы данных
	 */
	public function getDbName()
	{
		return $this->_dbName;
	}

	/**
	 * @param $value
	 * задаем максимальный размер таблицы попадающей в бэкап
	 */
	public function setMaxTableSizeMB($value)
	{
		$this->_maxTableSizeMB = $value;
	}

	/**
	 * @return string
	 * максимальный размер таблицы попадающей в бэкап
	 */
	public function getMaxTableSizeMB()
	{
		return $this->_maxTableSizeMB;
	}

	/**
	 * @param $value
	 * задаем адрес почты, кому будет приходить информация по бэкапам
	 */
	public function setMailTo($value)
	{
		$this->_mailTo = $value;
	}

	/**
	 * @return string
	 * адрес почты, кому будет приходить информация по бэкапам
	 */
	public function getMailTo()
	{
		return $this->_mailTo;
	}

	/**
	 * @param $value
	 * задаем адрес почты, от кого будет передаваться информация по бэкапам
	 */
	public function setMailFrom($value)
	{
		$this->_mailFrom = $value;
	}

	/**
	 * @return string
	 * адрес почты, от кого будет передаваться информация по бэкапам
	 */
	public function getMailFrom()
	{
		return $this->_mailFrom;
	}

	/**
	 * @param $value
	 * задаем тему сообщения
	 */
	public function setMailSubject($value)
	{
		$this->_mailSubject = 'Backup Data to '.$value.' '.date("d-m-Y");
	}

	/**
	 * @return string
	 * тема сообщения
	 */
	public function getMailSubject()
	{
		return $this->_mailSubject;
	}

	/**
	 * @return bool
	 * чтобы не было багов будем проверять все ли необходимые параметры заданы
	 */
//	public function checkRequiredParams()
//	{
//		if($this->_backupNameFile and $this->_backupNameSql and $this->_remoteServerIp
//			and $this->_remoteServerPass and $this->_dbPassword and $this->_dbName
//			and $this->_maxTableSizeMB and $this->_mailTo and $this->_mailFrom)
//			return true;
//		else
//		{
//			exit('Не заданы необходимые параметры: '.' backupNameFile-> '.$this->_backupNameFile
//				.' backupNameSql-> '. $this->_backupNameSql .' remoteServerIp-> '. $this->_remoteServerIp
//				.' remoteServerPass-> '. $this->_remoteServerPass .' dbPassword-> '. $this->_dbPassword
//				.' dbName-> '. $this->_dbName .' maxTableSizeMB-> '. $this->_maxTableSizeMB
//				.' mailTo-> '. $this->_mailTo.' mailFrom-> '. $this->_mailFrom);
//		}
//	}
	public function checkRequiredParams()
	{
		if($this->backupFolder and $this->backupName and $this->targetDirWithFiles
			and $this->delayDelete and $this->dbHost and $this->dbUser
			and $this->dbPassword and $this->dbName and $this->maxTableSizeMB
			and $this->mailTo and $this->mailFrom and $this->remoteServerIp
			and $this->remoteServerPort and $this->remoteBackupFolder
			and $this->remoteServerUserName and $this->remoteServerPass
		)
			return true;
		else
		{
			exit('Не заданы необходимые параметры: '.' backupNameFile-> '.$this->_backupNameFile
				.' backupNameSql-> '. $this->_backupNameSql .' remoteServerIp-> '. $this->_remoteServerIp
				.' remoteServerPass-> '. $this->_remoteServerPass .' dbPassword-> '. $this->_dbPassword
				.' dbName-> '. $this->_dbName .' maxTableSizeMB-> '. $this->_maxTableSizeMB
				.' mailTo-> '. $this->_mailTo.' mailFrom-> '. $this->_mailFrom);
		}
	}

	/**
	 * создает бэкап папки в архив tar.gz
	 * результат выполнения выводится в логи
	 * @return bool
	 */
	public function backupFiles()
	{
		$this->checkRequiredParams();
		$this->_fullNameBackupFile = $this->backupFolder. '/'. $this->backupNameFile;
		$command = 'tar -cvf ' . $this->_fullNameBackupFile . ' ' . $this->targetDirWithFiles ;

		shell_exec(escapeshellcmd($command));

		if(file_exists($this->_fullNameBackupFile) and filesize($this->_fullNameBackupFile) > 0)
		{
			Tools::log(self::SUCCESS_CREATED_BACKUP_FILES.': '.$this->_fullNameBackupFile);
			return true;
		}
		else
		{
			Tools::log(self::ERROR_CREATE_BACKUP_FILES.': '.$this->_fullNameBackupFile);
			return false;
		}
	}

	/**
	 * @param $backupName
	 * @return bool
	 *  ВАЖНО!!! Для работы функции ssh2_connect нужно установить расширение, в базе мануалов есть описание
	 */
	public function copyBackupToRemoteDir($backupName)
	{
		$this->checkRequiredParams();
		try
		{
			$connection = ssh2_connect($this->remoteServerIp, $this->remoteServerPort);
			if($connection)
			{
				if(ssh2_auth_password($connection, $this->remoteServerUserName, $this->remoteServerPass))
				{
					//удаляем старые копии конкретного файла используя маску даты
					$deleteMask = preg_replace('!('.date("d-m-Y").')!', '*', $backupName);

					if(ssh2_exec($connection, 'sudo rm '.$this->remoteBackupFolder.'/'.$deleteMask))
						Tools::log(self::DELETED_OLD_FILES.' на удаленном сервере: '.$deleteMask);
					else
					{
						Tools::log(self::ERROR_DELETE_REMOTE_FILES);
						ssh2_exec($connection, 'exit');
						return false;
					}
					if(ssh2_scp_send($connection, $this->backupFolder.'/'.$backupName, $this->remoteBackupFolder.'/'.$backupName, 0777))
					{
						Tools::log(self::SUCCESS_REMOTE_COPY_BACKUP.': '.$backupName);
						ssh2_exec($connection, 'exit');
						return true;
					}
					else
					{
						Tools::log(self::ERROR_REMOTE_COPY_BACKUP.': '.$backupName);
						ssh2_exec($connection, 'exit');
						return false;
					}
				}
				else
				{
					Tools::log(self::ERROR_AUTH);
					ssh2_exec($connection, 'exit');
					return false;
				}
			}
			else
			{
				Tools::log(self::ERROR_CONNECTION);
				return false;
			}
		}
		catch (Exception $e)
		{
			Tools::log($e->getMessage());
			return false;
		}
	}

	/**
	 * создает бэкап базы данных
	 * результат выполнения выводится в логи
	 * @return bool
	 */
	public function backupDb()
	{
		$this->checkRequiredParams();
		$this->_fullNameBackupSql = $this->backupFolder . '/' . $this->backupNameSql;

		$dbConnection = Yii::app()->db;

		if($dbConnection)
		{
			//определяем таблицы с подходящим размером
			$sqlCommand = 'SELECT table_name AS `Table`,
				round(((data_length + index_length) / 1024 / 1024), 2) `Size in MB`
				FROM information_schema.TABLES
				WHERE table_schema = "'.$this->dbName.'" AND
				round(((data_length + index_length) / 1024 / 1024), 2) < '.$this->maxTableSizeMB.';';

			$command = $dbConnection->createCommand($sqlCommand);

			$column = $command->queryColumn();
			$dbConnection->active=false;
			$tablesForExport = '';

			foreach($column as $tableName)
			{
				$tablesForExport .= $tableName.' ';
			}

			if($tablesForExport)
			{
				$command = 'mysqldump -h ' . $this->dbHost . ' -u ' . $this->dbUser . ' -p' . $this->dbPassword
					. ' ' . $this->dbName .' '.$tablesForExport. ' | gzip > ' . $this->_fullNameBackupSql;

				shell_exec($command);

				if(file_exists($this->_fullNameBackupSql) and filesize($this->_fullNameBackupSql) > 0)
				{
					Tools::log(self::SUCCESS_CREATED_BACKUP_MYSQL.': '.$this->_fullNameBackupSql);
					return true;
				}
				else
				{
					Tools::log(self::ERROR_CREATE_BACKUP_MYSQL.': '.$this->_fullNameBackupSql);
					return false;
				}
			}
			else
			{
				Tools::log(self::NO_TABLES_WITH_NEEDED_SIZE);
				return false;
			}
		}
		else
		{
			Tools::log('Ошибка : '.mysql_errno().' '.mysql_error());
			return false;
		}
	}

	/**
	 * очищает всю папку со старыми бэкапами, если время прошло больше чем $this->delayDelete
	 * @return array
	 */
	public function deleteOldArchives()
	{
		$this->checkRequiredParams();
		$thisTime = time();
		$files = glob($this->backupFolder . "/*");
		$deleted = [];
		foreach($files as $file)
		{
			if($thisTime - filemtime($file) > $this->delayDelete)
			{
				array_push($deleted, $file);
				unlink($file);
			}
		}
		if($deleted)
			Tools::log(self::DELETED_OLD_FILES.': '.Tools::arr2Str($deleted));
		else
			Tools::log(self::NO_FILES_TO_DELETE.' давнее '.($this->delayDelete/3600).' часов');

		return $deleted;
	}

	/**
	 * Функция выполняющая все операции с бэкапами автоматически от создания до удаления
	 * также отправляет сообщение на почту о статусе результата выполнения операций с бэкапами
	 * @return bool
	 */
	public function manipulateBackup()
	{
		$this->checkRequiredParams();
		session_write_close();

		$threadName = 'manipulateBackup';

		if(!Tools::threader($threadName))
		{
			Tools::log(self::ERROR_THREAD_ALLREADY_RUN.': '.$threadName);
			die(self::ERROR_THREAD_ALLREADY_RUN.': '.$threadName);
		}

		Tools::log(self::START_PROCEDURE_BACKUP);

		$start = microtime(true);		// запускаем таймер
		$dobackupFiles = $this->backupFiles();	// делаем бэкап файлов
		$doBackupDb = $this->backupDb();	// и базы данных

		//удаляем старые файлы только в случае успешного бэкапа
		if($dobackupFiles and $doBackupDb)
			$deleteOld = $this->deleteOldArchives();	// удаляем старые архивы

		// добавляем в письмо отчеты
		if($dobackupFiles)
		{
			$this->_mailMessage .= self::SUCCESS_CREATED_BACKUP_FILES.': '.$this->_fullNameBackupFile.'<br/>';

			if($this->copyBackupToRemoteDir($this->backupNameFile))
				$this->_mailMessage .= self::SUCCESS_REMOTE_COPY_BACKUP.': '.$this->backupNameFile.'<br/>';
		}
		else
			Tools::log(self::ERROR_CREATE_BACKUP_FILES);

		if($doBackupDb)
		{
			$this->_mailMessage .= self::SUCCESS_CREATED_BACKUP_MYSQL.': '.$this->_fullNameBackupSql.'<br/>';

			if($this->copyBackupToRemoteDir($this->backupNameSql))
				$this->_mailMessage .= self::SUCCESS_REMOTE_COPY_BACKUP.': '.$this->backupNameSql.'<br/>';
		}
		else
			Tools::log(self::ERROR_CREATE_BACKUP_MYSQL);

		if($deleteOld)
		{
			foreach($deleteOld as $val)
				$this->_mailMessage .= 'File deleted: ' . $val . '<br/>';
		}

		// считаем время, потраченое на выполнение скрипта
		$time = round(microtime(true) - $start, 3);
		$this->_mailMessage .= self::SCRIPT_EXECUTION_TIME .': '. $time .' с ';

		//отправляем письмо
//		$sender = new Sender();
//		$content = $sender->send("http://185.86.151.137/sendmail/index.php?key=testtest&method=SendEmail&to="
//			.urlencode($this->mailTo)."&text=".urlencode($this->_mailMessage)."&title="
//			.urlencode($this->mailSubject)."&from=".urlencode($this->mailFrom));
//
//		if(stristr($content, '[result] => 1') === FALSE)
//			Tools::log(self::ERROR_SEND_EMAIL.' на: '.$this->mailTo);
//		else
//			Tools::log('email на '.$this->mailTo);

		Tools::log(self::END_PROCEDURE_BACKUP);

		return true;
	}
}


