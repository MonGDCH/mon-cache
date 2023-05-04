<?php

declare(strict_types=1);

namespace mon\cache;

use mon\cache\drivers\File;
use mon\cache\drivers\Redis;
use mon\cache\CacheInterface;
use mon\cache\exception\InvalidArgumentException;

/**
 * 缓存类
 *
 * @method mixed get(string $key, mixed $default = null)  获取缓存
 * @method array getMultiple(array $keys, $default = nulll)  批量获取缓存
 * @method boolean set(string $key, mixed $value, integer $ttl = null) 设置缓存
 * @method boolean setMultiple(array $values, int $ttl = null)  批量设置缓存
 * @method boolean has(string $key) 是否存在缓存
 * @method boolean delete(string $key) 删除缓存
 * @method boolean deleteMultiple(array $keys) 批量删除缓存
 * @method boolean clear() 清空缓存
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Cache
{
    /**
     * 单例实体
     *
     * @var Cache
     */
    protected static $instance = null;

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [];

    /**
     * 缓存驱动
     *
     * @var CacheInterface[]
     */
    protected $driver = [];

    /**
     * 驱动类型
     *
     * @var array
     */
    protected $driverType = [
        // 文件驱动
        'file'  => File::class,
        // Redis驱动
        'redis' => Redis::class
    ];

    /**
     * 获取单例
     *
     * @param array $config 配置
     * @return Cache
     */
    public static function instance(array $config = []): Cache
    {
        if (is_null(static::$instance)) {
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    /**
     * 后缀方法
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取配置信息
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 设置配置信息
     *
     * @param array $config
     * @return Cache
     */
    public function setConfig(array $config): Cache
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * 获取支持的驱动
     *
     * @return array
     */
    public function supportDriver(): array
    {
        return $this->driverType;
    }

    /**
     * 扩展支持的驱动
     *
     * @param string $name  驱动名称
     * @param string $drive 驱动类名
     * @return Cache
     */
    public function extend(string $name, string $drive): Cache
    {
        if (!is_subclass_of($drive, CacheInterface::class)) {
            throw new InvalidArgumentException('Driver needs implements the ' . CacheInterface::class);
        }

        $this->driverType[$name] = $drive;
        return $this;
    }

    /**
     * 获取缓存驱动
     *
     * @param string $type      缓存驱动类型
     * @param array $config     初始化缓存驱动实例构造参数，默认配置信息
     * @param boolean $reset    是否重新生成缓存驱动
     * @return CacheInterface
     */
    public function connect(string $type = '', array $config = [], bool $reset = false): CacheInterface
    {
        $type = $type ?: $this->config['driver'] ?: 'file';
        $config = $config ?: $this->config;
        if (!isset($this->driver[$type]) || $reset) {
            if (!in_array($type, array_keys($this->driverType))) {
                throw new InvalidArgumentException("Cache driver type is not supported");
            }
            $this->driver[$type] = new $this->driverType[$type]($config);
        }

        return $this->driver[$type];
    }

    /**
     * 读取缓存并删除
     *
     * @param string $key 缓存变量名
     * @param mixed $default    默认值
     * @return mixed
     */
    public function pull(string $key, $default = null)
    {
        // 先获取值
        $result = $this->get($key, $default);
        // 存在key，则删除
        $this->has($key) && $this->delete($key);

        return $result;
    }

    /**
     * 自增
     *
     * @param string $key   键名
     * @param integer $step 步长
     * @param integer|null $ttl 有效期
     * @return boolean
     */
    public function inc(string $key, int $step = 1, int $ttl = null): bool
    {
        // 先获取值
        $result = $this->get($key);
        if (!is_numeric($result)) {
            return false;
        }
        // 自增
        $value = $result + $step;
        return $this->set($key, $value, $ttl);
    }

    /**
     * 自减
     *
     * @param string $key   键名
     * @param integer $step 步长
     * @param integer|null $ttl 有效期
     * @return boolean
     */
    public function dec(string $key, int $step = 1, int $ttl = null): bool
    {
        // 先获取值
        $result = $this->get($key);
        if (!is_numeric($result)) {
            return false;
        }
        // 自增
        $value = $result - $step;
        return $this->set($key, $value, $ttl);
    }

    /**
     * 调用缓存驱动方法
     *
     * @param string $method 调用方法
     * @param array $args 参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->connect(), $method], $args);
    }
}
