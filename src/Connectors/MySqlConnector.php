<?php

namespace Teamone\TeamoneWpDbOrm\Connectors;

use Exception;
use Teamone\TeamoneWpDbOrm\GeneralUtil;
use PDO;
use Throwable;

class MySqlConnector implements ConnectorInterface
{

    /**
     * @desc 默认 PDO 链接选项
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];

    /**
     * @desc 建立数据库连接
     * @param array $config
     * @return PDO
     */
    public function connect(array $config)
    {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

        // 连接选项
        $options = $config['options'] ?? [];
        $options = array_diff_key($this->options, $options) + $options;

        // 创建连接
        $connection = $this->createConnection($dsn, $config['username'], $config['password'], $options);

        // 选项数据库
        if (isset($config['database']) && !empty($config['database'])) {
            $connection->exec("use `{$config['database']}`;");
        }

        // 配置字符集
        if (isset($config['charset']) && !empty($config['charset'])) {
            // 字符集排序
            $collation = isset($config['collation']) ? " collate '{$config['collation']}'" : '';
            $sql       = "set names '{$config['charset']}'" . $collation;
            $connection->prepare($sql)->execute();
        }

        // 在连接上设置时区
        if (isset($config['timezone']) && !empty($config['timezone'])) {
            $sql = 'set time_zone="' . $config['timezone'] . '"';
            $connection->prepare($sql)->execute();
        }

        return $connection;
    }

    /**
     * @desc 创建一个新的PDO连接
     * @param $dsn
     * @param $username
     * @param $password
     * @param array $options
     * @return PDO
     * @throws Exception
     */
    public function createConnection($dsn, $username, $password, array $options)
    {
        try {
            return $this->createPdoConnection($dsn, $username, $password, $options);
        } catch (Exception $e) {
            return $this->tryAgainIfCausedByLostConnection($e, $dsn, $username, $password, $options);
        }
    }

    /**
     * @desc 创建一个新的PDO连接实例
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     * @return PDO
     */
    protected function createPdoConnection($dsn, $username, $password, $options)
    {
        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * @desc 处理连接执行期间发生的异常
     * @param Throwable $e
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     * @return PDO
     *
     * @throws Exception
     */
    protected function tryAgainIfCausedByLostConnection(Throwable $e, $dsn, $username, $password, $options)
    {
        if ($this->causedByLostConnection($e)) {
            return $this->createPdoConnection($dsn, $username, $password, $options);
        }

        throw $e;
    }

    /**
     * @desc 确定给定的异常是否由丢失的连接引起
     * @param Throwable $e
     * @return bool
     */
    protected function causedByLostConnection(Throwable $e)
    {
        $haystack = $e->getMessage();

        $needles = [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
            'query_wait_timeout',
            'reset by peer',
            'Physical connection is not usable',
            'TCP Provider: Error code 0x68',
            'ORA-03114',
            'Packets out of order. Expected',
            'Adaptive Server connection failed',
            'Communication link failure',
            'connection is no longer usable',
            'Login timeout expired',
            'SQLSTATE[HY000] [2002] Connection refused',
            'running with the --read-only option so it cannot execute this statement',
            'The connection is broken and recovery is not possible. The connection is marked by the client driver as unrecoverable. No attempt was made to restore the connection.',
            'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Try again',
            'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Name or service not known',
            'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: EOF detected',
            'SQLSTATE[HY000] [2002] Connection timed out',
            'SSL: Connection timed out',
            'SQLSTATE[HY000]: General error: 1105 The last transaction was aborted due to Seamless Scaling. Please retry.',
            'Temporary failure in name resolution',
            'SSL: Broken pipe',
            'SQLSTATE[08S01]: Communication link failure',
            'SQLSTATE[08006] [7] could not connect to server: Connection refused Is the server running on host',
            'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: No route to host',
            'The client was disconnected by the server because of inactivity. See wait_timeout and interactive_timeout for configuring this behavior.',
            'SQLSTATE[08006] [7] could not translate host name',
            'TCP Provider: Error code 0x274C',
        ];

        return GeneralUtil::contains($haystack, $needles);
    }
}
