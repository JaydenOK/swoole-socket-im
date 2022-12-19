<?php

namespace module\task;
/**
 * 工厂方法，生产任务模型
 * Class TaskFactory
 * @package module\task
 */
class TaskFactory
{
    const TASK_AMAZON = 'Amazon';
    const TASK_SHOPEE = 'Shopee';
    const TASK_EBAY = 'Ebay';

    public static function taskList()
    {
        $taskList = [];
        $class = new \ReflectionClass(TaskFactory::class);
        $constants = $class->getConstants();
        foreach ($constants as $key => $value) {
            if (strpos($key, 'TASK_') !== false) {
                $taskList[$key] = $value;
            }
        }
        return $taskList;
    }

    /**
     * @param $taskType
     * @param \PDO | \Swoole\Database\PDOProxy | null $pdo 为null则每个任务直接连接mysql，为PDO则使用连接池
     * @return AmazonModel|ShopeeModel|null
     * @throws \Exception
     */
    public static function factory($taskType, $pdo = null)
    {
        $task = null;
        switch ($taskType) {
            case self::TASK_AMAZON:
                $task = new AmazonModel($pdo);
                break;
            case self::TASK_SHOPEE:
                $task = new ShopeeModel($pdo);
                break;
            default:
                break;
        }
        if ($task === null) {
            throw new \Exception('task model not defined');
        }
        return $task;
    }


}