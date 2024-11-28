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
    'default'       => env('CACHE_TYPE', 'file'),
    // 服务保活
    'keep_alive'    => [
        // 是否启动
        'enable'    => env('CACHE_KEEP', true),
        // 定时Ping通服务，单位秒，0则不定时ping通服务
        'ping'      => env('CACHE_PING', 55),
        // 最大重启服务次数
        'reset_max' => env('CACHE_RESET', 3),
        // 异常事件名称
        'event'     => 'cache_error'
    ],
    // 缓存驱动
    'stores'        => [
        // Gaia内置redis缓存实例
        'rdb' => [
            // 驱动器
            'driver'        => \support\cache\extend\Rdb::class,
            // 默认缓存有效时间
            'expire'        => env('CACHE_EXPIRE', 0),
        ],
        // Gaia在使用sqlite作为缓存，依赖 mongdch/mon-think-orm 库
        'sqlite' => [
            'driver'        => \support\cache\extend\Sqlite::class,
            // 缓存的表
            'table'         => 'gaia_cache',
            // 数据库
            'database'      => ROOT_PATH . '/database/sqlite.db',
            // 是否开启SQL监听
            'trigger_sql'   => false,
            // 默认缓存有效时间
            'expire'        => env('CACHE_EXPIRE', 0),
        ],
        // 文件缓存
        'file'  => [
            // 驱动器
            'driver'        => \mon\cache\drivers\File::class,
            // 使用子目录保存
            'cache_subdir'  => env('CACHE_SUBDIR', false),
            // 缓存前缀
            'prefix'        => env('CACHE_PREFIX', ''),
            // 缓存路径
            'path'          => env('CACHE_PATH', RUNTIME_PATH . '/cache'),
            // 数据压缩
            'data_compress' => env('CACHE_COMPRESS', false),
            // 默认缓存有效时间
            'expire'        => env('CACHE_EXPIRE', 0),
        ],
        // Redis缓存
        'redis' => [
            // 驱动器
            'driver'        => \mon\cache\drivers\Redis::class,
            // 链接host
            'host'          => env('CACHE_HOST', '127.0.0.1'),
            // 链接端口
            'port'          => env('CACHE_PORT', 6379),
            // 链接密码
            'auth'          => env('CACHE_AUTH', ''),
            // 自定义键前缀
            'prefix'        => env('CACHE_PREFIX', ''),
            // redis数据库
            'database'      => env('CACHE_DB', 1),
            // 读取超时时间
            'timeout'       => env('CACHE_TIMEOUT', 2),
            // 保持链接
            'persistent'    => env('CACHE_PERSISTENT', false),
            // 默认缓存有效时间
            'expire'        => env('CACHE_EXPIRE', 0),
        ]
    ]
];
