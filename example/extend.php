<?php

use mon\cache\Cache;
use mon\cache\Driver;

require __DIR__ . '/../vendor/autoload.php';

// 扩展自定义缓存类型，需要继承Driver类
class MyDriver extends Driver
{
    public function __construct(array $config = [])
    {
        var_dump($config);
    }

    /**
     * 获取缓存内容
     *
     * @param  string $key      名称
     * @param  mixed  $default  默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return __CLASS__ . '_' . $key;
    }

    /**
     * 写入缓存
     *
     * @param string  $key      缓存变量名
     * @param mixed   $value    存储数据
     * @param integer $ttl      有效时间，0为永久
     * @return boolean
     */
    public function set(string $key, $value, int $ttl = null): bool
    {
        return true;
    }

    /**
     * 是否存在缓存
     *
     * @param  string  $key 名称
     * @return boolean
     */
    public function has(string $key): bool
    {
        return true;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存变量名
     * @return boolean
     */
    public function delete(string $key): bool
    {
        return true;
    }

    /**
     * 清除缓存
     *
     * @return boolean
     */
    public function clear(): bool
    {
        return true;
    }
}

// 自定义配置
$myConfig = [
    // 默认驱动类型
    'driver'    => 'mydirver',
    // 自定义配置
    'desc'      => 'my cache driver',
];

// 创建缓存实例
$cache = new Cache();
// 扩展自定义缓存
$cache->extend('mydirver', MyDriver::class);
// 配置
$cache->setConfig($myConfig);
// 使用缓存
$data = $cache->get('test');

var_dump($data);
