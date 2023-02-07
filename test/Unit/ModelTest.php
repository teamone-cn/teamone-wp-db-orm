<?php

namespace Teamone\TeamoneWpDbOrmTest\Unit;

use Teamone\TeamoneWpDbOrm\Capsule\Manager as DB;
use Teamone\TeamoneWpDbOrmTest\Config\DatabaseConfig;
use Teamone\TeamoneWpDbOrmTest\ConnectTrait;
use Teamone\TeamoneWpDbOrmTest\Model\Orders;
use Teamone\TeamoneWpDbOrmTest\Model\Users;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    use ConnectTrait;

    public function initConfig()
    {
        DB::resolverDatabaseConfig(DatabaseConfig::class);
    }

    /**
     * @desc 模型类使用
     */
    public function test01()
    {
        $this->initConfig();

        $users = new Users();

        $users = $users->newQuery()->where('id', '=', 1)->get();

        print_r($users);

        $this->assertTrue(true);
    }

    /**
     * @desc 一次查询一次调用 newQuery()
     */
    public function test02()
    {
        $this->initConfig();

        $users = new Users();

        // Query 1
        $query = $users->newQuery();
        $list  = $query->select('id', 'name')->where('id', '=', 1)->get();
        print_r($list);

        // Query 2
        $query = $users->newQuery();
        $list  = $query->select('id', 'name')->where('id', '=', 2)->get();
        print_r($list);

        // Config
        print_r($query->getConnection()->getConfig());

        $this->assertTrue(true);
    }

    /**
     * @desc 选择连接
     */
    public function test03()
    {
        $this->initConfig();

        $users = new Users();

        // Query 1
        $query = $users->newQuery();
        $list  = $query->select('id', 'name')->where('id', '=', 1)->get();
        print_r($list);
        print_r($query->getConnection()->getConfig());

        // Query 2
        $query = $users->setConnection('ali-rds')->newQuery();
        $list  = $query->select('id', 'name')->where('id', '=', 1)->get();
        print_r($list);
        print_r($query->getConnection()->getConfig());

        $this->assertTrue(true);
    }

    /**
     * @desc 表前缀切换
     */
    public function test04()
    {
        $this->initConfig();

        $orders = new Orders();

        $query = $orders->newQuery();
        $list  = $query->select(['id', 'order_number'])->where('id', 1)->get();
        print_r($list);
        print_r($query->getConnection()->getConfig());
        print_r($query->toSql());

        echo "\r\n\r\n";

        // 表前缀切换
        $orders->setTablePrefix('th_');
        $query = $orders->newQuery();
        $list  = $query->select(['id', 'order_number'])->where('id', 1)->get();
        print_r($list);
        print_r($query->getConnection()->getConfig());
        print_r($query->toSql());

        $this->assertTrue(true);
    }

    /**
     * @desc 查询指定连接的查询日志
     */
    public function test05()
    {
        $orders = new Orders();

        DB::connection($orders->getConnection())->enableQueryLog();

        // 使用了模型配置的表前缀
        $query = $orders->newQuery();
        $list  = $query->select(['id', 'order_number'])->where('id', 1)->get();
        print_r($list);

        // 手动切换表前缀
        $orders->setTablePrefix('th_');
        $query = $orders->newQuery();
        $list  = $query->select(['id', 'order_number'])->where('id', 1)->get();
        print_r($list);

        $logs = DB::connection($orders->getConnection())->getQueryLog();

        print_r($logs);

        $this->assertTrue(true);
    }

    public function test06()
    {
        $this->initConfig();

        $orders = new Orders();
        $query  = $orders->newQuery();
        $list   = $query->select(['id', 'order_number'])->where('id', 1)->get();
        print_r($list);

        $users = new Users();
        $query = $users->newQuery();
        $list  = $query->select(['id', 'name'])->where('id', 1)->get();
        print_r($list);

        $this->assertTrue(true);
    }
}
