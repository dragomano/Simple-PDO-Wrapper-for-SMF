<?php

declare(strict_types = 1);

namespace Bugo\PDOSMF;

/**
 * Database.php
 *
 * @package Simple PDO Wrapper for SMF
 * @link https://github.com/dragomano/Simple-PDO-Wrapper-for-SMF
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2021 Bugo
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class Integration
{
	/**
	 * @param array $classMap
	 * @return void
	 */
	public function autoload(array &$classMap)
	{
		$classMap['Bugo\\PDOSMF\\'] = 'PDOSMF/';
	}
}
