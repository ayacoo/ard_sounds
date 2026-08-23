<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Event;

/**
 * Dispatched by ArdSoundsOEmbedService after it tried to parse the "PodcastEpisode" JSON-LD block
 * from the ARD Sounds episode page. Listen to this event to replace the extracted data with your
 * own parsing of $html - for example if ARD Sounds changes their page markup and the built-in
 * ArdSoundsOEmbedService::extractPodcastEpisode() no longer matches, without having to wait for
 * an extension update.
 */
final class ModifyPodcastEpisodeEvent
{
    public function __construct(
        private readonly string $html,
        private ?array $episode
    ) {
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function getEpisode(): ?array
    {
        return $this->episode;
    }

    public function setEpisode(?array $episode): void
    {
        $this->episode = $episode;
    }
}
