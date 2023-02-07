<?php

namespace Teamone\TeamoneWpDbOrm\Capsule;

interface DatabaseConfigContract
{
    /**
     * @desc 数据库连接配置
     * @return array
     */
    public function getConnectionConfig();
}
