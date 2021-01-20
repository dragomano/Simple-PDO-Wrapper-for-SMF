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

/**
 * Simple PDO wrapper for SMF
 */
class Database
{
	/** @var static */
	private static $instance;

	/** @var  \PDO */
	private $pdo;

	/** @var string|null */
	private $prefix;

	/** @var string|null */
	protected $table;

	/** @var array */
	protected $columns = [];

	/** @var bool */
	protected $distinct = false;

	/** @var array */
	protected $joins = [];

	/** @var array */
	protected $wheres = [];

	/** @var array */
	protected $groupBy = [];

	/** @var array */
	protected $orderBy = [];

	/** @var string|null */
	protected $having;

	/** @var string|null */
	protected $limit;

	/** @var int|null */
	protected $offset;

	/** @var array */
	protected $params = [];

	/** @var array */
	protected $queries = [];

	public static function getInstance()
	{
		if (empty(self::$instance)) {
			self::$instance = new static();
		}

		return self::$instance;
	}

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

	/**
	 * Set a table name and get $this
	 *
	 * @param string $name
	 * @return $this
	 */
	public function table(string $name)
	{
		$this->table = $this->prefix . $name;

		return $this;
	}

	/**
	 * Get the query result
	 *
	 * @param bool $oneRow
	 * @param int $type
	 * @return array
	 */
	public function get($oneRow = false, $type = \PDO::FETCH_ASSOC)
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

	/**
	 * Run a select statement against the database
	 *
	 * @param array|mixed $columns
	 * @return $this
	 */
	public function select($columns = ['*'])
	{
		// Support of raw queries
/* 		if (is_string($columns) && strpos($columns, 'SELECT') !== false) {
			$sql = $columns;
			$stm = $this->prepare($sql);

			return $stm->fetchAll(\PDO::FETCH_ASSOC);
		} */

		$this->columns = is_array($columns) ? $columns : func_get_args();

		return $this;
	}

	/**
	 * Force the query to only return distinct results
	 *
	 * @return $this
	 */
	public function distinct()
	{
		$this->distinct = true;

		return $this;
	}

	/**
	 * Add a new select column to the query
	 *
	 * Добавляем дополнительные столбцы для выборки
	 *
	 * @param array|mixed $columns
	 * @return $this
	 */
	public function addSelect($columns = ['*'])
	{
		$columns = is_array($columns) ? $columns : func_get_args();

		$this->columns = array_merge($this->columns, $columns);

		return $this;
	}

	/**
	 * Add a new "raw" select expression to the query
	 *
	 * Добавляем необработанное выражение SELECT в текущий запрос
	 *
	 * @param string $expression
	 * @param array $bindings
	 * @return $this
	 */
	public function selectRaw($expression, array $bindings = [])
	{
		$columns = [];

		if (!empty($binding)) {
			foreach ($binding as $key => $value) {
				$columns[$key] = strtr($expression, ['?' => $value]);
			}
		} else {
			$this->columns[] = $expression;
		}

		$this->columns = str_replace('{db_prefix}', $this->prefix, array_merge($this->columns, $columns));

		return $this;
	}

	/**
	 * Add a join clause to the query
	 *
	 * Добавляем в запрос дополнительную таблицу через JOIN
	 *
	 * @param string $table
	 * @param string $first
	 * @param string|null $operator
	 * @param string|null $second
	 * @param string $type
	 * @return $this
	 */
	public function join($table, $first, $operator = null, $second = null, $type = 'inner')
	{
		$type = $type === 'left' ? 'LEFT' : 'INNER';

		$this->joins[] = " {$type} JOIN {$this->prefix}{$table} ON ({$first}" . rtrim(" {$operator} {$second}") . ')';

		return $this;
	}

	/**
	 * Add a left join to the query
	 *
	 * Добавляем в запрос дополнительную таблицу через LEFT JOIN
	 *
	 * @param string $table
	 * @param string $first
	 * @param string|null $operator
	 * @param string|null $second
	 * @return $this
	 */
	public function leftJoin($table, $first, $operator = null, $second = null)
	{
		return $this->join($table, $first, $operator, $second, 'left');
	}

	/**
	 * Add a basic where clause to the query
	 *
	 * @param string|array $column
	 * @param mixed $operator
	 * @param mixed $value
	 * @param string $boolean
	 * @return $this
	 */
	public function where($column, $operator = null, $value = null, $boolean = 'and')
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

