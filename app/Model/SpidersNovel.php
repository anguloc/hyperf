<?php

declare (strict_types=1);
namespace App\Model;

/**
 * @property int $id 
 * @property string $title 
 * @property string $url 
 * @property string $key 
 * @property string $content 
 * @property int $add_time 
 * @property int $is_deleted 
 */
class SpidersNovel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'spiders_novel';
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
    protected $casts = ['id' => 'int', 'add_time' => 'integer', 'is_deleted' => 'integer'];
}