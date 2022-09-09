<?php

declare(strict_types=1);

namespace mon\cache\drivers;

use mon\cache\Driver;
use mon\cache\exception\InvalidArgumentException;

/**
 * 文件缓存驱动
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class File extends Driver
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 有效时间
        'expire'        => 0,
        // 使用子目录保存
        'cache_subdir'  => false,
        // 缓存前缀
        'prefix'        => '',
        // 缓存路径
        'path'          => '',
        // 数据压缩
        'data_compress' => false,
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
     * 获取缓存内容
     *
     * @param  string $key    名称
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
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
     * 写入缓存
     *
     * @param string    $key    缓存变量名
     * @param mixed     $value   存储数据
     * @param integer   $expire  有效时间 0为永久
     * @return boolean
     */
    public function set(string $key, $value, int $expire = null): bool
    {
        if (is_null($expire)) {
            $expire = $this->config['expire'];
        }
        $filename = $this->getCacheKey($key);
        $data = serialize($value);
        if ($this->config['data_compress'] && function_exists('gzcompress')) {
            //数据压缩
            $data = gzcompress($data, 3);
        }
        $data   = "<?php\n//" . sprintf('%012d', $expire) . $data . "\n?>";
        $result = file_put_contents($filename, $data);
        if ($result) {
            clearstatcache();
            return true;
        }

        return false;
    }

    /**
     * 是否存在缓存
     *
     * @param  string  $key 名称
     * @return boolean
     */
    public function has(string $key): bool
    {
        return $this->get($key) ? true : false;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存变量名
     * @return boolean
     */
    public function delete(string $key): bool
    {
        $filename = $this->getCacheKey($key);
        if (file_exists($filename)) {
            unlink($filename);
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
