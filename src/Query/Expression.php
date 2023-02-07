<?php

namespace Teamone\TeamoneWpDbOrm\Query;

class Expression
{
    /**
     * @var mixed 表达式的值
     */
    protected $value;

    /**
     * @desc 创建一个新的原始查询表达式
     * @param  mixed  $value
     * @return void
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed 获取表达式的值
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string 获取表达式的值
     */
    public function __toString()
    {
        return (string) $this->getValue();
    }
}
