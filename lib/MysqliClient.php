<?php

namespace module\lib;

class MysqliClient
{
    /**
     * @var MysqliDb
     */
    private $query;

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function databaseConfig()
    {
        $configDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        $filePath = $configDir . 'database.php';
        if (!file_exists($filePath)) {
            throw new \Exception('database config not exist:' . $filePath);
        }
        return include($filePath);
    }

    public function getQuery()
    {
        $config = $this->databaseConfig();
        $this->query = new MysqliDb([
            'host' => $config['host'],
            'username' => $config['user'],
            'password' => $config['password'],
            'db' => $config['dbname'],
            'port' => $config['port'],
            //'prefix' => 't_',
            'charset' => $config['charset'],
        ]);
        return $this->query;
    }

}