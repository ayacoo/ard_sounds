<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Core\Event\BootCompletedEvent;
use TYPO3\CMS\Core\Imaging\IconRegistry;

#[AsEventListener(identifier: 'ayacoo/ard-sounds/register-file-extension-icon')]
final class RegisterFileExtensionIconEventListener
{
    public function __construct(
        private readonly IconRegistry $iconRegistry
    ) {
    }

    public function __invoke(BootCompletedEvent $event): void
    {
        $this->iconRegistry->registerFileExtension('ardsounds', 'mimetypes-media-image-ardsounds');
    }
}
