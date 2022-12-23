<?php

namespace module\server;


use Exception;
use InvalidArgumentException;
use module\lib\Dispatcher;
use module\services\SocketService;
use module\services\UserService;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class SocketServerManager
{

    const EVENT_OPEN = 'open';
    const EVENT_HANDSHAKE = 'handshake';
    const EVENT_MESSAGE = 'message';
    const EVENT_REQUEST = 'request';
    const EVENT_CLOSE = 'close';
    const EVENT_DISCONNECT = 'disconnect';

    /**
     * @var Server
     */
    protected $server;
    /**
     * @var int
     */
    private $port;
    private $processPrefix = 'socket-im-';
    private $setting = [];
    /**
     * @var bool
     */
    private $daemon;
    /**
     * @var string
     */
    private $pidFile;

    public function run($argv)
    {
        try {
            $cmd = isset($argv[1]) ? (string)$argv[1] : 'status';
            $this->port = isset($argv[2]) ? (int)$argv[2] : 9501;
            $this->daemon = isset($argv[3]) && (in_array($argv[3], ['daemon', 'd', '-d'])) ? true : false;
            if (empty($this->port) || empty($cmd)) {
                throw new InvalidArgumentException('params error');
            }
            $this->pidFile = $this->port . '.pid';
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
        $this->bindEvent(self::EVENT_CLOSE, [$this, 'onClose']);
        $this->bindEvent(self::EVENT_REQUEST, [$this, 'onRequest']);
        //$this->bindEvent(self::EVENT_DISCONNECT, [$this, 'onDisconnect']);  // swoole > 4.7
        $this->renameProcessName($this->processPrefix . $this->port);
        $setting = [
            'daemonize' => (bool)$this->daemon,
            'log_file' => MODULE_DIR . '/logs/server-' . date('Y-m') . '.log',
            'pid_file' => MODULE_DIR . '/logs/' . $this->pidFile,
        ];
        $this->setServerSetting($setting);
        $this->startServer();
    }

    private function setServerSetting($setting = [])
    {
        //开启内置协程，默认开启
        $this->server->set(array_merge($this->setting, $setting));
    }

    private function bindEvent($event, callable $callback)
    {
        $this->server->on($event, $callback);
    }

    private function startServer()
    {
        $this->server->start();
    }

    //校验http登录的用户uid或者access_token解密出来的uid与fd，绑定到socket服务，redis存储
    //onHandShake 事件回调是可选的
    //设置 onHandShake 回调函数后不会再触发 onOpen 事件，需要应用代码自行处理，可以使用 $server->defer 调用 onOpen 逻辑
    //onHandShake 中必须调用 response->status() 设置状态码为 101 并调用 response->end() 响应，否则会握手失败.
    //内置的握手协议为 Sec-WebSocket-Version: 13，低版本浏览器需要自行实现握手
    public function onHandShake(Request $request, Response $response)
    {
        //验证access_token
        $accessToken = $request->get['access_token'] ?? '';
        if (empty($accessToken)) {
            $response->end('handshake fail');
            return false;
        }
        $uid = $this->authUser($accessToken);
        if (empty($uid)) {
            $response->end('auth fail');
            return false;
        }
        $this->handleUserAndFd($uid, $request->fd);
        // websocket握手连接算法验证
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            $response->end();
            return false;
        }
        //XazMS4NAEMiMcWyNqFRJTw==
        echo 'onHandShake, fd:' . $request->fd . ', uid:' . $uid . PHP_EOL;
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

    //当 WebSocket 客户端与服务器建立连接并完成握手后会回调此函数。设置 onHandShake 回调函数后不会再触发 onOpen 事件
    //onOpen 事件函数中可以调用 push 向客户端发送数据或者调用 close 关闭连接
    public function onOpen(Server $server, Request $request)
    {
        echo "onOpen: fd{$request->fd}\n";
    }

    //$frame 是 Swoole\WebSocket\Frame 对象，包含了客户端发来的数据帧信息
    //onMessage 回调必须被设置，未设置服务器将无法启动
    //客户端发送的 ping 帧不会触发 onMessage，底层会自动回复 pong 包，也可设置 open_websocket_ping_frame 参数手动处理
    //
    //$frame->fd	客户端的 socket id，使用 $server->push 推送数据时需要用到
    //$frame->data	数据内容，可以是文本内容也可以是二进制数据，可以通过 opcode 的值来判断
    //$frame->opcode	WebSocket 的 OPCode 类型，可以参考 WebSocket 协议标准文档，WEBSOCKET_OPCODE_TEXT = 0x1	文本数据;WEBSOCKET_OPCODE_BINARY = 0x2	二进制数据
    //$frame->finish	表示数据帧是否完整，一个 WebSocket 请求可能会分成多个数据帧进行发送（底层已经实现了自动合并数据帧，现在不用担心接收到的数据帧不完整）
    public function onMessage(Server $server, Frame $frame)
    {
        if (in_array($frame->opcode, [1, 2])) {
            //文本消息，二进制数据
            try {
                $dataArr = @json_decode($frame->data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $server->push($frame->fd, "error data format");
                }
                $data = (new SocketService())->message($server, $frame->fd, $frame->data);
                $return = ['code' => 200, 'message' => 'success', 'data' => $data];
            } catch (Exception $e) {
                $return = ['code' => 201, 'message' => 'success', 'data' => []];
            }
            $server->push($frame->fd, json_encode($return, JSON_UNESCAPED_UNICODE));
        } else {
            echo 'error opcode:' . $frame->opcode;
        }
    }

    //设置了 onRequest 回调，WebSocket\Server 也可以同时作为 HTTP 服务器
    //未设置 onRequest 回调，WebSocket\Server 收到 HTTP 请求后会返回 HTTP 400 错误页面
    //如果想通过接收 HTTP 触发所有 WebSocket 的推送，需要注意作用域的问题，面向过程请使用 global 对 WebSocket\Server 进行引用，面向对象可以把 WebSocket\Server 设置成一个成员属性
    public function onRequest(Request $request, Response $response)
    {
        try {
            $dispatcher = new Dispatcher($this->server, $request, $response);
            $data = $dispatcher->dispatch();
            $return = ['code' => 200, 'message' => 'success', 'data' => $data];
        } catch (Exception $e) {
            $return = ['code' => 201, 'message' => $e->getMessage(), 'data' => []];
        }
        $response->header('Content-Type', 'application/json;charset=utf-8');
        $response->end(json_encode($return));
        return true;
        //广播
        // 接收http请求从get获取message参数的值，给用户推送
        // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
//        foreach ($this->server->connections as $fd) {
//            // 需要先判断是否是正确的websocket连接，否则有可能会push失败
//            if ($this->server->isEstablished($fd)) {
//                $this->server->push($fd, $request->get['message']);
//            }
//        }
    }

    //socket关闭事件
    public function onClose(Server $server, $fd)
    {
        (new SocketService())->removeFd($fd);
        $server->close($fd);
        echo "client {$fd} closed\n";
    }

    //只有非 WebSocket 连接关闭时才会触发该事件。
    public function onDisconnect(Server $server, $fd)
    {
        echo "onDisconnect: {$fd} \n";
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

    //用户websocket握手验证
    private function authUser(string $accessToken)
    {
        $userService = new UserService();
        return $userService->authUser($accessToken);
    }

    private function handleUserAndFd($uid, $fd)
    {
        return (new SocketService())->saveUserAndFd($uid, $fd);
    }

}