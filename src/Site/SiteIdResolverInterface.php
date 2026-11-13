<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Site;

use GuzzleHttp\Exception\GuzzleException;
use Microsoft\Graph\Exception\GraphException;
use PERSPEQTIVE\FlysystemOneDrive\Exception\SiteNotFoundException;
use PERSPEQTIVE\FlysystemOneDrive\Exception\UnknownDriveException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

interface SiteIdResolverInterface
{
    /**
     * Resolves the Graph site id of a drive configured under "flysystem_one_drive_adapter.drives".
     *
     * @throws SiteNotFoundException
     * @throws UnknownDriveException
     * @throws GraphException
     * @throws GuzzleException
     * @throws ExceptionInterface
     */
    public function resolve(string $driveIdentifier): string;
}
