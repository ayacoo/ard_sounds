<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Tests\Functional\Domain\Repository;

use Ayacoo\ArdSounds\Domain\Repository\FileRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FileRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['ard_sounds'];

    private FileRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(FileRepository::class);
    }

    #[Test]
    public function getVideosByFileExtensionForNoRecordsReturnsEmptyResult(): void
    {
        $result = $this->subject->getVideosByFileExtension('jpg', 10);

        self::assertCount(0, $result);
    }

    #[Test]
    public function getVideosByFileExtensionReturnsArdSoundsMedia(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Basic.csv');
        $row = $this->subject->getVideosByFileExtension('ardsounds', 10);

        self::assertCount(1, $row);
        self::assertSame(1, $row[0]['uid']);
    }

    #[Test]
    public function getVideosByFileExtensionWithMaxResultsReturnsArdSoundsMedia(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MaxResults.csv');
        $row = $this->subject->getVideosByFileExtension('ardsounds', 1);

        self::assertCount(1, $row);
    }

    #[Test]
    public function getVideosByFileExtensionIgnoresMissingMedia(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MissingArdSounds.csv');
        $row = $this->subject->getVideosByFileExtension('ardsounds', 1);

        self::assertCount(0, $row);
    }
}
