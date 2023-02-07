<?php

namespace Teamone\TeamoneWpDbOrm\Query;

use BadMethodCallException;
use Closure;
use DateTimeInterface;
use Error;
use Generator;
use InvalidArgumentException;
use Teamone\TeamoneWpDbOrm\Concerns\BuildsQueries;
use Teamone\TeamoneWpDbOrm\ConnectionInterface;
use Teamone\TeamoneWpDbOrm\GeneralUtil;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Arrayable;
use Teamone\TeamoneWpDbOrm\Pagination\Cursor;
use Teamone\TeamoneWpDbOrm\Pagination\CursorPaginator;
use Teamone\TeamoneWpDbOrm\Pagination\LengthAwarePaginator;
use Teamone\TeamoneWpDbOrm\Pagination\Paginator;
use Teamone\TeamoneWpDbOrm\Query\Grammars\Grammar;
use Teamone\TeamoneWpDbOrm\Query\Processors\Processor;
use RuntimeException;
use Teamone\TeamoneWpDbOrm\Query\Builder as EloquentBuilder;

class Builder
{
    use BuildsQueries;

    /**
     * @var ConnectionInterface 数据库连接实例
     */
    public $connection;

    /**
     * @var Grammar 数据库查询语法实例
     */
    public $grammar;

    /**
     * @varProcessor 数据库查询处理器实例
     */
    public $processor;

    /**
     * @var array[] 当前查询值绑定
     */
    public $bindings = [
        'select'     => [],
        'from'       => [],
        'join'       => [],
        'where'      => [],
        'groupBy'    => [],
        'having'     => [],
        'order'      => [],
        'union'      => [],
        'unionOrder' => [],
    ];

    /**
     * @var array 要运行的聚合函数和列
     */
    public $aggregate;

    /**
     * @var array 应该返回的列
     */
    public $columns;

    /**
     * @var bool|array 查询是否返回不同的结果
     */
    public $distinct = false;

    /**
     * @var string 查询所针对的表
     */
    public $from;

    /**
     * @var array 表连接查询
     */
    public $joins;

    /**
     * @var array 查询的 where 条件
     */
    public $wheres = [];

    /**
     * @var array 查询的分组
     */
    public $groups;

    /**
     * @var array 查询的约束条件
     */
    public $havings;

    /**
     * @var array 查询的排序
     */
    public $orders;

    /**
     * @var int 要返回的最大记录数
     */
    public $limit;

    /**
     * @var int 要跳过的记录数
     */
    public $offset;

    /**
     * @var array 查询联合语句
     */
    public $unions;

    /**
     * @var int 要返回的联合记录的最大数目
     */
    public $unionLimit;

    /**
     * @var int 要跳过的联合记录的数目
     */
    public $unionOffset;

    /**
     * @var array 联合查询的顺序
     */
    public $unionOrders;

    /**
     * @var string|bool 指示是否正在使用行锁定
     */
    public $lock;

    /**
     * @var array 在执行查询之前应该调用的回调
     */
    public $beforeQueryCallbacks = [];

    /**
     * @var string[] 所有可用的子句操作符
     */
    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>', '&~',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    /**
     * @var string[] 所有可用的位操作符
     */
    public $bitwiseOperators = [
        '&', '|', '^', '<<', '>>', '&~',
    ];

    /**
     * @var bool 是否使用只写 pdo
     */
    public $useWritePdo = false;

    /**
     * @desc 创建一个新的查询构建器实例
     * @param ConnectionInterface $connection
     * @param Grammar $grammar
     * @param Processor $processor
     */
    public function __construct(ConnectionInterface $connection, Grammar $grammar, Processor $processor)
    {
        $this->connection = $connection;
        $this->grammar    = $grammar ? : $connection->getQueryGrammar();
        $this->processor  = $processor ? : $connection->getPostProcessor();
    }

