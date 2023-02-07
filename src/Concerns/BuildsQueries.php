<?php

namespace Teamone\TeamoneWpDbOrm\Concerns;

use Generator;
use InvalidArgumentException;
use Teamone\TeamoneWpDbOrm\Pagination\Cursor;
use Teamone\TeamoneWpDbOrm\Pagination\CursorPaginator;
use Teamone\TeamoneWpDbOrm\Pagination\LengthAwarePaginator;
use Teamone\TeamoneWpDbOrm\Pagination\Paginator;
use Teamone\TeamoneWpDbOrm\Query\Builder;
use RuntimeException;

trait BuildsQueries
{
    /**
     * @desc 分块查询结果
     * @param int $count
     * @param callable $callback
     * @return bool
     */
    public function chunk($count, callable $callback)
    {
        $this->enforceOrderBy();

        $page = 1;

        do {
            // 我们将执行给定页面的查询并获得结果。如果没有结果，我们可以中断并从这里返回。
            // 当有结果时，我们将在此处使用这些结果的当前块调用回调。
            $results = $this->forPage($page, $count)->get();

            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            // 在每个块结果集上，我们将它们传递给回调，然后让开发人员处理回调中的所有事情，
            // 这使我们能够保持低内存，以便通过大型结果集进行工作。
            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * @desc 分块时对每个项目执行回调
     * @param callable $callback
     * @param int $count
     * @return bool
     *
     * @throws RuntimeException
     */
    public function each(callable $callback, $count = 1000)
    {
        return $this->chunk($count, function ($results) use ($callback){
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * @desc 通过比较 ID 对查询结果进行分块
     * @param int $count
     * @param callable $callback
     * @param string|null $column
     * @param string|null $alias
     * @return bool
     */
    public function chunkById($count, callable $callback, $column = null, $alias = null)
    {
        $column = $column ?? $this->defaultKeyName();

        $alias = $alias ?? $column;

        $lastId = null;

        $page = 1;

        do {
            $clone = clone $this;

            // 我们将执行给定页面的查询并获得结果。如果没有结果，我们可以中断并从这里返回。
            // 当有结果时，我们将在此处使用这些结果的当前块调用回调。
            $results = $clone->forPageAfterId($count, $lastId, $column)->get();

            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            // 在每个块结果集上，我们将它们传递给回调，然后让开发人员处理回调中的所有事情，这使我们能够保持低内存，以便通过大型结果集进行工作。
            if ($callback($results, $page) === false) {
                return false;
            }

            reset($results);
            $last = end($results);

            $lastId = $last->{$alias};

            if ($lastId === null) {
                throw new RuntimeException("The chunkById operation was aborted because the [{$alias}] column is not present in the query result.");
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * @desc 惰性查询，按给定大小的块
     * @param int $chunkSize
     * @return Generator
     *
     * @throws InvalidArgumentException
     */
    public function lazy($chunkSize = 1000)
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk size should be at least 1');
        }

        $this->enforceOrderBy();

        return $this->lazyGenerator($chunkSize);
    }

    /**
     * @desc 惰性查询生成器
     * @param $chunkSize
     * @return Generator|void
     */
    protected function lazyGenerator($chunkSize)
    {
        $page = 1;

        while (true) {
            $results = $this->forPage($page++, $chunkSize)->get();

            foreach ($results as $result) {
                yield $result;
            }

            if (count($results) < $chunkSize) {
                return;
            }
        }
    }

    /**
     * @desc 惰性查询，通过比较 ID 对查询结果进行分块
     * @param int $chunkSize
     * @param string|null $column
     * @param string|null $alias
     * @param bool $descending
     * @return Generator
     *
     * @throws InvalidArgumentException
     */
    public function lazyById($chunkSize = 1000, $column = null, $alias = null, $descending = false)
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk size should be at least 1');
        }

        $column = $column ?? $this->defaultKeyName();

        $alias = $alias ?? $column;

        return $this->lazyByIdGenerator($chunkSize, $column, $alias, $descending);
    }

    /**
     * @desc 惰性查询生成器
     * @param $chunkSize
     * @param $column
     * @param $alias
     * @param $descending
     * @return Generator|void
     */
    protected function lazyByIdGenerator($chunkSize, $column, $alias, $descending)
    {
        $lastId = null;

        while (true) {
            $clone = clone $this;

            if ($descending) {
                $results = $clone->forPageBeforeId($chunkSize, $lastId, $column)->get();
            } else {
                $results = $clone->forPageAfterId($chunkSize, $lastId, $column)->get();
            }

            foreach ($results as $result) {
                yield $result;
            }

            if (count($results) < $chunkSize) {
                return;
            }

            reset($results);

            $last = end($results);

            $lastId = $last->{$alias} ?? null;
        }
    }

    /**
     * @desc 执行查询并得到第一个结果
     * @param array|string $columns
     * @return object|null
     */
    public function first($columns = ['*'])
    {
        $result = $this->take(1)->get($columns);
        reset($result);

        return current($result);
    }

    /**
     * @desc 执行查询并获取第一个结果（如果它是唯一匹配的记录）
     * @param array|string $columns
     * @return object|null
     *
     * @throws RuntimeException
     * @throws RuntimeException
     */
    public function sole($columns = ['*'])
    {
        $result = $this->take(2)->get($columns);

        if (empty($result)) {
            throw new RuntimeException;
        }

        if (count($result) > 1) {
            throw new RuntimeException;
        }

        reset($result);

        return current($result);
    }

    /**
     * @desc 使用游标分页器对给定查询进行分页
     * @param int $perPage
     * @param array $columns
     * @param string $cursorName
     * @param Cursor|string|null $cursor
     * @return CursorPaginator
     */
    protected function paginateUsingCursor($perPage, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        if ( !$cursor instanceof Cursor) {
            $cursor = is_string($cursor) ? Cursor::fromEncoded($cursor) : CursorPaginator::resolveCurrentCursor($cursorName, $cursor);
        }

        $orders = $this->ensureOrderForCursorPagination(!is_null($cursor) && $cursor->pointsToPreviousItems());

        if ( !is_null($cursor)) {
            $addCursorConditions = function (self $builder, $previousColumn, $i) use (&$addCursorConditions, $cursor, $orders){
                if ( !empty($builder->unions)) {
                    $unionBuilders = array_column((array) $builder->unions, 'query');
                } else {
                    $unionBuilders = [];
                }

                if ( !is_null($previousColumn)) {
                    $builder->where(
                        $this->getOriginalColumnNameForCursorPagination($this, $previousColumn),
                        '=',
                        $cursor->parameter($previousColumn)
                    );

                    /** @var Builder $unionBuilder */
                    foreach ($unionBuilders as $unionBuilder) {
                        if ($unionBuilder instanceof Builder) {
                            $unionBuilder->where(
                                $this->getOriginalColumnNameForCursorPagination($this, $previousColumn),
                                '=',
                                $cursor->parameter($previousColumn)
                            );
                            $this->addBinding($unionBuilder->getRawBindings()['where'], 'union');
                        }
                    }
                }

                $builder->where(function (self $builder) use ($addCursorConditions, $cursor, $orders, $i, $unionBuilders){
                    ['column' => $column, 'direction' => $direction] = $orders[$i];

                    $builder->where(
                        $this->getOriginalColumnNameForCursorPagination($this, $column),
                        $direction === 'asc' ? '>' : '<',
                        $cursor->parameter($column)
                    );

                    if ($i < count($orders) - 1) {
                        $builder->orWhere(function (self $builder) use ($addCursorConditions, $column, $i){
                            $addCursorConditions($builder, $column, $i + 1);
                        });
                    }

                    foreach ($unionBuilders as $unionBuilder) {
                        if ($unionBuilder instanceof Builder) {
                            $unionBuilder->where(function ($unionBuilder) use ($column, $direction, $cursor, $i, $orders, $addCursorConditions){
                                $unionBuilder->where(
                                    $this->getOriginalColumnNameForCursorPagination($this, $column),
                                    $direction === 'asc' ? '>' : '<',
                                    $cursor->parameter($column)
                                );

                                if ($i < count($orders) - 1) {
                                    $unionBuilder->orWhere(function (self $builder) use ($addCursorConditions, $column, $i){
                                        $addCursorConditions($builder, $column, $i + 1);
                                    });
                                }

                                $this->addBinding($unionBuilder->getRawBindings()['where'], 'union');
                            });
                        }
                    }
                });
            };

            $addCursorConditions($this, null, 0);
        }

        $this->limit($perPage + 1);

        if ( !empty($orders) && is_array($orders)) {
            $parameters = array_column($orders, 'column');
        } else {
            $parameters = [];
        }

        $items = $this->get($columns);

        return $this->cursorPaginator($items, $perPage, $cursor, [
            'path'       => Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'parameters' => $parameters,
        ]);
    }

    /**
     * @desc 获取给定列的原始列名，没有任何别名
     * @param Builder $builder
     * @param string $parameter
     * @return string
     */
    protected function getOriginalColumnNameForCursorPagination($builder, string $parameter)
    {
        //$columns = $builder instanceof EloquentBuilder ? $builder->getQuery()->columns : $builder->columns;
        $columns = $builder->columns;

        if ( !is_null($columns)) {
            foreach ($columns as $column) {
                if (($position = stripos($column, ' as ')) !== false) {
                    $as = substr($column, $position, 4);

                    [$original, $alias] = explode($as, $column);

                    if ($parameter === $alias) {
                        return $original;
                    }
                }
            }
        }

        return $parameter;
    }

    /**
     * @desc 创建一个新的长度感知分页器实例
     * @param array $items
     * @param int $total
     * @param int $perPage
     * @param int $currentPage
     * @param array $options
     * @return LengthAwarePaginator
     */
    protected function paginator($items, $total, $perPage, $currentPage, $options)
    {
        $lengthAwarePaginator = new LengthAwarePaginator($items, $total, $perPage, $currentPage, $options);

        return $lengthAwarePaginator;
    }

    /**
     * @desc 创建一个新的简单分页器实例
     * @param array $items
     * @param int $perPage
     * @param int $currentPage
     * @param array $options
     * @return Paginator
     */
    protected function simplePaginator($items, $perPage, $currentPage, $options)
    {
        $paginator = new Paginator($items, $perPage, $currentPage, $options);

        return $paginator;
    }

    /**
     * @desc 创建一个新的游标分页器实例
     * @param array $items
     * @param int $perPage
     * @param Cursor $cursor
     * @param array $options
     * @return CursorPaginator
     */
    protected function cursorPaginator($items, $perPage, $cursor, $options)
    {
        $cursorPaginator = new CursorPaginator($items, $perPage, $cursor, $options);

        return $cursorPaginator;
    }

    /**
     * @desc 将查询传递给给定的回调
     * @param callable $callback
     * @return $this|mixed
     */
    public function tap($callback)
    {
        return $this->when(true, $callback);
    }

    /**
     * @desc 如果给定的“值”为真，则应用回调
     * @param mixed $value
     * @param callable $callback
     * @param callable|null $default
     *
     * @return mixed
     */
    public function when($value, $callback, $default = null)
    {
        if ($value) {
            return $callback($this, $value) ? : $this;
        } elseif ($default) {
            return $default($this, $value) ? : $this;
        }

        return $this;
    }

    /**
     * @desc 如果给定的 "value" 是假的，则应用回调
     * @param mixed $value
     * @param callable $callback
     * @param callable|null $default
     *
     * @return mixed
     */
    public function unless($value, $callback, $default = null)
    {
        if ( !$value) {
            return $callback($this, $value) ? : $this;
        } elseif ($default) {
            return $default($this, $value) ? : $this;
        }

        return $this;
    }
}
