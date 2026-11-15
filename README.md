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
// $ttl 支持 int、DateInterval、null；传入负数或非法类型会抛出 InvalidArgumentException
$cache->set('key', 'value', 60);
$cache->set('key', 'value', new DateInterval('PT1H'));

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

> **注意**：`inc` / `dec` / `pull` 为「读取-写回」模式实现，非原子操作，高并发计数场景建议使用 Redis 原生命令（可通过 `handler()` 获取驱动实例）。`inc` / `dec` 对不存在的 key 或非数字值返回 `false`，不会自动创建 key。

> **说明**：所有驱动对读到损坏数据（反序列化失败、解压失败）的处理一致——删除该缓存项并按「未命中」返回默认值，不会把 `false` 之类的脏值返回给业务。

### 驱动管理

```php
// 获取指定驱动实例
$fileDriver  = $cache->store('file');

// 第二个参数可传入临时配置：与当前驱动实例配置不一致时会自动重建驱动（也可显式传 $reset = true 强制重建）
$tmpDriver   = $cache->store('file', ['driver' => File::class, 'path' => '/tmp/cache']);
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

补充说明：

- 读取操作（`get`/`has`/`getTtl`/`delete`）每次都会清理该文件的 stat 缓存，**常驻进程（Workerman/Swoole）中其他进程改写缓存后能立刻感知**，不会因为 PHP 进程级 stat 缓存而误判过期或误删新数据。
- `has()`/`getTtl()` 只读取文件头 12 字节判断有效期，不读取完整内容。
- 过期文件采用「读取时删除」的懒清理策略，写入多读取少的 key 会残留占用磁盘，可在定时任务中调用 `$driver->gc()` 主动回收（返回清理数量）；`clear()` 会一并清理旧版 `.php` 遗留文件与写入中断残留的 `.tmp` 文件。
- `gc()` 会同步扫描缓存目录并逐文件读取，缓存文件数量极大时会阻塞常驻进程的事件循环，建议在业务低峰期调用或分批清理。
- 支持 `gc()` 的驱动为 File；Redis/Sqlite 依靠自身的 TTL/过期字段机制。

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

> **`clear()` 行为**：以连接上实际生效的 `OPT_PREFIX` 为准——存在前缀时通过 `SCAN + DEL` 只删除该前缀下的缓存键（复用外部连接的 `rdb` 驱动同样按外部连接的前缀处理）；**未配置前缀时等价于 `flushDB()`，会清空当前 database 的所有键**（包括其他业务写入的数据）。因此与其他业务共用同一个 database 时，请务必配置独立的 `prefix`。

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
        // 写入缓存，$ttl 支持 null(使用默认配置)、int、DateInterval
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

    // 注意：PSR-16 v3 中 $keys/$values 为 iterable、$ttl 为 DateInterval|int|null，
    // 实现时不要用 array 收窄参数类型，否则会触发 Fatal error（参数类型不兼容）
    public function getMultiple($keys, $default = null): array {}
    public function setMultiple($values, $ttl = null): bool {}
    public function deleteMultiple($keys): bool {}
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

保活行为说明：

- 未提供 `keep_alive` 配置时使用内置默认值（`enable=true`、`ping=55`、`reset_max=3`），不会因为配置缺失导致服务初始化失败。
- `ping = 0` 时不注册定时器。
- 每次 Ping 会遍历**所有已建立连接的驱动**（不只是默认驱动），任一驱动异常即触发 `cache_error` 事件。
- `err_count` 超过 `reset_max` 后停止保活；成功 Ping 一次会清零计数。重复调用 `keep()` 会先移除旧定时器，重新从 0 计数。

## 升级说明（安全修复版本）

近期版本对 File 驱动做了安全加固，升级前请确认以下事项：

1. **存储格式变更**：File 驱动缓存文件由 `.php`（含PHP标签包裹）变更为 `.cache`（`12位有效期 + 序列化数据`），旧缓存文件升级后无法读取，会在下次写入时自动重建；`clear()` 会同时清理旧版 `.php` 缓存文件
2. **反序列化限制**：`unserialize` 默认禁止还原对象（`allowed_classes => false`），如业务存在「缓存对象后再取出使用」的场景需自行调整
3. **`has()` 行为修正**：缓存值为 `0`、`''`、`false` 等假值时 `has()` 现在正确返回 `true`（旧版本会误判为不存在）
4. **`inc`/`dec` 行为修正**：未指定 `$ttl` 时不再把原key重置为永久/默认有效期，而是保留剩余有效期
5. **TTL 校验**：`$ttl` 传入负数或非法类型时抛出 `InvalidArgumentException`（遵循 PSR-16），同时新增 `DateInterval` 支持。若历史代码依赖负数 TTL 表达「立即过期」，请改为直接删除 key
6. **损坏数据不再返回脏值**：反序列化/解压失败时删除该缓存项并返回默认值（旧版会返回 `false`）
7. **配置改为递归合并**：`stores` 下各驱动的配置支持只覆盖部分键（如只传 `path`），不再整体替换导致 `driver` 等默认配置丢失
8. **`store($type, $config)` 语义**：传入的临时配置与当前驱动实例不一致时会自动重建驱动，不再静默忽略（相同配置仍复用实例，无额外连接开销）
9. **`clear()` 范围收紧**：Redis 配置了 `prefix` 时只删除该前缀下的键，不再无条件 `flushDB`
10. **常驻进程保活修复**：配置缺失时使用内置默认值不再报错；`ping = 0` 不启动定时器；重复调用 `keep()` 不会叠加定时器；保活会 Ping 所有已建立连接的驱动

### Workerman 环境注意事项

- **服务实例化时机**：`CacheService` 的保活定时器在实例化时注册，请在 `onWorkerStart`（子进程）中首次使用，避免在 master 进程/启动引导阶段实例化导致定时器注册在错误的进程。
- **`rdb` 驱动（复用 Gaia 内置 Redis 连接）**：其连接由外部服务持有，保活重启只会重建 `Cache` 对象，**不会重建底层连接**。若使用该驱动，需监听 `cache_error` 事件自行重建底层连接。
- **`inc` / `dec` 非原子**：多进程并发计数请使用 Redis 原生命令。
- **Sqlite 驱动**：建表使用 `CREATE TABLE IF NOT EXISTS`，多进程并发启动安全；过期数据在实例化时清理，并在运行期间按 300 秒间隔自动清理。

## License

MIT
