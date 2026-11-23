<?php

declare(strict_types=1);

namespace App\Licences\Regulators;

/** The `regulator` block of a register entry (B10.3): the public files REOS imports nightly. */
final readonly class RegulatorDefinition
{
    /**
     * @param  'TREC'|'TDI'  $authority
     * @param  list<array{kind: 'agents'|'inspectors'|'insurance', url: string}>  $files
     */
    public function __construct(
        public string $authority,
        public array $files,
    ) {}

    /** @param  array<mixed>  $block */
    public static function from(array $block): ?self
    {
        $authority = $block['authority'] ?? null;
        $files = [];

        foreach (is_array($block['files'] ?? null) ? $block['files'] : [] as $file) {
            $kind = is_array($file) ? ($file['kind'] ?? null) : null;
            $url = is_array($file) ? ($file['url'] ?? null) : null;

            if (in_array($kind, ['agents', 'inspectors', 'insurance'], true) && is_string($url)) {
                $files[] = ['kind' => $kind, 'url' => $url];
            }
        }

        return in_array($authority, ['TREC', 'TDI'], true) && $files !== [] ? new self($authority, $files) : null;
    }
}
