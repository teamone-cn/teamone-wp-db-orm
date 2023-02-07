<?php

namespace Teamone\TeamoneWpDbOrmTest\Config;

use Teamone\TeamoneWpDbOrm\Capsule\DatabaseConfigContract;

class DatabaseConfig implements DatabaseConfigContract
{
    /**
     * @desc 数据库连接配置
     * @return array
     */
    public function getConnectionConfig()
    {
        $config = [
            'default'     => 'ali-rds',
            'connections' => [
                'ali-rds'     => [
                    'name'      => 'ali-rds',
                    'read'      => [
                        'host' => [
                            '192.168.10.47',
                            '192.168.10.47',
                        ],
                    ],
                    // 读写
                    'write'     => [
                        'host' => [
                            '192.168.10.47',
                        ],
                    ],
                    'driver'    => 'mysql',
                    'port'      => 3306,
                    'database'  => 'blog',
                    'username'  => 'root',
                    'password'  => '123456',
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix'    => 'th_',
                ],
                'tencent-rds' => [
                    'name'      => 'tencent-rds',
                    'read'      => [
                        'host' => [
                            '192.168.10.47',
                            '192.168.10.47',
                        ],
                    ],
                    // 读写
                    'write'     => [
                        'host' => [
                            '192.168.10.47',
                        ],
                    ],
                    'driver'    => 'mysql',
                    'port'      => 3306,
                    'database'  => 'blog',
                    'username'  => 'root',
                    'password'  => '123456',
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix'    => 'th_',
                ],
            ],
        ];

        return $config;
    }
}
