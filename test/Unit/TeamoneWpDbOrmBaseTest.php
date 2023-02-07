<?php

namespace Teamone\TeamoneWpDbOrmTest\Unit;

use Teamone\TeamoneWpDbOrm\Capsule\DatabaseConfigContract;
use Teamone\TeamoneWpDbOrm\Capsule\Manager as DB;
use Teamone\TeamoneWpDbOrm\Query\Builder;
use Teamone\TeamoneWpDbOrmTest\Config\DatabaseConfig;
use Teamone\TeamoneWpDbOrmTest\ConnectTrait;
use PHPUnit\Framework\TestCase;

class TeamoneWpDbOrmBaseTest extends TestCase
{
    use ConnectTrait;

    /** 数据库连接 ***********************************/

    /**
     * @desc 数据库连接，提供实现类
     */
    public function test01()
    {
        $db = DB::resolverDatabaseConfig(DatabaseConfig::class)::connection('ali-rds');

        $users = $db->table('users')->limit(2)->get();

        print_r($users);

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 数据库连接，提供匿名实现类
     */
    public function test02()
    {
        $clazz = new class() implements DatabaseConfigContract{
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
                                    'localhost',
                                    'localhost',
                                ],
                            ],
                            // 读写
                            'write'     => [
                                'host' => [
                                    'localhost',
                                ],
                            ],
                            'driver'    => 'mysql',
                            'port'      => 3306,
                            'database'  => 'blog',
                            'username'  => 'root',
                            'password'  => '123456',
                            'charset'   => 'utf8mb4',
                            'collation' => 'utf8mb4_general_ci',
                            'prefix'    => 'th_',
                        ],
                        'tencent-rds' => [
                            'name'      => 'tencent-rds',
                            'read'      => [
                                'host' => [
                                    'localhost',
                                    'localhost',
                                ],
                            ],
                            // 读写
                            'write'     => [
                                'host' => [
                                    'localhost',
                                ],
                            ],
                            'driver'    => 'mysql',
                            'port'      => 3306,
                            'database'  => 'blog',
                            'username'  => 'root',
                            'password'  => '123456',
                            'charset'   => 'utf8mb4',
                            'collation' => 'utf8mb4_general_ci',
                            'prefix'    => 'th_',
                        ],
                    ],
                ];

                return $config;
            }
        };

        $db = DB::resolverDatabaseConfig($clazz)::connection('ali-rds');

        $users = $db->table('users')->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 查询日志
     */
    public function test03()
    {
        $db = DB::resolverDatabaseConfig(DatabaseConfig::class)::connection('ali-rds');

        // 开启查询日志
        $db->enableQueryLog();

        $users = $db->table('users')->get();

        $this->assertTrue(count($users) > 0);

        // 获取查询日志
        $logs = $db->getQueryLog();

        $this->assertIsArray($logs);
    }

    /**
     * @desc 原生查询与占位符
     */
    public function test04()
    {
        $db = DB::resolverDatabaseConfig(DatabaseConfig::class)::connection('ali-rds');

        $users = $db->select('SELECT * FROM th_users WHERE `id` IN (?, ?)', [1, 3]);

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 指定连接查询(切换实例)
     */
    public function test05()
    {
        // 解析配置，只执行一次
        DB::resolverDatabaseConfig(DatabaseConfig::class);

        // 查询 1
        $users = DB::connection('ali-rds')->table('users')->select('name', 'mobile')->where('id', '>', 0)->get();
        print_r($users);

        // 查询 2
        $users = DB::connection('ali-rds')->table('users')->select('name', 'status')->where('id', '>', 0)->get();
        print_r($users);

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 分析
     */
    public function test06()
    {
        $db = DB::resolverDatabaseConfig(DatabaseConfig::class)::connection('ali-rds');

        $users = $db->table('users')->where('id', '>=', 1)->explain();

        $this->assertTrue(count($users) > 0);
    }

    /** 数据库查询 ***********************************/

    /**
     * @desc 从表中检索所有行
     */
    public function test10()
    {
        $users = self::db()->table('users')->get();

        foreach ($users as $user) {
            echo $user->name;
        }

        $this->assertTrue(true);
    }

    /**
     * @desc 从数据表中获取单行或单列
     * 如果你只需要从数据表中获取一行数据，你可以使用 first 方法。
     */
    public function test11()
    {
        $user = self::db()->table('users')->where('name', '八戒')->first();

        $this->assertEquals($user->name, '八戒');
    }

    /**
     * @desc 可以使用 value 方法从记录中获取单个值。
     */
    public function test12()
    {
        $email = self::db()->table('users')->where('name', '八戒')->value('email');

        $this->assertEquals($email, '13899990003@qq.com');
    }

    /**
     * @desc 如果是通过 id 字段值获取一行数据，可以使用 find 方法
     */
    public function test13()
    {
        $user = self::db()->table('users')->find(3);

        $this->assertEquals($user->name, '八戒');
    }

    /**
     * @desc 获取一列的值
     * 如果你想获取包含单列值的集合，则可以使用 pluck 方法。
     */
    public function test14()
    {
        $mobiles = self::db()->table('users')->pluck('mobile');

        foreach ($mobiles as $mobile) {
            echo $mobile;
        }

        $this->assertTrue(count($mobiles) > 0);
    }

    /**
     * @desc 您可以通过向 pluck 方法提供第二个参数来指定结果集中应将其用作键的列
     */
    public function test15()
    {
        $mobiles = self::db()->table('users')->pluck('mobile', 'name');

        foreach ($mobiles as $mobile => $name) {
            echo $name;
        }

        $this->assertTrue(count($mobiles) > 0);
    }

    /** 分块结果 ***********************************/

    public function test16()
    {
        $db = self::db();

        // 开启查询日志
        $db->enableQueryLog();;

        $result = $db->table('users')->orderBy('id')->chunk(2, function ($users){
            echo "\r\n================\r\n";
            foreach ($users as $user) {
                print_r($user);
            }
            echo "\r\n================\r\n";
        });

        $this->assertTrue($result);

        // 获取查询日志
        $logs = $db->getQueryLog();

        print_r($logs);
    }

    /**
     * @desc 从闭包中返回 false 来停止处理其他块
     */
    public function test17()
    {
        $result = self::db()->table('users')->orderBy('id')->chunk(2, function ($users){
            // Process the records...
            print_r($users);

            return false;
        });

        $this->assertIsBool($result);
    }

    /** 聚合 ***********************************/

    /**
     * @desc 总数
     */
    public function test18()
    {
        $count = self::db()->table('users')->count();

        $this->assertTrue($count > 0);
    }

    /**
     * @desc 最大值
     */
    public function test19()
    {
        $maxPrice = self::db()->table('users')->max('price');

        $this->assertTrue($maxPrice > 0);
    }

    /**
     * @desc 最小值
     */
    public function test20()
    {
        $minPrice = self::db()->table('users')->min('price');

        $this->assertIsString($minPrice);
    }

    /**
     * @desc 平均值
     */
    public function test21()
    {
        $avgPrice = self::db()->table('users')->where('is_disable', 0)->avg('price');

        $this->assertTrue($avgPrice > 0);
    }

    /**
     * @desc 平均值
     */
    public function test22()
    {
        $avgPrice = self::db()->table('users')->where('is_disable', 0)->average('price');

        $this->assertTrue($avgPrice > 0);
    }

    /**
     * @desc 总和
     */
    public function test23()
    {
        $sumPrice = self::db()->table('users')->sum('price');

        $this->assertTrue($sumPrice > 0);
    }


    /** Select ***********************************/

    /**
     * @desc select 指定字段
     */
    public function test24()
    {
        $users = self::db()->table('users')->select('name', 'email as user_email')->get();
        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc distinct 方法会强制让查询返回的结果不重复
     */
    public function test25()
    {
        $users = self::db()->table('users')->distinct()->get();
        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 如果你已经有了一个查询构造器实例，并且希望在现有的查询语句中加入一个字段，那么你可以使用 addSelect 方法
     */
    public function test26()
    {
        $query = self::db()->table('users')->select('name');

        $users = $query->addSelect('email')->get();

        $this->assertTrue(count($users) > 0);
    }

    /** 原生表达式 ***********************************/

    /**
     * @desc 创建一个原生表达式(使用原生表达式，你需要避免SQL注入漏洞)
     */
    public function test27()
    {
        $users = self::db()->table('users')
            ->select(DB::raw('count(*) as user_count, status'))
            ->where('status', '<>', 1)
            ->groupBy('status')
            ->get();
        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 原生方法 selectRaw
     */
    public function test28()
    {
        $users = self::db()->table('users')->selectRaw('price * ? as price_with_tax', [1.0825])->get();
        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 原生方法 whereRaw / orWhereRaw
     */
    public function test29()
    {
        $users = self::db()->table('users')->whereRaw('price > IF(status = "1", ?, 1)', [2])->get();
        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 原生方法 havingRaw / orHavingRaw
     */
    public function test30()
    {
        $users = self::db()->table('users')->select('status', DB::raw('SUM(price) as total_sales'))
            ->groupBy('status')->havingRaw('SUM(price) > ?', [2.1])->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 原生方法 orderByRaw
     */
    public function test31()
    {
        // $users = self::db()->table('users')->orderByRaw('updated_at - created_at DESC')->get();
        $users = self::db()->table('users')->orderByRaw('price, status DESC')->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 原生方法 groupByRaw
     */
    public function test32()
    {
        $users = self::db()->table('users')->select('status', 'is_disable')->groupByRaw('status, is_disable')->get();

        $this->assertTrue(count($users) > 0);
    }

    /** Joins 连接 ***********************************/

    /**
     * @desc Inner Join 语句
     */
    public function test33()
    {
        $users = self::db()->table('users')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->select('users.*', 'orders.*')
            ->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc Left Join
     */
    public function test34()
    {
        $users = self::db()->table('users')
            ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
            ->select('users.*', 'orders.*')
            ->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc Right Join
     */
    public function test35()
    {
        $users = self::db()->table('users')
            ->rightJoin('orders', 'users.id', '=', 'orders.user_id')
            ->select('users.*', 'orders.*')
            ->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 子连接查询
     */
    public function test36()
    {
        $latestPosts = self::db()->table('posts')
            ->select('user_id', DB::raw('MAX(created_at) as last_post_created_at'))
            ->where('is_disable', 1)
            ->groupBy('user_id');

        $sql = self::db()->table('users')
            ->joinSub($latestPosts, 'latest_posts', function ($join){
                $join->on('users.id', '=', 'latest_posts.user_id');
            })->toSql();
        echo "\r\n\r\n";
        print_r($sql);
        echo "\r\n\r\n";

        $this->assertTrue(! !$sql);
    }

    /** Unions ***********************************/

    /**
     * @desc Union 联合查询
     */
    public function test37()
    {
        $first = self::db()->table('users')->whereNull('name');

        $users = self::db()->table('users')->whereNull('password')->union($first)->get();

        $this->assertTrue(count($users) == 0);
    }

    /**
     * @desc Union All 联合查询
     */
    public function test38()
    {
        $first = self::db()->table('users')->whereNull('name');

        $users = self::db()->table('users')->whereNull('password')->unionAll($first)->get();

        $this->assertTrue(count($users) == 0);
    }

    /** 基础的 Where 语句 ***********************************/

    /**
     * @desc Where 语句
     */
    public function test39()
    {
        $users = self::db()->table('users')->where('is_disable', '=', 0)->where('status', '>', 0)->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 默认使用 = 操作符
     */
    public function test40()
    {
        $users = self::db()->table('users')->where('is_disable', 0)->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 可以使用数据库支持的任意操作符
     */
    public function test41()
    {
        $users = self::db()->table('users')->where('status', '>=', 0)->get();
        $this->assertTrue(count($users) > 0);

        $users = self::db()->table('users')->where('status', '<>', 1)->get();
        $this->assertTrue(count($users) > 0);

        $users = self::db()->table('users')->where('name', 'like', '李%')->get();
        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 支持数组条件参数，默认是 = 操作符
     */
    public function test42()
    {
        $wheres = [
            'is_disable' => 0,
            'status'     => 1,
            'deleted_at' => null,
        ];
        $users  = self::db()->table('users')->where($wheres)->get();
        $this->assertTrue(count($users) > 0);
    }

    /** Or Where 语句 ***********************************/

    /**
     * @desc orWhere 方法接收的参数和 where 方法接收的参数一样
     */
    public function test43()
    {
        $users = self::db()->table('users')->where('status', '>', 2)->orWhere('name', '李白')->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 如果您需要在括号内对 or 条件进行分组，那么可以传递一个闭包作为 orWhere 方法的第一个参数
     */
    public function test44()
    {
        $users = self::db()->table('users')->where('status', '>', 1)
            ->orWhere(function (Builder $query){
                $query->where('name', '哪吒')->where('status', '=', 1);
            })->get();

        $this->assertTrue(count($users) > 0);
    }

    /** 其他 Where 语句 ***********************************/

    /**
     * @desc whereBetween / orWhereBetween , whereBetween 方法是用来验证字段的值是否在给定的两个值之间
     */
    public function test45()
    {
        $users = self::db()->table('users')->whereBetween('status', [1, 3])->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc whereNotBetween / orWhereNotBetween, whereNotBetween 方法是用来验证字段的值是否不在给定的两个值之间
     */
    public function test46()
    {
        $users = self::db()->table('users')->whereNotBetween('status', [1, 2])->get();

        $this->assertTrue(count($users) > 0);
    }

    /** whereIn / whereNotIn / orWhereIn / orWhereNotIn ***********************************/

    /**
     * @desc whereIn 方法是用来验证一个字段的值是否在给定的数组中
     */
    public function test47()
    {
        $users = self::db()->table('users')->whereIn('id', [1, 2, 3])->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc whereNotIn
     */
    public function test48()
    {
        $users = self::db()->table('users')->whereNotIn('id', [1, 2, 3])->get();

        $this->assertTrue(count($users) > 0);
    }

    /** whereNull / whereNotNull / orWhereNull / orWhereNotNull ***********************************/

    /**
     * @desc whereNull 方法是用来验证给定字段的值是否为 NULL
     */
    public function test49()
    {
        $users = self::db()->table('users')->whereNull('deleted_at')->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc whereNotNull
     */
    public function test50()
    {
        $users = self::db()->table('users')->whereNotNull('deleted_at')->get();

        $this->assertTrue(count($users) == 0);
    }

    /** whereDate / whereMonth / whereDay / whereYear / whereTime ***********************************/

    /**
     * @desc whereDate 方法是用来比较字段的值与给定的日期值是否相等 （年 - 月 - 日）
     */
    public function test51()
    {
        $users = self::db()->table('users')->whereDate('deleted_at', '2016-12-31')->get();

        $this->assertTrue(count($users) == 0);
    }

    /**
     * @desc whereMonth 方法是用来比较字段的值与给定的月份是否相等（月）
     */
    public function test52()
    {
        $users = self::db()->table('users')->whereMonth('deleted_at', '12')->get();

        $this->assertTrue(count($users) == 0);
    }

    /**
     * @desc whereDay 方法是用来比较字段的值与一个月中给定的日期是否相等 （日）
     */
    public function test53()
    {
        $users = self::db()->table('users')->whereDay('deleted_at', '31')->get();

        $this->assertTrue(count($users) == 0);
    }

    /**
     * @desc whereYear 方法是用来比较字段的值与给定的年份是否相等（年）
     */
    public function test54()
    {
        $users = self::db()->table('users')->whereYear('deleted_at', '2016')->get();

        $this->assertTrue(count($users) == 0);
    }

    /**
     * @desc whereTime 方法是用来比较字段的值与给定的时间是否相等（时：分: 秒）
     */
    public function test55()
    {
        $users = self::db()->table('users')->whereTime('deleted_at', '=', '11:20:45')->get();

        $this->assertTrue(count($users) == 0);
    }

    /** whereColumn / orWhereColumn ***********************************/

    /**
     * @desc whereColumn 方法是用来比较两个给定的字段的值是否相等
     */
    public function test56()
    {
        $users = self::db()->table('users')->whereColumn('mobile', 'name')->get();

        $this->assertTrue(count($users) == 0);
    }

    /**
     * @desc 您也可以传递一个比较运算符来作为 whereColumn 方法的第二个参数，如下
     */
    public function test57()
    {
        $users = self::db()->table('orders')->whereColumn('deleted_at', '>', 'created_at')->get();

        $this->assertTrue(count($users) == 0);
    }

    /**
     * @desc 您还可以向 whereColumn 方法中传递一个数组。数组中的条件将会被看作是 and 关系
     */
    public function test58()
    {
        $whereColumns = [
            ['first_name', '=', 'last_name'],
            ['updated_at', '>', 'created_at'],
        ];

        $users = self::db()->table('users')->whereColumn($whereColumns)->toSql();

        $this->assertIsString($users);
    }

    /** 逻辑分组 ***********************************/

    /**
     * @desc 有时您可能需要将括号内的几个 “where” 子句分组
     */
    public function test59()
    {
        $users = self::db()->table('users')
            ->where('name', '=', '沙僧')
            ->where(function (Builder $query){
                $query->where('is_disable', '>', 1)->orWhere('status', '=', 1);
            })->get();

        $this->assertTrue(count($users) > 0);
    }

    /** Where Exists 语句 ***********************************/

    /**
     * @desc whereExists 方法允许你使用 where exists SQL 语句
     */
    public function test60()
    {
        $users = self::db()->table('users')->whereExists(function ($query){
            $query->select(DB::raw(1))->from('orders')->whereColumn('orders.user_id', 'users.id');
        })->get();

        $this->assertTrue(count($users) > 0);
    }

    /** 子查询 Where 语句 ***********************************/

    /**
     * @desc 有时候，您可能需要构造一个 where 子句，将子查询的结果与给定值进行比较。您可以通过向 where 方法传递一个闭包和一个值来完成此操作。
     */
    public function test61()
    {
        $users = self::db()->table('users')->where(function (Builder $query){
            $query->select('amount')->from('orders')
                ->whereColumn('orders.user_id', 'users.id')->orderByDesc('orders.created_at')->limit(1);
        }, 'Pro')->get();

        $this->assertTrue(count($users) == 0);
    }

    /**
     * @desc 您可能需要构造一个 “where” 子句，将列与子查询的结果进行比较。
     */
    public function test62()
    {
        $users = self::db()->table('users')->where('price', '<', function (Builder $query){
            $query->selectRaw('avg(th_i.amount)')->from('orders as i');
        })->get();

        $this->assertTrue(count($users) > 0);
    }

    /** Ordering, Grouping, Limit & Offset ***********************************/

    /**
     * @desc orderBy 方法允许你通过给定字段对结果集进行排序。 orderBy 的第一个参数应该是你希望排序的字段，第二个参数控制排序的方向，可以是 asc 或 desc
     */
    public function test63()
    {
        $users = self::db()->table('users')->orderBy('name', 'desc')->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 如果你需要使用多个字段进行排序，你可以多次引用 orderBy
     */
    public function test64()
    {
        $users = self::db()->table('users')->orderBy('name', 'desc')->orderBy('email', 'asc')->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc latest & oldest 方法, 方法让你以一种便捷的方式通过日期进行排序。它们默认使用 created_at 列作为排序依据。当然，你也可以传递自定义的列名
     */
    public function test65()
    {
        $users = self::db()->table('users')->latest('deleted_at')->first();

        $this->assertIsObject($users);
    }

    /**
     * @desc 随机排序, inRandomOrder 方法被用来将结果进行随机排序。例如，你可以使用此方法随机找到一个用户
     */
    public function test66()
    {
        $users = self::db()->table('users')->inRandomOrder()->first();
        $this->assertIsObject($users);
    }

    /**
     * @desc 删除已经存在的所有排序, reorder 方法允许你删除已经存在的所有排序，如果你愿意，可以在之后附加一个新的排序。例如，你可以删除所有已存在的排序
     */
    public function test67()
    {
        $query = self::db()->table('users')->orderBy('name');

        $unorderedUsers = $query->reorder()->get();

        $this->assertTrue(count($unorderedUsers) > 0);

        // 删除所有已存在的排序并且附加新的排序，并且在方法上提供新的排序字段和顺序，用于重新排序：
        $usersOrderedByEmail = $query->reorder('email', 'desc')->get();

        $this->assertTrue(count($usersOrderedByEmail) > 0);
    }

    /** Grouping ***********************************/

    /**
     * @desc groupBy & having 方法
     */
    public function test68()
    {
        $users = self::db()->table('users')
            ->select('status', DB::raw('count(*) statusTotal'))
            ->groupBy('status')
            ->having('statusTotal', '>', 1)
            ->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc groupBy & having 方法
     */
    public function test69()
    {
        $users = self::db()->table('users')
            ->select('status', 'is_disable', DB::raw('count(*) statusTotal'))
            ->groupBy('status', 'is_disable')
            ->having('statusTotal', '>', 1)
            ->get();

        $this->assertTrue(count($users) > 0);
    }

    /** Limit & Offset ***********************************/

    /**
     * @desc skip & take 方法, 要限制结果的返回数量，或跳过指定数量的结果，你可以使用 skip 和 take 方法
     */
    public function test70()
    {
        $users = self::db()->table('users')->skip(10)->take(5)->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 你也可以使用 limit 和 offset 方法，这些方法在功能上分别等效于 take 和 skip 方法
     */
    public function test71()
    {
        $users = self::db()->table('users')->offset(10)->limit(5)->get();

        $this->assertTrue(count($users) > 0);
    }

    /** 插入语句 ***********************************/

    /**
     * @desc insert 方法用于插入记录到数据库中
     */
    public function test72()
    {
        $mobile = '138' . mt_rand(10000000, 99999999);

        $row = [

            'name'       => '悟空' . mt_rand(1000, 9999),
            'password'   => '123456',
            'mobile'     => $mobile,
            'email'      => $mobile . '@qq.com',
            'price'      => 1.30,
            'is_disable' => 0,
            'status'     => 2,
            'bio'        => '',
        ];

        $result = self::db()->table('users')->insert($row);

        $this->assertIsBool($result);;
    }

    /**
     * @desc insert 批量插入
     */
    public function test73()
    {
        $generateRow = function (){
            $mobile = '138' . mt_rand(10000000, 99999999);

            $row = [

                'name'       => '悟空' . mt_rand(1000, 9999),
                'password'   => '123456',
                'mobile'     => $mobile,
                'email'      => $mobile . '@qq.com',
                'price'      => 9.30,
                'is_disable' => 0,
                'status'     => 2,
                'bio'        => '',
            ];

            return $row;
        };

        $rows   = [];
        $rows[] = $generateRow();
        $rows[] = $generateRow();

        $result = self::db()->table('users')->insert($rows);

        $this->assertIsBool($result);;
    }

    /**
     * @desc insertOrIgnore 方法在将记录插入数据库时将忽略重复记录错误
     */
    public function test74()
    {
        $mobile = '138' . mt_rand(10000000, 99999999);
        $row    = [
            'id'         => 12,
            'name'       => '悟空sb' . mt_rand(1000, 9999),
            'password'   => '123456',
            'mobile'     => $mobile,
            'email'      => $mobile . '@qq.com',
            'price'      => 1.30,
            'is_disable' => 0,
            'status'     => 2,
            'bio'        => '',
        ];

        $result = self::db()->table('users')->insertOrIgnore($row);

        $this->assertIsInt($result);

        $mobile = '138' . mt_rand(10000000, 99999999);
        $row    = [
            [
                'id'         => 12,
                'name'       => '悟空sb' . mt_rand(1000, 9999),
                'password'   => '123456',
                'mobile'     => $mobile,
                'email'      => $mobile . '@qq.com',
                'price'      => 1.30,
                'is_disable' => 0,
                'status'     => 2,
                'bio'        => '',
            ],
            [
                'id'         => 13,
                'name'       => '悟空sb' . mt_rand(1000, 9999),
                'password'   => '123456',
                'mobile'     => $mobile,
                'email'      => $mobile . '@qq.com',
                'price'      => 1.30,
                'is_disable' => 0,
                'status'     => 2,
                'bio'        => '',
            ],
        ];

        $result = self::db()->table('users')->insertOrIgnore($row);

        $this->assertIsInt($result);;
    }

    /**
     * @desc 如果数据表有自增 ID ，使用 insertGetId 方法来插入记录可以返回 ID 值
     */
    public function test75()
    {
        $mobile = '138' . mt_rand(10000000, 99999999);

        $row = [
            'name'       => '悟空sb' . mt_rand(1000, 9999),
            'password'   => '123456',
            'mobile'     => $mobile,
            'email'      => $mobile . '@qq.com',
            'price'      => 1.30,
            'is_disable' => 0,
            'status'     => 2,
            'bio'        => '',
        ];

        $id = self::db()->table('users')->insertGetId($row);

        $this->assertIsInt($id);;
    }

    /**
     * @desc upsert
     * upsert 方法用于插入不存在的记录，并使用您指定的新值更新已存在的记录。
     * 方法的第一个参数由要插入或更新的值组成，而第二个参数列出了唯一标识关联表中记录的列。
     * 该方法的第三个也是最后一个参数是一个列数组，如果数据库中已存在匹配的记录，则应更新这些列。
     *
     * 在示例中，会尝试插入两条记录，如果记录存在与 name 和 password 列相同的值，将会更新 price 列的值。
     */
    public function test76()
    {
        $row = [
            [
                'name'     => '悟空sb' . mt_rand(1000, 9999),
                'password' => '123456',
                'price'    => 1.30,
            ],
        ];

        $result = self::db()->table('users')->upsert($row, ['name', 'password'], ['price']);

        $this->assertIsInt($result);;
    }

    /** 更新语句 ***********************************/

    /**
     * @desc update
     */
    public function test77()
    {
        $result = self::db()->table('users')->where('id', 1)->update(['status' => 1]);
        $this->assertIsInt($result);;
    }

    /**
     * @desc 更新或新增
     * 有时您可能希望更新数据库中的现有记录，或者如果不存在匹配记录则创建它。
     * 在这种情况下，可以使用 updateOrInsert 方法。
     * updateOrInsert 方法接受两个参数：一个用于查找记录的条件数组，以及一个包含要更该记录的键值对数组。
     */
    public function test78()
    {
        $result = self::db()->table('users')->updateOrInsert(
        // 条件
            ['email' => '13899990007@qq.com', 'name' => '李靖'],
            // 更新
            ['status' => '2']
        );

        $this->assertIsBool($result);;
    }

    /** 自增与自减 ***********************************/

    /**
     * @desc 自增
     */
    public function test79()
    {
        $result = self::db()->table('users')->where('id', 1)->increment('price');
        $this->assertIsInt($result);;

        $result = self::db()->table('users')->where('id', 1)->increment('price', 3);
        $this->assertIsInt($result);;
    }

    /**
     * @desc 自减
     */
    public function test80()
    {
        $result = self::db()->table('users')->where('id', 1)->decrement('price');
        $this->assertIsInt($result);;

        $result = self::db()->table('users')->where('id', 1)->decrement('price', 3);
        $this->assertIsInt($result);;
    }

    /** 删除语句 ***********************************/

    /**
     * @desc Delete
     */
    public function test81()
    {
        $result = self::db()->table('users')->where('id', 14)->delete();
        $this->assertIsInt($result);;
    }

    /**
     * @desc 悲观锁，共享锁 (lock in share mode)
     * 查询构造器也包含了一些能够帮助您在 select 语句中实现「悲观锁」的函数。
     * 要执行一个含有「共享锁」的语句，您可以在查询中使用 sharedLock 方法。
     * 共享锁可防止指定的数据列被篡改，直到事务被提交为止。
     */
    public function test82()
    {
        $users = self::db()->table('users')->where('status', '>', 1)->sharedLock()->get();

        $this->assertTrue(count($users) > 0);
    }

    /**
     * @desc 排他锁 (for update)
     * 您可以使用 lockForUpdate 方法。使用 update 锁可以避免数据行被其他共享锁修改或选定。
     */
    public function test83()
    {
        $db = self::db();

        $db->beginTransaction();

        $users = $db->table('users')->where('status', '>', 1)->lockForUpdate()->get();

        $db->commit();

        $this->assertTrue(count($users) > 0);
    }

}
