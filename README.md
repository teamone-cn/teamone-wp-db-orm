# Teamone WordPress Database ORM

霆万平头哥开源

![TeamOne](https://font.thwpmanage.com/img/teamone.jpg) 

Teamone WordPress Database ORM 是【霆万平头哥】使用PHP开发的数据库操作组件，此组件并无任何其他依赖，开箱即用。

在实际情况中，我们的应用往往需要使用连接多个不同的实例、读写分离等，在一些诸如 WordPress 的框架，其内置的 `wpdb` 数据库操作类有非常大的局限性，其无法支持切换实例、读写分离等有价值的功能特性。

针对 WordPress 框架，由于其插件机制，多个插件同时应用时，A 插件可以会使用了 B 插件的类加载而使用了 B 插件的配置。

对此，我们对插件之间进行了隔离，完美决绝了这类冲突问题，每个插件都可以独立地安装此组件，互不影响。本组件可以帮助我们在开发 WordPress 主题、插件时，提供灵活性，解决多数据库连接问题，降低数据库单实例的压力。

当然，远不止这些。在 WordPress 框架中，我们可以愉快地使用如 事务管理器、分页管理器、游标管理、丰富地查询功能等等，完全增强了我们在 WordPress 中操作数据库的能力。

## 功能模块

1. 连接器(连接、配置)
2. 数据库接口层
3. 语法分析器
4. SQL编译器
5. 事务管理器
6. 分页管理器
7. 游标管理
8. 防SQL注入
9. SQL执行日志
10. 支持丰富的查询
11. 支持读写分离
12. 支持连接实例切换
13. 支持表前缀切换

## 测试表

```mysql
-- 测试库
CREATE
    DATABASE `blog` DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_general_ci;

-- 用户表
CREATE TABLE `th_users`
(
    `id`         int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
    `name`       varchar(32)  NOT NULL   DEFAULT '' COMMENT '用户姓名',
    `password`   varchar(255) NOT NULL   DEFAULT '' COMMENT '登陆密码',
    `mobile`     char(11)     NOT NULL   DEFAULT '' COMMENT '电话',
    `email`      varchar(64)  NOT NULL   DEFAULT '' COMMENT '邮箱',
    `price`      decimal(12, 2) unsigned default 0.00 not null comment '测试价格',
    `is_disable` tinyint unsigned        default 0 not null comment '是否禁用:0=否,1=是',
    `status`     tinyint unsigned        default 0 not null comment '状态:0=未知,1=会议中,2=休息中,3=打码中',
    `bio`        text COMMENT '个人简介',
    `deleted_at` datetime     null comment '删除时间',
    PRIMARY KEY (`id`) COMMENT '主键索引'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='用户表';

-- 测试数据库
INSERT INTO `th_users`(`name`, `password`, `mobile`, `email`, `price`, `is_disable`, `status`, `bio`, `deleted_at`)
VALUES ('唐僧', '123456', '13899990001', '13899990001@qq.com', '1.5', 0, 1, '', null),
       ('悟空', '123456', '13899990002', '13899990002@qq.com', '1.6', 0, 2, '', null),
       ('八戒', '123456', '13899990003', '13899990003@qq.com', '1.1', 0, 3, '', null),
       ('沙僧', '123456', '13899990004', '13899990004@qq.com', '1.2', 0, 1, '', null),
       ('白龙马', '123456', '13899990005', '13899990005@qq.com', '1.3', 0, 2, '', null),
       ('李靖', '123456', '13899990006', '13899990006@qq.com', '1.4', 0, 3, '', null),
       ('哪吒', '123456', '13899990007', '13899990007@qq.com', '1.5', 0, 1, '', null),
       ('李白', '123456', '13899990008', '13899990008@qq.com', '1.3', 0, 2, '', null);

-- 订单表
CREATE TABLE `th_orders`
(
    `id`           int UNSIGNED                NOT NULL AUTO_INCREMENT COMMENT '主键',
    `user_id`      int            default 0    not null comment '用户ID',
    `order_number` varchar(255)   default ''   not null comment '订单号',
    `order_status` tinyint        default 0    not null comment '订单状态',
    `amount`       decimal(12, 2) default 0.00 not null comment '订单金额',
    `remark`       text                        null comment '备注',
    `created_at`   datetime                    null comment '创建时间',
    `deleted_at`   datetime                    null comment '删除时间',
    PRIMARY KEY (`id`) COMMENT '主键索引'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='订单表';

INSERT INTO `th_orders`(`user_id`, `order_number`, `order_status`, `amount`, `remark`, `created_at`)
VALUES (1, 'OD0001', 1, 123.12, '订单', '2023-01-15 16:32:14'),
       (1, 'OD0002', 1, 322, '订单', '2023-01-16 16:32:14'),
       (2, 'OD0003', 1, 196, '订单', '2023-01-12 16:32:14'),
       (2, 'OD0004', 1, 60, '订单', '2023-01-11 16:32:14'),
       (3, 'OD0005', 1, 1470, '订单', '2023-01-13 16:32:14');

-- 订单详情表
CREATE TABLE `th_order_details`
(
    `id`           int UNSIGNED                NOT NULL AUTO_INCREMENT COMMENT '主键',
    `order_id`     int            default 0    not null comment '订单ID',
    `product_id`   int            default 0    not null comment '产品ID',
    `sku`          varchar(128)   default 0    not null comment '产品SKU',
    `price`        decimal(12, 2) default 0.00 not null comment '单价',
    `origin_price` decimal(12, 2) default 0.00 not null comment '原价',
    `num`          int            default 0    not null comment '产品数量',
    `subtotal`     int            default 0    not null comment '小计',
    `created_at`   datetime                    null comment '创建时间',
    `deleted_at`   datetime                    null comment '删除时间',
    PRIMARY KEY (`id`) COMMENT '主键索引'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='订单详情表';

INSERT INTO `th_order_details`(`order_id`, `product_id`, `sku`, `price`, `origin_price`, `num`, `subtotal`,
                               `created_at`)
VALUES (1, 11, 'SK221', 123.12, 123.12, 1, 123.12, '2023-01-15 16:32:14'),
       (2, 12, 'SK222', 161, 322, 1, 161, '2023-01-15 16:32:14'),
       (2, 13, 'SK229', 161, 322, 1, 161, '2023-01-15 16:32:14'),
       (3, 14, 'SK223', 98, 138, 2, 196, '2023-02-15 16:32:14'),
       (4, 15, 'SK224', 20, 30, 3, 60, '2023-01-25 16:32:14'),
       (5, 16, 'SK225', 490, 510, 1, 490, '2023-01-18 16:32:14'),
       (5, 17, 'SK226', 490, 510, 2, 980, '2023-01-19 16:32:14');

```

## Composer 安装

- 安装 Simple Database

```sh
$ composer require teamone/simple-database
```

## Composer 依赖工具安装

注意，如果您需要对 Simple Database 进行扩展，在开发过程中可以安装下列工具，在投入生产是不需要使用的。

1. 安装调试工具 VarDumper

```sh
$ composer require --dev symfony/var-dumper
```

2. 安装静态代码分析工具

```sh
$ composer require --dev phpstan/phpstan
```

3. 安装 PHPUnit

```sh
$ composer require --dev phpunit/phpunit 8.5
```

## 数据库连接配置

```php
$config = [
    'default'     => 'ali-rds',
    'connections' => [
        // 连接配置
        'ali-rds'     => [
            // 连接名称
            'name'      => 'ali-rds',
            // 只读
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
            // 连接驱动
            'driver'    => 'mysql',
            // 端口
            'port'      => 3306,
            // 数据库
            'database'  => 'blog',
            // 账户
            'username'  => 'root',
            // 密码
            'password'  => '123456',
            // 字符集
            'charset'   => 'utf8mb4',
            // 排序字符集
            'collation' => 'utf8mb4_general_ci',
            // 表前缀
            'prefix'    => 'th_',
        ],
        
    ],
];

```

`default` 声明默认连接，取值为 `connections` 的 key。`connections` 可以定义多个连接。

## 单元测试解释

在 `test/Unit` 目录中，编写了详细的测试用例。

我们来看下 `test` 目录结构：

```sh
./test
├── Config
│         ├── DatabaseConfig.php #配置文件
│         ├── PluginFirstDatabase.php #配置文件
│         └── PluginSecondDatabase.php #配置文件
├── ConnectTrait.php
├── Model
│         ├── Orders.php #模型类
│         └── Users.php #模型类
└── Unit
    ├── DatabaseTest.php #单元测试类
    ├── ModelTest.php #单元测试类
    ├── MultiTest.php #单元测试类
    └── TeamoneWpDbOrmBaseTest.php #单元测试类
```

- trait ConnectTrait 说明

ConnectTrait::db() 用于获取连接，setupSomeFixtures() 表示前置执行，tearDownSomeFixtures() 表示后置执行。

```php
namespace Teamone\TeamoneWpDbOrmTest\Unit;

use Teamone\TeamoneWpDbOrm\Capsule\Manager as DB;
use Teamone\TeamoneWpDbOrmTest\Config\DatabaseConfig;

trait ConnectTrait
{
    protected static $_db;

    /**
     * @desc 获取连接
     * @return \Teamone\TeamoneWpDbOrm\Connection
     */
    public static function db()
    {
        if (is_null(self::$_db)) {
            self::$_db = DB::resolverDatabaseConfig(DatabaseConfig::class)::connection('ali-rds');
        }

        return self::$_db;
    }

    /**
     * @before
     */
    public function setupSomeFixtures()
    {
        // 开启查询日志
        self::db()->enableQueryLog();
    }

    /**
     * @after
     */
    public function tearDownSomeFixtures()
    {
        // 获取查询日志
        $logs = self::db()->getQueryLog();
        echo "\r\n\r\n";
        print_r($logs);
    }
}

```

- 简单的单元测试类声明

```php
use PHPUnit\Framework\TestCase;

class TeamoneWpDbOrmBaseTest extends TestCase
{
    use ConnectTrait;

    /**
     * @desc 
     */
    public function test1()
    {
        $this->assertTrue(1 + 1 == 2);
    }
}
```

执行测试：

```sh
$ ./vendor/phpunit/phpunit/phpunit --configuration phpunit.xml ./test/Unit --filter TeamoneWpDbOrmBaseTest::test1
```

## 单元测试

1. 执行指定目录所有用例

```sh
$ ./vendor/phpunit/phpunit/phpunit --configuration phpunit.xml ./test/Unit
```

2. 执行指定文件

```sh
$ ./vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --test-suffix TeamoneWpDbOrmBaseTest.php ./test/Unit
```

3. 执行 TeamoneWpDbOrmBaseTest 用例

```sh
$ ./vendor/phpunit/phpunit/phpunit --configuration phpunit.xml ./test/Unit --filter TeamoneWpDbOrmBaseTest
```

4. 执行 TeamoneWpDbOrmBaseTest::test1 用例

```sh
$ ./vendor/phpunit/phpunit/phpunit --configuration phpunit.xml ./test/Unit --filter TeamoneWpDbOrmBaseTest::test01
```

## 数据库连接

为了简化，后续我们都使用 DB 作为 Manager 的别名

```php
use Teamone\TeamoneWpDbOrm\Capsule\Manager as DB;
```

1. 数据库连接，提供实现类

```php
use Teamone\TeamoneWpDbOrm\Capsule\Manager as DB;

// 初始化配置
DB::resolverDatabaseConfig(DatabaseConfig::class);
// 获取连接
$db = DB::connection('ali-rds');
// 执行查询
$users = $db->table('users')->get();
```

2. 数据库连接，提供匿名实现类

```php
$clazz = new class() implements DatabaseConfigContract{
    /**
     * @desc 数据库连接配置
     * @return array
     */
    public function getConnectionConfig()
    {
        $config = [
            // 默认配置，表示选择 connections 数组，key 为 ali-rds 的配置作为默认数据库配置
            'default'     => 'ali-rds',
            // 连接组
            'connections' => [
                // 连接 ali-rds
                'ali-rds'     => [
                    // 连接名
                    'name'      => 'ali-rds',
                    // 只读
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
                    // 驱动名，目前仅支持 mysql
                    'driver'    => 'mysql',
                    // 数据库连接端口
                    'port'      => 3306,
                    // 数据库
                    'database'  => 'blog',
                    // 数据库用户
                    'username'  => 'root',
                    // 数据库密码
                    'password'  => '123456',
                    // 字符集
                    'charset'   => 'utf8',
                    // 字符排序
                    'collation' => 'utf8_unicode_ci',
                    // 表前缀
                    'prefix'    => 'th_',
                ],
                // 连接 tencent-rds
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
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix'    => 'th_',
                ],
            ],
        ];

        return $config;
    }
};

$db = DB::resolverDatabaseConfig($clazz)::connection('ali-rds');

$users = $db->table('users')->get();
```

3. 查询日志

```php
$db = DB::resolverDatabaseConfig(DatabaseConfig::class)::connection('ali-rds');

// 开启查询日志
$db->enableQueryLog();

$users = $db->table('users')->get();

// 获取查询日志
$logs = $db->getQueryLog();
```

4. 原生查询与占位符

```php
$db = DB::resolverDatabaseConfig(DatabaseConfig::class)::connection('ali-rds');

$users = $db->select('SELECT * FROM th_users WHERE `id` IN (?, ?)', [1, 3]);
```

5. 指定连接查询(切换实例)

注意：当没有调用 `connection()` 方法时，使用的时默认连接配置。

```php
 // 解析配置，只执行一次
DB::resolverDatabaseConfig(DatabaseConfig::class);

// 查询 1
$users = DB::connection('ali-rds')->table('users')->select('name', 'mobile')->where('id', '>', 0)->get();
print_r($users);

// 查询 2
$users = DB::connection('ali-rds')->table('users')->select('name', 'status')->where('id', '>', 0)->get();
print_r($users);
```

6. SQL分析

```php
$db = DB::resolverDatabaseConfig(DatabaseConfig::class)::connection('ali-rds');
$users = $db->table('users')->where('id', '>=', 1)->explain();
```

## 数据库查询

1. 从表中检索所有行

```php
$users = self::db()->table('users')->get();

foreach ($users as $user) {
    echo $user->name;
}
```

2. 从数据表中获取单行或单列

如果你只需要从数据表中获取一行数据，你可以使用 first 方法。

```php
$user = self::db()->table('users')->where('name', '八戒')->first();

return $user->name;
```

3. 可以使用 value 方法从记录中获取单个值。

```php
$email = self::db()->table('users')->where('name', '八戒')->value('email');
```

4. 如果是通过 id 字段值获取一行数据，可以使用 find 方法

```php
$user = self::db()->table('users')->find(3);
```

5. 获取一列的值

如果你想获取包含单列值的集合，则可以使用 pluck 方法。

```php
$mobiles = self::db()->table('users')->pluck('mobile');

foreach ($mobiles as $mobile) {
    echo $mobile;
}
```

您可以通过向 pluck 方法提供第二个参数来指定结果集中应将其用作键的列：

```php
$mobiles = self::db()->table('users')->pluck('mobile', 'name');

foreach ($mobiles as $mobile => $name) {
    echo $name;
}
```

## 分块结果

如果您需要处理成千上万的数据库记录，这个方法一次检索一小块结果，并将每个块反馈到闭包函数中进行处理。 例如，让我们以一次 2
条记录的块为单位检索整个 users 表。:

```php
self::db()->table('users')->orderBy('id')->chunk(2, function ($users) {
    foreach ($users as $user) {
        //
    }
});
```

您可以通过从闭包中返回 false 来停止处理其他块:

```php

self::db()->table('users')->orderBy('id')->chunk(2, function ($users) {
    // Process the records...

    return false;
});

```

## 聚合

总数：

```php
$count = self::db()->table('users')->count();
```

最大值：

```php
$maxPrice = self::db()->table('users')->max('price');
```

最小值：

```php
$minPrice = self::db()->table('users')->min('price');
```

平均值：

```php
$avgPrice = self::db()->table('users')->where('is_disable', 0)->avg('price')
```

平均值：

```php
$avgPrice = self::db()->table('users')->where('is_disable', 0)->average('price');
```

总和：

```php
$sumPrice = self::db()->table('users')->sum('price');
```

## Select

1. select 指定字段

```php
$users = self::db()->table('users')->select('name', 'email as user_email')->get();
```

- distinct 方法会强制让查询返回的结果不重复

```php
$users = self::db()->table('users')->distinct()->get();
```

- 如果你已经有了一个查询构造器实例，并且希望在现有的查询语句中加入一个字段，那么你可以使用 addSelect 方法

```php
$query = self::db()->table('users')->select('name');

$users = $query->addSelect('email')->get();
```

## 原生表达式

- 创建一个原生表达式(使用原生表达式，你需要避免SQL注入漏洞)

```php
$users = self::db()->table('users')
    ->select(DB::raw('count(*) as user_count, status'))
    ->where('status', '<>', 1)
    ->groupBy('status')
    ->get();
```

- 原生方法 selectRaw

```php
$users = self::db()->table('users')->selectRaw('price * ? as price_with_tax', [1.0825])->get();
```

- 原生方法 whereRaw / orWhereRaw

```php
$users = self::db()->table('users')->whereRaw('price > IF(status = "1", ?, 1)', [2])->get();
```

- 原生方法 havingRaw / orHavingRaw

```php
 $users = self::db()->table('users')->select('status', DB::raw('SUM(price) as total_sales'))
    ->groupBy('status')->havingRaw('SUM(price) > ?', [2.1])->get();
```

- 原生方法 orderByRaw

```php
// $users = self::db()->table('users')->orderByRaw('updated_at - created_at DESC')->get();
$users = self::db()->table('users')->orderByRaw('price, status DESC')->get();
```

- 原生方法 groupByRaw

```php
$users = self::db()->table('users')->select('status', 'is_disable')->groupByRaw('status, is_disable')->get();
```

## Joins 连接

- Inner Join 语句

```php
$users = self::db()->table('users')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->select('users.*', 'orders.*')
    ->get();
```

- Left Join

```php
$users = self::db()->table('users')
    ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
    ->select('users.*', 'orders.*')
    ->get();
```

- Right Join

```php
$users = self::db()->table('users')
    ->rightJoin('orders', 'users.id', '=', 'orders.user_id')
    ->select('users.*', 'orders.*')
    ->get();
``` 

- 子连接查询

```php
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
```

## Unions

- Union 联合查询

```php
$first = self::db()->table('users')->whereNull('name');

$users = self::db()->table('users')->whereNull('password')->union($first)->get();
```

- Union All 联合查询

```php
$first = self::db()->table('users')->whereNull('name');

$users = self::db()->table('users')->whereNull('password')->unionAll($first)->get();
```

## 基础的 Where 语句

- Where 语句

```php
$users = self::db()->table('users')->where('is_disable', '=', 0)->where('status', '>', 0)->get();
```

- 默认使用 = 操作符

```php
$users = self::db()->table('users')->where('is_disable', 0)->get();
```

- 可以使用数据库支持的任意操作符

```php
$users = self::db()->table('users')->where('status', '>=', 0)->get();

$users = self::db()->table('users')->where('status', '<>', 1)->get();

$users = self::db()->table('users')->where('name', 'like', '李%')->get();
```

- 支持数组条件参数，默认是 = 操作符

```php
$wheres = [
    'is_disable' => 0,
    'status'     => 1,
    'deleted_at' => null,
];
$users  = self::db()->table('users')->where($wheres)->get();
```

## Or Where 语句

- orWhere 方法接收的参数和 where 方法接收的参数一样

```php
$users = self::db()->table('users')->where('status', '>', 2)->orWhere('name', '李白')->get();
```

- 如果您需要在括号内对 or 条件进行分组，那么可以传递一个闭包作为 orWhere 方法的第一个参数

```php
$users = self::db()->table('users')->where('status', '>', 1)
    ->orWhere(function (Builder $query){
        $query->where('name', '哪吒')->where('status', '=', 1);
    })->get();
```

## 其他 Where 语句

- whereBetween / orWhereBetween , whereBetween 方法是用来验证字段的值是否在给定的两个值之间

```php
$users = self::db()->table('users')->whereBetween('status', [1, 3])->get();
```

- whereNotBetween / orWhereNotBetween, whereNotBetween 方法是用来验证字段的值是否不在给定的两个值之间

```php
$users = self::db()->table('users')->whereNotBetween('status', [1, 2])->get();
```

## whereIn / whereNotIn / orWhereIn / orWhereNotIn

- whereIn 方法是用来验证一个字段的值是否在给定的数组中

```php
$users = self::db()->table('users')->whereIn('id', [1, 2, 3])->get();
```

- whereNotIn

```php
$users = self::db()->table('users')->whereNotIn('id', [1, 2, 3])->get();
```

## whereNull / whereNotNull / orWhereNull / orWhereNotNull

- whereNull 方法是用来验证给定字段的值是否为 NULL

```php
$users = self::db()->table('users')->whereNull('deleted_at')->get();
```

- whereNotNull

```php
$users = self::db()->table('users')->whereNotNull('deleted_at')->get();
```

## whereDate / whereMonth / whereDay / whereYear / whereTime

- whereDate 方法是用来比较字段的值与给定的日期值是否相等 （年 - 月 - 日）

```php
$users = self::db()->table('users')->whereDate('deleted_at', '2016-12-31')->get();
```

- whereMonth 方法是用来比较字段的值与给定的月份是否相等（月）

```php
$users = self::db()->table('users')->whereMonth('deleted_at', '12')->get();
```

- whereDay 方法是用来比较字段的值与一个月中给定的日期是否相等 （日）

```php
$users = self::db()->table('users')->whereDay('deleted_at', '31')->get();
```

- whereYear 方法是用来比较字段的值与给定的年份是否相等（年）

```php
$users = self::db()->table('users')->whereYear('deleted_at', '2016')->get();
```

- whereTime 方法是用来比较字段的值与给定的时间是否相等（时：分: 秒）

```php
$users = self::db()->table('users')->whereTime('deleted_at', '=', '11:20:45')->get();
```

## whereColumn / orWhereColumn

- whereColumn 方法是用来比较两个给定的字段的值是否相等

```php
$users = self::db()->table('users')->whereColumn('mobile', 'name')->get();
```

- 您也可以传递一个比较运算符来作为 whereColumn 方法的第二个参数，如下

```php
$users = self::db()->table('orders')->whereColumn('deleted_at', '>', 'created_at')->get();
```

- 您还可以向 whereColumn 方法中传递一个数组。数组中的条件将会被看作是 and 关系

```php
$whereColumns = [
    ['first_name', '=', 'last_name'],
    ['updated_at', '>', 'created_at'],
];

$users = self::db()->table('users')->whereColumn($whereColumns)->toSql();
```

## 逻辑分组

- 有时您可能需要将括号内的几个 “where” 子句分组

```php
$users = self::db()->table('users')
    ->where('name', '=', '沙僧')
    ->where(function (Builder $query){
        $query->where('is_disable', '>', 1)->orWhere('status', '=', 1);
    })->get();
```

## Where Exists 语句

- whereExists 方法允许你使用 where exists SQL 语句

```php
$users = self::db()->table('users')->whereExists(function ($query){
    $query->select(DB::raw(1))->from('orders')->whereColumn('orders.user_id', 'users.id');
})->get();
```

## 子查询 Where 语句

- 有时候，您可能需要构造一个 where 子句，将子查询的结果与给定值进行比较。您可以通过向 where 方法传递一个闭包和一个值来完成此操作。

```php
$users = self::db()->table('users')->where(function (Builder $query){
        $query->select('amount')->from('orders')
            ->whereColumn('orders.user_id', 'users.id')->orderByDesc('orders.created_at')->limit(1);
    }, 'Pro')->get();
```

- 您可能需要构造一个 “where” 子句，将列与子查询的结果进行比较。

```php
$users = self::db()->table('users')->where('price', '<', function (Builder $query){
        $query->selectRaw('avg(th_i.amount)')->from('orders as i');
    })->get();
```

## Ordering, Grouping, Limit & Offset

- orderBy 方法允许你通过给定字段对结果集进行排序。 orderBy 的第一个参数应该是你希望排序的字段，第二个参数控制排序的方向，可以是
  asc 或 desc

```php
$users = self::db()->table('users')->orderBy('name', 'desc')->get();
```

- 如果你需要使用多个字段进行排序，你可以多次引用 orderBy

```php
$users = self::db()->table('users')->orderBy('name', 'desc')->orderBy('email', 'asc')->get();
```

- latest & oldest 方法, 方法让你以一种便捷的方式通过日期进行排序。它们默认使用 created_at 列作为排序依据。当然，你也可以传递自定义的列名

```php
$users = self::db()->table('users')->latest('deleted_at')->first();
```

- 随机排序, inRandomOrder 方法被用来将结果进行随机排序。例如，你可以使用此方法随机找到一个用户

```php
$users = self::db()->table('users')->inRandomOrder()->first();
```

- 删除已经存在的所有排序, reorder 方法允许你删除已经存在的所有排序，如果你愿意，可以在之后附加一个新的排序。例如，你可以删除所有已存在的排序

```php
$query = self::db()->table('users')->orderBy('name');

$unorderedUsers = $query->reorder()->get();

// 删除所有已存在的排序并且附加新的排序，并且在方法上提供新的排序字段和顺序，用于重新排序：
$usersOrderedByEmail = $query->reorder('email', 'desc')->get();
```

## Grouping

- groupBy & having 方法

```php
$users = self::db()->table('users')
    ->select('status', DB::raw('count(*) statusTotal'))
    ->groupBy('status')
    ->having('statusTotal', '>', 1)
    ->get();
```

- groupBy & having 方法

```php
$users = self::db()->table('users')
    ->select('status', 'is_disable', DB::raw('count(*) statusTotal'))
    ->groupBy('status', 'is_disable')
    ->having('statusTotal', '>', 1)
    ->get();
```

## Limit & Offset

- skip & take 方法, 要限制结果的返回数量，或跳过指定数量的结果，你可以使用 skip 和 take 方法

```php
$users = self::db()->table('users')->skip(10)->take(5)->get();
```

- 你也可以使用 limit 和 offset 方法，这些方法在功能上分别等效于 take 和 skip 方法

```php
$users = self::db()->table('users')->offset(10)->limit(5)->get();
```

## 插入语句

- insert 方法用于插入记录到数据库中

```php
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
```

- insert 批量插入

```php
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
```

- insertOrIgnore 方法在将记录插入数据库时将忽略重复记录错误

```php
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
```

- 如果数据表有自增 ID ，使用 insertGetId 方法来插入记录可以返回 ID 值

```php
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
```

- upsert
  upsert 方法用于插入不存在的记录，并使用您指定的新值更新已存在的记录。
  方法的第一个参数由要插入或更新的值组成，而第二个参数列出了唯一标识关联表中记录的列。
  该方法的第三个也是最后一个参数是一个列数组，如果数据库中已存在匹配的记录，则应更新这些列。

*

在示例中，会尝试插入两条记录，如果记录存在与 name 和 password 列相同的值，将会更新 price 列的值。

```php
$row = [
    [
        'name'     => '悟空sb' . mt_rand(1000, 9999),
        'password' => '123456',
        'price'    => 1.30,
    ],
];

$result = self::db()->table('users')->upsert($row, ['name', 'password'], ['price']);
```

## 更新语句

- update

```php
$result = self::db()->table('users')->where('id', 1)->update(['status' => 1]);
```

- 更新或新增
  有时您可能希望更新数据库中的现有记录，或者如果不存在匹配记录则创建它。
  在这种情况下，可以使用 updateOrInsert 方法。
  updateOrInsert 方法接受两个参数：一个用于查找记录的条件数组，以及一个包含要更该记录的键值对数组。

```php
$result = self::db()->table('users')->updateOrInsert(
    // 条件
    ['email' => '13899990007@qq.com', 'name' => '李靖'],
    // 更新
    ['status' => '2']
);
```

## 自增与自减

- 自增

```php
$result = self::db()->table('users')->where('id', 1)->increment('price');

$result = self::db()->table('users')->where('id', 1)->increment('price', 3);
```

- 自减

```php
$result = self::db()->table('users')->where('id', 1)->decrement('price');

$result = self::db()->table('users')->where('id', 1)->decrement('price', 3);
```

## 删除语句

- Delete

```php
$result = self::db()->table('users')->where('id', 14)->delete();
```

## 锁

- 悲观锁，共享锁 (lock in share mode)

查询构造器也包含了一些能够帮助您在 select 语句中实现「悲观锁」的函数。
要执行一个含有「共享锁」的语句，您可以在查询中使用 sharedLock 方法。
共享锁可防止指定的数据列被篡改，直到事务被提交为止。

```php
$users = self::db()->table('users')->where('status', '>', 1)->sharedLock()->get();
```

- 排他锁 (for update)

```php
$db = self::db();

$db->beginTransaction();

$users = $db->table('users')->where('status', '>', 1)->lockForUpdate()->get();

$db->commit();
```

## 执行原生 SQL 查询

- SELECT

```php
$users = self::db()->select('SELECT * FROM `th_users` WHERE `status` = ?', [1]);
```

- INSERT

```php
$result = self::db()->insert('INSERT INTO `th_users`(`name`, `email`) VALUES (?, ?)', ['牛魔王', '13812344321@qq.com']);
```

- DELETE

```php
$result = self::db()->update('UPDATE `th_users` SET `status` = 3 WHERE `name` = ?', ['白龙马']);
```

- 执行普通查询

部分数据库语句没有返回值。你可以使用statement 方法：

```php
$result = self::db()->statement('SET NAMES utf8mb4');
```

- 数据库事务

```php
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
```

- Exists 判断记录是否存在

```php
$result = self::db()->table('users')->where('is_disable', 1)->exists();

$result = self::db()->table('users')->select(['id', 'name'])->where('name', '朱世杰')->existsOr(function (){
    var_dump('hello');
});
```

- doesntExist 判断记录是否存在

```php
$result = self::db()->table('users')->where('is_disable', 1)->doesntExist();

$result = self::db()->table('users')->select(['id', 'name'])->where('name', '朱世杰')->doesntExistOr(function (){
    var_dump('world');
});
```

- 分页

```php
$users = self::db()->table('users')->paginate();
```

- 简单分页

```php
$users = self::db()->table('users')->simplePaginate();
```

- 游标分页

```php
$users = self::db()->table('users')->orderBy('id')->cursorPaginate(2);
```

- 分页参数

```php
$perPage  = 5;
$columns  = ['*'];
$pageName = 'page';
$page     = null;

$users = self::db()->table('users')->paginate($perPage, $columns, $pageName, $page);
```

- 使用游标

```php
$cursor     = new Cursor(['id' => 0], true);
$perPage    = 5;
$columns    = ['*'];
$cursorName = 'cursor';
$users      = self::db()->table('users')->orderBy('id')->cursorPaginate($perPage, $columns, $cursorName, $cursor);
```

- 使用游标 2

```php
PaginationState::resolveUsing();

// 游标
$cursor = new Cursor(['id' => 0], true);

/** @var CursorPaginator $cursorPaginate */
$cursorPaginate = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->cursorPaginate(3, ['*'], 'cursor', $cursor);

// 下个游标
$nextCursor = $cursorPaginate->nextCursor();

/** @var CursorPaginator $cursorPaginate */
$cursorPaginate = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->cursorPaginate(3, ['*'], 'cursor', $nextCursor);
```

- 惰性查询

```php
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
```

- 惰性查询

```php
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
```

- 使用 ID 分页

```php
$users = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->forPageBeforeId(3, 10)->get();
```

- 将给定列的值连接为字符串

```php
$idString = self::db()->table('users')->select(['id', 'name'])->orderBy('id')->limit(3)->implode('id', ',');
```

## 模型 Model

无论使用模型类还是DB类，都必须执行 `DB::resolverDatabaseConfig(DatabaseConfig::class)` 方法进行初始化配置。

```php
DB::resolverDatabaseConfig(DatabaseConfig::class);
```

- 声明模型类

其中，`$table` 属性是必填项，如果未声明 `$tablePrefix`
，则采用配置文件`DatabaseConfig::getConnectionConfig()['connections']['prefix']`字段作为前缀。

如果未声明 `$connection` 属性，则采用配置文件`DatabaseConfig::getConnectionConfig()['default']` 字段作为默认连接。

```php
use Teamone\TeamoneWpDbOrm\Query\Model;

class Users extends Model
{
    /**
     * @var string
     */
    protected $table = 'users';

}

class Orders extends Model
{
    /**
     * @var string 表前缀
     */
    protected $tablePrefix = 'mrz_';

    /**
     * @var string 表名
     */
    protected $table = 'orders';

    /**
     * @var string 连接
     */
    protected $connection = 'tencent-rds';

}

```

- 模型类使用

```php
$users = new Users();

$users = $users->newQuery()->where('id', '=', 1)->get();

print_r($users);
```

- 一次查询一次调用 newQuery()

```php
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
```

- 选择连接

```php
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
```

- 表前缀切换

```php
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
```

- 查询指定连接的查询日志

```php
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
```

- 指定连接、表前缀、表名

```php
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
```

## WordPress 引入方式

假设现在有个 WordPress 插件，名为 `plugin-first`，插件所在目录即: `wp-content/plugins/plugin-first`，
插件入口文件通常与插件名相同，如：plugin-first.php，在入口文件，我们声明如下代码：

```php
use Teamone\TeamoneWpDbOrm\Capsule\PluginManagerAbstract;

class PluginFirstDatabase extends PluginManagerAbstract
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
                'ali-rds' => [
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
            ],
        ];

        return $config;
    }

    /**
     * @desc 获取入口文件
     * @return mixed|string
     */
    public function getEntryFile()
    {
        return __FILE__;
    }
}
```

如上例，我们要实现两个方法，`getConnectionConfig()`用于初始化数据库配置；`getEntryFile()`，提供入口文件，用于在 WordPress 插件机制中起到隔离作用。

- 查询
```php
$dbManager = PluginFirstDatabase::getInstance()->getManager();

$users = $dbManager::connection('ali-rds')->table('users')->where('id', 1)->get();
```

## 参考文档

- 单元测试 https://phpunit.readthedocs.io/zh_CN/latest/index.html
