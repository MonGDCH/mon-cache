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
 * @method boolean set(string $key, mixed $value, integer $ttl = null) 设置缓存
 * @method boolean setMultiple(array $values, int $ttl = null)  批量设置缓存
 * @method boolean has(string $key) 是否存在缓存
 * @method boolean delete(string $key) 删除缓存
 * @method boolean deleteMultiple(array $keys) 批量删除缓存
 * @method boolean clear() 清空缓存
 * @method boolean inc(string $key, int $step = 1, int $ttl = null) 自增
 * @method boolean dec(string $key, int $step = 1, int $ttl = null) 自减
 * @method mixed pull(string $key, $default = null) 读取缓存并删除
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
     * 定时器ID
     *
     * @var integer
     */
    protected $ping_timer;

    /**
     * 构造方法
     */
    protected function __construct()
    {
        $config = Config::instance()->get('cache', []);
        $this->service = new Cache($config);
        // 是否开启Ping
        if ($config['ping'] > 0) {
            $this->keep($config['ping']);
        }
    }

    /**
     * 获取缓存服务实例
     *
     * @return Cache
     */
    public function getService(): Cache
    {
        return $this->service;
    }

    /**
     * 使用Ping保持连接
     *
     * @param integer $ping ping的间隔
     * @return void
     */
    public function keep(int $ping = 55)
    {
        $this->ping_timer = \Workerman\Timer::add($ping, function () {
            $this->getService()->ping();
        });
    }

    /**
     * 移除定时ping
     *
     * @return void
     */
    public function unKeep()
    {
        \Workerman\Timer::del($this->ping_timer);
    }

    /**
     * 回调服务
     *
     * @param string $name  方法名
     * @param array $args   参数列表
     * @return mixed
     */
    public function __call($name, $args)
    {
        return call_user_func_array([$this->getService(), $name], $args);
    }
}
