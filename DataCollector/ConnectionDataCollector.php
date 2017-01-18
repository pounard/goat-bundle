<?php

declare(strict_types=1);

namespace Goat\Bundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * Connection methods calls data collector
 */
class ConnectionDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var TimeConnectionProxy
     */
    private $session;

    /**
     * Default constructor
     *
     * @param TimeConnectionProxy $session
     */
    public function __construct(TimeConnectionProxy $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = $this->session->getCollectedData();
    }

    /**
     * Get collected data
     *
     * @return array
     */
    public function getData() : array
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        return $this->session->getCollectedData();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'goat_connection';
    }
}
