<?php

namespace module\controllers;

use module\services\UserService;
use Swoole\WebSocket\Server;

class Controller
{
    /**
     * @var Server
     */
    protected $server;
    protected $header;
    protected $get;
    protected $post;
    protected $rawContent;
    /**
     * @var bool
     */
    protected $uid;

    public function __construct(Server $server = null, $get = null, $post = null, $rawContent = null, $header = null)
    {
        $this->server = $server;
        $this->get = $get;
        $this->post = $post;
        $this->rawContent = $rawContent;
        $this->header = $header;
        $this->init();
    }

    protected function init()
    {
    }

    protected function checkUid()
    {
        $accessToken = $this->get['access_token'] ?? '';
        if (empty($accessToken)) {
            throw new \Exception('empty access_token');
        }
        $this->uid = (new UserService())->authUser($accessToken);
        return $this->uid;
    }

}