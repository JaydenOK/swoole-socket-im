<?php

namespace module\models;

use module\lib\MysqliClient;
use module\lib\MysqliDb;

class Model
{
    /**
     * 共用DB
     * @var MysqliDb
     */
    protected static $db;
    /**
     * @var []static
     */
    protected static $models;

    public function __construct($className = __CLASS__)
    {
        self::getDb();
    }

    /**
     * @param string $className
     * @return static
     */
    public static function model($className = __CLASS__)
    {
        if (!isset(self::$models[$className])) {
            self::$models[$className] = new static($className);
        }
        return self::$models[$className];
    }

    public static function getDb()
    {
        if (is_null(self::$db)) {
            $mysqliClient = new MysqliClient();
            self::$db = $mysqliClient->getQuery();
        }
        return self::$db;
    }

    public function closeDb()
    {
        if (self::$db !== null) {
            self::$db->disconnect();
            self::$db = null;
        }
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