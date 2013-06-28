<?php

/**
 * Doctrine DBAL bridge driver
 * Copyright (C) 2013 Tristan Lins
 *
 * PHP version 5
 *
 * @copyright  bit3 UG 2013
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @package    doctrine-dbal
 * @license    LGPL-3.0+
 * @filesource
 */

namespace Contao\Doctrine\Driver\MySQL;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\ApcCache;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\Common\Cache\XcacheCache;
use Doctrine\DBAL\Cache\QueryCacheProfile;

/**
 * {@inheritdoc}
 */
class Database extends \Database
{
	/**
	 * Connection ID
	 *
	 * @var \Doctrine\DBAL\Connection
	 */
	protected $resConnection;

	/**
	 * @var QueryCacheProfile
	 */
	protected $queryCacheProfile;

	/**
	 * @return \Doctrine\DBAL\Connection
	 */
	public function getConnection()
	{
		return $this->resConnection;
	}

	/**
	 * @param \Contao\Doctrine\Driver\MySQL\QueryCacheProfile $queryCacheProfile
	 */
	public function setQueryCacheProfile(QueryCacheProfile $queryCacheProfile = null)
	{
		$this->queryCacheProfile = $queryCacheProfile;
		return $this;
	}

	/**
	 * @return \Contao\Doctrine\Driver\MySQL\QueryCacheProfile
	 */
	public function getQueryCacheProfile()
	{
		return $this->queryCacheProfile;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function connect()
	{
		$config = new \Doctrine\DBAL\Configuration();

		$connectionParameters = array(
			'dbname'   => $this->arrConfig['dbDatabase'],
			'user'     => $this->arrConfig['dbUser'],
			'password' => $this->arrConfig['dbPass'],
			'host'     => $this->arrConfig['dbHost'],
			'port'     => $this->arrConfig['dbPort'],
		);

		switch (strtolower($this->arrConfig['dbDriver'])) {
			case 'mysql':
			case 'mysqli':
			case 'doctrinemysql':
				$connectionParameters['driver']  = 'pdo_mysql';
				$connectionParameters['charset'] = $this->arrConfig['dbCharset'];
				if (!empty($this->arrConfig['dbSocket'])) {
					$connectionParameters['unix_socket'] = $this->arrConfig['dbSocket'];
				}
				break;
			default:
				throw new \RuntimeException('Database driver ' . $this->arrConfig['dbDriver'] . ' not known by doctrine.');
		}

		if (!empty($this->arrConfig['dbPdoDriverOptions'])) {
			$connectionParameters['driverOptions'] = deserialize($this->arrConfig['dbPdoDriverOptions'], true);
		}

		if (array_key_exists('dbCache_' . TL_MODE, $this->arrConfig)) {
			$dbCache = $this->arrConfig['dbCache_' . TL_MODE];
		}
		else if (array_key_exists('dbCache', $this->arrConfig)) {
			$dbCache = $this->arrConfig['dbCache'];
		}
		else if (array_key_exists('dbCache_' . TL_MODE, $GLOBALS['TL_CONFIG'])) {
			$dbCache = $GLOBALS['TL_CONFIG']['dbCache_' . TL_MODE];
		}
		else if (array_key_exists('dbCache', $GLOBALS['TL_CONFIG'])) {
			$dbCache = $GLOBALS['TL_CONFIG']['dbCache'];
		}
		else {
			$dbCache = 'array';
		}

		if (array_key_exists('dbCacheTTL_' . TL_MODE, $this->arrConfig)) {
			$dbCacheTTL = $this->arrConfig['dbCacheTTL_' . TL_MODE];
		}
		else if (array_key_exists('dbCacheTTL', $this->arrConfig)) {
			$dbCacheTTL = $this->arrConfig['dbCacheTTL'];
		}
		else if (array_key_exists('dbCacheTTL_' . TL_MODE, $GLOBALS['TL_CONFIG'])) {
			$dbCacheTTL = $GLOBALS['TL_CONFIG']['dbCacheTTL_' . TL_MODE];
		}
		else if (array_key_exists('dbCacheTTL', $GLOBALS['TL_CONFIG'])) {
			$dbCacheTTL = $GLOBALS['TL_CONFIG']['dbCacheTTL'];
		}
		else if (TL_MODE == 'BE') {
			$dbCacheTTL = 1;
		}
		else {
			$dbCacheTTL = 15;
		}

		if (array_key_exists('dbCacheName_' . TL_MODE, $this->arrConfig)) {
			$dbCacheName = $this->arrConfig['dbCacheName_' . TL_MODE];
		}
		else if (array_key_exists('dbCacheName', $this->arrConfig)) {
			$dbCacheName = $this->arrConfig['dbCacheName'];
		}
		else if (array_key_exists('dbCacheName_' . TL_MODE, $GLOBALS['TL_CONFIG'])) {
			$dbCacheName = $GLOBALS['TL_CONFIG']['dbCacheName_' . TL_MODE];
		}
		else if (array_key_exists('dbCacheName', $GLOBALS['TL_CONFIG'])) {
			$dbCacheName = $GLOBALS['TL_CONFIG']['dbCacheName'];
		}
		else {
			$dbCacheName = md5(TL_ROOT);
		}

		$url = parse_url($dbCache);
		if (empty($url['scheme'])) {
			$url['scheme'] = $url['path'];
		}
		switch ($url['scheme']) {
			case 'apc':
				$cache = new ApcCache();
				break;
			case 'xcache':
				$cache = new XcacheCache();
				break;
			case 'memcache':
				$memcache = new \Memcache();
				$memcache->connect(
					empty($url['host']) ? '127.0.0.1' : $url['host'],
					empty($url['port']) ? null : $url['port']
				);
				$cache = new MemcacheCache();
				$cache->setMemcache($memcache);
				break;
			case 'redis':
				$redis = new \Redis();
				if (empty($url['path'])) {
					$redis->connect(
						empty($url['host']) ? '127.0.0.1' : $url['host'],
						empty($url['port']) ? 6379 : $url['port']
					);
				}
				else {
					$redis->connect($url['path']);
				}
				$cache = new RedisCache();
				$cache->setRedis($redis);
				break;
			case 'array':
				$cache = new ArrayCache();
				break;
			case '':
				$cache = false;
				break;
			default:
				throw new RuntimeException('Invalid doctrine cache impl ' . $dbCache);
		}

		if ($cache) {
			$this->queryCacheProfile = new QueryCacheProfile($dbCacheTTL, $dbCacheName, $cache);
			$config->setResultCacheImpl($cache);
		}

		// Call hook prepareDoctrineConnection
		if (array_key_exists('TL_HOOKS', $GLOBALS) && array_key_exists('prepareDoctrineConnection', $GLOBALS['TL_HOOKS']) && is_array($GLOBALS['TL_HOOKS']['prepareDoctrineConnection'])) {
			foreach ($GLOBALS['TL_HOOKS']['prepareDoctrineConnection'] as $callback) {
				$object = method_exists($callback[0], 'getInstance') ? call_user_func(array($callback[0], 'getInstance')) : new $callback[0];
				$object->$callback[1]($connectionParameters, $config);
			}
		}

		$this->resConnection = \Doctrine\DBAL\DriverManager::getConnection($connectionParameters, $config);

		// Call hook doctrineConnect
		if (array_key_exists('TL_HOOKS', $GLOBALS) && array_key_exists('doctrineConnect', $GLOBALS['TL_HOOKS']) && is_array($GLOBALS['TL_HOOKS']['doctrineConnect'])) {
			foreach ($GLOBALS['TL_HOOKS']['doctrineConnect'] as $callback) {
				$object = method_exists($callback[0], 'getInstance') ? call_user_func(array($callback[0], 'getInstance')) : new $callback[0];
				$object->$callback[1]($connectionParameters, $config);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function disconnect()
	{
		unset($this->resConnection);
	}

	/**
	 * {@inheritdoc}
	 */
	public function listTables($databaseName=null, $noCache=false)
	{
		if ($databaseName === null)
		{
			$databaseName = $this->arrConfig['dbDatabase'];
		}

		if (!$noCache && isset($this->arrCache[$databaseName]))
		{
			return $this->arrCache[$databaseName];
		}

		$schemaManager = $this->resConnection->getSchemaManager();
		$this->arrCache[$databaseName] = $schemaManager->listTableNames();

		return $this->arrCache[$databaseName];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_error()
	{
		if ($this->resConnection) {
			$info = $this->resConnection->errorInfo();
			return 'SQLSTATE ' . $info[0] . ': error ' . $info[1] . ': ' . $info[2];
		}
		else {
			return 'doctrine connection not available';
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function find_in_set($strKey, $strSet, $blnIsField = false)
	{
		if ($blnIsField) {
			return "FIND_IN_SET(" . $this->resConnection->quoteIdentifier($strKey) . ", " . $strSet . ")";
		}
		else {
			return "FIND_IN_SET(" . $this->resConnection->quoteIdentifier($strKey) . ", " . $this->resConnection->quote(
				$strSet
			) . ")";
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function begin_transaction()
	{
		return $this->resConnection->beginTransaction();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function commit_transaction()
	{
		return $this->resConnection->commit();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function rollback_transaction()
	{
		return $this->resConnection->rollBack();
	}

	protected function list_fields($strTable)
	{
		$arrReturn = array();
		$arrFields = $this->query(sprintf('SHOW COLUMNS FROM `%s`', $strTable))->fetchAllAssoc();

		foreach ($arrFields as $k=>$v)
		{
			$arrChunks = preg_split('/(\([^\)]+\))/', $v['Type'], -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

			$arrReturn[$k]['name'] = $v['Field'];
			$arrReturn[$k]['type'] = $arrChunks[0];

			if (!empty($arrChunks[1]))
			{
				$arrChunks[1] = str_replace(array('(', ')'), array('', ''), $arrChunks[1]);
				$arrSubChunks = explode(',', $arrChunks[1]);

				$arrReturn[$k]['length'] = trim($arrSubChunks[0]);

				if (!empty($arrSubChunks[1]))
				{
					$arrReturn[$k]['precision'] = trim($arrSubChunks[1]);
				}
			}

			if (!empty($arrChunks[2]))
			{
				$arrReturn[$k]['attributes'] = trim($arrChunks[2]);
			}

			if (!empty($v['Key']))
			{
				switch ($v['Key'])
				{
					case 'PRI':
						$arrReturn[$k]['index'] = 'PRIMARY';
						break;

					case 'UNI':
						$arrReturn[$k]['index'] = 'UNIQUE';
						break;

					case 'MUL':
						// Ignore
						break;

					default:
						$arrReturn[$k]['index'] = 'KEY';
						break;
				}
			}

			$arrReturn[$k]['null'] = ($v['Null'] == 'YES') ? 'NULL' : 'NOT NULL';
			$arrReturn[$k]['default'] = $v['Default'];
			$arrReturn[$k]['extra'] = $v['Extra'];
		}

		$arrIndexes = $this->query("SHOW INDEXES FROM `$strTable`")->fetchAllAssoc();

		foreach ($arrIndexes as $arrIndex)
		{
			$arrReturn[$arrIndex['Key_name']]['name'] = $arrIndex['Key_name'];
			$arrReturn[$arrIndex['Key_name']]['type'] = 'index';
			$arrReturn[$arrIndex['Key_name']]['index_fields'][] = $arrIndex['Column_name'];
			$arrReturn[$arrIndex['Key_name']]['index'] = (($arrIndex['Non_unique'] == 0) ? 'UNIQUE' : 'KEY');
		}

		return $arrReturn;
	}

	/**
	 * TODO: Using the doctrine schema does not work, because some types are not available
	protected function list_fields($strTable)
	{
		$platform      = $this->resConnection->getDatabasePlatform();
		$schemaManager = $this->resConnection->getSchemaManager();
		$columns       = $schemaManager->listTableColumns($strTable);
		$indexes       = $schemaManager->listTableIndexes($strTable);
		$fields        = array();

		// fill fields
		foreach ($columns as $column) {
			$fields[$column->getName()]['name'] = $column->getName();

			$type = $column->getType();
			$typeSQL = strtolower($type->getSQLDeclaration(array(), $platform));
			$nativeType = preg_replace('#\(.*\)$#', '', $typeSQL);
			$fields[$column->getName()]['type'] = $nativeType;

			if ($column->getLength()) {
				$fields[$column->getName()]['length']    = $column->getLength();
				$fields[$column->getName()]['precision'] = $column->getPrecision();
			}
			else {
				$fields[$column->getName()]['length'] = $column->getPrecision();
			}

			$fields[$column->getName()]['attributes'] = $column->getUnsigned() ? 'unsigned' : '';
			$fields[$column->getName()]['index']      = '';
			$fields[$column->getName()]['null']       = $column->getNotnull() ? 'NOT NULL' : 'NULL';
			$fields[$column->getName()]['default']    = $column->getDefault();
			$fields[$column->getName()]['extra']      = $column->getAutoincrement() ? 'auto_increment' : '';
		}

		// update field index
		foreach ($indexes as $index) {
			$columnNames = $index->getColumns();
			foreach ($columnNames as $columnName) {
				if ($index->isPrimary()) {
					$fields[$columnName]['index'] = 'PRIMARY';
				}
				else if ($index->isUnique()) {
					$fields[$columnName]['index'] = 'UNIQUE';
				}
				else if ($index->isSimpleIndex()) {
					$fields[$columnName]['index'] = 'KEY';
				}
			}
		}

		// make indexes numeric
		$fields = array_values($fields);

		foreach ($indexes as $index) {
			$fields[] = array(
				'name'         => $index->getName(),
				'type'         => 'index',
				'index_fields' => $index->getUnquotedColumns(),
				'index'        => $index->isUnique() ? 'UNIQUE' : 'KEY',
			);
		}

		var_dump($fields);
		exit;
		return $fields;
	}
	 */

	/**
	 * {@inheritdoc}
	 */
	protected function set_database($strDatabase)
	{
		throw new \RuntimeException('Not implemented yet');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function lock_tables($arrTables)
	{
		$arrLocks = array();

		foreach ($arrTables as $table => $mode) {
			$arrLocks[] = $this->resConnection->quoteIdentifier($table) . ' ' . $mode;
		}

		$this->resConnection->exec('LOCK TABLES ' . implode(', ', $arrLocks) . ';');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function unlock_tables()
	{
		$this->resConnection->exec('UNLOCK TABLES;');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_size_of($strTable)
	{
		$statement = $this->resConnection->executeQuery(
			'SHOW TABLE STATUS LIKE ' . $this->resConnection->quote($strTable)
		);
		$status    = $statement->fetch(\PDO::FETCH_ASSOC);

		return ($status['Data_length'] + $status['Index_length']);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_next_id($strTable)
	{
		$statement = $this->resConnection->executeQuery(
			'SHOW TABLE STATUS LIKE ' . $this->resConnection->quote($strTable)
		);
		$status    = $statement->fetch(\PDO::FETCH_ASSOC);

		return $status['Auto_increment'];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function createStatement($resConnection, $blnDisableAutocommit)
	{
		$statement = new Statement($resConnection, $blnDisableAutocommit);
		$statement->setQueryCacheProfile($this->queryCacheProfile);
		return $statement;
	}

}