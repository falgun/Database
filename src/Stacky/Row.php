<?php
namespace Falgun\Database\Stacky;

use stdClass;

class Row extends stdClass
{

    public static function __set_state($array)
    {
        $row = new Row();
        if (!empty($array)) {
            foreach ($array as $column => $value) {
                $row->{$column} = $value;
            }
        }

        return $row;
    }
}
