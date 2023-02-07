<?php

namespace Teamone\TeamoneWpDbOrm;

interface ConnectionResolverInterface
{
    /**
     * @desc 获取一个数据库连接实例
     * @param string|null $name
     * @return Connection
     */
    public function connection($name = null);

    /**
     * @desc 获取默认连接名
     * @return string
     */
    public function getDefaultConnection();
}
