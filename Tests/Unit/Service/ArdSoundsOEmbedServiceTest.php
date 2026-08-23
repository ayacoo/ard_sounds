<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Tests\Unit\Service;

use Ayacoo\ArdSounds\Event\ModifyPodcastEpisodeEvent;
use Ayacoo\ArdSounds\Service\ArdSoundsOEmbedService;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ArdSoundsOEmbedServiceTest extends UnitTestCase
{
    /**
     * The JSON-LD "PodcastEpisode" block as it is embedded by ARD Sounds on every episode page.
     */
    private const EXAMPLE_JSON_LD = '{"@context":"https://schema.org","@type":"PodcastEpisode","encodingFormat":'
        . '"audio/mp3","inLanguage":"de-DE","partOfSeries":{"@type":"PodcastSeries","name":'
        . '"Ball you need is love – aus Liebe zum Fußball","url":'
        . '"https://www.ardsounds.de/sendung/ball-you-need-is-love-aus-liebe-zum-fussball/'
        . 'urn:ard:show:b596125a6688fb35/","about":"Arnd Zeiglers Fußball-Podcast – Ball you need is love"},'
        . '"identifier":"16414373","name":"Per Mertesacker - Ich hatte den Ehrgeiz, es den Leuten zu zeigen","url":'
        . '"https://www.ardsounds.de/episode/urn:ard:episode:96fa3cc508a2d92f/","description":'
        . '"Der ehemalige Fußballprofi und Weltmeister spricht mit Arnd Zeigler.","image":'
        . '"https://api.ardmediathek.de/image-service/images/'
        . 'urn:ard:image:b09b0a010b3fd4f6?w=1280&ch=7b5a89b64060f97c",'
        . '"datePublished":"2026-05-15T12:00:09+02:00","timeRequired":"3687","duration":"PT1H61M27S",'
        . '"associatedMedia":{"@type":"MediaObject","contentUrl":'
        . '"https://wdrmedien-a.akamaihd.net/media/p/public/weltweit/2026/05/13/'
        . '6ea97870-e9e1-48e8-a884-064f2b29d788/6ea97870-e9e1-48e8-a884-064f2b29d788_MP3-128.mp3"},'
        . '"expires":"2028-05-15","productionCompany":"WDR"}';

    #[Test]
    public function getOEmbedDataParsesJsonLdFromEpisodePage(): void
    {
        $html = '<html><head><script type="application/ld+json" data-next-head="">'
            . self::EXAMPLE_JSON_LD . '</script></head><body></body></html>';

        $subject = $this->buildSubjectWithResponse(200, $html);

        $result = $subject->getOEmbedData('urn:ard:episode:96fa3cc508a2d92f');

        self::assertSame(
            'Per Mertesacker - Ich hatte den Ehrgeiz, es den Leuten zu zeigen',
            $result['title']
        );
        self::assertSame(
            'https://api.ardmediathek.de/image-service/images/'
            . 'urn:ard:image:b09b0a010b3fd4f6?w=1280&ch=7b5a89b64060f97c',
            $result['thumbnail_url']
        );
        self::assertSame(640, $result['width']);
        self::assertSame(185, $result['height']);
        self::assertSame('ARD Sounds', $result['provider_name']);
    }

    #[Test]
    public function getOEmbedDataReturnsEmptyArrayOnNonSuccessStatusCode(): void
    {
        $subject = $this->buildSubjectWithResponse(404, '');

        self::assertSame([], $subject->getOEmbedData('urn:ard:episode:96fa3cc508a2d92f'));
    }

    #[Test]
    public function getOEmbedDataReturnsEmptyArrayWhenNoJsonLdBlockIsPresent(): void
    {
        $subject = $this->buildSubjectWithResponse(200, '<html><head></head><body></body></html>');

        self::assertSame([], $subject->getOEmbedData('urn:ard:episode:96fa3cc508a2d92f'));
    }

    #[Test]
    public function getOEmbedDataReturnsEmptyArrayWhenJsonLdIsNotAPodcastEpisode(): void
    {
        $html = '<html><head><script type="application/ld+json">{"@type":"WebPage"}</script></head></html>';

        $subject = $this->buildSubjectWithResponse(200, $html);

        self::assertSame([], $subject->getOEmbedData('urn:ard:episode:96fa3cc508a2d92f'));
    }

    #[Test]
    public function getOEmbedDataReturnsEmptyArrayWhenRequestThrows(): void
    {
        $requestFactoryMock = $this->getMockBuilder(RequestFactory::class)
            ->onlyMethods(['request'])
            ->disableOriginalConstructor()
            ->getMock();
        $requestFactoryMock->method('request')->willThrowException(
            new ConnectException('Connection failed', $this->createMock(RequestInterface::class))
        );

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects(self::never())->method('dispatch');

        $subject = new ArdSoundsOEmbedService($requestFactoryMock, $eventDispatcherMock);

        self::assertSame([], $subject->getOEmbedData('urn:ard:episode:96fa3cc508a2d92f'));
    }

    #[Test]
    public function modifyPodcastEpisodeEventCanOverrideMarkupThatNoLongerMatches(): void
    {
        // Simulates ARD Sounds having changed their page markup so the built-in
        // extractPodcastEpisode() no longer finds a JSON-LD block, and a listener on
        // ModifyPodcastEpisodeEvent supplying its own extraction instead.
        $html = '<html><head><meta name="title" content="Overridden Title"></head></html>';

        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($html);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);

        $requestFactoryMock = $this->getMockBuilder(RequestFactory::class)
            ->onlyMethods(['request'])
            ->disableOriginalConstructor()
            ->getMock();
        $requestFactoryMock->method('request')->willReturn($responseMock);

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->method('dispatch')->willReturnCallback(
            static function (ModifyPodcastEpisodeEvent $event) {
                self::assertNull($event->getEpisode());
                $event->setEpisode([
                    'name' => 'Overridden Title',
                    'image' => 'https://example.com/override.jpg',
                ]);
                return $event;
            }
        );

        $subject = new ArdSoundsOEmbedService($requestFactoryMock, $eventDispatcherMock);

        $result = $subject->getOEmbedData('urn:ard:episode:96fa3cc508a2d92f');

        self::assertSame('Overridden Title', $result['title']);
        self::assertSame('https://example.com/override.jpg', $result['thumbnail_url']);
    }

    private function buildSubjectWithResponse(int $statusCode, string $body): ArdSoundsOEmbedService
    {
        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($body);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn($statusCode);
        $responseMock->method('getBody')->willReturn($streamMock);

        $requestFactoryMock = $this->getMockBuilder(RequestFactory::class)
            ->onlyMethods(['request'])
            ->disableOriginalConstructor()
            ->getMock();
        $requestFactoryMock->method('request')
            ->with('https://www.ardsounds.de/episode/urn:ard:episode:96fa3cc508a2d92f/')
            ->willReturn($responseMock);

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->method('dispatch')->willReturnArgument(0);

        return new ArdSoundsOEmbedService($requestFactoryMock, $eventDispatcherMock);
    }
}
