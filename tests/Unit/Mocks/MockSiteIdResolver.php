<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks;

use PERSPEQTIVE\FlysystemOneDrive\Site\SiteIdResolverInterface;

final class MockSiteIdResolver implements SiteIdResolverInterface
{
    public string $siteIdToReturn = '';

    /**
     * @var list<string>
     */
    public array $resolvedDriveIdentifiers = [];

    public function resolve(string $driveIdentifier): string
    {
        $this->resolvedDriveIdentifiers[] = $driveIdentifier;

        return $this->siteIdToReturn;
    }
}
