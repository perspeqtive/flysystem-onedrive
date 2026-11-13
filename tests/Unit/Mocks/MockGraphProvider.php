<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks;

use Microsoft\Graph\Graph;
use PERSPEQTIVE\FlysystemOneDrive\Graph\GraphProviderInterface;

final class MockGraphProvider implements GraphProviderInterface
{
    public Graph $graphToReturn;

    public int $refreshCount = 0;

    public function getGraph(): Graph
    {
        return $this->graphToReturn;
    }

    public function refreshGraphToken(): void
    {
        ++$this->refreshCount;
    }
}
