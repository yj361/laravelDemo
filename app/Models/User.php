<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // 用户表
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'users';

    // 不可以批量赋值的字段，为空则表示都可以
    protected $guarded = [];

    /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $hidden = [];
    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = true;

    // 关联商品评价
    public function good_comment()
    {
        return $this->hasMany('\App\Models\GoodComment','user_id','id');
    }

    // 属性值
    public function return_good()
    {
        return $this->hasMany('\App\Models\ReturnGood','user_id','id');
    }

    // 属性值
    public function card()
    {
        return $this->hasMany('\App\Models\Card','user_id','id');
    }
}
