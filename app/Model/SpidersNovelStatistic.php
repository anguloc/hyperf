<?php

declare (strict_types=1);
namespace App\Model;

/**
 * @property int $id 
 * @property int $nid 
 * @property int $month_ticket 
 * @property int $rec_ticket 
 * @property int $reward 
 * @property int $line_time 
 * @property int $add_time 
 * @property int $is_deleted 
 */
class SpidersNovelStatistic extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'spiders_novel_statistics';
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
    protected $casts = ['id' => 'int', 'nid' => 'integer', 'month_ticket' => 'integer', 'rec_ticket' => 'integer', 'reward' => 'integer', 'line_time' => 'integer', 'add_time' => 'integer', 'is_deleted' => 'integer'];


}