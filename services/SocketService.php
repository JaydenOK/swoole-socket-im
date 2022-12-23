<?php

namespace module\services;

use module\lib\JWT;
use module\lib\RedisClient;
use module\models\ChatModel;
use module\models\UserModel;
use Swoole\WebSocket\Server;

class SocketService
{

    const CACHE_PREFIX = 'socket.im.';

    protected $redisClient;

    public function __construct()
    {
        $this->redisClient = (new RedisClient())->getRedisProxy();
        $this->redisClient->open();
    }

    public function authUser($accessToken)
    {
        $jwt = new JWT('key', 'HS256', 86400);
        $decodeData = $jwt->decode($accessToken, false);
        if (!isset($decodeData['uid'], $decodeData['iat'], $decodeData['exp'])) {
            return false;
        }
        if ($decodeData['iat'] + $decodeData['exp'] < time()) {
            echo 'token expire';
            return false;
        }
        $user = (new UserModel())->getOne(['uid' => $decodeData['uid']]);
        if (empty($user)) {
            echo 'user not found:' . $decodeData['uid'];
            return false;
        }
        return true;
    }

    //处理用户与连接文件描述符关系
    public function saveUserAndFd($uid, $fd)
    {
        $this->redisSetUid($uid, $fd, 86400);
        $this->redisSetFd($fd, $uid, 86400);
        return true;
    }


    //处理用户与连接文件描述符关系
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
     * @param $frameData string 定义交互数据格式
     * @return array
     * @throws \Exception
     */
    public function message(Server $server, $fd, $frameData)
    {
        //定义拉数据格式：{"chat_type":1,"msg_type":"text","msg_id":"","to_uid":2,"content":"hello，胡"}
        $dataArr = @json_decode($frameData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('data format error');
        }
        $data = [];
        $uid = $this->redisGetFd($fd);
        switch ($dataArr['chat_type']) {
            case ChatModel::CHAT_TYPE_SINGLE:
                $data = $this->sendSingleChat($server, $uid, $dataArr['to_uid'], $dataArr);
                break;
            default:
                break;
        }
        return $data;
    }

    //单聊
    private function sendSingleChat(Server $server, $uid, $toUid, $dataArr)
    {
        $data = [];
        switch ($dataArr['msg_type']) {
            case ChatModel::MSG_TYPE_TEXT:
                $toFd = $this->redisGetUid($toUid);
                if (empty($toFd)) {
                    $data = ['msg' => 'user is not online'];
                    return $data;
                }
                //检查连接是否为有效的 WebSocket 客户端连接
                $isOnline = $server->isEstablished($toFd);
                if (!$isOnline) {
                    $data = ['msg' => 'user is not connected:' . $toFd];
                    return $data;
                }
                $pushData = ['chat_id' => $this->getChatId($uid, $toUid, $dataArr['chat_type']), 'chat_type' => $dataArr['chat_type'], 'msg_type' => $dataArr['msg_type'], 'content' => $dataArr['content']];
                $server->push($toFd, json_encode($pushData, JSON_UNESCAPED_UNICODE));
                $data = ['status' => 1];
                break;
        }
        return $data;
    }

    private function getChatId($uid, $toUid, $chat_type)
    {
        return $uid . '_' . $toUid . '_' . $chat_type . '_' . time();
    }


}