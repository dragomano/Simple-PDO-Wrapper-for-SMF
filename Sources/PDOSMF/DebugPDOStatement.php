<?php

declare(strict_types = 1);

namespace Bugo\PDOSMF;

/**
 * DebugPDOStatement.php
 *
 * @package Simple PDO Wrapper for SMF
 * @link https://github.com/dragomano/Simple-PDO-Wrapper-for-SMF
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2021 Bugo
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @version 0.1
 */

/**
 * Extended variation of PDOStatement, for debug purposes
 */
class DebugPDOStatement extends \PDOStatement
{
	/** @var array */
	protected $_debugValues = [];

	protected function __construct() {}

	/**
	 * Execute a query
	 *
	 * @param array $values
	 * @return void
	 */
	public function execute($values = [])
	{
		$this->_debugValues = $values;

		try {
			return parent::execute($values);
		} catch (\PDOException $e) {
			log_error($e->getMessage() . "\n" . $this->_debugQuery(), 'database', $e->getFile(), $e->getLine());
		}
	}

	/**
	 * Replace callback for query
	 *
	 * @param bool $replaced
	 * @return string|null
	 */
	public function _debugQuery($replaced = true)
	{
		$q = $this->queryString;

		if (!$replaced) {
			return $q;
		}

		return preg_replace_callback('/:([0-9a-z_]+)/i', array($this, '_debugReplace'), $q);
	}

	/**
	 * Replace values to display full SQL string
	 *
	 * @param string $m
	 * @return string|null
	 */
	protected function _debugReplace($m)
	{
		$v = $this->_debugValues[$m[1]];

		if ($v === null) {
			return null;
		}

		if (!is_numeric($v)) {
			$v = str_replace("'", "''", $v);
		}

		return "'" . $v . "'";
	}
}