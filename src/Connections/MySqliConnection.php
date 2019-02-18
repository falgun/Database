<?php
namespace Falgun\Database\Connections;

use mysqli;
use Falgun\Database\Configuration\Configuration;
use Falgun\Database\Connections\ConnectionInterface;
use Falgun\Database\Exceptions\MySqliConnectionException;

class MySqliConnection implements ConnectionInterface
{

    protected static $connection = [];

    /**
     * Connect to MySQL 
     * 
     * @param Configuration $configuration
     * @return mysqli
     * @throws MySqliConnectionException
     */
    public function connect(Configuration $configuration): mysqli
    {
        if (!empty(self::$connection) || isset(self::$connection[$configuration->database])) {
            return self::$connection[$configuration->database];
        }

        $connection = self::$connection[$configuration->database] = new mysqli($configuration->host, $configuration->user, $configuration->password, $configuration->database);

        if (!empty($connection->connect_errno)) {
            throw new MySqliConnectionException($connection->connect_error);
        }

        if ($configuration->characterSet !== null) {
            $connection->set_charset($configuration->characterSet);
        }



        return $connection;
    }

    public function disconnect(Configuration $configuration)
    {
        $this->getConnection($configuration)->close();
    }

    public function getConnection(Configuration $configuration)
    {
        if (!empty(self::$connection) && isset(self::$connection[$configuration->database])) {
            return self::$connection[$configuration->database];
        }

        return false;
    }

    public static function terminate()
    {
        if (!empty(self::$connection)) {
            foreach (self::$connection as $connection) {
                $connection->close();
            }
            return true;
        }

        return false;
    }
}
