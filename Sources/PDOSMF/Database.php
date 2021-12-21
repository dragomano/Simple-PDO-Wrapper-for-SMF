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
 * @version 0.2
 */

if (! defined('SMF'))
	die('No direct access...');

/**
 * Simple PDO wrapper for SMF
 */
class Database
{
	private static Database $instance;

	private \PDO $pdo;

	private ?string $prefix;

	protected ?string $table;

	protected array $columns = [];

	protected bool $distinct = false;

	protected array $joins = [];

	protected array $wheres = [];

	protected array $groupBy = [];

	protected array $orderBy = [];

	protected ?string $having;

	protected ?string $limit;

	protected ?int $offset;

	protected array $params = [];

	protected array $queries = [];

	public static function getInstance(): Database
	{
		if (empty(self::$instance)) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * @see fatal_error function in SMF/Sources/Errors.php
	 */
	private function __construct()
	{
		global $db_type, $db_server, $db_name, $db_user, $db_passwd, $db_prefix;

		$options = [
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_STATEMENT_CLASS => [DebugPDOStatement::class, []],
		];

		try {
			$this->pdo = new \PDO("{$db_type}:host={$db_server};dbname={$db_name};", $db_user, $db_passwd, $options);

			$this->pdo->exec('SET NAMES UTF8');
		} catch (\PDOException $e) {
			fatal_error($e->getMessage());
		}

		$this->prefix = $db_prefix;
	}

	private function __clone() {}

	public function __wakeup() {}

	public function table(string $name): Database
	{
		$this->table = $this->prefix . $name;

		return $this;
	}

	public function get(bool $oneRow = false, int $type = \PDO::FETCH_ASSOC): array
	{
		$distinct = $this->getPreparedDistinct();

		$columns = $this->getPreparedColumns();

		$joins = $this->getPreparedJoins();

		$where = $this->getPreparedWhere();

		$having = $this->getPreparedHaving();

		$group = $this->getPreparedGroupBy();

		$order = $this->getPreparedOrderBy();

		$limit = $this->getPreparedLimit();

		$offset = $this->getPreparedOffset();

		$sql = "SELECT {$distinct}{$columns} FROM {$this->table}{$joins}{$where}{$having}{$group}{$order}{$limit}{$offset}";

		$stm = $this->prepare($sql);

		return $oneRow ? $stm->fetch($type) : $stm->fetchAll($type);
	}

	public function select($columns = ['*']): Database
	{
		$this->columns = is_array($columns) ? $columns : func_get_args();

		return $this;
	}

	public function distinct(): Database
	{
		$this->distinct = true;

		return $this;
	}

	public function addSelect($columns = ['*']): Database
	{
		$columns = is_array($columns) ? $columns : func_get_args();

		$this->columns = array_merge($this->columns, $columns);

		return $this;
	}

	public function selectRaw(string $expression): Database
	{
		$columns = [];

		if (! empty($binding)) {
			foreach ($binding as $key => $value) {
				$columns[$key] = strtr($expression, ['?' => $value]);
			}
		} else {
			$this->columns[] = $expression;
		}

		$this->columns = str_replace('{db_prefix}', $this->prefix, array_merge($this->columns, $columns));

		return $this;
	}

	public function join(string $table, string $first, string $operator = null, string $second = null, string $type = 'inner'): Database
	{
		$type = $type === 'left' ? 'LEFT' : 'INNER';

		$this->joins[] = " {$type} JOIN {$this->prefix}{$table} ON ({$first}" . rtrim(" {$operator} {$second}") . ')';

		return $this;
	}

	public function leftJoin(string $table, string $first, string $operator = null, string $second = null): Database
	{
		return $this->join($table, $first, $operator, $second, 'left');
	}

	public function where($column, $operator = null, $value = null, string $boolean = 'and'): Database
	{
		if (empty($column)) {
			return $this;
		}

		if (is_array($column)) {
			foreach ($column as $key => $val) {
				if (is_numeric($key) && is_array($val)) {
					$this->where(...array_values($val));
				} else {
					if (is_numeric($key)) {
						$this->where($val);
					} else {
						$this->where($key, $val);
					}
				}
			}

			return $this;
		}

		// Case for where('1=1')
		if (func_num_args() === 1 || is_null($operator) && is_null($value)) {
			return $this->where(substr($column, 0, 1), '=', substr($column, 2, 3), $boolean);
		}

		// Case for where($column, $value)
		if (func_num_args() === 2 || is_null($value)) {
			return $this->where($column, '=', $operator, $boolean);
		}

		if (! is_array($value)) {
			$this->params[str_replace('.', '_', $column)] = $value;
		}

		$value = is_array($value) ? '(' . implode(', ', $value) . ')' : str_replace('.', '_', ":{$column}");

		$type  = 'basic';

		$this->wheres[] = compact(
			'type', 'column', 'operator', 'value', 'boolean'
		);

		return $this;
	}

	public function orWhere($column, $operator = null, $value = null): Database
	{
		return $this->where($column, $operator, $value, 'or');
	}

	public function whereIn(string $column, $values, $boolean = 'and', bool $not = false): Database
	{
		return $this->where($column, $not ? 'NOT IN' : 'IN', $values, $boolean);
	}

	public function orWhereIn(string $column, $values, string $boolean = 'or', bool $not = false): Database
	{
		return $this->whereIn($column, $values, $boolean, $not);
	}

	public function whereNotIn(string $column, $values, string $boolean = 'and'): Database
	{
		return $this->whereIn($column, $values, $boolean, true);
	}

	public function orWhereNotIn(string $column, $values): Database
	{
		return $this->whereNotIn($column, $values, 'or');
	}

	public function whereRaw(string $sql, $bindings = [], string $boolean = 'and'): Database
	{
		$this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];
		$this->params = array_merge($this->params, $bindings);

		return $this;
	}

