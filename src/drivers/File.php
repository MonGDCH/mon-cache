<?php

declare(strict_types=1);

namespace mon\cache\drivers;

use mon\cache\Traits;
use mon\cache\CacheInterface;
use mon\cache\exception\CacheException;
use mon\cache\exception\InvalidArgumentException;

/**
 * 文件缓存驱动
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class File implements CacheInterface
{
    use Traits;

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 使用子目录保存
        'cache_subdir'  => false,
        // 缓存前缀
        'prefix'        => '',
        // 缓存路径
        'path'          => '',
        // 数据压缩
        'data_compress' => false,
        // 默认缓存有效时间
        'expire'        => 0,
    ];

    /**
     * 缓存文件后缀
     *
     * @var string
     */
    protected $file_ext = '.cache';

    /**
     * 构造方法
     *
     * @param array $config 配置信息
     */
    public function __construct(array $config = [])
    {
        if (!isset($config['path']) || empty($config['path'])) {
            throw new InvalidArgumentException("Cache File Driver config required 'path'");
        }

        // 定义配置
        $this->config = array_merge($this->config, $config);
        if (substr($this->config['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->config['path'] .= DIRECTORY_SEPARATOR;
        }

        // 创建缓存目录
        is_dir($this->config['path']) || mkdir($this->config['path'], 0755, true);
    }

    /**
     * Ping
     *
     * @throws \Exception
     * @return void
     */
    public function ping() {}

    /**
     * 获取缓存内容
     *
     * @param  string $key    名称
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $filename = $this->getCacheKey($key, false);
        // 常驻进程内 PHP 会缓存 stat 结果，其他进程改写缓存后必须清理，否则 mtime 判断失真
        clearstatcache(true, $filename);
        if (!is_file($filename)) {
            return $default;
        }
        $content = @file_get_contents($filename);
        if (false === $content) {
            return $default;
        }
        // 前12位为有效期
        $expire = (int) substr($content, 0, 12);
        if (0 != $expire && time() > filemtime($filename) + $expire) {
            //缓存过期删除缓存文件
            @unlink($filename);
            return $default;
        }
        $content = substr($content, 12);
        if ($this->config['data_compress'] && function_exists('gzcompress')) {
            //数据解压
            $content = @gzuncompress($content);
            if (false === $content) {
                // 数据损坏，删除后按未命中处理
                @unlink($filename);
                return $default;
            }
        }
        $value = $this->unserialize($content);
        if ($this->isUnserializeFailed($value, $content)) {
            // 反序列化失败（数据损坏或被截断），删除后按未命中处理
            @unlink($filename);
            return $default;
        }

        return $value;
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
     * 写入缓存
     *
     * @param string    $key    缓存变量名
     * @param mixed     $value  存储数据
     * @param integer   $ttl    有效时间 0为永久
     * @throws InvalidArgumentException
     * @return boolean
     */
    public function set($key, $value, $ttl = null): bool
    {
        $ttl = $this->ttlToSeconds($ttl);
        if (is_null($ttl)) {
            $ttl = (int) $this->config['expire'];
        }
        $filename = $this->getCacheKey($key);
        $data = $this->serialize($value);
        if ($this->config['data_compress'] && function_exists('gzcompress')) {
            //数据压缩
            $data = gzcompress($data, 3);
        }
        // 前12位为有效期，数据不再包裹在PHP标签内，避免缓存文件被当作脚本执行
        $data = sprintf('%012d', $ttl) . $data;
        // 先写临时文件再原子重命名，加锁避免并发下读到写了一半的数据
        $temp = $filename . '.' . uniqid('', true) . '.tmp';
        $result = file_put_contents($temp, $data, LOCK_EX);
        if (false !== $result && rename($temp, $filename)) {
            clearstatcache(true, $filename);
            return true;
        }
        is_file($temp) && @unlink($temp);

        return false;
    }

    /**
     * 批量设置缓存
     *
     * @param array $values 关联数组作为缓存的键值
     * @param integer $ttl  有效时间，0为永久
     * @throws CacheException
     * @throws InvalidArgumentException
     * @return boolean
     */
    public function setMultiple($values, $ttl = null): bool
    {
        // 提前校验，避免批量写入中途才抛出异常
        $ttl = $this->ttlToSeconds($ttl);
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                throw new CacheException("Cache the [{$key}] value faild");
            }
        }

        return true;
    }

    /**
     * 是否存在缓存
     *
     * @param  string  $key 名称
     * @return boolean
     */
    public function has($key): bool
    {
        $filename = $this->getCacheKey($key, false);
        clearstatcache(true, $filename);
        if (!is_file($filename)) {
            return false;
        }
        // 只读取有效期部分，避免为了判断存在性读取整个缓存内容
        $header = @file_get_contents($filename, false, null, 0, 12);
        if (false === $header) {
            return false;
        }
        // 只判断存在性与有效期，避免值为假值（0、''、false等）时误判为不存在
        $expire = (int) $header;

        return 0 == $expire || time() <= filemtime($filename) + $expire;
    }

    /**
     * 获取缓存剩余有效期
     *
     * @param  string $key 缓存变量名
     * @return integer|null 剩余秒数，0为永久，key不存在时返回null
     */
    public function getTtl(string $key): ?int
    {
        $filename = $this->getCacheKey($key, false);
        clearstatcache(true, $filename);
        if (!is_file($filename)) {
            return null;
        }
        $header = @file_get_contents($filename, false, null, 0, 12);
        if (false === $header) {
            return null;
        }
        $expire = (int) $header;
        if (0 == $expire) {
            return 0;
        }
        $remaining = filemtime($filename) + $expire - time();

        return $remaining > 0 ? $remaining : null;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存变量名
     * @return boolean
     */
    public function delete($key): bool
    {
        $filename = $this->getCacheKey($key, false);
        clearstatcache(true, $filename);
        if (file_exists($filename)) {
            return @unlink($filename);
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
    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                throw new CacheException("Delete the [{$key}] value faild");
            }
        }

        return true;
    }

    /**
     * 清除缓存
     *
     * 同时清理历史版本遗留的 .php 缓存文件与写入中断残留的 .tmp 文件。
     *
     * @return boolean
     */
    public function clear(): bool
    {
        $base = $this->getCachePath();
        $files = (array) glob($base . '*');
        foreach ($files as $path) {
            if (is_dir($path)) {
                // 子目录内的缓存文件（包含旧版 .php 文件与残留的 .tmp 文件）
                foreach ((array) glob($path . DIRECTORY_SEPARATOR . '*') as $file) {
                    is_file($file) && @unlink($file);
                }
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        clearstatcache();

        return true;
    }

    /**
     * 清理过期缓存文件
     *
     * 文件驱动的过期数据只在被读取时才删除，写入多读取少的场景会持续占用磁盘，
     * 常驻进程下可在定时器中周期性调用本方法回收空间。
     *
     * @return integer 清理的文件数量
     */
    public function gc(): int
    {
        $count = 0;
        $base = $this->getCachePath();
        foreach ((array) glob($base . '*') as $path) {
            if (is_dir($path)) {
                $files = (array) glob($path . DIRECTORY_SEPARATOR . '*' . $this->file_ext);
            } else {
                $files = [$path];
            }
            foreach ($files as $file) {
                $header = @file_get_contents($file, false, null, 0, 12);
                if (false === $header) {
                    continue;
                }
                $expire = (int) $header;
                if ($expire > 0 && time() > @filemtime($file) + $expire && @unlink($file)) {
                    $count++;
                }
            }
        }
        clearstatcache();

        return $count;
    }

    /**
     * 获取缓存存储根目录（含前缀目录）
     *
     * @return string
     */
    protected function getCachePath(): string
    {
        return $this->config['path'] . ($this->config['prefix'] ? $this->config['prefix'] . DIRECTORY_SEPARATOR : '');
    }

    /**
     * 取得变量的存储文件名
     *
     * @param  string  $key       缓存名称
     * @param  boolean $createDir 是否确保目录存在，只读操作无需创建
     * @return string
     */
    protected function getCacheKey(string $key, bool $createDir = true): string
    {
        $name = md5($key);
        if ($this->config['cache_subdir']) {
            // 使用子目录
            $name = substr($name, 0, 2) . DIRECTORY_SEPARATOR . substr($name, 2);
        }
        if ($this->config['prefix']) {
            $name = $this->config['prefix'] . DIRECTORY_SEPARATOR . $name;
        }
        $filename = $this->config['path'] . $name . $this->file_ext;
        if ($createDir) {
            $dir = dirname($filename);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }

        return $filename;
    }
}
