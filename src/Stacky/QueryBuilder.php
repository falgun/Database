<?php
namespace Falgun\Database\Stacky;

use Exception;
use InvalidArgumentException;
use Falgun\Cache\CacheFactory;
use Falgun\Pagination\PaginationInterface;

abstract class QueryBuilder
{

    protected $sql;
    protected $columns;
    protected $queryColumns;
    protected $modifyColumns;
    protected $queryValues;
    protected $conditionStarted;
    protected $cachier;
    protected $cachettl;
    protected $isCached;
    protected $cacheTime;

    public function __construct()
    {
        $this->queryColumns = [];
        $this->queryValues = [];
        $this->modifyColumns = [];
        $this->conditionStarted = false;
    }

    /**
     * Set up custom properties
     * 
     * @param string $name
     * @param mixed $value
     * @throws InvalidArgumentException
     */
    public function __set(string $column, $value)
    {
        if (isset($this->columns[$column])) {
            $this->queryColumns[] = $this->modifyColumns[] = $column;
            $this->queryValues[] = $value;
        } else {
            throw new InvalidArgumentException('Invalid DB column ' . $column . ' !');
        }
    }

    /**
     * 
     * @param string $name
     * @param type $arg
     * @return type
     * @throws InvalidArgumentException
     */
    public function __call(string $name, $arg)
    {
        if (preg_match('/findBy(?P<field>\w+)/', $name, $match)) {
            array_unshift($arg, strtolower($match['field']));

            return $this->find(...$arg);
        } else {

            throw new InvalidArgumentException($name . ' method not found !');
        }
    }

    /**
     * Get All rows from last select query
     *
     * @return array of object
     */
    public function getRows()
    {
        if (is_int($this->cachettl)) {
            $rows = $this->getFromCache();

            if ($rows !== null) {
                return $rows;
            }
        }

        if ($this->runQuery($this->sql) !== false) {

            $results = $this->loadRelatedMultiData($this->fetch_result(false));
            $this->clearRelations();

            if (empty($results)) {
                return false;
            }

            $collection = new RowCollection($results);

            if (is_int($this->cachettl)) {
                $this->setToCache($collection);
            }

            return $collection;
        }

        return false;
    }

    /**
     * Get Single row from last select query
     *
     * @return object
     */
    public function getRow()
    {
        if (is_int($this->cachettl)) {
            $row = $this->getFromCache();

            if ($row !== null) {
                return $row;
            }
        }

        if ($this->runQuery() !== false) {

            $result = $this->loadRelatedSingleData($this->fetch_result(true));
            $this->clearRelations();

            if (empty($result)) {
                return false;
            }

            if (is_int($this->cachettl)) {
                $this->setToCache($result);
            }

            return $result;
        }

        return false;
    }

    /**
     * Get Single Query result
     *
     * @param field names to fetch (optional)
     * @return mysqli object
     */
    public function get(array $columns = null)
    {
        $this->sql = 'SELECT ' . $this->prepareSelectColumns($columns) . ' FROM ' . $this->table . ' ' . $this->sql;

        return $this->getRow();
    }

    /**
     * Get all Query result
     *
     * @param array $columns field names to fetch (optional)
     * @return mixed
     */
    public function getAll(array $columns = null)
    {
        $this->sql = 'SELECT ' . $this->prepareSelectColumns($columns) . ' FROM ' . $this->table . ' ' . $this->sql;

        return $this->getRows();
    }

    public function prepareSelectColumns(array $columns = null)
    {
        if (is_null($columns)) {
            return '*';
        } elseif (is_string($columns)) {
            return $columns;
        } elseif (is_array($columns)) {
            return implode(', ', $columns);
        }

        throw new InvalidArgumentException('Invalid column name specified !');
    }

    /**
     *
     * @param Minimum and Maximum Field Name
     * @return array
     */
    public function getMinMax($minField, $maxField = null)
    {
        if ($maxField === null) {
            $maxField = $minField;
        }

        $this->sql = "SELECT MIN($minField) as min, MAX($maxField) as max FROM $this->table $this->sql";

        return $this->getRow();
    }

