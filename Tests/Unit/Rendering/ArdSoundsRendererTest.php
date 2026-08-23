<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Tests\Unit\Rendering;

use Ayacoo\ArdSounds\Event\ModifyArdSoundsOutputEvent;
use Ayacoo\ArdSounds\Helper\ArdSoundsHelper;
use Ayacoo\ArdSounds\Rendering\ArdSoundsRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ArdSoundsRendererTest extends UnitTestCase
{
    private ArdSoundsRenderer $subject;

    protected bool $resetSingletonInstances = true;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $configurationManagerMock = $this->getMockBuilder(ConfigurationManager::class)
            ->onlyMethods(['getConfiguration'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->subject = new ArdSoundsRenderer($eventDispatcherMock, $configurationManagerMock);
    }

    #[Test]
    public function hasFileRendererInterface(): void
    {
        self::assertInstanceOf(FileRendererInterface::class, $this->subject);
    }

    #[Test]
    public function canRenderWithMatchingMimeTypeReturnsTrue(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers']['ardsounds'] = ArdSoundsHelper::class;

        $fileResourceMock = $this->createMock(File::class);
        $fileResourceMock->expects(self::any())->method('getMimeType')->willReturn('audio/ardsounds');
        $fileResourceMock->expects(self::any())->method('getExtension')->willReturn('ardsounds');

        $result = $this->subject->canRender($fileResourceMock);
        self::assertTrue($result);
    }

    #[Test]
    public function canRenderReturnsFalseWhenOnlineMediaHelperNotRegistered(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers']['ardsounds']);

        $fileResourceMock = $this->createMock(File::class);
        $fileResourceMock->expects(self::any())->method('getMimeType')->willReturn('audio/ardsounds');
        $fileResourceMock->expects(self::any())->method('getExtension')->willReturn('ardsounds');

        $result = $this->subject->canRender($fileResourceMock);
        self::assertFalse($result);
    }

    #[Test]
    #[DataProvider('getPrivacySettingWithExistingConfigReturnsBooleanDataProvider')]
    public function getPrivacySettingWithExistingConfigReturnsBoolean(array $pluginConfig, bool $expected)
    {
        $eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $configurationManagerMock = $this->getMockBuilder(ConfigurationManager::class)
            ->onlyMethods(['getConfiguration'])
            ->disableOriginalConstructor()
            ->getMock();

        $configurationManagerMock
            ->expects(self::atLeastOnce())
            ->method('getConfiguration')
            ->with(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT)
            ->willReturn($pluginConfig);

        $subject = new ArdSoundsRenderer($eventDispatcherMock, $configurationManagerMock);

        $params = [];
        $methodName = 'getPrivacySetting';
        $result = $this->buildReflectionForProtectedFunction($methodName, $params, $subject);

        self::assertEquals($expected, $result);
    }

    public static function getPrivacySettingWithExistingConfigReturnsBooleanDataProvider(): array
    {
        return [
            'Privacy setting true' => [
                [
                    'plugin.' => [
                        'tx_ardsounds.' => [
                            'settings.' => [
                                'privacy' => true,
                            ],
                        ],
                    ],
                ],
                true,
            ],
            'Privacy setting false' => [
                [
                    'plugin.' => [
                        'tx_ardsounds.' => [
                            'settings.' => [
                                'privacy' => false,
                            ],
                        ],
                    ],
                ],
                false,
            ],
            'Privacy setting non-existing' => [
                [],
                false,
            ],
        ];
    }

    #[Test]
    public function renderWithoutPrivacyReturnsRequiredIframeMarkup(): void
    {
        $mediaId = 'urn:ard:episode:96fa3cc508a2d92f';
        $expected = '<iframe src="https://www.ardsounds.de/embed/episode/urn:ard:episode:96fa3cc508a2d92f/" '
            . 'width="640px" height="185px" frameBorder="0" scrolling="no"></iframe>';

        $fileResourceMock = $this->createMock(File::class);

        $event = new ModifyArdSoundsOutputEvent($expected);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects(self::once())->method('dispatch')->with($event)->willReturn($event);

        $configurationManagerMock = $this->getMockBuilder(ConfigurationManager::class)
            ->onlyMethods(['getConfiguration'])
            ->disableOriginalConstructor()
            ->getMock();
        $configurationManagerMock->method('getConfiguration')->willReturn([]);

        $ardSoundsRendererMock = $this->getMockBuilder(ArdSoundsRenderer::class)
            ->setConstructorArgs([$eventDispatcherMock, $configurationManagerMock])
            ->onlyMethods(['getMediaIdFromFile'])
            ->getMock();
        $ardSoundsRendererMock->method('getMediaIdFromFile')->with($fileResourceMock)->willReturn($mediaId);

        $result = $ardSoundsRendererMock->render($fileResourceMock, 640, 185);
        self::assertSame($expected, $result);
    }

    #[Test]
    public function renderWithPrivacyReturnsDataSrcIframeMarkup(): void
    {
        $mediaId = 'urn:ard:episode:96fa3cc508a2d92f';
        $expected = '<iframe data-src="https://www.ardsounds.de/embed/episode/urn:ard:episode:96fa3cc508a2d92f/" '
            . 'width="640px" height="185px" frameBorder="0" scrolling="no"></iframe>';

        $fileResourceMock = $this->createMock(File::class);

        $event = new ModifyArdSoundsOutputEvent($expected);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects(self::once())->method('dispatch')->with($event)->willReturn($event);

        $pluginConfig = [
            'plugin.' => [
                'tx_ardsounds.' => [
                    'settings.' => [
                        'privacy' => true,
                    ],
                ],
            ],
        ];

        $configurationManagerMock = $this->getMockBuilder(ConfigurationManager::class)
            ->onlyMethods(['getConfiguration'])
            ->disableOriginalConstructor()
            ->getMock();
        $configurationManagerMock
            ->expects(self::atLeastOnce())
            ->method('getConfiguration')
            ->with(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT)
            ->willReturn($pluginConfig);

        $ardSoundsRendererMock = $this->getMockBuilder(ArdSoundsRenderer::class)
            ->setConstructorArgs([$eventDispatcherMock, $configurationManagerMock])
            ->onlyMethods(['getMediaIdFromFile'])
            ->getMock();
        $ardSoundsRendererMock->method('getMediaIdFromFile')->with($fileResourceMock)->willReturn($mediaId);

        $result = $ardSoundsRendererMock->render($fileResourceMock, 640, 185);
        self::assertSame($expected, $result);
    }

    protected function buildReflectionForProtectedFunction(
        string $methodName,
        array $params,
        ArdSoundsRenderer $subject
    ): mixed {
        $reflectionClass = new \ReflectionClass($subject);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($subject, $params);
    }
}
