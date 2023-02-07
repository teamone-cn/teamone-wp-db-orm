<?php

namespace Teamone\TeamoneWpDbOrm;

use ArrayAccess;
use Closure;

class GeneralUtil
{
    /**
     * @desc 确定给定的字符串是否包含给定的子字符串
     * @param string $haystack
     * @param string|string[] $needles
     * @return bool
     */
    public static function contains($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @desc 使用“点”符号检查数组中是否存在一个或多个项
     * @param ArrayAccess|array  $array
     * @param  string|array  $keys
     * @return bool
     */
    public static function has($array, $keys)
    {
        $keys = (array) $keys;

        if (! $array || $keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            $subKeyArray = $array;

            if (static::exists($array, $key)) {
                continue;
            }

            foreach (explode('.', $key) as $segment) {
                if (static::accessible($subKeyArray) && static::exists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @desc 使用 "." 符号从数组中获取一个项
     * @param ArrayAccess|array $array
     * @param string|int|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        if ( !static::accessible($array)) {
            return static::value($default);
        }

        if (is_null($key)) {
            return $array;
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (strpos($key, '.') === false) {
            return $array[$key] ?? static::value($default);
        }

        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return static::value($default);
            }
        }

        return $array;
    }

    /**
     * @desc 返回给定值的默认值
     * @param $value
     * @param ...$args
     * @return mixed
     */
    public static function value($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }

    /**
     * @desc 确定给定值是否可被数组访问
     * @param mixed $value
     * @return bool
     */
    public static function accessible($value)
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * @desc 确定给定的键是否存在于提供的数组中
     * @param ArrayAccess|array $array
     * @param string|int $key
     * @return bool
     */
    public static function exists($array, $key)
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        return array_key_exists($key, $array);
    }

    /**
     * @desc 获取除指定键数组外的所有给定数组
     * @param array $array
     * @param array|string $keys
     * @return array
     */
    public static function except($array, $keys)
    {
        static::forget($array, $keys);

        return $array;
    }

    /**
     * @desc 使用“点”符号从给定数组中删除一个或多个数组项。
     * @param array $array
     * @param array|string $keys
     * @return void
     */
    public static function forget(&$array, $keys)
    {
        $original = &$array;

        $keys = (array) $keys;

        if (count($keys) === 0) {
            return;
        }

        foreach ($keys as $key) {
            if (static::exists($array, $key)) {
                unset($array[$key]);

                continue;
            }

            $parts = explode('.', $key);

            $array = &$original;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }

            unset($array[array_shift($parts)]);
        }
    }

    /**
     * @desc 将字符串转换为蛇形大小写
     * @param string $value
     * @param string $delimiter
     * @return string
     */
    public static function snake($value, $delimiter = '_')
    {
        if ( !ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));

            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return $value;
    }

    /**
     * @desc 将给定的字符串转换为小写
     * @param string $value
     * @return string
     */
    public static function lower($value)
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * @desc 将多维数组平展为单个层次
     * @param iterable $array
     * @param float $depth
     * @return array
     */
    public static function flatten($array, $depth = INF)
    {
        if ( !is_array($array)) {
            $array = [$array];
        }

        $result = [];

        foreach ($array as $item) {
            if ( !is_array($item)) {
                $result[] = $item;
            } else {
                $values = $depth === 1
                    ? array_values($item)
                    : static::flatten($item, $depth - 1);

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * @desc 如果给定的值不是数组，也不是null，则将其包装为一个数组
     * @param  mixed  $value
     * @return array
     */
    public static function wrap($value)
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * @desc 创建未通过给定真值测试的所有元素的集合
     * @param callable|mixed $callback
     * @return array
     */
    public static function reject(array $items, $callback = true)
    {
        $useAsCallable = !is_string($callback) && is_callable($callback);

        return static::filter($items, function ($value, $key) use ($callback, $useAsCallable){
            if ($useAsCallable) {
                return !$callback($value, $key);
            } else {
                return true;
            }
        });
    }

    /**
     * @desc 对每个项目运行过滤器
     * @param array $items
     * @param callable|null $callback
     * @return array
     */
    public static function filter(array $items, callable $callback = null)
    {
        return array_filter($items, $callback, ARRAY_FILTER_USE_BOTH);
    }

}
