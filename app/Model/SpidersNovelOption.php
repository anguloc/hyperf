<?php

declare (strict_types=1);
namespace App\Model;

/**
 * @property int $id 
 * @property int $nid 
 * @property string $option 
 * @property string $value 
 * @property int $add_time 
 * @property int $is_deleted 
 */
class SpidersNovelOption extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'spiders_novel_options';
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
    protected $casts = ['id' => 'int', 'nid' => 'integer', 'add_time' => 'integer', 'is_deleted' => 'integer'];

    const _OPTION = '';
}