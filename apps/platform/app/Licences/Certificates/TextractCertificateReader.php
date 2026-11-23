<?php

declare(strict_types=1);

namespace App\Licences\Certificates;

use Aws\Exception\AwsException;
use Aws\Textract\TextractClient;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * AWS Textract (B10.3b): `AnalyzeDocument` with queries, one per field,
 * on the image or one-page PDF as bytes (nothing is stored at AWS). Answers
 * below 50% confidence are left for the person to fill in.
 */
final readonly class TextractCertificateReader implements CertificateReader
{
    public const MIN_CONFIDENCE = 50.0;

    /** Field => the question Textract answers from the page. */
    public const QUERIES = [
        'name' => "What is the student's or attendee's full name?",
        'course' => 'What is the course title?',
        'hours' => 'How many credit hours or clock hours were completed?',
        'completed_on' => 'What is the completion date?',
        'provider' => 'Who is the education provider or school?',
    ];

    public function __construct(private TextractClient $client) {}

    public function id(): string
    {
        return 'textract';
    }

    public function read(string $bytes, string $mime, ?string $accountName): CertificateFields
    {
        try {
            $result = $this->client->analyzeDocument([
                'Document' => ['Bytes' => $bytes],
                'FeatureTypes' => ['QUERIES'],
                'QueriesConfig' => ['Queries' => array_map(
                    static fn (string $alias, string $text): array => ['Text' => $text, 'Alias' => $alias],
                    array_keys(self::QUERIES),
                    array_values(self::QUERIES),
                )],
            ]);
        } catch (AwsException $e) {
            throw new CertificateUnreadable('Textract: '.$e->getAwsErrorCode(), 0, $e);
        }

        $blocks = $result['Blocks'] ?? [];
        $byId = [];
        $answers = [];

        foreach (is_iterable($blocks) ? $blocks : [] as $block) {
            if (is_array($block) && is_string($block['Id'] ?? null)) {
                $byId[$block['Id']] = $block;
            }
        }

        foreach ($byId as $block) {
            if (($block['BlockType'] ?? null) !== 'QUERY' || ! is_array($block['Query'] ?? null)) {
                continue;
            }

            $alias = $block['Query']['Alias'] ?? null;

            foreach (is_array($block['Relationships'] ?? null) ? $block['Relationships'] : [] as $relation) {
                if (! is_array($relation) || ($relation['Type'] ?? null) !== 'ANSWER') {
                    continue;
                }

                foreach (is_array($relation['Ids'] ?? null) ? $relation['Ids'] : [] as $id) {
                    $answer = is_string($id) ? ($byId[$id] ?? null) : null;
                    $confidence = is_numeric($answer['Confidence'] ?? null) ? (float) $answer['Confidence'] : 0.0;
                    $text = is_string($answer['Text'] ?? null) ? trim($answer['Text']) : '';

                    if (is_string($alias) && $text !== '' && $confidence >= self::MIN_CONFIDENCE && $confidence > ($answers[$alias][1] ?? -1.0)) {
                        $answers[$alias] = [$text, round($confidence, 1)];
                    }
                }
            }
        }

        $text = static fn (string $key): ?string => isset($answers[$key]) ? mb_substr($answers[$key][0], 0, 200) : null;

        return new CertificateFields(
            name: $text('name'),
            course: $text('course'),
            hours: self::hours($text('hours')),
            completedOn: self::date($text('completed_on')),
            provider: $text('provider'),
            confidence: array_map(static fn (array $a): float => $a[1], $answers),
        );
    }

    /** "4.0 hours", "4 CE hours", "Four (4)": the number. */
    private static function hours(?string $value): ?float
    {
        if ($value === null || preg_match('/(\d+(?:\.\d+)?)/', $value, $m) !== 1) {
            return null;
        }

        $hours = (float) $m[1];

        return $hours > 0 && $hours <= 60 ? round($hours, 1) : null;
    }

    private static function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $date->year >= 1990 && $date->lte(CarbonImmutable::now()->addDay()) ? $date->toDateString() : null;
    }
}
