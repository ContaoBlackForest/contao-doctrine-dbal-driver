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
use Doctrine\DBAL\Statement;

/**
 * {@inheritdoc}
 */
class Result extends \Database\Result
{
	/**
	 * Current result
	 * @var \Doctrine\DBAL\Statement
	 */
	protected $resResult;

	/**
	 * We need to cache the complete result, because doctrine does not support seeking.
	 *
	 * @var array
	 */
	protected $arrResult;

	/**
	 * This is the index of the next fetch'able row.
	 *
	 * @var int
	 */
	protected $index = 0;

	/**
	 * @param Statement $resResult
	 * @param string    $strQuery
	 */
	public function __construct($resResult, $strQuery)
	{
		parent::__construct($resResult, $strQuery);
		$this->arrResult = $resResult->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function fetch_row()
	{
		if ($this->index >= count($this->arrResult)) {
			return null;
		}

		return array_values($this->arrResult[$this->index ++]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function fetch_assoc()
	{
		if ($this->index >= count($this->arrResult)) {
			return null;
		}

		return $this->arrResult[$this->index ++];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function num_rows()
	{
		return count($this->arrResult);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function num_fields()
	{
		return $this->resResult->columnCount();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function fetch_field($intOffset)
	{
		if ($this->index >= count($this->arrResult)) {
			return null;
		}

		$row = $this->fetch_row();
		return $row[$intOffset];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function data_seek($index)
	{
		if ($index < 0)
		{
			throw new \OutOfBoundsException("Invalid index $index (must be >= 0)");
		}

		$intTotal = $this->num_rows();

		if ($intTotal <= 0)
		{
			return; // see #6319
		}

		if ($index >= $intTotal)
		{
			throw new \OutOfBoundsException("Invalid index $index (only $intTotal rows in the result set)");
		}

		$this->index = $index;
	}

	/**
	 * {@inheritdoc}
	 */
	public function free()
	{
		$this->resResult->closeCursor();
		unset($this->arrResult);
	}
}
