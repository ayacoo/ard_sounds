<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Tests\Unit\Helper;

use Ayacoo\ArdSounds\Helper\ArdSoundsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\AbstractOEmbedHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ArdSoundsHelperTest extends UnitTestCase
{
    private ArdSoundsHelper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ArdSoundsHelper('ardsounds');
    }

    #[Test]
    public function isAbstractOEmbedHelper(): void
    {
        self::assertInstanceOf(AbstractOEmbedHelper::class, $this->subject);
    }

    #[Test]
    public function getMetaDataWithOEmbedData()
    {
        $fileMock = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->getMock();

        $expectedMetaData = [
            'width' => 640,
            'height' => 185,
            'title' => 'Sample Title',
            'ardsounds_thumbnail' => 'https://api.ardmediathek.de/image-service/images/urn:ard:image:abc',
        ];

        $ardSoundsHelper = $this->getMockBuilder(ArdSoundsHelper::class)
            ->onlyMethods(['getOnlineMediaId', 'getOEmbedData'])
            ->disableOriginalConstructor()
            ->getMock();

        $ardSoundsHelper->method('getOnlineMediaId')->willReturn('urn:ard:episode:96fa3cc508a2d92f');
        $ardSoundsHelper->method('getOEmbedData')
            ->with('urn:ard:episode:96fa3cc508a2d92f')
            ->willReturn([
                'title' => 'Sample Title',
                'thumbnail_url' => 'https://api.ardmediathek.de/image-service/images/urn:ard:image:abc',
            ]);

        $metaData = $ardSoundsHelper->getMetaData($fileMock);

        self::assertEquals($expectedMetaData, $metaData);
    }

    #[Test]
    public function getMetaDataWithoutOEmbedData()
    {
        $fileMock = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->getMock();

        $ardSoundsHelper = $this->getMockBuilder(ArdSoundsHelper::class)
            ->onlyMethods(['getOnlineMediaId', 'getOEmbedData'])
            ->disableOriginalConstructor()
            ->getMock();

        $ardSoundsHelper->method('getOnlineMediaId')->willReturn('urn:ard:episode:96fa3cc508a2d92f');
        $ardSoundsHelper->method('getOEmbedData')
            ->with('urn:ard:episode:96fa3cc508a2d92f')
            ->willReturn([]);

        $metaData = $ardSoundsHelper->getMetaData($fileMock);

        self::assertEmpty($metaData);
    }

    #[Test]
    public function getPublicUrlReturnsPublicUrl()
    {
        $mediaId = 'urn:ard:episode:96fa3cc508a2d92f';

        $fileMock = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->getMock();

        $ardSoundsHelperMock = $this->getMockBuilder(ArdSoundsHelper::class)
            ->onlyMethods(['getOnlineMediaId'])
            ->disableOriginalConstructor()
            ->getMock();
        $ardSoundsHelperMock->method('getOnlineMediaId')->with($fileMock)->willReturn($mediaId);

        $result = $ardSoundsHelperMock->getPublicUrl($fileMock);
        $expectedUrl = 'https://www.ardsounds.de/episode/urn:ard:episode:96fa3cc508a2d92f/';
        self::assertEquals($expectedUrl, $result);
    }

    #[Test]
    #[DataProvider('getMediaIdDataProvider')]
    public function getMediaIdWithValidUrlReturnsMediaIdOrNull(string $url, mixed $expectedMediaId)
    {
        $params = [$url];
        $methodName = 'getMediaId';
        $actualMediaId = $this->buildReflectionForProtectedFunction($methodName, $params);

        self::assertSame($expectedMediaId, $actualMediaId);
    }

    public static function getMediaIdDataProvider(): array
    {
        return [
            [
                'https://www.ardsounds.de/episode/urn:ard:episode:96fa3cc508a2d92f/',
                'urn:ard:episode:96fa3cc508a2d92f',
            ],
            [
                'https://ardsounds.de/episode/urn:ard:episode:96fa3cc508a2d92f/',
                'urn:ard:episode:96fa3cc508a2d92f',
            ],
            [
                'urn:ard:episode:96fa3cc508a2d92f',
                'urn:ard:episode:96fa3cc508a2d92f',
            ],
            ['https://www.ardsounds.de/episode/', null],
            ['https://www.ardsounds.de/', null],
            ['https://google.com', null],
            ['', null],
        ];
    }

    private function buildReflectionForProtectedFunction(string $methodName, array $params)
    {
        $reflectionClass = new \ReflectionClass($this->subject);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->subject, $params);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        GeneralUtility::purgeInstances();
    }
}
