<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphCollectionRequest;
use Microsoft\Graph\Http\GraphRequest;
use Microsoft\Graph\Model\DriveItem;
use PERSPEQTIVE\FlysystemOneDrive\Flysystem\OneDriveAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OneDriveAdapterTest extends TestCase
{
    private const SITE_DRIVE = 'contoso.sharepoint.com,1234/drive';

    public function testListContentsReturnsDriveRelativePaths(): void
    {
        $driveItem = new DriveItem([
            'name' => 'Hersteller_Vinylboden.xlsx',
            'size' => 4711,
            'file' => ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'lastModifiedDateTime' => '2026-08-23T10:00:00Z',
            'parentReference' => ['path' => '/drives/b!someDriveId/root:/Musterfirma/Import/neu'],
        ]);

        $collectionRequest = $this->createMock(GraphCollectionRequest::class);
        $collectionRequest->method('setReturnType')->willReturnSelf();
        $collectionRequest->method('isEnd')->willReturnOnConsecutiveCalls(false, true);
        $collectionRequest->method('getPage')->willReturn([$driveItem]);

        $graph = $this->createMock(Graph::class);
        $graph->method('createCollectionRequest')->willReturn($collectionRequest);

        $adapter = $this->createAdapter($graph);

        $contents = [...$adapter->listContents('Musterfirma/Import/neu', false)];

        self::assertCount(1, $contents);
        self::assertInstanceOf(FileAttributes::class, $contents[0]);
        self::assertSame('Musterfirma/Import/neu/Hersteller_Vinylboden.xlsx', $contents[0]->path());
        self::assertSame(4711, $contents[0]->fileSize());
    }

    public function testListContentsExposesTheWebUrlAsExtraMetadata(): void
    {
        $driveItem = new DriveItem([
            'name' => '123456_1.jpg',
            'file' => ['mimeType' => 'image/jpeg'],
            'webUrl' => 'https://contoso.sharepoint.com/sites/Umgebung/Dokumente/Musterfirma/123456_1.jpg',
            'parentReference' => ['path' => '/drives/b!someDriveId/root:/Musterfirma/Import/neu'],
        ]);

        $collectionRequest = $this->createMock(GraphCollectionRequest::class);
        $collectionRequest->method('setReturnType')->willReturnSelf();
        $collectionRequest->method('isEnd')->willReturnOnConsecutiveCalls(false, true);
        $collectionRequest->method('getPage')->willReturn([$driveItem]);

        $graph = $this->createMock(Graph::class);
        $graph->method('createCollectionRequest')->willReturn($collectionRequest);

        $contents = [...$this->createAdapter($graph)->listContents('Musterfirma/Import/neu', false)];

        self::assertSame(
            ['webUrl' => 'https://contoso.sharepoint.com/sites/Umgebung/Dokumente/Musterfirma/123456_1.jpg'],
            $contents[0]->extraMetadata(),
        );
        self::assertSame(
            OneDriveAdapter::WEB_URL_METADATA_KEY,
            array_key_first($contents[0]->extraMetadata()),
        );
    }

    public function testListContentsOmitsTheWebUrlWhenGraphDoesNotReportOne(): void
    {
        $driveItem = new DriveItem([
            'name' => 'Musterfirma',
            'folder' => ['childCount' => 1],
        ]);

        $collectionRequest = $this->createMock(GraphCollectionRequest::class);
        $collectionRequest->method('setReturnType')->willReturnSelf();
        $collectionRequest->method('isEnd')->willReturnOnConsecutiveCalls(false, true);
        $collectionRequest->method('getPage')->willReturn([$driveItem]);

        $graph = $this->createMock(Graph::class);
        $graph->method('createCollectionRequest')->willReturn($collectionRequest);

        $contents = [...$this->createAdapter($graph)->listContents('', false)];

        self::assertSame([], $contents[0]->extraMetadata());
    }

    public function testPublicUrlReturnsTheWebUrlOfTheDriveItem(): void
    {
        $graph = $this->createGraphReturningDriveItem(new DriveItem([
            'name' => '123456_1.jpg',
            'file' => ['mimeType' => 'image/jpeg'],
            'webUrl' => 'https://contoso.sharepoint.com/sites/Umgebung/Dokumente/Musterfirma/123456_1.jpg',
        ]));

        $url = $this->createAdapter($graph)->publicUrl('Musterfirma/Import/neu/123456_1.jpg', new Config());

        self::assertSame(
            'https://contoso.sharepoint.com/sites/Umgebung/Dokumente/Musterfirma/123456_1.jpg',
            $url,
        );
    }

    public function testPublicUrlFailsWhenTheDriveItemHasNoWebUrl(): void
    {
        $graph = $this->createGraphReturningDriveItem(new DriveItem(['name' => '123456_1.jpg']));

        $this->expectException(UnableToGeneratePublicUrl::class);

        $this->createAdapter($graph)->publicUrl('Musterfirma/Import/neu/123456_1.jpg', new Config());
    }

    public function testPublicUrlFailsWhenTheLookupFails(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->method('createRequest')->willThrowException(new RuntimeException('boom'));

        $this->expectException(UnableToGeneratePublicUrl::class);

        $this->createAdapter($graph)->publicUrl('Musterfirma/Import/neu/123456_1.jpg', new Config());
    }

    public function testTheAdapterIsUsedAsPublicUrlGeneratorByTheFilesystem(): void
    {
        $graph = $this->createGraphReturningDriveItem(new DriveItem([
            'name' => '123456_1.jpg',
            'file' => ['mimeType' => 'image/jpeg'],
            'webUrl' => 'https://contoso.sharepoint.com/sites/Umgebung/Dokumente/Musterfirma/123456_1.jpg',
        ]));
        $adapter = $this->createAdapter($graph);

        self::assertInstanceOf(PublicUrlGenerator::class, $adapter);
        self::assertSame(
            'https://contoso.sharepoint.com/sites/Umgebung/Dokumente/Musterfirma/123456_1.jpg',
            (new Filesystem($adapter))->publicUrl('Musterfirma/Import/neu/123456_1.jpg'),
        );
    }

    public function testListContentsToleratesMissingMetadata(): void
    {
        $driveItem = new DriveItem([
            'name' => 'Musterfirma',
            'folder' => ['childCount' => 3],
        ]);

        $collectionRequest = $this->createMock(GraphCollectionRequest::class);
        $collectionRequest->method('setReturnType')->willReturnSelf();
        $collectionRequest->method('isEnd')->willReturnOnConsecutiveCalls(false, true);
        $collectionRequest->method('getPage')->willReturn([$driveItem]);

        $graph = $this->createMock(Graph::class);
        $graph->method('createCollectionRequest')->willReturn($collectionRequest);

        $contents = [...$this->createAdapter($graph)->listContents('', false)];

        self::assertCount(1, $contents);
        self::assertSame('Musterfirma', $contents[0]->path());
        self::assertNull($contents[0]->lastModified());
    }

    public function testMoveSendsParentReferenceIdInsteadOfAddressingUrl(): void
    {
        $lookupRequest = $this->createMock(GraphRequest::class);
        $lookupRequest->method('setReturnType')->willReturnSelf();
        $lookupRequest->method('execute')->willReturn(new DriveItem(['id' => 'target-folder-id']));

        $body = null;
        $patchRequest = $this->createMock(GraphRequest::class);
        $patchRequest->method('attachBody')->willReturnCallback(
            static function (array $attachedBody) use (&$body, $patchRequest): GraphRequest {
                $body = $attachedBody;

                return $patchRequest;
            },
        );

        $requestedUrls = [];
        $graph = $this->createMock(Graph::class);
        $graph->method('createRequest')->willReturnCallback(
            static function (string $method, string $url) use (&$requestedUrls, $lookupRequest, $patchRequest): GraphRequest {
                $requestedUrls[] = $method . ' ' . $url;

                return $method === 'GET' ? $lookupRequest : $patchRequest;
            },
        );

        $this->createAdapter($graph)->move(
            'Musterfirma/Import/eingang/liste.xlsx',
            'Musterfirma/Import/aufgeteilt/2026-08-23/liste.xlsx',
        );

        self::assertSame(
            [
                'parentReference' => ['id' => 'target-folder-id'],
                'name' => 'liste.xlsx',
                '@microsoft.graph.conflictBehavior' => 'replace',
            ],
            $body,
        );
        self::assertContains(
            'GET /sites/' . self::SITE_DRIVE . '/root:/Musterfirma/Import/aufgeteilt/2026-08-23',
            $requestedUrls,
        );
    }

    public function testMoveFailureIsTranslatedIntoFlysystemException(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->method('createRequest')->willThrowException(new RuntimeException('boom'));

        $this->expectException(UnableToMoveFile::class);

        $this->createAdapter($graph)->move('a/b.xlsx', 'c/b.xlsx');
    }

    public function testFileExistsDistinguishesFilesFromFolders(): void
    {
        $adapter = $this->createAdapter($this->createGraphReturningDriveItem(new DriveItem([
            'name' => 'neu',
            'folder' => ['childCount' => 0],
        ])));

        self::assertFalse($adapter->fileExists('Musterfirma/Import/neu'));
        self::assertTrue($adapter->directoryExists('Musterfirma/Import/neu'));
    }

    public function testDirectoryExistsDistinguishesFoldersFromFiles(): void
    {
        $adapter = $this->createAdapter($this->createGraphReturningDriveItem(new DriveItem([
            'name' => 'liste.xlsx',
            'file' => ['mimeType' => 'application/octet-stream'],
        ])));

        self::assertTrue($adapter->fileExists('Musterfirma/Import/neu/liste.xlsx'));
        self::assertFalse($adapter->directoryExists('Musterfirma/Import/neu/liste.xlsx'));
    }

    public function testReadStreamReturnsAPhpStreamResource(): void
    {
        $graph = $this->createGraphReturningDriveItem(new DriveItem([
            'name' => 'liste.xlsx',
            'file' => ['mimeType' => 'application/octet-stream'],
            '@microsoft.graph.downloadUrl' => 'https://example.invalid/download',
        ]));
        $httpClient = new MockHttpClient(new MockResponse('tabellen-inhalt'));

        $adapter = new OneDriveAdapter($graph, self::SITE_DRIVE, $httpClient, ['directory_type' => 'sites']);
        $stream = $adapter->readStream('Musterfirma/Import/eingang/liste.xlsx');

        self::assertIsResource($stream);
        self::assertSame('tabellen-inhalt', stream_get_contents($stream));
        fclose($stream);
    }

    public function testReadReturnsTheFileContents(): void
    {
        $graph = $this->createGraphReturningDriveItem(new DriveItem([
            'name' => 'liste.xlsx',
            '@microsoft.graph.downloadUrl' => 'https://example.invalid/download',
        ]));
        $httpClient = new MockHttpClient(new MockResponse('tabellen-inhalt'));

        $adapter = new OneDriveAdapter($graph, self::SITE_DRIVE, $httpClient, ['directory_type' => 'sites']);

        self::assertSame('tabellen-inhalt', $adapter->read('Musterfirma/Import/eingang/liste.xlsx'));
    }

    public function testReadFailsWhenTheDriveItemHasNoDownloadUrl(): void
    {
        $graph = $this->createGraphReturningDriveItem(new DriveItem(['name' => 'liste.xlsx']));

        $this->expectException(UnableToReadFile::class);

        $this->createAdapter($graph)->read('Musterfirma/Import/eingang/liste.xlsx');
    }

    private function createGraphReturningDriveItem(DriveItem $driveItem): Graph
    {
        $request = $this->createMock(GraphRequest::class);
        $request->method('setReturnType')->willReturnSelf();
        $request->method('execute')->willReturn($driveItem);

        $graph = $this->createMock(Graph::class);
        $graph->method('createRequest')->willReturn($request);

        return $graph;
    }

    private function createAdapter(Graph $graph): OneDriveAdapter
    {
        return new OneDriveAdapter(
            $graph,
            self::SITE_DRIVE,
            $this->createMock(HttpClientInterface::class),
            ['directory_type' => 'sites'],
        );
    }
}
