<?php

namespace module\models;

class ShopModel extends Model
{

    public function tableName()
    {
        return 'yb_shop';
    }

    /**
     * @param string $className
     * @return Model
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

}