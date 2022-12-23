<?php

namespace module\models;

class ChatModel extends Model
{
    //1单聊，2群聊，3客服，4广播
    const CHAT_TYPE_SINGLE = 1;
    const CHAT_TYPE_GROUP = 2;
    const CHAT_TYPE_SERVICE = 3;
    const CHAT_TYPE_BROADCAST = 4;

    //内容格式
    const MSG_TYPE_TEXT = 'text';
    const MSG_TYPE_IMAGE = 'image';

    //是否删除
    const IS_NOT_DEL = 0;
    const IS_DEL = 1;

    public function tableName()
    {
        return 'yb_chat';
    }

    /**
     * @param string $className
     * @return Model
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

}