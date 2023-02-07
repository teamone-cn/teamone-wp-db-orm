<?php

namespace Teamone\TeamoneWpDbOrm;

use Closure;
use DateTimeInterface;
use Exception;
use LogicException;
use Teamone\TeamoneWpDbOrm\Query\Builder;
use Teamone\TeamoneWpDbOrm\Query\Expression;
use Teamone\TeamoneWpDbOrm\Query\Grammars\MySqlGrammar;
use Teamone\TeamoneWpDbOrm\Query\Processors\MySqlProcessor;
use PDO;
use PDOException;
use PDOStatement;

abstract class Connection implements ConnectionInterface
{
    use Concerns\ManagesTransactions;

    /**
     * @var Closure|PDO 活跃的 PDO 实例
     */
    protected $pdo;

    /**
     * @var PDO|Closure 活跃的 PDO 只读实例
     */
    protected $readPdo;

    /**
     * @var string 连接的数据库的名称
     */
    protected $database;

    /**
     * @var string|null 连接的类型
     */
    protected $readWriteType;

    /**
     * @var string 连接的表前缀
     */
    protected $tablePrefix = '';

    /**
     * @var array 数据库连接配置选项
     */
    protected $config = [];

    /**
     * @var callable 连接的重连接器实例
     */
    protected $reconnector;

    /**
     * @var MySqlGrammar MySQL 语法分析器
     */
    protected $queryGrammar;

    /**
     * @var MySqlProcessor
     */
    protected $postProcessor;

    /**
     * @var int 连接的默认获取模式
     */
    protected $fetchMode = PDO::FETCH_OBJ;

    /**
     * @var int 活动事务的数量
     */
    protected $transactions = 0;

    /**
     * @var DatabaseTransactionsManager 事务管理实例
     */
    protected $transactionsManager;

    /**
     * @var bool 是否对数据库进行了更改
     */
    protected $recordsModified = false;

    /**
     * @var bool 连接是否应使用“写”PDO连接
     */
    protected $readOnWriteConnection = false;

    /**
     * @var array 所有查询都针对连接运行
     */
    protected $queryLog = [];

    /**
     * @var bool 指示是否记录查询
     */
    protected $loggingQueries = false;

    /**
     * @var bool 指示连接是否处于“试运行”
     */
    protected $pretending = false;

    /**
     * @var array 在执行查询之前应该调用的所有回调
     */
    protected $beforeExecutingCallbacks = [];

    /**
     * @desc 创建新的数据库连接实例
     * @param PDO|Closure $pdo
     * @param string $database
     * @param string $tablePrefix
     * @param array $config
     * @return void
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', $config = [])
    {
        // CallBack
        $this->pdo = $pdo;

        // DB Name
        $this->database = (string) $database;

        $this->tablePrefix = (string) $tablePrefix;

        $this->config = (array) $config;

        // 选择默认查询语法
        $this->useDefaultQueryGrammar();
        // 选择默认处理器
        $this->useDefaultPostProcessor();
        // 选择默认事务处理器
        $this->useDefaultTransactionsManager();
    }

    /**
     * @desc 选择默认事务处理器
     */
    abstract public function useDefaultTransactionsManager();

    /**
     * @desc 获取默认事务处理器
     * @return DatabaseTransactionsManager
     */
    public function getTransactionsManager()
    {
        return $this->transactionsManager;
    }

    /**
     * @desc 将查询语法设置为默认实现
     * @return void
     */
    abstract public function useDefaultQueryGrammar();

    /**
     * @desc 获取默认查询语法实例
     * @return MySqlGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->queryGrammar;
    }

    /**
     * @desc 将查询后处理器设置为默认实现
     * @return void
     */
    abstract public function useDefaultPostProcessor();

    /**
     * @desc 获取默认的后处理器实例
     * @return MySqlProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return $this->postProcessor;
    }

    /**
     * @desc 开始对数据库表进行流畅查询
     * @param Closure|Builder|string $table
     * @param string|null $as
     * @return Builder
     */
    public function table($table, $as = null)
    {
        return $this->query()->from($table, $as);
    }

    /**
     * @desc 获取一个新的查询构建器实例
     * @return Builder
     */
    public function query()
    {
        return new Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }

    /**
     * @desc 运行一个select语句并返回一个结果
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return mixed
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
        $records = $this->select($query, $bindings, $useReadPdo);

        return array_shift($records);
    }

    /**
     * @desc 对数据库运行一个选择语句
     * @param string $query
     * @param array $bindings
     * @return array
     */
    public function selectFromWriteConnection($query, $bindings = [])
    {
        return $this->select($query, $bindings, false);
    }

