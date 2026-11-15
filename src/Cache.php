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
 * @method mixed ping() Ping
 * @method mixed get(string $key, mixed $default = null)  获取缓存
 * @method array getMultiple(array $keys, $default = null)  批量获取缓存
 * @method boolean set(string $key, mixed $value, integer $ttl = null) 设置缓存
 * @method boolean setMultiple(array $values, int $ttl = null)  批量设置缓存
 * @method boolean has(string $key) 是否存在缓存
 * @method boolean delete(string $key) 删除缓存
 * @method boolean deleteMultiple(array $keys) 批量删除缓存
 * @method boolean clear() 清空缓存
 * @method integer|null getTtl(string $key) 获取剩余有效期，0为永久，key不存在返回null
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Cache
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 默认缓存驱动
        'default'   => 'file',
        // 缓存驱动
        'stores'    => [
            // 文件缓存
            'file'  => [
                // 驱动器
                'driver'        => File::class,
                // 默认缓存有效时间
                'expire'        => 0,
                // 使用子目录保存
                'cache_subdir'  => false,
                // 缓存前缀
                'prefix'        => '',
                // 缓存路径
                'path'          => '',
                // 数据压缩
                'data_compress' => false,
            ],
            // Redis缓存
            'redis' => [
                // 驱动器
                'driver'        => Redis::class,
                // 链接host
                'host'          => '127.0.0.1',
                // 链接端口
                'port'          => 6379,
                // 链接密码
                'auth'          => '',
                // 自定义键前缀
                'prefix'        => '',
                // redis数据库
                'database'      => 1,
                // 读取超时时间
                'timeout'       => 2,
                // 默认缓存有效时间
                'expire'        => 0,
                // 持久链接
                'persistent'    => false,
            ]
        ]
    ];

    /**
     * 缓存驱动
     *
     * @var CacheInterface[]
     */
    protected $driver = [];

    /**
     * 驱动实例的构造配置，用于判断临时配置是否需要重建驱动
     *
     * @var array
     */
    protected $driver_config = [];

    /**
     * 后缀方法
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
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
        // 递归合并，避免只覆盖部分配置项（如仅传 path）时丢失同级的 driver、expire 等默认配置
        $this->config = $this->mergeConfig($this->config, $config);
        return $this;
    }

    /**
     * 递归合并配置信息
     *
     * @param array $base     默认配置
     * @param array $override 自定义配置
     * @return array
     */
    protected function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * 扩展支持的驱动
     *
     * @param string $name   驱动名称
     * @param array  $config 驱动配置
     * @return Cache
     */
    public function extend(string $name, array $config): Cache
    {
        if (!isset($config['driver']) || !is_subclass_of($config['driver'], CacheInterface::class)) {
            throw new InvalidArgumentException('Driver needs implements the ' . CacheInterface::class);
        }

        $this->config['stores'][$name] = $config;
        // 已实例化的同名驱动信息失效，下次获取时按新配置重建
        unset($this->driver[$name], $this->driver_config[$name]);

        return $this;
    }

    /**
     * 设置默认缓存驱动
     *
     * @param string $name  驱动名
     * @return Cache
     */
    public function setDefaultDriver(string $name): Cache
    {
        if (!isset($this->config['stores'][$name])) {
            throw new InvalidArgumentException('Driver type not supported');
        }

        $this->config['default'] = $name;
        return $this;
    }

    /**
     * 获取缓存驱动
     *
     * @param string $type      缓存驱动类型
     * @param array $config     初始化缓存驱动实例构造参数，默认配置信息
     * @param boolean $reset    是否重新生成缓存驱动
     * @throws InvalidArgumentException
     * @return CacheInterface
     */
    public function store(string $type = '', array $config = [], bool $reset = false): CacheInterface
    {
        $type = $type ?: $this->config['default'];
        // 已实例化时按需重建：传入的临时配置与实例配置不一致（== 忽略键序差异），
        // 或未传配置但实例并非由 stores 配置构建（避免临时配置泄漏到后续默认调用）
        if (!$reset && isset($this->driver[$type])) {
            $expected = empty($config) ? ($this->config['stores'][$type] ?? null) : $config;
            if ($expected !== null && $this->driver_config[$type] != $expected) {
                $config = $expected;
                $reset = true;
            }
        }
        // 加载驱动
        if (!isset($this->driver[$type]) || $reset) {
            // 获取驱动配置
            if (empty($config)) {
                if (!isset($this->config['stores'][$type])) {
                    throw new InvalidArgumentException("Cache driver type is not supported");
                }
                $config = $this->config['stores'][$type];
            }
            // 验证有效驱动
            if (!isset($config['driver']) || !is_subclass_of($config['driver'], CacheInterface::class)) {
                throw new InvalidArgumentException('Driver [' . $type . '] needs implements the ' . CacheInterface::class);
            }
            $driver = $config['driver'];
            $this->driver[$type] = new $driver($config);
            $this->driver_config[$type] = $config;
        }

        return $this->driver[$type];
    }

    /**
     * 获取已实例化的缓存驱动
     *
     * 常驻进程保活时需要遍历所有已经建立的连接，而不是只处理默认驱动。
     *
     * @return CacheInterface[]
     */
    public function getDrivers(): array
    {
        return $this->driver;
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
        // 删除key，key不存在时删除操作本身就是幂等的，无需先判断存在性
        $this->delete($key);

        return $result;
    }

    /**
     * 自增
     *
     * @param string $key   键名
     * @param integer $step 步长
     * @param integer|null $ttl 有效期，为null且key已存在时保留原有效期
     * @return boolean
     */
    public function inc(string $key, int $step = 1, ?int $ttl = null): bool
    {
        return $this->calculate($key, $step, $ttl);
    }

    /**
     * 自减
     *
     * @param string $key   键名
     * @param integer $step 步长
     * @param integer|null $ttl 有效期，为null且key已存在时保留原有效期
     * @return boolean
     */
    public function dec(string $key, int $step = 1, ?int $ttl = null): bool
    {
        return $this->calculate($key, -$step, $ttl);
    }

    /**
     * 计算缓存值
     *
     * @param string $key   键名
     * @param integer $step 步长，可为负
     * @param integer|null $ttl 有效期
     * @return boolean
     */
    protected function calculate(string $key, int $step, ?int $ttl): bool
    {
        $driver = $this->store();
        // 先获取值
        $result = $driver->get($key);
        if (!is_numeric($result)) {
            return false;
        }
        // 未指定有效期且驱动支持时，保留原key的剩余有效期，避免重置为默认值
        if (is_null($ttl) && method_exists($driver, 'getTtl')) {
            $ttl = $driver->getTtl($key);
        }
        return $driver->set($key, $result + $step, $ttl);
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
        return call_user_func_array([$this->store(), $method], $args);
    }
}
