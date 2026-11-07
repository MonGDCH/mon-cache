# mon-cache

一个基于 `PSR-16` 实现的缓存库，内置 `File`、`Redis` 缓存驱动，支持自定义扩展缓存驱动，并提供 Gaia 框架的 `Sqlite`、`Rdb` 扩展驱动与缓存服务保活机制。

## 环境要求

- PHP >= 8.0
- psr/simple-cache ^3.0
- ext-redis（使用 Redis 驱动时需要）
- mongdch/mon-think-orm（使用 Sqlite 扩展驱动时需要）

## 安装

```bash
composer require mongdch/mon-cache
```

## 快速使用

```php
use mon\cache\Cache;
use mon\cache\drivers\File;
use mon\cache\drivers\Redis;

$config = [
    // 默认缓存驱动
    'default'   => 'file',
    // 缓存驱动
    'stores'    => [
        // 文件缓存
        'file'  => [
            // 驱动器
            'driver'        => File::class,
            // 默认缓存有效时间，0为永久
            'expire'        => 0,
            // 使用子目录保存
            'cache_subdir'  => false,
            // 缓存前缀
            'prefix'        => '',
            // 缓存路径
            'path'          => __DIR__ . '/cache',
            // 数据压缩
            'data_compress' => false,
        ],
        // Redis缓存
        'redis' => [
            // 驱动器
            'driver'        => Redis::class,
            // 链接host
            'host'          => '127.0.0.1',
            // 链接端口
            'port'          => 6379,
            // 链接密码
            'auth'          => '',
            // 自定义键前缀
            'prefix'        => '',
            // redis数据库
            'database'      => 1,
            // 链接及读取超时时间，单位秒
            'timeout'       => 2,
            // 默认缓存有效时间，0为永久
            'expire'        => 0,
            // 持久链接
            'persistent'    => false,
        ]
    ]
];

// 获取缓存实例
$cache = new Cache($config);
```

## 方法说明

`Cache` 类的方法会通过 `__call` 代理到默认缓存驱动，可使用 `store($type)` 切换驱动实例：

```php
// 设置缓存，$ttl为null时使用配置的默认expire，0为永久
$cache->set('key', 'value', 60);

// 获取缓存，不存在或已过期时返回默认值
$data = $cache->get('key', 'default');

// 是否存在缓存（只判断存在性与有效期，值为0、''、false等假值时依然返回true）
$has = $cache->has('key');

// 删除缓存，key不存在时同样返回true
$del = $cache->delete('key');

// 清空当前驱动的全部缓存
$clear = $cache->clear();

// 批量设置，$default支持字符串或按key映射的数组
$cache->setMultiple(['a' => 1, 'b' => 2], 60);

// 批量获取
$data = $cache->getMultiple(['a', 'b', 'c'], ['c' => 'aaa']);
$data = $cache->getMultiple(['a', 'b', 'c'], 'default');

// 批量删除
$cache->deleteMultiple(['a', 'b']);

// 读取并删除
$value = $cache->pull('key', 'default');

// 自增/自减，$ttl为null时保留原剩余有效期（新key使用配置默认expire）
$cache->set('cnt', 5, 100);
$cache->inc('cnt');        // 6
$cache->inc('cnt', 10);    // 16
$cache->dec('cnt', 2);     // 14

// 获取剩余有效期（秒），0为永久，key不存在返回null
$ttl = $cache->store()->getTtl('cnt');
```

> **注意**：`inc` / `dec` / `pull` 为「读取-写回」模式实现，非原子操作，高并发计数场景建议使用 Redis 原生命令（可通过 `handler()` 获取驱动实例）。

### 驱动管理

```php
// 获取指定驱动实例（第二个参数可传入临时配置，需配合$reset使用）
$fileDriver  = $cache->store('file');
$redisDriver = $cache->store('redis');

// 获取Redis原生对象
$redis = $redisDriver->handler();

// 扩展自定义驱动，驱动需实现 mon\cache\CacheInterface
$cache->extend('my_driver', [
    'driver' => MyDriver::class,
]);

// 设置默认驱动
$cache->setDefaultDriver('my_driver');
```

## 驱动配置说明

