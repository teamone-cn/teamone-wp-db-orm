<?php

namespace Teamone\TeamoneWpDbOrm\Query\Processors;

class MySqlProcessor extends Processor
{
    /**
     * @desc 处理列列表查询的结果
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        return array_map(function ($result) {
            return ((object) $result)->column_name;
        }, $results);
    }
}
