<?php

namespace module\task;

interface Task
{
    public function tableName();

    public function getTaskList($params);

    public function taskRun($id, $task);

    public function taskDone($id, $data);
}