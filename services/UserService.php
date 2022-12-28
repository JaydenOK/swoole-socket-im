<?php

namespace module\services;

use module\lib\JWT;
use module\models\UserModel;

class UserService
{
    public function authUser($accessToken)
    {
        $jwt = new JWT('key', 'HS256', 86400);
        $decodeData = $jwt->decode($accessToken, false);
        if (!isset($decodeData['uid'], $decodeData['iat'], $decodeData['exp'])) {
            echo 'token parse failure' . PHP_EOL;
            return false;
        }
        if ($decodeData['iat'] + $decodeData['exp'] < time()) {
            echo 'token expire' . PHP_EOL;
            return false;
        }
        $userModel = new UserModel();
        $user = $userModel->findOne(['uid' => $decodeData['uid']]);
        if (empty($user)) {
            echo 'user not found:' . $decodeData['uid'] . PHP_EOL;
            return false;
        }
        $userModel->closeDb();
        return $decodeData['uid'];
    }

    public function getUser($uid)
    {
        $user = UserModel::model()->findOne(['uid' => $uid]);
        return $user;
    }

}