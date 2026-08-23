<?php

declare(strict_types=1);

namespace Ayacoo\ArdSounds\Rendering;

use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;

/**
 * Class ArdSoundsPreviewRenderer
 */
class ArdSoundsPreviewRenderer extends StandardContentPreviewRenderer
{
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $content = '';
        $row = $item->getRecord();
        if ($row['bodytext']) {
            $content = $this->linkEditContent($this->renderText($row['bodytext']), $row);
        }

        if ($row['assets']) {
            $content .= '<br/><br/><h6><strong>Assets</strong></h6>';
            $fileReferences = BackendUtility::resolveFileReferences('tt_content', 'assets', $row);
            foreach ($fileReferences as $fileReferenceObject) {
                if ((bool)$fileReferenceObject->getProperty('hidden')) {
                    continue;
                }
                $fileObject = $fileReferenceObject->getOriginalFile();
                if (!$fileObject->isMissing()) {
                    $content .= '<a href="' . htmlspecialchars((string)$fileObject->getPublicUrl());
                    $content .= '" target="_blank">';
                    $content .= htmlspecialchars($fileObject->getProperty('title'));

                    $image = $fileObject->getMetaData()->offsetGet('ardsounds_thumbnail') ?? '';
                    $content .= '<br/><img style="height: 150px;" src="' . htmlspecialchars((string)$image) . '" />';

                    $content .= '</a>';
                    $content .= '<hr/>';
                }
            }
        }

        return $content;
    }
}
