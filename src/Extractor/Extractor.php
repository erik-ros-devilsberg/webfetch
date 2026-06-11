<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

use Devilsberg\Webfetch\Fetcher\FetchSuccess;

/**
 * The seam between "I have these fetched bytes" and "turn them into readable
 * content". Each source format is one implementation: HtmlExtractor handles
 * text/html today; a PdfExtractor slots in behind the same interface later.
 * DispatchingExtractor picks the right one by content type.
 */
interface Extractor
{
    public function extract(FetchSuccess $fetch): ExtractOutcome;
}
