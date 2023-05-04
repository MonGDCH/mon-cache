<?php

use mon\cache\Cache;
use mon\cache\CacheInterface;

require __DIR__ . '/../vendor/autoload.php';

// 扩展自定义缓存类型，需要继承Driver类
class MyDriver implements CacheInterface
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

    public function getMultiple(array $keys, $default = null): array
    {
        return [];
    }

    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        return true;
    }
}

// 创建缓存实例
$cache = new Cache();
// 扩展自定义缓存
$cache->extend('my_driver', [
    'driver'    => MyDriver::class,
    'desc'      => 'My Driver'
]);

// 设置默认使用的缓存驱动
// $cache->setDefaultDriver('my_driver');
// $data = $cache->get('demo');

// 使用自定义扩展的缓存
$data = $cache->store('my_driver')->get('test');

var_dump($data);
