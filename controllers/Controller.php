<?php

namespace module\controllers;

class Controller
{
    protected $header;
    protected $get;
    protected $post;
    protected $rawContent;

    public function __construct($get = null, $post = null, $rawContent = null, $header = null)
    {
        $this->get = $get;
        $this->post = $post;
        $this->rawContent = $rawContent;
        $this->header = $header;
        $this->init();
    }

    protected function init()
    {
    }

}