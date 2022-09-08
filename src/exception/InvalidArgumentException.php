<?php

declare(strict_types=1);

namespace app\cache\exception;

use Exception;

/**
 * 参数无效异常
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class InvalidArgumentException extends Exception implements \Psr\SimpleCache\InvalidArgumentException
{
}