    /**
     * Find rows by its field name and values
     * 
     * @param string $column
     * @param mixed $value
     * @return mixed
     */
    public function find(string $column, $value)
    {
        $this->setSQL('SELECT * FROM ' . $this->table . ' WHERE ' . $column . '= ? ORDER BY id LIMIT 1', [$column => $value]);

        $result = $this->getRow();

        if ($result !== false) {
            return $result;
        }
        return false;
    }

    /**
     * 
     * @param int $id
     * @return mixed
     */
    public function findByID(int $id)
    {
        return $this->find('id', $id);
    }

    /**
     * Enable Cache for current SQL
     * 
     * @param int $ttl
     * @return $this
     */
    public function cache(int $ttl = null)
    {
        $this->cachettl = $ttl;

        return $this;
    }

    public function getFromCache()
    {
        if (empty($this->cachePath)) {
            $this->cacheFilePath();
        }

        $cachedResult = $this->getCachier()->get($this->cachePath, $this->cachettl);

        if ($cachedResult !== null) {
            $this->isCached = true;
            $this->cacheTime = $this->cachettl;
            $this->cleanProperties();
            $this->cachePath = [];
        }

        return $cachedResult;
    }

    public function setToCache($data)
    {
        $cached = $this->getCachier()->set($this->cachePath, $data, $this->cachettl);

        $this->cachettl = null;
        $this->cachePath = [];
        return true;
    }

    public function cacheFilePath()
    {
        if (empty($this->queryValues)) {
            $this->queryValues = [];
        }
        if (empty($this->queryColumns)) {
            $this->queryColumns = [];
        }

        $cacheFile = sha1(($this->sql) . '-' . (implode('-', $this->queryColumns)) . '-' . (implode('-', $this->queryValues)));

        $this->cachePath = $this->table . DS . $cacheFile;
    }

    public function flushCache()
    {
        $this->getCachier()->flush($this->table);
    }

    public function countRows()
    {
        $this->sql = 'SELECT COUNT(*) as total FROM ' . $this->table . ' ' . $this->sql;

        if (strpos($this->sql, 'GROUP ') !== false) {
            $counts = $this->getRows();

            if (!empty($counts)) {
                return count($counts);
            }
        } else {
            $count = $this->getRow();

            if (!empty($count)) {
                return $count->total;
            }
        }

        return 0;
    }

    protected function paginationCount(int $cache)
    {
        $sql = $this->sql;
        $values = $this->queryValues;
        $columns = $this->queryColumns;
        $cacheTTL = $this->cachettl;

        $this->cache($cache);

        if (!empty($this->sql)) {
            if (strpos($this->sql, 'ORDER') !== false) {
                $separator = 'ORDER';
            } elseif (strpos($this->sql, 'LIMIT') !== false) {
                $separator = 'LIMIT';
            } else {
                $separator = false;
            }

            if (!empty($separator)) {
                $this->sql = trim(strstr($this->sql, $separator, true));
            }
        }

        $total = $this->countRows($cache);

        $this->sql = $sql;
        $this->queryValues = $values;
        $this->queryColumns = $columns;
        $this->cachettl = $cacheTTL;

        if (!empty($total)) {
            return $total;
        }

        return 0;
    }

    public function paginate(PaginationInterface $pagination, int $cache = 3600)
    {
        $pagination->setTotalContent($this->paginationCount($cache));

        return $this;
    }

    public function join($secondTable, $secondField, $firstField, $comp = '=', $type = 'INNER')
    {
        $this->sql .= " $type JOIN $secondTable ON $secondField $comp $firstField ";
        return $this;
    }

    public function leftJoin($secondTable, $secondField, $firstField, $comp = '=')
    {
        $this->sql .= " LEFT JOIN $secondTable ON $secondField $comp $firstField ";
        return $this;
    }

    protected function whereCondition(string $condition, string $column, string $compare, $value)
    {
        $this->sql .= ' ' . $condition . ' ' . $column . ' ' . $compare . ' ? ';
        $this->queryColumns[] = $column;
        $this->queryValues[] = $value;
        $this->conditionStarted = true;

        return $this;
    }

