<?php

namespace Teamone\TeamoneWpDbOrm\Concerns;

use Closure;
use Teamone\TeamoneWpDbOrm\GeneralUtil;
use PDOException;
use RuntimeException;
use Throwable;

trait ManagesTransactions
{
    /**
     * @desc 在事务中执行一个闭包
     * @param Closure $callback
     * @param int $attempts 尝试的次数
     * @return mixed|void
     * @throws Throwable
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            // 在try/catch块中执行给定的回调。捕获到任何异常，我们可以回滚此事务。
            try {
                $callbackResult = $callback($this);
            } catch (Throwable $e) {
                // 如果捕捉到异常，将尝试处理，如果仍然无法处理，则抛出异常，让开发自行处理
                $this->handleTransactionException($e, $currentAttempt, $attempts);

                continue;
            }

            try {
                if ($this->transactions == 1) {
                    $this->getPdo()->commit();
                }

                $this->transactions = max(0, $this->transactions - 1);

                if ($this->transactions == 0) {
                    $this->getTransactionsManager()->commit($this->getName());
                }
            } catch (Throwable $e) {
                $this->handleCommitTransactionException($e, $currentAttempt, $attempts);

                continue;
            }

            return $callbackResult;
        }
    }

    /**
     * @desc 处理运行事务处理语句时遇到的异常
     * @param Throwable $e
     * @param int $currentAttempt
     * @param int $maxAttempts
     * @return void
     *
     * @throws Throwable
     */
    protected function handleTransactionException(Throwable $e, $currentAttempt, $maxAttempts)
    {
        // 当发生死锁时，MySQL会回滚整个事务，这样我们就不能重试查询。
        // 我们必须抛出这个异常让开发人员以另一种方式处理它。
        if ($this->causedByConcurrencyError($e)
            && $this->transactions > 1) {
            $this->transactions--;

            $this->getTransactionsManager()->rollback(
                $this->getName(), $this->transactions
            );

            throw $e;
        }

        // 如果有一个异常，我们将回滚这个事务，然后我们可以检查是否已经超过了这个和的最大尝试计数。
        // 如果没有，则返回并在循环中再次尝试此查询。
        $this->rollBack();

        if ($this->causedByConcurrencyError($e) && $currentAttempt < $maxAttempts) {
            return;
        }

        throw $e;
    }

    /**
     * @desc 启动一个新的数据库事务
     * @return void
     *
     * @throws Throwable
     */
    public function beginTransaction()
    {
        $this->createTransaction();

        $this->transactions++;

        $this->getTransactionsManager()->begin($this->getName(), $this->transactions);
    }

    /**
     * @desc 在数据库中创建一个事务
     * @return void
     *
     * @throws Throwable
     */
    protected function createTransaction()
    {
        if ($this->transactions == 0) {
            $this->reconnectIfMissingConnection();

            try {
                $this->getPdo()->beginTransaction();
            } catch (Throwable $e) {
                $this->handleBeginTransactionException($e);
            }
        } else if ($this->transactions >= 1 && $this->queryGrammar->supportsSavepoints()) {
            $this->createSavepoint();
        }
    }

    /**
     * @desc 在数据库中创建一个保存点
     * @return void
     *
     * @throws Throwable
     */
    protected function createSavepoint()
    {
        $this->getPdo()->exec(
            $this->queryGrammar->compileSavepoint('trans' . ($this->transactions + 1))
        );
    }

    /**
     * @desc 从事务开始处理异常
     * @param Throwable $e
     * @return void
     *
     * @throws Throwable
     */
    protected function handleBeginTransactionException(Throwable $e)
    {
        if ($this->causedByLostConnection($e)) {
            $this->reconnect();

            $this->getPdo()->beginTransaction();
        } else {
            throw $e;
        }
    }

    /**
     * @desc 提交活跃的事务
     * @return void
     *
     * @throws Throwable
     */
    public function commit()
    {
        if ($this->transactions == 1) {
            $this->getPdo()->commit();
        }

        $this->transactions = max(0, $this->transactions - 1);

        if ($this->transactions == 0) {
            $this->getTransactionsManager()->commit($this->getName());
        }
    }