	public function orWhereRaw(string $sql, $bindings = []): Database
	{
		return $this->whereRaw($sql, $bindings, 'or');
	}

	public function having(string $column, string $operator = null, string $value = null, string $boolean = 'and'): Database
	{
		$this->having .= ($this->having ? " {$boolean} " : '') . "{$column} {$operator} {$value}";

		return $this;
	}

	public function groupBy(...$columns): Database
	{
		foreach ($columns as $column) {
			$this->groupBy[] = $column;
		}

		return $this;
	}

	public function orderBy(string $column, string $direction = 'ASC'): Database
	{
		[$column, $dir] = explode(' ', $column);

		$this->orderBy[$column] = strtoupper($dir ?: $direction);

		return $this;
	}

	public function limit(int $value, int $offset = null): Database
	{
		if ($value >= 0) {
			$this->limit = (string) $value;

			if ($offset) {
				$this->limit = "{$value}, {$offset}";
			}
		}

		return $this;
	}

	public function offset(int $value): Database
	{
		if ($value >= 0) {
			$this->offset = max(0, $value);
		}

		return $this;
	}

	public function find(int $id, string $uniqueKey = 'id', array $columns = ['*']): array
	{
		return $this->where($uniqueKey, $id)->first($columns);
	}

	/**
	 * Get a single column's value from the first result of a query
	 * Получаем значение заданного столбца из первой строки результата запроса
	 *
	 * @param string $column
	 * @return mixed
	 */
	public function value(string $column)
	{
		return $this->select($column)->limit(1)->get(true, \PDO::FETCH_COLUMN);
	}

	public function first(array $columns = ['*']): array
	{
		return $this->select($columns)->limit(1)->get(true);
	}

	public function pluck(string $column, ?string $key = null): array
	{
		if ($key) {
			$column = "{$key}, {$column}";
		}

		$sql = "SELECT {$column} FROM {$this->table}";
		$stm = $this->query($sql);

		return $stm->fetchAll($key ? \PDO::FETCH_KEY_PAIR : \PDO::FETCH_COLUMN);
	}

	public function count(string $column = '*'): int
	{
		$sql = "SELECT COUNT({$column}) FROM {$this->table}";
		$stm = $this->query($sql);

		return $stm->fetchColumn();
	}

	/**
	 * Retrieve the minimum value of a given column
	 * Получаем наименьшее значение выбранного столбца
	 *
	 * @param string $column
	 * @return mixed
	 */
	public function min(string $column)
	{
		$sql = "SELECT MIN({$column}) FROM {$this->table}";
		$stm = $this->query($sql);

		return $stm->fetchColumn();
	}

	/**
	 * Retrieve the maximum value of a given column
	 * Получаем наибольшее значение выбранного столбца
	 *
	 * @param string $column
	 * @return mixed
	 */
	public function max(string $column)
	{
		$sql = "SELECT MAX({$column}) FROM {$this->table}";
		$stm = $this->query($sql);

		return $stm->fetchColumn();
	}

