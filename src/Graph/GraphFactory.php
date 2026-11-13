<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph;

use Microsoft\Graph\Graph;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\GuzzleClientFactoryInterface;

final readonly class GraphFactory implements GraphFactoryInterface
{
    public function __construct(
        private GuzzleClientFactoryInterface $clientFactory,
        private float $requestTimeout,
    ) {
    }

    public function create(): Graph
    {
        return new TimeoutAwareGraph($this->clientFactory, $this->requestTimeout);
    }
}
