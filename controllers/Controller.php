<?php

namespace module\controllers;

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

}