<?php

declare(strict_types=1);

namespace support\cache\extend;

use Throwable;
use mon\thinkORM\Db;
use mon\cache\Traits;
use think\db\PDOConnection;
use mon\cache\CacheInterface;
use mon\cache\exception\CacheException;

/**
 * Sqlite作为缓存存储
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Sqlite implements CacheInterface
{
    use Traits;

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 默认缓存有效时间
        'expire'        => 0,
        // 缓存的表
        'table'         => 'mon_cache',
        // 数据库名
        'database'      => '',
        // 是否开启SQL监听
        'trigger_sql'   => false
    ];

    /**
     * 数据库链接
     *
     * @var PDOConnection
     */
    protected $db;

    /**
     * 构造方法
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->init();
        try {
            $this->optimize();
        } catch (Throwable $e) {
            // 多进程并发启动时可能短暂锁库，过期数据清理失败不影响服务可用性
        }
        $this->last_optimize = time();
    }

    /**
     * 上次清理过期数据的时间
     *
     * @var integer
     */
    protected $last_optimize = 0;

    /**
     * 过期数据清理间隔，单位秒
     *
     * @var integer
     */
    protected $optimize_interval = 300;

    /**
     * 按间隔清理过期数据
     *
     * 常驻进程长期运行不会重新实例化驱动，需要运行期间周期性清理过期数据，
     * 否则过期数据会持续堆积导致表体积和维护成本上涨。
     *
     * @return void
     */
    protected function maybeOptimize()
    {
        $now = time();
        if ($now - $this->last_optimize < $this->optimize_interval) {
            return;
        }
        $this->last_optimize = $now;
        try {
            $this->optimize();
        } catch (Throwable $e) {
            // 清理失败不影响正常读写
        }
    }

    /**
     * 获取缓存内容
     *
     * @param  string $key    名称
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $now = time();
        $value = $this->getDb()->table($this->config['table'])->where('key', $key)->where("`expire` = 0 OR `expire` >= {$now}")->value('value');
        // 只判断未查询到，避免缓存了空字符串等假值时误判为不存在
        if (is_null($value) || $value === false) {
            return $default;
        }
        $result = $this->unserialize($value);
        if ($this->isUnserializeFailed($result, $value)) {
            // 数据损坏，删除后按未命中处理
            $this->getDb()->table($this->config['table'])->where('key', $key)->delete();
            return $default;
        }

        return $result;
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
        // PSR-16 允许传入 iterable，统一转为数组，避免 Generator 被重复迭代
        if (!is_array($keys)) {
            $keys = iterator_to_array($keys, false);
        }
        $now = time();
        $data = $this->getDb()->table($this->config['table'])->where('key', 'in', $keys)->where("`expire` = 0 OR `expire` >= {$now}")->column('value', 'key');
        $values = [];
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $value = $this->unserialize($data[$key]);
                // 数据损坏时按未命中处理
                $values[$key] = $this->isUnserializeFailed($value, $data[$key]) ? null : $value;
            } elseif (is_array($default)) {
                // 与File/Redis驱动对齐，支持数组按key映射默认值
                $values[$key] = isset($default[$key]) ? $default[$key] : null;
            } else {
                $values[$key] = $default;
            }
        }

        return $values;
    }

    /**
     * 设置缓存
     *
     * @param  string   $key    名称
     * @param  mixed    $value  值
     * @param  integer  $ttl    有效时间
     * @return boolean
     */
    public function set($key, $value, $ttl = null): bool
    {
        $this->maybeOptimize();
        $now = time();
        $ttl = $this->ttlToSeconds($ttl);
        $ttl = is_null($ttl) ? (int) $this->config['expire'] : $ttl;
        $expire = $ttl > 0 ? $now + $ttl : 0;
        $save = $this->getDb()->table($this->config['table'])->replace(true)->insert([
            'key'    => $key,
            'value'  => $this->serialize($value),
            'expire' => $expire,
        ]);

        return $save ? true : false;
    }

    /**
     * 批量设置缓存
     *
     * @param array $values 关联数组作为缓存的键值
     * @param integer $ttl  有效时间，0为永久
     * @return boolean
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $this->maybeOptimize();
        $now = time();
        $ttl = $this->ttlToSeconds($ttl);
        $ttl = is_null($ttl) ? (int) $this->config['expire'] : $ttl;
        $expire = $ttl > 0 ? $now + $ttl : 0;
        $saveData = [];
        foreach ($values as $key => $value) {
            $saveData[] = [
                'key'    => $key,
                'value'  => $this->serialize($value),
                'expire' => $expire,
            ];
        }
        $save = $this->getDb()->table($this->config['table'])->replace(true)->insertAll($saveData);
        return $save ? true : false;
    }

    /**
     * 删除缓存
     *
     * @param  string $key 名称
     * @return bool
     */
    public function delete($key): bool
    {
        return $this->getDb()->table($this->config['table'])->where('key', $key)->delete() !== false;
    }

    /**
     * 批量删除缓存
     *
     * @param array $keys   缓存变量名一维数组
     * @return boolean
     */
    public function deleteMultiple($keys): bool
    {
        // PSR-16 允许传入 iterable，统一转为数组
        if (!is_array($keys)) {
            $keys = iterator_to_array($keys, false);
        }
        if (empty($keys)) {
            return true;
        }

        return $this->getDb()->table($this->config['table'])->where('key', 'in', $keys)->delete() !== false;
    }

    /**
     * 清空缓存
     *
     * @return boolean
     */
    public function clear(): bool
    {
        return $this->getDb()->table($this->config['table'])->where('1 = 1')->delete() !== false;
    }

    /**
     * 优化缓存
     *
     * @return void
     */
    public function optimize()
    {
        $this->getDb()->table($this->config['table'])->where('expire', '>', 0)->where('expire', '<', time())->delete();
    }

    /**
     * 是否存在缓存
     *
     * @param  string  $key 名称
     * @return boolean
     */
    public function has($key): bool
    {
        $now = time();
        // 使用COUNT判断存在性，避免值为假值（0、''、false等）时误判为不存在
        $count = $this->getDb()->table($this->config['table'])->where('key', $key)->where("`expire` = 0 OR `expire` >= {$now}")->count('key');

        return $count > 0;
    }

    /**
     * Ping
     *
     * @return mixed
     */
    public function ping()
    {
        $this->getDb()->query('SELECT 1');
    }

    /**
     * 获取Db链接
     *
     * @return PDOConnection
     */
    public function getDb(): PDOConnection
    {
        if (!$this->db) {
            // SQLite 不会自动创建父级目录，连接前先确保目录存在
            $this->ensureDatabaseDir();
            $this->db = Db::configConnect([
                // 数据库类型
                'type'      => 'sqlite',
                // 数据库名
                'database'  => $this->config['database'],
                // 数据库连接参数
                'params'    => [\PDO::ATTR_TIMEOUT => 3],
                // 断线重连
                'break_reconnect'   => true,
                // 是否开启SQL监听，默认关闭，如需要开启，则需要调用 Db::setLog 注入日志记录对象，否则常驻进程长期运行会爆内存
                'trigger_sql'       => $this->config['trigger_sql'],
            ]);
        }
        return $this->db;
    }

    /**
     * 确保缓存数据库文件所在目录存在
     *
     * SQLite 允许数据库文件不存在（连接时自动创建），但父级目录必须存在，
     * 否则会抛出 SQLSTATE[HY000] [14] unable to open database file。
     *
     * @throws CacheException
     * @return void
     */
    protected function ensureDatabaseDir()
    {
        $database = (string)$this->config['database'];
        // 未配置数据库(临时库)与内存库(:memory:)无需目录
        if ($database === '' || strpos($database, ':memory:') === 0 || strpos($database, 'file:') === 0) {
            return;
        }

        $dir = dirname($database);
        if ($dir === '' || $dir === '.' || is_dir($dir)) {
            return;
        }

        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new CacheException('Init Sqlite Cache Dir Error: Cannot create directory ' . $dir . ', please check the permission of `database` config');
        }
    }

    /**
     * 初始化
     *
     * @return void
     */
    protected function init()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS "main"."{$this->config['table']}" (
    "key" TEXT NOT NULL,
    "value" TEXT NOT NULL DEFAULT '',
    "expire" integer NOT NULL DEFAULT 0,
    PRIMARY KEY ("key")
);
SQL;
        try {
            // 创建缓存表，使用 IF NOT EXISTS 避免多进程（Workerman 子进程并发启动）建表的竞态
            $this->getDb()->execute($sql);
        } catch (Throwable $e) {
            // 极端并发下仍可能因竞态命中已存在错误，视为初始化成功
            if (stripos($e->getMessage(), 'already exists') !== false) {
                return;
            }
            throw new CacheException('Init Sqlite Cache Table Error: ' . $e->getMessage() . ' [database: ' . $this->config['database'] . ']', 0, $e);
        }
    }
}
