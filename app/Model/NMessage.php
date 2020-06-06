<?php

declare (strict_types=1);
namespace App\Model;

/**
 * @property int $id 
 * @property int $from_uid 
 * @property int $to_uid 
 * @property int $opcode 
 * @property string $content 
 * @property int $add_time 
 * @property int $is_deleted 
 */
class NMessage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'n_message';
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
    protected $casts = ['id' => 'int', 'from_uid' => 'integer', 'to_uid' => 'integer', 'opcode' => 'integer', 'add_time' => 'integer', 'is_deleted' => 'integer'];
}