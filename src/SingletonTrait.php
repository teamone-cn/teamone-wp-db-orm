<?php

namespace Teamone\TeamoneWpDbOrm;

trait SingletonTrait
{
    /**
     * @var static[]
     */
    private static $instances = [];

    /**
     * @param mixed ...$args
     * @return static
     */
    public static function getInstance(...$args)
    {
        $className = static::class;

        if ( !isset(self::$instances[$className])) {
            self::$instances[$className] = new static(...$args);
        }

        return self::$instances[$className];
    }

    /**
     * @desc 获取所有实例
     * @return static[]
     */
    public function getInstances()
    {
        return self::$instances;
    }
}
