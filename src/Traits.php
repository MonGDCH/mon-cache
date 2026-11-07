<?php

declare(strict_types=1);

namespace mon\cache;

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
        return unserialize($value, ['allowed_classes' => false]);
    }
}
