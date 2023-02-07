<?php

namespace Teamone\TeamoneWpDbOrmTest\Unit;

use Teamone\TeamoneWpDbOrm\Capsule\Manager as DB;
use Teamone\TeamoneWpDbOrm\Pagination\Cursor;
use Teamone\TeamoneWpDbOrm\Pagination\CursorPaginator;
use Teamone\TeamoneWpDbOrm\Pagination\PaginationState;
use Teamone\TeamoneWpDbOrmTest\Config\DatabaseConfig;
use Teamone\TeamoneWpDbOrmTest\ConnectTrait;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    use ConnectTrait;

    /** 执行原生 SQL 查询 ***********************************/

    /**
     * @desc SELECT
     */
    public function test01()
    {
        $users = self::db()->select('SELECT * FROM `th_users` WHERE `status` = ?', [1]);

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc INSERT
     */
    public function test02()
    {
        $result = self::db()->insert('INSERT INTO `th_users`(`name`, `email`) VALUES (?, ?)', ['牛魔王', '13812344321@qq.com']);

        $this->assertIsBool($result);
    }

    /**
     * @desc UPDATE
     */
    public function test03()
    {
        $result = self::db()->update('UPDATE `th_users` SET `status` = 3 WHERE `name` = ?', ['白龙马']);

        $this->assertIsInt($result);
    }

    /**
     * @desc DELETE
     */
    public function test04()
    {
        $result = self::db()->delete('DELETE FROM `th_users` WHERE `id` = ?', [14]);

        $this->assertIsInt($result);
    }

    /**
     * @desc 执行普通查询
     * 部分数据库语句没有返回值。你可以使用statement 方法：
     */
    public function test05()
    {
        // self::db()->statement('drop table users');
        $result = self::db()->statement('SET NAMES utf8mb4');

        $this->assertIsBool($result);
    }

    /**
     * @desc 数据库事务
     * 记住一点，不要嵌套使用
     */
    public function test06()
    {
        $db = self::db();

        // 开始事务
        $db->beginTransaction();
        try {
            $result1 = self::db()->update('UPDATE `th_users` SET `status` = 3 WHERE `name` = ?', ['白龙马']);

            if ( !$result1) {
                throw new \Exception();
            }

            $result2 = self::db()->update('UPDATE `th_users` SET `status` = 4 WHERE `name` = ?', ['八戒']);

            if ( !$result2) {
                throw new \Exception();
            }

            // 提交事务
            $db->commit();
        } catch (\Exception $e) {
            // 回滚事务
            $db->rollBack();
        }

        $this->assertTrue(true);
    }

    /**
     * @desc Exists 判断记录是否存在
     */
    public function test07()
    {
        // Exists
        $result = self::db()->table('users')->where('is_disable', 1)->exists();
        $this->assertIsBool($result);

        $result = self::db()->table('users')->select(['id', 'name'])->where('name', '朱世杰')->existsOr(function (){
            var_dump('hello');
        });
        $this->assertIsBool($result);
    }

    /**
     * @desc doesntExist 判断记录是否存在
     */
    public function test08()
    {
        $result = self::db()->table('users')->where('is_disable', 1)->doesntExist();
        $this->assertIsBool($result);

        $result = self::db()->table('users')->select(['id', 'name'])->where('name', '朱世杰')->doesntExistOr(function (){
            var_dump('world');
        });
        $this->assertIsBool($result);
    }

    /**
     * @desc 分页
     */
    public function test09()
    {
        $users = self::db()->table('users')->paginate();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 简单分页
     */
    public function test09v2()
    {
        $users = self::db()->table('users')->simplePaginate();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 游标分页
     */
    public function test10()
    {
        $users = self::db()->table('users')->orderBy('id')->cursorPaginate(2);

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 分页参数
     */
    public function test11()
    {
        $perPage  = 5;
        $columns  = ['*'];
        $pageName = 'page';
        $page     = null;

        $users = self::db()->table('users')->paginate($perPage, $columns, $pageName, $page);

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 使用游标
     */
    public function test12()
    {
        $cursor     = new Cursor(['id' => 0], true);
        $perPage    = 5;
        $columns    = ['*'];
        $cursorName = 'cursor';
        $users      = self::db()->table('users')->orderBy('id')->cursorPaginate($perPage, $columns, $cursorName, $cursor);
        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 使用游标
     */
    public function test13()
    {
        PaginationState::resolveUsing();

        // 游标
        $cursor = new Cursor(['id' => 0], true);

        /** @var CursorPaginator $cursorPaginate */
        $cursorPaginate = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->cursorPaginate(3, ['*'], 'cursor', $cursor);

        // 下个游标
        $nextCursor = $cursorPaginate->nextCursor();

        /** @var CursorPaginator $cursorPaginate */
        $cursorPaginate = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->cursorPaginate(3, ['*'], 'cursor', $nextCursor);

        $this->assertTrue(true);
    }

    /**
     * @desc 惰性查询
     */
    public function test14()
    {
        // 返回的是生成器，当迭代完一个块时，进入下个块迭代才进行实际查询
        $users = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->lazyById(3);

        $i = 1;
        foreach ($users as $item) {
            print_r($i);
            print_r($item);

            $i++;
            if ($i > 3) {
                break;
            }
        }

        $this->assertTrue(true);
    }

    /**
     * @desc 惰性查询
     */
    public function test15()
    {
        $users = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->lazy(3);

        $i = 1;
        foreach ($users as $item) {
            print_r($i);
            print_r($item);

            $i++;
            if ($i > 6) {
                break;
            }
        }

        $this->assertTrue(true);
    }

    /**
     * @desc 使用 ID 分页
     */
    public function test16()
    {
        $users = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->forPageBeforeId(3, 10)->get();
        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 将给定列的值连接为字符串
     */
    public function test17()
    {
        $idString = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->limit(3)->implode('id', ',');

        $this->assertIsString($idString);
    }

    /**
     * @desc 指定连接、表前缀、表名
     */
    public function test18()
    {
        DB::resolverDatabaseConfig(DatabaseConfig::class);

        // 连接实例
        $db = DB::connection('ali-rds');
        // 开启日志
        $db->enableQueryLog();
        // 执行查询
        $orders = $db->setTablePrefix('mrz_')->table('orders')
            ->select('id', 'order_number')->where('id', 1)->get();
        print_r($orders);
        // 查询日志
        $logs = $db->getQueryLog();
        print_r($logs);

        // -----------------

        // 连接实例
        $db = DB::connection('tencent-rds');
        // 开启日志
        $db->enableQueryLog();
        // 执行查询
        $orders = $db->setTablePrefix('th_')->table('orders')
            ->select('id', 'order_number')->where('id', 1)->get();
        print_r($orders);
        // 查询日志
        $logs = $db->getQueryLog();
        print_r($logs);

        $this->assertTrue(true);
    }

    public function test19()
    {
        echo __FUNCTION__ . "\r\n";
        $this->test01();
        $this->test02();
        $this->test03();
        $this->test04();
    }
}
