<?php

declare(strict_types=1);

namespace KNPLabs\Snappy\Backend\WkHtmlToPdf\ExtraOption;

use KNPLabs\Snappy\Backend\WkHtmlToPdf\ExtraOption;

class EnableTocBackLinks implements ExtraOption
{
    public function isRepeatable(): bool
    {
        return false;
    }

    public function compile(): array
    {
        return ['--enable-toc-back-links'];
    }
}