<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Corpus;

use Devilsberg\Webfetch\Extractor\ExtractSuccess;
use Devilsberg\Webfetch\Extractor\HtmlExtractor;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Serializer\JsonSerializer;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * The extraction quality gate (roadmap Phase 5): every corpus page must
 * keep meeting its committed baseline, and the corpus itself must stay at
 * ≥80% usable. Page expectations live in tests/corpus/baseline.json; the
 * pages and their licenses in tests/corpus/ (see ATTRIBUTION.md).
 */
final class CorpusGateTest extends TestCase
{
    private const float MIN_USABLE_SHARE = 0.80;

    /**
     * @return array<string, array{file: string, url: string, rating: string,
     *                             strategy: string, min_words: int,
     *                             contains: string, title_contains: ?string}>
     */
    private static function baseline(): array
    {
        $json = file_get_contents(__DIR__ . '/../corpus/baseline.json');
        self::assertNotFalse($json);
        /** @var array<string, array{file: string, url: string, rating: string, strategy: string, min_words: int, contains: string, title_contains: ?string}> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function testBaselineDeclaresAtLeast80PercentUsable(): void
    {
        $baseline = self::baseline();
        $usable = count(array_filter($baseline, static fn (array $page): bool => $page['rating'] === 'usable'));

        self::assertGreaterThanOrEqual(
            self::MIN_USABLE_SHARE,
            $usable / count($baseline),
            'The committed corpus baseline has dropped below 80% usable — extraction quality has regressed.',
        );
    }

    public function testEveryCorpusPageMeetsItsBaseline(): void
    {
        $extractor = new HtmlExtractor();
        $serializer = new JsonSerializer();
        $validator = new Validator();
        $schemaJson = file_get_contents(__DIR__ . '/../../schema/webfetch-success.schema.json');
        self::assertNotFalse($schemaJson);
        $schema = json_decode($schemaJson);
        self::assertIsObject($schema);

        $regressions = [];
        foreach (self::baseline() as $name => $expect) {
            $html = file_get_contents(__DIR__ . '/../' . $expect['file']);
            if ($html === false) {
                $regressions[] = "{$name}: corpus file {$expect['file']} is missing";
                continue;
            }

            $outcome = $extractor->extract(new FetchSuccess($expect['url'], 200, 'text/html', 'utf-8', $html));
            if (!$outcome instanceof ExtractSuccess) {
                $regressions[] = "{$name}: extraction failed (baseline rating: {$expect['rating']})";
                continue;
            }

            $content = $outcome->content;
            $misses = [];
            if ($content->strategy->value !== $expect['strategy']) {
                $misses[] = "strategy {$content->strategy->value} (expected {$expect['strategy']})";
            }
            if ($content->wordCount < $expect['min_words']) {
                $misses[] = "word count {$content->wordCount} (expected ≥{$expect['min_words']})";
            }
            if (!str_contains($content->contentMarkdown, $expect['contains'])) {
                $misses[] = "content fingerprint '{$expect['contains']}' missing";
            }
            if ($expect['title_contains'] !== null
                && !str_contains((string) $content->title, $expect['title_contains'])) {
                $misses[] = "title '{$content->title}' lacks '{$expect['title_contains']}'";
            }

            $json = $serializer->success($content, $expect['url'], new \DateTimeImmutable('2026-06-10T12:00:00+00:00'));
            if (!$validator->validate(json_decode($json), $schema)->isValid()) {
                $misses[] = 'output violates the success schema';
            }

            if ($misses !== []) {
                $regressions[] = "{$name}: " . implode('; ', $misses);
            }
        }

        self::assertSame(
            [],
            $regressions,
            "Corpus regressions detected:\n - " . implode("\n - ", $regressions),
        );
    }
}
