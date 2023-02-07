<?php

namespace Teamone\TeamoneWpDbOrm\Query\Grammars;

use Teamone\TeamoneWpDbOrm\GeneralUtil;
use Teamone\TeamoneWpDbOrm\Query\Builder;
use Teamone\TeamoneWpDbOrm\Query\Expression;
use Teamone\TeamoneWpDbOrm\Query\JoinClause;
use RuntimeException;

class Grammar
{

    /**
     * @var string 表前缀
     */
    protected $tablePrefix = '';

    /**
     * @var array 语法特定运算符
     */
    protected $operators = [];

    /**
     * @var array 语法特定的位运算符
     */
    protected $bitwiseOperators = [];

    /**
     * @var string[] 组成 select 子句的组件
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'lock',
    ];

    /**
     * @desc 将选择查询编译成 SQL
     * @param Builder $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        if (($query->unions || $query->havings) && $query->aggregate) {
            return $this->compileUnionAggregate($query);
        }

        // 如果查询没有设置任何列，我们会将列设置为 * 字符以从数据库中获取所有列。
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $compileResult = $this->compileComponents($query);

        // 为了编译查询，我们将遍历查询的每个组件并查看该组件是否存在。 如果是这样，我们将只调用负责生成 SQL 的组件的编译器函数
        $sql = trim($this->concatenate($compileResult));

        if ($query->unions) {
            $sql = $this->wrapUnion($sql) . ' ' . $this->compileUnions($query);
        }

        $query->columns = $original;

        return $sql;
    }

    /**
     * @desc 编译 select 子句所需的组件
     * @param Builder $query
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (isset($query->$component)) {
                $method = 'compile' . ucfirst($component);

                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    /**
     * @desc 编译聚合的 select 子句
     * @param Builder $query
     * @param array $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);

        // 如果查询有一个"distinct"约束并且我们不要求所有列，我们需要在列名前加上"distinct"，以便查询在对数据执行聚合操作时考虑到它
        if (is_array($query->distinct)) {
            $column = 'distinct ' . $this->columnize($query->distinct);
        } elseif ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }

        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }

    /**
     * @desc 编译查询的"select *"部分
     * @param Builder $query
     * @param array $columns
     * @return string|null
     */
    protected function compileColumns(Builder $query, $columns)
    {
        // 如果查询实际上执行聚合选择，我们将让编译器处理选择子句的构建，因为它需要更多语法，最好由该函数处理以保持整洁
        if ( !is_null($query->aggregate)) {
            return null;
        }

        if ($query->distinct) {
            $select = 'select distinct ';
        } else {
            $select = 'select ';
        }

        return $select . $this->columnize($columns);
    }

    /**
     * @desc 编译查询的"from"部分
     * @param Builder $query
     * @param string $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table)
    {
        return 'from ' . $this->wrapTable($table);
    }

    /**
     * @desc 编译查询的"join"部分
     * @param Builder $query
     * @param array $joins
     * @return string
     */
    protected function compileJoins(Builder $query, $joins)
    {
        $callback = function ($join) use ($query){
            $table = $this->wrapTable($join->table);

            $nestedJoins = is_null($join->joins) ? '' : ' ' . $this->compileJoins($query, $join->joins);

            $tableAndNestedJoins = is_null($join->joins) ? $table : '(' . $table . $nestedJoins . ')';

            return trim("{$join->type} join {$tableAndNestedJoins} {$this->compileWheres($join)}");
        };

        $keys = array_keys($joins);

        $items = array_map($callback, $joins, $keys);

        $items = array_combine($keys, $items);

        $result = implode(' ', $items);

        return $result;
    }

    /**
     * @desc 编译查询的"where"部分
     * @param Builder $query
     * @return string
     */
    public function compileWheres(Builder $query)
    {
        // 每种类型的 where 子句都有自己的编译器函数，它负责用于实际创建 where 子句 SQL。
        // 这有助于保持代码美观且可维护，因为每个子句都有一个它使用的非常小的方法。
        if (is_null($query->wheres)) {
            return '';
        }

        // 如果我们确实有一些 where 子句，我们将去掉第一个布尔运算符，这是查询构建器为方便起见添加的，
        // 这样我们就可以避免在每个编译器方法中检查第一个子句。
        if (count($sql = $this->compileWheresToArray($query)) > 0) {
            return $this->concatenateWhereClauses($query, $sql);
        }

        return '';
    }