	/**
	 * Insert new records into the database
	 * Добавляем новые записи в базу данных
	 *
	 * @param array $values
	 * @return int|array
	 */
	public function insert(array $values)
	{
		if (empty($values)) {
			return 0;
		}

		if (! is_array(reset($values))) {
			$values = [$values];
		} else {
			foreach ($values as $key => $value) {
				ksort($value);

				$values[$key] = $value;
			}
		}

		$columns = $params = [];

		foreach ($values as $key => $value) {
			foreach ($value as $k => $v) {
				$columns[$k] = "{$k} = :{$k}";
				$params[$key][$k] = $v;
			}
		}

		$columns = implode(', ', $columns);

		$sql = "INSERT INTO {$this->table} SET {$columns}";

		$ids = [];

		$this->pdo->beginTransaction();

		try {
			foreach ($values as $key => $value) {
				$this->params = $params[$key];
				$this->prepare($sql);

				$ids[] = $this->pdo->lastInsertId();
			}

			$this->pdo->commit();
		} catch (\PDOException $e) {
			$this->pdo->rollBack();
		}

		return $ids;
	}

	public function update(array $values): int
	{
		$columns = [];
		foreach ($values as $key => $value) {
			$columns[$key] = "{$key} = :{$key}";
			$this->params[$key] = $value;
		}

		$columns = implode(', ', $columns);

		$where = $this->getPreparedWhere();

		$sql = "UPDATE {$this->table} SET {$columns}{$where}";

		$stm = $this->prepare($sql);

		return $stm->rowCount();
	}

	public function increment(string $column, int $amount = 1, array $extra = []): int
	{
		if (! is_numeric($amount))
			return 0;

		$updates = [];
		foreach ($extra as $key => $value) {
			$updates[] = "{$key} = :{$key}";

			$this->params[$key] = $value;
		}

		$extra = $updates ? ', ' . implode(', ', $updates) : '';
		$where = $this->getPreparedWhere();

		$sql = "UPDATE {$this->table} SET {$column} = CASE WHEN {$column} >= 0 THEN {$column} + {$amount} ELSE 0 END{$extra}{$where}";
		$stm = $this->prepare($sql);

		return $stm->rowCount();
	}

	public function decrement(string $column, int $amount = 1, array $extra = []): int
	{
		return $this->increment($column, -$amount, $extra);
	}

	public function delete(): int
	{
		$where = $this->getPreparedWhere();

		$sql = "DELETE FROM {$this->table}{$where}";

		$stm = $this->prepare($sql);

		return $stm->rowCount();
	}

	private function prepare(string $sql): \PDOStatement
	{
		$stm = $this->pdo->prepare($sql);
		$stm->execute($this->params);

		$this->queries[] = ['base_query' => $stm->_debugQuery(), 'safe_query' => $stm->queryString];

		return $stm;
	}

	private function query(string $sql): \PDOStatement
	{
		$stm = $this->pdo->query($sql);

		$this->queries[] = ['base_query' => $stm->_debugQuery(), 'safe_query' => $stm->queryString];

		return $stm;
	}

	public function getQueries(): array
	{
		return $this->queries;
	}

	private function getPreparedDistinct(): string
	{
		return $this->distinct ? 'DISTINCT ' : '';
	}

	private function getPreparedColumns(): string
	{
		if (! $this->columns) {
			$this->select(['*']);
		}

		return implode(', ', $this->columns);
	}

	private function getPreparedJoins(): string
	{
		return implode('', $this->joins);
	}

	private function getPreparedWhere(): string
	{
		$result = '';

		foreach ($this->wheres as $where) {
			extract($where);

			if ($type === 'raw') {
				if ($sql) {
					$result .= ($result ? strtoupper(" {$boolean} ") : ' WHERE ') . $sql;
				}
			} else {
				$result .= ($result ? strtoupper(" {$boolean} ") : ' WHERE ') . "{$column} {$operator} {$value}";
			}
		}

		return $result;
	}

	private function getPreparedHaving(): string
	{
		return empty($this->having) ? '' : " HAVING {$this->having}";
	}

	private function getPreparedGroupBy(): string
	{
		return empty($this->groupBy) ? '' : (' GROUP BY ' . implode(', ', $this->groupBy));
	}

	private function getPreparedOrderBy(): string
	{
		$order = empty($this->orderBy) ? '' : ' ORDER BY ';

		foreach ($this->orderBy as $column => $direction) {
			$order .= "{$column} {$direction}, ";
		}

		if (! empty($order)) {
			$order = rtrim($order, ', ');
		}

		return $order;
	}

	private function getPreparedLimit(): string
	{
		return $this->limit ? " LIMIT {$this->limit}" : '';
	}

	private function getPreparedOffset(): string
	{
		return $this->offset ? " OFFSET {$this->offset}" : '';
	}
}
