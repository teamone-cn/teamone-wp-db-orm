<?php

namespace Teamone\TeamoneWpDbOrm\Pagination\Contract;

interface LengthAwarePaginator extends Paginator
{
    /**
     * @desc 创建一系列分页 URL
     * @param  int  $start
     * @param  int  $end
     * @return array
     */
    public function getUrlRange($start, $end);

    /**
     * @desc 总数
     *
     * @return int
     */
    public function total();

    /**
     * @desc 最后一页
     * @return int
     */
    public function lastPage();
}
