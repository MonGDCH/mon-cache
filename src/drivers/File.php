<?php

declare(strict_types=1);

namespace mon\cache\drivers;

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
    public function ping()
    {
    }

    /**
     * 获取缓存内容
     *
     * @param  string $key    名称
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $filename = $this->getCacheKey($key);
        if (!is_file($filename)) {
            return $default;
        }
        $content = file_get_contents($filename);
        if (false !== $content) {
            $expire = (int) substr($content, 8, 12);
            if (0 != $expire && time() > filemtime($filename) + $expire) {
                //缓存过期删除缓存文件
                unlink($filename);
                return $default;
            }
            $content = substr($content, 20, -3);
            if ($this->config['data_compress'] && function_exists('gzcompress')) {
                //启用数据压缩
                $content = gzuncompress($content);
            }

            $content = unserialize($content);
            return $content;
        }

        return $default;
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
     * @param mixed     $value   存储数据
     * @param integer   $expire  有效时间 0为永久
     * @return boolean
     */
    public function set($key, $value, $ttl = null): bool
    {
        if (is_null($ttl)) {
            $ttl = $this->config['expire'];
        }
        $filename = $this->getCacheKey($key);
        $data = serialize($value);
        if ($this->config['data_compress'] && function_exists('gzcompress')) {
            //数据压缩
            $data = gzcompress($data, 3);
        }
        $data   = "<?php\n//" . sprintf('%012d', $ttl) . $data . "\n?>";
        $result = file_put_contents($filename, $data);
        if ($result) {
            clearstatcache();
            return true;
        }

        return false;
    }

    /**
     * 批量设置缓存
     *
     * @param array $values 关联数组作为缓存的键值
     * @param integer $ttl  有效时间，0为永久
     * @throws CacheException
     * @return boolean
     */
    public function setMultiple($values, $ttl = null): bool
    {
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
        return $this->get($key) ? true : false;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存变量名
     * @return boolean
     */
    public function delete($key): bool
    {
        $filename = $this->getCacheKey($key);
        if (file_exists($filename)) {
            return unlink($filename);
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
     * @return boolean
     */
    public function clear(): bool
    {
        $files = (array) glob($this->config['path'] . ($this->config['prefix'] ? $this->config['prefix'] . DIRECTORY_SEPARATOR : '') . '*');
        foreach ($files as $path) {
            if (is_dir($path)) {
                array_map('unlink', glob($path . '/*.php'));
                rmdir($path);
            } else {
                unlink($path);
            }
        }
        return true;
    }

    /**
     * 取得变量的存储文件名
     *
     * @param  string $key 缓存名称
     * @return string
     */
    protected function getCacheKey(string $key): string
    {
        $name = md5($key);
        if ($this->config['cache_subdir']) {
            // 使用子目录
            $name = substr($name, 0, 2) . DIRECTORY_SEPARATOR . substr($name, 2);
        }
        if ($this->config['prefix']) {
            $name = $this->config['prefix'] . DIRECTORY_SEPARATOR . $name;
        }
        $filename = $this->config['path'] . $name . '.php';
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $filename;
    }
}