    /**
     * @desc 获取查询的所有 where 子句的数组
     * @param Builder $query
     * @return array
     */
    protected function compileWheresToArray($query)
    {
        $callback = function ($where) use ($query){
            return $where['boolean'] . ' ' . $this->{"where{$where['type']}"}($query, $where);
        };

        $keys = array_keys($query->wheres);

        $items = array_map($callback, $query->wheres, $keys);

        $items = array_combine($keys, $items);

        return $items;
    }

    /**
     * @desc 将 where 子句语句格式化为一个字符串
     * @param Builder $query
     * @param array $sql
     * @return string
     */
    protected function concatenateWhereClauses($query, $sql)
    {
        $conjunction = $query instanceof JoinClause ? 'on' : 'where';

        return $conjunction . ' ' . $this->removeLeadingBoolean(implode(' ', $sql));
    }

    /**
     * @desc 编译原始 where 子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereRaw(Builder $query, $where)
    {
        return $where['sql'];
    }

    /**
     * @desc 编译一个基本的 where 子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereBasic(Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        $operator = str_replace('?', '??', $where['operator']);

        return $this->wrap($where['column']) . ' ' . $operator . ' ' . $value;
    }

    /**
     * @desc 编译按位运算符 where 子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereBitwise(Builder $query, $where)
    {
        return $this->whereBasic($query, $where);
    }

    /**
     * @desc 编译一个"where in"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereIn(Builder $query, $where)
    {
        if ( !empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . $this->parameterize($where['values']) . ')';
        }

        return '0 = 1';
    }

    /**
     * @desc 编译一个"where not in"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereNotIn(Builder $query, $where)
    {
        if ( !empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . $this->parameterize($where['values']) . ')';
        }

        return '1 = 1';
    }

    /**
     * @desc 编译一个"where not in raw"子句，为了安全起见，whereIntegerInRaw 确保此方法仅用于整数值
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereNotInRaw(Builder $query, $where)
    {
        if ( !empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . implode(', ', $where['values']) . ')';
        }

        return '1 = 1';
    }

    /**
     * @desc 编译一个"where in raw"子句，为了安全起见，whereIntegerInRaw 确保此方法仅用于整数值
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereInRaw(Builder $query, $where)
    {
        if ( !empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . implode(', ', $where['values']) . ')';
        }

        return '0 = 1';
    }

    /**
     * @desc 编译一个"where null"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereNull(Builder $query, $where)
    {
        return $this->wrap($where['column']) . ' is null';
    }

    /**
     * @desc 编译一个"where not null"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereNotNull(Builder $query, $where)
    {
        return $this->wrap($where['column']) . ' is not null';
    }

    /**
     * @desc 编译一个"between"where子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereBetween(Builder $query, $where)
    {
        $between = $where['not'] ? 'not between' : 'between';

        $min = $this->parameter(reset($where['values']));

        $max = $this->parameter(end($where['values']));

        return $this->wrap($where['column']) . ' ' . $between . ' ' . $min . ' and ' . $max;
    }

    /**
     * @desc 编译一个"between"where子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereBetweenColumns(Builder $query, $where)
    {
        $between = $where['not'] ? 'not between' : 'between';

        $min = $this->wrap(reset($where['values']));

        $max = $this->wrap(end($where['values']));

        return $this->wrap($where['column']) . ' ' . $between . ' ' . $min . ' and ' . $max;
    }

    /**
     * @desc 编译一个"where date"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereDate(Builder $query, $where)
    {
        return $this->dateBasedWhere('date', $query, $where);
    }

    /**
     * @desc 编译一个"where time"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereTime(Builder $query, $where)
    {
        return $this->dateBasedWhere('time', $query, $where);
    }

    /**
     * @desc 编译一个"where day"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereDay(Builder $query, $where)
    {
        return $this->dateBasedWhere('day', $query, $where);
    }

    /**
     * @desc 编译一个"where month"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereMonth(Builder $query, $where)
    {
        return $this->dateBasedWhere('month', $query, $where);
    }

    /**
     * @desc 编译一个"where year"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereYear(Builder $query, $where)
    {
        return $this->dateBasedWhere('year', $query, $where);
    }

    /**
     * @desc 编译一个基于日期的 where 子句
     * @param string $type
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return $type . '(' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
    }

    /**
     * @desc 编译比较两列的 where 子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereColumn(Builder $query, $where)
    {
        return $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
    }

    /**
     * @desc 编译嵌套的 where 子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereNested(Builder $query, $where)
    {
        // 在这里，我们将计算需要删除的字符串部分。 如果这是一个连接子句查询，我们需要删除 SQL 的"on"部分，如果它是一个普通查询，我们需要采用查询的前导"where"。
        $offset = $query instanceof JoinClause ? 3 : 6;

        return '(' . substr($this->compileWheres($where['query']), $offset) . ')';
    }

    /**
     * @desc 使用子选择编译 where 条件
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereSub(Builder $query, $where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) . ' ' . $where['operator'] . " ($select)";
    }

    /**
     * @desc 编译 where exists 子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereExists(Builder $query, $where)
    {
        return 'exists (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * 编译 where exists 子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereNotExists(Builder $query, $where)
    {
        return 'not exists (' . $this->compileSelect($where['query']) . ')';
    }

    /**
     * @desc 编译 where 行值条件
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereRowValues(Builder $query, $where)
    {
        $columns = $this->columnize($where['columns']);

        $values = $this->parameterize($where['values']);

        return '(' . $columns . ') ' . $where['operator'] . ' (' . $values . ')';
    }

    /**
     * @desc 编译一个"where JSON boolean"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereJsonBoolean(Builder $query, $where)
    {
        $column = $this->wrapJsonBooleanSelector($where['column']);

        $value = $this->wrapJsonBooleanValue(
            $this->parameter($where['value'])
        );

        return $column . ' ' . $where['operator'] . ' ' . $value;
    }

    /**
     * @desc 编译一个"where JSON contains"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereJsonContains(Builder $query, $where)
    {
        $not = $where['not'] ? 'not ' : '';

        return $not . $this->compileJsonContains(
                $where['column'],
                $this->parameter($where['value'])
            );
    }

    /**
     * @desc 将"JSON 包含"语句编译成 SQL
     * @param string $column
     * @param string $value
     * @return string
     *
     * @throws RuntimeException
     */
    protected function compileJsonContains($column, $value)
    {
        throw new RuntimeException('This database engine does not support JSON contains operations.');
    }

