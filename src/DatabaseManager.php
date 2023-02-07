<?php

namespace Teamone\TeamoneWpDbOrm;

use Closure;
use InvalidArgumentException;
use Teamone\TeamoneWpDbOrm\Capsule\DatabaseConfigContract;
use Teamone\TeamoneWpDbOrm\Connectors\ConnectionFactory;

class DatabaseManager implements ConnectionResolverInterface
{
    /**
     * @var ConnectionFactory 数据库链接工厂实例
     */
    protected $factory;

    /**
     * @var array 活跃的链接实例
     */
    protected $connections = [];

    /**
     * @var Closure 为重新连接数据库而执行的回调。
     */
    protected $reconnector;

    /**
     * @var DatabaseConfigContract
     */
    protected $databaseConfigContract;

    public function __construct(DatabaseConfigContract $databaseConfigContract, ConnectionFactory $factory)
    {
        $this->databaseConfigContract = $databaseConfigContract;
        $this->factory                = $factory;

        $this->reconnector = function ($connection){
            $this->reconnect($connection->getNameWithReadWriteType());
        };
    }

    /**
     * @desc 获取一个数据库连接实例
     * @param string|null $name
     * @return Connection
     */
    public function connection($name = null)
    {
        [$database, $type] = $this->parseConnectionName($name);

        $name = $name ? : $database;

        if ( !isset($this->connections[$name])) {
            $this->connections[$name] = $this->configure($this->makeConnection($database), $type);
        }

        return $this->connections[$name];
    }

    /**
     * @desc 解析链接名称
     * @param string $name
     * @return array
     */
    protected function parseConnectionName($name)
    {
        $name = $name ? : $this->getDefaultConnection();

        if (substr($name, -strlen('::read')) === '::read' || substr($name, -strlen('::write')) === '::write') {
            $result = explode('::', $name, 2);
        } else {
            $result = [$name, null];
        }

        return $result;
    }

    /**
     * @desc 创建数据库链接实例
     * @param string $name
     * @return Connection
     */
    protected function makeConnection($name)
    {
        $config = $this->configuration($name);

        return $this->factory->make($config, $name);
    }

    /**
     * @desc 获取连接的配置。
     * @param string $name
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function configuration($name)
    {
        $name = $name ? : $this->getDefaultConnection();

        $connectionsConfig = $this->databaseConfigContract->getConnectionConfig();

        $config = $connectionsConfig['connections'][$name] ?? [];

        if (empty($config)) {
            throw new InvalidArgumentException("Database connection [{$name}] not configured.");
        }

        return $config;
    }

    /**
     * @desc 准备数据库连接实例
     * @param Connection $connection
     * @param string $type
     * @return Connection
     */
    protected function configure(Connection $connection, $type)
    {
        $connection = $this->setPdoForType($connection, $type)->setReadWriteType($type);

        $connection->setReconnector($this->reconnector);

        return $connection;
    }

    /**
     * @desc 准备数据库连接实例的读/写模式。
     * @param Connection $connection
     * @param string|null $type
     * @return Connection
     */
    protected function setPdoForType(Connection $connection, $type = null)
    {
        if ($type === 'read') {
            $connection->setPdo($connection->getReadPdo());
        } elseif ($type === 'write') {
            $connection->setReadPdo($connection->getPdo());
        }

        return $connection;
    }

    /**
     * @desc 断开与给定数据库的连接，并从本地缓存中删除
     * @param string|null $name
     * @return void
     */
    public function purge($name = null)
    {
        $name = $name ? : $this->getDefaultConnection();

        $this->disconnect($name);

        unset($this->connections[$name]);
    }

    /**
     * @desc 断开与给定数据库的连接
     * @param string|null $name
     * @return void
     */
    public function disconnect($name = null)
    {
        if (isset($this->connections[$name = $name ? : $this->getDefaultConnection()])) {
            $this->connections[$name]->disconnect();
        }
    }

    /**
     * @desc 重新连接到给定的数据库
     * @param string|null $name
     * @return Connection
     */
    public function reconnect($name = null)
    {
        $this->disconnect($name = $name ? : $this->getDefaultConnection());

        if ( !isset($this->connections[$name])) {
            return $this->connection($name);
        }

        return $this->refreshPdoConnections($name);
    }

    /**
     * @desc 刷新给定连接上的PDO连接
     * @param string $name
     * @return Connection
     */
    protected function refreshPdoConnections($name)
    {
        [$database, $type] = $this->parseConnectionName($name);

        $fresh = $this->configure($this->makeConnection($database), $type);

        $connection = null;

        if (isset($this->connections[$name]) && $this->connections[$name] instanceof Connection) {
            $connection = $this->connections[$name];
        }

        if (is_null($connection)) {
            throw new InvalidArgumentException();
        }

        return $connection->setPdo($fresh->getRawPdo())->setReadPdo($fresh->getRawReadPdo());
    }

    /**
     * @desc 获取默认链接名称
     * @return string
     */
    public function getDefaultConnection()
    {
        $config = $this->databaseConfigContract->getConnectionConfig();

        return $config['default'] ?? 'default';
    }

    /**
     * @desc 返回所有已经创建的链接
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * @desc 动态地将方法传递给默认连接
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}
