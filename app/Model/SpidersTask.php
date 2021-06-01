<?php

declare (strict_types=1);
namespace App\Model;

/**
 * @property int $id 
 * @property string|array $content
 * @property int $add_time 
 * @property int $update_time 
 * @property int $deleted_time 
 */
class SpidersTask extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'spiders_task';
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
    protected $casts = ['id' => 'int', 'add_time' => 'integer', 'update_time' => 'integer', 'deleted_time' => 'integer'];
}