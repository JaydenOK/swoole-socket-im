<?php

namespace module\controllers;

use module\services\ChatService;

class Room extends Controller
{

    //创建房间
    public function create()
    {
        $this->checkUid();
        $chatId = (new ChatService())->createRoom($this->uid);
        return ['chat_id' => $chatId];
    }

}