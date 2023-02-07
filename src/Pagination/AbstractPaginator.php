<?php

namespace Teamone\TeamoneWpDbOrm\Pagination;

use ArrayIterator;
use BadMethodCallException;
use Closure;
use Teamone\TeamoneWpDbOrm\Pagination\Contract\Htmlable;

abstract class AbstractPaginator implements Htmlable
{
    /**
     * @var array 分页项目
     */
    protected $items;

    /**
     * @var int 每页显示的项目数
     */
    protected $perPage;

    /**
     * @var int 正在“查看”的当前页面
     */
    protected $currentPage;

    /**
     * @var string 分配给所有 URL 的基本路径
     */
    protected $path = '/';

    /**
     * @var array 添加到所有 URL 的查询参数
     */
    protected $query = [];

    /**
     * @var string|null 要添加到所有 URL 的 URL 片段
     */
    protected $fragment;

    /**
     * @var string 用于存储页面的查询字符串变量
     */
    protected $pageName = 'page';

    /**
     * @var int 当前页面链接每边显示的链接数
     */
    public $onEachSide = 3;

    /**
     * @var array 分页选项
     */
    protected $options;

    /**
     * @var Closure 当前路径解析器回调
     */
    protected static $currentPathResolver;

    /**
     * @var Closure 当前页面解析器回调
     */
    protected static $currentPageResolver;

    /**
     * @var Closure 当前查询字符解析器回调
     */
    protected static $queryStringResolver;

    /**
     * 确定给定值是否为有效页码
     * @param int $page
     * @return bool
     */
    protected function isValidPageNumber($page)
    {
        return $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * @desc 上一个页面路径
     * @return string|null
     */
    public function previousPageUrl()
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }

        return null;
    }

    /**
     * @desc 创建一个范围分页 URLs
     * @param int $start
     * @param int $end
     * @return array
     */
    public function getUrlRange($start, $end)
    {
        $callback = function ($page){
            return [$page => $this->url($page)];
        };

        $items = range($start, $end);

        $result = [];

        foreach ($items as $value) {
            $assoc = $callback($value);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return $result;
    }

    /**
     * @desc 获取给定页码的 URL
     * @param int $page
     * @return string
     */
    public function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }

        // 如果我们有任何额外的查询字符串键/值对需要添加到 URL 中，我们将把它们放在查询字符串形式中，
        // 然后将其附加到 URL 中。 这允许额外的信息，如排序存储。
        $parameters = [$this->pageName => $page];

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
     * @desc 取/设置要附加到 URL 的 URL 片段
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
        if (isset(static::$queryStringResolver)) {
            return $this->appends(call_user_func(static::$queryStringResolver));
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
        if ($key !== $this->pageName) {
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
     * @return array
     */
    public function items()
    {
        return $this->items;
    }

    /**
     * @return int
     */
    public function firstItem()
    {
        return count($this->items) > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    /**
     * @return int
     */
    public function lastItem()
    {
        return count($this->items) > 0 ? $this->firstItem() + $this->count() - 1 : null;
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
     * @desc 是否有更多页
     * @return bool
     */
    abstract public function hasMorePages();

    /**
     * @desc 确定是否有足够的项目拆分成多个页面
     * @return bool
     */
    public function hasPages()
    {
        return $this->currentPage() != 1 || $this->hasMorePages();
    }

    /**
     * @desc 确定分页器是否在第一页
     * @return bool
     */
    public function onFirstPage()
    {
        return $this->currentPage() <= 1;
    }

    /**
     * @desc 获取当前页面
     * @return int
     */
    public function currentPage()
    {
        return $this->currentPage;
    }

    /**
     * @desc 获取用于存储页面的查询字符串变量
     * @return string
     */
    public function getPageName()
    {
        return $this->pageName;
    }

    /**
     * @desc 设置用于存储页面的查询字符串变量
     * @param string $name
     * @return $this
     */
    public function setPageName($name)
    {
        $this->pageName = $name;

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
     * @desc 设置在当前页面链接的每一侧显示的链接数
     * @param int $count
     * @return $this
     */
    public function onEachSide($count)
    {
        $this->onEachSide = $count;

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
     * @desc 解析当前请求路径或返回默认值
     * @param string $default
     * @return string
     */
    public static function resolveCurrentPath($default = '/')
    {
        if (isset(static::$currentPathResolver)) {
            return call_user_func(static::$currentPathResolver);
        }

        return $default;
    }

    /**
     * @desc 设置当前请求路径解析器回调
     * @param Closure $resolver
     * @return void
     */
    public static function currentPathResolver(Closure $resolver)
    {
        static::$currentPathResolver = $resolver;
    }

    /**
     * @desc 解析当前页面或返回默认值
     * @param string $pageName
     * @param int $default
     * @return int
     */
    public static function resolveCurrentPage($pageName = 'page', $default = 1)
    {
        if (isset(static::$currentPageResolver)) {
            return (int) call_user_func(static::$currentPageResolver, $pageName);
        }

        return $default;
    }

    /**
     * @desc 设置当前页面解析器回调
     * @param Closure $resolver
     * @return void
     */
    public static function currentPageResolver(Closure $resolver)
    {
        static::$currentPageResolver = $resolver;
    }

    /**
     * @desc 解析查询字符串或返回默认值
     * @param string|array|null $default
     * @return string
     */
    public static function resolveQueryString($default = null)
    {
        if (isset(static::$queryStringResolver)) {
            return (static::$queryStringResolver)();
        }

        return $default;
    }

    /**
     * @desc 使用查询字符串解析器回调设置
     * @param Closure $resolver
     * @return void
     */
    public static function queryStringResolver(Closure $resolver)
    {
        static::$queryStringResolver = $resolver;
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
        return count($this->items);
    }

    /**
     * @desc 获取分页器选项
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
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
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->items[$key] = $value;
    }

    /**
     * @param mixed $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * @desc 渲染
     * @return string
     */
    abstract public function render();

    /**
     * @return string
     */
    public function toHtml()
    {
        return static::render();
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
     * @return string
     */
    public function __toString()
    {
        return static::render();
    }
}