    /**
     * @desc 设置要选择的列
     * @param array|mixed $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->columns            = [];
        $this->bindings['select'] = [];
        $columns                  = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $as => $column) {
            if (is_string($as) && $this->isQueryable($column)) {
                $this->selectSub($column, $as);
            } else {
                $this->columns[] = $column;
            }
        }

        return $this;
    }

    /**
     * @desc 向查询添加子选择表达式
     * @param Closure|Builder|EloquentBuilder|string $query
     * @param string $as
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function selectSub($query, $as)
    {
        [$query, $bindings] = $this->createSub($query);

        return $this->selectRaw(
            '(' . $query . ') as ' . $this->grammar->wrap($as), $bindings
        );
    }

    /**
     * @desc 向查询添加一个新的"原始"选择表达式
     * @param string $expression
     * @param array $bindings
     * @return $this
     */
    public function selectRaw($expression, array $bindings = [])
    {
        $this->addSelect(new Expression($expression));

        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    /**
     * @desc 从子查询中获取"from"
     * @param Closure|Builder|string $query
     * @param string $as
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function fromSub($query, $as)
    {
        [$query, $bindings] = $this->createSub($query);

        return $this->fromRaw('(' . $query . ') as ' . $this->grammar->wrapTable($as), $bindings);
    }

    /**
     * @desc 向查询添加一个原始的 from 子句
     * @param string $expression
     * @param mixed $bindings
     * @return $this
     */
    public function fromRaw($expression, $bindings = [])
    {
        $this->from = new Expression($expression);

        $this->addBinding($bindings, 'from');

        return $this;
    }

    /**
     * @desc 创建一个子查询并解析它
     * @param Closure|Builder|string $query
     * @return array
     */
    protected function createSub($query)
    {
        // 如果给定的查询是一个闭包，我们将在传入一个新的时执行它
        // 查询实例到闭包。 这将使开发人员有机会
        // 在将查询转换为原始 SQL 字符串之前格式化并处理查询。
        if ($query instanceof Closure) {
            $callback = $query;

            $callback($query = $this->forSubQuery());
        }

        return $this->parseSub($query);
    }

    /**
     * @desc 将子查询解析为 SQL 和绑定
     * @param mixed $query
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function parseSub($query)
    {
        if ($query instanceof self) {
            $query = $this->prependDatabaseNameIfCrossDatabaseQuery($query);

            return [$query->toSql(), $query->getBindings()];
        } elseif (is_string($query)) {
            return [$query, []];
        } else {
            throw new InvalidArgumentException('A subquery must be a query builder instance, a Closure, or a string.');
        }
    }

    /**
     * @desc 如果给定的查询在另一个数据库上，则在数据库名称前添加
     * @param mixed $query
     * @return mixed
     */
    protected function prependDatabaseNameIfCrossDatabaseQuery($query)
    {
        if ($query->getConnection()->getDatabaseName() !==
            $this->getConnection()->getDatabaseName()) {
            $databaseName = $query->getConnection()->getDatabaseName();

            if (strpos($query->from, $databaseName) !== 0 && strpos($query->from, '.') === false) {
                $query->from($databaseName . '.' . $query->from);
            }
        }

        return $query;
    }

    /**
     * @desc 向查询添加一个新的选择列
     * @param array|mixed $column
     * @return $this
     */
    public function addSelect($column)
    {
        $columns = is_array($column) ? $column : func_get_args();

        foreach ($columns as $as => $column) {
            if (is_string($as) && $this->isQueryable($column)) {
                if (is_null($this->columns)) {
                    $this->select($this->from . '.*');
                }

                $this->selectSub($column, $as);
            } else {
                $this->columns[] = $column;
            }
        }

        return $this;
    }

    /**
     * @desc 强制查询只返回不同的结果
     * @param ...$distinct
     * @return $this
     */
    public function distinct(...$distinct)
    {
        $columns = is_array($distinct) && !empty($distinct) ? $distinct : func_get_args();

        if (count($columns) > 0) {
            $this->distinct = is_array($columns[0]) || is_bool($columns[0]) ? $columns[0] : $columns;
        } else {
            $this->distinct = true;
        }

        return $this;
    }

    /**
     * @desc 设置查询的目标表。
     * @param Closure|Builder|string $table
     * @param string|null $as
     * @return $this
     */
    public function from($table, $as = null)
    {
        if ($this->isQueryable($table)) {
            return $this->fromSub($table, $as);
        }

        $this->from = $as ? "{$table} as {$as}" : $table;

        return $this;
    }

    /**
     * @desc 向查询添加连接子句。
     * @param string $table
     * @param Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $type
     * @param bool $where
     * @return $this
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $join = $this->newJoinClause($this, $type, $table);

        // 如果连接的第一个"列"真的是一个 Closure 实例开发者
        // 正在尝试使用包含超过
        //  一个条件，所以我们将添加连接并使用查询调用闭包。
        if ($first instanceof Closure) {
            $first($join);

            $this->joins[] = $join;

            $this->addBinding($join->getBindings(), 'join');
        } else {
            // 如果列只是一个字符串，我们可以假设连接只是有一个基本的
            // 带有单一条件的"on"子句。 所以我们将建立连接
            // 这个简单的连接子句附在它上面。 没有加入回调。
            $method = $where ? 'where' : 'on';

            $this->joins[] = $join->$method($first, $operator, $second);

            $this->addBinding($join->getBindings(), 'join');
        }

        return $this;
    }

    /**
     * @desc 向查询中添加"join where"子句
     * @param string $table
     * @param Closure|string $first
     * @param string $operator
     * @param string $second
     * @param string $type
     * @return $this
     */
    public function joinWhere($table, $first, $operator, $second, $type = 'inner')
    {
        return $this->join($table, $first, $operator, $second, $type, true);
    }

    /**
     * @desc 向查询添加子查询连接子句
     * @param Closure|Builder|EloquentBuilder|string $query
     * @param string $as
     * @param Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $type
     * @param bool $where
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        [$query, $bindings] = $this->createSub($query);

        $expression = '(' . $query . ') as ' . $this->grammar->wrapTable($as);

        $this->addBinding($bindings, 'join');

        return $this->join(new Expression($expression), $first, $operator, $second, $type, $where);
    }

    /**
     * @desc 向查询添加左连接
     * @param string $table
     * @param Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     * @return $this
     */
    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * @desc 向查询中添加"join where"子句
     * @param string $table
     * @param Closure|string $first
     * @param string $operator
     * @param string $second
     * @return $this
     */
    public function leftJoinWhere($table, $first, $operator, $second)
    {
        return $this->joinWhere($table, $first, $operator, $second, 'left');
    }

    /**
     * @desc 向查询添加子查询左连接
     * @param Closure|Builder|EloquentBuilder|string $query
     * @param string $as
     * @param Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     * @return $this
     */
    public function leftJoinSub($query, $as, $first, $operator = null, $second = null)
    {
        return $this->joinSub($query, $as, $first, $operator, $second, 'left');
    }

    /**
     * @desc 向查询添加右连接
     * @param string $table
     * @param Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     * @return $this
     */
    public function rightJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * @desc 向查询添加"右连接位置"子句。
     * @param string $table
     * @param Closure|string $first
     * @param string $operator
     * @param string $second
     * @return $this
     */
    public function rightJoinWhere($table, $first, $operator, $second)
    {
        return $this->joinWhere($table, $first, $operator, $second, 'right');
    }

    /**
     * @desc 向查询添加子查询右连接
     * @param Closure|Builder|EloquentBuilder|string $query
     * @param string $as
     * @param Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     * @return $this
     */
    public function rightJoinSub($query, $as, $first, $operator = null, $second = null)
    {
        return $this->joinSub($query, $as, $first, $operator, $second, 'right');
    }

    /**
     * @desc 向查询添加"交叉连接"子句
     * @param string $table
     * @param Closure|string|null $first
     * @param string|null $operator
     * @param string|null $second
     * @return $this
     */
    public function crossJoin($table, $first = null, $operator = null, $second = null)
    {
        if ($first) {
            return $this->join($table, $first, $operator, $second, 'cross');
        }

        $this->joins[] = $this->newJoinClause($this, 'cross', $table);

        return $this;
    }

    /**
     * @desc 向查询添加子查询交叉连接
     * @param Closure|Builder|string $query
     * @param string $as
     * @return $this
     */
    public function crossJoinSub($query, $as)
    {
        [$query, $bindings] = $this->createSub($query);

        $expression = '(' . $query . ') as ' . $this->grammar->wrapTable($as);

        $this->addBinding($bindings, 'join');

        $this->joins[] = $this->newJoinClause($this, 'cross', new Expression($expression));

        return $this;
    }

    /**
     * @desc 获取一个新的连接子句
     * @param Builder $parentQuery
     * @param string $type
     * @param string $table
     * @return JoinClause
     */
    protected function newJoinClause(self $parentQuery, $type, $table)
    {
        return new JoinClause($parentQuery, $type, $table);
    }

    /**
     * @desc 合并一组 where 子句和绑定
     * @param array $wheres
     * @param array $bindings
     * @return void
     */
    public function mergeWheres($wheres, $bindings)
    {
        $this->wheres = array_merge($this->wheres, (array) $wheres);

        $this->bindings['where'] = array_values(
            array_merge($this->bindings['where'], (array) $bindings)
        );
    }

    /**
     * @desc 向查询添加 where 子句
     * @param Closure|string|array $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // 如果列是一个数组，我们将假设它是一个键值对数组，并可以将它们分别作为where子句添加。
        // 我们将维护调用方法时接收到的布尔值，并将其传递到嵌套的where中。
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // 这里我们将对运算符做一些假设。 如果只有 2 个值
        // 传递给方法，我们将假设运算符是等号并继续。 否则，我们将要求传入运算符。
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        // 如果列实际上是一个 Closure 实例，我们将假定开发人员 想要开始嵌套在括号中的 where 语句。
        // 我们会将闭包添加到查询中，然后立即返回。
        if ($column instanceof Closure && is_null($operator)) {
            return $this->whereNested($column, $boolean);
        }

        // 如果该列是一个 Closure 实例并且有一个运算符值，我们将
        // 假设开发人员想要运行一个子查询然后比较结果
        // 该子查询具有提供给该方法的给定值。
        if ($this->isQueryable($column) && !is_null($operator)) {
            [$sub, $bindings] = $this->createSub($column);

            return $this->addBinding($bindings, 'where')
                ->where(new Expression('(' . $sub . ')'), $operator, $value, $boolean);
        }

        // 如果在有效运算符列表中找不到给定的运算符，我们将
        // 假设开发人员只是简化了"="运算符，并且
        // 我们会将运算符设置为"="并适当地设置值。
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        // 如果值为闭包，则意味着开发人员正在执行一个完整的
        // 查询中的子选择，我们需要编译子选择
        // 在 where 子句中获取适当的查询记录结果。
        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // 如果值为"null"，我们将假定开发人员想要添加一个
        // where null 子句到查询。 所以，我们将在这里允许一个捷径
        // 该方法是为了方便，因此开发人员不必检查。
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        $type = 'Basic';

        // 如果该列正在生成 JSON 引用，我们将检查该值是否
        // 是一个布尔值。 如果是，我们将添加原始布尔字符串作为实际
        // 查询的值以确保查询正确处理。
        if (mb_strpos($column, '->') !== false && is_bool($value)) {
            $value = new Expression($value ? 'true' : 'false');

            if (is_string($column)) {
                $type = 'JsonBoolean';
            }
        }

        if ($this->isBitwiseOperator($operator)) {
            $type = 'Bitwise';
        }

        // 现在我们正在处理一个简单的查询，我们可以将元素
        // 在我们的数组中，并将查询绑定添加到我们的绑定数组中
        // 最终执行时将绑定到每个 SQL 语句。
        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );

        if ( !$value instanceof Expression) {
            $this->addBinding($this->flattenValue($value), 'where');
        }

        return $this;
    }

    /**
     * @desc 向查询添加一个 where 子句数组
     * @param array $column
     * @param string $boolean
     * @param string $method
     * @return $this
     */
    protected function addArrayOfWheres($column, $boolean, $method = 'where')
    {
        return $this->whereNested(function ($query) use ($column, $method, $boolean){
            foreach ($column as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    $query->{$method}(...array_values($value));
                } else {
                    $query->$method($key, '=', $value, $boolean);
                }
            }
        }, $boolean);
    }

