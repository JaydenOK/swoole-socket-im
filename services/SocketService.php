<?php

namespace module\services;

use module\controllers\User;
use module\lib\RedisClient;
use module\models\ChatMessageModel;
use module\models\ChatModel;
use Swoole\WebSocket\Server;

class SocketService
{
    //'single_chat', 'join_group', 'group_chat'
    const ACTION_SINGLE_CHAT = 'single_chat';
    const ACTION_JOIN_GROUP = 'join_group';
    const ACTION_EXIT_GROUP = 'exit_group';
    const ACTION_GROUP_CHAT = 'group_chat';
    const ACTION_SERVICE_CHAT = 'service_chat';

    const CACHE_PREFIX = 'socket.im.';

    protected $redisClient;

    public function __construct()
    {
        $this->redisClient = (new RedisClient())->getRedisProxy();
        $this->redisClient->open();
    }

    public function __destruct()
    {
        $this->redisClient->close();
    }

    //处理用户与连接文件描述符关系
    public function saveUserAndFd($uid, $fd)
    {
        $this->redisSetUid($uid, $fd, 86400);
        $this->redisSetFd($fd, $uid, 86400);
        return true;
    }


    //处理用户与连接文件描述符关系，todo，移除群聊uid
    public function removeFd($fd)
    {
        $uid = $this->redisGetFd($fd);
        if (!empty($uid)) {
            $this->redisDeleteUid($uid);
        }
        return $this->redisDeleteFd($fd);
    }

    public function redisGetUid($uid)
    {
        return $this->redisClient->get(self::CACHE_PREFIX . 'uid.' . $uid);
    }

    public function redisGetFd($fd)
    {
        return $this->redisClient->get(self::CACHE_PREFIX . 'fd.' . $fd);
    }

    public function redisSetUid($uid, $value, $timeout = 0)
    {
        $this->redisClient->set(self::CACHE_PREFIX . 'uid.' . $uid, $value, $timeout);
    }

    public function redisSetFd($fd, $value, $timeout = 0)
    {
        $this->redisClient->set(self::CACHE_PREFIX . 'fd.' . $fd, $value, $timeout);
    }

    public function redisDeleteUid($uid)
    {
        return $this->redisClient->delete(self::CACHE_PREFIX . 'uid.' . $uid);
    }

    public function redisDeleteFd($fd)
    {
        return $this->redisClient->delete(self::CACHE_PREFIX . 'fd.' . $fd);
    }

    /**
     * @param Server $server
     * @param $fd string  发送人描述符
     * @param $dataArr array 定义交互数据格式
     * @return array
     * @throws \Exception
     */
    public function message(Server $server, $fd, $dataArr)
    {
        //定义拉数据格式：{"chat_type":1,"msg_type":"text","msg_id":"","chat_id":"","to_uid":2,"msg":"hello"}
        $uid = $this->redisGetFd($fd);
        $action = $dataArr['action'] ?? '';
        $toUid = $dataArr['to_uid'] ?? 0;
        $chatId = $dataArr['chat_id'] ?? '';
        $chatType = $dataArr['chat_type'] ?? 0;
        if (!in_array($action, [self::ACTION_SINGLE_CHAT, self::ACTION_JOIN_GROUP, self::ACTION_EXIT_GROUP, self::ACTION_GROUP_CHAT])) {
            return ['message' => 'unknown action.'];
        }
        $data = [];
        switch ($action) {
            case self::ACTION_SINGLE_CHAT:
                if ($uid == $dataArr['to_uid']) {
                    return ['message' => 'Cannot send to yourself'];
                }
                $userService = new UserService();
                $user = $userService->getUser($toUid);
                if (empty($user)) {
                    return ['message' => 'user not exist'];
                }
                $data = $this->sendSingleChat($server, $chatType, $uid, $toUid, $chatId, $dataArr);
                break;
            case self::ACTION_JOIN_GROUP:
                $data = $this->joinGroup($chatId, $uid);
                break;
            case self::ACTION_EXIT_GROUP:
                $data = $this->exitGroup($chatId, $uid);
                break;
            case self::ACTION_GROUP_CHAT:
                $data = $this->sendGroupChat($server, $chatType, $uid, $toUid, $chatId, $dataArr);
                break;
            case self::ACTION_SERVICE_CHAT:
                //service
                $data = $this->sendServiceChat($server, $chatType, $uid, $toUid, $chatId, $dataArr);
                break;
        }
        return $data;
    }

    //单聊，支持发送离线消息
    private function sendSingleChat(Server $server, $chatType, $uid, $toUid, $chatId = '', $dataArr = [])
    {
        $data = [];
        switch ($dataArr['msg_type']) {
            case ChatModel::MSG_TYPE_TEXT:
                //发送在线消息
                $toFd = $this->redisGetUid($toUid);
                //检查连接是否为有效的 WebSocket 客户端连接
                $isOnline = $server->isEstablished($toFd);
                //处理聊天关联数据chat
                $chatId = $this->ensureChatId($chatType, $uid, $toUid, $chatId);
                //增加聊天记录chat_message
                $this->addChatMessage($chatType, $uid, $toUid, $chatId, $dataArr['msg_type'], $dataArr['msg']);
                //更新关联数据最新状态
                $this->updateChatStatus($uid, $toUid, $chatId, $dataArr['msg_type'], $dataArr['msg']);
                //在线：推送socket更新用户消息
                if ($toFd && $isOnline) {
                    $pushData = ['chat_type' => $chatType, 'chat_id' => $chatId, 'uid' => $uid, 'msg_type' => $dataArr['msg_type'], 'msg' => $dataArr['msg']];
                    $server->push($toFd, json_encode($pushData, JSON_UNESCAPED_UNICODE));
                }
                $data = ['status' => 1, 'message' => 'ok'];
                break;
        }
        return $data;
    }

