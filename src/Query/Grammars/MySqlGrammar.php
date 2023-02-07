<?php

namespace Teamone\TeamoneWpDbOrm\Query\Grammars;

use Teamone\TeamoneWpDbOrm\Query\Builder;

class MySqlGrammar extends Grammar
{
    /**
     * @var string[] 特定于语法的运算符
     */
    protected $operators = ['sounds like'];

    /**
     * @desc 向查询添加“where null”子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereNull(Builder $query, $where)
    {
        if ($this->isJsonSelector($where['column'])) {
            [$field, $path] = $this->wrapJsonFieldAndPath($where['column']);

            return '(json_extract(' . $field . $path . ') is null OR json_type(json_extract(' . $field . $path . ')) = \'NULL\')';
        }

        return parent::whereNull($query, $where);
    }

    /**
     * @desc 向查询添加“where not null”子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    protected function whereNotNull(Builder $query, $where)
    {
        if ($this->isJsonSelector($where['column'])) {
            [$field, $path] = $this->wrapJsonFieldAndPath($where['column']);

            return '(json_extract(' . $field . $path . ') is not null AND json_type(json_extract(' . $field . $path . ')) != \'NULL\')';
        }

        return parent::whereNotNull($query, $where);
    }

    /**
     * @desc 编译一个“where fulltext”子句
     * @param Builder $query
     * @param array $where
     * @return string
     */
    public function whereFullText(Builder $query, $where)
    {
        $columns = $this->columnize($where['columns']);

        $value = $this->parameter($where['value']);

        $mode = ($where['options']['mode'] ?? []) === 'boolean'
            ? ' in boolean mode'
            : ' in natural language mode';

        $expanded = ($where['options']['expanded'] ?? []) && ($where['options']['mode'] ?? []) !== 'boolean'
            ? ' with query expansion'
            : '';

        return "match ({$columns}) against (" . $value . "{$mode}{$expanded})";
    }

    /**
     * @desc 将 insert ignore 语句编译成 SQL
     * @param Builder $query
     * @param array $values
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        $search  = 'insert';
        $replace = 'insert ignore';
        $subject = $this->compileInsert($query, $values);

        $position = strpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * @desc 将“JSON 包含”语句编译成 SQL
     * @param string $column
     * @param string $value
     * @return string
     */
    protected function compileJsonContains($column, $value)
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'json_contains(' . $field . ', ' . $value . $path . ')';
    }

    /**
     * @desc 将“JSON 长度”语句编译成 SQL
     * @param string $column
     * @param string $operator
     * @param string $value
     * @return string
     */
    protected function compileJsonLength($column, $operator, $value)
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'json_length(' . $field . $path . ') ' . $operator . ' ' . $value;
    }

    /**
     * @desc 将随机语句编译成SQL
     * @param string $seed
     * @return string
     */
    public function compileRandom($seed)
    {
        return 'RAND(' . $seed . ')';
    }

    /**
     * @desc 将锁编译成SQL
     * @param Builder $query
     * @param bool|string $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        if ( !is_string($value)) {
            return $value ? 'for update' : 'lock in share mode';
        }

        return $value;
    }

    /**
     * @desc 将插入语句编译成 SQL
     * @param Builder $query
     * @param array $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        if (empty($values)) {
            $values = [[]];
        }

        return parent::compileInsert($query, $values);
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
            if ($this->isJsonSelector($key)) {
                return $this->compileJsonUpdateColumn($key, $value);
            }

            return $this->wrap($key) . ' = ' . $this->parameter($value);
        };

        $keys = array_keys($values);

        $items = array_map($callback, $values, $keys);

        $items = array_combine($keys, $items);

        $items = implode(', ', $items);

        return $items;
    }

    /**
     * @desc 将“upsert”语句编译成 SQL
     * @param Builder $query
     * @param array $values
     * @param array $uniqueBy
     * @param array $update
     * @return string
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update)
    {
        $sql = $this->compileInsert($query, $values) . ' on duplicate key update ';

        $callback = function ($value, $key){
            return is_numeric($key)
                ? $this->wrap($value) . ' = values(' . $this->wrap($value) . ')'
                : $this->wrap($key) . ' = ' . $this->parameter($value);
        };

        $keys = array_keys($update);

        $items = array_map($callback, $update, $keys);

        $items = array_combine($keys, $items);

        $columns = implode(', ', $items);

        $sql = $sql . $columns;

        return $sql;
    }

    /**
     * @desc 使用 JSON_SET 函数准备要更新的 JSON 列
     * @param string $key
     * @param mixed $value
     * @return string
     */
    protected function compileJsonUpdateColumn($key, $value)
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = 'cast(? as json)';
        } else {
            $value = $this->parameter($value);
        }

        [$field, $path] = $this->wrapJsonFieldAndPath($key);

        return "{$field} = json_set({$field}{$path}, {$value})";
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
        $sql = parent::compileUpdateWithoutJoins($query, $table, $columns, $where);

        if ( !empty($query->orders)) {
            $sql .= ' ' . $this->compileOrders($query, $query->orders);
        }

        if ( !empty($query->limit)) {
            $sql .= ' ' . $this->compileLimit($query, $query->limit);
        }

        return $sql;
    }

    /**
     * @desc 为更新语句准备绑定。 布尔值、整数和双精度值作为原始值插入到JSON更新中。
     * @param array $bindings
     * @param array $values
     * @return array
     */
    public function prepareBindingsForUpdate(array $bindings, array $values)
    {
        // Filter
        $rejectCallback = function ($value, $column){
            return ! ($this->isJsonSelector($column) && is_bool($value));
        };

        $items = array_filter($values, $rejectCallback, ARRAY_FILTER_USE_BOTH);

        // Map
        $mapCallback = function ($value){
            return is_array($value) ? json_encode($value) : $value;
        };
        $keys = array_keys($items);

        $items = array_map($mapCallback, $items, $keys);

        $items = array_combine($keys, $items);

        $result =  parent::prepareBindingsForUpdate($bindings, $items);

        return $result;
    }

    /**
     * @desc 编译一个不使用连接的删除查询
     * @param Builder $query
     * @param string $table
     * @param string $where
     * @return string
     */
    protected function compileDeleteWithoutJoins(Builder $query, $table, $where)
    {
        $sql = parent::compileDeleteWithoutJoins($query, $table, $where);

        // 使用 MySQL 时，delete 语句可能包含 order by 语句和 limits
        // 所以我们将在这里编译它们。 一旦我们完成编译
        // 我们将返回完成的 SQL 语句，以便它为我们执行。
        if ( !empty($query->orders)) {
            $sql .= ' ' . $this->compileOrders($query, $query->orders);
        }

        if ( !empty($query->limit)) {
            $sql .= ' ' . $this->compileLimit($query, $query->limit);
        }

        return $sql;
    }

    /**
     * @desc 将单个字符串包装在关键字标识符中
     * @param string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        return $value === '*' ? $value : '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * @desc 包装给定的 JSON 选择器
     * @param string $value
     * @return string
     */
    protected function wrapJsonSelector($value)
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_unquote(json_extract(' . $field . $path . '))';
    }

    /**
     * @desc 为布尔值包装给定的 JSON 选择器
     * @param string $value
     * @return string
     */
    protected function wrapJsonBooleanSelector($value)
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_extract(' . $field . $path . ')';
    }
}
