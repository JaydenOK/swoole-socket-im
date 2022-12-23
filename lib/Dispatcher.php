<?php

namespace module\lib;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;

class Dispatcher
{
    /**
     * 模块（即目录名）
     * @var string
     */
    private $module;
    /**
     * @var string
     */
    private $controller;
    /**
     * 方法，默认执行run方法
     * @var string
     */
    private $action = 'run';
    /**
     * 控制器所在空间，有且只有第一个大写字母
     * @var
     */
    private $className;
    /**
     * @var Server
     */
    private $server;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var Response
     */
    private $response;


    public function __construct(Server $server, Request $request = null, Response $response = null)
    {
        $this->server = $server;
        $this->request = $request;
        $this->response = $response;
    }

    public function dispatch()
    {
        $uriArr = explode('/', trim($this->request->server['request_uri'], '/'));
        $this->controller = $uriArr[0] ?? '';
        $this->action = $uriArr[1] ?? '';
        if (empty($this->controller) || empty($this->action)) {
            throw new \Exception("Not Found");
        }
        //加上命名空间
        $this->className = 'module\\controllers\\' . ucfirst($this->controller);
        if (!class_exists($this->className)) {
            throw new \Exception("Not Found.");
        }
        if (!method_exists($this->className, $this->action)) {
            throw new \Exception("NOT FOUND");
        }
        $controller = new $this->className($this->server, $this->request->get, $this->request->post, $this->request->rawContent(), $this->request->header);
        $result = call_user_func_array([$controller, $this->action], []);
        return $result;
    }

    /**
     * @return string
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @return string
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

}