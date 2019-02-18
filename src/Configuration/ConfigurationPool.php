<?php
namespace Falgun\Database\Configuration;

class ConfigurationPool
{

    protected $configurationPool;

    public function __construct()
    {
        $this->configurationPool = [];
    }

    /**
     * 
     * @param int|string $key
     * @param Configuration $configuration
     * @return bool
     */
    public function set($key, Configuration $configuration): bool
    {
        $this->configurationPool[$key] = $configuration;

        return $this->check($key);
    }

    /**
     * 
     * @param int|string $key
     * @return Configuration
     */
    public function get($key): Configuration
    {
        return $this->configurationPool[$key];
    }

    /**
     * 
     * @param int|string $key
     * @return bool
     */
    public function check($key): bool
    {
        return isset($this->configurationPool[$key]);
    }
}
