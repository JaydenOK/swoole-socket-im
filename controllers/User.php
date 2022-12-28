<?php

namespace module\controllers;

use module\lib\JWT;
use module\models\UserModel;

class User extends Controller
{

    public function init()
    {

    }

    //用户注册
    public function register()
    {
        //$this->post['mobile'];
        $username = $this->post['username'] ?? '';
        $password = $this->post['password'] ?? '';
        if (empty($username) || empty($password)) {
            throw new \Exception('empty username, password');
        }
        $salt = $this->generateSalt($username);
        $userModel = new UserModel();
        $data = ['username' => $username, 'password' => md5($password . $salt), 'salt' => $salt, 'create_time' => date('Y-m-d H:i:s')];
        $user = $userModel->findOne(['username' => $username]);
        if (!empty($user)) {
            throw new \Exception('register fail: user exist.');
        }
        $uid = $userModel->insertData($data);
        if ($uid <= 0) {
            throw new \Exception('register fail: try again later.');
        }
        $payload = ['iss' => 'im_server', 'iat' => time(), 'exp' => 86400, 'uid' => $uid, 'scopes' => []];
        $jwt = new JWT($password, 'HS256', 86400 * 7);
        $accessToken = $jwt->encode($payload);
        $userModel->saveData(['access_token' => $accessToken], ['uid' => $uid]);
        return ['uid' => $uid, 'access_token' => $accessToken];
    }

    //用户登录
    public function login()
    {
        //$this->post['mobile'];
        $username = $this->post['username'] ?? '';
        $password = $this->post['password'] ?? '';
        if (empty($username) || empty($password)) {
            throw new \Exception('empty username, password');
        }
        $userModel = new UserModel();
        $user = $userModel->findOne(['username' => $username]);
        if (empty($user)) {
            throw new \Exception('user not exist.');
        }
        if (md5($password . $user['salt']) !== $user['password']) {
            throw new \Exception('password error.');
        }
        $payload = ['iss' => 'im_server', 'iat' => time(), 'exp' => 86400, 'uid' => $user['uid'], 'scopes' => []];
        $jwt = new JWT($password, 'HS256', 86400 * 7);
        $accessToken = $jwt->encode($payload);
        $userModel->saveData(['access_token' => $accessToken], ['uid' => $user['uid']]);
        return ['uid' => $user['uid'], 'access_token' => $accessToken];
    }

    private function generateSalt(string $username)
    {
        return substr(sha1($username . time()), 0, 10);
    }

}