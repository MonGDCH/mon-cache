<?php

declare(strict_types=1);

namespace support\cache;

use mon\env\Config;
use mon\cache\Cache;
use mon\util\Instance;

/**
 * 缓存服务
 * 
 * @method mixed get(string $key, mixed $default = null)  获取缓存
 * @method array getMultiple(array $keys, $default = nulll)  批量获取缓存
 * @method boolean set(string $key, mixed $value, integer $expire = null) 设置缓存
 * @method boolean setMultiple(array $values, int $ttl = null)  批量设置缓存
 * @method boolean has(string $key) 是否存在缓存
 * @method boolean delete(string $key) 删除缓存
 * @method boolean deleteMultiple(array $keys) 批量删除缓存
 * @method boolean clear() 清空缓存
 * @method mixed pull(string $key, $default = null) 读取缓存并删除
 * @method mixed handler() 获取存储引擎
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class CacheService
{
    use Instance;

    /**
     * 缓存服务对象
     *
     * @var Cache
     */
    protected $service;

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 驱动类型
        'driver'    => 'redis',
        // 链接host
        'host'      => '127.0.0.1',
        // 链接端口
        'port'      => 6379,
        // 链接密码
        'auth'      => '',
        // 自定义键前缀
        'prefix'    => 'mon_cache_',
        // redis数据库
        'database'  => 1,
        // 读取超时时间
        'timeout'   => 2,
        // 默认缓存有效时间
        'expire'    => 0,
        // 常驻进程下，定时ping保持连接
        'ping'      => 55,
    ];

    /**
     * 构造方法
     */
    public function __construct()
    {
        $config = Config::instance()->get('cache', []);
        $this->register($config);
    }

    /**
     * 注册配置信息
     *
     * @param array $config
     * @return CacheService
     */
    public function register(array $config): CacheService
    {
        $this->config = array_merge($this->config, $config);
        return $this;
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
     * 获取缓存服务实例
     *
     * @return Cache
     */
    public function getService(): Cache
    {
        if (is_null($this->service)) {
            $this->service = new Cache($this->config);

            // 常驻进程，保持redis连接
            if (IN_HTTP && $this->config['driver'] == 'redis') {
                \Workerman\Timer::add(!empty($this->config['ping']) ? $this->config['ping'] : 55, function () {
                    $this->service->handler()->ping();
                });
            }
        }

        return $this->service;
    }

    /**
     * 回调服务
     *
     * @param string $name      方法名
     * @param mixed $arguments 参数列表
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->getService(), $name], (array) $arguments);
    }
}
