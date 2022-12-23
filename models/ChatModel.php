<?php

namespace module\models;

class ChatModel extends Model
{
    //1单聊，2群聊
    const CHAT_TYPE_SINGLE = 1;
    const CHAT_TYPE_GROUP = 2;
    //内容格式
    const MSG_TYPE_TEXT = 'text';
    const MSG_TYPE_IMAGE = 'image';

    public function tableName()
    {
        return 'yb_chat';
    }

}