<?php

namespace module\models;

class ShopServiceModel extends Model
{

    //是否在线
    const IS_ONLINE = 1;
    const IS_NOT_ONLINE = 0;

    public function tableName()
    {
        return 'yb_shop_service';
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