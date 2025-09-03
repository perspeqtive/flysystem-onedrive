<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Flysystem;

use Exception;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Directory;
use Microsoft\Graph\Model\DriveItem;
use Microsoft\Graph\Model\File;
use Microsoft\Graph\Model\UploadSession;
use stdClass;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OneDriveAdapter implements FilesystemAdapter
{
    protected Graph $graph;
    protected string $drive;
    protected array $options = [];
    protected HttpClientInterface $httpClient;

    public function __construct(Graph $graph, string $drive, HttpClientInterface $httpClient, array $options = [])
    {
        $this->graph = $graph;
        $this->drive = $drive;
        $this->httpClient = $httpClient;

        $default_options = [
            'request_timeout' => 90,
            'chunk_size' => 320 * 1024 * 10,
            'directory_type' => 'drive',
        ];

        $this->options = array_merge($default_options, $options);

        if ($this->options['chunk_size'] % (320 * 1024) !== 0) {
            throw new Exception('Chunk size must be a multiple of 320KB');
        }
    }

    protected function getDriveRootUrl(): string
    {
        return '/' . $this->options['directory_type'] . '/' . $this->drive . '/root';
    }

    protected function buildItemUrl(string $path): string
    {
        $path = trim($path, '/');
        if ($path === '' || $path === '.') {
            return $this->getDriveRootUrl();
        }
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        return $this->getDriveRootUrl() . ':/' . $encodedPath;
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->getFile($path);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $this->getDirectory($path);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config = null): void
    {
        if (strlen($contents) > 4194304) {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);
            $this->writeStream($path, $stream, $config);
            return;
        }

        $path = trim($path, '/');
        $parentItem = $this->buildItemUrl(dirname($path));
        $fileName = basename($path);

        $this->graph
            ->createRequest('PUT', $this->buildItemUrl($path) . ':/content')
            ->addHeaders(['Content-Type' => 'text/plain'])
            ->attachBody($contents)
            ->execute();
    }

    public function writeStream(string $path, $contents, Config $config = null): void
    {
        $path = trim($path, '/');
        $uploadSession = $this->createUploadSession($path);
        $uploadUrl = $uploadSession->getUploadUrl();

        $chunkSize = $config?->withDefaults($this->options)->get('chunk_size') ?? $this->options['chunk_size'];
        $offset = 0;
        $meta = fstat($contents);

        while ($chunk = fread($contents, $chunkSize)) {
            $this->uploadChunk($uploadUrl, $chunk, $meta['size'], $offset);
            $offset += strlen($chunk);
        }
    }

    protected function uploadChunk(string $uploadUrl, string $chunk, int $fileSize, int $firstByte): void
    {
        $chunkSize =  strlen($chunk);
        $lastByte = $firstByte + $chunkSize - 1;
        $headers = [
            'Content-Range' => "bytes $firstByte-$lastByte/$fileSize",
            'Content-Length' => (string) $chunkSize,
        ];

        $response = $this->httpClient->request('PUT', $uploadUrl, [
            'body' => $chunk,
            'headers' => $headers,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new Exception("Upload failed with status {$response->getStatusCode()}");
        }
    }

    protected function createUploadSession(string $path): UploadSession
    {
        return $this->graph
            ->createRequest('POST', $this->buildItemUrl($path) . ':/createUploadSession')
            ->setReturnType(UploadSession::class)
            ->execute();
    }

    public function read(string $path): string
    {
        try {
            $downloadUrl = $this->getDriveItem($path)->getProperties()['@microsoft.graph.downloadUrl'];
            $response = $this->httpClient->request('GET', $downloadUrl);
            return $response->getContent();
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $downloadUrl = $this->getDriveItem($path)->getProperties()['@microsoft.graph.downloadUrl'];
            return $this->httpClient->request('GET', $downloadUrl)->toStream(true)->detach();
        } catch (\Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        $this->graph->createRequest('DELETE', $this->buildItemUrl($path))->execute();
    }

    public function deleteDirectory(string $path): void
    {
        $this->delete($path);
    }

    public function createDirectory(string $path, ?Config $config = null): void
    {
        $parentPath = dirname($path);
        $dirName = basename($path);

        $this->graph
            ->createRequest('POST', $this->buildItemUrl($parentPath) . ($parentPath === '.' ? '' : ':') . '/children')
            ->attachBody([
                'name' => $dirName,
                'folder' => new stdClass(),
            ])
            ->setReturnType(DriveItem::class)
            ->execute();
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Unsupported Operation');
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, 'Unsupported Operation');
    }

    public function move(string $source, string $destination, Config $config = null): void
    {
        $destinationPath = dirname($destination);
        $newName = basename($destination);

        $this->graph
            ->createRequest('PATCH', $this->buildItemUrl($source))
            ->attachBody([
                'parentReference' => ['path' => $this->buildItemUrl($destinationPath)],
                'name' => $newName,
            ])
            ->execute();
    }

    public function copy(string $source, string $destination, Config $config = null): void
    {
        $destinationPath = dirname($destination);
        $newName = basename($destination);

        $this->graph
            ->createRequest('POST', $this->buildItemUrl($source) . '/copy')
            ->attachBody([
                'parentReference' => ['path' => $this->buildItemUrl($destinationPath)],
                'name' => $newName,
            ])
            ->execute();
    }

    public function mimeType(string $path): FileAttributes
    {

        $file = $this->getFile($path);
        return new FileAttributes($path, $file->getSize(), null, $file->getLastModifiedDateTime()->getTimestamp(), $file->getFile()->getMimeType());
    }

    public function lastModified(string $path): FileAttributes
    {
        $file = $this->getDriveItem($path);
        return new FileAttributes($path, $file->getSize(), null, $file->getLastModifiedDateTime()->getTimestamp());
    }

    public function fileSize(string $path): FileAttributes
    {
        $file = $this->getFile($path);
        return new FileAttributes($path, $file->getSize());
    }

    public function getDriveItem(string $path): DriveItem
    {
        return $this->graph
            ->createRequest('GET', $this->buildItemUrl($path))
            ->setReturnType(DriveItem::class)
            ->execute();
    }

    /**
     * @return iterable<StorageAttributes>
     * @throws \Exception
     */
    public function listContents(string $path, bool $deep = true): iterable
    {
        try {
            $rootPath = trim($path, '/');
            $url = $rootPath ? $this->buildItemUrl($rootPath) . ':/children' : $this->getDriveRootUrl() . '/children';

            $items = $this->fetchDriveItems($url);

            if ($deep) {
                $folders = array_filter($items, fn(DriveItem $item) => $item->getFolder() !== null);

                while (!empty($folders)) {
                    $folder = array_pop($folders);
                    $folderPath = ltrim(str_replace($this->getDriveRootUrl() . ':', '', $folder->getParentReference()->getPath()) . '/' . $folder->getName(), '/');
                    $children = $this->fetchDriveItems($this->buildItemUrl($folderPath) . ':/children');
                    $items = array_merge($items, $children);
                    $folders = array_merge($folders, array_filter($children, fn(DriveItem $child) => $child->getFolder() !== null));
                }
            }

            return $this->convertDriveItemsToStorageAttributes($items);
        } catch (\Throwable $e) {
            throw new \Exception('Error listing contents: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Hilfsmethode, um DriveItems mit Graph API abzurufen
     *
     * @param string $url
     * @return DriveItem[]
     * @throws \Microsoft\Graph\Exception\GraphException
     */
    protected function fetchDriveItems(string $url): array
    {
        $request = $this->graph->createCollectionRequest('GET', $url)->setReturnType(DriveItem::class);
        $items = [];

        while (!$request->isEnd()) {
            $items = array_merge($items, $request->getPage());
        }

        return $items;
    }

    /**
     * Konvertiert DriveItems in Flysystem StorageAttributes
     *
     * @param DriveItem[] $driveItems
     * @return StorageAttributes[]
     */
    protected function convertDriveItemsToStorageAttributes(array $driveItems): array
    {
        return array_map(function (DriveItem $item) {
            $isFile = $item->getFile() !== null;
            $class = $isFile ? FileAttributes::class : DirectoryAttributes::class;

            $path = ltrim(str_replace($this->getDriveRootUrl() . ':', '', $item->getParentReference()->getPath()) . '/' . $item->getName(), '/');

            return $class::fromArray([
                StorageAttributes::ATTRIBUTE_TYPE => $isFile ? StorageAttributes::TYPE_FILE : StorageAttributes::TYPE_DIRECTORY,
                StorageAttributes::ATTRIBUTE_PATH => $path,
                StorageAttributes::ATTRIBUTE_LAST_MODIFIED => $item->getLastModifiedDateTime()->getTimestamp(),
                StorageAttributes::ATTRIBUTE_FILE_SIZE => $isFile ? $item->getSize() : null,
                StorageAttributes::ATTRIBUTE_MIME_TYPE => $isFile ? $item->getFile()->getMimeType() : null,
                'visibility' => 'public',
            ]);
        }, $driveItems);
    }

    private function getFile(string $path): File
    {
        $path = $this->buildItemUrl($path);
        return $this->graph
            ->createRequest('GET', $path)
            ->setReturnType(File::class)
            ->execute();
    }

    private function getDirectory(string $path): Directory
    {
        $path = $this->buildItemUrl($path);
        return $this->graph
            ->createRequest('GET', $path)
            ->setReturnType(Directory::class)
            ->execute();
    }

}
