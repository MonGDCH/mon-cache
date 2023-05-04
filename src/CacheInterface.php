<?php

declare(strict_types=1);

namespace mon\cache;

/**
 * 缓存驱动接口
 * @see 由于`psr/simple-cache`库没有`PHP7`的版本，这里自行实现，并扩展`ping`方法
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
interface CacheInterface
{
    /**
     * Ping
     *
     * @return mixed
     */
    public function ping();

    /**
     * 从缓存中取出值
     *
     * @param string $key     该项在缓存中唯一的key值
     * @param mixed  $default key不存在时，返回的默认值
     * @throws \Psr\SimpleCache\InvalidArgumentException 如果给定的key不是一个合法的字符串时，抛出该异常
     * @return mixed 从缓存中返回的值，或者是不存在时的默认值
     */
    public function get(string $key, $default = null);

    /**
     * 存储值在cache中，唯一关键到一个key及一个可选的存在时间
     *
     * @param string    $key   存储项目的key.
     * @param mixed     $value 存储的值，必须可以被序列化的
     * @param null|int  $ttl   可选项.项目的存在时间，如果该值没有设置，且驱动支持生存时间时，将设置一个默认值，或者驱自行处理。
     * @throws \Psr\SimpleCache\InvalidArgumentException 如果给定的key不是一个合法的字符串时，抛出该异常。
     * @return bool true 存储成功  false 存储失败
     */
    public function set(string $key, $value, int $ttl = null): bool;

    /**
     * 删除指定键值的缓存项
     *
     * @param string $key 指定的唯一缓存key对应的项目将会被删除
     * @throws \Psr\SimpleCache\InvalidArgumentException 如果给定的key不是一个合法的字符串时，抛出该异常。
     * @return bool 成功删除时返回ture，有其它错误时时返回false
     *
     */
    public function delete(string $key): bool;

    /**
     * 清除所有缓存中的key
     *
     * @return bool 成功返回true.失败返回false
     */
    public function clear(): bool;

    /**
     * 根据指定的缓存键值列表获取得多个缓存项目
     *
     * @param array $keys   在单次操作中可被获取的键值项
     * @param mixed    $default 如果key不存在时，返回的默认值
     * @throws \Psr\SimpleCache\InvalidArgumentException 如果给定的keys既不是合法的数组，或者给得的任何一个key不是一个合法的值时，拖出该异常。
     * @return array  返回键值对（key=>value形式）列表。如果key不存在，或者已经过期时，返回默认值。
     */
    public function getMultiple(array $keys, $default = null): array;

    /**
     * 存储一个键值对形式的集合到缓存中。
     *
     * @param array         $values 一系列操作的键值对列表
     * @param null|int|     $ttl     可选项.项目的存在时间，如果该值没有设置，且驱动支持生存时间时，将设置一个默认值，或者驱自行处理。
     * @throws \Psr\SimpleCache\InvalidArgumentException 如果给定的keys既不是合法的数组，或者给得的任何一个key不是一个合法的值时，拖出该异常.
     * @return bool 成功返回True.失败返回False.
     */
    public function setMultiple(array $values, int $ttl = null): bool;

    /**
     *  单次操作删除多个缓存项目.
     *
     * @param array $keys 一个基于字符串键列表会被删除
     * @throws \Psr\SimpleCache\InvalidArgumentException 如果给定的keys既不是合法的数组，或者给得的任何一个key不是一个合法的值时，拖出该异常.
     * @return bool True 所有项目都成功被删除时回true,有任何错误时返回false
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * 判断一个项目在缓存中是否存在
     *
     * 注意: has()方法仅仅在缓存预热的场景被推荐使用且不允许的活跃的应用中场景中对get/set方法使用, 因为方法受竞态条件的限制，
     * 当你调用has()方法时会立即返回true。另一个脚本可以删除它，使应用状态过期。
     * 
     * @param string $key 缓存键值
     * @return bool  
     */
    public function has(string $key): bool;
}
