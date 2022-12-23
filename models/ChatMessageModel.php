<?php

namespace module\models;

class ChatMessageModel extends Model
{

    public function tableName()
    {
        return 'yb_chat_message';
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