    /**
     * @desc 处理提交事务时遇到的异常
     * @param Throwable $e
     * @param int $currentAttempt
     * @param int $maxAttempts
     * @return void
     *
     * @throws Throwable
     */
    protected function handleCommitTransactionException(Throwable $e, $currentAttempt, $maxAttempts)
    {
        $this->transactions = max(0, $this->transactions - 1);

        if ($this->causedByConcurrencyError($e) && $currentAttempt < $maxAttempts) {
            return;
        }

        if ($this->causedByLostConnection($e)) {
            $this->transactions = 0;
        }

        throw $e;
    }

    /**
     * @desc 回滚活跃的事务
     * @param int|null $toLevel
     * @return void
     *
     * @throws Throwable
     */
    public function rollBack($toLevel = null)
    {
        //我们允许开发人员回滚到某个事务级别。我们将验证在尝试回滚到之前，该给定事务级别是有效的该级别。如果不是，我们就会返回，不做任何尝试。
        $toLevel = is_null($toLevel) ? $this->transactions - 1 : $toLevel;

        if ($toLevel < 0 || $toLevel >= $this->transactions) {
            return;
        }

        //接下来，我们将在此数据库中实际执行此回滚，并触发回滚事件。我们还将当前事务级别设置为给定级别,level被传递到这个方法，所以它将从这里出来。
        try {
            $this->performRollBack($toLevel);
        } catch (Throwable $e) {
            $this->handleRollBackException($e);
        }

        $this->transactions = $toLevel;

        $this->getTransactionsManager()->rollback($this->getName(), $this->transactions);
    }

    /**
     * @desc 在数据库中执行回滚
     * @param int $toLevel
     * @return void
     *
     * @throws Throwable
     */
    protected function performRollBack($toLevel)
    {
        if ($toLevel == 0) {
            $this->getPdo()->rollBack();
        } elseif ($this->queryGrammar->supportsSavepoints()) {
            $this->getPdo()->exec(
                $this->queryGrammar->compileSavepointRollBack('trans' . ($toLevel + 1))
            );
        }
    }

    /**
     * @desc 处理回滚中的异常
     * @param Throwable $e
     * @return void
     *
     * @throws Throwable
     */
    protected function handleRollBackException(Throwable $e)
    {
        if ($this->causedByLostConnection($e)) {
            $this->transactions = 0;

            $this->getTransactionsManager()->rollback($this->getName(), $this->transactions);
        }

        throw $e;
    }

    /**
     * @return int
     */
    public function transactionLevel()
    {
        return $this->transactions;
    }

    /**
     * @desc 在事务提交后执行回调
     * @param callable $callback
     * @return void
     *
     * @throws RuntimeException
     */
    public function afterCommit($callback)
    {
        if ($this->getTransactionsManager()) {
            $this->getTransactionsManager()->addCallback($callback);
        }

        throw new RuntimeException('Transactions Manager has not been set.');
    }

    /**
     * @param Throwable $e
     * @return bool
     */
    protected function causedByConcurrencyError(Throwable $e)
    {
        if ($e instanceof PDOException && ($e->getCode() === 40001 || $e->getCode() === '40001')) {
            return true;
        }

        $message = $e->getMessage();

        return GeneralUtil::contains($message, [
            'Deadlock found when trying to get lock',
            'deadlock detected',
            'The database file is locked',
            'database is locked',
            'database table is locked',
            'A table in the database is locked',
            'has been chosen as the deadlock victim',
            'Lock wait timeout exceeded; try restarting transaction',
            'WSREP detected deadlock/conflict and aborted the transaction. Try restarting the transaction',
        ]);
    }

    /**
     * @param Throwable $e
     * @return bool
     */
    protected function causedByLostConnection(Throwable $e)
    {
        $message = $e->getMessage();

        return GeneralUtil::contains($message, [
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
        ]);
    }
}
