<?php
namespace Falgun\Database\Stacky;

use Falgun\Database\Stacky\Relations\HasOne;
use Falgun\Database\Stacky\Relations\HasMany;
use Falgun\Database\Stacky\Relations\BelongsTo;
use Falgun\Database\Stacky\Relations\HasManyToMany;
use Falgun\Database\Exceptions\InvalidStatementException;

trait RelationTrait
{

    protected $relations = [];

    protected function hasOne($foreignModel, $foreignColumn, $localColumn = 'id')
    {
        return $this->relations[] = new HasOne($localColumn, $foreignModel, $foreignColumn);
    }

    protected function hasMany($foreignModel, $foreignColumn)
    {
        return $this->relations[] = new HasMany('id', $foreignModel, $foreignColumn);
    }

    protected function hasManyToMany($foreignModel, $mediumModel, $mediumLocalColumn, $mediumForeignColumn)
    {
        return $this->relations[] = new HasManyToMany('id', $foreignModel, 'id', $mediumModel, $mediumLocalColumn, $mediumForeignColumn);
    }

    protected function belongsTo($foreignModel, $localColumn, $foreignColumn = 'id')
    {
        return $this->relations[] = new BelongsTo($localColumn, $foreignModel, $foreignColumn);
    }

    protected function loadRelatedMultiData($results)
    {
        if (empty($results) || empty($this->relations)) {
            return $results;
        }

        foreach ($this->relations as $relation) {
            $relation->fetchMultiData($results);
        }

        return $results;
    }

    protected function loadRelatedSingleData($result)
    {
        if (empty($result) || empty($this->relations)) {
            return $result;
        }

        foreach ($this->relations as $relation) {
            $relation->fetchSingleData($result);
        }

        return $result;
    }

    public function returnRelatedDeleteSQL($deletable = [])
    {
        $sql = '';

        if (empty($this->relations)) {
            return compact('sql', 'deletable');
        }

        foreach ($this->relations as $relation) {
            $relation->prepareDeleteSQL($sql, $deletable, $this->getTable());
        }


        return compact('sql', 'deletable');
    }

    public function attachTo($localValue, $foreignIDs)
    {
        if (empty($this->relations)) {
            throw new InvalidStatementException('Please define a relationship first !');
        }

        $relation = end($this->relations);
        $this->clearRelations();

        if (($relation instanceof HasManyToMany) === false) {
            throw new InvalidStatementException('Please define a many2many relationship first !');
        }

        return $relation->attachManyToMany($localValue, $foreignIDs);
    }

    public function getRelations()
    {
        return $this->relations;
    }

    public function clearRelations()
    {
        $this->relations = [];

        return $this;
    }
}
