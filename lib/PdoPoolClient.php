<?php

namespace module\lib;

use RuntimeException;
use Swoole\Coroutine;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;
use Swoole\Runtime;

class PdoPoolClient
{
    /**
     * @var Query
     */
    private $query;

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function database()
    {
        $configDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        $filePath = $configDir . 'database.php';
        if (!file_exists($filePath)) {
            throw new \Exception('database config not exist:' . $filePath);
        }
        return include($filePath);
    }

    /**
     * @return Query
     * @throws \Exception
     */
    public function getQuery()
    {
        $config = $this->database();
        $pdo = new \PDO("mysql:dbname={$config['dbname']};host={$config['host']};charset={$config['charset']}", "{$config['user']}", "{$config['password']}");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        $pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->query = new Query($pdo);
        return $this->query;
    }

    //ConnectionPool
    //ConnectionPool，原始连接池，基于 Channel 自动调度，支持传入任意构造器 (callable)，构造器需返回一个连接对象
    //
    //get 方法获取连接（连接池未满时会创建新的连接）
    //put 方法回收连接
    //fill 方法填充连接池（提前创建连接）
    //close 关闭连接池
    //Simps 框架 的 DB 组件 基于 Database 进行封装，实现了自动归还连接、事务等功能，可以进行参考或直接使用，具体可查看 Simps 文档
    //
    //Database
    //各种数据库连接池和对象代理的高级封装，支持自动断线重连。目前包含 PDO，Mysqli，Redis 三种类型的数据库支持：
    //
    //PDOConfig, PDOProxy, PDOPool
    //MysqliConfig, MysqliProxy, MysqliPool
    //RedisConfig, RedisProxy, RedisPool
    //1. MySQL 断线重连可自动恢复大部分连接上下文 (fetch 模式，已设置的 attribute，已编译的 Statement 等等)，但诸如事务等上下文无法恢复，若处于事务中的连接断开，将会抛出异常，请自行评估重连的可靠性；
    //2. 将处于事务中的连接归还给连接池是未定义行为，开发者需要自己保证归还的连接是可重用的；
    //3. 若有连接对象出现异常不可重用，开发者需要调用 $pool->put(null); 归还一个空连接以保证连接池的数量平衡。
    public function poolDemo()
    {
        Runtime::enableCoroutine();
        $s = microtime(true);
        Coroutine\run(function () {
            $pdoConfig = (new PDOConfig)->withHost('127.0.0.1')->withPort(3306)->withDbName('test')
                ->withCharset('utf8mb4')->withUsername('root')->withPassword('root');
            $pool = new PDOPool($pdoConfig);
            for ($n = N; $n--;) {
                Coroutine::create(function () use ($pool) {
                    $pdo = $pool->get();
                    $statement = $pdo->prepare('SELECT ? + ?');
                    if (!$statement) {
                        throw new RuntimeException('Prepare failed');
                    }
                    $a = mt_rand(1, 100);
                    $b = mt_rand(1, 100);
                    $result = $statement->execute([$a, $b]);
                    if (!$result) {
                        throw new RuntimeException('Execute failed');
                    }
                    $result = $statement->fetchAll();
                    if ($a + $b !== (int)$result[0][0]) {
                        throw new RuntimeException('Bad result');
                    }
                    $pool->put($pdo);
                });
            }
        });
        $s = microtime(true) - $s;
        echo 'Use ' . $s . 's for ' . N . ' queries' . PHP_EOL;
    }

    /**
     * @param int $poolSize
     * @return PDOPool
     * @throws \Exception
     */
    public function initPool($poolSize = 64)
    {
        $config = $this->database();
        $pdoConfig = (new PDOConfig())->withHost($config['host'])->withPort($config['port'])->withDbName($config['dbname'])
            ->withCharset($config['charset'])->withUsername($config['user'])->withPassword($config['password']);
        $pool = new PDOPool($pdoConfig, $poolSize);
        return $pool;
    }

}