<?php

declare(strict_types=1);

namespace Goat\Bundle\DataCollector;

use Goat\Core\Client\AbstractConnectionProxy;
use Goat\Core\Client\ConnectionInterface;
use Goat\Core\Client\EmptyResultIterator;
use Goat\Core\Client\ResultIteratorInterface;
use Goat\Core\EventDispatcher\Timer;
use Goat\Core\Query\DeleteQuery;
use Goat\Core\Query\InsertQueryQuery;
use Goat\Core\Query\InsertValuesQuery;
use Goat\Core\Query\SelectQuery;
use Goat\Core\Query\UpdateQuery;
use Goat\Core\Transaction\Transaction;

/**
 * Connection proxy that emits events via Symfony's EventDispatcher
 */
class TimeConnectionProxy extends AbstractConnectionProxy
{
    private $connection;
    private $data = [];

    /**
     * Default constructor
     *
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->data = [
            'execute_count' => 0,
            'execute_time' => 0,
            'perform_count' => 0,
            'perform_time' => 0,
            'prepare_count' => 0,
            'query_count' => 0,
            'query_time' => 0,
            'total_count' => 0,
            'total_time' => 0,
            'transaction_count' => 0,
        ];
    }

    /**
     * Get collected data
     *
     * @return array
     */
    public function getCollectedData() : array
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInnerConnection() : ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function query($query, array $parameters = null, $options = null) : ResultIteratorInterface
    {
        $timer = new Timer();
        $this->data['query_count']++;
        $this->data['total_count']++;

        $ret = $this->getInnerConnection()->query($query, $parameters ?? [], $options);

        $duration = $timer->stop();
        // Ignore empty result iterator, this means it fallbacked on perform()
        if ($ret instanceof EmptyResultIterator) {
            $this->data['query_count']--;
            $this->data['total_count']--;
        } else {
            $this->data['query_time'] += $duration;
            $this->data['total_time'] += $duration;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function perform($query, array $parameters = null, $options = null) : int
    {
        $timer = new Timer();
        $this->data['perform_count']++;
        $this->data['total_count']++;

        $ret = $this->getInnerConnection()->perform($query, $parameters ?? [], $options);

        $duration = $timer->stop();
        $this->data['perform_time'] += $duration;
        $this->data['total_time'] += $duration;

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareQuery($query, string $identifier = null) : string
    {
        $this->data['prepare_count']++;

        $ret = $this->getInnerConnection()->prepareQuery($query, $identifier);

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function executePreparedQuery(string $identifier, array $parameters = null, $options = null) : ResultIteratorInterface
    {
        $timer = new Timer();
        $this->data['execute_count']++;
        $this->data['total_count']++;

        $ret = $this->getInnerConnection()->executePreparedQuery($identifier, $parameters ?? [], $options);

        $duration = $timer->stop();
        $this->data['execute_time'] += $duration;
        $this->data['total_time'] += $duration;

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function startTransaction(int $isolationLevel = Transaction::REPEATABLE_READ, bool $allowPending = false) : Transaction
    {
        $this->data['transaction_count']++;

        $ret = $this->getInnerConnection()->startTransaction($isolationLevel, $allowPending);

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    final public function select($relation, string $alias = null) : SelectQuery
    {
        $select = new SelectQuery($relation, $alias);
        $select->setConnection($this);

        return $select;
    }

    /**
     * {@inheritdoc}
     */
    final public function update($relation, string $alias = null) : UpdateQuery
    {
        $update = new UpdateQuery($relation, $alias);
        $update->setConnection($this);

        return $update;
    }

    /**
     * {@inheritdoc}
     */
    final public function insertQuery($relation) : InsertQueryQuery
    {
        $insert = new InsertQueryQuery($relation);
        $insert->setConnection($this);

        return $insert;
    }

    /**
     * {@inheritdoc}
     */
    final public function insertValues($relation) : InsertValuesQuery
    {
        $insert = new InsertValuesQuery($relation);
        $insert->setConnection($this);

        return $insert;
    }

    /**
     * {@inheritdoc}
     */
    final public function delete($relation, string $alias = null) : DeleteQuery
    {
        $insert = new DeleteQuery($relation, $alias);
        $insert->setConnection($this);

        return $insert;
    }
}