    /**
     * Set Where Statement in SQL
     * <p>First parameter is supposed to be column name</p>
     * <p>Second parameter should be either compare sign or value.</p>
     * @param string $one
     * @param mixed $two
     * @param mixed $three
     * @return $this
     */
    public function where(string $one, $two, $three = null)
    {
        if ($three === null) {
            $column = $one;
            $compare = '=';
            $value = $two;
        } else {
            $column = $one;
            $compare = $two;
            $value = $three;
        }

        return $this->whereCondition($this->condition(), $column, $compare, $value);
    }

    /**
     * Set AND Where Statement in sql
     *
     * @param field names and values with comparison
     * @return $this
     */
    public function andWhere($one, $two, $three = null)
    {
        if ($three === null) {
            $column = $one;
            $compare = '=';
            $value = $two;
        } else {
            $column = $one;
            $compare = $two;
            $value = $three;
        }

        return $this->whereCondition($this->condition('AND'), $column, $compare, $value);
    }

    /**
     * Set OR Where Statement in sql
     *
     * @param field names and values with comparison
     * @return $this
     */
    public function orWhere($one, $two, $three = null)
    {
        if ($three === null) {
            $column = $one;
            $compare = '=';
            $value = $two;
        } else {
            $column = $one;
            $compare = $two;
            $value = $three;
        }

        return $this->whereCondition($this->condition('OR'), $column, $compare, $value);
    }

    /**
     * Group Multiple consition in a block
     * @param string $condition
     * @param \Closure $callback
     * @return $this
     */
    public function groupCondition(string $condition, \Closure $callback)
    {
        $this->sql .= ' ' . $condition . ' (';
        $callback($this);
        $this->sql .= ') ';

        return $this;
    }

    /**
     * Start Conditional Statement in Group
     * <p>Only use this in grouped condition</p>
     * <p>Must be first condition in the group</p>
     * 
     * @param string $one
     * @param mixed $two
     * @param mixed $three
     * @return $this
     */
    public function justWhere(string $one, $two, $three = null)
    {
        if ($three === null) {
            $column = $one;
            $compare = '=';
            $value = $two;
        } else {
            $column = $one;
            $compare = $two;
            $value = $three;
        }

        return $this->whereCondition('', $column, $compare, $value);
    }

    /**
     * Return currently usable condition
     * @param string $alt
     * @return string
     */
    public function condition(string $alt = 'AND')
    {
        if ($this->conditionStarted) {
            return $alt;
        }

        $this->conditionStarted = true;
        return 'WHERE';
    }

    /**
     * 
     * @param string $condition
     * @param string $column
     * @param mixed $values
     * @param bool $negetive
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function inCondition(string $condition, string $column, $values, bool $negetive = false)
    {
        if (is_object($values)) {
            throw new \InvalidArgumentException('value cannot be object !');
        }
        /**
         * value cannot be empty
         * but can be literal 0
         */
        if (!empty($values) || $values === '0' || $values === 0) {
            if (is_string($values)) {
                $values = explode(',', $values);
            }

            if (is_array($values)) {
                foreach ($values as $value) {
                    $this->setFieldValue($column, $value);
                }

                $placeholder = substr(str_repeat('?,', count($values)), 0, -1);
            } else {
                $this->setFieldValue($column, $values);
                $placeholder = '?';
            }

            $this->sql .= ' ' . $condition . ' ' . $column . ' ' . ($negetive === true ? 'NOT' : '') . ' IN (' . $placeholder . ') ';
        } else {
            $this->sql .= ' ' . $condition . ' ' . ($negetive === false ? '2=1' : '1=1');
        }

