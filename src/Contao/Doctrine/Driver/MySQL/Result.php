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

/**
 * {@inheritdoc}
 */
class Result extends \Database_Result
{
	/**
	 * Current result
	 * @var \Doctrine\DBAL\Statement
	 */
	protected $resResult;

	protected function fetch_row()
	{
		return $this->resResult->fetch(\PDO::FETCH_NUM);
	}

	protected function fetch_assoc()
	{
		return $this->resResult->fetch(\PDO::FETCH_ASSOC);
	}

	protected function num_rows()
	{
		// the method_exists is for forward compatibility to
		// https://github.com/doctrine/dbal/pull/329
		if ($this->resResult instanceof ArrayStatement && !method_exists($this->resResult, 'rowCount')) {
			// hack to get row count of cached results
			$class = new \ReflectionClass($this->resResult);
			$property = $class->getProperty('data');
			$property->setAccessible(true);
			return count($property->getValue($this->resResult));
		}
		else {
			return $this->resResult->rowCount();
		}
	}

	protected function num_fields()
	{
		return $this->resResult->columnCount();
	}

	protected function fetch_field($intOffset)
	{
		return $this->resResult->fetchColumn($intOffset);
	}

	public function free()
	{
		$this->resResult->closeCursor();
	}
}
