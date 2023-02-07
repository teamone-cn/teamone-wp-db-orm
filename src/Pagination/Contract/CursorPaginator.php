<?php

namespace Teamone\TeamoneWpDbOrm\Pagination\Contract;

use Teamone\TeamoneWpDbOrm\Pagination\Cursor;

interface CursorPaginator
{
    /**
     * @desc 获取给定游标的 URL
     * @param Cursor|null $cursor
     * @return string
     */
    public function url($cursor);

    /**
     * @desc 向分页器添加一组查询字符串值
     * @param array|string|null $key
     * @param string|null $value
     * @return $this
     */
    public function appends($key, $value = null);

    /**
     * @desc 获取/设置要附加到 URL 的 URL 片段
     * @param string|null $fragment
     * @return $this|string|null
     */
    public function fragment($fragment = null);

    /**
     * @desc 获取上一页的 URL，或者为 null
     * @return string|null
     */
    public function previousPageUrl();

    /**
     * @desc 下一页的 URL，或 null。
     * @return string|null
     */
    public function nextPageUrl();

    /**
     * @desc 获取所有被分页的项目
     * @return array
     */
    public function items();

    /**
     * @desc 获取上一组项目的“游标”
     * @return Cursor|null
     */
    public function previousCursor();

    /**
     * @desc 获取下一组项目的“光标”
     * @return Cursor|null
     */
    public function nextCursor();

    /**
     * @desc 确定每页显示多少项目确定每页显示多少项目
     * @return int
     */
    public function perPage();

    /**
     * @desc 获取当前正在分页的游标
     * @return Cursor|null
     */
    public function cursor();

    /**
     * @desc 定是否有足够的项目拆分成多个页面
     * @return bool
     */
    public function hasPages();

    /**
     * @desc 获取分页器生成的 URL 的基本路径
     * @return string|null
     */
    public function path();

    /**
     * @desc 确定项目列表是否为空
     * @return bool
     */
    public function isEmpty();

    /**
     * @desc 确定项目列表是否不为空
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