    /**
     * @desc 对数据库运行一个选择语句
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo){
            if ($this->pretending()) {
                return [];
            }

            $stat = $this->getPdoForSelect($useReadPdo)->prepare($query);

            $statement = $this->prepared($stat);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement->fetchAll();
        });
    }

    /**
     * @desc 对数据库运行select语句并返回一个生成器
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        $statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo){
            if ($this->pretending()) {
                return [];
            }

            //首先，我们为查询创建一条语句。然后，我们将设置取回模式，并为查询准备绑定。
            //一旦完成，我们就会准备好对数据库执行查询并返回游标。
            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)->prepare($query));

            $this->bindValues($statement, $this->prepareBindings($bindings));

            //接下来，我们将对数据库执行查询并返回语句这样我们就可以返回光标。
            //游标将使用PHP生成器给予每次返回一行，而不需要使用大量内存来渲染它们。
            $statement->execute();

            return $statement;
        });

        while ($record = $statement->fetch()) {
            yield $record;
        }
    }

    /**
     * @desc 配置PDO预处理语句
     *
     * @param PDOStatement $statement
     * @return PDOStatement
     */
    protected function prepared(PDOStatement $statement)
    {
        $statement->setFetchMode($this->fetchMode);

        return $statement;
    }

    /**
     * @desc 获取用于选择查询的PDO连接
     * @param bool $useReadPdo
     * @return PDO
     */
    protected function getPdoForSelect($useReadPdo = true)
    {
        return $useReadPdo ? $this->getReadPdo() : $this->getPdo();
    }

    /**
     * @desc 对数据库运行添加语句
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }

    /**
     * @desc 对数据库运行更新语句
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * @desc 对数据库运行删除语句
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * @desc 执行SQL语句并返回布尔结果
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings){
            if ($this->pretending()) {
                return true;
            }

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $this->recordsHaveBeenModified();

            return $statement->execute();
        });
    }

    /**
     * @desc 运行SQL语句并获得受影响的行数
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings){
            if ($this->pretending()) {
                return 0;
            }

            //对于update或delete语句，我们希望获得受影响的行数，然后返回给开发人员。
            //我们首先需要执行该语句，然后使用PDO获取受影响的语句。
            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            $this->recordsHaveBeenModified(($count = $statement->rowCount()) > 0);

            return $count;
        });
    }

    /**
     * @desc 对PDO连接运行一个原始的、未准备好的查询
     * @param string $query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, [], function ($query){
            if ($this->pretending()) {
                return true;
            }

            $this->recordsHaveBeenModified(
                $change = $this->getPdo()->exec($query) !== false
            );

            return $change;
        });
    }

    /**
     * @desc 以“演练”模式执行给定的回调
     * @param Closure $callback
     * @return array
     */
    public function pretend(Closure $callback)
    {
        return $this->withFreshQueryLog(function () use ($callback){
            $this->pretending = true;

            //基本上是为了让数据库连接“假装”，我们将返回
            //所有查询方法的默认值，然后返回一个
            //在Closure回调函数中“执行”的查询数组。
            $callback($this);

            $this->pretending = false;

            return $this->queryLog;
        });
    }

    /**
     * @desc 在“演练”模式下执行给定的回调
     * @param Closure $callback
     * @return array
     */
    protected function withFreshQueryLog($callback)
    {
        $loggingQueries = $this->loggingQueries;

        //首先我们将备份logging queries属性的值，然后我们将准备好运行回调。
        //该查询日志也将被清除这样我们就会有一个新的日志，记录现在执行的所有查询。
        $this->enableQueryLog();

        $this->queryLog = [];

        //现在我们将执行这个回调函数并捕获结果。一旦发生了
        //一旦发生了执行，我们将恢复查询日志的值，并返回回调函数的值，这样原始的调用者就可以得到结果。
        $result = $callback();

        $this->loggingQueries = $loggingQueries;

        return $result;
    }

