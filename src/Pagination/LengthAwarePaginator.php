<?php

namespace Teamone\TeamoneWpDbOrm\Pagination;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Arrayable;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Jsonable;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\LengthAwarePaginator as LengthAwarePaginatorContract;

class LengthAwarePaginator extends AbstractPaginator implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable, LengthAwarePaginatorContract
{
    /**
     * @var int 总数
     */
    protected $total;

    /**
     * @var int 最后页码
     */
    protected $lastPage;

    /**
     * @desc 创建分页实例
     * @param mixed $items
     * @param int $total
     * @param int $perPage
     * @param int|null $currentPage
     * @param array $options (path, query, fragment, pageName)
     * @return void
     */
    public function __construct($items, $total, $perPage, $currentPage = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->total       = $total;
        $this->perPage     = $perPage;
        $this->lastPage    = max((int) ceil($total / $perPage), 1);
        $this->path        = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;
        $this->currentPage = $this->setCurrentPage($currentPage, $this->pageName);
        $this->items       = $items;
    }

    /**
     * @desc 设置当前页
     * @param int $currentPage
     * @param string $pageName
     * @return int
     */
    protected function setCurrentPage($currentPage, $pageName)
    {
        $currentPage = $currentPage ? : static::resolveCurrentPage($pageName);

        return $this->isValidPageNumber($currentPage) ? (int) $currentPage : 1;
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
     * @desc 获取总数
     * @return int
     */
    public function total()
    {
        return $this->total;
    }

    /**
     * @desc 是否有更多页
     * @return bool
     */
    public function hasMorePages()
    {
        return $this->currentPage() < $this->lastPage();
    }

    /**
     * @desc 获取下一页的 URL
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
     * @return int 最后一页
     */
    public function lastPage()
    {
        return $this->lastPage;
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
            'last_page'      => $this->lastPage(),
            'last_page_url'  => $this->url($this->lastPage()),
            'next_page_url'  => $this->nextPageUrl(),
            'path'           => $this->path(),
            'per_page'       => $this->perPage(),
            'prev_page_url'  => $this->previousPageUrl(),
            'to'             => $this->lastItem(),
            'total'          => $this->total(),
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
