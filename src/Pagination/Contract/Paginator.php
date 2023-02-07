<?php

namespace Teamone\TeamoneWpDbOrm\Pagination\Contract;

interface Paginator
{
    /**
     * @desc 获取给定页面的 URL
     * @param int $page
     * @return string
     */
    public function url($page);

    /**
     * @desc 向分页器添加一组查询字符串值
     * @param array|string $key
     * @param string|null $value
     * @return $this
     */
    public function appends($key, $value = null);

    /**
     * @desc 获取/设置要附加到 URL 的 URL 片段
     * @param string|null $fragment
     * @return $this|string
     */
    public function fragment($fragment = null);

    /**
     * @desc 下一页的 URL，或 null
     * @return string|null
     */
    public function nextPageUrl();

    /**
     * @desc 获取上一页的 URL，或者为 null
     * @return string|null
     */
    public function previousPageUrl();

    /**
     * @desc 获取所有被分页的项目
     * @return array
     */
    public function items();

    /**
     * 获取被分页的第一个项目的“索引”
     * @return int
     */
    public function firstItem();

    /**
     * @desc 获取被分页的最后一项的“索引”
     * @return int
     */
    public function lastItem();

    /**
     * @desc 确定每页显示多少项目
     * @return int
     */
    public function perPage();

    /**
     * @desc 确定正在分页的当前页面
     * @return int
     */
    public function currentPage();

    /**
     * @desc 确定是否有足够的项目拆分成多个页面
     * @return bool
     */
    public function hasPages();

    /**
     * @desc 确定数据存储中是否有更多项目
     * @return bool
     */
    public function hasMorePages();

    /**
     * @desc 获取分页器生成的 URL 的基本路径
     * @return string|null
     */
    public function path();

    /**
     * @return bool
     */
    public function isEmpty();

    /**
     * @return bool
     */
    public function isNotEmpty();

    /**
     * @desc 使用给定视图渲染分页器
     * @param string|null $view
     * @param array $data
     * @return string
     */
    public function render($view = null, $data = []);
}
