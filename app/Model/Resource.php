<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property string $key 
 * @property string $uri 
 * @property int $type 
 * @property int $add_time 
 * @property int $update_time 
 * @property int $is_deleted 
 */
class Resource extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'resources';
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
    protected $casts = ['id' => 'int', 'type' => 'integer', 'add_time' => 'integer', 'update_time' => 'integer', 'is_deleted' => 'integer'];
}