        return $this;
    }

    /**
     * 
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function whereIn(string $column, $values)
    {
        return $this->inCondition($this->condition(), $column, $values);
    }

    /**
     * 
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function andIn(string $column, $values)
    {
        return $this->inCondition($this->condition('AND'), $column, $values);
    }

    /**
     * 
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function orIn(string $column, $values)
    {
        return $this->inCondition($this->condition('OR'), $column, $values);
    }

    /**
     * 
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function notIN(string $column, $values)
    {
        return $this->inCondition($this->condition(), $column, $values, true);
    }

    /**
     * 
     * @param string $condition
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function match(string $condition, string $column, $value)
    {
        $this->sql .= $condition . ' MATCH(' . $column . ') AGAINST (? IN BOOLEAN MODE) ';
        $this->setFieldValue($column, $value);

        return $this;
    }

    /**
     * 
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function andMatch(string $column, $value)
    {
        return $this->match($column, $this->condition(), $value);
    }

    /**
     * 
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function orMatch(string $column, $value)
    {
        return $this->match($column, $this->condition('OR'), $value);
    }

    /**
     * 
     * @param string $column
     * @param string $order
     * @return $this
     */
    public function orderBy(string $column, string $order = 'ASC')
    {
        $column = preg_replace('#[^a-z0-9\-\_\.]#', '', $column);

        /**
          if (strpos($field, '.') !== false) {
          $column = trim(strstr($field, '.'), '.');
          }

          $field = $this->isColumn($field) ? $field : current($this->columns);
         * 
         */
        $order = strtoupper($order);
        if ($order === 'ASC') {
            $order = 'ASC';
        } else {
            $order = 'DESC';
        }

        $this->sql .= ' ORDER BY ' . $column . ' ' . $order . ' ';

        return $this;
    }

    /**
     * 
     * @return $this
     */
    public function orderByRand()
    {
        $this->sql .= ' ORDER BY RAND() ';

        return $this;
    }

    /**
     * Return First elements
     * @param string $column
     * @param int $limit
     * @return type
     */
    public function first(string $column = 'id', int $limit = 1)
    {
        return $this->orderBy($column, 'ASC')->limit($limit);
    }

    /**
     * Return last elements
     * @param string $column
     * @param int $limit
     * @return $this
     */
    public function last(string $column = 'id', int $limit = 1)
    {
        return $this->orderBy($column, 'DESC')->limit($limit);
    }

    public function groupBy(string $column, string $order = 'ASC')
    {

        if (preg_match('/[a-zA-Z_\-\.]+/', $column, $match)) {
            $column = $match[0];
        } else {
            return false;
        }

        $this->sql .= ' GROUP BY ' . $column . ' ';

        return $this;
    }

    /**
     * 
     * @param int $one
     * @param mixed $two
     * @return $this
     */
    public function limit($one, $two = false)
    {
        if ($one !== false && $two === false) {
            $start = 0;
            $limit = $one;
        } else if ($one !== false && $two !== false) {
            $start = $one;
            $limit = $two;
        } else {
            return $this;
        }

        $this->sql .= ' LIMIT ?,?';

        $this->queryColumns[] = 'offset';
        $this->queryValues[] = (int) $start;
        $this->queryColumns[] = 'limit';
        $this->queryValues[] = (int) $limit;

        return $this;
    }

    public function checkIfExists($column, $value)
    {
        $this->setSQL("SELECT COUNT(*) AS count FROM $this->table WHERE $column=? LIMIT 1", [$column => $value]);

        $tempRow = $this->getRow();

        return $tempRow->count !== 0;
    }

    public function fetchColumns($table = null)
    {
        if ($table === null) {
            $tables = $this->setQuery('DESCRIBE ' . $this->table)->getRows();
        } else {
            $tables = $this->setQuery('DESCRIBE ' . $table)->getRows();
        }

        if (!empty($tables)) {
            return $tables->column('Field');
        }

        return [];
    }

    /**
     * 
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @param mixed $condition
     * @return $this
     */
    public function between(string $column, $start, $end, $condition = false)
    {
        if ($condition === false) {
            $condition = $this->condition();
        }

        $this->sql .= ' ' . $condition . ' (' . $column . ' ' . 'BETWEEN ? AND ?) ';

        $this->setColumnValue($column, $start);
        $this->setColumnValue($column, $end);

        return $this;
    }

    public function dateRange(string $column, $startDate, $endDate, $groupBy = 'DATE')
    {
        if (!empty($groupBy)) {
            $groupedColumn = $groupBy . '(' . $column . ')';
        } else {
            $groupedColumn = $column;
        }

        $this->appendQuery(' ' . $this->returnCondition() . ' ');

        $this->appendQuery(' ' . $groupedColumn . ' BETWEEN ? AND ?');
        $this->setColumnValue($column, $startDate);
        $this->setColumnValue($column, $endDate);

        return $this;
    }

    /**
     * Insert values in DB
     *
     * @return last inserted ID
     */
    public function insert($multiple = false, $duplicate = false)
    {
        if (empty($this->modifyColumns)) {
            throw new Exception('Did you forgot to specify update field ?');
        }

        if ($multiple === false) {
            $columns = implode(',', $this->modifyColumns);
            $placeholders = implode(',', array_fill(0, count($this->modifyColumns), '?'));
        } else {
            $columns = implode(',', array_slice($this->modifyColumns, 0, $multiple));
            $placeholders = implode('), (', array_fill(0, (count($this->modifyColumns) / $multiple), implode(',', array_fill(0, $multiple, '?'))));
        }

        if ($duplicate === true) {
            if ($multiple !== false) {
                throw new Exception('Duplicate on multiple insert is not supported');
            }
            $upSqlArr = [];
            foreach ($this->modifyColumns as $key => $column) {
                $upSqlArr[] = " $column = ? ";
                $this->setColumnValue($column, $this->queryValues[$key]);
            }
            $upSql = implode(',', $upSqlArr);

            $this->sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $upSql";
        } else {
            $this->sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders)";
        }

        if ($this->runQuery() !== false) {
            $this->flushCache();
            return $this->stmt->insert_id;
        }
        return false;
    }

    /**
     * Update values in DB
     *
     * @return updated ID
     */
    public function update()
    {
        $upSqlArr = [];

        if (!empty($this->modifyColumns)) {
            foreach ($this->modifyColumns as $key => $column) {
                $upSqlArr[] = $column . ' = ?';
            }
        }

        $upSql = implode(',', $upSqlArr);
        unset($upSqlArr);

        if (!empty($this->sql)) {
            $this->sql = 'UPDATE ' . $this->table . ' SET ' . $upSql . ' ' . $this->sql;

            if ($this->runQuery() !== false) {
                $this->flushCache();
                return $this->stmt->affected_rows;
            }
        }

        return false;
    }

    /**
     * Modify single field value
     * 
     * @param string $column
     * @param mixed $value
     * @param string $modifier
     * @return mixed
     */
    public function modifyField(string $column, $value, string $modifier = '+')
    {
        $this->sql = "UPDATE $this->table SET $column = $column $modifier ? $this->sql";

        array_unshift($this->queryColumns, $column);
        array_unshift($this->queryValues, $value);

        if ($this->runQuery() !== false) {
            return $this->affected_row();
        }
        return false;
    }

    /**
     * Delete field from Table
     * 
     * @param array $tables
     * @return mixed
     */
    public function delete(array $tables = [])
    {
        $deletable = (!empty($tables)) ? $tables : [$this->getTable()];

        $deleteInfo = $this->returnRelatedDeleteSQL($deletable);

        $this->sql = 'DELETE ' . implode(',', $deleteInfo['deletable']) . ' FROM ' . $this->table . ' ' . $deleteInfo['sql'] . ' ' . $this->sql;

        if ($this->runQuery() !== false) {
            $this->flushCache();
            return $this->affected_row();
        }

        return false;
    }

    /**
     * Get affected row from last query
     *
     * @return int
     */
    public function affected_row()
    {
        return $this->stmt->affected_rows;
    }

    /**
     * Get id of last excuted SQL
     *
     * @return int
     */
    public function insert_id()
    {
        return $this->stmt->insert_id;
    }

    /**
     * use setSQL
     * @deprecated since version 4
     * @param string $sql
     * @param mixed $values
     * @return Model
     */
    public function setQuery(string $sql, $values = null)
    {
        return $this->setSQL($sql);
    }

    /**
     * @deprecated since version 4
     * @param string $sql
     * @param type $values
     * @return type
     */
    public function appendQuery(string $sql, $values = null)
    {
        return $this->appendSQL($sql, $values);
    }

    /**
     * @deprecated since version 4
     * @param string $sql
     * @param type $values
     * @return type
     */
    public function prependQuery(string $sql, $values = null)
    {
        return $this->prependSQL($sql, $values);
    }

    /**
     * Return Current SQL statement
     * @return string
     */
    public function getSQL()
    {
        return $this->sql;
    }

    /**
     * 
     * @param string $sql
     * @param mixed $values
     * @return $this
     */
    public function setSQL(string $sql, $values = null)
    {
        $this->sql = $sql;

        if (!empty($values) && is_array($values)) {
            foreach ($values as $key => $value) {
                $this->queryColumns[] = $key;
                $this->queryValues[] = $value;
            }
        }

        return $this;
    }

    /**
     * 
     * @param string $sql
     * @param mixed $values
     * @return $this
     */
    public function appendSQL(string $sql, $values = null)
    {
        $this->sql .= ' ' . $sql;

        if (!empty($values) && is_array($values)) {
            $this->setBatchColumnValue($values);
        }

        return $this;
    }

    /**
     * 
     * @param string $sql
     * @param mixed $values
     * @return $this
     */
    public function prependSQL(string $sql, $values = null)
    {
        $this->sql = $sql . ' ' . $this->sql;
        if (!empty($values) && is_array($values)) {
            $this->setBatchColumnValue($values);
        }

        return $this;
    }

    public function setBatchColumnValue(array $values)
    {
        if (is_array($values)) {
            foreach ($values as $column => $value) {
                $this->queryColumns[] = $column;
                $this->queryValues[] = $value;
            }
        }

        return $this;
    }

    public function setColumnValue(string $column, $value)
    {
        $this->queryColumns[] = $this->modifyColumns[] = $column;
        $this->queryValues[] = $value;

        return $this;
    }

    /**
     * use setColumnValue
     * @deprecated since version 4
     * @param string $field
     * @param mixed $value
     * @return Model
     * @deprecated since version 4
     */
    public function setFieldValue(string $field, $value)
    {
        return $this->setColumnValue($field, $value);
    }

    /**
     * 
     * @param string $field
     * @return $this
     * @deprecated since version 4
     */
    public function removeFieldValue(string $field)
    {
        if (($key = array_search($field, $this->queryColumns)) !== false) {
            unset($this->queryColumns[$key]);
            unset($this->queryValues[$key]);
        }
        if (($key = array_search($field, $this->modifyColumns)) !== false) {
            unset($this->modifyColumns[$key]);
        }

        return $this;
    }

    public function setColumnType($column, $type = 'int')
    {
        $this->columns[$column] = $type;

        return $this;
    }

    public function setCachier()
    {
        if (empty($this->cachier)) {
            $this->cachier = CacheFactory::build();
        }
    }

    /**
     * 
     * @return Falgun\Cache\Adapters\AdapterInterface
     */
    public function getCachier()
    {
        if (empty($this->cachier)) {
            $this->cachier = CacheFactory::build(CACHE_CLIENT, ROOT_DIR . DS . 'var' . DS . 'caches' . DS . 'stacky');
        }

        return $this->cachier;
    }

    public function isColumn(string $column)
    {
        return isset($this->columns[$column]);
    }

    public function getTable()
    {
        return $this->table;
    }

    public function returnColumns()
    {
        return $this->columns;
    }

    public function conditionStated()
    {
        return $this->conditionStarted;
    }

    public function backupQuery()
    {
        return array('sql' => $this->sql,
            'values' => $this->queryValues,
            'columns' => $this->queryColumns);
    }

    public function restoreBackUp(array $backup)
    {
        $this->sql = $backup['sql'];
        $this->queryValues = $backup['values'];
        $this->queryColumns = $backup['columns'];

        return $this;
    }
}
