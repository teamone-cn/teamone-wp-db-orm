<?php

namespace Teamone\TeamoneWpDbOrm;

use Closure;

class DatabaseTransactionsManager
{
    /**
     * @var array 所有的事务记录
     */
    protected $transactions;

    /**
     * @desc 创建数据库事务管理器
     */
    public function __construct()
    {
        $this->transactions = [];
    }

    /**
     * @desc 开始新的数据库事务
     * @param string $connection
     * @param int $level
     * @return void
     */
    public function begin($connection, $level)
    {
        array_push($this->transactions, new DatabaseTransactionRecord($connection, $level));
    }

    /**
     * @desc 回滚活跃的数据库事务
     * @param string $connection
     * @param int $level
     * @return void
     */
    public function rollback($connection, $level)
    {
        $callback = function ($transaction) use ($connection, $level){
            return $transaction->connection == $connection && $transaction->level > $level;
        };

        $transactions = $this->reject($this->transactions, $callback);

        $this->transactions = $transactions;
    }

    /**
     * @desc 创建未通过给定真值测试的所有元素的集合
     * @param callable|mixed $callback
     * @return array
     */
    protected function reject($items, $callback = true)
    {
        $useAsCallable = !is_string($callback) && is_callable($callback);

        return $this->filter($items, function ($value, $key) use ($callback, $useAsCallable){
            if ($useAsCallable) {
                return !$callback($value, $key);
            } else {
                return true;
            }
        });
    }

    /**
     * @desc 对每个项目运行过滤器
     * @param array $items
     * @param callable|null $callback
     * @return array
     */
    protected function filter(array $items, callable $callback = null)
    {
        return array_filter($items, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @desc 提交活跃的数据库事务
     * @param string $connection
     * @return void
     */
    public function commit($connection)
    {
        [$forThisConnection, $forOtherConnections] = $this->partition($this->transactions, function ($transaction) use ($connection){
            return $transaction->connection == $connection;
        });

        $this->transactions = array_values($forOtherConnections);

        if ($forThisConnection instanceof DatabaseTransactionRecord) {
            $forThisConnection->executeCallbacks();
        }
    }

    /**
     * @desc 注册事务回调
     * @param Closure $callback
     */
    public function addCallback(Closure $callback)
    {
        reset($this->transactions);

        if ($current = end($this->transactions)) {
            return $current->addCallback($callback);
        }

        call_user_func($callback);
    }

    /**
     * @desc 获取所有事务
     * @return array
     */
    public function getTransactions()
    {
        return $this->transactions;
    }

    /**
     * @desc 使用给定的回调或键将集合分成两个数组
     * @param array $transactions
     * @param Closure $callback
     * @return array[]
     */
    protected function partition(array $transactions, Closure $callback)
    {
        $passed = [];
        $failed = [];

        foreach ($transactions as $key => $item) {
            if ($callback($item, $key)) {
                $passed[$key] = $item;
            } else {
                $failed[$key] = $item;
            }
        }

        return [$passed, $failed];
    }
}
