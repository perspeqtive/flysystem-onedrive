<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Exception;

use Symfony\Component\HttpClient\Exception\TransportException;

final class ConnectTimeoutException extends TransportException
{
}
