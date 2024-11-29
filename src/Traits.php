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
        return unserialize($value);
    }
}