    /**
     * @desc 将值绑定到给定语句中的参数
     * @param PDOStatement $statement
     * @param array $bindings
     * @return void
     */
    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }

    /**
     * @desc 为执行准备查询绑定
     * @param array $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }

    /**
     * @desc 运行SQL语句并记录其执行上下文
     * @param string $query
     * @param array $bindings
     * @param Closure $callback
     * @return mixed
     *
     * @throws Exception
     */
    protected function run($query, $bindings, Closure $callback)
    {
        foreach ($this->beforeExecutingCallbacks as $beforeExecutingCallback) {
            $beforeExecutingCallback($query, $bindings, $this);
        }

        $this->reconnectIfMissingConnection();

        $start = microtime(true);

        //在这里我们将运行这个查询。如果发生异常，我们将确定是否发生了异常
        //由于连接丢失而导致。如果是这个原因，我们试试重新建立连接并使用新的连接重新运行查询。
        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (Exception $e) {
            $result = $this->handleQueryException(
                $e, $query, $bindings, $callback
            );
        }

        //一旦我们运行了查询，我们将计算它运行所需的时间和，然后记录查询、绑定和执行时间
        $this->logQuery($query, $bindings, $this->getElapsedTime($start));

        return $result;
    }

    /**
     * @desc 运行SQL语句
     * @param string $query
     * @param array $bindings
     * @param Closure $callback
     * @return mixed
     *
     * @throws Exception
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            return $callback($query, $bindings);
        } catch (PDOException $e) {
            $message = $e->getMessage() . ' (SQL: ' . $this->replaceArray('?', $bindings, $query) . ')';

            throw new Exception($message);
        }
    }

    /**
     * @desc 替换数组
     * @param string $search
     * @param array $replace
     * @param string $subject
     * @return string
     */
    protected function replaceArray($search, $replace, $subject)
    {
        $search  = (string) $search;
        $replace = (array) $replace;
        $subject = (string) $subject;

        // 以 ? 切割成数组
        $segments = explode($search, $subject);
        // 弹出第一个
        $result = array_shift($segments);

        foreach ($segments as $segment) {
            $shift  = (array_shift($replace) ?? $search);
            $result .= $shift . $segment;
        }

        return $result;
    }

    /**
     * @desc 在连接的查询日志中记录查询
     * @param string $query
     * @param array $bindings
     * @param float|null $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null)
    {
        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings');
        }
    }

    /**
     * @desc 获取从给定起始点开始经过的时间
     * @param int $start
     * @return float
     */
    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * @desc 处理查询异常
     * @param Exception $e
     * @param string $query
     * @param array $bindings
     * @param Closure $callback
     * @return mixed
     *
     * @throws Exception
     */
    protected function handleQueryException(Exception $e, $query, $bindings, Closure $callback)
    {
        if ($this->transactions >= 1) {
            throw $e;
        }

        return $this->tryAgainIfCausedByLostConnection(
            $e, $query, $bindings, $callback
        );
    }

    /**
     * @desc 处理查询执行期间发生的查询异常
     * @param Exception $e
     * @param string $query
     * @param array $bindings
     * @param Closure $callback
     * @return mixed
     *
     * @throws Exception
     */
    protected function tryAgainIfCausedByLostConnection(Exception $e, $query, $bindings, Closure $callback)
    {
        $e = $e->getPrevious() ? : $e;

        if ($this->causedByLostConnection($e)) {
            $this->reconnect();

            return $this->runQueryCallback($query, $bindings, $callback);
        }

        throw $e;
    }

    /**
     * @desc 重新连接数据库
     * @return mixed
     * @throws LogicException
     */
    public function reconnect()
    {
        if (is_callable($this->reconnector)) {
            return call_user_func($this->reconnector, $this);
        }

        throw new LogicException('Lost connection and no reconnector available.');
    }

    /**
     * @desc 如果缺少PDO连接，则重新连接到数据库
     * @return void
     */
    protected function reconnectIfMissingConnection()
    {
        if (is_null($this->pdo)) {
            $this->reconnect();
        }
    }

    /**
     * @desc 从底层PDO连接断开连接
     * @return void
     */
    public function disconnect()
    {
        $this->setPdo(null)->setReadPdo(null);
    }

    /**
     * @desc 在执行数据库查询之前注册一个要运行的钩子
     * @param Closure $callback
     * @return $this
     */
    public function beforeExecuting(Closure $callback)
    {
        $this->beforeExecutingCallbacks[] = $callback;

        return $this;
    }

    /**
     * @desc 获取一个新的原始查询表达式
     * @param mixed $value
     * @return Expression
     */
    public function raw($value)
    {
        return new Expression($value);
    }

    /**
     * @desc 确定数据库连接是否修改了任何数据库记录
     * @return bool
     */
    public function hasModifiedRecords()
    {
        return $this->recordsModified;
    }

    /**
     * @desc 说明是否修改了任何记录
     * @param bool $value
     * @return void
     */
    public function recordsHaveBeenModified($value = true)
    {
        if ( !$this->recordsModified) {
            $this->recordsModified = $value;
        }
    }

    /**
     * @desc 设置记录修改状态
     * @param bool $value
     * @return $this
     */
    public function setRecordModificationState(bool $value)
    {
        $this->recordsModified = $value;

        return $this;
    }

    /**
     * @desc 重置记录修改状态
     * @return void
     */
    public function forgetRecordModificationState()
    {
        $this->recordsModified = false;
    }

    /**
     * @desc 指示该连接应使用写PDO连接进行读操作
     * @param bool $value
     * @return $this
     */
    public function useWriteConnectionWhenReading($value = true)
    {
        $this->readOnWriteConnection = $value;

        return $this;
    }

    /**
     * @desc 获取当前PDO连接
     * @return PDO
     */
    public function getPdo()
    {
        if ($this->pdo instanceof Closure) {
            return $this->pdo = call_user_func($this->pdo);
        }

        return $this->pdo;
    }

    /**
     * @desc 在不执行任何重新连接逻辑的情况下获取当前PDO连接参数
     * @return PDO|Closure|null
     */
    public function getRawPdo()
    {
        return $this->pdo;
    }

    /**
     * @desc 获取用于读取的当前PDO连接
     * @return PDO
     */
    public function getReadPdo()
    {
        if ($this->transactions > 0) {
            return $this->getPdo();
        }

        $sticky = $this->config['sticky'] ?? "";

        if ($this->readOnWriteConnection || ($this->recordsModified && $sticky)) {
            return $this->getPdo();
        }

        if ($this->readPdo instanceof Closure) {
            return $this->readPdo = call_user_func($this->readPdo);
        }

        return $this->readPdo ? : $this->getPdo();
    }

    /**
     * @desc 在不执行任何重新连接逻辑的情况下获取当前读PDO连接参数
     * @return PDO|Closure|null
     */
    public function getRawReadPdo()
    {
        return $this->readPdo;
    }

    /**
     * @desc 设置当前PDO连接
     * @param PDO|Closure|null $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        $this->transactions = 0;

        $this->pdo = $pdo;

        return $this;
    }

    /**
     * @desc 设置用于读取的PDO连接
     * @param PDO|Closure|null $pdo
     * @return $this
     */
    public function setReadPdo($pdo)
    {
        $this->readPdo = $pdo;

        return $this;
    }

    /**
     * @desc 在连接上设置重新连接实例
     * @param callable $reconnector
     * @return $this
     */
    public function setReconnector(callable $reconnector)
    {
        $this->reconnector = $reconnector;

        return $this;
    }

    /**
     * @desc 获取数据库连接名称
     * @return string|null
     */
    public function getName()
    {
        return $this->config['name'] ?? "";
    }

    /**
     * @desc 获取数据库连接的全名
     * @return string|null
     */
    public function getNameWithReadWriteType()
    {
        return $this->getName() . ($this->readWriteType ? '::' . $this->readWriteType : '');
    }

    /**
     * @desc 从配置选项中获取一个选项
     * @param string|null $option
     * @return mixed
     */
    public function getConfig($option = null)
    {
        return $option ? ($this->config[$option] ?? null) : $this->config;
    }

    /**
     * @desc 获取PDO驱动程序名称
     * @return string
     */
    public function getDriverName()
    {
        return $this->config['driver'] ?? "";
    }

    /**
     * @desc 获取连接使用的查询语法
     * @return MySqlGrammar
     */
    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    /**
     * @desc 获取连接使用的查询后处理器
     * @return MysqlProcessor
     */
    public function getPostProcessor()
    {
        return $this->postProcessor;
    }

    /**
     * desc 在连接上设置事务管理器实例
     * @param DatabaseTransactionsManager $manager
     * @return $this
     */
    public function setTransactionManager($manager)
    {
        $this->transactionsManager = $manager;

        return $this;
    }

    /**
     * @desc 为此连接取消设置事务管理器
     */
    public function unsetTransactionManager()
    {
        $this->transactionsManager = null;
    }

    /**
     * @desc 确定连接是否处于“演练”状态
     * @return bool
     */
    public function pretending()
    {
        return $this->pretending === true;
    }

    /**
     * @desc 获取连接查询日志
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * @desc 清除查询日志
     * @return void
     */
    public function flushQueryLog()
    {
        $this->queryLog = [];
    }

    /**
     * @desc 在连接上启用查询日志
     * @return void
     */
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }

    /**
     * @desc 禁用连接上的查询日志
     * @return void
     */
    public function disableQueryLog()
    {
        $this->loggingQueries = false;
    }

    /**
     * @desc 确定是否记录查询
     * @return bool
     */
    public function logging()
    {
        return $this->loggingQueries;
    }

    /**
     * @desc 获取连接数据库的名称
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->database;
    }

    /**
     * @desc 设置连接数据库的名称
     * @param string $database
     * @return $this
     */
    public function setDatabaseName($database)
    {
        $this->database = $database;

        return $this;
    }

    /**
     * @desc 设置连接的读写类型
     * @param string|null $readWriteType
     * @return $this
     */
    public function setReadWriteType($readWriteType)
    {
        $this->readWriteType = $readWriteType;

        return $this;
    }

    /**
     * @desc 获取表前缀
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * @desc 设置连接使用的表前缀
     * @param string $prefix
     * @return $this
     */
    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = (string) $prefix;

        $this->getQueryGrammar()->setTablePrefix($prefix);

        return $this;
    }

}
