<?php
namespace Falgun\Database\Stacky;

use Falgun\Helpers\Collection;

class RowCollection extends Collection
{

    public function returnAllID()
    {
        return array_column($this->collection, 'id');
    }
}
