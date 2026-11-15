<?php

declare(strict_types=1);

namespace mon\cache;

use DateInterval;
use DateTimeImmutable;
use mon\cache\exception\InvalidArgumentException;

/**
 * 公共辅助业务方法
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
trait Traits
{
    /**
     * 序列化值
     *
     * @param mixed $value
     * @return string
     */
    protected function serialize($value): string
    {
        return serialize($value);
    }

    /**
     * 反序列化值
     *
     * @param string $value
     * @return mixed
     */
    protected function unserialize(string $value)
    {
        // 禁止反序列化为对象，避免存储介质被篡改时的PHP对象注入风险
        // 使用 @ 抑制损坏数据触发的 E_NOTICE，损坏情况由调用方通过 isUnserializeFailed 判断
        return @unserialize($value, ['allowed_classes' => false]);
    }

    /**
     * 反序列化结果是否代表数据损坏
     *
     * unserialize 失败时返回 false，而合法的 false 值序列化后为 'b:0;'，需区分两者，
     * 避免存储介质中的数据损坏时被当作正常的 false 值返回。
     *
     * @param mixed  $result  反序列化结果
     * @param string $value   原始序列化数据
     * @return boolean
     */
    protected function isUnserializeFailed($result, string $value): bool
    {
        return false === $result && 'b:0;' !== $value;
    }

    /**
     * 校验并转换有效期为秒数
     *
     * 遵循 PSR-16，支持 int、DateInterval、null(使用配置默认值，由调用方处理)，
     * 非法类型或负数有效期抛出 InvalidArgumentException。
     *
     * @param mixed $ttl
     * @throws InvalidArgumentException
     * @return integer|null 秒数，0为永久，null表示未指定
     */
    protected function ttlToSeconds($ttl): ?int
    {
        if (is_null($ttl)) {
            return null;
        }
        if ($ttl instanceof DateInterval) {
            $now = new DateTimeImmutable();
            $seconds = $now->add($ttl)->getTimestamp() - $now->getTimestamp();
            if ($seconds < 0) {
                throw new InvalidArgumentException('Cache TTL must be a non-negative interval');
            }

            return $seconds;
        }
        if (!is_numeric($ttl)) {
            throw new InvalidArgumentException('Cache TTL must be an integer, a DateInterval or null');
        }

        $ttl = (int) $ttl;
        if ($ttl < 0) {
            throw new InvalidArgumentException('Cache TTL must be a non-negative integer');
        }

        return $ttl;
    }
}
