<?php

declare(strict_types=1);

namespace mon\cache\drivers;

use mon\cache\CacheInterface;
use mon\cache\exception\CacheException;

/**
 * Redis缓存驱动
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Redis implements CacheInterface
{
    /**
     * Redis实例
     *
     * @var \Redis
     */
    protected $handler;

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
        'auth'      => '',
        // 自定义键前缀
        'prefix'    => '',
        // redis数据库
        'database'  => 1,
        // 读取超时时间
        'timeout'   => 2,
        // 默认缓存有效时间
        'expire'    => 0,
        // 保持链接
        'persistent' => false,
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
        if ($this->config['persistent']) {
            // 持久连接
            $this->handler->pconnect($this->config['host'], $this->config['port'], $this->config['timeout']);
        } else {
            // 短连接
            $this->handler->connect($this->config['host'], $this->config['port'], $this->config['timeout']);
        }
        // 密码
        if ($this->config['auth']) {
            $this->handler->auth($this->config['auth']);
        }
        // 选择库
        if ($this->config['database']) {
            $this->handler->select($this->config['database']);
        }
        // 设置前缀
        if ($this->config['prefix']) {
            $this->handler->setOption(\Redis::OPT_PREFIX, $this->config['prefix']);
        }
        // 设置超时时间
        if ($this->config['timeout']) {
            $this->handler->setOption(\Redis::OPT_READ_TIMEOUT, $this->config['timeout']);
        }
    }

    /**
     * 获取Redis驱动
     *
     * @throws \Exception
     * @return \Redis
     */
    public function handler(): \Redis
    {
        return $this->handler;
    }

    /**
     * Ping
     *
     * @return mixed
     */
    public function ping()
    {
        return $this->handler()->ping();
    }

    /**
     * 获取缓存内容
     *
     * @param  string $key     键名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $value = $this->handler()->get($key);
        if (is_null($value) || $value === false) {
            return $default;
        }

        return unserialize($value);
    }

    /**
     * 批量获取缓存内容
     *
     * @param array $keys       缓存变量名一维数组
     * @param mixed $default    字符串或索引数组，不存在对应键时作为返回值
     * @return array
     */
    public function getMultiple(array $keys, $default = null): array
    {
        $values = [];
        $cacheData = $this->handler()->mGet($keys);
        $i = 0;
        foreach ($keys as $k) {
            if (!isset($cacheData[$i]) || $cacheData[$i] === false) {
                if (is_array($default)) {
                    $value = isset($default[$k]) ? $default[$k] : null;
                } else {
                    $value = $default;
                }
            } else {
                $value = unserialize($cacheData[$i]);
            }
            $values[$k] = $value;
            $i++;
        }

        return $values;
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

        $value = serialize($value);
        if ($ttl) {
            $result = $this->handler()->setex($key, $ttl, $value);
        } else {
            $result = $this->handler()->set($key, $value);
        }

        return $result;
    }

    /**
     * 批量设置缓存
     *
     * @param array $values 关联数组作为缓存的键值
     * @param integer $ttl  有效时间，0为永久
     * @return boolean
     */
    public function setMultiple(array $values, int $ttl = null): bool
    {
        if (is_null($ttl)) {
            $ttl = $this->config['expire'];
        }
        if ($ttl) {
            // 设置有效期
            $pipe = $this->handler()->multi(\Redis::PIPELINE);
            foreach ($values as $k => $v) {
                $pipe->setex($k, $ttl, serialize($v));
            }
            $exec = $pipe->exec();
            $result = true;
            foreach ($exec as $r) {
                if ($r !== true) {
                    $result = false;
                    break;
                }
            }
        } else {
            $data = [];
            foreach ($values as $k => $v) {
                $data[$k] = serialize($v);
            }
            $result = $this->handler()->mSet($data);
        }

        return $result;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存变量名
     * @return boolean
     */
    public function delete(string $key): bool
    {
        return $this->handler()->del($key) !== false;
    }

    /**
     * 批量删除缓存
     *
     * @param array $keys   缓存变量名一维数组
     * @throws CacheException
     * @return boolean
     */
    public function deleteMultiple(array $keys): bool
    {
        return call_user_func_array([$this->handler(), 'del'], $keys) !== false;
    }

    /**
     * 是否存在缓存
     *
     * @param  string  $key 名称
     * @return boolean
     */
    public function has(string $key): bool
    {
        return $this->handler()->exists($key) ? true : false;
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
