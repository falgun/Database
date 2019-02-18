<?php
namespace Falgun\Database\Configuration;

use Falgun\Database\Exceptions\MySqliConnectionException;

class Configuration
{

    public $host, $user, $password, $database, $characterSet;

    public function loadFromFile(string $path)
    {

        if (file_exists($path) === false) {
            throw new MySqliConnectionException('Please provide valid DB config file path !');
        }

        $configuration = require $path;

        if (empty($configuration) || is_array($configuration) === false) {
            throw new \Exception('Confiuration file must have to return an array !');
        }
        if (isset($configuration['host']) === false) {
            throw new \Exception('Host not defined in DB confiuration at ' . $path . ' !');
        }
        if (isset($configuration['user']) === false) {
            throw new \Exception('User not defined in DB confiuration at ' . $path . ' !');
        }
        if (isset($configuration['password']) === false) {
            throw new \Exception('Password not defined in DB confiuration ' . $path . ' !');
        }
        if (isset($configuration['db']) === false) {
            throw new \Exception('database not defined in DB confiuration ' . $path . ' !');
        }

        $this->host = $configuration['host'];
        $this->user = $configuration['user'];
        $this->password = $configuration['password'];
        $this->database = $configuration['db'];
        $this->characterSet = $configuration['character-set'] ?? null;
    }
}
