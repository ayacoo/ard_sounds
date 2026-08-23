<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Rendering;

use Ayacoo\ArdSounds\Event\ModifyArdSoundsOutputEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperInterface;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * ARD Sounds renderer class
 */
class ArdSoundsRenderer implements FileRendererInterface
{
    /**
     * @var OnlineMediaHelperInterface|false
     */
    protected $onlineMediaHelper;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConfigurationManagerInterface $configurationManager
    ) {
    }

    public function getPriority()
    {
        return 1;
    }

    public function canRender(FileInterface $file)
    {
        return ($file->getMimeType() === 'audio/ardsounds' || $file->getExtension() === 'ardsounds') &&
            $this->getOnlineMediaHelper($file) !== false;
    }

    public function render(FileInterface $file, $width, $height, array $options = [])
    {
        $mediaId = $this->getMediaIdFromFile($file);
        $output = $this->renderIframe($mediaId);
        if ($this->getPrivacySetting()) {
            $output = str_replace('src', 'data-src', $output);
        }

        $modifyArdSoundsOutputEvent = $this->eventDispatcher->dispatch(
            new ModifyArdSoundsOutputEvent($output)
        );
        return $modifyArdSoundsOutputEvent->getOutput();
    }

    protected function getOnlineMediaHelper(FileInterface $file)
    {
        if ($this->onlineMediaHelper === null) {
            $orgFile = $file;
            if ($orgFile instanceof FileReference) {
                $orgFile = $orgFile->getOriginalFile();
            }
            if ($orgFile instanceof File) {
                $this->onlineMediaHelper = GeneralUtility::makeInstance(OnlineMediaHelperRegistry::class)
                    ->getOnlineMediaHelper($orgFile);
            } else {
                $this->onlineMediaHelper = false;
            }
        }
        return $this->onlineMediaHelper;
    }

    protected function getMediaIdFromFile(FileInterface $file)
    {
        if ($file instanceof FileReference) {
            $orgFile = $file->getOriginalFile();
        } else {
            $orgFile = $file;
        }

        return $this->getOnlineMediaHelper($file)->getOnlineMediaId($orgFile);
    }

    protected function renderIframe(string $mediaId): string
    {
        return '<iframe src="https://www.ardsounds.de/embed/episode/' . $mediaId
            . '/" width="640px" height="185px" frameBorder="0" scrolling="no"></iframe>';
    }

    protected function getPrivacySetting(): bool
    {
        $privacy = false;
        $extbaseFrameworkConfiguration = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );
        $extSettings = $extbaseFrameworkConfiguration['plugin.']['tx_ardsounds.']['settings.'] ?? null;
        if (is_array($extSettings)) {
            $privacy = (bool)($extSettings['privacy'] ?? false);
        }
        return $privacy;
    }
}
