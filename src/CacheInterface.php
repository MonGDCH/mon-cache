<?php

declare(strict_types=1);

namespace mon\cache;

/**
 * 缓存驱动接口，扩展ping接口
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
interface CacheInterface extends \Psr\SimpleCache\CacheInterface
{
    /**
     * Ping
     *
     * @throws \Exception
     * @return mixed
     */
    public function ping();
}
