<?php

declare(strict_types=1);

/**
 * A stand-in PDF worker that never returns — used by PdfExtractorTest to prove
 * the parent enforces its wall-clock timeout and reports a typed failure
 * instead of hanging. Not part of the shipped library.
 */
sleep(60);
