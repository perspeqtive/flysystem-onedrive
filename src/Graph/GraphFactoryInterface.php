<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph;

use Microsoft\Graph\Graph;

interface GraphFactoryInterface
{
    public function create(): Graph;
}
