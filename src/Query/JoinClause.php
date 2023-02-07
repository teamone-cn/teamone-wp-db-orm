<?php

namespace Teamone\TeamoneWpDbOrm\Query;

use Closure;
use Teamone\TeamoneWpDbOrm\ConnectionInterface;
use Teamone\TeamoneWpDbOrm\Query\Grammars\Grammar;
use Teamone\TeamoneWpDbOrm\Query\Processors\Processor;
use InvalidArgumentException;

class JoinClause extends Builder
{
    /**
     * @var string 正在执行的连接类型
     */
    public $type;

    /**
     * @var string join子句要连接的表
     */
    public $table;

    /**
     * @var ConnectionInterface
     */
    protected $parentConnection;

    /**
     * @var Grammar
     */
    protected $parentGrammar;

    /**
     * @var Processor
     */
    protected $parentProcessor;

    /**
     * @var string 父查询生成器的类名
     */
    protected $parentClass;

    /**
     * @desc 创建一个新的join子句实例
     * @param Builder $parentQuery
     * @param string $type
     * @param string $table
     * @return void
     */
    public function __construct(Builder $parentQuery, $type, $table)
    {
        $this->type             = $type;
        $this->table            = $table;
        $this->parentClass      = get_class($parentQuery);
        $this->parentGrammar    = $parentQuery->getGrammar();
        $this->parentProcessor  = $parentQuery->getProcessor();
        $this->parentConnection = $parentQuery->getConnection();

        parent::__construct($this->parentConnection, $this->parentGrammar, $this->parentProcessor);
    }

    /**
     * @desc 在连接中添加一个“on”子句。On子句可以连用，例如:
     *
     * $join->on('contacts.user_id', '=', 'users.id')
     *      ->on('contacts.info_id', '=', 'info.id')
     *
     * 将产生以下SQL:
     *
     * on `contacts`.`user_id` = `users`.`id` and `contacts`.`info_id` = `info`.`id`
     *
     * @param Closure|string $first
     * @param string|null $operator
     * @param Expression|string|null $second
     * @param string $boolean
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function on($first, $operator = null, $second = null, $boolean = 'and')
    {
        if ($first instanceof Closure) {
            return $this->whereNested($first, $boolean);
        }

        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    /**
     * @desc 在连接中添加一个“or on”子句
     * @param Closure|string $first
     * @param string|null $operator
     * @param Expression|string|null $second
     * @return JoinClause
     */
    public function orOn($first, $operator = null, $second = null)
    {
        return $this->on($first, $operator, $second, 'or');
    }

    /**
     * @desc 获取连接子句构建器的一个新实例
     * @return JoinClause
     */
    public function newQuery()
    {
        return new static($this->newParentQuery(), $this->type, $this->table);
    }

    /**
     * @desc 为子查询创建一个新的查询实例
     * @return Builder
     */
    protected function forSubQuery()
    {
        return $this->newParentQuery()->newQuery();
    }

    /**
     * @desc 创建一个新的父查询实例
     * @return Builder
     */
    protected function newParentQuery()
    {
        $class = $this->parentClass;

        return new $class($this->parentConnection, $this->parentGrammar, $this->parentProcessor);
    }
}
