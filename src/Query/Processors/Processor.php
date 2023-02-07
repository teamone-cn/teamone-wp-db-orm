<?php

namespace Teamone\TeamoneWpDbOrm\Query\Processors;

use Teamone\TeamoneWpDbOrm\Query\Builder;

class Processor
{
    /**
     * @desc 处理"选择"查询的结果
     * @param  Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processSelect(Builder $query, $results)
    {
        return $results;
    }

    /**
     * @desc 处理一个 插入获取 ID 查询
     * @param  Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $query->getConnection()->insert($sql, $values);

        $id = $query->getConnection()->getPdo()->lastInsertId($sequence);

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * @desc 处理列列表查询的结果
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        return $results;
    }
}
