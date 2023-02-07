<?php

namespace Teamone\TeamoneWpDbOrm;

class DatabaseTransactionRecord
{
    /**
     * @var string 数据库连接的名称
     */
    public $connection;

    /**
     * @var int 事务级别
     */
    public $level;

    /**
     * @var array 提交后应该执行的回调
     */
    protected $callbacks = [];

    /**
     * @desc 创建一个新的数据库事务记录实例
     * @param  string  $connection
     * @param  int  $level
     * @return void
     */
    public function __construct($connection, $level)
    {
        $this->connection = $connection;
        $this->level = $level;
    }

    /**
     * @desc 注册一个回调，在提交后执行
     * @param  callable  $callback
     * @return void
     */
    public function addCallback($callback)
    {
        $this->callbacks[] = $callback;
    }

    /**
     * @desc 执行所有回调
     */
    public function executeCallbacks()
    {
        foreach ($this->callbacks as $callback) {
            call_user_func($callback);
        }
    }

    /**
     * @desc 获取所有回调
     * @return array
     */
    public function getCallbacks()
    {
        return $this->callbacks;
    }
}
