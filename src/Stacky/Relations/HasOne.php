<?php
namespace Falgun\Database\Stacky\Relations;

use Falgun\Database\Stacky\Model;

class HasOne extends BaseRelation
{

    protected $localColumn,
        $foreignModel,
        $foreignColumn;

    public function __construct($localColumn, $foreignModel, $foreignColumn)
    {
        $this->localColumn = $localColumn;
        $this->foreignModel = $foreignModel;
        $this->foreignColumn = $foreignColumn;
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


        $relatedResults = $foreignModel->andIn($this->foreignColumn, array_keys($resultIndex))->orderBy('id')->getAll($this->columns, $this->cacheTTL);

        if (!empty($relatedResults)) {
            foreach ($relatedResults as $relatedResult) {
                $results[$resultIndex[$relatedResult->id]]->{$alias} = $relatedResult;
            }
        }
    }

    public function fetchSingleData($result)
    {
        /* @var $foreignModel Model */
        $foreignModel = new $this->foreignModel();
        $alias = $this->alias ?: $foreignModel->getTable();

        $relatedValue = $result->{$this->localColumn};

        $relatedResult = $foreignModel->where($this->foreignColumn, $relatedValue)->first()->get($this->columns, $this->cacheTTL);

        $result->{$alias} = $relatedResult;
    }

    public function prepareDeleteSQL(&$sql, &$deletable, $localTable)
    {
        /* @var $foreignModel Model */
        $foreignModel = new $this->foreignModel();
        $foreignTable = $foreignModel->getTable();


        $sql .= ' LEFT JOIN ' . $foreignTable . ' ON ' . $foreignTable . '.' . $this->foreignColumn . ' = ' . $localTable . '.' . $this->localColumn;
        $deletable[] = $foreignTable;

        // Recursive related data
        $relatedInfo = $foreignModel->returnRelatedDeleteSQL($sql, $deletable, $localTable);

        if (!empty($relatedInfo['sql'])) {
            $sql .= $relatedInfo['sql'];
            $deletable = array_merge($deletable, $relatedInfo['deletable']);
        }

        return true;
    }
}
