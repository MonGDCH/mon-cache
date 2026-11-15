<?php

declare(strict_types=1);

namespace mon\cache\drivers;

use mon\cache\Traits;
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
    use Traits;

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
        // 保持链接
        'persistent' => false,
        // 默认缓存有效时间
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
        if ($this->config['persistent']) {
            // 持久连接
            $this->handler->pconnect($this->config['host'], $this->config['port'], $this->config['timeout'], 'persistent_id_' . $this->config['database']);
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
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->handler()->get($key);
        if (is_null($value) || $value === false) {
            return $default;
        }
        $result = $this->unserialize($value);
        if ($this->isUnserializeFailed($result, $value)) {
            // 数据损坏，删除后按未命中处理
            $this->handler()->del($key);
            return $default;
        }

        return $result;
    }

    /**
     * 批量获取缓存内容
     *
     * @param array $keys       缓存变量名一维数组
     * @param mixed $default    字符串或索引数组，不存在对应键时作为返回值
     * @return array
     */
    public function getMultiple($keys, $default = null): array
    {
        // PSR-16 允许传入 iterable，统一转为数组，避免 Generator 被重复迭代
        if (!is_array($keys)) {
            $keys = iterator_to_array($keys, false);
        }
        // 空数组直接返回，避免mGet空参数报错
        if (empty($keys)) {
            return [];
        }
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
                $value = $this->unserialize($cacheData[$i]);
                if ($this->isUnserializeFailed($value, $cacheData[$i])) {
                    // 数据损坏，按未命中处理
                    $value = is_array($default) ? (isset($default[$k]) ? $default[$k] : null) : $default;
                }
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
     * @param integer $ttl  有效时间 0为永久
     * @return boolean
     */
    public function set($key, $value, $ttl = null): bool
    {
        $ttl = $this->ttlToSeconds($ttl);
        if (is_null($ttl)) {
            $ttl = (int) $this->config['expire'];
        }

        $value = $this->serialize($value);
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
    public function setMultiple($values, $ttl = null): bool
    {
        $ttl = $this->ttlToSeconds($ttl);
        if (is_null($ttl)) {
            $ttl = (int) $this->config['expire'];
        }
        if ($ttl) {
            // 设置有效期
            $pipe = $this->handler()->multi(\Redis::PIPELINE);
            foreach ($values as $k => $v) {
                $pipe->setex($k, $ttl, $this->serialize($v));
            }
            $exec = $pipe->exec();
            // pipeline 执行失败时返回 false，不能直接遍历
            if (!is_array($exec)) {
                return false;
            }
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
                $data[$k] = $this->serialize($v);
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
    public function delete($key): bool
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
    public function deleteMultiple($keys): bool
    {
        // PSR-16 允许传入 iterable，统一转为数组
        if (!is_array($keys)) {
            $keys = iterator_to_array($keys, false);
        }
        // 空数组直接返回，避免del空参数报错
        if (empty($keys)) {
            return true;
        }

        return $this->handler()->del($keys) !== false;
    }

    /**
     * 是否存在缓存
     *
     * @param  string  $key 名称
     * @return boolean
     */
    public function has($key): bool
    {
        return $this->handler()->exists($key) ? true : false;
    }

    /**
     * 获取缓存剩余有效期
     *
     * @param  string $key 缓存变量名
     * @return integer|null 剩余秒数，0为永久，key不存在时返回null
     */
    public function getTtl(string $key): ?int
    {
        $ttl = $this->handler()->ttl($key);
        if (-2 === $ttl) {
            // key不存在
            return null;
        }
        if (-1 === $ttl) {
            // 永久有效
            return 0;
        }

        return $ttl >= 0 ? $ttl : null;
    }

    /**
     * 清除缓存
     *
     * 连接上生效了键前缀（OPT_PREFIX）时只清理该前缀下的缓存键；
     * 未配置前缀时保持 flushDB 语义（清空当前库），与大范围清理前请先确认前缀配置。
     *
     * @return boolean
     */
    public function clear(): bool
    {
        $handler = $this->handler();
        // 以连接上实际生效的前缀为准（复用外部连接的驱动，如 Gaia 的 Rdb，配置中可能没有该前缀）
        $prefix = (string) $handler->getOption(\Redis::OPT_PREFIX);
        // 未配置前缀，只能清空当前数据库
        if ($prefix === '') {
            return $handler->flushDB();
        }

        // 配置了前缀，按前缀扫描删除，避免 flushDB 误删同库其他业务数据
        // SCAN 的 pattern 不受 OPT_PREFIX 影响，且 DEL 会自动附加前缀，先临时关闭前缀以保持一致
        $handler->setOption(\Redis::OPT_PREFIX, '');
        try {
            $iterator = null;
            while (true) {
                $keys = $handler->scan($iterator, $prefix . '*', 1000);
                if (false === $keys) {
                    break;
                }
                if (!empty($keys)) {
                    $handler->del($keys);
                }
                // 迭代结束
                if (0 === (int) $iterator || is_null($iterator)) {
                    break;
                }
            }
        } finally {
            $handler->setOption(\Redis::OPT_PREFIX, $prefix);
        }

        return true;
    }
}
