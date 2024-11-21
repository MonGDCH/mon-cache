<?php

declare(strict_types=1);

namespace mon\cache;

use support\Plugin;

/**
 * Gaia框架安装驱动
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Install
{
    /**
     * 标志为Gaia的驱动
     */
    const GAIA_PLUGIN = true;

    /**
     * 移动的文件
     *
     * @var array
     */
    protected static $file_relation = [
        'gaia/config.php' => 'config/cache.php',
        'gaia/Rdb.php' => 'support/cache/extend/Rdb.php',
        'gaia/CacheService.php' => 'support/cache/CacheService.php',
    ];

    /**
     * 安装
     *
     * @return void
     */
    public static function install()
    {
        echo '[mon-cache] installation successful, please execute `php gaia vendor:publish mon\cache`' . PHP_EOL;
    }

    /**
     * 更新升级
     *
     * @return void
     */
    public static function update()
    {
        echo '[mon-cache] upgrade successful, please execute `php gaia vendor:publish mon\cache`' . PHP_EOL;
    }

    /**
     * 发布安装
     *
     * @return void
     */
    public static function publish()
    {
        // 创建框架文件
        $source_path = __DIR__ . DIRECTORY_SEPARATOR;
        // 移动文件
        foreach (static::$file_relation as $source => $dest) {
            $sourceFile = $source_path . $source;
            Plugin::copyFile($sourceFile, $dest, true);
        }
    }
}