    private function ensureChatId($chatType, $uid, $toUid, $chatId, $shopId = 0)
    {
        $data = [
            'uid' => $uid,
            'to_uid' => $toUid,
            'chat_type' => $chatType,
        ];
        if (!empty($chatId)) {
            $data['chat_id'] = $chatId;
        }
        $chat = ChatModel::model()->getOne($data);
        if (empty($chat)) {
            $chatId = (new ChatService())->createChatId($chatType, $uid, $toUid, $shopId);
            $data['chat_id'] = $chatId;
            $data['create_time'] = date('Y-m-d H:i:s');
            ChatModel::model()->insertData($data);
            $partnerData = [
                'uid' => $toUid,
                'to_uid' => $uid,
                'chat_type' => $chatType,
                'chat_id' => $chatId,
                'create_time' => date('Y-m-d H:i:s'),
            ];
            ChatModel::model()->insertData($partnerData);
        } else {
            $chatId = $chat['chat_id'];
        }
        return $chatId;
    }

    private function addChatMessage($chatType, $uid, $toUid, $chatId, $msgType, $msg)
    {
        $data = [
            'uid' => $uid,
            'to_uid' => $toUid,
            'chat_id' => $chatId,
            'chat_type' => $chatType,
            'msg_type' => $msgType,
            'msg' => $msg,
            'create_time' => date('Y-m-d H:i:s'),
        ];
        $id = ChatMessageModel::model()->insertData($data);
        return $id;
    }

    private function updateChatStatus($uid, $toUid, $chatId, $msgType, $msg)
    {
        $data = ['last_msg' => $msg, 'msg_type' => $msgType, 'last_time' => date('Y-m-d H:i:s'), 'is_del' => ChatModel::IS_NOT_DEL];
        ChatModel::model()->saveData($data, ['chat_id' => $chatId, 'uid' => $uid, 'to_uid' => $toUid]);
        //接收人，未读消息+1，前端查看时再重置0
        $toChat = ChatModel::model()->getOne(['chat_id' => $chatId, 'uid' => $toUid, 'to_uid' => $uid]);
        $data['unread'] = $toChat['unread'] + 1;
        ChatModel::model()->saveData($data, ['chat_id' => $chatId, 'uid' => $toUid, 'to_uid' => $uid]);
    }

    private function sendServiceChat(Server $server, int $chatType, $uid, int $toUid, string $chatId, array $dataArr)
    {
        return [];
    }

    private function sendGroupChat(Server $server, int $chatType, $uid, int $toUid, string $chatId, array $dataArr)
    {
        $this->addChatMessage($chatType, $uid, $toUid, $chatId, $dataArr['msg_type'], $dataArr['msg']);
        ChatModel::model()->saveData(
            ['last_msg' => $dataArr['msg'], 'last_time' => date('Y-m-d H:i:s')],
            ['chat_id' => $chatId, 'chat_type' => $dataArr['msg_type']]
        );
        $groupUidArr = $this->redisClient->getRedis()->sMembers(self::CACHE_PREFIX . $chatId);
        foreach ($groupUidArr as $itemUid) {
            //发送在线消息(排除自己)
            if ($itemUid == $uid) {
                continue;
            }
            $itemFd = $this->redisGetUid($itemUid);
            //检查连接是否为有效的 WebSocket 客户端连接
            $isOnline = $server->isEstablished($itemFd);
            if ($itemFd && $isOnline) {
                $pushData = ['chat_type' => $chatType, 'chat_id' => $chatId, 'uid' => $itemUid, 'msg_type' => $dataArr['msg_type'], 'msg' => $dataArr['msg']];
                $server->push($itemFd, json_encode($pushData, JSON_UNESCAPED_UNICODE));
            } else {
                //$this->redisDeleteUid($uid);
            }
        }
        $data = ['status' => 1, 'message' => 'ok'];
        return $data;
    }

    //加入群聊
    private function joinGroup($chatId, $uid)
    {
        $this->redisClient->sAdd(self::CACHE_PREFIX . $chatId, $uid);
        $data = ['status' => 1, 'message' => 'join group', 'chat_id' => $chatId];
        echo json_encode($data) . PHP_EOL;
        return $data;
    }

    //退出群聊
    private function exitGroup($chatId, $uid)
    {
        $this->redisClient->getRedis()->sRem(self::CACHE_PREFIX . $chatId, $uid);
        $data = ['status' => 1, 'message' => 'exit group', 'chat_id' => $chatId];
        echo json_encode($data) . PHP_EOL;
        return $data;
    }
}