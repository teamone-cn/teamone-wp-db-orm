<?php

namespace Teamone\TeamoneWpDbOrm\Capsule;

use Teamone\TeamoneWpDbOrm\SingletonTrait;

abstract class PluginManagerAbstract implements DatabaseConfigContract
{
    use SingletonTrait;

    private $manager;

    protected function __construct()
    {
        $entryFile = static::getEntryFile();

        $entryFile = str_replace('\\', '/', $entryFile);

        $this->manager = Manager::resolverDatabaseConfig($this, $entryFile);
    }

    /**
     * @desc 获取入口文件
     * @return mixed
     */
    abstract public function getEntryFile();

    /**
     * @desc 获取连接管理器
     * @return Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

}
