<?php

namespace Teamone\TeamoneWpDbOrm\Pagination;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use Closure;
use Error;
use Exception;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Htmlable;
use stdClass;

abstract class AbstractCursorPaginator implements Htmlable
{
    /**
     * @var array
     */
    protected $items;

    /**
     * @var int 每页数
     */
    protected $perPage;

    /**
     * @var string URLs 路径
     */
    protected $path = '/';

    /**
     * @var array URLs 查询参数
     */
    protected $query = [];

    /**
     * @var string|null 要添加到所有 URL 的 URL 片段
     */
    protected $fragment;

    /**
     * @var string 游标名称
     */
    protected $cursorName = 'cursor';

    /**
     * @var Cursor|null 游标
     */
    protected $cursor;

    /**
     * @var array 当前游标分页参数
     */
    protected $parameters;

    /**
     * @var array 分页选项
     */
    protected $options;

    /**
     * @var Closure 当前游标解析器回调
     */
    protected static $currentCursorResolver;

    /**
     * @return bool 指示数据源中是否有更多项
     */
    protected $hasMore;

    /**
     * 获取给定游标的 URL
     *
     * @param Cursor|null $cursor
     * @return string
     */
    public function url($cursor)
    {
        // 如果我们有任何额外的查询字符串键/值对需要添加到 URL 中，我们将把它们放在查询字符串形式中，
        // 然后将其附加到 URL 中。 这允许额外的信息，如排序存储。
        $parameters = is_null($cursor) ? [] : [$this->cursorName => $cursor->encode()];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        $parameters = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return $this->path()
               . ((mb_strpos($this->path(), '?') !== false) ? '&' : '?')
               . $parameters
               . $this->buildFragment();
    }

    /**
     * @desc 获取上一页的 URL
     * @return string|null
     */
    public function previousPageUrl()
    {
        if (is_null($previousCursor = $this->previousCursor())) {
            return null;
        }

        return $this->url($previousCursor);
    }

    /**
     * @desc 获取下一页的 URL
     * @return string|null
     */
    public function nextPageUrl()
    {
        if (is_null($nextCursor = $this->nextCursor())) {
            return null;
        }

        return $this->url($nextCursor);
    }

    /**
     * @desc 获取指向上一组项目的“光标”
     * @return Cursor|null
     */
    public function previousCursor()
    {
        if (is_null($this->cursor)
            || ($this->cursor->pointsToPreviousItems() && !$this->hasMore)) {
            return null;
        }

        $items = $this->items;
        reset($items);
        $first = current($items);

        return $this->getCursorForItem($first, false);
    }

    /**
     * @desc 获取指向下一组项目的“光标”
     * @return Cursor|null
     */
    public function nextCursor()
    {
        if ((is_null($this->cursor) && !$this->hasMore)
            || ( !is_null($this->cursor) && $this->cursor->pointsToNextItems() && !$this->hasMore)) {
            return null;
        }

        $items = $this->items;
        $last  = end($items);

        return $this->getCursorForItem($last);
    }

    /**
     * @desc 获取给定项目的游标实例
     * @param ArrayAccess|stdClass $item
     * @param bool $isNext
     * @return Cursor
     */
    public function getCursorForItem($item, $isNext = true)
    {
        $parameters = $this->getParametersForItem($item);

        return new Cursor($parameters, $isNext);
    }

    /**
     * @desc 获取给定对象的游标参数
     * @param $item
     * @return array|false
     * @throws Exception
     */
    public function getParametersForItem($item)
    {
        $callback = function ($_, $parameterName) use ($item){
            $position = strrpos($parameterName, '.');

            if ($position === false) {
                $parameterNameSubstr = $parameterName;
            } else {
                $parameterNameSubstr = substr($parameterName, $position + strlen('.'));
            }

            if ($item instanceof ArrayAccess || is_array($item)) {
                return $item[$parameterName] ?? $item[$parameterNameSubstr];
            } elseif (is_object($item)) {
                return $item->{$parameterName} ?? $item->{$parameterNameSubstr};
            }

            throw new Exception('Only arrays and objects are supported when cursor paginating items.');
        };

        $parameters = array_flip($this->parameters);

        $keys = array_keys($parameters);

        $items = array_map($callback, $parameters, $keys);

        $items = array_combine($keys, $items);

        return $items;
    }

