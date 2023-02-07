<?php

namespace Teamone\TeamoneWpDbOrm;

use Closure;
use Teamone\TeamoneWpDbOrm\Query\Grammars\MySqlGrammar;
use Teamone\TeamoneWpDbOrm\Query\Processors\MySqlProcessor;
use PDO;

interface ConnectionInterface
{
    /**
     * @desc 开始对数据库表进行查询
     * @param string $table
     * @param $as
     * @return mixed
     */
    public function table($table, $as = null);

    /**
     * @desc 获取一个新的原始查询表达式。
     * @param $value
     * @return mixed
     */
    public function raw($value);

    /**
     * @desc 运行一个select语句并返回一个结果。
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return mixed
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true);

    /**
     * @desc 对数据库运行一个查询语句
     * @param $query
     * @param $bindings
     * @param $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true);

    /**
     * @desc 对数据库运行查询语句并返回一个生成器
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true);

    /**
     * @desc 对数据库运行插入语句
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function insert($query, $bindings = []);

    /**
     * @desc 对数据库运行更新语句
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function update($query, $bindings = []);

    /**
     * @desc 对数据库运行删除语句
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function delete($query, $bindings = []);

    /**
     * @desc 执行SQL语句并返回布尔结果
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function statement($query, $bindings = []);

    /**
     * @desc 运行SQL语句并获得受影响的行数
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = []);

    /**
     * @desc 对PDO连接运行一个原始的、未准备好的查询
     * @param string $query
     * @return bool
     */
    public function unprepared($query);

    /**
     * @desc 为执行准备查询绑定
     * @param array $bindings
     * @return array
     */
    public function prepareBindings(array $bindings);

    /**
     * @desc 在事务中执行一个闭包
     * @param Closure $callback
     * @param int $attempts
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1);

    /**
     * @desc 启动一个新的数据库事务。
     * @return void
     */
    public function beginTransaction();

    /**
     * @desc 提交活动数据库事务
     * @return void
     */
    public function commit();

    /**
     * @desc 回滚活动数据库事务
     * @return void
     */
    public function rollBack();

    /**
     * @desc 获取活动事务的数量
     * @return int
     */
    public function transactionLevel();

    /**
     * @desc 以“演练”模式执行给定的回调
     * @param Closure $callback
     * @return array
     */
    public function pretend(Closure $callback);

    /**
     * @desc 获取连接数据库的名称
     * @return string
     */
    public function getDatabaseName();

    /**
     * @desc 获取当前PDO连接
     * @return PDO
     */
    public function getPdo();

    /**
     * @desc 获取连接使用的查询语法
     * @return MySqlGrammar
     */
    public function getQueryGrammar();

    /**
     * @desc 获取连接使用的查询后处理器
     * @return MysqlProcessor
     */
    public function getPostProcessor();
}