    /**
     * @desc 为 where 子句准备值和运算符
     * @param string $value
     * @param string $operator
     * @param bool $useDefault
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function prepareValueAndOperator($value, $operator, $useDefault = false)
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * @desc 确定给定的运算符和值组合是否合法，防止将 Null 值与无效运算符一起使用
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        return is_null($value) && in_array($operator, $this->operators) && !in_array($operator, ['=', '<>', '!=']);
    }

    /**
     * @desc 确定是否支持给定的运算符
     * @param string $operator
     * @return bool
     */
    protected function invalidOperator($operator)
    {
        return !is_string($operator)
               || ( !in_array(strtolower($operator), $this->operators, true)
                    && !in_array(strtolower($operator), $this->grammar->getOperators(), true));
    }

    /**
     * @desc 判断运算符是否为位运算符
     * @param string $operator
     * @return bool
     */
    protected function isBitwiseOperator($operator)
    {
        return in_array(strtolower($operator), $this->bitwiseOperators, true)
               || in_array(strtolower($operator), $this->grammar->getBitwiseOperators(), true);
    }

    /**
     * @desc 向查询添加 "or where" 子句
     * @param Closure|string|array $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * 添加一个"where"子句，将两列与查询进行比较
     * @param string|array $first
     * @param string|null $operator
     * @param string|null $second
     * @param string|null $boolean
     * @return $this
     */
    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and')
    {
        // 如果该列是一个数组，我们将假定它是一个键值对数组，并且可以将它们分别添加为一个 where 子句。
        // 我们将维护调用方法时收到的布尔值，并将其传递到嵌套的 where 中。
        if (is_array($first)) {
            return $this->addArrayOfWheres($first, $boolean, 'whereColumn');
        }

        // 如果在有效运算符列表中找不到给定的运算符，我们将假定开发人员只是在简化"="运算符，我们会将运算符设置为"="并适当地设置值。
        if ($this->invalidOperator($operator)) {
            [$second, $operator] = [$operator, '='];
        }

        // 最后，我们将把这个 where 子句添加到我们为查询构建的子句数组中。一旦查询即将执行并针对数据库运行，所有这些都将通过语法进行编译。
        $type = 'Column';

        $this->wheres[] = compact(
            'type', 'first', 'operator', 'second', 'boolean'
        );

        return $this;
    }

    /**
     * 添加一个"or where"子句，将两列与查询进行比较
     * @param string|array $first
     * @param string|null $operator
     * @param string|null $second
     * @return $this
     */
    public function orWhereColumn($first, $operator = null, $second = null)
    {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    /**
     * 向查询添加原始 where 子句
     * @param string $sql
     * @param mixed $bindings
     * @param string $boolean
     * @return $this
     */
    public function whereRaw($sql, $bindings = [], $boolean = 'and')
    {
        $this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];

        $this->addBinding((array) $bindings, 'where');

        return $this;
    }

    /**
     * 向查询添加原始或 where 子句
     * @param string $sql
     * @param mixed $bindings
     * @return $this
     */
    public function orWhereRaw($sql, $bindings = [])
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    /**
     * 向查询添加"where in"子句
     * @param string $column
     * @param mixed $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotIn' : 'In';

        // 如果该值是一个查询生成器实例，我们将假设开发人员想要查找存在于该给定查询中的任何值。 所以我们将相应地添加查询，以便在运行时正确执行此查询。
        if ($this->isQueryable($values)) {
            [$query, $bindings] = $this->createSub($values);

            $values = [new Expression($query)];

            $this->addBinding($bindings, 'where');
        }

        // 接下来，如果该值是 Arrayable，我们需要将其转换为原始数组形式，这样我们就有了底层数组值，而不是 Arrayable 对象，它不能作为绑定等添加。然后我们将添加到 wheres 数组 .
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        // 最后，我们将为每个值添加一个绑定，除非该值是一个表达式，在这种情况下我们将跳过它，因为它将作为原始字符串进行查询，而不是作为要由 PDO 替换的参数化占位符。
        $this->addBinding($this->cleanBindings($values), 'where');

        return $this;
    }

    /**
     * @desc 向查询添加 " or where in " 子句
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * @desc 向查询添加 "where not in" 子句
     * @param string $column
     * @param mixed $values
     * @param string $boolean
     * @return $this
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * @desc 向查询添加 "or where not in" 子句
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function orWhereNotIn($column, $values)
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * @desc 为查询添加整数值的"where in raw"子句
     * @param string $column
     * @param Arrayable|array $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereIntegerInRaw($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotInRaw' : 'InRaw';

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        foreach ($values as &$value) {
            $value = (int) $value;
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        return $this;
    }

    /**
     * @desc 为查询添加整数值的“or where in raw”子句
     * @param string $column
     * @param Arrayable|array $values
     * @return $this
     */
    public function orWhereIntegerInRaw($column, $values)
    {
        return $this->whereIntegerInRaw($column, $values, 'or');
    }

    /**
     * @desc 为查询添加整数值的“where not in raw”子句
     * @param string $column
     * @param Arrayable|array $values
     * @param string $boolean
     * @return $this
     */
    public function whereIntegerNotInRaw($column, $values, $boolean = 'and')
    {
        return $this->whereIntegerInRaw($column, $values, $boolean, true);
    }

    /**
     * @desc 为查询的整数值添加一个“or where not in raw”子句
     * @param string $column
     * @param Arrayable|array $values
     * @return $this
     */
    public function orWhereIntegerNotInRaw($column, $values)
    {
        return $this->whereIntegerNotInRaw($column, $values, 'or');
    }

    /**
     * @desc 向查询添加“where null”子句
     * @param string|array $columns
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotNull' : 'Null';

        foreach (GeneralUtil::wrap($columns) as $column) {
            $this->wheres[] = compact('type', 'column', 'boolean');
        }

        return $this;
    }

    /**
     * @desc 向查询添加“or where null”子句
     * @param string|array $column
     * @return $this
     */
    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * @desc 向查询添加“or where not null”子句
     * @param string|array $columns
     * @param string $boolean
     * @return $this
     */
    public function whereNotNull($columns, $boolean = 'and')
    {
        return $this->whereNull($columns, $boolean, true);
    }

    /**
     * @desc 在查询中添加一个 where between 语句
     * @param string|Expression $column
     * @param array $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');

        $this->addBinding(array_slice($this->cleanBindings(GeneralUtil::flatten($values)), 0, 2), 'where');

        return $this;
    }

    /**
     * @desc 使用列向查询添加 where between 语句
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereBetweenColumns($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'betweenColumns';

        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');

        return $this;
    }

    /**
     * @desc 在查询中添加 or where between 语句
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function orWhereBetween($column, array $values)
    {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * @desc 使用列向查询添加 or where between 语句
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function orWhereBetweenColumns($column, array $values)
    {
        return $this->whereBetweenColumns($column, $values, 'or');
    }

    /**
     * @desc 在查询中添加一个 where not between 语句
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @return $this
     */
    public function whereNotBetween($column, array $values, $boolean = 'and')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * @desc 使用列向查询添加 where not between 语句
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @return $this
     */
    public function whereNotBetweenColumns($column, array $values, $boolean = 'and')
    {
        return $this->whereBetweenColumns($column, $values, $boolean, true);
    }

