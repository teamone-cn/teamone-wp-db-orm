<?php

namespace Teamone\TeamoneWpDbOrm\Pagination;

use Teamone\TeamoneWpDbOrm\Pagination\Contract\Arrayable;
use UnexpectedValueException;

class Cursor implements Arrayable
{
    /**
     * @desc 与游标关联的参数
     * @var array
     */
    protected $parameters;

    /**
     * @desc 确定光标是指向下一组还是上一组项目
     * @var bool
     */
    protected $pointsToNextItems;

    /**
     * @desc 创建新的游标实例
     * @param  array  $parameters
     * @param  bool  $pointsToNextItems
     */
    final public function __construct(array $parameters, $pointsToNextItems = true)
    {
        $this->parameters = $parameters;
        $this->pointsToNextItems = $pointsToNextItems;
    }

    /**
     * @desc 从游标中获取给定的参数
     * @param  string  $parameterName
     * @return string|null
     *
     * @throws UnexpectedValueException
     */
    public function parameter(string $parameterName)
    {
        if (! array_key_exists($parameterName, $this->parameters)) {
            throw new UnexpectedValueException("Unable to find parameter [{$parameterName}] in pagination item.");
        }

        return $this->parameters[$parameterName];
    }

    /**
     * @desc 从游标中获取给定的参数
     * @param  array  $parameterNames
     * @return array
     */
    public function parameters(array $parameterNames)
    {
        $callback = function ($parameterName) {
            return $this->parameter($parameterName);
        };

        $keys = array_keys($parameterNames);

        $items = array_map($callback, $parameterNames, $keys);

        return  array_combine($keys, $items);
    }

    /**
     * @desc 判断光标是否指向下一组项目
     * @return bool
     */
    public function pointsToNextItems()
    {
        return $this->pointsToNextItems;
    }

    /**
     * @desc 判断光标是否指向上一组项目
     * @return bool
     */
    public function pointsToPreviousItems()
    {
        return ! $this->pointsToNextItems;
    }

    /**
     * @desc 获取游标的数组表示
     * @return array
     */
    public function toArray()
    {
        return array_merge($this->parameters, [
            '_pointsToNextItems' => $this->pointsToNextItems,
        ]);
    }

    /**
     * @desc 获取光标的编码字符串表示以构造 URL
     *
     * @return string
     */
    public function encode()
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($this->toArray())));
    }

    /**
     * @desc 从编码的字符串表示中获取游标实例
     * @param  string|null  $encodedString
     * @return static|null
     */
    public static function fromEncoded($encodedString)
    {
        if (is_null($encodedString) || ! is_string($encodedString)) {
            return null;
        }

        $parameters = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $encodedString)), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $pointsToNextItems = $parameters['_pointsToNextItems'];

        unset($parameters['_pointsToNextItems']);

        return new static($parameters, $pointsToNextItems);
    }
}
