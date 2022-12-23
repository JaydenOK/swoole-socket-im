<?php

namespace module\controllers;

class Message extends Controller
{
    //发送在线公告消息
    public function sendPublicMessage()
    {
        $data = $this->post['data'] ?? '';
        foreach ($this->server->connections as $fd) {
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, json_encode($data, JSON_UNESCAPED_UNICODE));
            }
        }
        return ['status' => 1, 'message' => 'ok'];
    }

}