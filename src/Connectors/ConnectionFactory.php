<?php

namespace Teamone\TeamoneWpDbOrm\Connectors;

use Closure;
use InvalidArgumentException;
use Teamone\TeamoneWpDbOrm\Connection;
use Teamone\TeamoneWpDbOrm\MySqlConnection;
use PDO;
use PDOException;

class ConnectionFactory
{
    /**
     * @desc 根据配置建立PDO连接
     * @param array $config
     * @param string|null $name
     * @return Connection
     */
    public function make(array $config, $name = null)
    {
        if (isset($config['read'])) {
            return $this->createReadWriteConnection($config);
        }

        return $this->createSingleConnection($config);
    }

    /**
     * @desc 创建一个数据库连接实例
     * @param array $config
     * @return Connection
     */
    protected function createSingleConnection(array $config)
    {
        $pdo = $this->createPdoResolver($config);

        return $this->createConnection($config['driver'], $pdo, $config['database'], $config['prefix'], $config);
    }

    /**
     * @desc 创建一个读/写数据库连接实例
     * @param array $config
     * @return Connection
     */
    protected function createReadWriteConnection(array $config)
    {
        $connection = $this->createSingleConnection($this->getWriteConfig($config));

        $readPdo = $this->createReadPdo($config);

        return $connection->setReadPdo($readPdo);
    }

    /**
     * @desc 创建一个用于读取的新PDO实例
     * @param array $config
     * @return Closure
     */
    protected function createReadPdo(array $config)
    {
        return $this->createPdoResolver($this->getReadConfig($config));
    }

    /**
     * @desc 获取读/写连接的读配置
     * @param array $config
     * @return array
     */
    protected function getReadConfig(array $config)
    {
        $readConfig = $this->getReadWriteConfig($config, 'read');

        $mergeConfig = $this->mergeReadWriteConfig($config, $readConfig);

        return $mergeConfig;
    }

    /**
     * @desc 获取读/写连接的写配置
     * @param array $config
     * @return array
     */
    protected function getWriteConfig(array $config)
    {
        $writeConfig = $this->getReadWriteConfig($config, 'write');

        $mergeConfig = $this->mergeReadWriteConfig($config, $writeConfig);

        return $mergeConfig;
    }

    /**
     * @desc 获得读/写级别的配置
     * @param array $config
     * @param string $type
     * @return array
     */
    protected function getReadWriteConfig(array $config, string $type)
    {
        $rwConfig = $config[$type] ?? null;

        if (is_null($rwConfig)) {
            throw new InvalidArgumentException("Invalid Argument config[{$type}]");
        }

        if (isset($rwConfig[0])) {
            $conf = array_rand($rwConfig);
        } else {
            $conf = $rwConfig;
        }

        return $conf;
    }

    /**
     * @desc 合并读/写连接的配置
     * @param array $config
     * @param array $merge
     * @return array
     */
    protected function mergeReadWriteConfig(array $config, array $merge)
    {
        $conf = array_merge($config, $merge);

        unset($conf['read']);
        unset($conf['write']);

        return $conf;
    }

    /**
     * @desc 创建一个解析为PDO实例的新闭包
     * @param array $config
     * @return Closure
     */
    protected function createPdoResolver(array $config)
    {
        return array_key_exists('host', $config) ? $this->createPdoResolverWithHosts($config) : $this->createPdoResolverWithoutHosts($config);
    }

    /**
     * @desc 创建一个解析为具有特定主机或主机数组的 PDO 实例的新闭包
     * @param array $config
     * @return Closure
     *
     * @throws PDOException
     */
    protected function createPdoResolverWithHosts(array $config)
    {
        return function () use ($config){
            $hosts = is_array($config['host']) ? $config['host'] : [$config['host']];

            if (empty($hosts)) {
                throw new InvalidArgumentException('Database hosts array is empty.');
            }

            shuffle($hosts);

            foreach ($hosts as $host) {
                $config['host'] = $host;

                try {
                    return $this->createConnector($config)->connect($config);
                } catch (PDOException $e) {
                    continue;
                }
            }

            if (isset($e) && $e instanceof PDOException) {
                throw $e;
            }

            return null;
        };
    }

    /**
     * @desc 创建一个解析为没有配置主机的PDO实例的新闭包
     * @param array $config
     * @return Closure
     */
    protected function createPdoResolverWithoutHosts(array $config)
    {
        return function () use ($config){
            return $this->createConnector($config)->connect($config);
        };
    }

    /**
     * @desc 根据配置创建连接器实例
     * @param array $config
     * @return ConnectorInterface
     * @throws InvalidArgumentException
     */
    public function createConnector(array $config)
    {
        switch ($config['driver']) {
            case 'mysql':
                return new MySqlConnector();
        }

        throw new InvalidArgumentException("Unsupported driver [{$config['driver']}].");
    }

    /**
     * @desc 创建一个新的链接实例
     * @param string $driver
     * @param PDO|Closure $connection
     * @param string $database
     * @param string $prefix
     * @param array $config
     * @return Connection
     *
     * @throws InvalidArgumentException
     */
    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        switch ($driver) {
            case 'mysql':
                return new MySqlConnection($connection, $database, $prefix, $config);
        }

        throw new InvalidArgumentException("Unsupported driver [{$driver}].");
    }
}
