<?php
/**
 *
 */

interface DataBackupInterface
{
	/**
	 * @return bool
	 * чтобы не было багов будем проверять все ли необходимые параметры заданы
	 */
	public function checkRequiredParams();

	/**
	 * создает бэкап папки в архив tar.gz
	 * результат выполнения выводится в логи
	 * @return bool
	 */
	public function backupFiles();

	/**
	 * @param $backupName
	 * @return bool
	 * ВАЖНО!!! Для работы функции ssh2_connect
	 * нужно установить расширение, в базе мануалов есть описание
	 */
	public function copyBackupToRemoteDir($backupName);

	/**
	 * создает бэкап базы данных
	 * результат выполнения выводится в логи
	 * @return bool
	 */
	public function backupDb();

	/**
	 * очищает всю папку со старыми бэкапами, если время прошло больше чем $this->delayDelete
	 * @return array
	 */
	public function deleteOldArchives();

	/**
	 * Функция выполняющая все операции с бэкапами автоматически от создания до удаления
	 * также отправляет сообщение на почту о статусе результата выполнения операций с бэкапами
	 * @return bool
	 */
	public function manipulateBackup();

}