### File 驱动

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| driver | `File::class` | 驱动器类名 |
| expire | `0` | 默认缓存有效时间，0为永久 |
| path | `''`（必填） | 缓存文件保存路径，不存在时自动创建 |
| cache_subdir | `false` | 是否使用子目录保存（取key摘要前两位分片） |
| prefix | `''` | 缓存前缀（作为子目录） |
| data_compress | `false` | 是否启用gzcompress数据压缩 |

存储格式为：`12位有效期 + 序列化数据`，文件后缀为 `.cache`，不再使用PHP文件格式。写入采用「临时文件 + 排它锁 + 原子重命名」，多进程并发读写安全。

### Redis 驱动

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| driver | `Redis::class` | 驱动器类名 |
| expire | `0` | 默认缓存有效时间，0为永久 |
| host | `127.0.0.1` | 链接host |
| port | `6379` | 链接端口 |
| auth | `''` | 链接密码 |
| prefix | `''` | 自定义键前缀（通过 `OPT_PREFIX` 实现） |
| database | `1` | redis数据库，0时不执行select |
| timeout | `2` | 链接及读取超时时间，单位秒 |
| persistent | `false` | 是否持久链接 |

## 自定义扩展驱动

```php
use mon\cache\Cache;
use mon\cache\CacheInterface;

class MyDriver implements CacheInterface
{
    public function __construct(array $config = [])
    {
        // 初始化配置
    }

    public function ping() {}

    public function get(string $key, mixed $default = null): mixed
    {
        // 读取缓存
    }

    public function set(string $key, mixed $value, mixed $ttl = null): bool
    {
        // 写入缓存
    }

    public function has(string $key): bool
    {
        // 判断是否存在
    }

    public function delete(string $key): bool
    {
        // 删除缓存
    }

    public function clear(): bool
    {
        // 清空缓存
    }

    public function getMultiple(array $keys, $default = null): array {}
    public function setMultiple(array $values, $ttl = null): bool {}
    public function deleteMultiple(array $keys): bool {}
}
```

> **建议**：`unserialize` 反序列化时请使用 `unserialize($value, ['allowed_classes' => false])`，避免PHP对象注入风险；文件类驱动请勿将缓存文件以 `.php` 等可执行后缀保存。

## Gaia 框架支持

`composer` 安装后执行 `php gaia vendor:publish mon\cache` 即可发布配置与扩展驱动文件，包含：

| 发布文件 | 说明 |
| --- | --- |
| config/cache.php | 缓存配置文件 |
| support/cache/CacheService.php | 缓存服务，支持 Workerman 环境下的连接保活 |
| support/cache/extend/Sqlite.php | Sqlite 缓存驱动（依赖 mongdch/mon-think-orm） |
| support/cache/extend/Rdb.php | 复用 Gaia 内置 Redis 链接的缓存驱动 |

### 服务保活（keep_alive）

针对 Workerman 常驻内存进程，`CacheService` 支持定时 Ping 保活，异常时自动重建缓存服务：

```php
'keep_alive' => [
    // 是否启动，仅在Workerman环境下生效
    'enable'    => env('CACHE_KEEP', true),
    // 定时Ping通服务，单位秒，0则不定时Ping
    'ping'      => env('CACHE_PING', 55),
    // 最大自动重启服务次数，超过后停止保活并触发异常事件
    'reset_max' => env('CACHE_RESET', 3),
    // 异常事件名称，可通过 mon\util\Event 监听处理
    'event'     => 'cache_error'
],
```

## 升级说明（安全修复版本）

近期版本对 File 驱动做了安全加固，升级前请确认以下事项：

1. **存储格式变更**：File 驱动缓存文件由 `.php`（含PHP标签包裹）变更为 `.cache`（`12位有效期 + 序列化数据`），旧缓存文件升级后无法读取，会在下次写入时自动重建；`clear()` 会同时清理旧版 `.php` 缓存文件
2. **反序列化限制**：`unserialize` 默认禁止还原对象（`allowed_classes => false`），如业务存在「缓存对象后再取出使用」的场景需自行调整
3. **`has()` 行为修正**：缓存值为 `0`、`''`、`false` 等假值时 `has()` 现在正确返回 `true`（旧版本会误判为不存在）
4. **`inc`/`dec` 行为修正**：未指定 `$ttl` 时不再把原key重置为永久/默认有效期，而是保留剩余有效期

## License

MIT
