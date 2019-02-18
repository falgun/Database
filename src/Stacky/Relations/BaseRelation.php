<?php
namespace Falgun\Database\Stacky\Relations;

class BaseRelation implements RelationInterface
{

    protected $alias;
    protected $columns;
    protected $cacheTTL;

    public function nameAs(string $alias)
    {
        $this->alias = $alias;

        return $this;
    }

    public function columns(array $columns)
    {
        $this->columns = $columns;

        return $this;
    }

    public function cache(int $cacheTTL)
    {
        $this->cacheTTL = $cacheTTL;

        return $this;
    }
}
