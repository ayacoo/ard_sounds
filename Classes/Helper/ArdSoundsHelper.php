<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Helper;

use Ayacoo\ArdSounds\Service\ArdSoundsOEmbedService;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\AbstractOEmbedHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ARD Sounds helper class
 */
class ArdSoundsHelper extends AbstractOEmbedHelper
{
    protected function getOEmbedData($mediaId)
    {
        return GeneralUtility::makeInstance(ArdSoundsOEmbedService::class)->getOEmbedData($mediaId);
    }

    /**
     * ARD Sounds does not offer an official oEmbed endpoint yet, so this points to the
     * episode page that ArdSoundsOEmbedService parses instead of a real oEmbed URL.
     */
    protected function getOEmbedUrl($mediaId, $format = 'json')
    {
        return sprintf('https://www.ardsounds.de/episode/%s/', $mediaId);
    }

    public function transformUrlToFile($url, Folder $targetFolder)
    {
        $mediaId = $this->getMediaId($url);
        if ($mediaId === null || $mediaId === '') {
            return null;
        }

        return $this->transformMediaIdToFile($mediaId, $targetFolder, $this->extension);
    }

    public function getPublicUrl(File $file, $relativeToCurrentScript = false)
    {
        return sprintf('https://www.ardsounds.de/episode/%s/', $this->getOnlineMediaId($file));
    }

    public function getMetaData(File $file): array
    {
        $metaData = [];
        $oEmbed = $this->getOEmbedData($this->getOnlineMediaId($file));
        if ($oEmbed) {
            $metaData['width'] = 640;
            $metaData['height'] = 185;
            $metaData['title'] = $oEmbed['title'] ?? '';
            $metaData['ardsounds_thumbnail'] = $oEmbed['thumbnail_url'] ?? '';
        }

        return $metaData;
    }

    public function getPreviewImage(File $file)
    {
        $properties = $file->getProperties();
        $previewImageUrl = $properties['ardsounds_thumbnail'] ?? '';

        if ($previewImageUrl === '') {
            $oEmbed = $this->getOEmbedData($this->getOnlineMediaId($file));
            $previewImageUrl = $oEmbed['thumbnail_url'] ?? '';
        }

        $mediaId = $this->getOnlineMediaId($file);
        $temporaryFileName = $this->getTempFolderPath() . $file->getExtension() . '_' . md5($mediaId) . '.jpg';

        if (!empty($previewImageUrl)) {
            $previewImage = GeneralUtility::getUrl($previewImageUrl);
            if ($previewImage !== false && $previewImage !== '') {
                file_put_contents($temporaryFileName, $previewImage);
                GeneralUtility::fixPermissions($temporaryFileName);
                return $temporaryFileName;
            }
        }

        return '';
    }

    public function getTempFolderPath(): string
    {
        $path = Environment::getPublicPath() . '/typo3temp/assets/online_media/';
        if (!is_dir($path)) {
            GeneralUtility::mkdir_deep($path);
        }
        return $path;
    }

    /**
     * Accepts both the full episode URL and a bare URN, e.g.
     * "https://www.ardsounds.de/episode/urn:ard:episode:96fa3cc508a2d92f/" or
     * "urn:ard:episode:96fa3cc508a2d92f".
     */
    protected function getMediaId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~^urn:ard:episode:[a-z0-9]+$~i', $url) === 1) {
            return $url;
        }

        $pattern = '~^https?://(?:www\.)?ardsounds\.de/episode/(urn:ard:episode:[a-z0-9]+)/?(?:[?#].*)?$~i';
        if (preg_match($pattern, $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
