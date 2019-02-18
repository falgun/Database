<?php
namespace Falgun\Database\Connections;

use mysqli;
use Falgun\Database\Configuration\Configuration;

interface ConnectionInterface
{

    public function connect(Configuration $configuration): mysqli;
}
