<?php

use mon\cache\Cache;

require __DIR__ . '/../vendor/autoload.php';

// 文件缓存配置
$fileConfig = [
    // 驱动类型
    'driver'        => 'file',
    // 使用子目录保存
    'cache_subdir'  => false,
    // 缓存路径
    'path'          => __DIR__ . '/cache',
    // 数据压缩
    'data_compress' => false,
    // 自定义键前缀
    'prefix'        => '',
    // 默认缓存有效时间
    'expire'        => 0,
];

// Redis缓存配置
$redisConfig = [
    // 驱动类型
    'driver'    => 'redis',
    // 链接host
    'host'      => '192.168.88.101',
    // 链接端口
    'port'      => 6379,
    // 链接密码
    'auth'      => 'redis123456',
    // 读取超时时间
    'timeout'   => 2,
    // 自定义键前缀
    'prefix'    => '',
    // 默认缓存有效时间
    'expire'    => 300,
    // redis数据库
    'database'  => 2,
    // 保持链接
    'persistent' => false,
];

// 获取缓存实例
$cache = new Cache($redisConfig);

// 设置
// $set = $cache->set('ab', 'aaaab');
// var_dump($set);
// $set = $cache->set('abc', 'aaaab', 10);
// var_dump($set);

// 批量设置
// $mSet = $cache->setMultiple(['a' => 1, 'b' => '2', 'c' => -3, 'd' => 4, 'e' => 'abc']);
// var_dump($mSet);

// 获取
$data = $cache->get('ab', 'cd');
var_dump($data);

// 批量获取
// $mGet = $cache->getMultiple(['a', 'b', 'c', 'd'], ['d' => 'aaa']);
// $mGet = $cache->getMultiple(['a', 'b', 'c', 'd'], 'default');
// var_dump($mGet);

// 是否存在
// $has = $cache->has('abc');
// var_dump($has);

// 删除
// $del = $cache->delete('b');
// var_dump($del);

// 批量删除
// $mDel = $cache->deleteMultiple(['a', 'd', 'ab']);
// var_dump($mDel);

// 清空缓存
// $clear = $cache->clear();
// var_dump($clear);

// $pull = $cache->pull('b');
// var_dump($pull);


// $inc = $cache->dec('b', 3);
// var_dump($inc);