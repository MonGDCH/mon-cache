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
    'host'      => '127.0.0.1',
    // 链接端口
    'port'      => 6379,
    // 链接密码
    'password'  => '',
    // 读取超时时间
    'timeout'   => 0,
    // 自定义键前缀
    'prefix'    => '',
    // 默认缓存有效时间
    'expire'    => 0,
];

// 获取缓存实例
$cache = new Cache($redisConfig);

// 设置
$set = $cache->set('ab', 'aaaab');
$set = $cache->set('abc', 'aaaab', 10);

// 批量设置
$mSet = $cache->setMultiple(['a' => 1, 'b' => '2', 'c' => 3]);

// 获取
// $data = $cache->get('ab', 'd');

// 批量获取
// $mGet = $cache->getMultiple(['a', 'b', 'c', 'd'], ['c' => 'aaa']);
// $mGet = $cache->getMultiple(['a', 'b', 'c', 'd'], 'default');

// 是否存在
// $has = $cache->has('ab');

// 删除
// $del = $cache->delete('b');

// 批量删除
$mDel = $cache->deleteMultiple(['a', 'd']);

// 清空缓存
// $clear = $cache->clear();

// 获取并删除
// $pull = $cache->pull('ab', 'def');

var_dump($mDel);
