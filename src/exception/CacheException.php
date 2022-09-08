<?php

declare(strict_types=1);

namespace app\cache\exception;

use Exception;

/**
 * 缓存异常
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class CacheException extends Exception implements \Psr\SimpleCache\CacheException
{
}
