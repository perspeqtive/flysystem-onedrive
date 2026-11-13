<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks;

use Microsoft\Graph\Graph;
use PERSPEQTIVE\FlysystemOneDrive\Graph\GraphFactoryInterface;

final class MockGraphFactory implements GraphFactoryInterface
{
    public Graph $graphToReturn;

    public int $createCount = 0;

    public function create(): Graph
    {
        ++$this->createCount;

        return $this->graphToReturn;
    }
}
