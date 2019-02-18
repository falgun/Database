<?php
namespace Falgun\Database\Stacky;

use Falgun\DInjector\Singleton;
use Falgun\Reporter\DevReporter;
use Falgun\Database\Exceptions\InvalidColumnException;
use Falgun\Database\Exceptions\InvalidStatementException;

abstract class QueryExecuter extends QueryBuilder
{

    protected $stmt;
    protected $queryBind;
    protected $isCached;
    protected $cachePath;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Run Query
     * @param null
     * @return this
     */
    public function runQuery()
    {
        $this->prepareQuery();
        $this->bindFieldType($this->getFields());
        $this->bindParam();

        $this->stmt->execute();

        if (!empty($this->stmt->error)) {
            return $this->stmtError($this->stmt);
        }

        //Save SQL for cache
        $this->cacheFilePath();
        //Clear all initiated properties
        $this->cleanProperties();

        return $this;
    }

    public function cleanProperties()
    {
        if (isDebug()) {
            $cached = ($this->isCached) ? 'yes' : 'no';
            $tempArr = array_combine($this->queryColumns, $this->queryValues);

            $sqlDetails = array("sql" => $this->sql,
                "values" => $tempArr,
                "bind" => $this->queryBind,
                "cached" => $cached);
            ($this->isCached) ? $sqlDetails['cache time'] = $this->cacheTime : '';

            $devReporter = Singleton::get(DevReporter::class);
            $devReporter->sqlDetails($sqlDetails);
        }

        $this->sql = '';
        $this->queryColumns = [];
        $this->queryValues = [];
        $this->modifyColumns = [];
        $this->queryBind = '';
        $this->conditionStarted = false;
    }

    /**
     * Prepare Query for DB
     *
     * @param DB instance
     * @return boolian
     */
    private function prepareQuery()
    {
        $this->stmt = $this->connection->prepare($this->sql);

        if ($this->stmt === false) {
            throw new InvalidStatementException('There is something wrong with your sql : ' . $this->sql . '<br>' . $this->connection->error);
        }
        return true;
    }

    /**
     * Bind Params to its types
     */
    private function bindParam()
    {
        $valueCount = count($this->queryValues);
        $bindCount = strlen($this->queryBind);

        if (empty($this->queryValues)) {
            return true;
        }

        if ($valueCount !== $bindCount) {
            throw new \Exception('Bind Param not matched with values');
        }

        return $this->stmt->bind_param($this->queryBind, ...$this->queryValues);
    }

    /**
     * Bind Field names from SQL
     */
    private function getFields()
    {
        if (!empty($this->queryColumns)) {
            return $this->queryColumns;
        }
        return false;
    }

    /**
     * Bind Fields to its types
     *
     * @param Fields
     */
    private function bindFieldType($columns)
    {
        if (empty($columns) || is_array($columns) === false) {
            return false;
        }

        foreach ($columns as $column) {
            if ($column === 'offset' || $column === 'limit') {
                $this->queryBind .= 'i';
                continue;
            }

            if (strpos($column, '.') !== false) {
                $column = trim(trim(strstr($column, '.'), '.'));
            }

            if (isset($this->columns[$column]) === true) {

                $type = $this->columns[$column];

                switch ($type) {
                    case 'int':
                        $this->queryBind .= 'i';
                        break;

                    case 'string':
                        $this->queryBind .= 's';
                        break;

                    case 'double':
                        $this->queryBind .= 'd';
                        break;

                    case 'blob':
                        $this->queryBind .= 'b';
                        break;

                    default :
                        $this->queryBind .= 'i';
                        break;
                }
            } else {
                throw new InvalidColumnException($column . ' don\'t have permision to access this DB');
                return false;
            }
        }

        return true;
    }

    protected function fetch_result(bool $single = false)
    {
        $result = $this->stmt->get_result();

        if ($result->num_rows === 0) {
            return false;
        }


        if ($single === false) {
            $data = array();

            while ($row = $this->fetch_row_data($result)) {
                if ($row === null) {
                    break;
                }
                $data[] = $row;
            }
        } else {
            $data = $this->fetch_row_data($result);
        }

        return $data;
    }

    protected function fetch_row_data($result)
    {
        return $result->fetch_object(Row::class);
    }

    protected function stmtError($stmt)
    {
        throw new InvalidStatementException($stmt->error);
    }
}
