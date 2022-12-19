<?php

namespace module\server;


use Exception;
use InvalidArgumentException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class SocketServerManager
{
    const EVENT_START = 'start';
    const EVENT_OPEN = 'open';
    const EVENT_HANDSHAKE = 'handshake';
    const EVENT_MESSAGE = 'message';
    const EVENT_REQUEST = 'request';
    const EVENT_CLOSE = 'close';

    /**
     * @var Server
     */
    protected $server;
    /**
     * @var string
     */
    private $taskType;
    /**
     * @var int|string
     */
    private $port;
    private $processPrefix = 'co-server-';
    private $setting = ['worker_num' => 2, 'enable_coroutine' => true];
    /**
     * @var bool
     */
    private $daemon;
    /**
     * @var string
     */
    private $pidFile;
    /**
     * @var int
     */
    private $poolSize = 16;
    /**
     * 是否使用连接池，可参数指定，默认不使用
     * @var bool
     */
    private $isUsePool = false;
    /**
     * @var PDOPool
     */
    private $pool;
    private $checkAvailableTime = 1;
    private $checkLiveTime = 10;
    private $availableTimerId;
    private $liveTimerId;
    /**
     * @var Table
     */
    private $poolTable;

    public function run($argv)
    {
        try {
            $cmd = isset($argv[1]) ? (string)$argv[1] : 'status';
            $this->taskType = isset($argv[2]) ? (string)$argv[2] : '';
            $this->port = isset($argv[3]) ? (string)$argv[3] : 9501;
            $this->daemon = isset($argv[4]) && (in_array($argv[4], ['daemon', 'd', '-d'])) ? true : false;
            if (empty($this->taskType) || empty($this->port) || empty($cmd)) {
                throw new InvalidArgumentException('params error');
            }
            $this->pidFile = $this->taskType . '.pid';
            if (!in_array($this->taskType, TaskFactory::taskList())) {
                throw new InvalidArgumentException('task_type not exist');
            }
            switch ($cmd) {
                case 'start':
                    $this->start();
                    break;
                case 'stop':
                    $this->stop();
                    break;
                case 'status':
                    $this->status();
                    break;
                default:
                    break;
            }
        } catch (Exception $e) {
            $this->logMessage('Exception:' . $e->getMessage());
        }
    }

    private function start()
    {
        $this->server = new Server("0.0.0.0", $this->port);
        $this->bindEvent(self::EVENT_HANDSHAKE, [$this, 'onHandShake']);
        $this->bindEvent(self::EVENT_OPEN, [$this, 'onOpen']);
        $this->bindEvent(self::EVENT_MESSAGE, [$this, 'onMessage']);
        $this->bindEvent(self::EVENT_REQUEST, [$this, 'onRequest']);
        $this->bindEvent(self::EVENT_CLOSE, [$this, 'onClose']);
        $this->startServer();
    }

    //onHandShake 事件回调是可选的
    //设置 onHandShake 回调函数后不会再触发 onOpen 事件，需要应用代码自行处理，可以使用 $server->defer 调用 onOpen 逻辑
    //onHandShake 中必须调用 response->status() 设置状态码为 101 并调用 response->end() 响应，否则会握手失败.
    //内置的握手协议为 Sec-WebSocket-Version: 13，低版本浏览器需要自行实现握手
    public function onHandShake(Request $request, Response $response)
    {
        // print_r( $request->header );
        // if (如果不满足我某些自定义的需求条件，那么返回end输出，返回false，握手失败) {
        //    $response->end();
        //     return false;
        // }
        // websocket握手连接算法验证
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            $response->end();
            return false;
        }
        echo $request->header['sec-websocket-key'];
        $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];
        // WebSocket connection to 'ws://127.0.0.1:9502/'
        // failed: Error during WebSocket handshake:
        // Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }
        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }
        $response->status(101);
        $response->end();
    }

    //当 WebSocket 客户端与服务器建立连接并完成握手后会回调此函数。
    public function onOpen(Request $request, Response $response)
    {
        echo "open: fd{$request->fd}\n";
    }

    //$frame 是 Swoole\WebSocket\Frame 对象，包含了客户端发来的数据帧信息
    //onMessage 回调必须被设置，未设置服务器将无法启动
    //客户端发送的 ping 帧不会触发 onMessage，底层会自动回复 pong 包，也可设置 open_websocket_ping_frame 参数手动处理
    public function onMessage(Server $server, Frame $frame)
    {
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        $server->push($frame->fd, "this is server");
    }

    public function onRequest($request, $response)
    {
        // 接收http请求从get获取message参数的值，给用户推送
        // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
        foreach ($this->server->connections as $fd) {
            // 需要先判断是否是正确的websocket连接，否则有可能会push失败
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $request->get['message']);
            }
        }
    }

    public function onClose($ser, $fd)
    {
        echo "client {$fd} closed\n";
    }

    /**
     * 当前进程重命名
     * @param $processName
     * @return bool|mixed
     */
    private function renameProcessName($processName)
    {
        if (function_exists('cli_set_process_title')) {
            return cli_set_process_title($processName);
        } else if (function_exists('swoole_set_process_name')) {
            return swoole_set_process_name($processName);
        }
        return false;
    }

    private function setServerSetting($setting = [])
    {
        //开启内置协程，默认开启
        //当 enable_coroutine 设置为 true 时，底层自动在 onRequest 回调中创建协程，开发者无需自行使用 go 函数创建协程
        //当 enable_coroutine 设置为 false 时，底层不会自动创建协程，开发者如果要使用协程，必须使用 go 自行创建协程
        $this->httpServer->set(array_merge($this->setting, $setting));
    }

    private function bindEvent($event, callable $callback)
    {
        $this->server->on($event, $callback);
    }

    private function startServer()
    {
        $this->server->start();
    }

    private function logMessage($logData)
    {
        $logData = (is_array($logData) || is_object($logData)) ? json_encode($logData, JSON_UNESCAPED_UNICODE) : $logData;
        echo date('[Y-m-d H:i:s]') . $logData . PHP_EOL;
    }

    private function stop($force = false)
    {
        $pidFile = MODULE_DIR . '/logs/' . $this->pidFile;
        if (!file_exists($pidFile)) {
            throw new Exception('server not running');
        }
        $pid = file_get_contents($pidFile);
        if (!Process::kill($pid, 0)) {
            unlink($pidFile);
            throw new Exception("pid not exist:{$pid}");
        } else {
            if ($force) {
                Process::kill($pid, SIGKILL);
            } else {
                Process::kill($pid);
            }
        }
    }

    private function status()
    {
        $pidFile = MODULE_DIR . '/logs/' . $this->pidFile;
        if (!file_exists($pidFile)) {
            throw new Exception('server not running');
        }
        $pid = file_get_contents($pidFile);
        //$signo=0，可以检测进程是否存在，不会发送信号
        if (!Process::kill($pid, 0)) {
            echo 'not running, pid:' . $pid . PHP_EOL;
        } else {
            echo 'running, pid:' . $pid . PHP_EOL;
        }
    }

    /**
     * swoole官方连接池，PDOProxy 实现了自动重连(代理模式)，构造函数注入 \PDO 对象。即$__object属性
     * 可改用EasySwoole连接池
     * @return PDO|PDOProxy
     * @throws Exception
     */
    private function getPoolObject()
    {
        $pdo = $this->pool->get();
        if (!($pdo instanceof PDOProxy || $pdo instanceof PDO)) {
            throw new Exception('getNullPoolObject');
        }
        $this->logMessage('pdo get:' . spl_object_hash($pdo));
        defer(function () use ($pdo) {
            //协程函数结束归还对象
            if ($pdo !== null) {
                $this->logMessage('pdo put:' . spl_object_hash($pdo));
                $this->pool->put($pdo);
            }
        });
        return $pdo;
    }

    //连接池对象注意点：
    //1，需要定期检查是否可用；
    //2，需要定期更新对象，防止在任务执行过程中连接断开（记录最后获取，使用时间，定时校验对象是否留存超时）
    public function checkPool()
    {
        if (true) {
            return 'not support now';
        }
        $this->availableTimerId = Timer::tick($this->checkAvailableTime * 1000, function () {

        });

        $this->liveTimerId = Timer::tick($this->checkLiveTime * 1000, function () {
        });
    }

    private function clearTimer()
    {
        if ($this->availableTimerId) {
            Timer::clear($this->availableTimerId);
        }
        if ($this->liveTimerId) {
            Timer::clear($this->liveTimerId);
        }
    }

    private function createTable()
    {
        if (true) {
            return 'not support now';
        }
        //存储数据size，即mysql总行数
        $size = 1024;
        $this->poolTable = new Table($size);
        $this->poolTable->column('created', Table::TYPE_INT, 10);
        $this->poolTable->column('pid', Table::TYPE_INT, 10);
        $this->poolTable->column('inuse', Table::TYPE_INT, 10);
        $this->poolTable->column('loadWaitTimes', Table::TYPE_FLOAT, 10);
        $this->poolTable->column('loadUseTimes', Table::TYPE_INT, 10);
        $this->poolTable->column('lastAliveTime', Table::TYPE_INT, 10);
        $this->poolTable->create();
    }

    public function s()
    {
        $this->server = new Server("0.0.0.0", 9501);
        $this->server->on('open', function (Server $server, $request) {
            echo "server: handshake success with fd{$request->fd}\n";
        });
        $this->server->on('message', function (Server $server, $frame) {
            echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
            $server->push($frame->fd, "this is server");
        });
        $this->server->on('close', function ($ser, $fd) {
            echo "client {$fd} closed\n";
        });
        $this->server->on('request', function ($request, $response) {
            // 接收http请求从get获取message参数的值，给用户推送
            // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
            foreach ($this->server->connections as $fd) {
                // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                if ($this->server->isEstablished($fd)) {
                    $this->server->push($fd, $request->get['message']);
                }
            }
        });
        $this->server->start();
    }

}