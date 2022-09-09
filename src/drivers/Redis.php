<?php

declare(strict_types=1);

namespace mon\cache\drivers;

use mon\cache\Driver;
use app\cache\exception\CacheException;

/**
 * Redis缓存驱动
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Redis extends Driver
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 链接host
        'host'      => '127.0.0.1',
        // 链接端口
        'port'      => 6379,
        // 链接密码
        'password'  => '',
        // 自定义键前缀
        'prefix'    => '',
        // 读取超时时间
        'timeout'   => 0,
        // 缓存有效时间
        'expire'    => 0,
    ];

    /**
     * 构造方法
     *
     * @param array $config 配置信息
     */
    public function __construct(array $config = [])
    {
        if (!extension_loaded('redis')) {
            throw new CacheException('Cache Redis Driver required ext-redis.');
        }
        $this->config = array_merge($this->config, $config);

        $this->handler = new \Redis();
        $this->handler->connect($this->config['host'], $this->config['port']);
        if ($this->config['password']) {
            $this->handler->auth($this->config['password']);
        }
        if ($this->config['prefix']) {
            $this->handler->setOption(\Redis::OPT_PREFIX, $this->config['prefix']);
        }
        if ($this->config['timeout']) {
            $this->handler->setOption(\Redis::OPT_READ_TIMEOUT, $this->config['timeout']);
        }
    }

    /**
     * 获取缓存内容
     *
     * @param  string $name    名称
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        $value = $this->handler()->get($this->getCacheKey($name));
        if (is_null($value) || false === $value) {
            return $default;
        }

        return unserialize($value);
    }

    /**
     * 写入缓存
     *
     * @param string  $key    缓存变量名
     * @param mixed   $value   存储数据
     * @param integer $expire  有效时间 0为永久
     * @return boolean
     */
    public function set(string $key, $value, int $ttl = null): bool
    {
        if (is_null($ttl)) {
            $ttl = $this->config['expire'];
        }

        $key = $this->getCacheKey($key);
        $value = serialize($value);

        if ($ttl) {
            $result = $this->handler()->setex($key, $ttl, $value);
        } else {
            $result = $this->handler()->set($key, $value);
        }

        return $result;
    }

    /**
     * 是否存在缓存
     *
     * @param  string  $key 名称
     * @return boolean
     */
    public function has(string $key): bool
    {
        return $this->handler()->exists($this->getCacheKey($key)) ? true : false;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存变量名
     * @return boolean
     */
    public function delete(string $key): bool
    {
        return $this->handler()->del($this->getCacheKey($key)) !== false ? true : false;
    }

    /**
     * 清除缓存
     *
     * @return boolean
     */
    public function clear(): bool
    {
        return $this->handler->flushDB();
    }
}
