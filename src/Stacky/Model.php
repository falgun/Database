<?php
namespace Falgun\Database\Stacky;

use Falgun\DInjector\Singleton;
use Falgun\Database\Configuration\Configuration;
use Falgun\Database\Connections\MySqliConnection;
use Falgun\Database\Configuration\ConfigurationPool;

abstract class Model extends QueryExecuter
{

    use RelationTrait;

    protected $config;
    protected $connection;
    protected $connectionClass;

    public final function __construct()
    {
        parent::__construct();

        $this->connect();

        if (method_exists($this, 'boot')) {
            $this->boot();
        }
    }

    protected function connect()
    {
        /**
         * We are forcing MySQL
         * because we don't have plan for any other driver
         */
        $this->connectionClass = new MySqliConnection();
        $this->connection = $this->connectionClass->connect($this->getConfiguration());
    }

    public function disconnect()
    {
        $this->connectionClass->disconnect($this->getConfiguration());
    }

    protected function getConfiguration(): Configuration
    {
        if (empty($this->config)) {
            throw new MySqliConnectionException('Please provide DB config !');
        }
        /**
         * We break "SingleTon is BAD" rule here
         * Just for simplicity & caching
         * Don't get mad...LOL
         */
        /* @var $configurationPool ConfigurationPool */
        $configurationPool = Singleton::get(ConfigurationPool::class);
        
        if ($configurationPool->check($this->config)) {
            return $configurationPool->get($this->config);
        }

        $configuration = new Configuration();

        $configuration->loadFromFile(CONFIG_DIR . DS . $this->config);
        return $configuration;
    }
}
