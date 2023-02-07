<?php

namespace Teamone\TeamoneWpDbOrm;

use Teamone\TeamoneWpDbOrm\Query\Grammars\MySqlGrammar;
use Teamone\TeamoneWpDbOrm\Query\Processors\MySqlProcessor;

class MySqlConnection extends Connection
{
    /**
     * @desc 选择默认事务处理器
     */
    public function useDefaultTransactionsManager()
    {
        $this->transactionsManager = new DatabaseTransactionsManager();
    }

    /**
     * @desc 将查询语法设置为默认实现
     * @return void
     */
    public function useDefaultQueryGrammar()
    {
        $grammar = new MySqlGrammar();

        $grammar->setTablePrefix($this->tablePrefix);

        $this->queryGrammar = $grammar;
    }

    /**
     * @desc 将查询后处理器设置为默认实现
     * @return void
     */
    public function useDefaultPostProcessor()
    {
        $this->postProcessor = new MySqlProcessor();
    }
}
