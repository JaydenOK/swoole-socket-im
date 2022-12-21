<?php

namespace module\controllers;

use module\models\UserModel;

class User extends Controller
{

    public function init()
    {

    }

    public function register()
    {
        //$this->post['mobile'];
        $username = $this->post['username'] ?? '';
        $password = $this->post['password'] ?? '';
        if (empty($username) || empty($password)) {
            throw new \Exception('empty username, password');
        }
        $userModel = new UserModel();
        $data = ['username' => $username, 'password' => $password, 'create_time' => date('Y-m-d H:i:s')];
        $user = $userModel->getOne(['username' => $username]);
        if (!empty($user)) {
            throw new \Exception('register fail : user exist');
        }
        $uid = $userModel->insertData($data);
        return ['uid' => $uid];
    }
}