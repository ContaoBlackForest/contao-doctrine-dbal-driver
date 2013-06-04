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
	 * @return \Doctrine\DBAL\Connection
	 */
	public function getConnection()
	{
		return $this->resConnection;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function connect()
	{
		$config = new \Doctrine\DBAL\Configuration();

		$connectionParameters = array(
			'dbname'   => $GLOBALS['TL_CONFIG']['dbDatabase'],
			'user'     => $GLOBALS['TL_CONFIG']['dbUser'],
			'password' => $GLOBALS['TL_CONFIG']['dbPass'],
			'host'     => $GLOBALS['TL_CONFIG']['dbHost'],
			'port'     => $GLOBALS['TL_CONFIG']['dbPort'],
		);

		switch (strtolower($GLOBALS['TL_CONFIG']['dbDriver'])) {
			case 'mysql':
			case 'mysqli':
			case 'doctrinemysql':
				$connectionParameters['driver']  = 'pdo_mysql';
				$connectionParameters['charset'] = $GLOBALS['TL_CONFIG']['dbCharset'];
				if (!empty($GLOBALS['TL_CONFIG']['dbSocket'])) {
					$connectionParameters['unix_socket'] = $GLOBALS['TL_CONFIG']['dbSocket'];
				}
				break;
			default:
				throw new \RuntimeException('Database driver ' . $GLOBALS['TL_CONFIG']['dbDriver'] . ' not known by doctrine.');
		}

		if (!empty($GLOBALS['TL_CONFIG']['dbPdoDriverOptions'])) {
			$connectionParameters['driverOptions'] = deserialize($GLOBALS['TL_CONFIG']['dbPdoDriverOptions'], true);
		}

		$this->resConnection = \Doctrine\DBAL\DriverManager::getConnection($connectionParameters, $config);
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
			$databaseName = $GLOBALS['TL_CONFIG']['dbDatabase'];
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
		$schemaManager = $this->resConnection->getSchemaManager();
		$columns       = $schemaManager->listTableColumns($strTable);
		$fields        = array();
		foreach ($columns as $column) {
			$fields[] = array(
				'name'       => $column->getName(),
				'type'       => $column
					->getType()
					->getName(),
				'length'     => $column->getLength(),
				'precision'  => $column->getPrecision(),
				'attributes' => '',
				'index'      => '',
				'null'       => !$column->getNotnull(),
				'default'    => $column->getDefault(),
				'extra'      => ''
			);
		}
		return $fields;
	}

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
		$status    = $statement->fetch(\PDO::FETCH_CLASS);

		return ($status->Data_length + $status->Index_length);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_next_id($strTable)
	{
		$statement = $this->resConnection->executeQuery(
			'SHOW TABLE STATUS LIKE ' . $this->resConnection->quote($strTable)
		);
		$status    = $statement->fetch(\PDO::FETCH_CLASS);

		return $status->Auto_increment;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function createStatement($resConnection, $blnDisableAutocommit)
	{
		return new Statement($resConnection, $blnDisableAutocommit);
	}

}