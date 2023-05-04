<?php

/*
|--------------------------------------------------------------------------
| 缓存配置文件
|--------------------------------------------------------------------------
| 定义缓存配置信息
|
*/

return [
    // 默认缓存驱动
    'default'   => 'file',
    // 定时Ping通服务，单位秒，0则不定时Ping通
    'ping'      => 55,
    // 缓存驱动
    'stores'    => [
        // 文件缓存
        'file'  => [
            // 驱动器
            'driver'        => \mon\cache\drivers\File::class,
            // 默认缓存有效时间
            'expire'        => 0,
            // 使用子目录保存
            'cache_subdir'  => false,
            // 缓存前缀
            'prefix'        => '',
            // 缓存路径
            'path'          => RUNTIME_PATH . '/cache',
            // 数据压缩
            'data_compress' => false,
        ],
        // Redis缓存
        'redis' => [
            // 驱动器
            'driver'        => \mon\cache\drivers\Redis::class,
            // 链接host
            'host'          => '127.0.0.1',
            // 链接端口
            'port'          => 6379,
            // 链接密码
            'auth'          => '',
            // 读取超时时间
            'timeout'       => 2,
            // 自定义键前缀
            'prefix'        => '',
            // 默认缓存有效时间
            'expire'        => 0,
            // redis数据库
            'database'      => 1,
            // 保持链接
            'persistent'    => false,
        ]
    ]
];
