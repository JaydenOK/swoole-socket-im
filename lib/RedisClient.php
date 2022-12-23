<?php

namespace module\lib;

class RedisClient
{
    /**
     * @var RedisProxy
     */
    private static $redisProxy;

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function redisConfig()
    {
        $configDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        $filePath = $configDir . 'redis.php';
        if (!file_exists($filePath)) {
            throw new \Exception('database config not exist:' . $filePath);
        }
        return include($filePath);
    }

    public function getRedisProxy()
    {
        if (is_null(self::$redisProxy)) {
            $config = $this->redisConfig();
            self::$redisProxy = new RedisProxy($config['host'], $config['port'], $config['password'], 30);
        }
        return self::$redisProxy;
    }

}
