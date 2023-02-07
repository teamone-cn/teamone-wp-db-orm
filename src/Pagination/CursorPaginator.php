<?php

namespace Teamone\TeamoneWpDbOrm\Pagination;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Arrayable;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\CursorPaginator as PaginatorContract;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Jsonable;

class CursorPaginator extends AbstractCursorPaginator implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable, PaginatorContract
{
    /**
     * @desc 创建新的分页实例
     * @param mixed $items
     * @param int $perPage
     * @param Cursor|null $cursor
     * @param array $options (path, query, fragment, pageName)
     * @return void
     */
    public function __construct($items, $perPage, $cursor = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->perPage = $perPage;
        $this->cursor  = $cursor;
        $this->path    = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;

        $this->setItems($items);
    }

    /**
     * @desc 为分页器设置项目
     * @param mixed $items
     * @return void
     */
    protected function setItems($items)
    {
        $this->hasMore = count($items) > $this->perPage;
        $offset        = 0;
        $length        = $this->perPage;
        $items         = array_slice($items, $offset, $length, true);

        if ( !is_null($this->cursor) && $this->cursor->pointsToPreviousItems()) {
            $items = array_reverse($items, true);
            $items = array_values($items);
        }

        $this->items = $items;
    }

    /**
     * @desc 使用给定的视图渲染分页器
     * @param string|null $view
     * @param array $data
     * @return string
     */
    public function links($view = null, $data = [])
    {
        return $this->render($view, $data);
    }

    /**
     * @desc 使用给定的视图渲染分页器
     * @param string|null $view
     * @param array $data
     * @return string
     */
    public function render($view = null, $data = [])
    {
        return json_encode($this->toArray());
    }

    /**
     * @desc 判断数据源中是否有更多项
     * @return bool
     */
    public function hasMorePages()
    {
        return (is_null($this->cursor) && $this->hasMore)
               || ( !is_null($this->cursor) && $this->cursor->pointsToNextItems() && $this->hasMore)
               || ( !is_null($this->cursor) && $this->cursor->pointsToPreviousItems());
    }

    /**
     * @desc 确定是否有足够的项目拆分成多个页面
     * @return bool
     */
    public function hasPages()
    {
        return !$this->onFirstPage() || $this->hasMorePages();
    }

    /**
     * @desc 确定分页器是否在第一页
     * @return bool
     */
    public function onFirstPage()
    {
        return is_null($this->cursor) || ($this->cursor->pointsToPreviousItems() && !$this->hasMore);
    }

    /**
     * @desc 将实例作为数组获取
     * @return array
     */
    public function toArray()
    {
        return [
            'data'          => $this->items,
            'path'          => $this->path(),
            'per_page'      => $this->perPage(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_page_url' => $this->previousPageUrl(),
        ];
    }

    /**
     * @desc 将对象转换为 JSON 可序列化的
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @desc 将对象转换为其 JSON 表示形式
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
