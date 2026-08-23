<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Service;

use Ayacoo\ArdSounds\Event\ModifyPodcastEpisodeEvent;
use GuzzleHttp\Exception\GuzzleException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * ARD Sounds does not (yet) offer an official oEmbed endpoint. As a stand-in, this service
 * fetches the public episode page and parses the "PodcastEpisode" JSON-LD block that ARD Sounds
 * embeds in every episode page to derive oEmbed-like metadata. Once an official oEmbed endpoint
 * exists, only this class needs to be replaced with a real oEmbed HTTP call.
 */
class ArdSoundsOEmbedService
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @param string $mediaId the episode URN, e.g. "urn:ard:episode:96fa3cc508a2d92f"
     */
    public function getOEmbedData(string $mediaId): array
    {
        $episodeUrl = $this->getEpisodeUrl($mediaId);

        try {
            $response = $this->requestFactory->request($episodeUrl);
            if ($response->getStatusCode() !== 200) {
                return [];
            }
            $html = $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            return [];
        }

        $modifyPodcastEpisodeEvent = $this->eventDispatcher->dispatch(
            new ModifyPodcastEpisodeEvent($html, $this->extractPodcastEpisode($html))
        );
        $episode = $modifyPodcastEpisodeEvent->getEpisode();
        if ($episode === null) {
            return [];
        }

        return [
            'title' => $episode['name'] ?? '',
            'description' => $episode['description'] ?? '',
            'thumbnail_url' => $episode['image'] ?? '',
            'width' => 640,
            'height' => 185,
            'provider_name' => 'ARD Sounds',
        ];
    }

    protected function getEpisodeUrl(string $mediaId): string
    {
        return sprintf('https://www.ardsounds.de/episode/%s/', $mediaId);
    }

    /**
     * Searches all "application/ld+json" script blocks on the page for the one
     * describing the "PodcastEpisode" and returns its decoded data.
     */
    protected function extractPodcastEpisode(string $html): ?array
    {
        $pattern = '~<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>~is';
        $matchCount = preg_match_all($pattern, $html, $matches);
        if ($matchCount === false || $matchCount === 0) {
            return null;
        }

        foreach ($matches[1] as $jsonLd) {
            $data = json_decode(trim($jsonLd), true);
            if (is_array($data) && ($data['@type'] ?? '') === 'PodcastEpisode') {
                return $data;
            }
        }

        return null;
    }
}