    /**
     * @desc 为"JSON 包含"语句准备绑定
     * @param mixed $binding
     * @return string
     */
    public function prepareBindingForJsonContains($binding)
    {
        return json_encode($binding);
    }

    /**
     * @desc 编译一个"where JSON length"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereJsonLength(Builder $query, $where)
    {
        return $this->compileJsonLength(
            $where['column'],
            $where['operator'],
            $this->parameter($where['value'])
        );
    }

    /**
     * @desc 将"JSON 长度"语句编译成 SQL
     * @param string $column
     * @param string $operator
     * @param string $value
     * @return string
     *
     * @throws RuntimeException
     */
    protected function compileJsonLength($column, $operator, $value)
    {
        throw new RuntimeException('This database engine does not support JSON length operations.');
    }

    /**
     * @desc 编译一个"where fulltext"子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    public function whereFullText(Builder $query, $where)
    {
        throw new RuntimeException('This database engine does not support fulltext search operations.');
    }

    /**
     * @desc 编译查询的"分组依据"部分
     * @param Builder $query
     * @param array $groups
     * @return string
     */
    protected function compileGroups(Builder $query, $groups)
    {
        return 'group by ' . $this->columnize($groups);
    }

    /**
     * @desc 编译查询的"有"部分
     * @param Builder $query
     * @param array $havings
     * @return string
     */
    protected function compileHavings(Builder $query, $havings)
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having ' . $this->removeLeadingBoolean($sql);
    }

    /**
     * @desc 编译单个 having 子句
     * @param array $having
     * @return string
     */
    protected function compileHaving(array $having)
    {
        // 如果 having 子句是"原始的"，我们可以直接返回该子句而不对其进行任何处理。 否则，我们将根据构建器组成的组件将子句编译成SQL
        if ($having['type'] === 'Raw') {
            return $having['boolean'] . ' ' . $having['sql'];
        } elseif ($having['type'] === 'between') {
            return $this->compileHavingBetween($having);
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * @desc 编译一个基本的 having 子句
     * @param array $having
     * @return string
     */
    protected function compileBasicHaving($having)
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return $having['boolean'] . ' ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
    }

    /**
     * @desc 编译一个"between"having 子句
     * @param array $having
     * @return string
     */
    protected function compileHavingBetween($having)
    {
        $between = $having['not'] ? 'not between' : 'between';

        $column = $this->wrap($having['column']);

        $head = reset($having['values']);

        $min = $this->parameter($head);

        $last = end($having['values']);

        $max = $this->parameter($last);

        return $having['boolean'] . ' ' . $column . ' ' . $between . ' ' . $min . ' and ' . $max;
    }

    /**
     * @desc 编译查询的"order by"部分
     * @param Builder $query
     * @param array $orders
     * @return string
     */
    protected function compileOrders(Builder $query, $orders)
    {
        if ( !empty($orders)) {
            return 'order by ' . implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * @desc 将查询订单编译成数组
     * @param Builder $query
     * @param array $orders
     * @return array
     */
    protected function compileOrdersToArray(Builder $query, $orders)
    {
        return array_map(function ($order){
            return $order['sql'] ?? $this->wrap($order['column']) . ' ' . $order['direction'];
        }, $orders);
    }

    /**
     * @desc 将随机语句编译成SQL
     * @param string $seed
     * @return string
     */
    public function compileRandom($seed)
    {
        return 'RANDOM()';
    }

    /**
     * @desc 编译查询的"限制"部分
     * @param Builder $query
     * @param int $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return 'limit ' . (int) $limit;
    }

    /**
     * @desc 编译查询的"偏移量"部分
     * @param Builder $query
     * @param int $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return 'offset ' . (int) $offset;
    }

    /**
     * @desc 编译附加到主查询的"联合"查询
     * @param Builder $query
     * @return string
     */
    protected function compileUnions(Builder $query)
    {
        $sql = '';

        foreach ($query->unions as $union) {
            $sql .= $this->compileUnion($union);
        }

        if ( !empty($query->unionOrders)) {
            $sql .= ' ' . $this->compileOrders($query, $query->unionOrders);
        }

        if (isset($query->unionLimit)) {
            $sql .= ' ' . $this->compileLimit($query, $query->unionLimit);
        }

        if (isset($query->unionOffset)) {
            $sql .= ' ' . $this->compileOffset($query, $query->unionOffset);
        }

        return ltrim($sql);
    }

    /**
     * @desc 编译单个联合语句
     * @param array $union
     * @return string
     */
    protected function compileUnion(array $union)
    {
        $conjunction = $union['all'] ? ' union all ' : ' union ';

        return $conjunction . $this->wrapUnion($union['query']->toSql());
    }

    /**
     * @desc 将联合子查询包裹在括号中
     * @param string $sql
     * @return string
     */
    protected function wrapUnion($sql)
    {
        return '(' . $sql . ')';
    }

    /**
     * @desc 将联合聚合查询编译成 SQL
     * @param Builder $query
     * @return string
     */
    protected function compileUnionAggregate(Builder $query)
    {
        $sql = $this->compileAggregate($query, $query->aggregate);

        $query->aggregate = [];

        return $sql . ' from (' . $this->compileSelect($query) . ') as ' . $this->wrapTable('temp_table');
    }

    /**
     * @desc 将 exists 语句编译成 SQL
     * @param Builder $query
     * @return string
     */
    public function compileExists(Builder $query)
    {
        $select = $this->compileSelect($query);

        return "select exists({$select}) as {$this->wrap('exists')}";
    }

    /**
     * @desc 将插入语句编译成 SQL
     * @param Builder $query
     * @param array $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        // 本质上，我们将强制每个插入都被视为批量插入
        // 只是让我们更容易创建 SQL，因为我们可以利用相同的
        // 基本例程不管给我们插入多少记录。
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "insert into {$table} default values";
        }

        if ( !is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        // 我们需要构建一个绑定值的参数占位符列表到查询。
        // 每个插入应具有完全相同数量的参数
        // 绑定，因此我们将遍历记录并将它们全部参数化。
        $map = array_map(function ($record){
            return '(' . $this->parameterize($record) . ')';
        }, $values);

        $parameters = implode(', ', $map);

        $sql = "insert into {$table} ({$columns}) values {$parameters}";

        return $sql;
    }

    /**
     * @desc 将 insert ignore 语句编译成 SQL
     * @param Builder $query
     * @param array $values
     * @return string
     *
     * @throws RuntimeException
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        throw new RuntimeException('This database engine does not support inserting while ignoring errors.');
    }

    /**
     * @desc 将插入和获取 ID 语句编译成 SQL
     * @param Builder $query
     * @param array $values
     * @param string $sequence
     * @return string
     */
    public function compileInsertGetId(Builder $query, $values, $sequence)
    {
        return $this->compileInsert($query, $values);
    }

    /**
     * @desc 将使用子查询的插入语句编译成 SQL
     * @param Builder $query
     * @param array $columns
     * @param string $sql
     * @return string
     */
    public function compileInsertUsing(Builder $query, array $columns, string $sql)
    {
        $table   = $this->wrapTable($query->from);
        $columns = $this->columnize($columns);

        return "insert into {$table} ({$columns}) $sql";
    }

    /**
     * @desc 将更新语句编译成 SQL
     * @param Builder $query
     * @param array $values
     * @return string
     */
    public function compileUpdate(Builder $query, array $values)
    {
        $table = $this->wrapTable($query->from);

        $columns = $this->compileUpdateColumns($query, $values);

        $where = $this->compileWheres($query);

        $joins = isset($query->joins) ? $this->compileUpdateWithJoins($query, $table, $columns, $where) : $this->compileUpdateWithoutJoins($query, $table, $columns, $where);

        return trim($joins);
    }

    /**
     * @desc 编译更新语句的列
     * @param Builder $query
     * @param array $values
     * @return string
     */
    protected function compileUpdateColumns(Builder $query, array $values)
    {
        $callback = function ($value, $key){
            return $this->wrap($key) . ' = ' . $this->parameter($value);
        };

        $keys = array_keys($values);

        $items = array_map($callback, $values, $keys);

        $items = array_combine($keys, $items);

        $items = implode(', ', $items);

        return $items;
    }

    /**
     * @desc 编译更新语句而不加入 SQL
     * @param Builder $query
     * @param string $table
     * @param string $columns
     * @param string $where
     * @return string
     */
    protected function compileUpdateWithoutJoins(Builder $query, $table, $columns, $where)
    {
        return "update {$table} set {$columns} {$where}";
    }

    /**
     * @desc 将带有连接的更新语句编译到 SQL 中
     * @param Builder $query
     * @param string $table
     * @param string $columns
     * @param string $where
     * @return string
     */
    protected function compileUpdateWithJoins(Builder $query, $table, $columns, $where)
    {
        $joins = $this->compileJoins($query, $query->joins);

        return "update {$table} {$joins} set {$columns} {$where}";
    }

    /**
     * @desc 将"upsert"语句编译成 SQL
     * @param Builder $query
     * @param array $values
     * @param array $uniqueBy
     * @param array $update
     * @return string
     *
     * @throws RuntimeException
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update)
    {
        throw new RuntimeException('This database engine does not support upserts.');
    }

    /**
     * @desc 为更新语句准备绑定
     * @param array $bindings
     * @param array $values
     * @return array
     */
    public function prepareBindingsForUpdate(array $bindings, array $values)
    {
        $cleanBindings = $bindings;
        unset($bindings['select']);
        unset($bindings['join']);
        $merge = array_merge($cleanBindings['join'], $values, GeneralUtil::flatten($bindings));

        return array_values($merge);
    }

    /**
     * @desc 将删除语句编译成 SQL
     * @param Builder $query
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $table = $this->wrapTable($query->from);

        $where = $this->compileWheres($query);

        $joins = isset($query->joins) ? $this->compileDeleteWithJoins($query, $table, $where) : $this->compileDeleteWithoutJoins($query, $table, $where);

        return trim($joins);
    }

    /**
     * @desc 将没有连接的删除语句编译成 SQL
     * @param Builder $query
     * @param string $table
     * @param string $where
     * @return string
     */
    protected function compileDeleteWithoutJoins(Builder $query, $table, $where)
    {
        return "delete from {$table} {$where}";
    }

    /**
     * @desc 将带有连接的删除语句编译成 SQL
     * @param Builder $query
     * @param string $table
     * @param string $where
     * @return string
     */
    protected function compileDeleteWithJoins(Builder $query, $table, $where)
    {
        $separatorTables = explode(' as ', $table);

        $alias = end($separatorTables);

        $joins = $this->compileJoins($query, $query->joins);

        return "delete {$alias} from {$table} {$joins} {$where}";
    }

    /**
     * @desc 为删除语句准备绑定
     * @param array $bindings
     * @return array
     */
    public function prepareBindingsForDelete(array $bindings)
    {
        unset($bindings['select']);

        return GeneralUtil::flatten($bindings);
    }

    /**
     * @desc 将 truncate table 语句编译成 SQL
     * @param Builder $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return ['truncate table ' . $this->wrapTable($query->from) => []];
    }

    /**
     * @desc 将锁编译成SQL
     * @param Builder $query
     * @param bool|string $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @desc 判断文法是否支持保存点
     * @return bool
     */
    public function supportsSavepoints()
    {
        return true;
    }

    /**
     * @desc 编译SQL语句定义保存点
     * @param string $name
     * @return string
     */
    public function compileSavepoint($name)
    {
        return 'SAVEPOINT ' . $name;
    }

    /**
     * @desc 编译SQL语句执行保存点回滚
     * @param string $name
     * @return string
     */
    public function compileSavepointRollBack($name)
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

    /**
     * @desc 在关键字标识符中包装一个值
     * @param Expression|string $value
     * @param bool $prefixAlias
     * @return string
     */
    public function wrap($value, $prefixAlias = false)
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        // 如果被包装的值有一个列别名，我们将需要分开这些部分，
        // 这样我们就可以单独包装表达式的每个部分，然后使用"as"连接器将它们重新连接在一起。
        if (is_string($value) && stripos($value, ' as ') !== false) {
            return $this->wrapAliasedValue($value, $prefixAlias);
        }

        // 如果给定值是一个 JSON 选择器，我们将以不同于传统价值。
        // 我们需要拆分这条路径并包装每个部分wrapped 等。否则，我们将简单地将值包装为字符串。
        if ($this->isJsonSelector($value)) {
            return $this->wrapJsonSelector($value);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    /**
     * @desc 包装给定的 JSON 选择器
     * @param string $value
     * @return string
     *
     * @throws RuntimeException
     */
    protected function wrapJsonSelector($value)
    {
        throw new RuntimeException('This database engine does not support JSON operations.');
    }

    /**
     * @desc 为布尔值包装给定的 JSON 选择器
     * @param string $value
     * @return string
     */
    protected function wrapJsonBooleanSelector($value)
    {
        return $this->wrapJsonSelector($value);
    }

    /**
     * @desc 包装给定的 JSON 布尔值
     * @param string $value
     * @return string
     */
    protected function wrapJsonBooleanValue($value)
    {
        return $value;
    }

    /**
     * @desc 将给定的 JSON 选择器拆分为字段和可选路径，并分别包装
     * @param string $column
     * @return array
     */
    protected function wrapJsonFieldAndPath($column)
    {
        $parts = explode('->', $column, 2);

        $field = $this->wrap($parts[0]);

        $path = count($parts) > 1 ? ', ' . $this->wrapJsonPath($parts[1], '->') : '';

        return [$field, $path];
    }

    /**
     * @desc 包装给定的 JSON 路径
     * @param string $value
     * @param string $delimiter
     * @return string
     */
    protected function wrapJsonPath($value, $delimiter = '->')
    {
        $value = preg_replace("/([\\\\]+)?\\'/", "''", $value);

        return '\'$."' . str_replace($delimiter, '"."', $value) . '"\'';
    }

    /**
     * @desc 确定给定的字符串是否为 JSON 选择器
     * @param string $value
     * @return bool
     */
    protected function isJsonSelector($value)
    {
        $haystack = $value;

        $needles = '->';

        foreach ((array) $needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @desc 连接一个段数组，删除空
     * @param array $segments
     * @return string
     */
    protected function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function ($value){
            return (string) $value !== '';
        }));
    }

    /**
     * @desc 从语句中移除前导布尔值
     * @param string $value
     * @return string
     */
    protected function removeLeadingBoolean($value)
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    /**
     * @desc 获取特定于语法的运算符
     * @return array
     */
    public function getOperators()
    {
        return $this->operators;
    }

    /**
     * @desc 获取语法特定的位运算符
     * @return array
     */
    public function getBitwiseOperators()
    {
        return $this->bitwiseOperators;
    }

    /**********/

    /**
     * @desc 包装一个数组值
     * @param array $values
     * @return array
     */
    public function wrapArray(array $values)
    {
        return array_map([$this, 'wrap'], $values);
    }

    /**
     * @desc 用关键字标识符包装表格
     * @param Expression|string $table
     * @return string
     */
    public function wrapTable($table)
    {
        if ( !$this->isExpression($table)) {
            return $this->wrap($this->tablePrefix . $table, true);
        }

        return $this->getValue($table);
    }

    /**
     * @desc 包装具有别名的值
     * @param string $value
     * @param bool $prefixAlias
     * @return string
     */
    protected function wrapAliasedValue($value, $prefixAlias = false)
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        // 如果我们正在包装一个表，我们还需要在别名前加上表前缀，以便生成正确的语法。
        // 如果这是一列，当然不需要前缀。 当来自 wrapTable 时，条件将为真。
        if ($prefixAlias) {
            $segments[1] = $this->tablePrefix . $segments[1];
        }

        return $this->wrap($segments[0]) . ' as ' . $this->wrapValue($segments[1]);
    }

    /**
     * @desc 包装给定的值
     * @param array $segments
     * @return string
     */
    protected function wrapSegments($segments)
    {
        $callback = function ($segment, $key) use ($segments){
            return $key == 0 && count($segments) > 1 ? $this->wrapTable($segment) : $this->wrapValue($segment);
        };

        $keys = array_keys($segments);

        $items = array_map($callback, $segments, $keys);

        $items = array_combine($keys, $items);

        $result = implode('.', $items);

        return $result;
    }

    /**
     * @desc 将单个字符串包装在关键字标识符中
     * @param string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value !== '*') {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * @desc 将列名数组转换为带分隔符的字符串
     * @param array $columns
     * @return string
     */
    public function columnize(array $columns)
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * @desc 为数组创建查询参数占位符
     * @param array $values
     * @return string
     */
    public function parameterize(array $values)
    {
        return implode(', ', array_map([$this, 'parameter'], $values));
    }

    /**
     * @desc 获取值的适当查询参数占位符
     * @param mixed $value
     * @return string
     */
    public function parameter($value)
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    /**
     * @desc 引用给定的字符串文字
     * @param string|array $value
     * @return string
     */
    public function quoteString($value)
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, __FUNCTION__], $value));
        }

        return "'$value'";
    }

    /**
     * @desc 确定给定值是否为原始表达式
     * @param mixed $value
     * @return bool
     */
    public function isExpression($value)
    {
        return $value instanceof Expression;
    }

    /**
     * @desc 获取原始表达式的值
     * @param Expression $expression
     * @return mixed
     */
    public function getValue(Expression $expression)
    {
        return $expression->getValue();
    }

    /**
     * @desc 获取数据库存储日期的格式
     * @return string
     */
    public function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * @desc 获取语法的表前缀
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * @desc 设置表前缀
     * @param string $prefix
     * @return $this
     */
    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;

        return $this;
    }
}
