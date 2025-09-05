<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph;

use Microsoft\Graph\Graph;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

interface GraphProviderInterface
{
    /**
     * @throws TransportExceptionInterface
     */
    public function getGraph(): Graph;

    /**
     * @throws TransportExceptionInterface
     */
    public function refreshGraphToken(): void;
}