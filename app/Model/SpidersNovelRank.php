<?php

declare (strict_types=1);

namespace App\Model;


/**
 * @property int $id
 * @property int $nid
 * @property string $title
 * @property int $add_time
 * @property int $is_deleted
 */
class SpidersNovelRank extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'spiders_novel_rank';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nid',
        'title',
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'int', 'nid' => 'integer', 'add_time' => 'integer', 'is_deleted' => 'integer'];

    const UPDATED_AT = null;

    public function a()
    {

    }
}