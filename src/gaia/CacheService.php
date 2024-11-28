<?php

declare(strict_types=1);

namespace support\cache;

use Throwable;
use mon\env\Config;
use mon\log\Logger;
use mon\util\Event;
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
 * @method \mon\cache\CacheInterface store(string $type = '', array $config = [], bool $reset = false)   获取缓存服务
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
     * 当前重启次数
     *
     * @var integer
     */
    protected $err_count = 0;

    /**
     * 缓存服务配置信息
     *
     * @var array
     */
    protected $config = [];

    /**
     * 服务存活保障配置
     *
     * @var array
     */
    protected $keepAliveConfig = [
        // 是否启动
        'enable'    => true,
        // 定时Ping通服务，单位秒，0则不定时Ping通
        'ping'      => 55,
        // 最大重启服务次数
        'reset_max' => 3,
        // 异常事件名称
        'event'     => 'cache_error'
    ];

    /**
     * 构造方法
     */
    protected function __construct()
    {
        $this->config = Config::instance()->get('cache', []);
        $this->service = new Cache($this->config);
        $this->keepAliveConfig = $this->config['keep_alive'];
        // 是否开启Ping
        if ($this->keepAliveConfig['enable']) {
            $this->keep($this->keepAliveConfig['ping'], $this->keepAliveConfig['reset_max'], $this->keepAliveConfig['event']);
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
     * @param integer $max  最大重启次数
     * @param string $evnet 异常事件名
     * @return void
     */
    public function keep(int $ping = 55, int $max = 3, string $evnet = 'cache_error')
    {
        $this->ping_timer = \Workerman\Timer::add($ping, function (int $max, string $evnet) {
            try {
                $this->getService()->ping();
                // 连接正常，清空错误计数
                $this->err_count = 0;
            } catch (Throwable $e) {
                $this->err_count++;
                Logger::instance()->channel()->error('Cache Service Exception. message => ' . $e->getMessage() . ' code => ' . $e->getCode());
                // 上报缓存错误事件
                Event::instance()->trigger($evnet, ['err_count' => $max, 'config' => $this->config]);
                if ($max >= $this->err_count) {
                    // 一定次数内，自动重启服务
                    Logger::instance()->channel()->info('Cache Service restart');
                    $this->service = new Cache($this->config);
                } else {
                    // 超过失败最大是，停止保活
                    $this->unKeep();
                }
            }
        }, [$max, $evnet]);
    }

    /**
     * 移除定时ping
     *
     * @return void
     */
    public function unKeep()
    {
        \Workerman\Timer::del($this->ping_timer);
        Logger::instance()->channel()->info('Cache Service keep stop.');
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
