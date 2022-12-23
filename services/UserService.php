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
        return $decodeData['uid'];
    }

}