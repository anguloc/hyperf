<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace App\Model;

use App\Util\Logger;
use Hyperf\DbConnection\Model\Model as BaseModel;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;
use Hyperf\Logger\LoggerFactory;

abstract class Model extends BaseModel
{
//    use Cacheable;

    protected $dateFormat = "U";

    const CREATED_AT = 'add_time';
    const UPDATED_AT = 'update_time';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(array $attributes = [])
    {
        $this->logger = Logger::get('model exception:', 'model');
        parent::__construct($attributes);
    }

    public function save(array $options = []): bool
    {
        try {
            return parent::save($options);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'class' => static::class,
                'method' => __FUNCTION__,
            ]);
            return false;
        }
    }
}
