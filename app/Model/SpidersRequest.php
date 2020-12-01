<?php

declare (strict_types=1);
namespace App\Model;

/**
 * @property int $id 
 * @property string $url 
 * @property int $request_num 
 * @property string $content 
 * @property int $add_time 
 * @property int $update_time 
 * @property int $deleted_time 
 */
class SpidersRequest extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'spiders_request';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'request_num' => 'integer', 'add_time' => 'integer', 'update_time' => 'integer', 'deleted_time' => 'integer'];

    const INIT_TYPE = 0; // 初始状态
    const LOCK_TYPE = 1; // 锁定状态
    const SUC_TYPE = 2; //
    const FAIL_TYPE = 3;
}