<?php


namespace App\Util;

use Hyperf\Task\Annotation\Task;
use Hyperf\Utils\Coroutine;
class Test
{
    /**
     * @Task
     */
    public function handle($cid)
    {
        return [
            'worker.cid' => $cid,
            // task_enable_coroutine=false 时返回 -1，反之 返回对应的协程 ID
            'task.cid' => Coroutine::id(),
            'asd' => 1,
        ];
    }
}