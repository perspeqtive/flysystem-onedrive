<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Site;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Model\Site;
use PERSPEQTIVE\FlysystemOneDrive\Exception\SiteNotFoundException;
use PERSPEQTIVE\FlysystemOneDrive\Exception\UnknownDriveException;
use PERSPEQTIVE\FlysystemOneDrive\Graph\GraphProviderInterface;

use function array_map;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function urlencode;

/**
 * Drives with a "site" configuration are resolved by their server relative path, which answers immediately.
 * Drives without it fall back to the search endpoint, which matches the display name but may be very slow.
 * Resolved site ids are kept for the lifetime of the service.
 */
final class GraphSiteIdResolver implements SiteIdResolverInterface
{
    private const HTTP_NOT_FOUND = 404;

    /**
     * @var array<string, string>
     */
    private array $resolvedSiteIds = [];

    /**
     * @param array<string, array{drive: string, site?: array{hostname: string, path: string}|null}> $drives
     */
    public function __construct(
        private readonly GraphProviderInterface $graphProvider,
        private readonly array $drives,
    ) {
    }

    /**
     * @throws GraphException
     * @throws GuzzleException
     */
    public function resolve(string $driveIdentifier): string
    {
        return $this->resolvedSiteIds[$driveIdentifier] ??= $this->lookUpSiteId($driveIdentifier);
    }

    /**
     * @throws GraphException
     * @throws GuzzleException
     */
    private function lookUpSiteId(string $driveIdentifier): string
    {
        $drive = $this->drives[$driveIdentifier]
            ?? throw new UnknownDriveException(sprintf('Unknown drive "%s".', $driveIdentifier));

        $site = $drive['site'] ?? null;

        if ($site === null) {
            return $this->lookUpSiteIdByDisplayName($drive['drive']);
        }

        return $this->lookUpSiteIdByPath($site['hostname'], $site['path']);
    }

    /**
     * @throws GraphException
     * @throws GuzzleException
     */
    private function lookUpSiteIdByPath(string $hostname, string $path): string
    {
        $encodedPath = implode('/', array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            explode('/', $path),
        ));

        try {
            $site = $this->graphProvider->getGraph()
                ->createRequest('GET', sprintf('/sites/%s:/%s', $hostname, $encodedPath))
                ->setReturnType(Site::class)
                ->execute();
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() !== self::HTTP_NOT_FOUND) {
                throw $exception;
            }

            throw new SiteNotFoundException(
                sprintf('Site "%s/%s" not found.', $hostname, $path),
                previous: $exception,
            );
        }

        $siteId = $site instanceof Site ? $site->getId() : null;

        if (!is_string($siteId) || $siteId === '') {
            throw new SiteNotFoundException(sprintf('Site "%s/%s" has no id.', $hostname, $path));
        }

        return $siteId;
    }

    /**
     * @throws GraphException
     * @throws GuzzleException
     */
    private function lookUpSiteIdByDisplayName(string $displayName): string
    {
        $sites = $this->graphProvider->getGraph()
            ->createRequest('GET', '/sites?search=' . urlencode($displayName))
            ->setReturnType(Site::class)
            ->execute();

        foreach (is_array($sites) ? $sites : [] as $site) {
            if ($site instanceof Site && $site->getDisplayName() === $displayName && is_string($site->getId())) {
                return $site->getId();
            }
        }

        throw new SiteNotFoundException(sprintf('Site "%s" not found.', $displayName));
    }
}