    /**
     * @desc 在查询中添加 or where not between 语句
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function orWhereNotBetween($column, array $values)
    {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /**
     * @desc 使用列向查询添加 or where not between 语句
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function orWhereNotBetweenColumns($column, array $values)
    {
        return $this->whereNotBetweenColumns($column, $values, 'or');
    }

    /**
     * @desc 向查询添加“or where not null”子句
     * @param string $column
     * @return $this
     */
    public function orWhereNotNull($column)
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * @desc 在查询中添加“where date”语句
     * @param Expression|string $column
     * @param string $operator
     * @param DateTimeInterface|string|null $value
     * @param string $boolean
     * @return $this
     */
    public function whereDate($column, $operator, $value = null, $boolean = 'and')
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        return $this->addDateBasedWhere('Date', $column, $operator, $value, $boolean);
    }

    /**
     * @desc 在查询中添加“或 where date”语句
     * @param string $column
     * @param string $operator
     * @param DateTimeInterface|string|null $value
     * @return $this
     */
    public function orWhereDate($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->whereDate($column, $operator, $value, 'or');
    }

    /**
     * @desc 在查询中添加“where time”语句
     * @param string $column
     * @param string $operator
     * @param DateTimeInterface|string|null $value
     * @param string $boolean
     * @return $this
     */
    public function whereTime($column, $operator, $value = null, $boolean = 'and')
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('H:i:s');
        }

        return $this->addDateBasedWhere('Time', $column, $operator, $value, $boolean);
    }

    /**
     * @desc 在查询中添加“或 where time”语句
     * @param string $column
     * @param string $operator
     * @param DateTimeInterface|string|null $value
     * @return $this
     */
    public function orWhereTime($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->whereTime($column, $operator, $value, 'or');
    }

    /**
     * @desc 在查询中添加“where day”语句
     * @param string $column
     * @param string $operator
     * @param DateTimeInterface|string|null $value
     * @param string $boolean
     * @return $this
     */
    public function whereDay($column, $operator, $value = null, $boolean = 'and')
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('d');
        }

        if ( !$value instanceof Expression) {
            $value = str_pad($value, 2, '0', STR_PAD_LEFT);
        }

        return $this->addDateBasedWhere('Day', $column, $operator, $value, $boolean);
    }

    /**
     * @desc 向查询中添加“or where day”语句
     * @param string $column
     * @param string $operator
     * @param DateTimeInterface|string|null $value
     * @return $this
     */
    public function orWhereDay($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->whereDay($column, $operator, $value, 'or');
    }

    /**
     * @desc 在查询中添加“where month”语句
     * @param string $column
     * @param string $operator
     * @param DateTimeInterface|string|null $value
     * @param string $boolean
     * @return $this
     */
    public function whereMonth($column, $operator, $value = null, $boolean = 'and')
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('m');
        }

        if ( !$value instanceof Expression) {
            $value = str_pad($value, 2, '0', STR_PAD_LEFT);
        }

        return $this->addDateBasedWhere('Month', $column, $operator, $value, $boolean);
    }

    /**
     * @desc 向查询中添加“或 where month”语句
     * @param string $column
     * @param string $operator
     * @param DateTimeInterface|string|null $value
     * @return $this
     */
    public function orWhereMonth($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->whereMonth($column, $operator, $value, 'or');
    }

    /**
     * @desc 在查询中添加“where year”语句
     * @param string $column
     * @param string $operator
     * @param DateTimeInterface|string|int|null $value
     * @param string $boolean
     * @return $this
     */
    public function whereYear($column, $operator, $value = null, $boolean = 'and')
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $value = $this->flattenValue($value);

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y');
        }

        return $this->addDateBasedWhere('Year', $column, $operator, $value, $boolean);
    }

    /**
     * @desc 在查询中添加“或 where year”语句
     * @param string $column
     * @param string $operator
     * @param DateTimeInterface|string|int|null $value
     * @return $this
     */
    public function orWhereYear($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->whereYear($column, $operator, $value, 'or');
    }

    /**
     * @desc 向查询中添加基于日期（年、月、日、时间）的语句
     * @param string $type
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and')
    {
        $this->wheres[] = compact('column', 'type', 'boolean', 'operator', 'value');

        if ( !$value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * @desc 向查询添加嵌套的 where 语句
     * @param Closure $callback
     * @param string $boolean
     * @return $this
     */
    public function whereNested(Closure $callback, $boolean = 'and')
    {
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addNestedWhereQuery($query, $boolean);
    }

    /**
     * @desc 为嵌套的 where 条件创建一个新的查询实例
     * @return Builder
     */
    public function forNestedWhere()
    {
        return $this->newQuery()->from($this->from);
    }

    /**
     * @desc 添加另一个查询构建器作为查询构建器的嵌套位置
     * @param Builder $query
     * @param string $boolean
     * @return $this
     */
    public function addNestedWhereQuery($query, $boolean = 'and')
    {
        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getRawBindings()['where'], 'where');
        }

        return $this;
    }

    /**
     * @desc 向查询添加完整的子选择
     * @param string $column
     * @param string $operator
     * @param Closure $callback
     * @param string $boolean
     * @return $this
     */
    protected function whereSub($column, $operator, Closure $callback, $boolean)
    {
        $type = 'Sub';

        // 一旦我们有了查询实例，我们就可以简单地执行它，这样它就可以将所有子选择的条件添加到自身，
        // 然后我们可以将它缓存在“主要”父查询实例的 where 子句数组中。
        call_user_func($callback, $query = $this->forSubQuery());

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'query', 'boolean'
        );

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    /**
     * @desc 向查询添加一个 exists 子句
     * @param Closure $callback
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereExists(Closure $callback, $boolean = 'and', $not = false)
    {
        $query = $this->forSubQuery();

        // 与子选择子句类似，我们将创建一个新的查询实例，以便开发人员可以清楚地指定整个存在的查询，我们将在语法中编译整个内容并将其插入到 SQL 中。
        call_user_func($callback, $query);

        return $this->addWhereExistsQuery($query, $boolean, $not);
    }

    /**
     * @desc 向查询添加 or exists 子句
     * @param Closure $callback
     * @param bool $not
     * @return $this
     */
    public function orWhereExists(Closure $callback, $not = false)
    {
        return $this->whereExists($callback, 'or', $not);
    }

    /**
     * 向查询添加一个 where not exists 子句
     * @param Closure $callback
     * @param string $boolean
     * @return $this
     */
    public function whereNotExists(Closure $callback, $boolean = 'and')
    {
        return $this->whereExists($callback, $boolean, true);
    }

    /**
     * @desc 为查询添加 not exists 子句
     * @param Closure $callback
     * @return $this
     */
    public function orWhereNotExists(Closure $callback)
    {
        return $this->orWhereExists($callback, true);
    }

    /**
     * @desc 为查询添加 exists 子句
     * @param Builder $query
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function addWhereExistsQuery(self $query, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotExists' : 'Exists';

        $this->wheres[] = compact('type', 'query', 'boolean');

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    /**
     * @desc 使用 row values 添加 where 条件
     * @param array $columns
     * @param string $operator
     * @param array $values
     * @param string $boolean
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function whereRowValues($columns, $operator, $values, $boolean = 'and')
    {
        if (count($columns) !== count($values)) {
            throw new InvalidArgumentException('The number of columns must match the number of values');
        }

        $type = 'RowValues';

        $this->wheres[] = compact('type', 'columns', 'operator', 'values', 'boolean');

        $this->addBinding($this->cleanBindings($values));

        return $this;
    }

    /**
     * @desc 使用 row values 添加 or where 条件
     * @param array $columns
     * @param string $operator
     * @param array $values
     * @return $this
     */
    public function orWhereRowValues($columns, $operator, $values)
    {
        return $this->whereRowValues($columns, $operator, $values, 'or');
    }

    /**
     * @desc 在查询中添加"where JSON contains"子句
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereJsonContains($column, $value, $boolean = 'and', $not = false)
    {
        $type = 'JsonContains';

        $this->wheres[] = compact('type', 'column', 'value', 'boolean', 'not');

        if ( !$value instanceof Expression) {
            $this->addBinding($this->grammar->prepareBindingForJsonContains($value));
        }

        return $this;
    }

    /**
     * @desc 在查询中添加"or where JSON contains"子句
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function orWhereJsonContains($column, $value)
    {
        return $this->whereJsonContains($column, $value, 'or');
    }

    /**
     * @desc 在查询中添加"where JSON not contains"子句
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function whereJsonDoesntContain($column, $value, $boolean = 'and')
    {
        return $this->whereJsonContains($column, $value, $boolean, true);
    }

    /**
     * @desc 在查询中添加"or where JSON not contains"子句
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function orWhereJsonDoesntContain($column, $value)
    {
        return $this->whereJsonDoesntContain($column, $value, 'or');
    }

    /**
     * @desc 在查询中添加"where where JSON length"子句
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function whereJsonLength($column, $operator, $value = null, $boolean = 'and')
    {
        $type = 'JsonLength';

        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if ( !$value instanceof Expression) {
            $this->addBinding((int) $this->flattenValue($value));
        }

        return $this;
    }

    /**
     * @desc 在查询中添加"or where JSON length"子句
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function orWhereJsonLength($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->whereJsonLength($column, $operator, $value, 'or');
    }

    /**
     * @desc 处理查询的动态“where”子句
     * @param string $method
     * @param array $parameters
     * @return $this
     */
    public function dynamicWhere($method, $parameters)
    {
        $finder = substr($method, 5);

        $segments = preg_split(
            '/(And|Or)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE
        );

        // 连接器变量将确定哪个连接器将用于查询条件。 当我们在动态方法字符串中遇到新的布尔值时，我们将更改它，其中可能包含许多这样的值。
        $connector = 'and';

        $index = 0;

        foreach ($segments as $segment) {
            // 如果该段不是布尔连接器，我们可以假设它是一个列的名称，我们将把它作为一个新的约束添加到查询中作为一个 where 子句，
            // 然后我们可以再次迭代动态方法字符串的段。
            if ($segment !== 'And' && $segment !== 'Or') {
                $this->addDynamic($segment, $connector, $parameters, $index);

                $index++;
            } else {
                // 否则，我们将存储连接器，以便我们知道我们在查询中找到的下一个 where 子句应该如何连接到之前的子句，
                // 这意味着我们将有适当的布尔连接器来连接找到的下一个 where 子句。
                $connector = $segment;
            }
        }

        return $this;
    }

    /**
     * @desc 向查询添加单个动态 where 子句语句
     * @param string $segment
     * @param string $connector
     * @param array $parameters
     * @param int $index
     * @return void
     */
    protected function addDynamic($segment, $connector, $parameters, $index)
    {
        // 一旦我们解析出列并格式化布尔运算符，我们就可以将其作为 where 子句添加到此查询中，就像查询中的任何其他子句一样。 然后我们将增加参数索引值
        $bool = strtolower($connector);

        $this->where(GeneralUtil::snake($segment), '=', $parameters[$index], $bool);
    }

    /**
     * @desc 在查询中添加"where fulltext"子句
     * @param string|string[] $columns
     * @param string $value
     * @param string $boolean
     * @return $this
     */
    public function whereFullText($columns, $value, array $options = [], $boolean = 'and')
    {
        $type = 'Fulltext';

        $columns = (array) $columns;

        $this->wheres[] = compact('type', 'columns', 'value', 'options', 'boolean');

        $this->addBinding($value);

        return $this;
    }

    /**
     * @desc 在查询中添加"or where fulltext"子句
     * @param string|string[] $columns
     * @param string $value
     * @return $this
     */
    public function orWhereFullText($columns, $value, array $options = [])
    {
        return $this->whereFulltext($columns, $value, $options, 'or');
    }

    /**
     * @desc 在查询中添加"group by"子句
     * @param array|string ...$groups
     * @return $this
     */
    public function groupBy(...$groups)
    {
        foreach ($groups as $group) {
            $this->groups = array_merge(
                (array) $this->groups,
                GeneralUtil::wrap($group)
            );
        }

        return $this;
    }

    /**
     * @desc 在查询中添加原始"group by"子句
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function groupByRaw($sql, array $bindings = [])
    {
        $this->groups[] = new Expression($sql);

        $this->addBinding($bindings, 'groupBy');

        return $this;
    }

    /**
     * @desc 在查询中添加"having"子句
     * @param string $column
     * @param string|null $operator
     * @param string|null $value
     * @param string $boolean
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        $type = 'Basic';

        // 这里我们将对运算符做一些假设。 如果只有 2 个值传递给该方法，我们将假设运算符是等号并继续。 否则，我们将要求传入运算符。
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        // 如果在有效运算符列表中找不到给定的运算符，我们将，假设开发人员只是简化了“=”运算符，我们会将运算符设置为“=”并适当地设置值。
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        if ($this->isBitwiseOperator($operator)) {
            $type = 'Bitwise';
        }

        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if ( !$value instanceof Expression) {
            $this->addBinding($this->flattenValue($value), 'having');
        }

        return $this;
    }

    /**
     * @desc 在查询中添加"or having"子句
     * @param string $column
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function orHaving($column, $operator = null, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->having($column, $operator, $value, 'or');
    }

    /**
     * @desc 在查询中添加"having between"子句
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function havingBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->havings[] = compact('type', 'column', 'values', 'boolean', 'not');

        $this->addBinding(array_slice($this->cleanBindings(GeneralUtil::flatten($values)), 0, 2), 'having');

        return $this;
    }

    /**
     * @desc 向查询添加原始 having 子句
     * @param string $sql
     * @param array $bindings
     * @param string $boolean
     * @return $this
     */
    public function havingRaw($sql, array $bindings = [], $boolean = 'and')
    {
        $type = 'Raw';

        $this->havings[] = compact('type', 'sql', 'boolean');

        $this->addBinding($bindings, 'having');

        return $this;
    }

    /**
     * @desc 向查询添加原始或具有子句
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function orHavingRaw($sql, array $bindings = [])
    {
        return $this->havingRaw($sql, $bindings, 'or');
    }

    /**
     * @desc 向查询添加"order by"子句
     * @param Closure|EloquentBuilder|Builder|Expression|string $column
     * @param string $direction
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function orderBy($column, $direction = 'asc')
    {
        if ($this->isQueryable($column)) {
            [$query, $bindings] = $this->createSub($column);

            $column = new Expression('(' . $query . ')');

            $this->addBinding($bindings, $this->unions ? 'unionOrder' : 'order');
        }

        $direction = strtolower($direction);

        if ( !in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = [
            'column'    => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * @desc 在查询中添加降序的"order by"子句
     * @param Closure|EloquentBuilder|Builder|Expression|string $column
     * @return $this
     */
    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * @desc 为查询添加时间戳的"order by"子句
     * @param Closure|EloquentBuilder|Builder|Expression|string $column
     * @return $this
     */
    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * @desc 为查询添加时间戳的"order by"子句
     * @param Closure|EloquentBuilder|Builder|Expression|string $column
     * @return $this
     */
    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * @desc 查询的结果按随机顺序排列
     * @param string $seed
     * @return $this
     */
    public function inRandomOrder($seed = '')
    {
        return $this->orderByRaw($this->grammar->compileRandom($seed));
    }

    /**
     * @desc 向查询添加原始的"order by"子句
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function orderByRaw($sql, $bindings = [])
    {
        $type = 'Raw';

        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = compact('type', 'sql');

        $this->addBinding($bindings, $this->unions ? 'unionOrder' : 'order');

        return $this;
    }

    /**
     * @desc 设置查询的"偏移"值的别名
     * @param int $value
     * @return $this
     */
    public function skip($value)
    {
        return $this->offset($value);
    }

    /**
     * @desc 设置查询的"偏移量"值
     * @param int $value
     * @return $this
     */
    public function offset($value)
    {
        $property = $this->unions ? 'unionOffset' : 'offset';

        $this->$property = max(0, (int) $value);

        return $this;
    }

    /**
     * @desc limit 别名
     * @param int $value
     * @return $this
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * @desc 设置查询的"限制"值
     * @param int $value
     * @return $this
     */
    public function limit($value)
    {
        $property = $this->unions ? 'unionLimit' : 'limit';

        if ($value >= 0) {
            $this->$property = !is_null($value) ? (int) $value : null;
        }

        return $this;
    }

    /**
     * @desc 为给定页面设置限制和偏移量
     * @param int $page
     * @param int $perPage
     * @return $this
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * @desc 将查询限制在给定 ID 之前的结果的前"页面"
     * @param int $perPage
     * @param int|null $lastId
     * @param string $column
     * @return $this
     */
    public function forPageBeforeId($perPage = 15, $lastId = 0, $column = 'id')
    {
        $this->orders = $this->removeExistingOrdersFor($column);

        if ( !is_null($lastId)) {
            $this->where($column, '<', $lastId);
        }

        return $this->orderBy($column, 'desc')->limit($perPage);
    }

    /**
     * @desc 将查询限制在给定 ID 后的下一个"页面"结果
     * @param int $perPage
     * @param int|null $lastId
     * @param string $column
     * @return $this
     */
    public function forPageAfterId($perPage = 15, $lastId = 0, $column = 'id')
    {
        $this->orders = $this->removeExistingOrdersFor($column);

        if ( !is_null($lastId)) {
            $this->where($column, '>', $lastId);
        }

        return $this->orderBy($column, 'asc')
            ->limit($perPage);
    }

    /**
     * @desc 删除所有已经存在的 orders 并可选择地添加新 order
     * @param Closure|Builder|Expression|string|null $column
     * @param string $direction
     * @return $this
     */
    public function reorder($column = null, $direction = 'asc')
    {
        $this->orders                 = [];
        $this->unionOrders            = [];
        $this->bindings['order']      = [];
        $this->bindings['unionOrder'] = [];

        if ($column) {
            return $this->orderBy($column, $direction);
        }

        return $this;
    }

    /**
     * @desc 获取一个数组，其中包含删除了给定列的所有订单
     * @param string $column
     * @return array
     */
    protected function removeExistingOrdersFor($column)
    {
        $orders = $this->orders;

        $callback = function ($order) use ($column){
            return isset($order['column'])
                ? $order['column'] === $column : false;
        };

        $orders = GeneralUtil::reject($orders, $callback);

        return array_values($orders);
    }

    /**
     * @desc 向查询添加联合语句
     * @param Builder|Closure $query
     * @param bool $all
     * @return $this
     */
    public function union($query, $all = false)
    {
        if ($query instanceof Closure) {
            call_user_func($query, $query = $this->newQuery());
        }

        $this->unions[] = compact('query', 'all');

        $this->addBinding($query->getBindings(), 'union');

        return $this;
    }

    /**
     * @desc 向查询添加一个 union all 语句
     * @param Builder|Closure $query
     * @return $this
     */
    public function unionAll($query)
    {
        return $this->union($query, true);
    }

    /**
     * @desc 锁定表中的选定行
     * @param string|bool $value
     * @return $this
     */
    public function lock($value = true)
    {
        $this->lock = $value;

        if ( !is_null($this->lock)) {
            $this->useWritePdo();
        }

        return $this;
    }

    /**
     * @desc 锁定表中选定的行以进行更新
     * @return Builder
     */
    public function lockForUpdate()
    {
        return $this->lock(true);
    }

    /**
     * @desc 共享锁定表中的选定行
     * @return Builder
     */
    public function sharedLock()
    {
        return $this->lock(false);
    }

    /**
     * @desc 在执行查询之前注册一个要调用的闭包
     * @param callable $callback
     * @return $this
     */
    public function beforeQuery(callable $callback)
    {
        $this->beforeQueryCallbacks[] = $callback;

        return $this;
    }

    /**
     * @desc 调用"查询前"修改回调
     * @return void
     */
    public function applyBeforeQueryCallbacks()
    {
        foreach ($this->beforeQueryCallbacks as $callback) {
            $callback($this);
        }

        $this->beforeQueryCallbacks = [];
    }

    /**
     * @desc 获取查询的 SQL 表示
     * @return string
     */
    public function toSql()
    {
        $this->applyBeforeQueryCallbacks();

        return $this->grammar->compileSelect($this);
    }

    /**
     * @desc 按 ID 查询单个记录
     * @param int|string $id
     * @param array $columns
     * @return mixed
     */
    public function find($id, $columns = ['*'])
    {
        return $this->where('id', '=', $id)->first($columns);
    }

    /**
     * @desc 从查询的第一个结果中获取单个列的值
     * @param string $column
     * @return mixed
     */
    public function value($column)
    {
        $result = (array) $this->first([$column]);

        return count($result) > 0 ? reset($result) : null;
    }

    /**
     * @desc 执行 select 语句查询
     * @param array|string $columns
     * @return array
     */
    public function get($columns = ['*'])
    {
        $list = $this->onceWithColumns(GeneralUtil::wrap($columns), function (){
            return $this->processor->processSelect($this, $this->runSelect());
        });

        return $list;
    }

    /**
     * @desc 将查询作为针对连接的"select"语句运行
     * @return array
     */
    protected function runSelect()
    {
        return $this->connection->select(
            $this->toSql(), $this->getBindings(), !$this->useWritePdo
        );
    }

    /**
     * @desc 将给定的查询分页成一个简单的分页器
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return LengthAwarePaginator
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ? : Paginator::resolveCurrentPage($pageName);

        $total = $this->getCountForPagination();

        $results = $total ? $this->forPage($page, $perPage)->get($columns) : [];

        return $this->paginator($results, $total, $perPage, $page, [
            'path'     => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * @desc 获取仅支持简单的下一个和上一个链接的分页器；这在更大的数据集等上更有效
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return Paginator
     */
    public function simplePaginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ? : Paginator::resolveCurrentPage($pageName);

        $this->offset(($page - 1) * $perPage)->limit($perPage + 1);

        return $this->simplePaginator($this->get($columns), $perPage, $page, [
            'path'     => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * @desc 获取仅支持简单的下一个和上一个链接的分页器；这在更大的数据集等上更有效
     * @param int|null $perPage
     * @param array $columns
     * @param string $cursorName
     * @param Cursor|string|null $cursor
     * @return CursorPaginator
     */
    public function cursorPaginate($perPage = 15, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        return $this->paginateUsingCursor($perPage, $columns, $cursorName, $cursor);
    }

    /**
     * @desc 确保游标分页所需的正确顺序
     * @param bool $shouldReverse
     * @return array
     */
    protected function ensureOrderForCursorPagination($shouldReverse = false)
    {
        $this->enforceOrderBy();

        $callback = function ($order){
            return GeneralUtil::has($order, 'direction');
        };

        $orders = $this->orders ?? $this->unionOrders ?? [];

        $orders = array_filter((array) $orders, $callback, ARRAY_FILTER_USE_BOTH);

        if ($shouldReverse) {
            $callback = function ($orders){
                $keys = array_keys($orders);

                $items = array_map(function ($order){
                    $order['direction'] = $order['direction'] === 'asc' ? 'desc' : 'asc';

                    return $order;
                }, $orders, $keys);

                return $items;
            };

            $orders = $callback($orders);
        }

        $values = [];
        if ( !empty($orders) && is_array($orders)) {
            $values = array_values($orders);
        }

        return $values;
    }

    /**
     * @desc 获取分页器的总记录数
     * @param array $columns
     * @return int
     */
    public function getCountForPagination($columns = ['*'])
    {
        $results = $this->runPaginationCountQuery($columns);

        if ( !isset($results[0])) {
            return 0;
        } elseif (is_object($results[0])) {
            return (int) $results[0]->aggregate;
        }

        return (int) array_change_key_case((array) $results[0])['aggregate'];
    }

    /**
     * @desc 运行分页计数查询
     * @param array $columns
     * @return array
     */
    protected function runPaginationCountQuery($columns = ['*'])
    {
        if ($this->groups || $this->havings) {
            $clone = $this->cloneForPaginationCount();

            if (is_null($clone->columns) && !empty($this->joins)) {
                $clone->select($this->from . '.*');
            }

            return $this->newQuery()
                ->from(new Expression('(' . $clone->toSql() . ') as ' . $this->grammar->wrap('aggregate_table')))
                ->mergeBindings($clone)
                ->setAggregate('count', $this->withoutSelectAliases($columns))
                ->get();
        }

        $without = $this->unions ? ['orders', 'limit', 'offset'] : ['columns', 'orders', 'limit', 'offset'];

        return $this->cloneWithout($without)
            ->cloneWithoutBindings($this->unions ? ['order'] : ['select', 'order'])
            ->setAggregate('count', $this->withoutSelectAliases($columns))
            ->get();
    }

    /**
     * @desc 克隆现有查询实例以用于分页子查询
     * @return self
     */
    protected function cloneForPaginationCount()
    {
        return $this->cloneWithout(['orders', 'limit', 'offset'])->cloneWithoutBindings(['order']);
    }

    /**
     * @desc 删除列别名，因为它们会破坏计数查询
     * @param array $columns
     * @return array
     */
    protected function withoutSelectAliases(array $columns)
    {
        return array_map(function ($column){
            return is_string($column) && ($aliasPosition = stripos($column, ' as ')) !== false
                ? substr($column, 0, $aliasPosition) : $column;
        }, $columns);
    }

    /**
     * @desc 获取给定查询的惰性集合
     * @return Generator
     */
    public function cursor()
    {
        if (is_null($this->columns)) {
            $this->columns = ['*'];
        }

        return $this->cursorGenerator();
    }

    /**
     * @desc 游标生成器
     * @return Generator
     */
    protected function cursorGenerator()
    {
        yield from $this->connection->cursor(
            $this->toSql(), $this->getBindings(), !$this->useWritePdo
        );
    }

    /**
     * @desc 如果查询没有orderBy子句，则抛出异常
     * @return void
     *
     * @throws RuntimeException
     */
    protected function enforceOrderBy()
    {
        if (empty($this->orders) && empty($this->unionOrders)) {
            throw new RuntimeException('You must specify an orderBy clause when using this function.');
        }
    }

    /**
     * @desc 获取包含给定列值的集合实例
     * @param string $column
     * @param string|null $key
     * @return array
     */
    public function pluck($column, $key = null)
    {
        // 首先，我们需要选择查询的结果占给定的列/键。 一旦我们得到结果，我们将能够采取结果并获得为查询请求的确切数据
        $queryResult = $this->onceWithColumns(
            is_null($key) ? [$column] : [$column, $key],
            function (){
                return $this->processor->processSelect(
                    $this, $this->runSelect()
                );
            }
        );

        if (empty($queryResult)) {
            return [];
        }

        // 如果列用表限定或有别名，我们不能直接在"采摘"操作中使用它们，因为数据库的结果仅由列本身键入。 我们将在这里剥离表格。
        $column = $this->stripTableForPluck($column);

        $key = $this->stripTableForPluck($key);

        return is_array($queryResult[0])
            ? $this->pluckFromArrayColumn($queryResult, $column, $key)
            : $this->pluckFromObjectColumn($queryResult, $column, $key);
    }

    /**
     * @desc 从列标识符中剥离表名或别名
     * @param string $column
     * @return string|null
     */
    protected function stripTableForPluck($column)
    {
        if (is_null($column)) {
            return $column;
        }

        $separator = strpos(strtolower($column), ' as ') !== false ? ' as ' : '\.';

        $items = preg_split('~' . $separator . '~i', $column);

        return end($items);
    }

    /**
     * @desc 从表示为对象的行中检索列值
     * @param array $queryResult
     * @param string $column
     * @param string $key
     * @return array
     */
    protected function pluckFromObjectColumn($queryResult, $column, $key)
    {
        $results = [];

        if (is_null($key)) {
            foreach ($queryResult as $row) {
                $results[] = $row->$column;
            }
        } else {
            foreach ($queryResult as $row) {
                $results[$row->$key] = $row->$column;
            }
        }

        return $results;
    }

    /**
     * @desc 从表示为数组的行中检索列值
     * @param array $queryResult
     * @param string $column
     * @param string $key
     * @return array
     */
    protected function pluckFromArrayColumn($queryResult, $column, $key)
    {
        $results = [];

        if (is_null($key)) {
            foreach ($queryResult as $row) {
                $results[] = $row[$column];
            }
        } else {
            foreach ($queryResult as $row) {
                $results[$row[$key]] = $row[$column];
            }
        }

        return $results;
    }

    /**
     * @desc 将给定列的值连接为字符串
     * @param string $column
     * @param string $glue
     * @return string
     */
    public function implode($column, $glue = '')
    {
        $items = $this->pluck($column);

        return implode($glue, $items);
    }

    /**
     * @desc 确定当前查询是否存在任何行
     * @return bool
     */
    public function exists()
    {
        $this->applyBeforeQueryCallbacks();

        $results = $this->connection->select(
            $this->grammar->compileExists($this), $this->getBindings(), !$this->useWritePdo
        );

        // 如果结果有行，我们将获取该行并查看存在的列是否为布尔值 true。
        // 如果此查询没有结果，我们将返回 false，因为此查询根本没有行，我们可以在此处返回该信息。
        if (isset($results[0])) {
            $results = (array) $results[0];

            return (bool) $results['exists'];
        }

        return false;
    }

    /**
     * @desc 确定当前查询是否不存在任何行
     * @return bool
     */
    public function doesntExist()
    {
        return !$this->exists();
    }

    /**
     * @desc 如果当前查询不存在任何行，则执行给定的回调
     * @param Closure $callback
     * @return mixed
     */
    public function existsOr(Closure $callback)
    {
        return $this->exists() ? true : $callback();
    }

    /**
     * @desc 如果当前查询存在行，则执行给定的回调
     * @param Closure $callback
     * @return mixed
     */
    public function doesntExistOr(Closure $callback)
    {
        return $this->doesntExist() ? true : $callback();
    }

    /**
     * @desc 检索查询的总数
     * @param string $columns
     * @return int
     */
    public function count($columns = '*')
    {
        return (int) $this->aggregate(__FUNCTION__, GeneralUtil::wrap($columns));
    }

    /**
     * @desc 检索给定列的最小值
     * @param string $column
     * @return mixed
     */
    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    /**
     * @desc 检索给定列的最大值
     * @param string $column
     * @return mixed
     */
    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    /**
     * @desc 检索给定列的值的总和
     * @param string $column
     * @return mixed
     */
    public function sum($column)
    {
        $result = $this->aggregate(__FUNCTION__, [$column]);

        return $result ? : 0;
    }

    /**
     * @desc 检索给定列的值的平均值
     * @param string $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    /**
     * @desc 检索给定列的值的平均值
     * @param string $column
     * @return mixed
     */
    public function average($column)
    {
        return $this->avg($column);
    }

    /**
     * @desc 在数据库上执行聚合函数
     * @param string $function
     * @param array $columns
     * @return mixed
     */
    public function aggregate($function, $columns = ['*'])
    {
        $results = $this->cloneWithout($this->unions || $this->havings ? [] : ['columns'])
            ->cloneWithoutBindings($this->unions || $this->havings ? [] : ['select'])
            ->setAggregate($function, $columns)
            ->get($columns);

        if ( !empty($results) && isset($results[0])) {
            $changeKeyCase = array_change_key_case((array) $results[0]);

            return $changeKeyCase['aggregate'] ?? null;
        }

        return null;
    }

    /**
     * @desc 在数据库上执行数字聚合函数
     * @param string $function
     * @param array $columns
     * @return float|int
     */
    public function numericAggregate($function, $columns = ['*'])
    {
        $result = $this->aggregate($function, $columns);

        // 如果没有结果，我们显然可以在这里返回 0。 接下来，我们将检查结果是整数还是浮点数。
        // 如果它已经是这两种数据类型之一，我们可以按原样返回结果，否则我们将转换它。
        if ( !$result) {
            return 0;
        }

        if (is_int($result) || is_float($result)) {
            return $result;
        }

        // 如果结果不包含小数位，我们将假定它是一个 int 然后将其转换为 1。
        // 当它出现时，我们会将其转换为浮点数，因为出于纯粹的方便需要将其转换为开发人员预期的数据类型。
        return strpos((string) $result, '.') === false ? (int) $result : (float) $result;
    }

    /**
     * @desc 在不运行查询的情况下设置聚合属性
     * @param string $function
     * @param array $columns
     * @return $this
     */
    protected function setAggregate($function, $columns)
    {
        $this->aggregate = compact('function', 'columns');

        if (empty($this->groups)) {
            $this->orders = [];

            $this->bindings['order'] = [];
        }

        return $this;
    }

    /**
     * @desc 选择给定的列时执行给定的回调。 运行回调后，列将重置为原始值。
     * @param array $columns
     * @param callable $callback
     * @return mixed
     */
    protected function onceWithColumns($columns, $callback)
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $result = $callback();

        $this->columns = $original;

        return $result;
    }

    /**
     * @desc 向数据库中插入新记录
     * @param array $values
     * @return bool
     */
    public function insert(array $values)
    {
        // 由于每个插入都被视为批量插入，我们将确保
        // 绑定的结构方式在构建这些时很方便
        // 通过验证这些元素实际上是一个数组来插入语句。
        if (empty($values)) {
            return true;
        }

        if ( !is_array(reset($values))) {
            $values = [$values];
        } else {
            // 在这里，我们将对每个记录的插入键进行排序，以便每个插入都是
            // 以相同的顺序记录。 我们需要确保情况确实如此
            // 因此插入这些记录时不会出现任何错误或问题。
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        // 最后，我们将对数据库连接运行此查询并返回结果。
        // 我们还需要在运行之前展平这些绑定查询，因此它们都在一个巨大的扁平数组中以供执行。
        $sql = $this->grammar->compileInsert($this, $values);

        return $this->connection->insert($sql, $this->cleanBindings(GeneralUtil::flatten($values, 1)));
    }

    /**
     * @desc 在忽略错误的同时将新记录插入数据库
     * @param array $values
     * @return int
     */
    public function insertOrIgnore(array $values)
    {
        if (empty($values)) {
            return 0;
        }

        if ( !is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        return $this->connection->affectingStatement(
            $this->grammar->compileInsertOrIgnore($this, $values),
            $this->cleanBindings(GeneralUtil::flatten($values, 1))
        );
    }

    /**
     * @desc 插入一条新记录并获取主键的值
     * @param array $values
     * @param string|null $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        $values = $this->cleanBindings($values);

        return $this->processor->processInsertGetId($this, $sql, $values, $sequence);
    }

    /**
     * @desc 使用子查询向表中插入新记录
     * @param array $columns
     * @param Closure|Builder|string $query
     * @return int
     */
    public function insertUsing(array $columns, $query)
    {
        $this->applyBeforeQueryCallbacks();

        [$sql, $bindings] = $this->createSub($query);

        $sql = $this->grammar->compileInsertUsing($this, $columns, $sql);

        return $this->connection->affectingStatement($sql, $this->cleanBindings($bindings));
    }

    /**
     * @desc 更新数据库中的记录
     * @param array $values
     * @return int
     */
    public function update(array $values)
    {
        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->update($sql, $this->cleanBindings(
            $this->grammar->prepareBindingsForUpdate($this->bindings, $values)
        ));
    }

    /**
     * @desc 插入或更新匹配属性的记录，并用值填充它
     * @param array $attributes
     * @param array $values
     * @return bool
     */
    public function updateOrInsert(array $attributes, array $values = [])
    {
        if ( !$this->where($attributes)->exists()) {
            return $this->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return (bool) $this->limit(1)->update($values);
    }

    /**
     * @desc 插入新记录或更新现有记录
     * @param array $values
     * @param array|string $uniqueBy
     * @param array|null $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (empty($values)) {
            return 0;
        } elseif ($update === []) {
            return (int) $this->insert($values);
        }

        if ( !is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        if (is_null($update)) {
            $update = array_keys(reset($values));
        }

        $this->applyBeforeQueryCallbacks();

        $flatten = GeneralUtil::flatten($values, 1);

        $reject = GeneralUtil::reject($update, function ($value, $key){
            return is_int($key);
        });

        $reject = array_values($reject);

        $bindings = $this->cleanBindings(array_merge($flatten, $reject));

        $sql = $this->grammar->compileUpsert($this, $values, (array) $uniqueBy, $update);

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /**
     * @desc 将列的值增加给定的数量
     * @param string $column
     * @param float|int $amount
     * @param array $extra
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        if ( !is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to increment method.');
        }

        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("$wrapped + $amount")], $extra);

        return $this->update($columns);
    }

    /**
     * @desc 将列的值减少给定的量
     * @param string $column
     * @param float|int $amount
     * @param array $extra
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        if ( !is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to decrement method.');
        }

        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("$wrapped - $amount")], $extra);

        return $this->update($columns);
    }

    /**
     * @desc 从数据库中删除记录
     * @param mixed $id
     * @return int
     */
    public function delete($id = null)
    {
        // 如果将 ID 传递给该方法，我们将设置 where 子句来检查
        // ID 让开发人员可以简单快速地从中删除一行
        // 数据库，而无需在查询中手动指定"where"子句。
        if ( !is_null($id)) {
            $this->where($this->from . '.id', '=', $id);
        }

        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileDelete($this);

        $bindings = $this->grammar->prepareBindingsForDelete($this->bindings);

        return $this->connection->delete($sql, $this->cleanBindings($bindings));
    }

    /**
     * @desc 在表上运行 truncate 语句
     * @return void
     */
    public function truncate()
    {
        /*
        $this->applyBeforeQueryCallbacks();

        foreach ($this->grammar->compileTruncate($this) as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
        */
    }

    /**
     * @return Builder
     */
    public function newQuery()
    {
        return new static($this->connection, $this->grammar, $this->processor);
    }

    /**
     * @desc 创建新的子查询实例
     * @return Builder
     */
    protected function forSubQuery()
    {
        return $this->newQuery();
    }

    /**
     * @desc 创建一个原始数据库表达式
     *
     * @param mixed $value
     * @return Expression
     */
    public function raw($value)
    {
        return $this->connection->raw($value);
    }

    /**
     * @desc 获取扁平化数组中的当前查询值绑定
     * @return array
     */
    public function getBindings()
    {
        return GeneralUtil::flatten($this->bindings);
    }

    /**
     * @desc 当前查询值绑定
     * @return array
     */
    public function getRawBindings()
    {
        return $this->bindings;
    }

    /**
     * @desc 在查询构建器上设置绑定
     * @param array $bindings
     * @param string $type
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setBindings(array $bindings, $type = 'where')
    {
        if ( !array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * @desc 向查询添加绑定
     * @param mixed $value
     * @param string $type
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function addBinding($value, $type = 'where')
    {
        if ( !array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        if (is_array($value)) {
            $map = array_map(
                [$this, 'castBinding'],
                array_merge($this->bindings[$type], $value),
            );

            $this->bindings[$type] = array_values($map);
        } else {
            $this->bindings[$type][] = $this->castBinding($value);
        }

        return $this;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function castBinding($value)
    {
        return $value;
    }

    /**
     * @desc 将一组绑定合并到我们的绑定中
     * @param Builder $query
     * @return $this
     */
    public function mergeBindings(self $query)
    {
        $this->bindings = array_merge_recursive($this->bindings, $query->bindings);

        return $this;
    }

    /**
     * @desc 从绑定列表中删除所有表达式
     * @param array $bindings
     * @return array
     */
    public function cleanBindings(array $bindings)
    {
        $callback = function ($binding){
            return !($binding instanceof Expression);
        };

        if ( !empty($bindings)) {
            $bindings = array_filter($bindings, $callback, ARRAY_FILTER_USE_BOTH);
        }

        if ( !empty($bindings)) {
            $bindings = array_map([$this, 'castBinding'], $bindings);
        }

        return array_values($bindings);
    }

    /**
     * @desc 从未知类型的输入中获取标量类型值
     * @param mixed $value
     * @return mixed
     */
    protected function flattenValue($value)
    {
        $flattenValue = GeneralUtil::flatten($value);

        $head = reset($flattenValue);

        return is_array($value) ? $head : $value;
    }

    /**
     * @desc 获取默认的主键名称
     * @return string
     */
    protected function defaultKeyName()
    {
        return 'id';
    }

    /**
     * @desc 获取当前连接
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return Processor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * @return Grammar
     */
    public function getGrammar()
    {
        return $this->grammar;
    }

    /**
     * @desc 使用可写查询
     * @return $this
     */
    public function useWritePdo()
    {
        $this->useWritePdo = true;

        return $this;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function isQueryable($value)
    {
        return $value instanceof self || $value instanceof Closure;
    }

    /**
     * @desc 克隆查询
     * @return $this
     */
    public function clone()
    {
        return clone $this;
    }

    /**
     * @desc 克隆没有给定属性的查询
     * @param array $properties
     * @return $this
     */
    public function cloneWithout(array $properties)
    {
        $value = $this->clone();

        $callback = function ($clone) use ($properties){
            foreach ($properties as $property) {
                $clone->{$property} = null;
            }
        };

        $callback($value);

        return $value;
    }

    /**
     * @desc 克隆没有给定绑定的查询
     * @param array $except
     * @return $this
     */
    public function cloneWithoutBindings(array $except)
    {
        $value = $this->clone();

        $callback = function ($clone) use ($except){
            foreach ($except as $type) {
                $clone->bindings[$type] = [];
            }
        };

        $callback($value);

        return $value;
    }

    /**
     * @desc 处理对方法的动态方法调用
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        $needle = 'where';

        if (strncmp($method, $needle, strlen($needle)) === 0) {
            return $this->dynamicWhere($method, $parameters);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
    }

    /**
     * @desc 分析查询
     * @return array
     */
    public function explain()
    {
        $sql = $this->toSql();

        $bindings = $this->getBindings();

        $explanation = $this->getConnection()->select('EXPLAIN ' . $sql, $bindings);

        return $explanation;
    }

    /**
     * @desc 将方法调用转发给给定的对象
     * @param mixed $object
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    protected function forwardCallTo($object, $method, $parameters)
    {
        try {
            return $object->{$method}(...$parameters);
        } catch (Error|BadMethodCallException $e) {
            $message = sprintf($e->getMessage() . ', Call to undefined method %s::%s()', static::class, $method);

            throw new BadMethodCallException($message);
        }
    }

}
