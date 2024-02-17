<?php

declare(strict_types=1);

namespace support\cache\extend;

use mon\cache\drivers\Redis;
use support\service\RedisService;

/**
 * 扩展的Gaia自带redis缓存库
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Rdb extends Redis
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 默认缓存有效时间
        'expire' => 0,
    ];

    /**
     * 构造方法
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取Redis驱动
     *
     * @throws \Exception
     * @return \Redis
     */
    public function handler(): \Redis
    {
        return RedisService::instance()->getRedis();
    }
}