    /**
     * @desc 返回最后一次出现给定值后字符串的剩余部分
     * @param string $subject
     * @param string $search
     * @return string
     */
    public function afterLast($subject, $search)
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, (string) $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + strlen($search));
    }

    /**
     * @desc 获取/设置要附加到 URL 的 URL 片段
     * @param string|null $fragment
     * @return $this|string|null
     */
    public function fragment($fragment = null)
    {
        if (is_null($fragment)) {
            return $this->fragment;
        }

        $this->fragment = $fragment;

        return $this;
    }

    /**
     * @desc 向分页器添加一组查询字符串值
     * @param array|string|null $key
     * @param string|null $value
     * @return $this
     */
    public function appends($key, $value = null)
    {
        if (is_null($key)) {
            return $this;
        }

        if (is_array($key)) {
            return $this->appendArray($key);
        }

        return $this->addQuery($key, $value);
    }

    /**
     * @desc 添加一个查询字符串值数组
     * @param array $keys
     * @return $this
     */
    protected function appendArray(array $keys)
    {
        foreach ($keys as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    /**
     * @desc 将所有当前查询字符串值添加到分页器
     * @return $this
     */
    public function withQueryString()
    {
        if ( !is_null($query = Paginator::resolveQueryString())) {
            return $this->appends($query);
        }

        return $this;
    }

    /**
     * @desc 向分页器添加查询字符串值
     * @param string $key
     * @param string $value
     * @return $this
     */
    protected function addQuery($key, $value)
    {
        if ($key !== $this->cursorName) {
            $this->query[$key] = $value;
        }

        return $this;
    }

    /**
     * @desc 构建 URL 的完整片段部分
     * @return string
     */
    protected function buildFragment()
    {
        return $this->fragment ? '#' . $this->fragment : '';
    }

    /**
     * @desc 获取正在分页的项目切片
     * @return array
     */
    public function items()
    {
        return $this->items;
    }

    /**
     * @desc 使用回调转换项目切片中的每个项目
     * @param callable $callback
     * @return $this
     */
    public function through(callable $callback)
    {
        $keys = array_keys($this->items);

        $this->items = array_map($callback, $this->items, $keys);

        $this->items = array_combine($keys, $this->items);

        return $this;
    }

    /**
     * @desc 获取每页显示的项目数
     * @return int
     */
    public function perPage()
    {
        return $this->perPage;
    }

    /**
     * @desc 获取正在分页的当前光标
     * @return Cursor|null
     */
    public function cursor()
    {
        return $this->cursor;
    }

    /**
     * @desc 获取用于存储游标的查询字符串变量
     * @return string
     */
    public function getCursorName()
    {
        return $this->cursorName;
    }

    /**
     * @desc 设置用于存储游标的查询字符串变量
     * @param string $name
     * @return $this
     */
    public function setCursorName($name)
    {
        $this->cursorName = $name;

        return $this;
    }

    /**
     * @desc 设置分配给所有 URL 的基本路径
     * @param string $path
     * @return $this
     */
    public function withPath($path)
    {
        return $this->setPath($path);
    }

    /**
     * @desc 设置分配给所有 URL 的基本路径
     * @param string $path
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @desc 获取分页器生成的 URL 的基本路径
     * @return string|null
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * @desc 解析当前游标或返回默认值
     * @param string $cursorName
     * @return Cursor|null
     */
    public static function resolveCurrentCursor($cursorName = 'cursor', $default = null)
    {
        if (isset(static::$currentCursorResolver)) {
            return call_user_func(static::$currentCursorResolver, $cursorName);
        }

        return $default;
    }

    /**
     * @desc 设置当前游标解析器回调
     * @param Closure $resolver
     * @return void
     */
    public static function currentCursorResolver(Closure $resolver)
    {
        static::$currentCursorResolver = $resolver;
    }

    /**
     * @desc 获取项目的迭代器
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * @return bool
     */
    public function isNotEmpty()
    {
        return !empty($this->items);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->items());
    }

    /**
     * @desc 获取分页选项
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @desc 确定给定的项目是否存在
     * @param mixed $key
     * @return bool
     */
    public function offsetExists($key)
    {
        $hasCallback = function ($items, $key){
            $keys = is_array($key) ? $key : func_get_args();

            foreach ($keys as $value) {
                if ( !array_key_exists($value, $items)) {
                    return false;
                }
            }

            return true;
        };

        return $hasCallback($this->items, $key);
    }

    /**
     * @desc 获取给定的键值
     * @param mixed $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return null;
    }

    /**
     * @desc 设置给定的键值
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->items[$key] = $value;
    }

    /**
     * @desc 销毁给定的键值
     * @param mixed $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * @return string
     */
    public function toHtml()
    {
        return '';
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $message = sprintf('Call to undefined method %s::%s()', static::class, $method);

        throw new BadMethodCallException($message);
    }

    /**
     * @desc 渲染
     * @return string
     */
    abstract public function render();

    /**
     * @return string
     */
    public function __toString()
    {
        return static::render();
    }

    /**
     * @desc 将方法调用转发给给定的对象
     * @param mixed $object
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    protected function forwardCallTo($object, $method, $parameters)
    {
        try {
            return $object->{$method}(...$parameters);
        } catch (Error|BadMethodCallException $e) {

            $message = sprintf($e->getMessage() . ', Call to undefined method %s::%s()', static::class, $method);

            throw new BadMethodCallException($message);
        }
    }
}
