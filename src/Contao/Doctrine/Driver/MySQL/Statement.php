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

use Doctrine\DBAL\Cache\ArrayStatement;
use Doctrine\DBAL\Cache\QueryCacheProfile;

/**
 * {@inheritdoc}
 */
class Statement extends \Database\Statement
{
	/**
	 * @var QueryCacheProfile
	 */
	protected $queryCacheProfile = null;

	/**
	 * Connection ID
	 * @var \Doctrine\DBAL\Connection
	 */
	protected $resConnection;

	/**
	 * Connection ID
	 * @var \Doctrine\DBAL\Statement
	 */
	protected $statement;

	/**
	 * @var array
	 */
	protected $parameters = array();

	/**
	 * @param \Doctrine\DBAL\Cache\QueryCacheProfile $queryCacheProfile
	 */
	public function setQueryCacheProfile(QueryCacheProfile $queryCacheProfile = null)
	{
		$this->queryCacheProfile = $queryCacheProfile;
		return $this;
	}

	/**
	 * @return \Doctrine\DBAL\Cache\QueryCacheProfile
	 */
	public function getQueryCacheProfile()
	{
		return $this->queryCacheProfile;
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepare($strQuery)
	{
		if (!strlen($strQuery))
		{
			throw new \RuntimeException('Empty query string');
		}

		$this->resResult = null;
		$this->strQuery  = ltrim($strQuery);

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set($arrParams)
	{
		$keys = array();
		foreach ($arrParams as $key => $value) {
			switch (gettype($value))
			{
				case 'boolean':
					$value = ($value === true) ? 1 : 0;
					break;

				case 'object':
				case 'array':
					$value = serialize($value);
					break;

				default:
					$value = ($value === null) ? 'NULL' : $value;
					break;
			}

			$identifier = '?';
			$keys[$this->resConnection->quoteIdentifier($key)] = $identifier;
			$this->parameters[] = $value;
		}

		// INSERT
		if (strncasecmp($this->strQuery, 'INSERT', 6) === 0)
		{
			$strQuery = sprintf(
				'(%s) VALUES (%s)',
				implode(', ', array_keys($keys)),
				implode(', ', $keys)
			);
		}

		// UPDATE
		elseif (strncasecmp($this->strQuery, 'UPDATE', 6) === 0)
		{
			$arrSet = array();

			foreach ($keys as $key=>$identifier)
			{
				$arrSet[] = $key . '=' . $identifier;
			}

			$strQuery = 'SET ' . implode(', ', $arrSet);
		}

		$this->strQuery = str_replace('%s', $strQuery, $this->strQuery);
		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute()
	{
		$parameters = func_get_args();

		if (is_array($parameters[0]))
		{
			$parameters = array_values($parameters[0]);
		}

		$this->parameters = array_values(
			array_merge(
				$this->parameters,
				$parameters
			)
		);

		$this->statement = $this->resConnection->executeQuery(
			$this->strQuery,
			$this->parameters,
			array(),
			$this->queryCacheProfile
		);

		if (!preg_match('#^(SELECT|SHOW)#iS', $this->strQuery)) {
			$this->debugQuery();
			return $this;
		}

		$result = new Result($this->statement, $this->strQuery);
		if (!$this->statement instanceof ArrayStatement) {
			$this->debugQuery($result);
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function executeUncached()
	{
		$parameters = func_get_args();

		if (is_array($parameters[0]))
		{
			$parameters = array_values($parameters[0]);
		}

		$this->parameters = array_values(
			array_merge(
				$this->parameters,
				$parameters
			)
		);

		$this->statement = $this->resConnection->executeQuery(
			$this->strQuery,
			$this->parameters
		);

		if (!preg_match('#^(SELECT|SHOW)#iS', $this->strQuery)) {
			$this->debugQuery();
			return $this;
		}

		$result = new Result($this->statement, $this->strQuery);
		$this->debugQuery($result);

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function query($strQuery = '')
	{
		if (!empty($strQuery))
		{
			$this->strQuery = ltrim($strQuery);
		}

		// Make sure there is a query string
		if ($this->strQuery == '')
		{
			throw new \RuntimeException('Empty query string');
		}

		return $this->execute();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function prepare_query($strQuery)
	{
		throw new \RuntimeException('Not implemented yet');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function string_escape($strString)
	{
		return $this->resConnection->quote($strString);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function limit_query($intRows, $intOffset)
	{
		if (strncasecmp($this->strQuery, 'SELECT', 6) === 0)
		{
			$this->strQuery .= ' LIMIT ' . $intOffset . ',' . $intRows;
		}
		else
		{
			$this->strQuery .= ' LIMIT ' . $intRows;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute_query()
	{
		throw new \RuntimeException('Not implemented yet');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_error()
	{
		$info = $this->statement->errorInfo();
		return 'SQLSTATE ' . $info[0] . ': error ' . $info[1] . ': ' . $info[2];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function affected_rows()
	{
		return $this->statement->rowCount();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function insert_id()
	{
		return $this->resConnection->lastInsertId();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function explain_query()
	{
		return $this->resConnection
			->executeQuery('EXPLAIN ' . $this->strQuery, $this->parameters)
			->fetch();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function createResult($resResult, $strQuery)
	{
		throw new \RuntimeException('Not implemented yet');
	}
}