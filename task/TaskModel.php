<?php

namespace module\task;

use module\lib\MysqliClient;

abstract class TaskModel implements Task
{

    /**
     * @var MysqliClient
     */
    protected $mysqliClient;
    /**
     * @var \module\lib\MysqliDb
     */
    protected $query;
    /**
     * @var \PDO | \Swoole\Database\PDOProxy | null
     */
    protected $poolObject;

    protected $isUsePool = false;

    /**
     * TaskModel constructor.
     * @param \PDO | \Swoole\Database\PDOProxy | null $poolObject
     */
    public function __construct($poolObject = null)
    {
        if ($poolObject !== null) {
            $this->isUsePool = true;
            $this->poolObject = $poolObject;
        } else {
            $this->mysqliClient = new MysqliClient();
            $this->query = $this->mysqliClient->getQuery();
        }
    }

    //关闭mysql短连接
    public function __destruct()
    {
        if ($this->isUsePool) {
            $this->poolObject = null;
        } else {
            $this->query->disconnect();
        }
        $this->query = null;
        $this->mysqliClient = null;
    }

}