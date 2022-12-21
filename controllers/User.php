<?php

namespace module\controllers;

class User extends Controller
{

    public function init()
    {

    }

    public function register()
    {
        return ['get' => $this->get, 'post' => $this->post, 'rawContent' => $this->rawContent, 'header' => $this->header];
    }
}