		if (!is_array($value)) {
			$this->params[str_replace('.', '_', $column)] = $value;
		}

		$value = is_array($value) ? '(' . implode(', ', $value) . ')' : str_replace('.', '_', ":{$column}");

		$type  = 'basic';

		$this->wheres[] = compact(
			'type', 'column', 'operator', 'value', 'boolean'
		);

		return $this;
	}

	/**
	 * Add a "or where" clause to the query
	 *
	 * Добавляем условие "or where" для текущего запроса
	 *
	 * @param string|array $columns
	 * @param mixed $operator
	 * @param mixed $value
	 * @return $this
	 */
	public function orWhere($column, $operator = null, $value = null)
	{
		return $this->where($column, $operator, $value, 'or');
	}

	/**
	 * Add a "where in" clause to the query
	 *
	 * Добавляем условие "where in" для текущего запроса
	 *
	 * @param string $column
	 * @param mixed $values
	 * @param string  boolean
	 * @param bool $not
	 * @return $this
	 */
	public function whereIn($column, $values, $boolean = 'and', $not = false)
	{
		return $this->where($column, $not ? 'NOT IN' : 'IN', $values, $boolean);
	}

	/**
	 * Add a "or where in" clause to the query
	 *
	 * Добавляем условие "or where in" для текущего запроса
	 *
	 * @param string $column
	 * @param mixed $values
	 * @param string  boolean
	 * @param bool $not
	 * @return $this
	 */
	public function orWhereIn($column, $values, $boolean = 'or', $not = false)
	{
		return $this->whereIn($column, $values, $boolean, $not);
	}

	/**
	 * Add a "where not in" clause to the query
	 *
	 * Добавляем условие "where not in" для текущего запроса
	 *
	 * @param string $column
	 * @param mixed $values
	 * @param string  boolean
	 * @return $this
	 */
	public function whereNotIn($column, $values, $boolean = 'and')
	{
		return $this->whereIn($column, $values, $boolean, true);
	}

	/**
	 * Add a "or where not in" clause to the query
	 *
	 * Добавляем условие "or where not in" для текущего запроса
	 *
	 * @param string $column
	 * @param mixed $values
	 * @return $this
	 */
	public function orWhereNotIn($column, $values)
	{
		return $this->whereNotIn($column, $values, 'or');
	}

	/**
	 * Add a raw where clause to the query
	 *
	 * Добавляем необработанное выражение WHERE в текущий запрос
	 *
	 * @param string $sql
	 * @param mixed $bindings
	 * @param string $boolean
	 * @return $this
	 */
	public function whereRaw($sql, $bindings = [], $boolean = 'and')
	{
		$this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];
		$this->params = array_merge($this->params, $bindings);

		return $this;
	}

	/**
	 * Add a raw or where clause to the query
	 *
	 * Добавляем необработанное выражение OR WHERE в текущий запрос
	 *
	 * @param string $sql
	 * @param mixed $bindings
	 * @return $this
	 */
	public function orWhereRaw($sql, $bindings = [])
	{
		return $this->whereRaw($sql, $bindings, 'or');
	}

	/**
	 * Add a "having" clause to the query
	 *
	 * Добавляем условие "having" для текущего запроса
	 *
	 * @param string $column
	 * @param string|null $operator
	 * @param string|null $value
	 * @param string $boolean
	 * @return $this
	 */
	public function having($column, $operator = null, $value = null, $boolean = 'and')
	{
		$this->having .= ($this->having ? " {$boolean} " : '') . "{$column} {$operator} {$value}";

		return $this;
	}

	/**
	 * Add an "group by" clause to the query
	 *
	 * Добавляем условие "group by" для текущего запроса
	 *
	 * @param string $column
	 * @return $this
	 */
	public function groupBy(...$columns)
	{
		foreach ($columns as $column) {
			$this->groupBy[] = $column;
		}

		return $this;
	}

	/**
	 * Add an "order by" clause to the query
	 *
	 * Добавляем условие "order by" для текущего запроса
	 *
	 * @param string $column
	 * @param string $direction
	 * @return $this
	 */
	public function orderBy($column, $direction = 'ASC')
	{
		[$column, $dir] = explode(' ', $column);

		$this->orderBy[$column] = strtoupper($dir ?: $direction);

		return $this;
	}

	/**
	 * Set the "limit" value of the query
	 *
	 * Задаем "limit" для текущего запроса
	 *
	 * @param int $value
	 * @param null|int $offset
	 * @return $this
	 */
	public function limit($value, $offset = null)
	{
		if ($value >= 0) {
			$this->limit = $value;

			if ($offset) {
				$this->limit = "{$value}, {$offset}";
			}
		}

		return $this;
	}

	/**
	 * Set the "offset" value of the query
	 *
	 * Задаем "offset" для текущего запроса
	 *
	 * @param int $value
	 * @return $this
	 */
	public function offset($value)
	{
		if ($value >= 0) {
			$this->offset = max(0, $value);
		}

		return $this;
	}

	/**
	 * Execute a query for a single record by ID
	 *
	 * @param string $id
	 * @param string $uniqueKey
	 * @param array $columns
	 * @return array
	 */
	public function find($id, $uniqueKey = 'id', $columns = ['*'])
	{
		return $this->where($uniqueKey, $id)->first($columns);
	}

	/**
	 * Get a single column's value from the first result of a query
	 *
	 * Получаем значение заданного столбца из первой строки результата запроса
	 *
	 * @param string $column
	 * @return mixed
	 */
	public function value($column)
	{
		return $this->select($column)->limit(1)->get(true, \PDO::FETCH_COLUMN);
	}

	/**
	 * Execute the query and get the first result
	 *
	 * @param array $columns
	 * @return array
	 */
	public function first($columns = ['*'])
	{
		return $this->select($columns)->limit(1)->get(true);
	}

	/**
	 * Get an array with the values of a given column
	 *
	 * @param string $column
	 * @param string|null $key
	 * @return array
	 */
	public function pluck($column, $key = null)
	{
		if ($key) {
			$column = "{$key}, {$column}";
		}

		$sql = "SELECT {$column} FROM {$this->table}";
		$stm = $this->query($sql);

		return $stm->fetchAll($key ? \PDO::FETCH_KEY_PAIR : \PDO::FETCH_COLUMN);
	}

	/**
	 * Get the number of rows in the table
	 *
	 * Получаем количество строк в таблице
	 *
	 * @param string $column
	 * @return int
	 */
	public function count($column = '*')
	{
		$sql = "SELECT COUNT({$column}) FROM {$this->table}";
		$stm = $this->query($sql);

		return $stm->fetchColumn();
	}

	/**
	 * Retrieve the minimum value of a given column
	 *
	 * Получаем наименьшее значение выбранного столбца
	 *
	 * @param string $column
	 * @return mixed
	 */
	public function min($column)
	{
		$sql = "SELECT MIN({$column}) FROM {$this->table}";
		$stm = $this->query($sql);

		return $stm->fetchColumn();
	}

	/**
	 * Retrieve the maximum value of a given column
	 *
	 * Получаем наибольшее значение выбранного столбца
	 *
	 * @param string $column
	 * @return mixed
	 */
	public function max($column)
	{
		$sql = "SELECT MAX({$column}) FROM {$this->table}";
		$stm = $this->query($sql);

		return $stm->fetchColumn();
	}

	/**
	 * Insert new records into the database
	 *
	 * Добавляем новые записи в базу данных
	 *
	 * @param array $values
	 * @return int|array
	 */
	public function insert($values)
	{
		if (empty($values)) {
			return 0;
		}

		if (!is_array(reset($values))) {
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

	/**
	 * Update records in the database
	 *
	 * Обновляем записи в базе данных
	 *
	 * @param array $values ([$column => $value] or [$column => [$expression]])
	 * @return int
	 */
	public function update($values)
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

	/**
	 * Undocumented function
	 *
	 * @param [type] $values
	 * @param array $keys
	 * @param array $update
	 * @return void
	 */
	public function upsert($values, $keys = [], $update = [])
	{
		if (empty($values)) {
			return 0;
		} elseif ($update === []) {
			return $this->insert($values);
		}

		if (!is_array(reset($values))) {
			$values = [$values];
		} else {
			foreach ($values as $key => $value) {
				ksort($value);

				$values[$key] = $value;
			}
		}

		if (is_null($update)) {
			$update = array_keys(reset($values));
		}

		$rowCount = 0;

		$this->pdo->beginTransaction();

		try {
			foreach ($values as $value) {
				$this->params = $value;

				$values = array_diff_key($value, array_flip($keys));
				$columns = array_diff_key($this->params, $value);

				$rowCount += $this->where($columns)->update($values);
			}

			$this->pdo->commit();
		} catch (\PDOException $e) {
			$this->pdo->rollBack();
		}

		return $rowCount;
	}

	/**
	 * Increment a column's value by a given amount
	 *
	 * Увеличиваем значение столбца на заданную величину
	 *
	 * @param string $column
	 * @param int $amount
	 * @param array $extra
	 * @return int
	 */
	public function increment($column, $amount = 1, $extra = [])
	{
		if (!is_numeric($amount))
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

	/**
	 * Decrement a column's value by a given amount
	 *
	 * Уменьшаем значение столбца на заданную величину
	 *
	 * @param string $column
	 * @param int $amount
	 * @param array $extra
	 * @return int
	 */
	public function decrement($column, $amount = 1, $extra = [])
	{
		return $this->increment($column, -$amount, $extra);
	}

	/**
	 * Delete records from the database
	 *
	 * Удаляем записи из базы данных
	 *
	 * @return int
	 */
	public function delete()
	{
		$where = $this->getPreparedWhere();

		$sql = "DELETE FROM {$this->table}{$where}";

		$stm = $this->prepare($sql);

		return $stm->rowCount();
	}

	/**
	 * Wrapper for PDO::prepare method
	 *
	 * Подготавливает запрос к выполнению и возвращает связанный с этим запросом объект
	 *
	 * @param string $sql
	 * @return \PDOStatement
	 */
	private function prepare($sql)
	{
		$stm = $this->pdo->prepare($sql);
		$stm->execute($this->params);

		$this->queries[] = ['your_query' => $stm->_debugQuery(), 'safe_query' => $stm->queryString];

		return $stm;
	}

	/**
	 * Wrapper for POD::query method
	 *
	 * Выполняет SQL-запрос и возвращает результирующий набор в виде объекта PDOStatement
	 *
	 * @param string $sql
	 * @return \PDOStatement
	 */
	private function query($sql)
	{
		$stm = $this->pdo->query($sql);

		$this->queries[] = ['your_query' => $stm->_debugQuery(), 'safe_query' => $stm->queryString];

		return $stm;
	}

	/**
	 * Get an array with current queries to the database
	 *
	 * Получаем массив с текущими запросами к базе данных
	 *
	 * @return array
	 */
	public function getQueries()
	{
		return $this->queries;
	}

	/**
	 * @return string
	 */
	private function getPreparedDistinct()
	{
		return $this->distinct ? 'DISTINCT ' : '';
	}

	/**
	 * @return string
	 */
	private function getPreparedColumns()
	{
		if (!$this->columns) {
			$this->select(['*']);
		}

		return implode(', ', $this->columns);
	}

	/**
	 * @return string
	 */
	private function getPreparedJoins()
	{
		return implode('', $this->joins);
	}

	/**
	 * @return string
	 */
	private function getPreparedWhere()
	{
		$result = '';

		foreach ($this->wheres as $where) {
			extract($where);

			if ($type == 'raw') {
				if ($sql) {
					$result .= ($result ? strtoupper(" {$boolean} ") : ' WHERE ') . $sql;
				}
			} else {
				$result .= ($result ? strtoupper(" {$boolean} ") : ' WHERE ') . "{$column} {$operator} {$value}";
			}
		}

		return $result;
	}

	/**
	 * @return string
	 */
	private function getPreparedHaving()
	{
		return !empty($this->having) ? " HAVING {$this->having}" : '';
	}

	/**
	 * @return string
	 */
	private function getPreparedGroupBy()
	{
		return !empty($this->groupBy) ? (' GROUP BY ' . implode(', ', $this->groupBy)) : '';
	}

	/**
	 * @return string
	 */
	private function getPreparedOrderBy()
	{
		$order = !empty($this->orderBy) ? ' ORDER BY ' : '';

		foreach ($this->orderBy as $column => $direction) {
			$order .= "{$column} {$direction}, ";
		}

		if (!empty($order)) {
			$order = rtrim($order, ', ');
		}

		return $order;
	}

	/**
	 * @return string
	 */
	private function getPreparedLimit()
	{
		return $this->limit ? " LIMIT {$this->limit}" : '';
	}

	/**
	 * @return string
	 */
	private function getPreparedOffset()
	{
		return $this->offset ? " OFFSET {$this->offset}" : '';
	}
}
