<?php
namespace Falgun\Database\Stacky\Relations;

use Falgun\Database\Stacky\Model;

class HasManyToMany extends BaseRelation
{

    protected $localColumn,
        $foreignModel,
        $foreignColumn,
        $mediumModel,
        $mediumLocalColumn,
        $mediumForeignColumn;

    public function __construct($localColumn, $foreignModel, $foreignColumn, $mediumModel, $mediumLocalColumn, $mediumForeignColumn)
    {
        $this->localColumn = $localColumn;
        $this->foreignModel = $foreignModel;
        $this->foreignColumn = $foreignColumn;
        $this->mediumModel = $mediumModel;
        $this->mediumLocalColumn = $mediumLocalColumn;
        $this->mediumForeignColumn = $mediumForeignColumn;
    }

    public function fetchMultiData($results)
    {
        /* @var $foreignModel Model */
        $foreignModel = new $this->foreignModel();

        $localColumn = $this->localColumn ?? 'id';
        $alias = $this->alias ?: $foreignModel->getTable();


        foreach ($results as $k => $result) {
            $resultIndex[$result->{$localColumn}] = $k;
        }

        $mediumModel = new $this->mediumModel();
        $mediumTable = $mediumModel->getTable();
        $foreignTable = $foreignModel->getTable();


        if (is_array($this->columns)) {
            $this->columns[] = $mediumTable . '.' . $this->mediumLocalColumn;
        } else {
            $this->columns = [$foreignTable . '.*', $mediumTable . '.' . $this->mediumLocalColumn];
        }

        $relatedResults = $foreignModel->join($mediumTable, $mediumTable . '.' . $this->mediumForeignColumn, $foreignTable . '.id')
            ->setColumnType($this->mediumLocalColumn, 'int')
            ->andIn($mediumTable . '.' . $this->mediumLocalColumn, array_keys($resultIndex))
            ->orderBy($foreignTable . '.id')
            ->getAll($this->columns, $this->cacheTTL);

        if (!empty($relatedResults)) {
            foreach ($relatedResults as $relatedResult) {
                $results[$resultIndex[$relatedResult->{$this->mediumLocalColumn}]]->{$foreignTable}[] = $relatedResult;
            }
        }
    }

    public function fetchSingleData($result)
    {
        /* @var $foreignModel Model */
        $foreignModel = new $this->foreignModel();
        $alias = $this->alias ?: $foreignModel->getTable();

        $relatedValue = $result->{$this->localColumn};

        $mediumModel = new $this->mediumModel();
        $mediumTable = $mediumModel->getTable();
        $foreignTable = $foreignModel->getTable();

        if (is_array($this->columns) === false) {
            $this->columns = [$foreignTable . '.*'];
        }

        $relatedResults = $foreignModel->join($mediumTable, $mediumTable . '.' . $this->mediumForeignColumn, $foreignTable . '.id')
            ->setColumnType($this->mediumLocalColumn, 'int')
            ->where($mediumTable . '.' . $this->mediumLocalColumn, $relatedValue)
            ->orderBy($foreignTable . '.id')
            ->getAll($this->columns, $this->cacheTTL);

        $result->{$foreignTable} = $relatedResults;
    }

    public function prepareDeleteSQL(&$sql, &$deletable, $localTable)
    {
        $mediumModel = new $this->mediumModel();
        $mediumTable = $mediumModel->getTable();

        $sql .= ' LEFT JOIN ' . $mediumTable . ' ON ' . $mediumTable . '.' . $this->mediumLocalColumn . ' = ' . $localTable . '.id';
        $deletable[] = $mediumTable;

        return true;
    }

    public function attachManyToMany($localValue, $foreignIDs)
    {
        /* @var $mediumModel Model */
        $mediumModel = new $this->mediumModel();
        $mediumTable = $mediumModel->getTable();

        $mediumModel->where($this->mediumLocalColumn, $localValue);

        if (!empty($foreignIDs)) {
            $mediumModel->notIN($this->mediumForeignColumn, $foreignIDs);
        }

        $mediumModel->delete();

        if (empty($foreignIDs)) {
            return true;
        }

        $currentElements = $mediumModel->where($this->mediumLocalColumn, $localValue)->orderBy('id')->getAll();
        $currentElementIDs = !empty($currentElements) ? $currentElements->column($this->mediumForeignColumn) : [];

        $shouldBeAdded = array_diff($foreignIDs, $currentElementIDs);

        if (!empty($shouldBeAdded)) {
            foreach ($shouldBeAdded as $elementID) {
                $mediumModel->setFieldValue($this->mediumLocalColumn, $localValue);
                $mediumModel->setFieldValue($this->mediumForeignColumn, $elementID);
            }
            return $mediumModel->insert(2);
        }

        return false;
    }
}
