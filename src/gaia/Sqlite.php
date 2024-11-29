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
        $this->optimize();
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
        $now = time();
        $value = $this->getDb()->table($this->config['table'])->where('key', $key)->where("`expire` = 0 OR `expire` >= {$now}")->value('value');
        if (!$value) {
            return $default;
        }

        return $this->unserialize($value);
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
        $now = time();
        $data = $this->getDb()->table($this->config['table'])->where('key', 'in', $keys)->where("`expire` = 0 OR `expire` >= {$now}")->column('value', 'key');
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                $data[$key] = $default;
            } else {
                $data[$key] = $this->unserialize($data[$key]);
            }
        }
        return $data ?: [];
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
        $now = time();
        $ttl = is_null($ttl) ? $this->config['expire'] : $ttl;
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
        $now = time();
        $ttl = is_null($ttl) ? $this->config['expire'] : $ttl;
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
        $value = $this->getDb()->table($this->config['table'])->where('key', $key)->where("`expire` = 0 OR `expire` >= {$now}")->value('value');
        return $value ? true : false;
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
     * 初始化
     *
     * @return void
     */
    protected function init()
    {
        $sql = <<<SQL
CREATE TABLE "main"."{$this->config['table']}" (
    "key" TEXT NOT NULL,
    "value" TEXT NOT NULL DEFAULT '',
    "expire" integer NOT NULL DEFAULT 0,
    PRIMARY KEY ("key")
);
SQL;
        try {
            // 创建缓存表
            $tables = $this->getDb()->getTables();
            if (!in_array($this->config['table'], $tables)) {
                $this->getDb()->execute($sql);
            }
        } catch (Throwable $e) {
            throw new CacheException('Init Sqlite Cache Table Error: ' . $e->getMessage(), 0, $e);
        }
    }
}
