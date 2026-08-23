<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Command;

use Ayacoo\ArdSounds\Domain\Repository\FileRepository;
use Ayacoo\ArdSounds\Helper\ArdSoundsHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class UpdateMetadataCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Updates the ARD Sounds metadata');
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_OPTIONAL,
            'Defines the number of ARD Sounds episodes to be checked',
            10
        );
    }

    public function __construct(
        protected FileRepository $fileRepository,
        protected MetaDataRepository $metadataRepository,
        protected ResourceFactory $resourceFactory,
        protected ProcessedFileRepository $processedFileRepository,
        protected ArdSoundsHelper $ardSoundsHelper
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int)($input->getOption('limit') ?? 10);

        $episodes = $this->fileRepository->getVideosByFileExtension('ardsounds', $limit);
        foreach ($episodes as $episode) {
            $file = $this->resourceFactory->getFileObject($episode['uid']);
            $metaData = $this->ardSoundsHelper->getMetaData($file);
            if (!empty($metaData)) {
                $newMetaData = [
                    'width' => (int)$metaData['width'],
                    'height' => (int)$metaData['height'],
                    'ardsounds_thumbnail' => $metaData['ardsounds_thumbnail'],
                ];
                if (isset($metaData['title'])) {
                    $newMetaData['title'] = $metaData['title'];
                }
                $this->metadataRepository->update($file->getUid(), $newMetaData);
                $this->handlePreviewImage($this->ardSoundsHelper, $file);
                $io->success($file->getProperty('title') . '(UID: ' . $file->getUid() . ') was processed');
            }
        }

        return Command::SUCCESS;
    }

    protected function handlePreviewImage(ArdSoundsHelper $onlineMediaHelper, File $file): void
    {
        $processedFiles = $this->processedFileRepository->findAllByOriginalFile($file);
        foreach ($processedFiles as $processedFile) {
            $processedFile->delete();
        }

        $mediaId = $onlineMediaHelper->getOnlineMediaId($file);
        $temporaryFileName = $onlineMediaHelper->getTempFolderPath() . $file->getExtension() . '_';
        $temporaryFileName .= md5($mediaId) . '.jpg';
        if (file_exists($temporaryFileName)) {
            unlink($temporaryFileName);
        }
        $onlineMediaHelper->getPreviewImage($file);
    }
}
