<?php

declare(strict_types=1);

namespace mon\cache;

use mon\cache\exception\CacheException;

/**
 * 缓存驱动类
 * 
 * @see 由于`psr/simple-cache`库没有`PHP7`的版本，这里直接实现，不进行 implements
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
abstract class Driver
{
    /**
     * 存储引擎实例
     * 
     * @var mixed
     */
    protected $handler;

    /**
     * 获取缓存内容
     *
     * @param  string $key      名称
     * @param  mixed  $default  默认值
     * @return mixed
     */
    abstract public function get(string $key, $default = null);

    /**
     * 写入缓存
     *
     * @param string  $key      缓存变量名
     * @param mixed   $value    存储数据
     * @param integer $ttl      有效时间，0为永久
     * @return boolean
     */
    abstract public function set(string $key, $value, int $ttl = null): bool;

    /**
     * 是否存在缓存
     *
     * @param  string  $key 名称
     * @return boolean
     */
    abstract public function has(string $key): bool;

    /**
     * 删除缓存
     *
     * @param string $key 缓存变量名
     * @return boolean
     */
    abstract public function delete(string $key): bool;

    /**
     * 清除缓存
     *
     * @return boolean
     */
    abstract public function clear(): bool;

    /**
     * 返回驱动实例，可执行其它高级方法
     *
     * @return mixed
     */
    public function handler()
    {
        return $this->handler;
    }

    /**
     * 读取缓存并删除
     *
     * @param  string $key 缓存变量名
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
     * 批量获取缓存内容
     *
     * @param array $keys       缓存变量名一维数组
     * @param mixed $default    字符串或索引数组，不存在对应键时作为返回值
     * @return array
     */
    public function getMultiple(array $keys, $default = null): array
    {
        $values = [];
        foreach ($keys as $key) {
            if (is_array($default)) {
                $defaultvalue = isset($default[$key]) ? $default[$key] : null;
            } else {
                $defaultvalue = $default;
            }
            $values[$key] = $this->get($key, $defaultvalue);
        }

        return $values;
    }

    /**
     * 批量设置缓存
     *
     * @param array $values 关联数组作为缓存的键值
     * @param integer $ttl  有效时间，0为永久
     * @throws CacheException
     * @return boolean
     */
    public function setMultiple(array $values, int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                throw new CacheException("Cache the [{$key}] value faild");
            }
        }

        return true;
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
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                throw new CacheException("Delete the [{$key}] value faild");
            }
        }

        return true;
    }

    /**
     * 获取实际的缓存标识
     *
     * @param  string $key 缓存名
     * @return string
     */
    protected function getCacheKey(string $key): string
    {
        return $this->config['prefix'] . $key;
    }
}
