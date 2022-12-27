<?php

namespace module\services;

use module\models\ChatModel;

class ChatService
{

    public function createRoom($uid)
    {
        $chatModel = new ChatModel();
        $data = [
            'uid' => $uid,
            'to_uid' => 0,
            'chat_id' => $this->createChatId(ChatModel::CHAT_TYPE_GROUP, $uid),
            'chat_type' => ChatModel::CHAT_TYPE_GROUP,
            'msg_type' => ChatModel::MSG_TYPE_TEXT,
            'create_time' => date('Y-m-d H:i:s'),
        ];
        $chatModel->insertData($data);
        return $data['chat_id'];
    }

    public function createChatId($chatType, $uid, $toUid = 0, $shopId = 0)
    {
        if ($chatType == ChatModel::CHAT_TYPE_GROUP) {
            $queryArr = [
                $chatType,
                $uid,
                date('ymdHis'),
            ];
        } else {
            $queryArr = [
                $uid,
                $toUid,
            ];
            sort($queryArr);
            $queryArr[] = $chatType;
            $queryArr[] = $shopId;
        }
        return implode('_', $queryArr);
    }

}