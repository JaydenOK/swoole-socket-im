<?php

namespace module\models;

use module\lib\MysqliClient;
use module\lib\MysqliDb;

class Model
{
    /**
     * @var MysqliDb
     */
    protected static $db;

    public function __construct()
    {
        self::getDb();
    }

    public static function getDb()
    {
        if (is_null(self::$db)) {
            $mysqliClient = new MysqliClient();
            self::$db = $mysqliClient->getQuery();
        }
        return self::$db;
    }

    public function tableName()
    {
        return '';
    }

    public function insertData($data)
    {
        self::$db->insert($this->tableName(), $data);
        return self::$db->getInsertId();
    }

    public function saveData($data, $where)
    {
        $query = self::$db;
        foreach ($where as $key => $value) {
            $query->where($key, $value);
        }
        $res = $query->update($this->tableName(), $data);
        return $res ? $query->count : 0;
    }

    public function getOne($where)
    {
        foreach ($where as $key => $value) {
            self::$db->where($key, $value);
        }
        return self::$db->getOne($this->tableName());
    }

}