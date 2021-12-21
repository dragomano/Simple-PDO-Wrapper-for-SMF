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
 * @version 0.2
 */

if (! defined('SMF'))
	die('No direct access...');

/**
 * Extended variation of PDOStatement, for debug purposes
 */
class DebugPDOStatement extends \PDOStatement
{
	protected array $_debugValues = [];

	protected function __construct() {}

	/**
	 * @see log_error function in SMF/Sources/Errors.php
	 */
	public function execute($params = [])
	{
		$this->_debugValues = $params;

		try {
			return parent::execute($params);
		} catch (\PDOException $e) {
			log_error($e->getMessage() . "\n" . $this->_debugQuery(), 'database', $e->getFile(), $e->getLine());
		}
	}

	public function _debugQuery(bool $replaced = true): ?string
	{
		$q = $this->queryString;

		if (! $replaced) {
			return $q;
		}

		return preg_replace_callback('/:([0-9a-z_]+)/i', array($this, '_debugReplace'), $q);
	}

	protected function _debugReplace(string $m): ?string
	{
		$v = $this->_debugValues[$m[1]];

		if ($v === null) {
			return null;
		}

		if (! is_numeric($v)) {
			$v = str_replace("'", "''", $v);
		}

		return "'" . $v . "'";
	}
}