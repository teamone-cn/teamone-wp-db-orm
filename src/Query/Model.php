<?php

namespace Teamone\TeamoneWpDbOrm\Query;

use Teamone\TeamoneWpDbOrm\Capsule\Manager as DB;

class Model
{
    /**
     * @var string 表前缀
     */
    protected $tablePrefix;

    /**
     * @var string 表名
     */
    protected $table;

    /**
     * @var string 连接
     */
    protected $connection;

    /**
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * @param string $tablePrefix
     */
    public function setTablePrefix($tablePrefix)
    {
        $this->tablePrefix = (string) $tablePrefix;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param string $table
     * @return Model
     */
    public function setTable($table)
    {
        $this->table = (string) $table;

        return $this;
    }

    /**
     * @return string
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param string $connection
     * @return Model
     */
    public function setConnection($connection)
    {
        $this->connection = (string) $connection;

        return $this;
    }

    /**
     * @desc 新建查询
     * @return Builder
     */
    public function newQuery()
    {
        $name = $this->getConnection();

        $connect = DB::connection($name ?? null);

        $tablePrefix = $this->getTablePrefix();

        if ( !empty($tablePrefix)) {
            $connect->setTablePrefix($tablePrefix);
        }

        $table = $this->getTable();

        return $connect->table($table);
    }

}
