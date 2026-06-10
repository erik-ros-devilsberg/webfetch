<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests;

use Devilsberg\Webfetch\Webfetch;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testPackageClassAutoloads(): void
    {
        self::assertTrue(class_exists(Webfetch::class));
    }

    public function testVersionIsNonEmpty(): void
    {
        self::assertNotSame('', Webfetch::VERSION);
    }
}
