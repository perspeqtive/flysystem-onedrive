<?php

namespace PERSPEQTIVE\FlysystemOneDrive\Flysystem;

use Exception;
use PERSPEQTIVE\FlysystemOneDrive\Exception\SiteNotFoundException;
use PERSPEQTIVE\FlysystemOneDrive\Exception\UnknownDriveException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

interface OneDriveFlysystemFactoryInterface
{
    /**
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws TransportExceptionInterface
     * @throws UnknownDriveException
     */
    public function get(string $identifier): OneDriveAdapter;
}