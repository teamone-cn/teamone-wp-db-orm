<?php

namespace Teamone\TeamoneWpDbOrm\Pagination;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Arrayable;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Jsonable;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Paginator as PaginatorContract;

class Paginator extends AbstractPaginator implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable, PaginatorContract
{
    /**
     * @return bool
     */
    protected $hasMore;

    /**
     * @desc 创建新的分页实例
     * @param mixed $items
     * @param int $perPage
     * @param int|null $currentPage
     * @param array $options (path, query, fragment, pageName)
     * @return void
     */
    public function __construct($items, $perPage, $currentPage = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->perPage     = $perPage;
        $this->currentPage = $this->setCurrentPage($currentPage);
        $this->path        = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;

        $this->setItems($items);
    }

    /**
     * @desc 获取请求的当前页面
     * @param int $currentPage
     * @return int
     */
    protected function setCurrentPage($currentPage)
    {
        $currentPage = $currentPage ? : static::resolveCurrentPage();

        return $this->isValidPageNumber($currentPage) ? (int) $currentPage : 1;
    }

    /**
     * @desc 为分页器设置项目
     * @param array $items
     * @return void
     */
    protected function setItems(array $items)
    {
        $this->items = $items;

        $this->hasMore = count($this->items) > $this->perPage;

        $this->items = array_slice($this->items, 0, $this->perPage, true);
    }

    /**
     * @return string|null
     */
    public function nextPageUrl()
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage() + 1);
        }

        return null;
    }

    /**
     * @param string|null $view
     * @param array $data
     * @return string
     */
    public function links($view = null, $data = [])
    {
        return $this->render($view, $data);
    }

    /**
     * @param string|null $view
     * @param array $data
     * @return string
     */
    public function render($view = null, $data = [])
    {
        return json_encode($this->toArray());
    }

    /**
     * @desc 手动指示分页器确实有更多页面
     * @param bool $hasMore
     * @return $this
     */
    public function hasMorePagesWhen($hasMore = true)
    {
        $this->hasMore = $hasMore;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasMorePages()
    {
        return $this->hasMore;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'current_page'   => $this->currentPage(),
            'data'           => $this->items,
            'first_page_url' => $this->url(1),
            'from'           => $this->firstItem(),
            'next_page_url'  => $this->nextPageUrl(),
            'path'           => $this->path(),
            'per_page'       => $this->perPage(),
            'prev_page_url'  => $this->previousPageUrl(),
            'to'             => $this->lastItem(),
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
